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

// select all banned users who are due to have their ban lifted
$bannedusers = $vbulletin->db->query_read("
	SELECT user.*,
		userban.usergroupid AS banusergroupid, userban.displaygroupid AS bandisplaygroupid, userban.customtitle AS bancustomtitle, userban.usertitle AS banusertitle
	FROM " . TABLE_PREFIX . "userban AS userban
	INNER JOIN " . TABLE_PREFIX . "user AS user USING(userid)
	WHERE liftdate <> 0 AND liftdate < " . TIMENOW . "
");

// do we have some results?
if ($vbulletin->db->num_rows($bannedusers))
{
	// some users need to have their bans lifted
	$userids = array();
	while ($banneduser = $vbulletin->db->fetch_array($bannedusers))
	{
		// get usergroup info
		$getusergroupid = iif($banneduser['bandisplaygroupid'], $banneduser['bandisplaygroupid'], $banneduser['banusergroupid']);
		$usergroup = $vbulletin->usergroupcache["$getusergroupid"];
		if ($banneduser['bancustomtitle'])
		{
			$usertitle = $banneduser['banusertitle'];
		}
		else if (!$usergroup['usertitle'])
		{
			$gettitle = $vbulletin->db->query_first("
				SELECT title
				FROM " . TABLE_PREFIX . "usertitle
				WHERE minposts <= " . intval($banneduser['posts']) . "
				ORDER BY minposts DESC
			");
			$usertitle = $gettitle['title'];
		}
		else
		{
			$usertitle = $usergroup['usertitle'];
		}

		// update users to get their old usergroupid/displaygroupid/usertitle back
		$userdm =& datamanager_init('User', $vbulletin, ERRTYPE_SILENT);
		$userdm->set_existing($banneduser);
		$userdm->set('usertitle', $usertitle);
		$userdm->set('usergroupid', $banneduser['banusergroupid']);
		$userdm->set('displaygroupid', $banneduser['bandisplaygroupid']);
		$userdm->set('customtitle', $banneduser['bancustomtitle']);

		$userdm->save();
		unset($userdm);

		$users["$banneduser[userid]"] = $banneduser['username'];
	}

	// delete ban records
	$vbulletin->db->query_write("
		DELETE FROM " . TABLE_PREFIX . "userban
		WHERE userid IN(" . implode(', ', array_keys($users)) . ")
	");

	// log the cron action
	log_cron_action(implode(', ', $users), $nextitem, 1);
}

$vbulletin->db->free_result($bannedusers);

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>