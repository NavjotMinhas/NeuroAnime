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

// ###################### Start fetch_cron_next_run #######################
// gets next run time today after $hour, $minute
// returns -1,-1 if not again today
function fetch_cron_next_run($crondata, $hour = -2, $minute = -2)
{
	if ($hour == -2)
	{
		$hour = intval(date('H', TIMENOW));
	}
	if ($minute == -2)
	{
		$minute = intval(date('i', TIMENOW));
	}

	### REMOVE BEFORE 3.1.0
	### CODE IS HERE FOR THOSE WHO DONT RUN UPGRADE SCRIPTS
	if (is_numeric($crondata['minute']))
	{
		$crondata['minute'] = array(0 => $crondata['minute']);
	}
	else
	{
		$crondata['minute'] = unserialize($crondata['minute']);
	}
	### END REMOVE
	if ($crondata['hour'] == -1 AND $crondata['minute'][0] == -1)
	{
		$newdata['hour'] = $hour;
		$newdata['minute'] = $minute + 1;
	}
	else if ($crondata['hour'] == -1 AND $crondata['minute'][0] != -1)
	{
		$newdata['hour'] = $hour;
		$nextminute = fetch_next_minute($crondata['minute'], $minute);
		if ($nextminute === false)
		{
			++$newdata['hour'];
			$nextminute = $crondata['minute'][0];
		}
		$newdata['minute'] = $nextminute;
	}
	else if ($crondata['hour'] != -1 AND $crondata['minute'][0] == -1)
	{
		if ($crondata['hour'] < $hour)
		{ // too late for today!
			$newdata['hour'] = -1;
			$newdata['minute'] = -1;
		}
		else if ($crondata['hour'] == $hour)
		{ // this hour
			$newdata['hour'] = $crondata['hour'];
			$newdata['minute'] = $minute + 1;
		}
		else
		{ // some time in future, so launch at 0th minute
			$newdata['hour'] = $crondata['hour'];
			$newdata['minute'] = 0;
		}
	}
	else if ($crondata['hour'] != -1 AND $crondata['minute'][0] != -1)
	{
		$nextminute = fetch_next_minute($crondata['minute'], $minute);
		if ($crondata['hour'] < $hour OR ($crondata['hour'] == $hour AND $nextminute === false))
		{ // it's not going to run today so return -1,-1
			$newdata['hour'] = -1;
			$newdata['minute'] = -1;
		}
		else
		{
			// all good!
			$newdata['hour'] = $crondata['hour'];
			$newdata['minute'] = $nextminute;
		}
	}

	return $newdata;
}

// ###################### Start fetch_next_minute #######################
// takes an array of numbers and a number and returns the next highest value in the array
function fetch_next_minute($minutedata, $minute)
{
	foreach ($minutedata AS $nextminute)
	{
		if ($nextminute > $minute)
		{
			return $nextminute;
		}
	}
	return false;
}

// ###################### Start build_cron_item #######################
// updates an entry in the cron table to determine the next run time
function build_cron_item($cronid, $crondata = '')
{
	global $vbulletin;

	if (!is_array($crondata))
	{
		$crondata = $vbulletin->db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "cron
			WHERE cronid = " . intval($cronid)
		);
	}

	$minutenow = intval(date('i', TIMENOW));
	$hournow = intval(date('H', TIMENOW));
	$daynow = intval(date('d', TIMENOW));
	$monthnow = intval(date('m', TIMENOW));
	$yearnow = intval(date('Y', TIMENOW));
	$weekdaynow = intval(date('w', TIMENOW));

	// ok need to work out, date and time of 1st and 2nd next opportunities to run
	if ($crondata['weekday'] == -1)
	{ // any day of week:
		if ($crondata['day'] == -1)
		{ // any day of month:
			$firstday = $daynow;
			$secondday = $daynow + 1;
		}
		else
		{	// specific day of month:
			$firstday = $crondata['day'];
			$secondday = $crondata['day'] + date('t', TIMENOW); // number of days this month
		}
	}
	else
	{ // specific day of week:
		$firstday = $daynow + ($crondata['weekday'] - $weekdaynow);
		$secondday = $firstday + 7;
	}

	if ($firstday < $daynow)
	{
		$firstday = $secondday;
	}

	if ($firstday == $daynow)
	{ // next run is due today?
		$todaytime = fetch_cron_next_run($crondata); // see if possible to run again today
		if ($todaytime['hour'] == -1 AND $todaytime['minute'] == -1)
		{
			// can't run today
			$crondata['day'] = $secondday;

			$newtime = fetch_cron_next_run($crondata, 0, -1);
			$crondata['hour'] = $newtime['hour'];
			$crondata['minute'] = $newtime['minute'];
		}
		else
		{
			$crondata['day'] = $firstday;
			$crondata['hour'] = $todaytime['hour'];
			$crondata['minute'] = $todaytime['minute'];
		}
	}
	else
	{
		$crondata['day'] = $firstday;

		$newtime = fetch_cron_next_run($crondata, 0, -1); // work out first run time that day
		$crondata['hour'] = $newtime['hour'];
		$crondata['minute'] = $newtime['minute'];
	}

	$nextrun = mktime($crondata['hour'], $crondata['minute'], 0, $monthnow, $crondata['day'], $yearnow);

	// save it
	$vbulletin->db->query_write("
		UPDATE " . TABLE_PREFIX . "cron
		SET nextrun = " . $nextrun . "
		WHERE cronid = " . intval($cronid) . " AND nextrun = " . $crondata['nextrun']
	);
	$not_run = ($vbulletin->db->affected_rows() > 0);

	build_cron_next_run($nextrun);
	return iif($not_run, $nextrun, 0);
}

// ###################### Start build_cron_next_run #######################
function build_cron_next_run($nextrun = '')
{
	global $vbulletin;

	// get next one to run
	if (!$nextcron = $vbulletin->db->query_first("
		SELECT MIN(nextrun) AS nextrun
		FROM " . TABLE_PREFIX . "cron AS cron
		" . (!defined('UPGRADE_COMPAT') ? "
			LEFT JOIN " . TABLE_PREFIX . "product AS product ON (cron.product = product.productid)
			WHERE cron.active = 1
			AND (product.productid IS NULL OR product.active = 1)" : '') . "
	"))
	{
		$nextcron['nextrun'] = TIMENOW + 60 * 60;
	}

	// update DB details
	build_datastore('cron', $nextcron['nextrun']);

	return $nextrun;
}

// ###################### Start log_cron_action #######################
// description = action that was performed
// $nextitem is an array containing the information for this cronjob
// $phrased is set to true if this action is phrased
function log_cron_action($description, $nextitem, $phrased = 0)
{
	global $vbulletin;

	if (defined('ECHO_CRON_LOG'))
	{
		echo "<p>$description</p>";
	}

	if ($nextitem['loglevel'])
	{
		/*insert query*/
		$vbulletin->db->query_write("
			INSERT INTO " . TABLE_PREFIX . "cronlog
				(varname, dateline, description, type)
			VALUES
				('" . $vbulletin->db->escape_string($nextitem['varname']) . "',
				" . TIMENOW . ",
				'" . $vbulletin->db->escape_string($description) . "',
				" . intval($phrased) . ")
		");
	}
}

// ###################### Start exec_cron #######################
function exec_cron($cronid = NULL)
{
	global $vbulletin, $workingdir, $subscriptioncache;

	if ($cronid = intval($cronid))
	{
		$nextitem = $vbulletin->db->query_first_slave("
			SELECT *
			FROM " . TABLE_PREFIX . "cron
			WHERE cronid = $cronid
		");
	}
	else
	{
		$nextitem = $vbulletin->db->query_first("
			SELECT cron.*
			FROM " . TABLE_PREFIX . "cron AS cron
			LEFT JOIN " . TABLE_PREFIX . "product AS product ON (cron.product = product.productid)
			WHERE cron.nextrun <= " . TIMENOW . " AND cron.active = 1
				AND (product.productid IS NULL OR product.active = 1)
			ORDER BY cron.nextrun
			LIMIT 1
		");
	}

	if ($nextitem)
	{
		if ($nextrun = build_cron_item($nextitem['cronid'], $nextitem))
		{
			include_once(DIR . '/' . $nextitem['filename']);
		}
	}
	else
	{
		build_cron_next_run();
	}

	//make sure that shutdown functions are called on script exit.
	$GLOBALS['vbulletin']->shutdown->shutdown();
	($hook = vBulletinHook::fetch_hook('cron_complete')) ? eval($hook) : false;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>
