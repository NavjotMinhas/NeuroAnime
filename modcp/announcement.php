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
define('CVS_REVISION', '$RCSfile$ - $Revision: 34547 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array('posting');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/adminfunctions_announcement.php');

// ############################# LOG ACTION ###############################
$vbulletin->input->clean_array_gpc('r', array(	'announcementid' => TYPE_INT));
log_admin_action(!empty($vbulletin->GPC['announcementid']) ? "announcement id = " . $vbulletin->GPC['announcementid'] : '');

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['announcement_manager']);

// ###################### Start add / edit #######################

if ($_REQUEST['do'] == 'add' OR $_REQUEST['do'] == 'edit')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'forumid' => TYPE_INT,
	));

	print_form_header('announcement', 'update');

	if ($_REQUEST['do'] == 'add')
	{
		$announcement = array(
			'startdate' => TIMENOW,
			'enddate' => (TIMENOW + 86400 * 31),
			'forumid' => $vbulletin->GPC['forumid'],
			'announcementoptions' => 29
		);
		print_table_header($vbphrase['post_new_announcement']);
	}
	else
	{
		// query announcement
		$announcement = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "announcement WHERE announcementid = " . $vbulletin->GPC['announcementid']);

		if ($retval = fetch_announcement_permission_error($announcement['forumid']))
		{
			print_table_header(fetch_announcement_permission_error_phrase($retval));
			print_table_break();
		}

		construct_hidden_code('announcementid', $vbulletin->GPC['announcementid']);
		print_table_header(construct_phrase($vbphrase['x_y_id_z'], $vbphrase['announcement'], htmlspecialchars_uni($announcement['title']), $announcement['announcementid']));

	}

	$issupermod = $permissions['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['ismoderator'];
	print_moderator_forum_chooser('forumid', $announcement['forumid'], $vbphrase['all_forums'], $vbphrase['forum_and_children'], iif($issupermod, true, false), false, false,'canannounce');
	print_input_row($vbphrase['title'], 'title', $announcement['title']);

	print_time_row($vbphrase['start_date'], 'startdate', $announcement['startdate'], 0);
	print_time_row($vbphrase['end_date'], 'enddate', $announcement['enddate'], 0);

	print_textarea_row($vbphrase['text'], 'pagetext', $announcement['pagetext'], 10, 50, 1, 0);

	if ($vbulletin->GPC['announcementid'])
	{
		print_yes_no_row($vbphrase['reset_views_counter'], 'reset_views', 0);
	}

	print_yes_no_row($vbphrase['allow_bbcode'], 'announcementoptions[allowbbcode]', ($announcement['announcementoptions'] & $vbulletin->bf_misc_announcementoptions['allowbbcode'] ? 1 : 0));
	print_yes_no_row($vbphrase['allow_smilies'], 'announcementoptions[allowsmilies]', ($announcement['announcementoptions'] & $vbulletin->bf_misc_announcementoptions['allowsmilies'] ? 1 : 0));
	print_yes_no_row($vbphrase['allow_html'], 'announcementoptions[allowhtml]', ($announcement['announcementoptions'] & $vbulletin->bf_misc_announcementoptions['allowhtml'] ? 1 : 0));
	print_yes_no_row($vbphrase['automatically_parse_links_in_text'], 'announcementoptions[parseurl]', ($announcement['announcementoptions'] & $vbulletin->bf_misc_announcementoptions['parseurl'] ? 1 : 0));
	print_yes_no_row($vbphrase['show_your_signature'], 'announcementoptions[signature]', ($announcement['announcementoptions'] & $vbulletin->bf_misc_announcementoptions['signature'] ? 1 : 0));

	print_submit_row(iif($_REQUEST['do'] == 'add', $vbphrase['add'], $vbphrase['save']));
}

// ###################### Start insert #######################
if ($_POST['do'] == 'update')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'startdate'           => TYPE_UNIXTIME,
		'enddate'             => TYPE_UNIXTIME,
		'forumid'             => TYPE_INT,
		'title'               => TYPE_STR,
		'pagetext'            => TYPE_STR,
		'announcementoptions' => TYPE_ARRAY_BOOL,
		'reset_views'         => TYPE_BOOL,
	));

	if ($retval = fetch_announcement_permission_error($vbulletin->GPC['announcement']['forumid']))
	{
		print_stop_message(fetch_announcement_permission_error_phrase($retval));
	}

	// query original data
	if ($vbulletin->GPC['announcementid'] AND (!$original_data = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "announcement WHERE announcementid = " . $vbulletin->GPC['announcementid'])))
	{
		if (!preg_match('#^(mailto:|http)#siU', $vbulletin->options['contactuslink']))
		{
			$vbulletin->options['contactuslink'] = '../' . $vbulletin->options['contactuslink'];
		}
		print_stop_message('invalidid', $vbphrase['announcement'], $vbulletin->options['contactuslink']);
	}

	if (!trim($vbulletin->GPC['title']))
	{
		$vbulletin->GPC['title'] = $vbphrase['announcement'];
	}

	$anncdata =& datamanager_init('Announcement', $vbulletin, ERRTYPE_CP);

	if ($vbulletin->GPC['announcementid'])
	{
		$anncdata->set_existing($original_data);

		if ($vbulletin->GPC['reset_views'])
		{
			define('RESET_VIEWS', true);
			$anncdata->set('views', 0);
		}
	}
	else
	{
		$anncdata->set('userid', $vbulletin->userinfo['userid']);
	}

	$anncdata->set('title', $vbulletin->GPC['title']);
	$anncdata->set('pagetext', $vbulletin->GPC['pagetext']);
	$anncdata->set('forumid', $vbulletin->GPC['forumid']);
	$anncdata->set('startdate', $vbulletin->GPC['startdate']);
	$anncdata->set('enddate', $vbulletin->GPC['enddate'] + 86399);

	foreach ($vbulletin->GPC['announcementoptions'] AS $key => $val)
	{
		$anncdata->set_bitfield('announcementoptions', $key, $val);
	}

	$announcementid = $anncdata->save();

	if ($original_data)
	{
		if ($vbulletin->GPC['reset_views'])
		{
			$db->query_write("DELETE FROM " . TABLE_PREFIX . "announcementread WHERE announcementid = " . $vbulletin->GPC['announcementid']);
		}
		$announcementid = $announcementinfo['announcementid'];
	}

	define('CP_REDIRECT', 'forum.php');
	print_stop_message('saved_announcement_x_successfully', htmlspecialchars_uni($vbulletin->GPC['title']));
}

// ###################### Start Remove #######################

if ($_REQUEST['do'] == 'remove')
{
	$announcement = $db->query_first("
		SELECT forumid
		FROM " . TABLE_PREFIX . "announcement
		WHERE announcementid = " . $vbulletin->GPC['announcementid'] . "
	");
	if ($retval = fetch_announcement_permission_error($announcement['forumid']))
	{
		print_stop_message(fetch_announcement_permission_error_phrase($retval));
	}

	print_delete_confirmation('announcement', $vbulletin->GPC['announcementid'], 'announcement', 'kill', 'announcement');
}

// ###################### Start Kill #######################

if ($_POST['do'] == 'kill')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'announcementid' 	=> TYPE_UINT
	));

	if ($announcement = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "announcement WHERE announcementid = " . $vbulletin->GPC['announcementid']))
	{
		if ($retval = fetch_announcement_permission_error($announcement['forumid']))
		{
			print_stop_message(fetch_announcement_permission_error_phrase($retval));
		}
		else
		{
			$anncdata =& datamanager_init('Announcement', $vbulletin, ERRTYPE_CP);
			$anncdata->set_existing($announcement);
			$anncdata->delete();
		}

		define('CP_REDIRECT', 'forum.php');
		print_stop_message('deleted_announcement_successfully');
	}
	else
	{
		print_stop_message('invalidid', $vbphrase['announcement'], $vbulletin->options['contactuslink']);
	}
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 34547 $
|| ####################################################################
\*======================================================================*/

?>