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

define('VB_API_LOADLANG', true);

loadCommonWhiteList();

$VB_API_WHITELIST = array(
	'response' => array(
		'content' => array(
			'blogheader',
			'blog' => $VB_API_WHITELIST_COMMON['blog'],
			'bloginfo' => $VB_API_WHITELIST_COMMON['bloginfo'],
			'blogtextinfo',
			'bookmarksites' => $VB_API_WHITELIST_COMMON['bookmarksites'],
			'effective_lastcomment', 'next', 'pagenav', 'prev',
			'responsebits' => $VB_API_WHITELIST_COMMON['responsebits'],
			'status',
			'trackbackbits' => array(
				'*' => array(
					'response' => array(
						'blogtrackbackid', 'checkbox_value', 'url', 'title', 'date',
						'time', 'snippet'
					)
				)
			)
		)
	),
	'show' => array(
		'moderatecomments', 'pingback', 'trackback', 'notify', 'blognav',
		'postcomment', 'rating', 'pingbacklink', 'trackbackrdf', 'blogsubscribed',
		'entrysubscribed', 'trackback_moderation', 'comment_moderation',
		'edit', 'delete', 'remove', 'undelete', 'approve', 'inlinemod', 'status',
		'private', 'entryedited', 'tags', 'tag_edit', 'notags', 'attachments',
		'reportlink', 'readmore', 'ignoreduser', 'blograting', 'rateblog',
		'trackbacks', 'entryonly', 'privateentry'
	)
);

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35584 $
|| ####################################################################
\*======================================================================*/