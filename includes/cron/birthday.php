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

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

$today = date('m-d', TIMENOW);

$ids = '0';
foreach($vbulletin->usergroupcache AS $usergroupid => $usergroup)
{
	if ($usergroup['genericoptions'] & $vbulletin->bf_ugp_genericoptions['showbirthday'] AND $usergroup['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup'] AND !in_array($usergroup['usergroupid'], array(1, 3, 4)))
	{
		$ids .= ",$usergroupid";
	}
}

$birthdays = $vbulletin->db->query_read("
	SELECT username, email, languageid
	FROM " . TABLE_PREFIX . "user
	WHERE birthday LIKE '$today-%' AND
	(options & " . $vbulletin->bf_misc_useroptions['adminemail'] . ") AND
	usergroupid IN ($ids)
");

vbmail_start();

while ($userinfo = $vbulletin->db->fetch_array($birthdays))
{
	$username = unhtmlspecialchars($userinfo['username']);
	eval(fetch_email_phrases('birthday', $userinfo['languageid']));
	vbmail($userinfo['email'], $subject, $message);
	$emails .= iif($emails, ', ');
	$emails .= $userinfo['username'];
}

vbmail_end();

if ($emails)
{
	log_cron_action($emails, $nextitem, 1);
}


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>