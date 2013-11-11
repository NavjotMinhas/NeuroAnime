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
$subobj = new vB_PaidSubscription($vbulletin);
$subobj->cache_user_subscriptions();

if (is_array($subobj->subscriptioncache))
{
	foreach ($subobj->subscriptioncache as $key => $subscription)
	{
		// disable people :)
		$subscribers = $vbulletin->db->query_read("
			SELECT userid
			FROM " . TABLE_PREFIX . "subscriptionlog
			WHERE subscriptionid = $subscription[subscriptionid]
				AND expirydate <= " . TIMENOW . "
				AND status = 1
		");

		while ($subscriber = $vbulletin->db->fetch_array($subscribers))
		{
			$subobj->delete_user_subscription($subscription['subscriptionid'], $subscriber['userid'], -1, true);
		}
	}

	// time for the reminders
	$subscriptions_reminders = $vbulletin->db->query_read("
		SELECT subscriptionlog.subscriptionid, subscriptionlog.userid, subscriptionlog.expirydate, user.username, user.email, user.languageid
		FROM " . TABLE_PREFIX . "subscriptionlog AS subscriptionlog
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = subscriptionlog.userid)
		WHERE subscriptionlog.expirydate >= " . (TIMENOW + (86400 * 2)) . "
			AND subscriptionlog.expirydate <= " . (TIMENOW + (86400 * 3)) . "
			AND status = 1
	");

	vbmail_start();
	while ($subscriptions_reminder = $vbulletin->db->fetch_array($subscriptions_reminders))
	{
		require_once(DIR . '/includes/functions_misc.php');
		$subscription_title = fetch_phrase('sub' . $subscriptions_reminder['subscriptionid'] . '_title', 'subscription', '', true, true, $subscriptions_reminder['languageid']);

		$username = unhtmlspecialchars($subscriptions_reminder['username']);
		eval(fetch_email_phrases('paidsubscription_reminder', $subscriptions_reminder['languageid']));
		vbmail($subscriptions_reminder['email'], $subject, $message);
	}
	vbmail_end();

	($hook = vBulletinHook::fetch_hook('cron_script_subscriptions')) ? eval($hook) : false;
}

log_cron_action('', $nextitem, 1);
/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>