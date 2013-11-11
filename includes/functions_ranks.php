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

error_reporting(E_ALL & ~E_NOTICE);

// #################### Fetch User's Rank ################
function &fetch_rank(&$userinfo)
{
	global $vbulletin;

	if (!is_array($vbulletin->ranks))
	{
		// grab ranks since we didn't include 'ranks' in $specialtemplates
		$vbulletin->ranks =& build_ranks();
	}

	$doneusergroup = array();
	$userrank = '';

	foreach ($vbulletin->ranks AS $rank)
	{
		$displaygroupid = empty($userinfo['displaygroupid']) ? $userinfo['usergroupid'] : $userinfo['displaygroupid'];
		if ($userinfo['posts'] >= $rank['m'] AND (!isset($doneusergroup["$rank[u]"]) OR $doneusergroup["$rank[u]"] === $rank['m'])
		AND
		(($rank['u'] > 0 AND is_member_of($userinfo, $rank['u'], false) AND (empty($rank['d']) OR $rank['u'] == $displaygroupid))
		OR
		($rank['u'] == 0 AND (empty($rank['d']) OR empty($userrank)))))
		{
			if (!empty($userrank) AND $rank['s'])
			{
				$userrank .= '<br />';
			}
			$doneusergroup["$rank[u]"] = $rank['m'];
			for ($x = $rank['l']; $x--; $x > 0)
			{
				if (empty($rank['t']))
				{
					$userrank .= "<img src=\"$rank[i]\" alt=\"\" border=\"\" />";
				}
				else
				{
					$userrank .= $rank['i'];
				}
			}
		}
	}

	return $userrank;
}

// #################### Begin Build Ranks PHP Code function ################
function &build_ranks()
{
	global $vbulletin;

	$ranks = $vbulletin->db->query_read_slave("
		SELECT ranklevel AS l, minposts AS m, rankimg AS i, type AS t, stack AS s, display AS d, ranks.usergroupid AS u
		FROM " . TABLE_PREFIX . "ranks AS ranks
		LEFT JOIN " . TABLE_PREFIX . "usergroup AS usergroup USING (usergroupid)
		ORDER BY ranks.usergroupid DESC, minposts DESC
	");

	$rankarray = array();
	while ($rank = $vbulletin->db->fetch_array($ranks))
	{
		$rankarray[] = $rank;
	}

	build_datastore('ranks', serialize($rankarray), 1);

	return $rankarray;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>