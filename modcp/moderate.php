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

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile$ - $Revision: 44589 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('thread',	'calendar', 'timezone', 'threadmanage');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_databuild.php');

// ############################# LOG ACTION ###############################
$vbulletin->input->clean_array_gpc('r', array(
	'calendarid' => TYPE_INT,
	'forumid'    => TYPE_INT,
));
log_admin_action(iif(!empty($vbulletin->GPC['calendarid']), "calendar id = " . $vbulletin->GPC['calendarid'], iif(!empty($vbulletin->GPC['forumid']), "forum id = " . $vbulletin->GPC['forumid'])));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['moderation']);

// ###################### Start message moderation #######################
if ($_REQUEST['do'] == 'messages')
{
	print_form_header('moderate', 'domessages');
	print_table_header($vbphrase['visitor_messages_awaiting_moderation']);
	$messages = $db->query_read("
		SELECT visitormessage.*, visitormessage.title AS subject, user.username, user2.username AS postusername, visitormessage.title
		FROM " . TABLE_PREFIX . "visitormessage AS visitormessage
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (visitormessage.userid = user.userid)
		LEFT JOIN " . TABLE_PREFIX . "user AS user2 ON (visitormessage.postuserid = user2.userid)
		WHERE state = 'moderation'
	");
	$done = false;
	while ($messageinfo = $db->fetch_array($messages))
	{
		if (!can_moderate(0, 'canmoderatevisitormessages'))
		{
			continue;
		}

		if ($done)
		{
			print_description_row('<span class="smallfont">&nbsp;</span>', 0, 2, 'thead');
		}
		else
		{
			print_description_row('
				<input type="button" value="' . $vbphrase['validate'] . '" onclick="js_check_all_option(this.form, 1);" class="button" title="' . $vbphrase['validate'] . '" />
				&nbsp;
				' . (can_moderate(0, 'candeletevisitormessages') ? '<input type="button" value="' . $vbphrase['delete'] . '" onclick="js_check_all_option(this.form, -1);" class="button" title="' . $vbphrase['delete'] . '" />
					&nbsp;' : '') . '
				<input type="button" value="' . $vbphrase['ignore'] . '" onclick="js_check_all_option(this.form, 0);" class="button" title="' . $vbphrase['ignore'] . '" />
			', 0, 2, 'thead', 'center');
		}

		if (!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
		{
			print_label_row('<b>' . $vbphrase['posted_by'] . '</b>', '<a href="user.php?' . $vbulletin->session->vars['sessionurl'] . "do=viewuser&u=$messageinfo[postuserid]\">$messageinfo[postusername]</a>");
			print_label_row('<b>' . $vbphrase['user_profile'] . '</b>', '<a href="user.php?' . $vbulletin->session->vars['sessionurl'] . "do=viewuser&u=$messageinfo[userid]\">$messageinfo[username]</a>");
		}
		else
		{
			print_label_row('<b>' . $vbphrase['posted_by'] . '</b>', '<a href="../' . $vbulletin->config['Misc']['admincpdir']  . '/user.php?' . $vbulletin->session->vars['sessionurl'] . "do=edit&u=$messageinfo[postuserid]\">$messageinfo[postusername]</a>");
			print_label_row('<b>' . $vbphrase['user_profile'] . '</b>', '<a href="../' . $vbulletin->config['Misc']['admincpdir']  . '/user.php?' . $vbulletin->session->vars['sessionurl'] . "do=edit&u=$messageinfo[userid]\">$messageinfo[username]</a>");
		}
		#print_input_row('<b>' . $vbphrase['subject'] . '</b>', "messagesubject[$messageinfo[vmid]]", $messageinfo['title']);

		if (can_moderate(0, 'caneditvisitormessages'))
		{
			print_textarea_row('<b>' . $vbphrase['message'] . '</b>', "messagetext[$messageinfo[vmid]]", $messageinfo['pagetext'], 15, 70);
		}
		else
		{
			print_label_row('<b>' . $vbphrase['message'] . '</b>', nl2br(htmlspecialchars_uni($messageinfo['pagetext'])));
			construct_hidden_code("messagetext[$messageinfo[vmid]]", $messageinfo['pagetext']);
		}

		print_label_row($vbphrase['action'], "
			<label for=\"val_$messageinfo[vmid]\"><input type=\"radio\" name=\"messageaction[$messageinfo[vmid]]\" value=\"1\" id=\"val_$messageinfo[vmid]\" tabindex=\"1\" />" . $vbphrase['validate'] . "</label>
			" . (can_moderate(0, 'candeletevisitormessages') ? "<label for=\"del_$messageinfo[vmid]\"><input type=\"radio\" name=\"messageaction[$messageinfo[vmid]]\" value=\"-1\" id=\"del_$messageinfo[vmid]\" tabindex=\"1\" />" . $vbphrase['delete'] . "</label>" : '') . "
			<label for=\"ign_$messageinfo[vmid]\"><input type=\"radio\" name=\"messageaction[$messageinfo[vmid]]\" value=\"0\" id=\"ign_$messageinfo[vmid]\" tabindex=\"1\" checked=\"checked\" /> " . $vbphrase['ignore'] . "</label>
		", '', 'top', 'messageaction');
		$done = true;
	}

	if (!$done)
	{
		print_description_row($vbphrase['no_messages_awaiting_moderation']);
		print_table_footer();
	}
	else
	{
		print_submit_row();
	}
}

// ###################### Start message moderation #######################
if ($_REQUEST['do'] == 'events')
{

	$sql = '';
	$calendars = $db->query_read("SELECT calendarid FROM " . TABLE_PREFIX . "calendar");
	$calendarids = array();
	while ($calendar = $db->fetch_array($calendars))
	{
		if (can_moderate_calendar($calendar['calendarid'], 'canmoderateevents'))
		{
			$calendarids[] = $calendar['calendarid'];
		}
	}
	if (!empty($calendarids))
	{
		$sql = "calendar.calendarid IN(" . implode(", ", $calendarids) . ")";
	}

	print_form_header('moderate', 'doevents');
	print_table_header($vbphrase['events_awaiting_moderation']);

	if ($sql)
	{
		$events = $db->query_read("
			SELECT event.*, event.title AS subject, user.username, calendar.title, IF(dateline_to = 0, 1, 0) AS singleday
			FROM " . TABLE_PREFIX . "event AS event
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON(event.userid = user.userid)
			LEFT JOIN " . TABLE_PREFIX . "calendar AS calendar ON(calendar.calendarid = event.calendarid)
			WHERE $sql AND visible = 0
		");
		$done = false;
		while ($eventinfo = $db->fetch_array($events))
		{
			if ($done)
			{
				print_description_row('<span class="smallfont">&nbsp;</span>', 0, 2, 'thead');
			}
			else
			{
				print_description_row('
					<input type="button" value="' . $vbphrase['validate'] . '" onclick="js_check_all_option(this.form, 1);" class="button" title="' . $vbphrase['validate'] . '" />
					&nbsp;
					' . (can_moderate_calendar(0, 'candeleteevents') ? '<input type="button" value="' . $vbphrase['delete'] . '" onclick="js_check_all_option(this.form, -1);" class="button" title="' . $vbphrase['delete'] . '" />
						&nbsp;' : '') . '
					<input type="button" value="' . $vbphrase['ignore'] . '" onclick="js_check_all_option(this.form, 0);" class="button" title="' . $vbphrase['ignore'] . '" />
				', 0, 2, 'thead', 'center');
			}

			if (!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
			{
				print_label_row('<b>' . $vbphrase['posted_by'] . '</b>', '<a href="user.php?' . $vbulletin->session->vars['sessionurl'] . "do=viewuser&u=$eventinfo[userid]\">$eventinfo[username]</a>");
			}
			else
			{
				print_label_row('<b>' . $vbphrase['posted_by'] . '</b>', '<a href="../' . $vbulletin->config['Misc']['admincpdir']  . '/user.php?' . $vbulletin->session->vars['sessionurl'] . "do=edit&u=$eventinfo[userid]\">$eventinfo[username]</a>");
			}
			print_label_row('<b>' . $vbphrase['calendar'] . '</b>', '<a href="../calendar.php?' . $vbulletin->session->vars['sessionurl'] . "c=$eventinfo[calendarid]\">$eventinfo[title]</a>");

			if (can_moderate_calendar($eventinfo['calendarid'], 'caneditevents'))
			{
				print_input_row('<b>' . $vbphrase['subject'] . '</b>', "eventsubject[$eventinfo[eventid]]", $eventinfo['subject']);
			}
			else
			{
				print_label_row('<b>' . $vbphrase['subject'] . '</b>', htmlspecialchars_uni($eventinfo['subject']));
				construct_hidden_code("eventsubject[$eventinfo[eventid]]", $eventinfo['subject']);
			}

			$time1 =  vbdate($vbulletin->options['timeformat'], $eventinfo['dateline_from']);
			$time2 =  vbdate($vbulletin->options['timeformat'], $eventinfo['dateline_to']);

			if ($eventinfo['singleday'])
			{
				print_label_row('<b>' . $vbphrase['date'] . '</b>', vbdate($vbulletin->options['dateformat'], $eventinfo['dateline_from']));
			}
			else if ($eventinfo['dateline_from'] != $eventinfo['dateline_to'])
			{
				$recurcriteria = fetch_event_criteria($eventinfo);
				$date1 = vbdate($vbulletin->options['dateformat'], $eventinfo['dateline_from']);
				$date2 = vbdate($vbulletin->options['dateformat'], $eventinfo['dateline_to']);
				if (!$recurcriteria)
				{
					$recurcriteria = $vbcalendar['word6']; // What is word6?
				}
				print_label_row('<b>' . $vbphrase['time'] . '</b>', construct_phrase($vbphrase['x_to_y'], $time1, $time2));
				print_label_row('<b>' . $vbphrase['timezone'] . '</b>', "<select name=\"eventtimezone[$eventinfo[eventid]]\" tabindex=\"1\" class=\"bginput\">" . construct_select_options(fetch_timezones_array(), $eventinfo['utc']) . '</select>');
				print_label_row('<b>' . $vbphrase['date_range'] . '</b>', $recurcriteria . ' | ' . construct_phrase($vbphrase['x_to_y'], $date1, $date2));
			}
			else
			{
				$date = vbdate($vbulletin->options['dateformat'], $eventinfo['from_date']);
				print_label_row('<b>' . $vbphrase['time'] . '</b>', construct_phrase($vbphrase['x_to_y'], $time1, $time2));
				print_label_row('<b>' . $vbphrase['timezone'] . '</b>', "<select name=\"eventtimezone[$eventinfo[eventid]]\" tabindex=\"1\" class=\"bginput\">" . construct_select_options(fetch_timezones_array(), $eventinfo['utc']) . '</select>');
				print_label_row('<b>' . $vbphrase['date_range'] . '</b>', $date);
			}

			if (can_moderate_calendar($eventinfo['calendarid'], 'caneditevents'))
			{
				print_textarea_row('<b>' . $vbphrase['event'] . '</b>', "eventtext[$eventinfo[eventid]]", $eventinfo['event'], 15, 70);
			}
			else
			{
				print_label_row('<b>' . $vbphrase['event'] . '</b>', nl2br(htmlspecialchars_uni($eventinfo['event'])));
				construct_hidden_code("eventtext[$eventinfo[eventid]]", $eventinfo['event']);
			}

			print_label_row($vbphrase['action'], "
				<label for=\"val_$eventinfo[eventid]\"><input type=\"radio\" name=\"eventaction[$eventinfo[eventid]]\" value=\"1\" id=\"val_$eventinfo[eventid]\" tabindex=\"1\" />" . $vbphrase['validate'] . "</label>
				" . (can_moderate_calendar($eventinfo['calendarid'], 'candeleteevents') ? "<label for=\"del_$eventinfo[eventid]\"><input type=\"radio\" name=\"eventaction[$eventinfo[eventid]]\" value=\"-1\" id=\"del_$eventinfo[eventid]\" tabindex=\"1\" />" . $vbphrase['delete'] . "</label>" : '') . "
				<label for=\"ign_$eventinfo[eventid]\"><input type=\"radio\" name=\"eventaction[$eventinfo[eventid]]\" value=\"0\" id=\"ign_$eventinfo[eventid]\" tabindex=\"1\" checked=\"checked\" /> " . $vbphrase['ignore'] . "</label>
			", '', 'top', 'eventaction');
			$done = true;
		}
	}
	if (!$done)
	{
		print_description_row($vbphrase['no_events_awaiting_moderation']);
		print_table_footer();
	}
	else
	{
		print_submit_row();
	}
}

// ###################### Start do message moderation #######################
if ($_POST['do'] == 'domessages')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'messageaction'  => TYPE_ARRAY_INT,
		'messagesubject' => TYPE_ARRAY_STR,
		'messagetext'    => TYPE_ARRAY_STR,
	));

	require_once(DIR . '/includes/functions_visitormessage.php');

	foreach ($vbulletin->GPC['messageaction'] AS $vmid => $action)
	{
		$vmid = intval($vmid);
		if (!can_moderate(0, 'canmoderatevisitormessages'))
		{
			continue;
		}

		$messageinfo = fetch_visitormessageinfo($vmid);
		if (!$messageinfo OR $messageinfo['state'] != 'moderation')
		{
			continue;
		}

		$dataman =& datamanager_init('VisitorMessage', $vbulletin, ERRTYPE_SILENT);
		$dataman->set_existing($messageinfo);

		if ($action == 1)
		{ // validate
			#$dataman->set('title', $vbulletin->GPC['messagesubject']["$vmid"]);
			if (can_moderate(0, 'caneditvisitormessages'))
			{
				$dataman->set('pagetext', $vbulletin->GPC['messagetext']["$vmid"]);
			}
			$dataman->set('state', 'visible');
			$dataman->save();
		}
		else if ($action == -1 AND can_moderate(0, 'candeletevisitormessages'))
		{ // delete
			$dataman->delete();
		}
	}

	define('CP_REDIRECT', 'moderate.php?do=messages');
	print_stop_message('moderated_visitor_messages_successfully');
}

// ###################### Start do event moderation #######################
if ($_POST['do'] == 'doevents')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'eventaction'   => TYPE_ARRAY_INT,
		'eventsubject'  => TYPE_ARRAY_STR,
		'eventtext'     => TYPE_ARRAY_STR,
		'eventtimezone' => TYPE_ARRAY_INT,
	));

	foreach ($vbulletin->GPC['eventaction'] AS $eventid => $action)
	{
		$eventid = intval($eventid);
		$getcalendarid = $db->query_first("
			SELECT calendarid
			FROM " . TABLE_PREFIX . "event
			WHERE eventid = $eventid
		");
		if (!can_moderate_calendar($getcalendarid['calendarid'], 'canmoderateevents'))
		{
			continue;
		}

		$eventinfo = array('eventid' => $eventid);
		// init event datamanager class
		$eventdata =& datamanager_init('Event', $vbulletin, ERRTYPE_SILENT);
		$eventdata->set_existing($eventinfo);

		if ($action == 1)
		{ // validate

			$eventdata->verify_datetime = false;
			$eventdata->set('utc', $vbulletin->GPC['eventtimezone']["$eventid"]);

			if (can_moderate_calendar($getcalendarid['calendarid'], 'caneditevents'))
			{
				$eventdata->set('title', $vbulletin->GPC['eventsubject']["$eventid"]);
				$eventdata->set('event', $vbulletin->GPC['eventtext']["$eventid"]);
			}
			$eventdata->set('visible', 1);
			$eventdata->save();
		}
		else if ($action == -1 AND can_moderate_calendar($getcalendarid['calendarid'], 'candeleteevents'))
		{ // delete

			$eventdata->delete();
		}
	}

	define('CP_REDIRECT', 'moderate.php?do=events');
	print_stop_message('moderated_events_successfully');
}

// ###################### Start thread/post moderation #######################
if ($_REQUEST['do'] == 'posts')
{
	// fetch threads and posts to be moderated from the moderation table
	// this saves a index on visible and a query with about 3 inner joins
	$threadids = array();
	$postids = array();

	$hasdelperm = array();

	$moderated = $db->query_read("
		SELECT *
		FROM " . TABLE_PREFIX . "moderation
		WHERE type IN ('thread', 'reply')
	");
	while ($moderate = $db->fetch_array($moderated))
	{
		if ($moderate['type'] == 'thread')
		{
			$threadids[] = $moderate['primaryid'];
		}
		else
		{
			$postids[] = $moderate['primaryid'];
		}
	}
	$db->free_result($moderated);

	$sql = fetch_moderator_forum_list_sql('canmoderateposts');

	print_form_header('moderate', 'doposts', 0, 1, 'threads');
	print_table_header($vbphrase['threads_awaiting_moderation']);

	if (!empty($threadids) AND $sql)
	{
		$threadids = implode(',', $threadids);
		$threads = $db->query_read("
			SELECT thread.threadid, thread.title AS title, thread.notes AS notes,
				thread.forumid AS forumid, thread.postuserid AS userid,
				thread.postusername AS username, thread.dateline, thread.firstpostid, pagetext
			FROM " . TABLE_PREFIX . "thread AS thread
			LEFT JOIN " . TABLE_PREFIX . "post AS post ON(thread.firstpostid = post.postid)
			WHERE $sql AND thread.threadid IN ($threadids)
			ORDER BY thread.lastpost
		");

		$havethreads = false;
		while ($thread = $db->fetch_array($threads))
		{
			if ($thread['firstpostid'] == 0)
			{ // eek potential for disaster
				$post_text = $db->query_first("SELECT pagetext FROM " . TABLE_PREFIX . "post WHERE threadid = $thread[threadid] ORDER BY dateline ASC");
				$thread['pagetext'] = $post_text['pagetext'];
			}

			if ($havethreads)
			{
				print_description_row('<span class="smallfont">&nbsp;</span>', 0, 2, 'thead');
			}
			else
			{
				print_description_row('
					<input type="button" value="' . $vbphrase['validate'] . '" onclick="js_check_all_option(this.form, 1);" class="button" title="' . $vbphrase['validate'] . '" />
					' . ((can_moderate(0, 'candeleteposts') OR can_moderate(0, 'canremoveposts')) ? '&nbsp;
					<input type="button" value="' . $vbphrase['delete'] . '" onclick="js_check_all_option(this.form, -1);" class="button" title="' . $vbphrase['delete'] . '" />' : '') . '
					&nbsp;
					<input type="button" value="' . $vbphrase['ignore'] . '" onclick="js_check_all_option(this.form, 0);" class="button" title="' . $vbphrase['ignore'] . '" />
				', 0, 2, 'thead', 'center');
			}
			if (!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
			{
				print_label_row('<b>' . $vbphrase['posted_by'] . '</b>', iif($thread['userid'], '<a href="user.php?' . $vbulletin->session->vars['sessionurl'] . "do=viewuser&u=$thread[userid]\" target=\"_blank\">$thread[username]</a>", empty($thread['username']) ? $vbphrase['guest'] : $thread['username']));
			}
			else
			{
				print_label_row('<b>' . $vbphrase['posted_by'] . '</b>', iif($thread['userid'], '<a href="../' . $vbulletin->config['Misc']['admincpdir'] . '/user.php?' . $vbulletin->session->vars['sessionurl'] . "do=edit&u=$thread[userid]\" target=\"_blank\">$thread[username]</a>", empty($thread['username']) ? $vbphrase['guest'] : $thread['username']));
			}
			print_label_row('<b>' . $vbphrase['forum'] . '</b>', '<a href="../' . fetch_seo_url('forum', array('forumid' => $thread['forumid'], 'title' => $vbulletin->forumcache["$thread[forumid]"]['title'])) . "\" target=\"_blank\">" . $vbulletin->forumcache["$thread[forumid]"]['title'] . "</a>");

			if (can_moderate(0, 'caneditthreads'))
			{
				print_input_row($vbphrase['title'], "threadtitle[$thread[threadid]]", $thread['title'], 0, 70);
			}
			else
			{
				print_label_row($vbphrase['title'], $thread['title']);
				construct_hidden_code("threadtitle[$thread[threadid]]", $thread['title'], false);
			}

			if (can_moderate(0, 'caneditposts'))
			{
				print_textarea_row($vbphrase['message'], "threadpagetext[$thread[threadid]]", $thread['pagetext'], 15, 70);
			}
			else
			{
				print_label_row($vbphrase['message'], nl2br(htmlspecialchars_uni($thread['pagetext'])));
				construct_hidden_code("threadpagetext[$thread[threadid]]", $thread['pagetext']);
			}

			print_input_row($vbphrase['notes'], "threadnotes[$thread[threadid]]", $thread['notes'], 1, 70);

			if (!isset($hasdelperm["$thread[forumid]"]))
			{
				$hasdelperm["$thread[forumid]"] = (can_moderate($thread['forumid'], 'candeleteposts') OR can_moderate($thread['forumid'], 'canremoveposts'));
			}

			print_label_row($vbphrase['action'], "
				<label for=\"val_$thread[threadid]\"><input type=\"radio\" name=\"threadaction[$thread[threadid]]\" value=\"1\" id=\"val_$thread[threadid]\" tabindex=\"1\" />" . $vbphrase['validate'] . "</label>
				" . ($hasdelperm["$thread[forumid]"] ? "<label for=\"del_$thread[threadid]\"><input type=\"radio\" name=\"threadaction[$thread[threadid]]\" value=\"-1\" id=\"del_$thread[threadid]\" tabindex=\"1\" />" . $vbphrase['delete'] . "</label>" : '') . "
				<label for=\"ign_$thread[threadid]\"><input type=\"radio\" name=\"threadaction[$thread[threadid]]\" value=\"0\" id=\"ign_$thread[threadid]\" tabindex=\"1\" checked=\"checked\" />" . $vbphrase['ignore'] . "</label>
			", '', 'top', 'threadaction');

			$havethreads = true;
		}
	}
	if (!$havethreads)
	{
		print_description_row($vbphrase['no_threads_awaiting_moderation']);
		print_table_footer();
	}
	else
	{
		print_submit_row();
	}

	print_form_header('moderate', 'doposts', 0, 1, 'posts');
	print_table_header($vbphrase['posts_awaiting_moderation'], 2, 0, 'postlist');


	if (!empty($postids) AND $sql)
	{
		$postids = implode(',', $postids);
		$posts = $db->query_read("
			SELECT postid, pagetext, post.dateline, post.userid, post.title AS post_title,
			thread.title AS threadtitle, thread.forumid AS forumid, username, thread.threadid
			FROM " . TABLE_PREFIX . "post AS post
			LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = post.threadid)
			WHERE $sql AND postid IN($postids)
			ORDER BY dateline
		");
		$haveposts = false;
		while ($post = $db->fetch_array($posts))
		{
			if ($haveposts)
			{
				print_description_row('<span class="smallfont">&nbsp;</span>', 0, 2, 'thead');
			}
			else
			{
				print_description_row('
					<input type="button" value="' . $vbphrase['validate'] . '" onclick="js_check_all_option(this.form, 1);" class="button" title="' . $vbphrase['validate'] . '" />
					' . ((can_moderate(0, 'candeleteposts') OR can_moderate(0, 'canremoveposts')) ? '&nbsp;
					<input type="button" value="' . $vbphrase['delete'] . '" onclick="js_check_all_option(this.form, -1);" class="button" title="' . $vbphrase['delete'] . '" />' : '') . '
					&nbsp;
					<input type="button" value="' . $vbphrase['ignore'] . '" onclick="js_check_all_option(this.form, 0);" class="button" title="' . $vbphrase['ignore'] . '" />
				', 0, 2, 'thead', 'center');
			}
			if (!($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
			{
				print_label_row('<b>' . $vbphrase['posted_by'] . '</b>', iif($post['userid'], '<a href="user.php?' . $vbulletin->session->vars['sessionurl'] . "do=viewuser&u=$post[userid]\" target=\"_blank\">$post[username]</a>", empty($post['username']) ? $vbphrase['guest'] : $post['username']));
			}
			else
			{
				print_label_row('<b>' . $vbphrase['posted_by'] . '</b>', iif($post['userid'], '<a href="../' . $vbulletin->config['Misc']['admincpdir'] . '/user.php?' . $vbulletin->session->vars['sessionurl'] . "do=edit&u=$post[userid]\" target=\"_blank\">$post[username]</a>", empty($post['username']) ? $vbphrase['guest'] : $post['username']));
			}
			print_label_row('<b>' . $vbphrase['thread'] . '</b>', '<a href="../' . fetch_seo_url('thread', $post, null, 'threadid', 'threadtitle') . "\" target=\"_blank\">$post[threadtitle]</a>");
			print_label_row('<b>' . $vbphrase['forum'] . '</b> ', '<a href="../' . fetch_seo_url('forum', array('forumid' => $post['forumid'], 'title' => $vbulletin->forumcache["$post[forumid]"]['title'])) . "\" target=\"_blank\">" . $vbulletin->forumcache["$post[forumid]"]['title'] . "</a>");

			if (can_moderate(0, 'caneditposts'))
			{
				print_input_row($vbphrase['title'], "posttitle[$post[postid]]", $post['post_title'], 0, 70);
				print_textarea_row($vbphrase['message'], "postpagetext[$post[postid]]", $post['pagetext'], 15, 70);
			}
			else
			{
				print_label_row($vbphrase['title'], $post['post_title']);
				print_label_row($vbphrase['message'], nl2br(htmlspecialchars_uni($post['pagetext'])));
				construct_hidden_code("posttitle[$post[postid]]", $post['post_title'], false);
				construct_hidden_code("postpagetext[$post[postid]]", $post['pagetext']);
			}

			if (!isset($hasdelperm["$post[forumid]"]))
			{
				$hasdelperm["$post[forumid]"] = (can_moderate($post['forumid'], 'candeleteposts') OR can_moderate($post['forumid'], 'canremoveposts'));
			}

			print_label_row($vbphrase['action'], "
				<label for=\"val_$post[postid]\"><input type=\"radio\" name=\"postaction[$post[postid]]\" value=\"1\" id=\"val_$post[postid]\" tabindex=\"1\" />" . $vbphrase['validate'] . "</label>
				" . ($hasdelperm["$post[forumid]"] ? "<label for=\"del_$post[postid]\"><input type=\"radio\" name=\"postaction[$post[postid]]\" value=\"-1\" id=\"del_$post[postid]\" tabindex=\"1\" />" . $vbphrase['delete'] . "</label>" : '') . "
				<label for=\"ign_$post[postid]\"><input type=\"radio\" name=\"postaction[$post[postid]]\" value=\"0\" id=\"ign_$post[postid]\" tabindex=\"1\"  checked=\"checked\" />" . $vbphrase['ignore'] . "</label>
			", '', 'top', 'postaction');

			$haveposts = true;
		}
	}
	if (!$haveposts)
	{
		print_description_row($vbphrase['no_posts_awaiting_moderation']);
		print_table_footer();
	}
	else
	{
		print_submit_row();
	}

}

// ###################### Start do thread/post moderation #######################
if ($_POST['do'] == 'doposts')
{

	// As of 3.5 user post counts are not incremented when a moderated thread/post is inserted
	// So when a post is accepted, posts are incremented. When deleted, nothing is done to posts

	$updateforum = array();
	$updatethread = array();
	$notified = array();
	$threadids = array();
	$postids = array();

	$hasdelperm = array();

	$vbulletin->input->clean_array_gpc('p', array(
		'threadaction'   => TYPE_ARRAY_INT,
		'threadtitle'    => TYPE_ARRAY_STR,
		'threadnotes'    => TYPE_ARRAY_STR,
		'threadpagetext' => TYPE_ARRAY_STR,
		'postpagetext'   => TYPE_ARRAY_STR,
		'postaction'     => TYPE_ARRAY_INT,
		'posttitle'      => TYPE_ARRAY_STR,
	));

	vbmail_start();

	$userbyuserid = array();

	if (!empty($vbulletin->GPC['threadaction']))
	{
		$modlog = array();
		foreach ($vbulletin->GPC['threadaction'] AS $threadid => $action)
		{
			$threadid = intval($threadid);
			// check whether moderator of this forum
			$threadinfo = fetch_threadinfo($threadid);
			$forumperms = $vbulletin->userinfo['forumpermissions']["$threadinfo[forumid]"];
			if (!can_moderate($threadinfo['forumid'], 'canmoderateposts') OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
			{
				continue;
			}

			$countposts = $vbulletin->forumcache["$threadinfo[forumid]"]['options'] & $vbulletin->bf_misc_forumoptions['countposts'];
			if ($action == 1)
			{ // validate
				// do queries
				$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
				$threadman->set_existing($threadinfo);
				$threadman->set_info('skip_first_post_update', true);
				$threadman->set('visible', 1);
				if (can_moderate(0, 'caneditthreads'))
				{
					$threadman->set('title', $vbulletin->GPC['threadtitle']["$threadid"]);
				}
				$threadman->set('notes', $vbulletin->GPC['threadnotes']["$threadid"]);
				if ($vbulletin->options['similarthreadsearch'])
				{
					require_once(DIR . '/vb/search/core.php');
					$searchcontroller = vB_Search_Core::get_instance()->get_search_controller();
					$similarthreads = $searchcontroller->get_similar_threads($vbulletin->GPC['threadtitle']["$threadid"],
					$threadinfo['threadid']);
					$threadman->set('similar', implode(',', $similarthreads));
				}
				$threadman->save();
				unset($threadman);

				$post = $db->query_first("
					SELECT *
					FROM " . TABLE_PREFIX . "post
					WHERE threadid = $threadid
					ORDER BY dateline
					LIMIT 1
				");
				$postman =& datamanager_init('Post', $vbulletin, ERRTYPE_SILENT, 'threadpost');
				$postman->set_existing($post);
				$postman->set('visible', 1); // This should already be visible

				if (can_moderate(0, 'caneditposts'))
				{
					$postman->set('title', $vbulletin->GPC['threadtitle']["$threadid"]);
					$postman->set('pagetext', $vbulletin->GPC['threadpagetext']["$threadid"], true, false); // bypass the verify_pagetext call
				}

				$postman->save();
				unset($postman);

				// This needs to be converted into a one query CASE statement
				if ($countposts)
				{
					// Increment post count of all visible posts in thread
					$posts = $vbulletin->db->query_read("
						SELECT userid
						FROM " . TABLE_PREFIX . "post
						WHERE threadid = $threadid AND visible = 1
					");
					while ($post = $vbulletin->db->fetch_array($posts))
					{
						if (!isset($userbyuserid["$post[userid]"]))
						{
							$userbyuserid["$post[userid]"] = 1;
						}
						else
						{
							$userbyuserid["$post[userid]"]++;
						}
					}
				}

				$threadids[] = $threadid;
				$npostids[] = $post['postid'];
				$updateforum["$threadinfo[forumid]"] = 1;

				$modlog[] = array(
					'userid'   => $vbulletin->userinfo['userid'],
					'forumid'  => $threadinfo['forumid'],
					'threadid' => $threadinfo['threadid'],
				);

			}
			else if ($action == -1)
			{
				// delete
				if (!isset($hasdelperm["$threadinfo[forumid]"]))
				{
					$hasdelperm["$threadinfo[forumid]"] = (can_moderate($threadinfo['forumid'], 'candeleteposts') OR can_moderate($threadinfo['forumid'], 'canremoveposts'));
				}
				if (!$hasdelperm["$threadinfo[forumid]"])
				{
					// doesn't have permission to delete in this forum
					continue;
				}

				$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
				$threadman->set_existing($threadinfo);
				$threadman->delete($countposts, can_moderate($threadinfo['forumid'], 'canremoveposts'));
				unset($threadman);

				$updateforum["$threadinfo[forumid]"] = 1;
			}
		}

		if (!empty($threadids))
		{
			$threadids = implode(',', $threadids);
			$db->query_write("
				DELETE FROM " . TABLE_PREFIX . "moderation
				WHERE primaryid IN($threadids) AND type = 'thread'
			");
		}

		if (!empty($modlog))
		{
			require_once(DIR . '/includes/functions_log_error.php');
			log_moderator_action($modlog, 'approved_thread');
		}
	}

	if (!empty($vbulletin->GPC['postaction']))
	{
		require_once(DIR . '/includes/functions_newpost.php');
		$modlog = array();
		foreach ($vbulletin->GPC['postaction'] AS $postid => $action)
		{
			$postid = intval($postid);

			if (!$postinfo = $db->query_first("
				SELECT post.*, thread.forumid
				FROM " . TABLE_PREFIX . "post AS post
				LEFT JOIN " . TABLE_PREFIX . "thread AS thread USING (threadid)
				WHERE post.postid = $postid
			"))
			{
				continue;
			}

			$forumperms = $vbulletin->userinfo['forumpermissions']["$postinfo[forumid]"];
			if (!can_moderate($threadinfo['forumid'], 'canmoderateposts') OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']))
			{
				continue;
			}
			if (!can_moderate($postinfo['forumid'], 'canmoderateposts'))
			{
				continue;
			}

			$countposts = $vbulletin->forumcache["$postinfo[forumid]"]['options'] & $vbulletin->bf_misc_forumoptions['countposts'];

			if ($post['visible'] != 0)
			{
				// this post should not be in the moderation queue
				$postids[] = $postid;
				continue;
			}

			if ($action == 1)
			{
				// validate
				$postman =& datamanager_init('Post', $vbulletin, ERRTYPE_SILENT, 'threadpost');
				$postman->set_existing($postinfo);
				$postman->set('visible', 1);
				if (can_moderate(0, 'caneditposts'))
				{
					$postman->set('pagetext', $vbulletin->GPC['postpagetext']["$postid"], true, false); // bypass the verify_pagetext call
					$postman->set('title', $vbulletin->GPC['posttitle']["$postid"]);
				}
				$postman->save();

				if ($countposts)
				{
					if (!isset($userbyuserid["$postinfo[userid]"]))
					{
						$userbyuserid["$postinfo[userid]"] = 1;
					}
					else
					{
						$userbyuserid["$postinfo[userid]"]++;
					}
				}

				// send notification
				if (!$notified["$postinfo[threadid]"])
				{
					$message = $vbulletin->GPC['postpagetext']["$postid"];
					exec_send_notification($postinfo['threadid'], $postinfo['userid'], $postid);
					$notified["$postinfo[threadid]"] = true;
				}

				$postids[] = $postid;
				$updatethread["$postinfo[threadid]"] = 1;
				$updateforum["$postinfo[forumid]"] = 1;

				$modlog[] = array(
					'userid'   => $vbulletin->userinfo['userid'],
					'forumid'  => $postinfo['forumid'],
					'threadid' => $postinfo['threadid'],
					'postid'   => $postid,
				);

			}
			else if ($action == -1)
			{
				// delete

				if (!isset($hasdelperm["$postinfo[forumid]"]))
				{
					$hasdelperm["$postinfo[forumid]"] = (can_moderate($postinfo['forumid'], 'candeleteposts') OR can_moderate($postinfo['forumid'], 'canremoveposts'));
				}
				if (!$hasdelperm["$postinfo[forumid]"])
				{
					// doesn't have permission to delete in this forum
					continue;
				}

				$postids[] = $postid;

				$postman =& datamanager_init('Post', $vbulletin, ERRTYPE_SILENT, 'threadpost');
				$postman->set_existing($postinfo);
				$postman->delete($countposts, $postinfo['threadid'], can_moderate($postinfo['forumid'], 'canremoveposts'));
				unset($postman);

				$updatethread["$postinfo[threadid]"] = 1;
				$updateforum["$postinfo[forumid]"] = 1;
			}
		}
		if (!empty($postids))
		{
			$postids = implode(',', $postids);
			$db->query_write("
				DELETE FROM " . TABLE_PREFIX . "moderation
				WHERE primaryid IN($postids) AND type = 'reply'
			");
		}

		if (!empty($modlog))
		{
			require_once(DIR . '/includes/functions_log_error.php');
			log_moderator_action($modlog, 'approved_post');
		}
	}

	vbmail_end();

	// Update post counts
	unset($userbyuserid[0]); // skip any guest posts
	if (!empty($userbyuserid))
	{
		$userbypostcount = array();
		foreach ($userbyuserid AS $postuserid => $postcount)
		{
			$alluserids .= ",$postuserid";
			$userbypostcount["$postcount"] .= ",$postuserid";
		}
		foreach($userbypostcount AS $postcount => $userids)
		{
			$casesql .= " WHEN userid IN (0$userids) THEN $postcount\n";
		}

		$db->query_write("
			UPDATE " . TABLE_PREFIX . "user
			SET posts = posts +
			CASE
				$casesql
				ELSE 0
			END
			WHERE userid IN (0$alluserids)
		");
	}

	// update counters
	if (!empty($updatethread))
	{
		foreach ($updatethread AS $threadid => $null)
		{
			build_thread_counters($threadid);
		}
	}
	if (!empty($updateforum))
	{
		foreach ($updateforum AS $forumid => $null)
		{
			build_forum_counters($forumid);
		}
	}

	define('CP_REDIRECT', 'moderate.php?do=posts');
	print_stop_message('moderated_posts_successfully');
}

// ###################### Start attachment moderation #######################
if ($_REQUEST['do'] == 'attachments')
{
	// Bootstrap to the vB Framework
	require_once(DIR . '/includes/class_bootstrap_framework.php');
	vB_Bootstrap_Framework::init();
	vB_Router::setRelativePath('../');

	print_form_header('moderate', 'doattachments');
	print_table_header($vbphrase['attachments_awaiting_moderation']);

	$done = false;

	require_once(DIR . '/packages/vbattach/attach.php');
	$attachmultiple = new vB_Attachment_Display_Multiple($vbulletin);
	$attachments = $attachmultiple->fetch_results("a.state = 'moderation' AND a.contentid <> 0", false, 0, 0);

	foreach ($attachments AS $attachment)
	{
		if ($done)
		{
			print_description_row('<span class="smallfont">&nbsp;</span>', 0, 2, 'thead');
		}
		else
		{
			print_description_row('
				<input type="button" value="' . $vbphrase['validate'] . '" onclick="js_check_all_option(this.form, 1);" class="button" title="' . $vbphrase['validate'] . '"
				/>&nbsp;<input type="button" value="' . $vbphrase['delete'] . '" onclick="js_check_all_option(this.form, -1);" class="button" title="' . $vbphrase['delete'] . '"
				/>&nbsp;<input type="button" value="' . $vbphrase['ignore'] . '" onclick="js_check_all_option(this.form, 0);" class="button" title="' . $vbphrase['ignore'] . '" />
			', 0, 2, 'thead', 'center');
		}
		print_label_row($vbphrase['attachment'], '<b> ' . '<a href="../attachment.php?' . $vbulletin->session->vars['sessionurl'] . "attachmentid=$attachment[attachmentid]&amp;d=$attachment[dateline]\" target=\"_blank\">" . htmlspecialchars_uni($attachment['filename']) . '</a></b>' . ' (' . vb_number_format($attachment['filesize'], 1, true) . ')');
		$extension = strtolower(file_extension($attachment['filename']));
		if ($extension == 'gif' OR $extension == 'jpg' OR $extension == 'jpe' OR $extension == 'jpeg' OR $extension == 'png' OR $extension == 'bmp')
		{
			if ($attachment['hasthumbnail'])
			{
				print_label_row($vbphrase['thumbnail'], '<a href="../attachment.php?' . $vbulletin->session->vars['sessionurl'] . "attachmentid=$attachment[attachmentid]&amp;stc=1&amp;d=$attachment[thumbnail_dateline]\" target=\"_blank\"><img src=\"../attachment.php?" . $vbulletin->session->vars['sessionurl'] . "attachmentid=$attachment[attachmentid]&amp;thumb=1&amp;d=$attachment[dateline]\" border=\"0\" style=\"border: outset 1px #AAAAAA\" alt=\"\" /></a>");
			}
			else
			{
				print_label_row($vbphrase['image'], '<img src="../attachment.php?' . $vbulletin->session->vars['sessionurl'] . "attachmentid=$attachment[attachmentid]&amp;d=$attachment[dateline]\" border=\"0\" />");
			}
		}
		print_label_row($vbphrase['posted_by'], iif($attachment['username'], $attachment['username'], $attachment['postusername']). ' ' . construct_link_code($vbphrase['view_content'], $attachmultiple->fetch_content_url($attachment, '../'), 1));
		print_label_row($vbphrase['action'], "
			<label for=\"val_$attachment[attachmentid]\"><input type=\"radio\" name=\"attachaction[$attachment[attachmentid]]\" value=\"1\" id=\"val_$attachment[attachmentid]\" tabindex=\"1\" />" . $vbphrase['validate'] . "</label>
			<label for=\"del_$attachment[attachmentid]\"><input type=\"radio\" name=\"attachaction[$attachment[attachmentid]]\" value=\"-1\" id=\"del_$attachment[attachmentid]\" tabindex=\"1\" />" . $vbphrase['delete'] . "</label>
			<label for=\"ign_$attachment[attachmentid]\"><input type=\"radio\" name=\"attachaction[$attachment[attachmentid]]\" value=\"0\" id=\"ign_$attachment[attachmentid]\" tabindex=\"1\" checked=\"checked\" />" . $vbphrase['ignore'] . "</label>
		", '', 'top', 'attachaction');
		$done = true;
	}

	if (!$done)
	{
		print_description_row($vbphrase['no_attachments_awaiting_moderation']);
		print_table_footer();
	}
	else
	{
		print_submit_row();
	}
}


// ###################### Start do attachment moderation #######################
if ($_POST['do'] == 'doattachments')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'attachaction' => TYPE_ARRAY_INT
	));

	$deleteids = array();
	$approvedids = array();
	$finalapproveids = array();
	$finaldeleteids = array();
	foreach ($vbulletin->GPC['attachaction'] AS $attachmentid => $action)
	{
		if ($action == 0)
		{ // no point in checking the permission if they dont want to do anything to the attachment
			continue;
		}

		$attachmentid = intval($attachmentid);

		if ($action == 1)
		{ // validate
			$approveids[] = $attachmentid;
		}
		else if ($action == -1)
		{ // delete
			$deleteids[] = $attachmentid;
		}
	}

	if (!empty($approveids))
	{
		require_once(DIR . '/packages/vbattach/attach.php');
		$attachmultiple = new vB_Attachment_Display_Multiple($vbulletin);
		$attachments = $attachmultiple->fetch_results("a.attachmentid IN (" . implode(",", $approveids) . ") AND a.state = 'moderation' AND a.contentid <> 0", false, 0, 0);

		$db->query_write("
			UPDATE " . TABLE_PREFIX . "attachment
			SET	state = 'visible'
			WHERE attachmentid IN (" . implode(",", array_keys($attachments)) . ")
		");
		
		//Bootstrap to the vB Framework
		require_once(DIR . '/includes/class_bootstrap_framework.php');
		vB_Bootstrap_Framework::init();
		vB_Router::setRelativePath('../');
		$contenttypeid = vB_Types::instance()->getContentTypeID('vBForum_Album');
		$albums = array();
		
		//Fetchs only contentid from attachments that are album pictures
		$pictures = $db->query_read_slave($sql = "
		SELECT a.attachmentid, a.contentid
		FROM attachment as a
		WHERE a.attachmentid IN (" . implode(',', array_keys($attachments)) . ")
			AND a.contenttypeid = $contenttypeid
		");
		
		require_once(DIR . '/includes/functions_album.php');
		while($picture = $db->fetch_array($pictures))
		{
			// check if album has cover if not save the possible attachment for cover
			$album = fetch_albuminfo($picture['contentid']);
			if($album['coverattachmentid'] == 0)
			{
				$albums[$album['albumid']][] = $picture['attachmentid'];
			}
			else
			{
				$albums[$album['albumid']] = '';
			}
		}

		foreach ($albums as $albumid => $attachments)
		{
			$albumid = array('albumid' => $albumid);
			$albumdata =& datamanager_init('Album', $vbulletin, ERRTYPE_SILENT);
			$albumdata->set_existing($albumid);
			//look for possible covers
			if($attachments[0])
			{
				$albumdata->set('coverattachmentid',$attachments[0]);
			}
			//update albums
			$albumdata->rebuild_counts();
			$albumdata->save();
			unset($albumdata);
			exec_album_updated($vbulletin->userinfo, $albumid);
		}
	}

	if (!empty($deleteids))
	{
		require_once(DIR . '/packages/vbattach/attach.php');
		$attachmultiple = new vB_Attachment_Display_Multiple($vbulletin);
		$attachments = $attachmultiple->fetch_results("a.attachmentid IN (" . implode(",", $deleteids) . ") AND a.state = 'moderation' AND a.contentid <> 0", false, 0, 0);

		$attachdata =& datamanager_init('Attachment', $vbulletin, ERRTYPE_CP, 'attachment');
		$attachdata->condition = "a.attachmentid IN (" . implode(",", array_keys($attachments)) . ")";
		$attachdata->delete(true, false);
}

	define('CP_REDIRECT', 'moderate.php?do=attachments');
	print_stop_message('moderated_attachments_successfully');
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 44589 $
|| ####################################################################
\*======================================================================*/
?>
