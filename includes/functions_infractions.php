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


/**
 * "Magic" Function that builds all the information regarding infractions
 * (only used in Cron)
 *
 * @param	array	Infraction Points Array
 * @param	array	Infractions Array
 * @param	array	Warnings Array
 *
 * @return	boolean	Whether infractions info was updated.
 *
 */
function build_user_infractions($points, $infractions, $warnings)
{
	global $vbulletin;

	$warningsql = array();
	$infractionsql = array();
	$ipointssql = array();
	$querysql = array();
	$userids = array();

	// ############################ WARNINGS #################################
	$wa = array();
	foreach($warnings AS $userid => $warning)
	{
		$wa["$warning"][] = $userid;
		$userids["$userid"] = $userid;
	}
	unset($warnings);

	foreach($wa AS $warning => $users)
	{
		$warningsql[] = "WHEN userid IN(" . implode(', ', $users) . ") THEN $warning";
	}
	unset($wa);
	if (!empty($warningsql))
	{
		$querysql[] = "
		warnings = CAST(warnings AS SIGNED) -
		CASE
			" . implode(" \r\n", $warningsql) . "
		ELSE 0
		END";
	}
	unset($warningsql);

	// ############################ INFRACTIONS ##############################
	$if = array();
	foreach($infractions AS $userid => $infraction)
	{
		$if["$infraction"][] = $userid;
		$userids["$userid"] = $userid;
	}
	unset($infractions);
	foreach($if AS $infraction => $users)
	{
		$infractionsql[] = "WHEN userid IN(" . implode(', ', $users) . ") THEN $infraction";
	}
	unset($if);
	if (!empty($infractionsql))
	{
		$querysql[] = "
		infractions = CAST(infractions AS SIGNED) -
		CASE
			" . implode(" \r\n", $infractionsql) . "
		ELSE 0
		END";
	}
	unset($infractionsql);

	// ############################ POINTS ###################################
	$ip = array();
	foreach($points AS $userid => $point)
	{
		$ip["$point"][] = $userid;
	}
	unset($points);
	foreach($ip AS $point => $users)
	{
		$ipointssql[] = "WHEN userid IN(" . implode(', ', $users) . ") THEN $point";
	}
	unset($ip);
	if (!empty($ipointssql))
	{
		$querysql[] = "
		ipoints = CAST(ipoints AS SIGNED) -
		CASE
			" . implode(" \r\n", $ipointssql) . "
		ELSE 0
		END";
	}
	unset($ipointssql);

	if (!empty($querysql))
	{
		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "user
			SET " . implode(', ', $querysql) . "
			WHERE userid IN (" . implode(', ', $userids) . ")
		");

		return true;
	}
	else
	{
		return false;
	}
}


/**
 * Builds infraction groups for users
 *
 * @param	array	User IDs to build
 *
 */
function build_infractiongroupids($userids)
{
	global $vbulletin;
	static $infractiongroups = array(), $beenhere;

	if (!$beenhere)
	{
		$beenhere = true;
		$groups = $vbulletin->db->query_read_slave("
			SELECT usergroupid, orusergroupid, pointlevel, override
			FROM " . TABLE_PREFIX . "infractiongroup
			ORDER BY pointlevel
		");
		while ($group = $vbulletin->db->fetch_array($groups))
		{
			$infractiongroups["$group[usergroupid]"]["$group[pointlevel]"][] = array(
				'orusergroupid' => $group['orusergroupid'],
				'override'      => $group['override'],
			);
		}
	}

	$users = $vbulletin->db->query_read("
		SELECT user.*
		FROM " . TABLE_PREFIX . "user AS user
		WHERE userid IN (" . implode(', ', $userids) . ")
	");
	while ($user = $vbulletin->db->fetch_array($users))
	{
		$infractioninfo = fetch_infraction_groups($infractiongroups, $user['userid'], $user['ipoints'], $user['usergroupid']);

		if (($groupids = implode(',', $infractioninfo['infractiongroupids'])) != $user['infractiongroupids'] OR $infractioninfo['infractiongroupid'] != $user['infractiongroupid'])
		{
			$userdata =& datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
			$userdata->set_existing($user);
			$userdata->set('infractiongroupids', $groupids);
			$userdata->set('infractiongroupid', $infractioninfo['infractiongroupid']);
			$userdata->save();
		}
	}
}

/**
* Takes valid data and sets it as part of the data to be saved
*
* @param	array		List of infraction groups
* @param integer  Userid of user
* @param	integer	Infraction Points
* @param interger Usergroupid
*
* @return array	User's final infraction groups
*/
function fetch_infraction_groups(&$infractiongroups, $userid, $ipoints, $usergroupid)
{
	static $cache;

	if (!is_array($data))
	{
		$data = array();
	}

	$infractiongroupids = array();

	if (!empty($infractiongroups["$usergroupid"]))
	{
		foreach($infractiongroups["$usergroupid"] AS $pointlevel => $orusergroupids)
		{
			if ($pointlevel <= $ipoints)
			{
				foreach($orusergroupids AS $infinfo)
				{
					$data['infractiongroupids']["$infinfo[orusergroupid]"] = $infinfo['orusergroupid'];
					if ($infinfo['override'] AND $cache["$userid"]['pointlevel'] <= $pointlevel)
					{
						$cache["$userid"]['pointlevel'] = $pointlevel;
						$cache["$userid"]['infractiongroupid'] = $infinfo['orusergroupid'];
					}
				}
			}
			else
			{
				break;
			}
		}
	}

	if (!is_array($data['infractiongroupids']))
	{
		$data['infractiongroupids'] = array();
	}

	if ($usergroupid != -1)
	{
		$temp = fetch_infraction_groups($infractiongroups, $userid, $ipoints, -1);
		$data['infractiongroupids'] = array_merge($data['infractiongroupids'], $temp['infractiongroupids']);
	}

	if (!is_array($data['infractiongroupids']))
	{
		$data['infractiongroupids'] = array();
	}

	$data['infractiongroupid'] = intval($cache["$userid"]['infractiongroupid']);
	return $data;
}

/**
* Recalculates the members of an infraction group based on changes to it.
* Specifying the (required) override group ID allows removal of users from the group.
* Specifying the point level and applicable group allows addition of users to the group.
*
* @param	integer	Usergroup ID users are placed in
* @param	integer	Point level when this infraction group kicks in
* @param	integer	User group that this infraction group applies to
*/
function check_infraction_group_change($override_groupid, $point_level = null, $applies_groupid = -1)
{
	global $vbulletin;

	$users = array();
	if ($point_level === null)
	{
		$user_sql = $vbulletin->db->query_read("
			SELECT userid
			FROM " . TABLE_PREFIX . "user
			WHERE FIND_IN_SET('" . intval($override_groupid) . "', infractiongroupids)
		");
	}
	else
	{
		$user_sql = $vbulletin->db->query_read("
			SELECT userid
			FROM " . TABLE_PREFIX . "user
			WHERE FIND_IN_SET('" . intval($override_groupid) . "', infractiongroupids)
				OR (ipoints >= " . intval($point_level) . "
					" . ($applies_groupid != -1 ? "AND usergroupid = " . intval($applies_groupid) : '') . "
				)
		");
	}
	while ($user = $vbulletin->db->fetch_array($user_sql))
	{
		$users[] = $user['userid'];
	}

	if ($users)
	{
		build_infractiongroupids($users);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>
