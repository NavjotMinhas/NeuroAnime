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

$VB_API_ROUTE_SEGMENT_WHITELIST = array(
	'action' => array (
		'view', 'edit', 'addcontent'
	)
);

loadCommonWhiteList();

global $methodsegments;

// $methodsegments[1] 'action'
if ($methodsegments[1] == 'view')
{
	$VB_API_WHITELIST = array(
		'response' => array(
			'layout' => array(
				'content' => array(
					'nodeid', 'title', 'page_url', 'publishdatelocal',
					'publishtimelocal', 'filter_unpublished_url', 'section_list_url',
					'no_results_phrase', 'pagenav', 'class', 'package', 'result_count',
					'can_publish', 'published', 'setpublish', 'publishdate', 'showall',
					'content' => array(
						'contents' => array(
							'*' => array(
								'id', 'node', 'title', 'authorid', 'authorname', 'page_url', 'showtitle', 'can_edit',
								'showuser', 'showpublishdate', 'viewcount', 'showviewcount',
								'showrating', 'publishdate', 'setpublish', 'publishdatelocal',
								'publishtimelocal', 'showupdated', 'lastupdated', 'dateformat',
								'rating', 'category', 'section_url', 'previewvideo', 'showpreviewonly',
								'previewimage', 'previewtext', 'preview_chopped', 'newcomment_url',
								'comment_count', 'ratingnum', 'ratingavg', 'avatar'
							)
						)
					),
					'userid', 'username', 'rating', 'ratingnum', 'ratingavg', 'node', 'votechecked',
					'showrating',
					'comment_block' => array(
						'nodeid', 'threadid', 'pageno',
						'node_comments' => array(
							'pagenav',
							'cms_comments' => array(
								'*' => array(
									'postid',
									'postbit' => array(
										'post' => array(
											'postid', 'avatarurl', 'userid', 'username', 'postdate',
											'posttime', 'message', 'message_bbcode', 'message_plain', 'editlink', 'replylink'
										)
									)
								)
							)
						)
					),
					'showpublishdate', 'showupdated', 'lastupdated',
					'viewcount', 'showviewcount', 'dateformat', 'comment_count', 'next_page_url',
					'prev_page_url', 'pagelist', 'pagetext', 'thumbnailattachments', 'imageattachments',
					'imageattachmentlinks', 'otherattachments', 'threadinfo', 'postitle',
					'poststarter', 'postauthor', 'postid', 'promoted_blogid', 'category',
					'contentid', 'showtags', 'tag_count', 'tags'
				)
			)
		)
	);
}
if ($methodsegments[1] == 'edit' OR $methodsegments[1] == 'addcontent')
{
	$VB_API_WHITELIST = array(
		'response' => array(
			'content' => array(
				'content' => array(
					'type', 'title', 'contenttypetitle', 'id', 'userid', 'username', 'class',
					'url', 'contentid', 'nodeid', 'tags', 'previewtext',
					'editor' => array(
						'attachmentoption' => $VB_API_WHITELIST_COMMON['attachmentoption'],
						'messagearea' => array(
							'newpost'
						),
						'nodeid', 'posthash', 'poststarttime', 'contentid', 'contenttypeid', 'values'
					),
					'contenttypeid', 'item_type', 'item_class', 'item_id',
					'publisher' => array(
						'publishdate', 'calendardateformat', 'username', 'node', 'sectionid',
						'categories' => array(
							'*' => array(
								'categoryid', 'checked', 'text', 'title'
							)
						),
						'groups' => array(
							'*' => array(
								'usergroupid', 'title'
							)
						),
						'nodelist' => array(
							'*' => array(
								'nodeid', 'selected', 'parent', 'leaf'
							)
						),
						'setpublish', 'show24', 'hour', 'minute', 'offset', 'showpreview',
						'showcomments', 'comments_enabled', 'publicpreview', 'show_showsettings',
						'showtitle', 'showuser', 'showpublishdate', 'showpreviewonly', 'showupdated',
						'showviewcount', 'showrating', 'settingsforboth', 'show_htmloption',
						'htmloption', 'show_hidden', 'hidden', 'show_pagination_link', 'pagination_links',
						'is_section', 'show_shownav', 'shownav', 'nosearch', 'show_categories'
					),
					'metadata' => array(
						'html_title', 'description', 'keywords'
					),
					'editbar' => array(
						'formid', 'view_url', 'submit_url', 'is_section', 'candelete'
					), 'view_url',
					'can_edit', 'showtags',
					// Section
					'style_select', 'display_order_select', 'content_layout_select', 'per_page', 'sections',
					'displayorder_array', 'perpage_select',
					'nodes' => array(
						'*' => array(
							'nodeid', 'sequence', 'view_url', 'title', 'class', 'parenttitle', 'prev_checked',
							'published_select', 'author', 'pub_date', 'viewcount', 'replycount'
						)
					),
					'pagination'
				)
			)
		),
		'bbuserinfo' => array(
			'userid', 'username'
		)
	);
}


function api_result_prerender_1($t, &$r)
{
	global $vbulletin;
	switch ($t)
	{
		case 'vbcms_content_article_page':
		case 'vbcms_content_article_preview':
			$r['publishdate'] = $r['publishdateline'];
			break;
	}
}

vB_APICallback::instance()->add('result_prerender', 'api_result_prerender_1', 1);

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35584 $
|| ####################################################################
\*======================================================================*/