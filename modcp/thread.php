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
define('CVS_REVISION', '$RCSfile$ - $Revision: 32878 $');
define('NOZIP', 1);

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('thread', 'threadmanage', 'prefix');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/adminfunctions_prefix.php');

@set_time_limit(0);

// ############################# LOG ACTION ###############################
$vbulletin->input->clean_array_gpc('r', array(
	'forumid' => TYPE_INT,
	'pollid'  => TYPE_INT,
	'type'    => TYPE_STR,
));
log_admin_action(iif(!empty($vbulletin->GPC['forumid']), "forum id = " . $vbulletin->GPC['forumid'], iif(!empty($vbulletin->GPC['pollid']), "poll id = " . $vbulletin->GPC['pollid'])));

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['thread_manager']);

if (!can_moderate(0, 'canmassmove') AND !can_moderate(0, 'canmassprune'))
{
	print_stop_message('no_permission');
}
else if ($_REQUEST['do'] != 'prune' AND $_REQUEST['do'] != 'move')
{
	$type = ($vbulletin->GPC['type']) == 'move' ? 'canmassmove' : 'canmassprune';
	if (!can_moderate(0, $type))
	{
		print_stop_message('no_permission');
	}

	// generate a list of valid forums that can be worked with for sanity purposes
	$forumids = array();
	foreach($vbulletin->forumcache AS $forum)
	{
		$perms = fetch_permissions($forum['forumid']);
		if (!($perms & $vbulletin->bf_ugp_forumpermissions['canview']))
		{
			continue;
		}
		if (empty($forum['link']))
		{
			if (can_moderate($forum['forumid'], $type))
			{
				$forumids["$forum[forumid]"] = $forum['forumid'];
			}
		}
	}

	if (empty($forumids))
	{	// shouldn't get here but just make sure
		print_stop_message('no_permission');
	}
}

// ###################### Function to generate move/prune input boxes #######################
function print_move_prune_rows($permcheck = '')
{
	global $vbphrase;

	print_description_row($vbphrase['date_options'], 0, 2, 'thead', 'center');
		print_input_row($vbphrase['original_post_date_is_at_least_xx_days_ago'], 'thread[originaldaysolder]', 0, 1, 5);
		print_input_row($vbphrase['original_post_date_is_at_most_xx_days_ago'] . '<dfn>' . construct_phrase($vbphrase['note_leave_x_specify_no_limit'], '0') . '</dfn>', 'thread[originaldaysnewer]', 0, 1, 5);
		print_input_row($vbphrase['last_post_date_is_at_least_xx_days_ago'], 'thread[lastdaysolder]', 0, 1, 5);
		print_input_row($vbphrase['last_post_date_is_at_most_xx_days_ago'] . '<dfn>' . construct_phrase($vbphrase['note_leave_x_specify_no_limit'], '0') . '</dfn>', 'thread[lastdaysnewer]', 0, 1, 5);

	print_description_row($vbphrase['view_options'], 0, 2, 'thead', 'center');
		print_input_row($vbphrase['thread_has_at_least_xx_replies'], 'thread[repliesleast]', 0, 1, 5);
		print_input_row($vbphrase['thread_has_at_most_xx_replies'] . '<dfn>' . construct_phrase($vbphrase['note_leave_x_specify_no_limit'], '-1') . '</dfn>', 'thread[repliesmost]', -1, 1, 5);
		print_input_row($vbphrase['thread_has_at_least_xx_views'], 'thread[viewsleast]', 0, 1, 5);
		print_input_row($vbphrase['thread_has_at_most_xx_views'] . '<dfn>' . construct_phrase($vbphrase['note_leave_x_specify_no_limit'], '-1') . '</dfn>', 'thread[viewsmost]', -1, 1, 5);

	print_description_row($vbphrase['status_options'], 0, 2, 'thead', 'center');
		print_yes_no_other_row($vbphrase['thread_is_sticky'], 'thread[issticky]', $vbphrase['either'], 0);

		$state = array(
			'visible' => $vbphrase['visible'],
			'moderation' => $vbphrase['awaiting_moderation'],
			'deleted' => $vbphrase['deleted'],
			'any' => $vbphrase['any']
		);
		print_radio_row($vbphrase['thread_state'], 'thread[state]', $state, 'any');

		$status = array(
			'open' => $vbphrase['open'],
			'closed' => $vbphrase['closed'],
			'redirect' => $vbphrase['redirect'],
			'not_redirect' => $vbphrase['not_redirect'],
			'any' => $vbphrase['any']
		);
		print_radio_row($vbphrase['thread_status'], 'thread[status]', $status, 'not_redirect');

	print_description_row($vbphrase['other_options'], 0, 2, 'thead', 'center');
		print_input_row($vbphrase['username'], 'thread[posteduser]');
		print_input_row($vbphrase['title'], 'thread[titlecontains]');
		print_moderator_forum_chooser('thread[forumid]', -1, $vbphrase['all_forums'], $vbphrase['forum'], true, false, true, $permcheck);
		print_yes_no_row($vbphrase['include_child_forums'], 'thread[subforums]');

		if ($prefix_options = construct_prefix_options(0, '', true, true))
		{
			print_label_row($vbphrase['prefix'], '<select name="thread[prefixid]" class="bginput">' . $prefix_options . '</select>', '', 'top', 'prefixid');
		}
}

// ###################### Function to generate move/prune query #######################
function fetch_thread_move_prune_sql($thread, &$forumids, $type)
{
	global $vbulletin, $vbphrase;

	$query = '1=1';

	$thread['forumid'] = intval($thread['forumid']);

	// original post
	if (intval($thread['originaldaysolder']))
	{
		$query .= ' AND thread.dateline <= ' . (TIMENOW - ($thread['originaldaysolder'] * 86400));
	}
	if (intval($thread['originaldaysnewer']))
	{
		$query .= ' AND thread.dateline >= ' . (TIMENOW - ($thread['originaldaysnewer'] * 86400));
	}

	// last post
	if (intval($thread['lastdaysolder']))
	{
		$query .= ' AND thread.lastpost <= ' . (TIMENOW - ($thread['lastdaysolder'] * 86400));
	}
	if (intval($thread['lastdaysnewer']))
	{
		$query .= ' AND thread.lastpost >= ' . (TIMENOW - ($thread['lastdaysnewer'] * 86400));
	}

	// replies
	if (intval($thread['repliesleast']) > 0)
	{
		$query .= ' AND thread.replycount >= ' . intval($thread['repliesleast']);
	}
	if (intval($thread['repliesmost']) > -1)
	{
		$query .= ' AND thread.replycount <= ' . intval($thread['repliesmost']);
	}

	// views
	if (intval($thread['viewsleast']) > 0)
	{
		$query .= ' AND thread.views >= ' . intval($thread['viewsleast']);
	}
	if (intval($thread['viewsmost']) > -1)
	{
		$query .= ' AND thread.views <= ' . intval($thread['viewsmost']);
	}

	// sticky
	if ($thread['issticky'] == 1)
	{
		$query .= ' AND thread.sticky = 1';
	}
	else if ($thread['issticky'] == 0)

	{
		$query .= ' AND thread.sticky = 0';
	}

	// state
	switch ($thread['state'])
	{
		case 'visible':
			$query .= ' AND thread.visible = 1';
			break;

		case 'moderation':
			$query .= ' AND thread.visible = 0';
			break;

		case 'deleted':
			$query .= ' AND thread.visible = 2';
			break;
	}

	//status
	switch ($thread['status'])
	{
		case 'open':
			$query .= ' AND thread.open = 1';
			break;

		case 'closed':
			$query .= ' AND thread.open = 0';
			break;

		case 'redirect':
			$query .= ' AND thread.open = 10';
			break;

		case 'not_redirect':
			$query .= ' AND thread.open <> 10';
			break;
	}

	// posted by
	if ($thread['posteduser'])
	{
		$user = $vbulletin->db->query_first("
			SELECT userid
			FROM " . TABLE_PREFIX . "user
			WHERE username = '" . $vbulletin->db->escape_string(htmlspecialchars_uni($thread['posteduser'])) . "'
		");
		if (!$user)
		{
			print_stop_message('invalid_user_specified');
		}
		$query .= " AND thread.postuserid = $user[userid]";
	}

	// title contains
	if ($thread['titlecontains'])
	{
		$query .= " AND thread.title LIKE '%" . $vbulletin->db->escape_string_like(htmlspecialchars_uni($thread['titlecontains'])) . "%'";
	}

	// forum
	$thread['forumid'] = intval($thread['forumid']);

	$threadtype = ($type == 'move' ? 'canmassmove' : 'canmassprune');

	if ($thread['forumid'] == -1)	// All Forums
	{
		$query .= " AND thread.forumid IN (-1," . implode(',', $forumids) . ")";
	}
	else if ($thread['subforums'])
	{
		$query .= " AND (thread.forumid = $thread[forumid] OR forum.parentlist LIKE '%,$thread[forumid],%') AND thread.forumid IN (". implode(',', $forumids) . ")";
	}
	else
	{
		// could check that $forumids[$thread[forumid]] exists here and throw an error but this is cleaner since this should be ok unless the mod
		// manipulates a form or if they happen to lose access the moment they perform an action
		$query .= " AND thread.forumid = $thread[forumid] AND thread.forumid IN (" . implode(',', $forumids) . ")";
	}

	// prefixid
	switch ($thread['prefixid'])
	{
		case '': // any prefix, no limit
			break;

		case '-1': // none
			$query .= " AND thread.prefixid = ''";
			break;

		default: // a prefix
			$query .= " AND thread.prefixid = '" . $vbulletin->db->escape_string($thread['prefixid']) . "'";
			break;
	}

	return $query;
}

// ###################### Start Prune #######################
if ($_REQUEST['do'] == 'prune')
{

	if (!can_moderate(0, 'canmassprune'))
	{
		print_stop_message('no_permission');
	}

	print_form_header('', '');
	print_table_header($vbphrase['prune_threads_manager']);
	print_description_row($vbphrase['pruning_many_threads_is_a_server_intensive_process']);
	print_table_footer();

	print_form_header('thread', 'dothreads');
	construct_hidden_code('type', 'prune');
	print_move_prune_rows('canmassprune');
	print_submit_row($vbphrase['prune_threads']);
}

// ###################### Start Move #######################
if ($_REQUEST['do'] == 'move')
{

	if (!can_moderate(0, 'canmassmove'))
	{
		print_stop_message('no_permission');
	}

	print_form_header('thread', 'dothreads');
	construct_hidden_code('type', 'move');
	print_table_header($vbphrase['move_threads']);
	print_moderator_forum_chooser('destforumid', -1, '', $vbphrase['destination_forum'], false, false, true, 'none');
	print_move_prune_rows('canmassmove');
	print_submit_row($vbphrase['move_threads']);
}

// ###################### Start thread move/prune by options #######################
if ($_POST['do'] == 'dothreads')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'thread'      => TYPE_ARRAY,
		'destforumid' => TYPE_INT
	));

	if ($vbulletin->GPC['thread']['forumid'] == 0)
	{
		print_stop_message('please_complete_required_fields');
	}

	$whereclause = fetch_thread_move_prune_sql($vbulletin->GPC['thread'], $forumids, $vbulletin->GPC['type']);

	if ($vbulletin->GPC['type'] == 'move')
	{
		$foruminfo = fetch_foruminfo($vbulletin->GPC['destforumid']);
		if (!$foruminfo)
		{
			print_stop_message('invalid_destination_forum_specified');
		}
		if (!$foruminfo['cancontainthreads'] OR $foruminfo['link'])
		{
			print_stop_message('destination_forum_cant_contain_threads');
		}
	}

	$fullquery = "
		SELECT COUNT(*) AS count
		FROM " . TABLE_PREFIX . "thread AS thread
		LEFT JOIN " . TABLE_PREFIX . "forum AS forum ON(forum.forumid = thread.forumid)
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(deletionlog.primaryid = thread.threadid AND deletionlog.type = 'thread')
		WHERE $whereclause
	";
	$count = $db->query_first($fullquery);

	if (!$count['count'])
	{
		print_stop_message('no_threads_matched_your_query');
	}

	print_form_header('thread', 'dothreadsall');
	construct_hidden_code('type', $vbulletin->GPC['type']);
	construct_hidden_code('criteria', sign_client_string(serialize($vbulletin->GPC['thread'])));

	print_table_header(construct_phrase($vbphrase['x_thread_matches_found'], $count['count']));
	if ($vbulletin->GPC['type'] == 'prune')
	{
		print_submit_row($vbphrase['prune_all_threads'], '');
	}
	else
	{
		construct_hidden_code('destforumid', $vbulletin->GPC['destforumid']);
		print_submit_row($vbphrase['move_all_threads'], '');
	}

	print_form_header('thread', 'dothreadssel');
	construct_hidden_code('type', $vbulletin->GPC['type']);
	construct_hidden_code('criteria', sign_client_string(serialize($vbulletin->GPC['thread'])));
	print_table_header(construct_phrase($vbphrase['x_thread_matches_found'], $count['count']));
	if ($vbulletin->GPC['type'] == 'prune')
	{
		print_submit_row($vbphrase['prune_threads_selectively'], '');
	}
	else
	{
		construct_hidden_code('destforumid', $vbulletin->GPC['destforumid']);
		print_submit_row($vbphrase['move_threads_selectively'], '');
	}
}

// ###################### Start move/prune all matching #######################
if ($_POST['do'] == 'dothreadsall')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'criteria'    => TYPE_BINARY,
		'destforumid' => TYPE_INT
	));

	$thread = @unserialize(verify_client_string($vbulletin->GPC['criteria']));
	if (!is_array($thread) OR sizeof($thread) == 0)
	{
		print_stop_message('please_complete_required_fields');
	}

	$whereclause = fetch_thread_move_prune_sql($thread, $forumids, $vbulletin->GPC['type']);

	$fullquery = "
		SELECT thread.*
		FROM " . TABLE_PREFIX . "thread AS thread
		LEFT JOIN " . TABLE_PREFIX . "forum AS forum ON(forum.forumid = thread.forumid)
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(deletionlog.primaryid = thread.threadid AND deletionlog.type = 'thread')
		WHERE $whereclause
	";
	$threads = $db->query_read($fullquery);

	if ($vbulletin->GPC['type'] == 'prune')
	{
		require_once(DIR . '/includes/functions_databuild.php');

		echo '<p>' . $vbphrase['deleting_threads'];
		while ($thread = $db->fetch_array($threads))
		{
			$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
			$threadman->set_existing($thread);
			$threadman->delete(0);
			unset($threadman);

			echo ". \n";
			vbflush();
		}
		echo $vbphrase['done'] . '</p>';

		define('CP_REDIRECT', 'index.php?do=home');
		print_stop_message('pruned_threads_successfully_modcp');
	}
	else //if ($vbulletin->GPC['type'] == 'move')
	{
		$threadslist = '0';
		while ($thread = $db->fetch_array($threads))
		{
			$threadslist .= ",$thread[threadid]";
		}

		$db->query_write("
			UPDATE " . TABLE_PREFIX . "thread SET
				forumid = " . $vbulletin->GPC['destforumid'] . "
			WHERE threadid IN ($threadslist)
		");

		$vbulletin->db->query_write("TRUNCATE TABLE " . TABLE_PREFIX . "postparsed");

		require_once(DIR . '/includes/functions_prefix.php');
		remove_invalid_prefixes($threadslist, $vbulletin->GPC['destforumid']);

		require_once(DIR . '/includes/functions_databuild.php');
		build_forum_counters($vbulletin->GPC['destforumid']);

		define('CP_REDIRECT', 'index.php?do=home');
		print_stop_message('moved_threads_successfully_modcp');
	}
}

// ###################### Start move/prune select #######################
if ($_POST['do'] == 'dothreadssel')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'criteria'    => TYPE_BINARY,
		'destforumid' => TYPE_INT
	));

	$thread = @unserialize(verify_client_string($vbulletin->GPC['criteria']));
	if (!is_array($thread) OR sizeof($thread) == 0)
	{
		print_stop_message('please_complete_required_fields');
	}

	$whereclause = fetch_thread_move_prune_sql($thread, $forumids, $vbulletin->GPC['type']);

	$fullquery = "
		SELECT thread.*, forum.title AS forum_title
		FROM " . TABLE_PREFIX . "thread AS thread
		LEFT JOIN " . TABLE_PREFIX . "forum AS forum ON(forum.forumid = thread.forumid)
		LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(deletionlog.primaryid = thread.threadid AND deletionlog.type = 'thread')
		WHERE $whereclause
	";
	$threads = $db->query_read($fullquery);

	print_form_header('thread', 'dothreadsselfinish');
	construct_hidden_code('type', $vbulletin->GPC['type']);
	construct_hidden_code('destforumid', $vbulletin->GPC['destforumid']);
	if ($vbulletin->GPC['type'] == 'prune')
	{
		print_table_header($vbphrase['prune_threads_selectively'], 5);
	}
	else if ($vbulletin->GPC['type'] == 'move')
	{
		print_table_header($vbphrase['move_threads_selectively'], 5);
	}
	print_cells_row(array(
		'<input type="checkbox" name="allbox" title="' . $vbphrase['check_all'] . '" onClick="js_check_all(this.form);" checked="checked" />',
		$vbphrase['title'],
		$vbphrase['posted_by'],
		$vbphrase['replies'],
		$vbphrase['last_post']
	), 1);

	while ($thread = $db->fetch_array($threads))
	{
		$thread['prefix_plain_html'] = ($thread['prefixid'] ? htmlspecialchars_uni($vbphrase["prefix_$thread[prefixid]_title_plain"]) : '');

		$cells = array();
		$cells[] = "<input type=\"checkbox\" name=\"thread[$thread[threadid]]\" tabindex=\"1\" checked=\"checked\" />";
		$cells[] = $thread['prefix_plain_html'] . ' <a href="../' . fetch_seo_url('thread', $thread) . "\" target=\"_blank\">$thread[title]</a>";
		if ($thread['postuserid'])
		{
			$cells[] = '<span class="smallfont"><a href="../' . fetch_seo_url('member', array('u' => $thread['postuserid'], 'username' => $thread['postusername'])) . "\">$thread[postusername]</a></span>";
		}
		else
		{
			$cells[] = '<span class="smallfont">' . $thread['postusername'] . '</span>';
		}
		$cells[] = "<span class=\"smallfont\">$thread[replycount]</span>";
		$cells[] = '<span class="smallfont">' . vbdate($vbulletin->options['dateformat'] . ' ' . $vbulletin->options['timeformat'], $thread['lastpost']) . '</span>';
		print_cells_row($cells, 0, 0, -1);
	}
	if ($vbulletin->GPC['type'] == 'prune')
	{
		print_submit_row($vbphrase['prune_threads'], NULL, 5);
	}
	else if ($vbulletin->GPC['type'] == 'move')
	{
		print_submit_row($vbphrase['move_threads'], NULL, 5);
	}
}

// ###################### Start move/prune select - finish! #######################
if ($_POST['do'] == 'dothreadsselfinish')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'thread'      => TYPE_ARRAY,
		'destforumid' => TYPE_INT
	));

	if (!empty($vbulletin->GPC['thread']))
	{
		require_once(DIR . '/includes/functions_databuild.php');
		if ($vbulletin->GPC['type'] == 'prune')
		{
			echo '<p>' . $vbphrase['deleting_threads'];
			foreach ($vbulletin->GPC['thread'] AS $threadid => $confirm)
			{
				$threadinfo = fetch_threadinfo($threadid);
				if (empty($forumids["$threadinfo[forumid]"]))
				{	// make sure we have access to prune / move this thread, if not then skip it
					continue;
				}

				$threadman =& datamanager_init('Thread', $vbulletin, ERRTYPE_SILENT, 'threadpost');
				$threadman->set_existing($threadinfo);
				$threadman->delete(0);
				unset($threadman);

				echo ". \n";
				vbflush();
			}
			echo $vbphrase['done'] . '</p>';

			define('CP_REDIRECT', 'index.php?do=home');
			print_stop_message('pruned_threads_successfully_modcp');
		}
		else if ($vbulletin->GPC['type'] == 'move')
		{
			$threadslist = '0';
			foreach ($vbulletin->GPC['thread'] AS $threadid => $confirm)
			{
				$threadslist .= ',' . intval($threadid);
			}

			$db->query_write("
				UPDATE " . TABLE_PREFIX . "thread SET
					forumid = " . $vbulletin->GPC['destforumid'] . "
				WHERE threadid IN ($threadslist)
					AND forumid IN (" . implode(',', $forumids) . ")
			");

			$vbulletin->db->query_write("TRUNCATE TABLE " . TABLE_PREFIX . "postparsed");

			require_once(DIR . '/includes/functions_prefix.php');
			remove_invalid_prefixes($threadslist, $vbulletin->GPC['destforumid']);

			require_once(DIR . '/includes/functions_databuild.php');
			build_forum_counters($vbulletin->GPC['destforumid']);

			define('CP_REDIRECT', 'index.php?do=home');
			print_stop_message('moved_threads_successfully_modcp');
		}
	}
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>