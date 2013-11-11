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

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

/**
* Fetches information about the selected message with permission checks
*
* @param	integer	The post we want info about
* @param	mixed		Should a permission check be performed as well
*
* @return	array	Array of information about the message or prints an error if it doesn't exist / permission problems
*/
function verify_visitormessage($vmid, $alert = true, $perm_check = true)
{
	global $vbulletin, $vbphrase;

	$messageinfo = fetch_visitormessageinfo($vmid);
	if (!$messageinfo)
	{
		if ($alert)
		{
			standard_error(fetch_error('invalidid', $vbphrase['visitor_message'], $vbulletin->options['contactuslink']));
		}
		else
		{
			return 0;
		}
	}

	if ($perm_check)
	{
		if ($messageinfo['state'] == 'deleted')
		{
			$can_view_deleted = (
				can_moderate(0,'canmoderatevisitormessages')
				OR ($messageinfo['userid'] == $vbulletin->userinfo['userid']
					AND $vbulletin->userinfo['permissions']['visitormessagepermissions'] & $vbulletin->bf_ugp_visitormessagepermissions['canmanageownprofile']
				)
			);

			if (!$can_view_deleted)
			{
				standard_error(fetch_error('invalidid', $vbphrase['visitor_message'], $vbulletin->options['contactuslink']));
			}
		}

		if ($messageinfo['state'] == 'moderation')
		{
			$can_view_moderated = (
				$messageinfo['postuserid'] == $vbulletin->userinfo['userid']
				OR ($messageinfo['userid'] == $vbulletin->userinfo['userid']
					AND $vbulletin->userinfo['permissions']['visitormessagepermissions'] & $vbulletin->bf_ugp_visitormessagepermissions['canmanageownprofile']
				)
				OR can_moderate(0, 'canmoderatevisitormessages')
			);

			if (!$can_view_moderated)
			{
				standard_error(fetch_error('invalidid', $vbphrase['visitor_message'], $vbulletin->options['contactuslink']));
			}
		}

// 	Need coventry support first
//		if (in_coventry($userinfo['userid']) AND !can_moderate())
//		{
//			standard_error(fetch_error('invalidid', $vbphrase['visitor_message'], $vbulletin->options['contactuslink']));
//		}
	}

	return $messageinfo;
}

/**
* Fetches information about the selected user message entry
*
* @param	integer	vmid of requested
*
* @return	array|false	Array of information about the user message or false if it doesn't exist
*/
function fetch_visitormessageinfo($vmid)
{
	global $vbulletin;
	static $messagecache;

	$vmid = intval($vmid);
	if (!isset($messagecache["$vmid"]))
	{
		$messagecache["$vmid"] = $vbulletin->db->query_first("
			SELECT *
			FROM " . TABLE_PREFIX . "visitormessage AS visitormessage
			WHERE visitormessage.vmid = $vmid
		");
	}

	if (!$messagecache["$vmid"])
	{
		return false;
	}
	else
	{
		return $messagecache["$vmid"];
	}
}

/**
* A wrapper for checking permissions based on can_moderate and the users ability to moderate their own profile
*
* @param	string		Permission to be evaluated
* @param	array		Result from fetch_userinfo, only userid is used at the moment
* @param	array		Result from fetch_messageinfo, not required for all permission checks
*
* @return	boolean
*/
function fetch_visitor_message_perm($perm, &$userinfo, $message = array())
{
	global $vbulletin;

	if ($message['state'] == 'deleted')
	{
		$can_view_deleted = (can_moderate(0, 'canmoderatevisitormessages')
			OR ($vbulletin->userinfo['userid'] == $userinfo['userid']
				AND $vbulletin->userinfo['permissions']['visitormessagepermissions'] & $vbulletin->bf_ugp_visitormessagepermissions['canmanageownprofile']
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
			($userinfo['userid'] == $vbulletin->userinfo['userid']
				AND $vbulletin->userinfo['permissions']['visitormessagepermissions'] & $vbulletin->bf_ugp_visitormessagepermissions['canmanageownprofile']
			)
			OR ($vbulletin->userinfo['userid'] AND $message['postuserid'] == $vbulletin->userinfo['userid'])
			OR can_moderate(0, 'canmoderatevisitormessages')
		);
		if (!$can_view_moderated)
		{
			return false;
		}
	}

	switch ($perm)
	{
		case 'canviewvisitormessages':
			// The above conditions satisfy this permission
			return true;

		case 'caneditvisitormessages':
			return
			(
				(
					($message['state'] == 'visible' OR $message['state'] == 'moderation')
					 AND
					$message['postuserid'] == $vbulletin->userinfo['userid']
					 AND
					$vbulletin->userinfo['permissions']['visitormessagepermissions'] & $vbulletin->bf_ugp_visitormessagepermissions['caneditownmessages']
				)
				 OR
				(
					($message['state'] != 'deleted' OR can_moderate(0, 'candeletevisitormessages'))
					 AND
					can_moderate(0, 'caneditvisitormessages')
				)
			);

		case 'canmoderatevisitormessages':
			return
			(
				(
					$message['state'] != 'deleted'
					 AND
					$userinfo['userid'] == $vbulletin->userinfo['userid']
					 AND
					$vbulletin->userinfo['permissions']['visitormessagepermissions'] & $vbulletin->bf_ugp_visitormessagepermissions['canmanageownprofile']
				)
				 OR
				(
					($message['state'] != 'deleted' OR can_moderate(0, 'candeletevisitormessages'))
					 AND
					can_moderate(0, 'canmoderatevisitormessages')
				)
			);

		case 'candeletevisitormessages':
			return
			(
				(
					$userinfo['userid'] == $vbulletin->userinfo['userid']
					 AND
					$vbulletin->userinfo['permissions']['visitormessagepermissions'] & $vbulletin->bf_ugp_visitormessagepermissions['canmanageownprofile']
				)
				 OR
					can_moderate(0, 'candeletevisitormessages')
				 OR
				 	can_moderate(0, 'canremovevisitormessages')
				 OR
				(
					($message['state'] == 'visible' OR $message['state'] == 'moderation')
					 AND
					$message['postuserid'] == $vbulletin->userinfo['userid']
					 AND
					$vbulletin->userinfo['permissions']['visitormessagepermissions'] & $vbulletin->bf_ugp_visitormessagepermissions['candeleteownmessages']
				)
			);

		case 'canundeletevisitormessages':
			return
			(
			/*
				(
					$userinfo['userid'] == $vbulletin->userinfo['userid']
					 AND
					$vbulletin->userinfo['permissions']['visitormessagepermissions'] & $vbulletin->bf_ugp_visitormessagepermissions['canmanageownprofile']
				)
				 OR
			*/
					can_moderate(0, 'candeletevisitormessages')
			);

		default:
			trigger_error('fetch_visitor_message_perm(): Argument #1; Invalid permission specified', E_USER_WARNING);
			return false;
	}
}

/**
* Parse message content for preview
*
* @param	array		Message and disablesmilies options
*
* @return	string	Eval'd html for display as the preview message
*/
function process_visitor_message_preview($message)
{
	global $vbulletin, $vbphrase, $show;

	require_once(DIR . '/includes/class_bbcode.php');
	$bbcode_parser = new vB_BbCodeParser($vbulletin, fetch_tag_list());

	$previewhtml = '';
	if ($previewmessage = $bbcode_parser->parse($message['message'], 'socialmessage', $message['disablesmilies'] ? 0 : 1))
	{
		$templater = vB_Template::create('visitormessage_preview');
			$templater->register('errorlist', $errorlist);
			$templater->register('message', $message);
			$templater->register('newpost', $newpost);
			$templater->register('previewmessage', $previewmessage);
		$previewhtml = $templater->render();
	}

	return $previewhtml;
}

/**
* Rebuild the unviewed and unmoderated messages
*
* @param	integer		Userid of visitor message data to rebuild
*
* @return	void
*/
function build_visitor_message_counters($userid)
{
	global $vbulletin;
	$userid = intval($userid);
	if ($userid)
	{
		$coventry = '';
		if ($vbulletin->options['globalignore'] != '')
		{
			require_once(DIR . '/includes/functions_bigthree.php');

			$coventry = fetch_coventry('string', true);

			$vbulletin->db->query_write("
				UPDATE " . TABLE_PREFIX . "visitormessage
				SET messageread = 1
				WHERE userid = $userid
				AND postuserid IN ($coventry)
			");
		}

		list($unread) = $vbulletin->db->query_first("
			SELECT COUNT(*) AS unread
			FROM " . TABLE_PREFIX . "visitormessage
			WHERE userid = $userid
				AND state = 'visible'
				AND messageread = 0", DBARRAY_NUM
		);

		list($moderated) = $vbulletin->db->query_first("
			SELECT COUNT(*) AS moderation
			FROM " . TABLE_PREFIX . "visitormessage
			WHERE userid = $userid
				AND state = 'moderation'
			" . ($coventry ? "AND (postuserid NOT IN ($coventry) OR postuserid = $userid)" : '')
			, DBARRAY_NUM
		);

		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "user
			SET vmunreadcount = " . intval($unread) . ", vmmoderatedcount = " . intval($moderated) . "
			WHERE userid = $userid
		");

		($hook = vBulletinHook::fetch_hook('visitor_message_build_counters')) ? eval($hook) : false;
	}
}

/**
* Fetches proper ajax query depending on post from a single user's messages or from a wall to wall page
*
* @param	array		Userinfo of user that this is being posted to
* @param	string	Source Page
* @param	int			The comment that was made during this ajax call
* @param	array		Userinfo of the other user of the wall to wall view
*
* @return	string
*/
function fetch_vm_ajax_query($userinfo, $vmid, $type = 'wall', $userinfo2 = null)
{
	global $vbulletin;

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
	$hook_query_fields2 = $hook_query_joins2 = $hook_query_where2 = '';

	($hook = vBulletinHook::fetch_hook('visitor_message_post_ajax')) ? eval($hook) : false;

	if ($type != 'wall')
	{
		$state = array('visible');
		if (can_moderate(0, 'canmoderatevisitormessages') OR $vbulletin->userinfo['userid'] == $userinfo['userid'])
		{
			$state[] = 'moderation';
		}

		if (can_moderate(0, 'canmoderatevisitormessages') OR ($vbulletin->userinfo['userid'] == $userinfo['userid'] AND $vbulletin->userinfo['permissions']['visitormessagepermissions'] & $vbulletin->bf_ugp_visitormessagepermissions['canmanageownprofile']))
		{
			$state[] = 'deleted';
			$deljoinsql = "LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (visitormessage.vmid = deletionlog.primaryid AND deletionlog.type = 'visitormessage')";
		}
		else
		{
			$deljoinsql = '';
		}

		$state_or = array(
			"visitormessage.state IN ('" . implode("','", $state) . "')"
		);
		// Get the viewing user's moderated posts
		if ($vbulletin->userinfo['userid'] AND !can_moderate(0, 'canmoderatevisitormessages') AND $vbulletin->userinfo['userid'] != $userinfo['userid'])
		{
			$state_or[] = "(visitormessage.postuserid = " . $vbulletin->userinfo['userid'] . " AND state = 'moderation')";
		}

		if ($type == 'edit')
		{
			$whereclause = "WHERE visitormessage.vmid = $vmid";
		}
		else
		{
			$whereclause = "
				WHERE visitormessage.userid = $userinfo[userid]
				AND (" . implode(" OR ", $state_or) . ")
				AND " . (($lastviewed = $vbulletin->GPC['lastcomment']) ?
					"(visitormessage.dateline > $lastviewed OR visitormessage.vmid = $vmid)" :
					"visitormessage.vmid = $vmid"
					);
		}

		$sql = "
			SELECT
				visitormessage.*, user.*, visitormessage.ipaddress AS messageipaddress, visitormessage.userid AS profileuserid
				" . ($vbulletin->userinfo['userid'] ? ",IF(userlist.userid IS NOT NULL, 1, 0) AS bbuser_iscontact_of_user" : "") . "
				" . ($deljoinsql ? ",deletionlog.userid AS del_userid, deletionlog.username AS del_username, deletionlog.reason AS del_reason" : "") . "
				" . ($vbulletin->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight, customavatar.width_thumb AS avwidth_thumb, customavatar.height_thumb AS avheight_thumb, customavatar.filedata_thumb" : "") . "
				$hook_query_fields
			FROM " . TABLE_PREFIX . "visitormessage AS visitormessage
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (visitormessage.postuserid = user.userid)
			" . ($vbulletin->userinfo['userid'] ? "LEFT JOIN " . TABLE_PREFIX . "userlist AS userlist ON (userlist.userid = user.userid AND userlist.type = 'buddy' AND userlist.relationid = " . $vbulletin->userinfo['userid'] . ")" : "") . "
			" . ($vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
			$deljoinsql
			$hook_query_joins
			$whereclause
				$hook_query_where
			ORDER BY visitormessage.dateline ASC
		";
	}
	else
	{
		$sql1 = $sql2 = array();
		$state1 = array('visible');
		if ($viewself OR fetch_visitor_message_perm('canmoderatevisitormessages', $userinfo))
		{
			$state1[] = 'moderation';
		}
		if (can_moderate(0, 'canmoderatevisitormessages'))
		{
			$state1[] = 'deleted';
			$delsql1 = ",deletionlog.userid AS del_userid, deletionlog.username AS del_username, deletionlog.reason AS del_reason";
			$deljoinsql1 = "LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (visitormessage.vmid = deletionlog.primaryid AND deletionlog.type = 'visitormessage')";
		}
		else if ($deljoinsql2)
		{
			$delsql1 = ",0 AS del_userid, '' AS del_username, '' AS del_reason";
		}

		$sql1[] = "visitormessage.userid = $userinfo[userid]";
		$sql1[] = "visitormessage.postuserid = $userinfo2[userid]";
		$sql1[] = "visitormessage.state IN ('" . implode("','", $state1) . "')";
		$sql1[] = "(visitormessage.dateline > " . $vbulletin->GPC['lastcomment'] . " OR visitormessage.vmid = $vmid)";

		$state2 = array('visible');
		if (fetch_visitor_message_perm('canmoderatevisitormessages', $userinfo2))
		{
			$state2[] = 'moderation';
		}
		if (can_moderate(0, 'canmoderatevisitormessages') OR ($viewself AND $vbulletin->userinfo['permissions']['visitormessagepermissions'] & $vbulletin->bf_ugp_visitormessagepermissions['canmanageownprofile']))
		{
			$state2[] = 'deleted';
			$deljoinsql2 = "LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (visitormessage.vmid = deletionlog.primaryid AND deletionlog.type = 'visitormessage')";
		}
		else
		{
			$deljoinsql2 = '';
		}

		$sql2[] = "visitormessage.userid = $userinfo2[userid]";
		$sql2[] = "visitormessage.postuserid = $userinfo[userid]";
		$sql2[] = "visitormessage.state IN ('" . implode("','", $state2) . "')";
		$sql2[] = "visitormessage.dateline > " . $vbulletin->GPC['lastcomment'];

		$sql = "
			(
				SELECT
					visitormessage.*, visitormessage.dateline AS pmdateline, user.*, visitormessage.ipaddress AS messageipaddress, visitormessage.userid AS profileuserid
					$delsql1
					" . ($vbulletin->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
					$hook_query_fields
				FROM " . TABLE_PREFIX . "visitormessage AS visitormessage
				LEFT JOIN " . TABLE_PREFIX . "user AS user ON (visitormessage.postuserid = user.userid)
				" . ($vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
				$deljoinsql1
				$hook_query_joins
				WHERE " . implode(" AND ", $sql1) . "
				$hook_query_where
			)
			UNION
			(
				SELECT
					visitormessage.*, visitormessage.dateline AS pmdateline, user.*, visitormessage.ipaddress AS messageipaddress, visitormessage.userid AS profileuserid
					" . ($deljoinsql2 ? ",deletionlog.userid AS del_userid, deletionlog.username AS del_username, deletionlog.reason AS del_reason" : "") . "
					" . ($vbulletin->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
					$hook_query_fields2
				FROM " . TABLE_PREFIX . "visitormessage AS visitormessage
				LEFT JOIN " . TABLE_PREFIX . "user AS user ON (visitormessage.postuserid = user.userid)
				" . ($vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
				$deljoinsql2
				$hook_query_joins2
				WHERE " . implode(" AND ", $sql2) . "
				$hook_query_where2
			)
			ORDER BY pmdateline ASC
			";
	}

	return $sql;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>