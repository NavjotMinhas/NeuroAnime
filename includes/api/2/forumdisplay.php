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
		'activeusers' => $VB_API_WHITELIST_COMMON['activeusers'],
		'announcebits' => array(
			'*' => array(
				'announcement' => array(
					'announcementid', 'title', 'views', 'username', 'userid', 'usertitle',
					'postdate'
				),
				'announcementidlink',
				'foruminfo' => array(
					'forumid'
				)
			)
		),
		'daysprune', 'daysprunesel',
		'forumbits' => array(
			'*' => $VB_API_WHITELIST_COMMON['forumbit']
		),
		'foruminfo' => $VB_API_WHITELIST_COMMON['foruminfo'],
		'forumrules', 'limitlower',
		'limitupper',
		'moderatorslist' => array(
			'*' => array(
				'moderator' => $VB_API_WHITELIST_COMMON['moderator']
			)
		),
		'numberguest', 'numberregistered',
		'order', 'pagenumber',
		'perpage', 'prefix_options', 'prefix_selected', 'sort',
		'threadbits' => array(
			'*' => $VB_API_WHITELIST_COMMON['threadbit']
		),
		'threadbits_sticky' => array(
			'*' => $VB_API_WHITELIST_COMMON['threadbit']
		),
		'totalmods', 'totalonline', 'totalthreads',
		'pagenav' => $VB_API_WHITELIST_COMMON['pagenav'],
	),
	'show' => array(
		'foruminfo', 'newthreadlink', 'threadicons', 'threadratings', 'subscribed_to_forum',
		'moderators', 'activeusers', 'post_queue', 'attachment_queue', 'mass_move',
		'mass_prune', 'post_new_announcement', 'addmoderator', 'adminoptions',
		'movethread', 'deletethread', 'approvethread', 'openthread', 'inlinemod',
		'spamctrls', 'noposts', 'dotthreads', 'threadslist', 'forumsearch',
		'forumslist', 'stickies'
	)
);

function api_result_prerender_2($t, &$r)
{
	switch ($t)
	{
		case 'threadbit_announcement':
			$r['announcement']['postdate'] = $r['announcement']['startdate'];
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