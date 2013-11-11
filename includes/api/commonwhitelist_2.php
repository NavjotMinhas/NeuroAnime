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

// This file defines common whitelist for API method to reuse.
$VB_API_WHITELIST_COMMON['humanverify'] = array(
	'humanverify' => array('hash', 'question', 'publickey', 'theme', 'langcode'),
	'var_prefix' => '*'
);

$VB_API_WHITELIST_COMMON['customfield'] = array(
	'custom_field_holder' => array(
		'optionalfield' => array(
			'optional', 'optionalname'
		),
		'profilefield' => array(
			'type', 'editable', 'optional', 'title', 'description', 'required', 'currentvalue'
		),
		'profilefieldname', 'radiobits', 'selectbits'
	),
	'show' => array(
		'noemptyoption'
	)
);

$VB_API_WHITELIST_COMMON['post'] = array(
	'announcementid',
	'postid', 'statusicon', 'posttime',
	'threadid', 'postcount', 'checkbox_value', 'onlinestatusphrase', 'userid', 'username',
	'avatarurl',
	'onlinestatus' => array('onlinestatus'),
	'usertitle', 'rank',
	'reputationdisplay' => array(
		'*' => array(
			'posneg',
			'post' => array('username', 'level')
		),
	),
	'joindate', 'field2', 'age', 'warnings', 'infractions', 'ipoints',
	'reppower', 'title', 'iconpath', 'icontitle', 'isfirstshown', 'islastshown',
	'message', 'message_plain', 'message_bbcode', 'thumbnailattachments', 'imageattachments', 'imageattachmentlinks',
	'otherattachments', 'moderatedattachments', 'edit_username',
	'edit_time', 'edit_reason', 'signature', 'del_userid', 'del_username', 'del_reason'
);
if ($_REQUEST['apitextformat'])
{
	foreach ($VB_API_WHITELIST_COMMON['post'] as $k => $v)
	{
		switch ($_REQUEST['apitextformat'])
		{
			case '1': // plain
				if ($v == 'message' OR $v == 'message_bbcode')
				{
					unset($VB_API_WHITELIST_COMMON['post'][$k]);
				}
				break;
			case '2': // html
				if ($v == 'message_plain' OR $v == 'message_bbcode')
				{
					unset($VB_API_WHITELIST_COMMON['post'][$k]);
				}
				break;
			case '3': // bbcode
				if ($v == 'message' OR $v == 'message_plain')
				{
					unset($VB_API_WHITELIST_COMMON['post'][$k]);
				}
				break;
			case '4': // plain & html
				if ($v == 'message_bbcode')
				{
					unset($VB_API_WHITELIST_COMMON['post'][$k]);
				}
				break;
			case '5': // bbcode & html
				if ($v == 'message_plain')
				{
					unset($VB_API_WHITELIST_COMMON['post'][$k]);
				}
				break;
			case '6': // plain & bbcode
				if ($v == 'message')
				{
					unset($VB_API_WHITELIST_COMMON['post'][$k]);
				}
				break;
		}
	}
}

$VB_API_WHITELIST_COMMON['postbits'] = array(
	'*' => array(
		'post' => $VB_API_WHITELIST_COMMON['post'],
		'postbit_type',
		'show' => array(
			'postedited', 'postedithistory', 'messageicon', 'avatar', 'reppower',
			'reputation', 'profile', 'search', 'buddy', 'emaillink', 'homepage',
			'pmlink', 'infraction', 'ip', 'multiquote_global', 'multiquote_post',
			'multiquote_selected', 'reportlink', 'postcount', 'reputationlink',
			'infractionlink', 'redcard', 'yellowcard', 'moderated', 'spam',
			'deletedpost', 'hasimicons', 'attachments', 'thumbnailattachment',
			'imageattachment', 'imageattachmentlink', 'otherattachment', 'moderatedattachment',
			'editlink', 'replylink', 'forwardlink'
		)
	)
);

$VB_API_WHITELIST_COMMON['thread'] = array(
	'threadid', 'threadtitle', 'postusername', 'postuserid', 'status',
	'del_userid', 'moderatedprefix', 'realthreadid', 'rating', 'sticky',
	'preview', 'dot_count', 'dot_lastpost', 'threadiconpath', 'threadicontitle',
	'movedprefix', 'typeprefix', 'prefix_rich', 'redirectthreadid',
	'starttime', 'dot_count', 'dot_lastpost',
	'pagenav' => array(
		'*' => array(
			'curpage'
		)
	),
	'totalpages', 'lastpagelink',
	'taglist', 'expiretime',
	'attach', 'replycount', 'views', 'lastposttime', 'highlight',
	'lastpostid', 'del_username', 'issubscribed'
);

$VB_API_WHITELIST_COMMON['threadbit'] = array(
	'thread' => $VB_API_WHITELIST_COMMON['thread'],
	'avatar',
	'show' => array(
		'threadtitle', 'deletereason', 'viewthread', 'managethread', 'moderated',
		'deletedthread', 'rexpires', 'rmanage', 'threadmoved', 'paperclip', 'unsubscribe',
		'sticky', 'pagenavmore', 'threadicon', 'gotonewpost', 'threadmoved',
		'subscribed', 'pagenav', 'guestuser', 'threadrating', 'threadcount',
		'taglist', 'avatar'
	)
);

$VB_API_WHITELIST_COMMON['threadinfo'] = array(
	'meta_description', 'prefix_plain_html', 'title', 'threadid', 'rating', 'keywords'
);

$VB_API_WHITELIST_COMMON['moderator'] = array(
	'moderatorid', 'userid', 'username', 'musername'
);

$VB_API_WHITELIST_COMMON['forum'] = array(
	'forumid', 'threadcount', 'replycount',
	'title', 'description', 'title_clean', 'description_clean',
	'lastpostinfo' => array(
		'icon',
		'lastpostinfo' => array(
			'lastposter', 'lastposterid', 'lastthread', 'lastthreadid',
			'lastposttime', 'trimthread', 'prefix'
		),
		'show' => array(
			'icon', 'lastpostinfo',
		)
	),
	'statusicon',
	'moderators' => array(
		'*' => array(
			'moderator' => $VB_API_WHITELIST_COMMON['moderator']
		)
	),
	'subforums' => array(
		'*' => array(
			'forum' => array(
				'forumid', 'threadcount', 'replycount',
				'title', 'description', 'title_clean', 'description_clean',
				'statusicon'
			)
		)
	),
	'browsers'
);

$VB_API_WHITELIST_COMMON['forumbit'] = array(
	'childforumbits' => array(
		'*' => array(
			'forum' => $VB_API_WHITELIST_COMMON['forum'],
			'parent_is_category',
			'show' => array(
				'forumsubscription', 'forumdescription', 'subforums', 'browsers'
			)
		)
	),
	'forum' => $VB_API_WHITELIST_COMMON['forum'],
	'parent_is_category',
	'show' => array(
		'forumsubscription', 'forumdescription', 'subforums', 'browsers'
	)
);

$VB_API_WHITELIST_COMMON['foruminfo'] = array(
	'forumid', 'title', 'description', 'title_clean', 'description_clean'
);

$VB_API_WHITELIST_COMMON['attachmentoption'] = array(
	'attachments' => array(
		'*' => array(
			'attach' => array(
				'imgpath', 'filesize', 'attachmentid', 'filename', 'extension'
			)
		)
	),
	'posthash', 'contentid', 'poststarttime', 'attachuserid', 'contenttypeid'
);

$VB_API_WHITELIST_COMMON['loggedin'] = array(
	'username', 'userid', 'musername', 'buddymark', 'invisiblemark'
);

$VB_API_WHITELIST_COMMON['activeusers'] = array(
	'*' => array(
		'loggedin' => $VB_API_WHITELIST_COMMON['loggedin']
	)
);

$VB_API_WHITELIST_COMMON['bookmarksites'] = array(
	'*' => array(
		'bookmarksite' => array(
			'bookmarksiteid', 'title', 'iconpath', 'link'
		)
	)
);

$VB_API_WHITELIST_COMMON['pagenav'] = array(
	'firstnumbers', 'lastnumbers', 'nextnumbers', 'nextpage', 'pagenumber',
	'prevnumbers', 'prevpage', 'total', 'totalpages', 'show_prior_elipsis',
	'show_after_elipsis',
	'pagenav' => array(
		'*' => array(
			'curpage', 'numbers', 'pagenumbers', 'total',
			'show' => array(
				'curpage'
			)
		)
	)
);

$VB_API_WHITELIST_COMMON['blog'] = array(
	'blogid', 'profilepicurl', 'postedby_username', 'avatarurl',
	'title', 'ratingnum', 'time', 'blogtitle', 'message',
	'edit_time', 'edit_userid', 'edit_username',
	'edit_reason',
	'tag_list' => array(
		'*' => array(
			'tag', 'tag_url',
			'userinfo' => array(
				'userid', 'username'
			)
		)
	),
	'categorybits' => array(
		'*' => array(
			'category' => array(
				'blogcategoryid', 'creatorid', 'title'
			)
		)
	),
	'thumbnailattachments',
	'imageattachments', 'imageattachmentlinks', 'otherattachments',
	'comments_visible', 'hidden'
);

$VB_API_WHITELIST_COMMON['responsebits'] = array(
	'*' => array(
		'response' => array(
			'blogtextid', 'checkbox_value', 'userid', 'username',
			'musername', 'time', 'avatarurl', 'message',
			'message_plain', 'message_bbcode',
			'edit_time', 'edit_userid', 'edit_username',
			'edit_reason'
		)
	)
);
// format switch
if ($_REQUEST['apitextformat'])
{
	foreach ($VB_API_WHITELIST_COMMON['responsebits']['*']['response'] as $k => $v)
	{
		switch ($_REQUEST['apitextformat'])
		{
			case '1': // plain
				if ($v == 'message' OR $v == 'message_bbcode')
				{
					unset($VB_API_WHITELIST_COMMON['responsebits']['*']['response'][$k]);
				}
				break;
			case '2': // html
				if ($v == 'message_plain' OR $v == 'message_bbcode')
				{
					unset($VB_API_WHITELIST_COMMON['responsebits']['*']['response'][$k]);
				}
				break;
			case '3': // bbcode
				if ($v == 'message' OR $v == 'message_plain')
				{
					unset($VB_API_WHITELIST_COMMON['responsebits']['*']['response'][$k]);
				}
				break;
			case '3': // plain & html
				if ($v == 'message_bbcode')
				{
					unset($VB_API_WHITELIST_COMMON['responsebits']['*']['response'][$k]);
				}
				break;
			case '3': // bbcode & html
				if ($v == 'message_plain')
				{
					unset($VB_API_WHITELIST_COMMON['responsebits']['*']['response'][$k]);
				}
				break;
			case '3': // bbcode & plain
				if ($v == 'message')
				{
					unset($VB_API_WHITELIST_COMMON['responsebits']['*']['response'][$k]);
				}
				break;
		}
	}
}

$VB_API_WHITELIST_COMMON['bloginfo'] = array(
	'blog_title', 'username', 'blogid', 'title', 'rating', 'ratingnum',
	'ratingavg', 'trackback_visible', 'views', 'userid'
);

$VB_API_WHITELIST_COMMON['albumbits'] = array(
	'*' => array(
		'album' => array(
			'albumid', 'attachmentid', 'thumbnail_dateline', 'title_html',
			'username', 'picturetime', 'picturecount',
			'hasthumbnail', 'moderatedcount', 'description_html',
			'lastpicturedate', 'pictureurl'
		),
		'show' => array(
			'personalalbum', 'moderated'
		)
	)
);

$VB_API_WHITELIST_COMMON['blogsidebarcategory'] = array(
	'sidebar' => array(
		'categorybits' => array(
			'*' => array(
				'category' => array(
					'blogcategoryid', 'userid', 'title', 'description',
					'parentid', 'displayorder', 'entrycount', 'childlist',
					'parentlist'
				)
			)
		),
		'localcategorybits', 'globalcategorybits'
	)
);

function api_result_prerender_c2($t, &$r)
{
	switch ($t)
	{
		case 'forumhome_lastpostby':
			$r['lastpostinfo']['lastposttime'] = $r['lastpostinfo']['lastpost'];
			break;
		case 'threadbit':
		case 'threadbit_deleted':
		case 'search_threadbit':
			$r['thread']['starttime'] = $r['thread']['dateline'];
			$r['thread']['lastposttime'] = $r['thread']['lastpost'];
			$r['thread']['posttime'] = $r['thread']['postdateline'];
			$r['thread']['expiretime'] = $r['thread']['expires'];
			break;
		case 'postbit_wrapper':
			$r['post']['posttime'] = $r['post']['dateline'];
			$r['post']['joindate'] = $r['post']['joindateline'];
			$r['post']['edit_time'] = $r['post']['edit_dateline'];

			$r['show']['editlink'] = false;
			$r['show']['replylink'] = false;
			$r['show']['forwardlink'] = false;
			if ($r['post']['editlink'])
			{
				$r['show']['editlink'] = true;
			}
			if ($r['post']['replylink'])
			{
				$r['show']['replylink'] = true;
			}
			if ($r['post']['forwardlink'])
			{
				$r['show']['forwardlink'] = true;
			}
			break;
		case 'blog_comment_ignore':
		case 'blog_comment_deleted':
		case 'blog_comment':
		case 'blog_comment_profile':
			$r['response']['time'] = $r['response']['dateline'];
			$r['response']['edit_time'] = $r['response']['edit_dateline'];
			break;
		case 'blog_entry':
		case 'blog_entry_ignore':
		case 'blog_entry_deleted':
		case 'blog_entry_featured':
		case 'blog_entry_profile':
		case 'blog_entry_external':
		case 'blog_show_entry':
			$r['blog']['time'] = $r['blog']['dateline'];
			$r['blog']['edit_time'] = $r['blog']['edit_dateline'];
			break;
		case 'albumbit':
		case 'album_latestbit':
			$r['album']['picturetime'] = $r['album']['lastpicturedate'];
			break;
	}
}

vB_APICallback::instance()->add('result_prerender', 'api_result_prerender_c2', 'c2');


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35584 $
|| ####################################################################
\*======================================================================*/