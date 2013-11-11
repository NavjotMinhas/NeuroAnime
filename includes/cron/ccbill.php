<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.1.5 Patch Level 1 - Licence Number VBF1F15E74
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2011 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);
if (!is_object($vbulletin->db))
{
	exit;
}

// ########################## REQUIRE BACK-END ############################
require_once(DIR . '/includes/class_paid_subscription.php');

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

$api = $vbulletin->db->query_first("SELECT * FROM " . TABLE_PREFIX . "paymentapi WHERE classname = 'ccbill'");

$subobj = new vB_PaidSubscription($vbulletin);
$settings = $subobj->construct_payment_settings($api['settings']);

if (!$api['active'] OR !$settings['clientAccnum'] OR !$settings['clientAccnum'] OR !$settings['username'] OR !$settings['password'])
{
	exit;
}

$args = array(
	'startTime'        => date('YmdHis', TIMENOW - 86400),
	'endTime'          => date('YmdHis', TIMENOW),
	'transactionTypes' => 'REFUND,VOID,CHARGEBACK',
	'clientAccnum'     => $settings['clientAccnum'],
	'clientSubacc'     => $settings['clientSubacc'],
	'username'         => $settings['username'],
	'password'         => $settings['password'],
#	'testMode'         => 1,
);

$params = '';
$result = '';
if (function_exists('curl_init') AND $ch = curl_init())
{
	$params = '';
	foreach($args AS $key => $value)
	{
		$params .= "$key=$value&";
	}

	curl_setopt($ch, CURLOPT_URL, 'https://datalink.ccbill.com/data/main.cgi');
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
	curl_setopt($ch, CURLOPT_TIMEOUT, 15);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_USERAGENT, 'vBulletin via cURL/PHP');

	$result = curl_exec($ch);
	if ($result === false AND curl_errno($ch) == '60') ## CURLE_SSL_CACERT problem with the CA cert (path? access rights?)
	{
		curl_setopt($ch, CURLOPT_CAINFO, DIR . '/includes/paymentapi/ca-bundle.crt');
		$result = curl_exec($ch);
	}

	if ($result === false)
	{
		echo 'CURL Failed<pre>' . curl_error($ch) . '</pre>';
	}
	else
	{
		$used_curl = true;
	}
	curl_close($ch);
}

if (!$used_curl AND function_exists('openssl_open'))
{
	if ($fp = fsockopen('ssl://datalink.ccbill.com', 443, $errno, $errstr, 15))
	{
		stream_set_timeout($fp, 15);

		$params = 'GET /data/main.cgi?';
		foreach($args AS $key => $value)
		{
			$params .= "$key=$value&";
		}

		$params .= " HTTP/1.0\r\n";
		$params .= "Host: datalink.ccbill.com\r\n";
		$params .= "User-Agent: PHP via fsockopen\r\n";
		$params .= "Connection: close\r\n\r\n";

		fwrite($fp, $params, strlen($params));

		while (!feof($fp))
		{
			$results = fgets($fp);
			if (preg_match('#^("|Error:)#', $results))
			{
				$result .= $results;
			}
		}
		fclose($fp);
	}
	else if (VB_AREA == 'AdminCP')
	{
		echo htmlspecialchars_uni("$errstr ($errno)");
	}
}

// Example Results
/*
$result =
'"REFUND","931045","0005","2000000001","20041201105542","1.99"
"REFUND","931045","0005","2000000002","20041201100542","4.32"
"REFUND","931045","0005","2000000003","20041201105542","2.90"
"VOID","931045","0005","2000000001","","1.99"
"VOID","931045","0005","2000000002","","4.32"
"VOID","931045","0005","2000000003","","2.90"
"CHARGEBACK","931045","0005","2000000001","20041201105542","1.99"
"CHARGEBACK","931045","0005","2000000002","20041201100542","4.32"
"CHARGEBACK","931045","0005","2000000003","20041201105542","2.90"
"CHARGEBACK","931045","0005","2000867333","20041201105542","2.90"';

$result = 'Error: Authentication failed.714';
*/

if ($vbulletin->debug AND VB_AREA == 'AdminCP')
{
	echo "<pre>$params</pre>";
	if ($result)
	{
		echo "<pre>$result</pre>";
	}
}

$log = '';
$count = 0;
if ($result)
{
	if (!preg_match('#^Error:#', $result))
	{
		$result = str_replace('"', '', $result);

		$ids = array();
		$trans = explode("\n", $result);

		foreach($trans AS $value)
		{
			$options = explode(',', $value);
			if (!empty($options[3]))
			{
				$ids[] = $vbulletin->db->escape_string($options[3]);
			}
		}

		if (!empty($ids))
		{

			$insert = array();
			$updatetrans = array();
			$subs = $vbulletin->db->query_read("
				SELECT paymentinfo.subscriptionsubid, subscription.subscriptionid, subscription.cost,
					paymentinfo.userid, paymentinfo.paymentinfoid, paymenttransaction.amount, paymenttransaction.transactionid,
					paymenttransaction.paymenttransactionid
				FROM " . TABLE_PREFIX . "paymenttransaction AS paymenttransaction
				INNER JOIN " . TABLE_PREFIX . "paymentinfo AS paymentinfo ON (paymentinfo.paymentinfoid = paymenttransaction.paymentinfoid)
				INNER JOIN " . TABLE_PREFIX . "subscription AS subscription ON (paymentinfo.subscriptionid = subscription.subscriptionid)
				INNER JOIN " . TABLE_PREFIX . "subscriptionlog AS subscriptionlog ON (subscriptionlog.subscriptionid = subscription.subscriptionid AND subscriptionlog.userid = paymentinfo.userid)
				WHERE transactionid IN ('" . implode("','", $ids) . "')
					AND subscriptionlog.status = 1
					AND paymenttransaction.reversed = 0
			");
			while ($sub = $vbulletin->db->fetch_array($subs))
			{
				$subobj->delete_user_subscription($sub['subscriptionid'], $sub['userid'], $sub['subscriptionsubid']);
				$insert[] = "2, " . TIMENOW . ", 'usd', $sub[amount], '" . $vbulletin->db->escape_string($sub['transactionid'] . 'R') . "', $sub[paymentinfoid], $api[paymentapiid]";
				$updatetrans[] = $sub['paymenttransactionid'];
				$count++;
			}

			if (!empty($insert))
			{
				$vbulletin->db->query_write("
					INSERT INTO " . TABLE_PREFIX . "paymenttransaction
					(state, dateline, currency, amount, transactionid, paymentinfoid, paymentapiid)
					VALUES
					(" . implode('),(', $insert) . ")
				");

				$vbulletin->db->query_write("
					UPDATE " . TABLE_PREFIX . "paymenttransaction
					SET reversed = 1
					WHERE paymenttransactionid IN (" . implode(', ', $updatetrans) . ")
				");
			}
		}
		$log = $count;
	}
	else
	{	// Error
		$log = htmlspecialchars_uni($result);
	}
}

log_cron_action($log, $nextitem, 1);


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>