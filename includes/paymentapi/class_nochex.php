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

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

/**
* Class that provides payment verification and form generation functions
*
* @package	vBulletin
* @version	$Revision: 36023 $
* @date		$Date: 2010-03-30 13:16:08 -0700 (Tue, 30 Mar 2010) $
*/
class vB_PaidSubscriptionMethod_nochex extends vB_PaidSubscriptionMethod
{
	/**
	* The variable indicating if this payment provider supports recurring transactions
	*
	* @var	bool
	*/
	var $supports_recurring = false;

	/**
	* Perform verification of the payment, this is called from the payment gateway
	*
	* @return	bool	Whether the payment is valid
	*/
	function verify_payment()
	{
		// Leave these values at TYPE_STR since they need to be sent back to nochex just as they were received
		$this->registry->input->clean_array_gpc('p', array(
			'order_id'       => TYPE_STR,
			'amount'         => TYPE_STR,
			'transaction_id' => TYPE_STR,
			'status'         => TYPE_STR
		));

		$this->transaction_id = $this->registry->GPC['transaction_id'];

		foreach($_POST AS $key => $val)
		{
			if (!empty($val))
			{
				$query[] = $key . '=' . urlencode($val);
			}
		}
		$query = implode('&', $query);

		$used_curl = false;

		if (function_exists('curl_init') AND $ch = curl_init())
		{
			curl_setopt($ch, CURLOPT_URL, 'http://www.nochex.com/nochex.dll/apc/apc');
			curl_setopt($ch, CURLOPT_TIMEOUT, 15);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_USERAGENT, 'vBulletin via cURL/PHP');

			$result = curl_exec($ch);
			curl_close($ch);
			if ($result !== false)
			{
				$used_curl = true;
			}
		}

		if (!$used_curl)
		{
			$header = "POST /nochex.dll/apc/apc HTTP/1.0\r\n";
			$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
			$header .= "Content-Length: " . strlen($query) . "\r\n\r\n";
			if ($fp = fsockopen('www.nochex.com', 80, $errno, $errstr, 15))
			{
				socket_set_timeout($fp, 15);
				fwrite($fp, $header . $query);
				while (!feof($fp))
				{
					$result = fgets($fp, 1024);
					if (strcmp($result, 'AUTHORISED') == 0)
					{
						break;
					}
				}
				fclose($fp);
			}
		}

		if (!empty($this->settings['ncxemail']) AND $result == 'AUTHORISED' AND $vbulletin->GPC['status'] != 'test')
		{
			$this->paymentinfo = $this->registry->db->query_first("
				SELECT paymentinfo.*, user.username
				FROM " . TABLE_PREFIX . "paymentinfo AS paymentinfo
				INNER JOIN " . TABLE_PREFIX . "user AS user USING (userid)
				WHERE hash = '" . $this->registry->db->escape_string($this->registry->GPC['order_id']) . "'
			");
			// lets check the values
			if (!empty($this->paymentinfo))
			{
				$sub = $this->registry->db->query_first("SELECT * FROM " . TABLE_PREFIX . "subscription WHERE subscriptionid = " . $this->paymentinfo['subscriptionid']);
				$cost = unserialize($sub['cost']);

				$this->paymentinfo['currency'] = 'gbp';
				$this->paymentinfo['amount'] = floatval($this->registry->GPC['amount']);
				// Check if its a payment or if its a reversal
				if ($this->registry->GPC['amount'] == $cost["{$this->paymentinfo[subscriptionsubid]}"]['cost']['gbp'])
				{
					$this->type = 1;
				}
			}

			return true;
		}
		else
		{
			$this->error = 'Invalid Request';
		}
		return false;
	}

	/**
	* Test that required settings are available, and if we can communicate with the server (if required)
	*
	* @return	bool	If the vBulletin has all the information required to accept payments
	*/
	function test()
	{
		$communication = false;

		if (function_exists('curl_init') AND $ch = curl_init())
		{
			curl_setopt($ch, CURLOPT_URL, 'http://www.nochex.com/nochex.dll/apc/apc');
			curl_setopt($ch, CURLOPT_TIMEOUT, 15);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

			$result = curl_exec($ch);
			curl_close($ch);
			if ($result !== false)
			{
				$communication = true;
			}
		}

		if (!$communication)
		{
			$header = "POST /nochex.dll/apc/apc HTTP/1.0\r\n";
			$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
			$header .= "Content-Length: " . strlen($query) . "\r\n\r\n";
			if ($fp = fsockopen('www.nochex.com', 80, $errno, $errstr, 15))
			{
				socket_set_timeout($fp, 15);
				fwrite($fp, $header . $query);
				while (!feof($fp))
				{
					$result = fgets($fp, 1024);
					if (strcmp($result, 'DECLINED') == 0)
					{
						$communication = true;
						break;
					}
				}
				fclose($fp);
			}
		}

		return (!empty($this->settings['ncxemail']) AND $communication);
	}

	/**
	* Generates HTML for the subscription form page
	*
	* @param	string		Hash used to indicate the transaction within vBulletin
	* @param	string		The cost of this payment
	* @param	string		The currency of this payment
	* @param	array		Information regarding the subscription that is being purchased
	* @param	array		Information about the user who is purchasing this subscription
	* @param	array		Array containing specific data about the cost and time for the specific subscription period
	*
	* @return	array		Compiled form information
	*/
	function generate_form_html($hash, $cost, $currency, $subinfo, $userinfo, $timeinfo)
	{
		global $vbphrase, $vbulletin, $show;

		$item = $hash;
		$currency = strtoupper($currency);

		$form['action'] = 'https://secure.nochex.com/';
		$form['method'] = 'post';

		// load settings into array so the template system can access them
		$settings = $this->settings;

		$templater = vB_Template::create('subscription_payment_nochex');
			$templater->register('cost', $cost);
			$templater->register('item', $item);
			$templater->register('settings', $settings);
		$form['hiddenfields'] .= $templater->render();
		return $form;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 36023 $
|| ####################################################################
\*======================================================================*/
?>