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
$phrasegroups = array('thread');
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');

// ############################# LOG ACTION ###############################
$vbulletin->input->clean_array_gpc('r', array('forumid' => TYPE_INT));
log_admin_action(!empty($vbulletin->GPC['forumid']) ? "forum id = " . $vbulletin->GPC['forumid'] : '');

// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['view_deleted_posts']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'view';
}

// ###################### Start View #######################
if ($_REQUEST['do'] == 'view')
{
	print_form_header('deletedposts', 'doview');
	print_table_header($vbphrase['view_deleted_posts']);
	print_moderator_forum_chooser('forumid', -1, $vbphrase['all_forums'], $vbphrase['forum']);
	print_select_row($vbphrase['view'], 'view', array($vbphrase['threads'], $vbphrase['posts']));
	print_select_row($vbphrase['order_by'], 'orderby', array($vbphrase['date'], $vbphrase['user'], $vbphrase['forum']));
	print_select_row($vbphrase['order'], 'order', array($vbphrase['ascending'], $vbphrase['descending']));
	print_submit_row($vbphrase['submit']);
}

// ###################### Do View ##########################
if ($_POST['do'] == 'doview')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'view'    => TYPE_INT,
		'orderby' => TYPE_INT,
		'order'   => TYPE_INT
	));

	if (!$vbulletin->GPC['forumid'])
	{
		print_stop_message('please_complete_required_fields');
	}
	else if ($vbulletin->GPC['forumid'] != -1 AND !can_moderate($vbulletin->GPC['forumid']))
	{
		print_stop_message('no_permission');
	}

	// gather forums that this person is a moderator of
	if ($vbulletin->GPC['forumid'] == -1)
	{
		$forumids = fetch_moderator_forum_list_sql();
	}
	else
	{
		$forumids = " OR thread.forumid = " . $vbulletin->GPC['forumid'];
	}

	switch($vbulletin->GPC['orderby'])
	{
		case 0:
			$vbulletin->GPC['orderby'] = 'postdateline';
			break;
		case 1:
			$vbulletin->GPC['orderby'] = 'postusername, postdateline';
			break;
		case 2:
			$vbulletin->GPC['orderby'] = 'forumid, postdateline';
			break;
		default:
			$vbulletin->GPC['orderby'] = 'postdateline';
	}

 	$vbulletin->GPC['orderby'] .= iif($vbulletin->GPC['order'], ' ASC', ' DESC');


	if (!$vbulletin->GPC['view']) // threads
	{
		$threads = $db->query_read("
			SELECT thread.threadid, title, postuserid AS userid, postusername, thread.dateline AS postdateline,
				deletionlog.userid AS del_userid, deletionlog.username AS del_username, deletionlog.reason AS del_reason, thread.forumid
			FROM " . TABLE_PREFIX . "thread AS thread
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(thread.threadid = deletionlog.primaryid AND deletionlog.type = 'thread')
			WHERE (1 = 0 $forumids) AND deletionlog.primaryid IS NOT NULL
			ORDER BY " . $vbulletin->GPC['orderby'] . "
		");

		if (!$db->num_rows($threads))
		{
			print_stop_message('no_matches_found');
		}

		print_form_header('', '');
		print_table_header($vbphrase['deleted_threads']);
		print_table_break();

		while ($thread = $db->fetch_array($threads))
		{
				print_label_row('<b>' . $vbphrase['thread'] . '</b>', construct_link_code($thread['title'], '../' . fetch_seo_url('thread', $thread), 1));
				print_label_row('<b>' . $vbphrase['posted_by'] . '</b>', "$thread[postusername] (" . vbdate($vbulletin->options['dateformat'] . ' ' . $vbulletin->options['timeformat'], $thread['postdateline']) . ')');
				print_label_row('<b>' . $vbphrase['deleted_by'] . '</b>', $thread['del_username']);
				print_label_row('<b>' . $vbphrase['reason'] . '</b>', $thread['del_reason']);
				print_table_break();
		}
	}
	else
	{
		$posts = $db->query_read("
			SELECT post.postid, post.title, post.userid, post.username AS postusername, post.dateline AS postdateline,
				deletionlog.userid AS del_userid, deletionlog.username AS del_username, deletionlog.reason AS del_reason, forumid,
				thread.title AS threadtitle, post.threadid, pagetext, allowsmilie
			FROM " . TABLE_PREFIX . "post AS post
			LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON(post.postid = deletionlog.primaryid AND deletionlog.type = 'post')
			LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON(thread.threadid = post.threadid)
			WHERE (1 = 0 $forumids) AND deletionlog.primaryid IS NOT NULL
			ORDER BY " . $vbulletin->GPC['orderby'] . "
		");

		if (!$db->num_rows($posts))
		{
			print_stop_message('no_matches_found');
		}

		print_form_header('', '');
		print_table_header($vbphrase['deleted_posts']);
		print_table_break();

		while($post = $db->fetch_array($posts))
		{
			print_label_row('<b>' . $vbphrase['post'] . '</b>', construct_link_code(iif($post['title'], $post['title'], $vbphrase['n_a']), '../' . fetch_seo_url('thread', $post, array('p' => $postinfo['postid']), 'threadid', 'threadtitle') . '#post$post[postid]', 1));
			print_label_row('<b>' . $vbphrase['thread'] . '</b>', construct_link_code($post['threadtitle'], '../' . fetch_seo_url('thread', $post, null, 'threadid', 'threadtitle'), 1));
			print_label_row('<b>' . $vbphrase['posted_by'] . '</b>', "$post[postusername] (" . vbdate($vbulletin->options['dateformat'] . ' ' . $vbulletin->options['timeformat'], $post['postdateline']) . ')');
			print_label_row('<b>' . $vbphrase['deleted_by'] . '</b>', $post['del_username']);
			print_label_row('<b>' . $vbphrase['reason'] . '</b>', $post['del_reason']);
			print_label_row('<b>' . $vbphrase['post'] . '</b>', htmlspecialchars_uni($post['pagetext']));
			print_table_break();
		}
	}

	print_table_footer();
}

print_cp_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>