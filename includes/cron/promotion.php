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

// $nextrun is the time difference between runs. Should be sent over from cron.php!!
// We only check the users that have been active since the lastrun to save a bit of cpu time.

$promotions = $vbulletin->db->query_read("
	SELECT user.joindate, user.userid, user.membergroupids, user.posts, user.reputation,
		user.usergroupid, user.displaygroupid, user.customtitle, user.username, user.ipoints,
		userpromotion.joinusergroupid, userpromotion.reputation AS jumpreputation, userpromotion.posts AS jumpposts,
		userpromotion.date AS jumpdate, userpromotion.type, userpromotion.strategy,
		usergroup.title, usergroup.usertitle AS ug_usertitle,
		usertextfield.rank
	FROM " . TABLE_PREFIX . "user AS user
	INNER JOIN " . TABLE_PREFIX . "userpromotion AS userpromotion ON (user.usergroupid = userpromotion.usergroupid)
	LEFT JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON (userpromotion.joinusergroupid = usergroup.usergroupid)
	LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON (usertextfield.userid = user.userid)
	" . iif(VB_AREA != 'AdminCP', "WHERE user.lastactivity >= " . (TIMENOW - ($nextrun - TIMENOW)))
);

$usertitlecache = array();
$usertitles = $vbulletin->db->query_read("SELECT minposts, title FROM " . TABLE_PREFIX . "usertitle ORDER BY minposts ASC");
while ($usertitle = $vbulletin->db->fetch_array($usertitles))
{
	$usertitlecache["$usertitle[minposts]"] = $usertitle['title'];
}

$primaryupdates = array();
$secondaryupdates = array();
$userupdates = array();
$primarynames = array();
$secondarynames = array();
$titles = array();

while ($promotion = $vbulletin->db->fetch_array($promotions))
{
	// First make sure user isn't already a member of the group we are joining
	if ((strpos(",$promotion[membergroupids],", ",$promotion[joinusergroupid],") === false AND $promotion['type'] == 2) OR ($promotion['usergroupid'] != $promotion['joinusergroupid'] AND $promotion['type'] == 1))
	{
		$daysregged = intval((TIMENOW - $promotion['joindate']) / 86400);
		$joinusergroupid = $promotion['joinusergroupid'];
		$titles["$joinusergroupid"] = $promotion['title'];
		$dojoin = false;
		$reputation = false;
		$posts = false;
		$joindate = false;
		// These strategies are negative reputation checking
		if (($promotion['strategy'] > 7 AND $promotion['strategy'] < 16) OR $promotion['strategy'] == 24)
		{
			if ($promotion['reputation'] < $promotion['jumpreputation'])
			{
				$reputation = true;
			}
		}
		else if ($promotion['reputation'] >= $promotion['jumpreputation'])
		{
			$reputation = true;
		}

		if ($promotion['posts'] >= $promotion['jumpposts'])
		{
			$posts = true;
		}
		if ($daysregged >= $promotion['jumpdate'])
		{
			$joindate = true;
		}

		if ($promotion['strategy'] == 17)
		{
			$dojoin = iif($posts, true, false);
		}
		else if ($promotion['strategy'] == 18)
		{
			$dojoin = iif($joindate, true, false);
		}
		else if ($promotion['strategy'] == 16 OR $promotion['strategy'] == 24)
		{
			$dojoin = iif($reputation, true, false);
		}
		else
		{
			switch($promotion['strategy'])
			{
				case 0:
				case 8:
					if ($posts AND $reputation AND $joindate)
					{
						$dojoin = true;
					}
					break;
				case 1:
				case 9:
					if ($posts OR $reputation OR $joindate)
					{
						$dojoin = true;
					}
					break;
				case 2:
				case 10:
					if (($posts AND $reputation) OR $joindate)
					{
						$dojoin = true;
					}
					break;
				case 3:
				case 11:
					if ($posts AND ($reputation OR $joindate))
					{
						$dojoin = true;
					}
					break;
				case 4:
				case 12:
					if (($posts OR $reputation) AND $joindate)
					{
						$dojoin = true;
					}
					break;
				case 5:
				case 13:
					if ($posts OR ($reputation AND $joindate))
					{
						$dojoin = true;
					}
					break;
				case 6:
				case 14:
					if ($reputation AND ($posts OR $joindate))
					{
						$dojoin = true;
					}
					break;
				case 7:
				case 15:
					if ($reputation OR ($posts AND $joindate))
					{
						$dojoin = true;
					}
			}
		}

		if ($dojoin)
		{
			$user = $promotion;

			if ($promotion['type'] == 1) // Primary
			{
				$primaryupdates["$joinusergroupid"] .= ",$promotion[userid]";
				$primarynames["$joinusergroupid"] .= iif($primarynames["$joinusergroupid"], ", $promotion[username]", $promotion['username']);

				if (
					(!$promotion['displaygroupid'] OR $promotion['displaygroupid'] == $promotion['usergroupid']) AND
					!$promotion['customtitle']
					)
				{
					if ($promotion['ug_usertitle'])
					{
						// update title if the user (doesn't have a special display group or if their display group is their primary group)
						// and he doesn't have a custom title already, and the new usergroup has a custom title
						$userupdates["$promotion[userid]"]['title'] = $promotion['ug_usertitle'];
					}
					else
					{ // need to use default thats specified for X posts.
						foreach ($usertitlecache AS $minposts => $title)
						{
							if ($minposts <= $promotion['posts'])
							{
								$userupdates["$promotion[userid]"]['title'] = $title;
							}
							else
							{
								break;
							}
						}
					}
				}

				$user['displaygroupid'] = ($user['displaygroupid'] == $user['usergroupid']) ? $joinusergroupid : $user['displaygroupid'];
				$user['usergroupid'] = $joinusergroupid;
			}
			else
			{
				$secondaryupdates["$joinusergroupid"] .= ",$promotion[userid]";
				$secondarynames["$joinusergroupid"] .= iif($secondarynames["$joinusergroupid"], ", $promotion[username]", $promotion['username']);
				$user['membergroupids'] .= (($user['membergroupids'] != '') ? ',' : '') . $joinusergroupid;
			}

			require_once(DIR . '/includes/functions_ranks.php');
			$userrank =& fetch_rank($user);
			if ($promotion['rank'] != $userrank)
			{
				$userupdates["$promotion[userid]"]['rank'] = $userrank;
			}

		}
	}
}

if (!empty($primaryupdates))
{
	$infractiongroupids = array();
	$groups = $vbulletin->db->query_read("
		SELECT usergroupid, orusergroupid, pointlevel, override
		FROM " . TABLE_PREFIX . "infractiongroup AS infractiongroup
		WHERE usergroupid IN (-1," . implode(',', array_keys($primaryupdates)) . ")
		ORDER BY pointlevel
	");
	while ($group = $vbulletin->db->fetch_array($groups))
	{
		$infractiongroupids["$group[usergroupid]"]["$group[pointlevel]"] =
			array(
				'orusergroupid' => $group['orusergroupid'],
				'override'      => $group['override'],
			);
	}

	foreach (array_keys($primaryupdates) AS $usergroupid)
	{
		if (empty($infractiongroupids["$usergroupid"]))
		{
			$infractiongroupids["$usergroupid"] = array();
		}
	}

	$groupids = array();
	if (!empty($infractiongroupids['-1']))
	{
		foreach ($infractiongroupids AS $usergroupid => $pointlevel)
		{
			$infractiongroupids["$usergroupid"] += $infractiongroupids['-1'];
			ksort($infractiongroupids["$usergroupid"]);
		}
		unset($infractiongroupids['-1']);
	}

	foreach ($infractiongroupids AS $usergroupid => $pointlevel)
	{
		$ids = '';
		$infractiongroupid = 0;
		foreach ($pointlevel AS $points => $infractioninfo)
		{
			if ($infractioninfo['override'])
			{
				$infractiongroupid = $infractioninfo['orusergroupid'];
			}
			$ids .= (!empty($ids) ? ',' : '') . $infractioninfo['orusergroupid'];
			$groupids["$usergroupid"]["$points"]['ids'] = $ids;
			$groupids["$usergroupid"]["$points"]['id'] = $infractiongroupid;
		}
		if (!empty($groupids["$usergroupid"]))
		{
			krsort($groupids["$usergroupid"]);
		}
	}
	unset($infractiongroupid, $infractiongroupids, $ids);

	$sql = array();
	$sql_id = array();
	foreach($groupids AS $usergroupid => $pointlevel)
	{
		foreach ($pointlevel AS $points => $info)
		{
			$sql["$usergroupid"][] = "WHEN ipoints >= $points THEN '$info[ids]'";
			$sql_id["$usergroupid"][] = "WHEN ipoints >= $points THEN $info[id]";
		}
	}
	unset($groupids);
}

if ($vbulletin->debug AND VB_AREA == 'AdminCP')
{
	#echo '<pre>'; print_r($sql); print_r($sql_id); echo '</pre>';
}

foreach ($primaryupdates AS $joinusergroupid => $ids)
{
	$vbulletin->db->query_write("
		UPDATE " . TABLE_PREFIX . "user
		SET displaygroupid = IF(displaygroupid = usergroupid, $joinusergroupid, displaygroupid),
		usergroupid = $joinusergroupid,

		infractiongroupid =
		" . (!empty($sql_id["$joinusergroupid"]) ? "
		CASE
			" . implode(" \r\n", $sql_id["$joinusergroupid"]) . "
		ELSE 0
		END" : "0") . "

		,infractiongroupids =
		" . (!empty($sql["$joinusergroupid"]) ? "
		CASE
			" . implode(" \r\n", $sql["$joinusergroupid"]) . "
		ELSE ''
		END" : "''") . "

		WHERE userid IN (0$ids)
	");
	$log = array(
		$titles["$joinusergroupid"],
		'*',
		$primarynames["$joinusergroupid"]
	);
	// the "1" indicates to use the second line of the phrase specified for this task
	log_cron_action(serialize($log), $nextitem, 1);
}

foreach ($userupdates AS $userid => $info)
{
	$userdm =& datamanager_init('User', $vbulletin, ERRTYPE_SILENT);
	$user = array('userid' => $userid);
	$userdm->set_existing($user);

	if ($info['title'])
	{
		$userdm->set('usertitle', $info['title']);
	}

	if ($info['rank'])
	{
		$userdm->setr('rank', $info['rank']);
	}

	$userdm->save();
	unset($userdm);
}

foreach ($secondaryupdates AS $joinusergroupid => $ids)
{
	$vbulletin->db->query_write("
		UPDATE " . TABLE_PREFIX . "user
		SET membergroupids = IF(membergroupids= '', '$joinusergroupid', CONCAT(membergroupids, ',$joinusergroupid'))
		WHERE userid IN (0$ids)
	");
	$log = array(
		$titles["$joinusergroupid"],
		'%',
		$secondarynames["$joinusergroupid"]
	);
	log_cron_action(serialize($log), $nextitem, 1);
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>