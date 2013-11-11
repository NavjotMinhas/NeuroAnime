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
	'postid', 'statusicon', 'startdate', 'enddate', 'postdate', 'posttime',
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
	'otherattachments', 'moderatedattachments', 'edit_username', 'edit_date',
	'edit_time', 'edit_reason', 'signature', 'del_userid', 'del_username', 'del_reason',
	'historyurl', 'editlink', 'replylink', 'forwardlink'
);

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
			'imageattachment', 'imageattachmentlink', 'otherattachment', 'moderatedattachment'
		)
	)
);

$VB_API_WHITELIST_COMMON['thread'] = array(
	'threadid', 'threadtitle', 'postusername', 'postuserid', 'status',
	'del_userid', 'moderatedprefix', 'realthreadid', 'rating', 'sticky',
	'preview', 'dot_count', 'dot_lastpost', 'threadiconpath', 'threadicontitle',
	'movedprefix', 'typeprefix', 'prefix_rich', 'redirectthreadid',
	'startdate', 'starttime', 'dot_count', 'dot_lastpost',
	'pagenav' => array(
		'*' => array(
			'curpage'
		)
	),
	'totalpages', 'lastpagelink',
	'taglist', 'expiredate', 'expiretime',
	'attach', 'replycount', 'views', 'lastpostdate', 'lastposttime', 'highlight',
	'lastpostid', 'del_username', 'issubscribed', 'startdate', 'starttime'
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
			'lastpostdate', 'lastposttime', 'trimthread', 'prefix'
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
	'title', 'ratingnum', 'date', 'time', 'blogtitle', 'message',
	'edit_date', 'edit_time', 'edit_userid', 'edit_username',
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
			'musername', 'date', 'time', 'avatarurl', 'message',
			'message_plain', 'message_bbcode',
			'edit_date', 'edit_time', 'edit_userid', 'edit_username',
			'edit_reason'
		)
	)
);

$VB_API_WHITELIST_COMMON['bloginfo'] = array(
	'blog_title', 'username', 'blogid', 'title', 'rating', 'ratingnum',
	'ratingavg', 'trackback_visible', 'views', 'userid'
);

$VB_API_WHITELIST_COMMON['albumbits'] = array(
	'*' => array(
		'album' => array(
			'albumid', 'attachmentid', 'thumbnail_dateline', 'title_html',
			'username', 'picturedate', 'picturetime', 'picturecount',
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

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35584 $
|| ####################################################################
\*======================================================================*/