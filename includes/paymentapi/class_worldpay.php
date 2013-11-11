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
class vB_PaidSubscriptionMethod_worldpay extends vB_PaidSubscriptionMethod
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
		$this->registry->input->clean_array_gpc('r', array(
			'callbackPW'  => TYPE_STR,
			'desc'        => TYPE_STR,
			'transStatus' => TYPE_STR,
			'authMode'    => TYPE_STR,
			'cost'        => TYPE_NUM,
			'currency'    => TYPE_STR,
			'transId'     => TYPE_STR
		));

		if (!$this->test())
		{
			$this->error = 'Payment processor not configured';
			return false;
		}

		$this->transaction_id = $this->registry->GPC['transId'];

		if ($this->registry->GPC['callbackPW'] == $this->settings['worldpay_password'])
		{
			$this->paymentinfo = $this->registry->db->query_first("
				SELECT paymentinfo.*, user.username
				FROM " . TABLE_PREFIX . "paymentinfo AS paymentinfo
				INNER JOIN " . TABLE_PREFIX . "user AS user USING (userid)
				WHERE hash = '" . $this->registry->db->escape_string($this->registry->GPC['desc']) . "'
			");
			// lets check the values
			if (!empty($this->paymentinfo))
			{
				$sub = $this->registry->db->query_first("SELECT * FROM " . TABLE_PREFIX . "subscription WHERE subscriptionid = " . $this->paymentinfo['subscriptionid']);
				$cost = unserialize($sub['cost']);
				$this->paymentinfo['currency'] = strtolower($this->registry->GPC['currency']);
				$this->paymentinfo['amount'] = floatval($this->registry->GPC['cost']);
				if ($this->registry->GPC['transStatus'] == 'Y' AND ($this->registry->GPC['authMode'] == 'A' OR $this->registry->GPC['authMode'] == 'O'))
				{
					if (doubleval($this->registry->GPC['cost']) == doubleval($cost["{$this->paymentinfo[subscriptionsubid]}"]['cost'][strtolower($this->registry->GPC['currency'])]))
					{
						$this->type = 1;
					}
				}
				return true;
			}
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
		return (!empty($this->settings['worldpay_instid']) AND !empty($this->settings['worldpay_password']));
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

		$form['action'] = 'https://select.worldpay.com/wcc/purchase';
		$form['method'] = 'post';

		// load settings into array so the template system can access them
		$settings =& $this->settings;

		$templater = vB_Template::create('subscription_payment_worldpay');
			$templater->register('cost', $cost);
			$templater->register('currency', $currency);
			$templater->register('item', $item);
			$templater->register('settings', $settings);
			$templater->register('title', $title);
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