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


/**
 * Fetches the Picture Comment HTML for a single picture
 *
 * @param	array	Information regarding the picture
 * @param	array	(return) Statistics regarding the messages shown
 * @param	integer	The current page pumber
 * @param	integer	The number of comments per page
 * @param	integer	A specific comment ID to focus on (causes pagenumber to be ignored)
 * @param	boolean	Whether to show ignored messages in their full
 *
 * @return	string	The HTML for the picture comments
 *
 */
function fetch_picturecommentbits($pictureinfo, &$messagestats, &$pagenumber, &$perpage, $commentid = 0, $showignored = false)
{
	global $vbulletin, $vbphrase, $show;

	require_once(DIR . '/includes/class_bbcode.php');
	require_once(DIR . '/includes/class_picturecomment.php');

	if ($vbulletin->options['globalignore'] != '' AND !can_moderate(0, 'candeletepicturecomments') AND !can_moderate(0, 'canremovepicturecomments'))
	{
		require_once(DIR . '/includes/functions_bigthree.php');

		$coventry = fetch_coventry('string');
	}

	$messagestats = array();
	$state = array('visible');
	$state_or = array();
	if (fetch_user_picture_message_perm('canmoderatemessages', $pictureinfo))
	{
		$state[] = 'moderation';
	}
	else if ($vbulletin->userinfo['userid'])
	{
		$state_or[] = "(picturecomment.postuserid = " . $vbulletin->userinfo['userid'] . " AND state = 'moderation')";
	}

	if (can_moderate(0, 'canmoderatepicturecomments') OR ($vbulletin->userinfo['userid'] == $pictureinfo['userid'] AND $vbulletin->userinfo['permissions']['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canmanagepiccomment']))
	{
		$state[] = 'deleted';
		$deljoinsql = "LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (picturecomment.commentid = deletionlog.primaryid AND deletionlog.type = 'picturecomment')";
	}
	else
	{
		$deljoinsql = '';
	}

	$state_or[] = "picturecomment.state IN ('" . implode("','", $state) . "')";

	$perpage = (!$perpage OR $perpage > $vbulletin->options['pc_maxperpage']) ? $vbulletin->options['pc_perpage'] : $perpage;

	if ($commentid AND $commentinfo = fetch_picturecommentinfo($pictureinfo['filedataid'], $pictureinfo['userid'], $commentid))
	{
		$getpagenum = $vbulletin->db->query_first("
			SELECT COUNT(*) AS comments
			FROM " . TABLE_PREFIX . "picturecomment AS picturecomment
			WHERE
				filedataid = $pictureinfo[filedataid]
					AND
				userid = $pictureinfo[userid]
					AND
				(" . implode(" OR ", $state_or) . ")
					AND
				dateline <= $commentinfo[dateline]
			" . ($coventry ? "AND picturecomment.postuserid NOT IN (" . $coventry . ")" : '' ) . "
		");
		$pagenumber = ceil($getpagenum['comments'] / $perpage);
	}

	do
	{
		if (!$pagenumber)
		{
			$pagenumber = 1;
		}
		$start = ($pagenumber - 1) * $perpage;

		$hook_query_fields = $hook_query_joins = $hook_query_where = '';
		($hook = vBulletinHook::fetch_hook('picture_comment_query')) ? eval($hook) : false;

		$messagebits = '';
		$messages = $vbulletin->db->query_read("
			SELECT SQL_CALC_FOUND_ROWS
				picturecomment.*, user.*, picturecomment.ipaddress AS messageipaddress
				" . ($deljoinsql ? ",deletionlog.userid AS del_userid, deletionlog.username AS del_username, deletionlog.reason AS del_reason" : "") . "
				" . ($vbulletin->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight, customavatar.width_thumb AS avwidth_thumb, customavatar.height_thumb AS avheight_thumb, filedata_thumb, NOT ISNULL(customavatar.userid) AS hascustom" : "") . "
				$hook_query_fields
			FROM " . TABLE_PREFIX . "picturecomment AS picturecomment
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (picturecomment.postuserid = user.userid)
			" . ($vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid)
			LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
			$deljoinsql
			$hook_query_joins
			WHERE
				picturecomment.filedataid = $pictureinfo[filedataid]
					AND
				picturecomment.userid = $pictureinfo[userid]
					AND (" . implode(" OR ", $state_or) . ")
			" . ($coventry ? "AND picturecomment.postuserid NOT IN (" . $coventry . ")" : '' ) . "
				$hook_query_where
			ORDER BY picturecomment.dateline
			LIMIT $start, $perpage
		");

		list($messagestats['total']) = $vbulletin->db->query_first("SELECT FOUND_ROWS()", DBARRAY_NUM);
		if ($start >= $messagestats['total'])
		{
			$pagenumber = ceil($messagestats['total'] / $perpage);
		}
	}
	while ($start >= $messagestats['total'] AND $messagestats['total']);

	$messagestats['start'] = $start + 1;
	$messagestats['end'] = min($start + $perpage, $messagestats['total']);

	$bbcode = new vB_BbCodeParser($vbulletin, fetch_tag_list());
	$factory = new vB_Picture_CommentFactory($vbulletin, $bbcode, $pictureinfo);

	$messagebits = '';

	$firstrecord = array();
	$read_ids = array();

	if ($vbulletin->userinfo['userid'] AND !$showignored)
	{
		$ignorelist = preg_split('/( )+/', trim($vbulletin->userinfo['ignorelist']), -1, PREG_SPLIT_NO_EMPTY);
	}
	else
	{
		$ignorelist = array();
	}

	while ($message = $vbulletin->db->fetch_array($messages))
	{
		if (!$firstrecord)
		{
			$firstrecord = $message;
		}

		if ($ignorelist AND in_array($message['postuserid'], $ignorelist))
		{
			$message['ignored'] = true;
		}

		if (!$showignored AND in_coventry($message['postuserid']))
		{
			$message['ignored'] = true;
		}

		$response_handler =& $factory->create($message);
		$response_handler->cachable = false;
		$messagebits .= $response_handler->construct();

		if (!$message['messageread'] AND $message['state'] == 'visible' AND $pictureinfo['userid'] == $vbulletin->userinfo['userid'])
		{
			$read_ids[] = $message['commentid'];
		}

		$messagestats['lastcomment'] = $message['dateline'];
	}

	if ($pictureinfo['userid'] == $vbulletin->userinfo['userid'])
	{
		$readpcs = 0;

		if (!empty($read_ids))
		{
			$readpcs = sizeof($read_ids);
			$vbulletin->db->query_write("UPDATE " . TABLE_PREFIX . "picturecomment SET messageread = 1 WHERE commentid IN (" . implode(',', $read_ids) . ")");
		}

		if ($vbulletin->userinfo['pcunreadcount'] - $readpcs > 0 AND $vbulletin->options['globalignore'] != '')
		{
			build_picture_comment_counters($vbulletin->userinfo['userid']);
		}
		else if ($readpcs)
		{
			$vbulletin->db->query_write("
				UPDATE " . TABLE_PREFIX . "user
				SET
					 pcunreadcount = IF(pcunreadcount >= $readpcs, pcunreadcount - $readpcs, 0)
				WHERE
					userid = " . $vbulletin->userinfo['userid']
			);
		}
	}
	$messagestats['perpage'] = $perpage;

	$show['delete'] = fetch_user_picture_message_perm('candeletemessages', $pictureinfo);
	$show['undelete'] = fetch_user_picture_message_perm('canundeletemessages', $pictureinfo);
	$show['approve'] = fetch_user_picture_message_perm('canmoderatemessages', $pictureinfo);
	$show['inlinemod'] = ($show['delete'] OR $show['undelete'] OR $show['approve']);

	return $messagebits;
}

/**
 * Fetches the AJAX Quick Comment Box for a Picture
 *
 * @param	array	Information Regarding the Picture
 * @param	integer	The current "page" number
 * @param	array	Message Statistics
 *
 */
function fetch_picturecomment_editor($pictureinfo, $pagenumber, $messagestats)
{
	global $vbulletin, $vbphrase, $show;

	// Only allow AJAX QC on the first page
	$show['quickcomment']  = ($vbulletin->userinfo['userid'] AND
		$vbulletin->userinfo['permissions']['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canpiccomment']
	);
	$show['allow_ajax_qc'] = (($pagenumber == ceil($messagestats['total'] / $messagestats['perpage'])) AND $messagestats['total']) ? 1 : 0;

	if ($show['quickcomment'])
	{
		require_once(DIR . '/includes/functions_editor.php');

		$editorid = construct_edit_toolbar(
			'',
			false,
			'picturecomment',
			$vbulletin->options['allowsmilies'],
			true,
			false,
			'qr_small',
			'',
			array(),
			'content',
			'vBForum_PictureComment',
			0,
			$pictureinfo['attachmentid']
		);
	}
	else
	{
		$editorid = '';
	}

	return $editorid;
}


/**
 * Fetches information regarding a Picture Comment
 *
 * @param	integer	Picture ID
 * @param	integer	Comment ID
 *
 * @return	array	Comment Information
 *
 */
function fetch_picturecommentinfo($filedataid, $userid, $commentid)
{
	global $vbulletin;

	return $vbulletin->db->query_first("
		SELECT *
		FROM " . TABLE_PREFIX . "picturecomment
		WHERE
			commentid = " . intval($commentid) . "
				AND
			filedataid = " . intval($filedataid) . "
				AND
			userid = " . intval($userid) . "
	");
}


/**
* Parse message content for preview
*
* @param	array		Message and disablesmilies options
*
* @return	string	Eval'd html for display as the preview message
*/
function process_picture_comment_preview($message)
{
	global $vbulletin, $vbphrase, $show;

	require_once(DIR . '/includes/class_bbcode.php');
	$bbcode_parser = new vB_BbCodeParser($vbulletin, fetch_tag_list());

	$previewhtml = '';
	if ($previewmessage = $bbcode_parser->parse($message['message'], 'socialmessage', $message['disablesmilies'] ? 0 : 1))
	{
		$templater = vB_Template::create('newpost_preview');
			$templater->register('errorlist', $errorlist);
			$templater->register('message', $message);
			$templater->register('newpost', $newpost);
			$templater->register('previewmessage', $previewmessage);
		$previewhtml = $templater->render();
	}

	return $previewhtml;
}

/**
* Rebuild the unviewed and unmoderated picture comment counters
*
* @param	integer		Userid of visitor message data to rebuild
*
* @return	void
*/
function build_picture_comment_counters($userid)
{
	global $vbulletin;

	$userid = intval($userid);
	if ($userid)
	{
		require_once(DIR . '/includes/class_bootstrap_framework.php');
		require_once(DIR . '/vb/types.php');
		vB_Bootstrap_Framework::init();
		$types = vB_Types::instance();
		$contenttypeid = intval($types->getContentTypeID('vBForum_Album'));

		$coventry = '';
		if ($vbulletin->options['globalignore'] != '')
		{
			require_once(DIR . '/includes/functions_bigthree.php');

			$coventry = fetch_coventry('string', true);

			$vbulletin->db->query_write("
				UPDATE " . TABLE_PREFIX . "attachment AS attachment
				INNER JOIN " . TABLE_PREFIX . "picturecomment AS picturecomment ON (attachment.filedataid = picturecomment.filedataid AND attachment.userid = picturecomment.userid)
				SET
					picturecomment.messageread = 1
				WHERE
					attachment.contenttypeid = $contenttypeid
						AND
					attachment.userid = $userid
						AND
					picturecomment.postuserid IN ($coventry)
			");
		}

		list($unread) = $vbulletin->db->query_first("
			SELECT COUNT(*) AS unread
			FROM " . TABLE_PREFIX . "attachment AS attachment
			INNER JOIN " . TABLE_PREFIX . "picturecomment AS picturecomment ON (attachment.filedataid = picturecomment.filedataid AND attachment.userid = picturecomment.userid)
			WHERE
				attachment.contenttypeid = $contenttypeid
					AND
				attachment.userid = $userid
					AND
				picturecomment.state = 'visible'
					AND
				picturecomment.messageread = 0", DBARRAY_NUM
		);

		list($moderated) = $vbulletin->db->query_first("
			SELECT COUNT(*) AS moderation
			FROM " . TABLE_PREFIX . "attachment AS attachment
			INNER JOIN " . TABLE_PREFIX . "picturecomment AS picturecomment ON (attachment.filedataid = picturecomment.filedataid AND attachment.userid = picturecomment.userid)
			WHERE
				attachment.contenttypeid = $contenttypeid
					AND
				attachment.userid = $userid
					AND
				picturecomment.state = 'moderation'
			" . ($coventry ? "AND (picturecomment.postuserid NOT IN ($coventry) OR picturecomment.postuserid = $userid)" : '')
			, DBARRAY_NUM
		);

		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "user
			SET pcunreadcount = " . intval($unread) . ", pcmoderatedcount = " . intval($moderated) . "
			WHERE userid = $userid
		");

		($hook = vBulletinHook::fetch_hook('picture_comment_build_counters')) ? eval($hook) : false;
	}
}
/**
* A wrapper for checking permissions based on can_moderate and the users ability to moderate their own pictures
*
* @param	string		Permission to be evaluated
* @param	array		Result from fetch_pictureinfo, only userid is used at the moment
* @param	array		Result from fetch_messageinfo, not required for all permission checks
*
* @return	boolean
*/
function fetch_user_picture_message_perm($perm, $pictureinfo, $message = array())
{
	global $vbulletin;

	if ($message['state'] == 'deleted')
	{
		$can_view_deleted = (can_moderate(0, 'canmoderatepicturecomments')
			OR ($vbulletin->userinfo['userid'] == $pictureinfo['userid']
				AND $vbulletin->userinfo['permissions']['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canmanagepiccomment']
			)
		);
		if (!$can_view_deleted)
		{
			return false;
		}
	}

	if ($message['state'] == 'moderation')
	{
		$can_view_moderated = (
			($pictureinfo['userid'] == $vbulletin->userinfo['userid']
				AND $vbulletin->userinfo['permissions']['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canmanagepiccomment']
			)
			OR ($vbulletin->userinfo['userid'] AND $message['postuserid'] == $vbulletin->userinfo['userid'])
			OR can_moderate(0, 'canmoderatepicturecomments')
		);
		if (!$can_view_moderated)
		{
			return false;
		}
	}

	switch ($perm)
	{
		case 'canviewmessages':
			// The above conditions satisfy this permission
			return true;

		case 'caneditmessages':
			return
			(
				(
					($message['state'] == 'visible' OR $message['state'] == 'moderation')
					 AND
					$message['postuserid'] == $vbulletin->userinfo['userid']
					 AND
					$vbulletin->userinfo['permissions']['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['caneditownpiccomment']
				)
				 OR
				(
					($message['state'] != 'deleted' OR can_moderate(0, 'candeletepicturecomments'))
					 AND
					can_moderate(0, 'caneditpicturecomments')
				)
			);

		case 'canmoderatemessages':
			return
			(
				(
					$message['state'] != 'deleted'
					 AND
					$pictureinfo['userid'] == $vbulletin->userinfo['userid']
					 AND
					$vbulletin->userinfo['permissions']['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canmanagepiccomment']
				)
				 OR
				(
					($message['state'] != 'deleted' OR can_moderate(0, 'candeletepicturecomments'))
					 AND
					can_moderate(0, 'canmoderatepicturecomments')
				)
			);

		case 'candeletemessages':
			return
			(
				(
					$pictureinfo['userid'] == $vbulletin->userinfo['userid']
					 AND
					$vbulletin->userinfo['permissions']['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canmanagepiccomment']
				)
				 OR
					can_moderate(0, 'candeletepicturecomments')
				 OR
				 	can_moderate(0, 'canremovepicturecomments')
				 OR
				(
					($message['state'] == 'visible' OR $message['state'] == 'moderation')
					 AND
					$message['postuserid'] == $vbulletin->userinfo['userid']
					 AND
					$vbulletin->userinfo['permissions']['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['candeleteownpiccomment']
				)
			);

		case 'canundeletemessages':
			return can_moderate(0, 'candeletepicturecomments');

		default:
			trigger_error('fetch_user_picture_message_perm(): Argument #1; Invalid permission specified', E_USER_WARNING);
			return false;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 42552 $
|| ####################################################################
\*======================================================================*/
?>