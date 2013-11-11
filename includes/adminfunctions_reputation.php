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

// ###################### Start reputationrecur #######################
function fetch_event_recurrence_sql($reputation)
{
	static $count;
	$count++;

	if ($count == sizeof($reputation))
	{ // last item
		// if we make it to the end than either the reputation is greather than our greatest value or it is less than our least value
		return 'IF (reputation >= ' . $reputation[$count]['value'] . ', ' . $reputation[$count]['index'] . ', ' . $reputation[1]['index'] . ')';
	}
	else
	{
		return 'IF (reputation >= ' . $reputation[$count]['value'] . ' AND reputation < ' . $reputation[($count + 1)]['value'] . ', ' . $reputation[$count]['index']. ',' . fetch_event_recurrence_sql($reputation) . ')';
	}
}

// ###################### Start updatereputationids #######################
function build_reputationids()
{
	global $vbulletin;

	$count = 1;
	$reputations = $vbulletin->db->query_read("
		SELECT reputationlevelid, minimumreputation
		FROM " . TABLE_PREFIX . "reputationlevel
		ORDER BY minimumreputation
	");
	while ($reputation = $vbulletin->db->fetch_array($reputations))
	{
		$ourreputation[$count]['value'] = $reputation['minimumreputation'];
		$ourreputation[$count]['index'] = $reputation['reputationlevelid'];
		$count++;
	}
	if ($count > 1)
	{
		$sql = fetch_event_recurrence_sql($ourreputation);
		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "user
			SET reputationlevelid = $sql
		");

	}
	else
	{
		// it seems we have deleted all of our reputation levels??
		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "user
			SET reputationlevelid = 0
		");
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>