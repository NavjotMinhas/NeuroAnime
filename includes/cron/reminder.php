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
require_once(DIR . '/includes/functions_calendar.php');

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

$timenow = TIMENOW;

$beginday = $timenow - 86400;
$endday = $timenow + 345600; # 4 Days

$eventlist = array();
$eventcache = array();
$userinfo = array();

$events = $vbulletin->db->query_read("
	SELECT event.eventid, event.title, recurring, recuroption, dateline_from, dateline_to, IF (dateline_to = 0, 1, 0) AS singleday,
		dateline_from AS dateline_from_user, dateline_to AS dateline_to_user, utc, dst, event.calendarid,
		subscribeevent.userid, subscribeevent.lastreminder, subscribeevent.subscribeeventid, subscribeevent.reminder,
		user.email, user.languageid, user.usergroupid, user.username, user.timezoneoffset, IF(user.options & 128, 1, 0) AS dstonoff,
		calendar.title AS calendar_title
	FROM " . TABLE_PREFIX . "event AS event
	INNER JOIN " . TABLE_PREFIX . "subscribeevent AS subscribeevent ON (subscribeevent.eventid = event.eventid)
	INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = subscribeevent.userid)
	LEFT JOIN " . TABLE_PREFIX . "calendar AS calendar ON (event.calendarid = calendar.calendarid)
	WHERE ((dateline_to >= $beginday AND dateline_from < $endday) OR (dateline_to = 0 AND dateline_from >= $beginday AND dateline_from <= $endday ))
		AND event.visible = 1
");

$updateids = array();
while ($event = $vbulletin->db->fetch_array($events))
{
	if (!($vbulletin->usergroupcache["$event[usergroupid]"]['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']))
	{
		continue;
	}

	$offset = $event['utc'] ? 0 : ($event['dstonoff'] ? 3600 : 0);

	$event['dateline_from_user'] = $event['dateline_from'] + $offset;
	$event['dateline_to_user'] = $event['dateline_to'] + $offset;

	if ($vbulletin->debug AND VB_AREA == 'AdminCP')
	{
		echo "<br>$event[title] $event[username]<br>";
		echo "-GM Start: " . gmdate('Y-m-d h:i:s a', $event['dateline_from']);
		echo "<br />User Start: " . gmdate('Y-m-d h:i:s a', $event['dateline_from_user']);
	}

	if (empty($userinfo["$event[userid]"]))
	{
		$userinfo["$event[userid]"] = array(
			'username'   => $event['username'],
			'email'      => $event['email'],
			'languageid' => $event['languageid']
		);
	}

	# Set invalid reminder times to one hour
	if (empty($reminders["$event[reminder]"]))
	{
		$event['reminder'] = 3600;
	}

	# Add 15 mins to reminder time to start emails going out 15 mins before actual reminder time.
	# This gives a 15 min window around the desired event reminder time assuming reminder emails are sent every 30 mins.
	$event['reminder'] += 900;

	$update = false;
	$foundevent = false;

	$offset = (($event['dstonoff'] AND $event['dst']) ? 1 : 0) * 3600;
	$dateline_from = $event['dateline_from'] - $offset;

	if (!$event['recurring'])
	{
		if ($event['singleday'])
		{
			$event['tzoffset'] = $event['timezoneoffset'];
			if ($event['dstonoff'])
			{
				// DST is on, add an hour
				$event['tzoffset']++;

				if (substr($event['tzoffset'], 0, 1) != '-')
				{
					// recorrect so that it has + sign, if necessary
					$event['tzoffset'] = '+' . $event['tzoffset'];
				}
			}
			if ($event['tzoffset'] > 0 AND strpos($event['tzoffset'], '+') === false)
			{
				$event['tzoffset'] = '+' . $event['tzoffset'];
			}

			$event['dateline_from_user'] = $event['dateline_from'] - $event['tzoffset'] * 3600;

			$time_until_event = $event['dateline_from_user'] - $timenow;
		}
		else
		{
			$time_until_event = $dateline_from - $timenow;
		}

		if ($vbulletin->debug AND VB_AREA == 'AdminCP')
		{
			echo "<br />Time until next event: " . ($time_until_event / 60 / 60) . " hours";
			echo "<br />Reminder Time: " . ($event['reminder'] / 60 / 60 - .25) . " hours";
		}

		if ($time_until_event <= $event['reminder'] AND $time_until_event >= 0)
		{
			# Between 0 and X hours until event starts
			if (!$event['lastreminder'])
			{
				$update = true;
			}
			if ($vbulletin->debug AND VB_AREA == 'AdminCP')
			{
				echo "<br />EVENT FOUND " . gmdate('Y-m-d h:i:s a', $dateline_from) . '<hr />';
			}
		}
	}
	else
	{ // Recurring Event

		# Advance start date up to the first occurence after now.
		if ($dateline_from <= $timenow)
		{
			$dateline_from = (ceil(($timenow - $dateline_from) / 86400) * 86400) + $dateline_from;
		}

		$time_until_event = $dateline_from - $timenow;

		while ($time_until_event <= $event['reminder'] AND $time_until_event >= 0 AND !$foundevent)
		{
			if ($vbulletin->debug AND VB_AREA == 'AdminCP')
			{
				echo "<br />Time until next event: " . ($time_until_event / 60 / 60) . " hours";
				echo "<br />Reminder Time: " . ($event['reminder'] / 60 / 60 - .25) . " hours";
			}

			# Between 0 and x hours until event starts
			$temp = explode('-', gmdate('n-j-Y', $dateline_from));
			if (cache_event_info($event, $temp[0], $temp[1], $temp[2], true, false))
			{
				if ($vbulletin->debug AND VB_AREA == 'AdminCP')
				{
					echo "<br />EVENT FOUND " . gmdate('Y-m-d h:i:s a', $dateline_from);
				}

				$foundevent = true;
				if ($event['lastreminder'] != $dateline_from)
				{
					#we've never sent a reminder for this event occurence
					$update = true;
				}
			}
			else
			{
				$time_until_event += 86400;
				$dateline_from += 86400;
			}
			if ($vbulletin->debug AND VB_AREA == 'AdminCP')
			{
				echo "<hr />";
			}
		}
	}

	if ($update)
	{
		$updateids[] = $event['subscribeeventid'];
		$sql[] = " WHEN subscribeeventid = $event[subscribeeventid] THEN $dateline_from ";
		$eventlist["$event[userid]"]["$event[eventid]"] = ceil($time_until_event / 60 / 60);
		if (empty($eventcache["$event[eventid]"]))
		{
			$eventcache["$event[eventid]"] = array(
				'title'      => $event['title'],
				'calendarid' => $event['calendarid'],
				'calendar'   => $event['calendar_title'],
				'eventid'    => $event['eventid'],
			);
		}
	}
}

if (!empty($updateids))
{
	$vbulletin->db->query_write("
		UPDATE " . TABLE_PREFIX . "subscribeevent
		SET lastreminder =
		CASE
		 " . implode(" \r\n", $sql) . "
		ELSE lastreminder
		END
		WHERE subscribeeventid IN (" . implode(', ', $updateids) . ")
	");
}

vbmail_start();

$usernames = '';
$reminderbits = '';
foreach ($eventlist AS $userid => $event)
{
	$usernames .= iif($usernames, ', ');
	$usernames .= $userinfo["$userid"]['username'];
	$reminderbits = '';

	foreach($event AS $eventid => $hour)
	{
		$eventinfo =& $eventcache["$eventid"];
		eval(fetch_email_phrases('reminderbit', $userinfo["$userid"]['languageid']));
		$reminderbits .= $message;
	}

	$username = unhtmlspecialchars($userinfo["$userid"]['username']);
	eval(fetch_email_phrases('reminder', $userinfo["$userid"]['languageid']));
	vbmail($userinfo["$userid"]['email'], $subject, $message, true);

	if ($vbulletin->debug AND VB_AREA == 'AdminCP')
	{
		"<pre>"; echo $subject; echo "</pre>"; echo "<pre>"; echo $message; echo "</pre><br />";
	}
}

vbmail_end();

if (!empty($usernames))
{
	log_cron_action($usernames, $nextitem, 1);
}


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>