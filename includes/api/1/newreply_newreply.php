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
if (!VB_API) die;

loadCommonWhiteList();

$VB_API_WHITELIST = array(
	'response' => array(
		'attachmentoption' => $VB_API_WHITELIST_COMMON['attachmentoption'],
		'disablesmiliesoption', 'emailchecked',
		'folderbits', 'checked', 'multiquote_empty', 'rate',
		'forumrules', 'human_verify', 'posthash', 'posticons', 'postpreview',
		'poststarttime', 'prefix_options', 'selectedicon', 'title',
		'htmloption', 'specifiedpost',
		'threadreviewbits' => array(
			'*' => array(
				'postdate', 'posttime', 'reviewmessage', 'reviewtitle',
				'post' => array(
					'postid', 'threadid', 'username', 'userid'
				)
			)
		),
		'unquoted_post_count', 'return_node',
		'threadinfo' => $VB_API_WHITELIST_COMMON['threadinfo']
	),
	'vboptions' => array(
		'postminchars', 'titlemaxchars', 'maxposts'
	),
	'show' => array(
		'posticons', 'smiliebox', 'attach', 'threadrating', 'openclose', 'stickunstick',
		'closethread', 'unstickthread', 'subscribefolders', 'reviewmore',
		'parseurl', 'misc_options', 'additional_options'
	)
);

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35584 $
|| ####################################################################
\*======================================================================*/