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

$VB_API_WHITELIST = array(
	'response' => array(
		'criteriaDisplay', 'displayCommon', 'searchtime', 'searchminutes',
		'first', 'last', 'total', 'search', 'pagenav' => $VB_API_WHITELIST_COMMON['pagenav'],
		'searchbits' => array(
			'*' => array(
				// Threadbit
				'post_statusicon',
				'userinfo' => array(
					'userid', 'username'
				),
				'thread' => $VB_API_WHITELIST_COMMON['thread'],
				'title', 'html_title', 'username', 'description',
				'parenttitle', 'parentid', 'previewtext',
				'publishtime', 'lastpostdatetime', 'lastposter',
				'lastposterinfo', 'avatar',
				// Blog
				'blog' => array(
					'blogid', 'username', 'userid', 'title',
					'blogtitle', 'previewtext', 'comments_total', 'trackbacks_total',
					'lastposttime', 'lastcommenter', 'time'
				), 'blogposter',
				// Forum
				'forum' => $VB_API_WHITELIST_COMMON['forum'],
				// Article
				'article' => array(
					'contentid', 'nodeid', 'username', 'userid'
				),
				'page_url', 'lastcomment_url', 'parent_url', 'parenttitle', 'replycount',
				'categories' => array(
					'*' => array(
						'category', 'category_url', 'categoryid'
					)
				),
				'tags' => array(
					'*' => array(
						'tagtext'
					)
				),
				// Postbit
				'post' => array(
					'postid', 'posttime', 'threadid', 'threadtitle',
					'userid', 'username', 'replycount', 'views', 'typeprefix',
					'prefix', 'prefix_rich', 'posticonpath', 'posttitle',
					'pagetext', 'message_plain'
				),
				'show' => array(
					'avatar', 'detailedtime',
					// Threadbit
					'threadcount', 'gotonewpost', 'unsubscribe', 'pagenavmore',
					'managethread', 'taglist', 'rexpires', 'moderated', 'deletedthread',
					'paperclip', 'notificationtype', 'deletereason', 'inlinemod',
					// Forum
					'deleted'
				)
			)
		)
	),
	'show' => array(
		'results'
	)
);
// format switch
if ($_REQUEST['apitextformat'])
{
	foreach ($VB_API_WHITELIST['response']['searchbits']['*']['post'] as $k => $v)
	{
		switch ($_REQUEST['apitextformat'])
		{
			case '1': // plain
				if ($v == 'message')
				{
					unset($VB_API_WHITELIST['response']['searchbits']['*']['post'][$k]);
				}
				break;
			case '2': // html
				if ($v == 'message_plain')
				{
					unset($VB_API_WHITELIST['response']['searchbits']['*']['post'][$k]);
				}
				break;
		}
	}
}

function api_result_prerender_2($t, &$r)
{
	switch ($t)
	{
		case 'search_results_postbit':
			$r['lastpostdatetime'] = $r['post']['lastpost'];
			break;
		case 'vbcms_searchresult_article_general':
			$r['publishtime'] = $r['publishdateline'];
			$r['lastpostdatetime'] = $r['lastpostdateline'];
			break;
		case 'blog_search_results_result':
			$r['blog']['lastposttime'] = $r['blog']['lastcomment'];
			$r['blog']['time'] = $r['blog']['dateline'];
			break;
	}
}

vB_APICallback::instance()->add('result_prerender', 'api_result_prerender_2', 2);

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35584 $
|| ####################################################################
\*======================================================================*/