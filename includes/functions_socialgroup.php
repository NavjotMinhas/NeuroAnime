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

/**#@+
* The maximum sizes for the "small" social group icons
*/
define('FIXED_SIZE_GROUP_ICON_WIDTH', 200);
define('FIXED_SIZE_GROUP_ICON_HEIGHT', 200);
define('FIXED_SIZE_GROUP_THUMB_WIDTH', 80);
define('FIXED_SIZE_GROUP_THUMB_HEIGHT', 80);
/**#@-*/

/**
* Fetches information about the selected message with permission checks
*
* @param	integer	The post we want info about
* @param	boolean	Should we throw an error if there is the id is invalid?
* @param	mixed	Should a permission check be performed as well
*
* @return	array	Array of information about the message or prints an error if it doesn't exist / permission problems
*/
function verify_groupmessage($gmid, $alert = true, $perm_check = true)
{
	global $vbulletin, $vbphrase;

	$messageinfo = fetch_groupmessageinfo($gmid);

	if (!$messageinfo)
	{
		if ($alert)
		{
			standard_error(fetch_error('invalidid', $vbphrase['social_group'], $vbulletin->options['contactuslink']));
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
			$can_view_deleted = (can_moderate(0, 'canmoderategroupmessages') OR
				($messageinfo['group_ownerid'] == $vbulletin->userinfo['userid']
					AND $vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canmanageowngroups']
				)
			);

			if (!$can_view_deleted)
			{
				standard_error(fetch_error('invalidid', $vbphrase['message'], $vbulletin->options['contactuslink']));
			}
		}

		if ($messageinfo['state'] == 'moderation')
		{
			$can_view_message = (
				can_moderate(0, 'canmoderategroupmessages')
				OR $messageinfo['postuserid'] == $vbulletin->userinfo['userid']
				OR (
					$messageinfo['group_ownerid'] == $vbulletin->userinfo['userid']
					AND $vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canmanageowngroups']
				)
			);

			if (!$can_view_message)
			{
				standard_error(fetch_error('invalidid', $vbphrase['message'], $vbulletin->options['contactuslink']));
			}
		}

//	 	Need coventry support first
//		if (in_coventry($userinfo['userid']) AND !can_moderate())
//		{
//			standard_error(fetch_error('invalidid', $vbphrase['gmessage'], $vbulletin->options['contactuslink']));
//		}
	}

	return $messageinfo;
}

/**
* Fetches information about the selected user message entry
*
* @param	integer	gmid of requested
*
* @return	array|false	Array of information about the user message or false if it doesn't exist
*/
function fetch_groupmessageinfo($gmid)
{
	global $vbulletin;
	static $groupmessagecache;

	$gmid = intval($gmid);
	if (!isset($groupmessagecache["$gmid"]))
	{
		$groupmessagecache["$gmid"] = $vbulletin->db->query_first("
			SELECT groupmessage.*, socialgroup.creatoruserid AS group_ownerid
			FROM " . TABLE_PREFIX . "groupmessage AS groupmessage
			LEFT JOIN " . TABLE_PREFIX . "discussion AS discussion ON (groupmessage.discussionid = discussion.discussionid)
			LEFT JOIN " . TABLE_PREFIX . "socialgroup AS socialgroup ON (discussion.groupid = socialgroup.groupid)
			WHERE groupmessage.gmid = $gmid
		");
	}

	if (!$groupmessagecache["$gmid"])
	{
		return false;
	}
	else
	{
		return $groupmessagecache["$gmid"];
	}
}

/**
 * Checks that a given discussion id is valid and optionally checks if the user has permission
 * to view the discussion.
 *
 * @param 	integer							The id of the discussion
 * @return  array mixed	| false				Info array for the valid discussion
 */
function verify_socialdiscussion($discussionid, $alert = true, $perm_check = true)
{
	global $vbphrase, $vbulletin;

	// Try to load discussion info and ensure it has a groupid
	if (!($discussion = fetch_socialdiscussioninfo($discussionid)) OR !isset($discussion['groupid']))
	{
		if ($alert)
		{
			standard_error(fetch_error('invalidid', $vbphrase['social_group_discussion'], $vbulletin->options['contactuslink']));
		}

		return false;
	}

	// Check the user has permission to view the discussion
	if ($perm_check)
	{
		if
		(
			// invalid group
			!($group = fetch_socialgroupinfo($discussion['groupid']))
			OR
			(
				// can't view deleted messages
				$discussion['state'] == 'deleted'
				AND !fetch_socialgroup_modperm('canviewdeleted', $group)
			)
			OR
			(
				// can't view moderated messages
				$discussion['state'] == 'moderation'
				AND !fetch_socialgroup_modperm('canmoderategroupmessages', $group)
				AND $discussion['postuserid'] != $vbulletin->userinfo['userid']
			)
		)
		{
			if ($alert)
			{
				standard_error(fetch_error('invalidid', $vbphrase['social_group_discussion'], $vbulletin->options['contactuslink']));
			}

			return false;
		}
	}

	return $discussion;
}

/**
 * Fetches information about the selected social discussion
 *
 * @param	integer				id of the discussion
 * @return 	array | false		Array of information about the discussion, or false
 */
function fetch_socialdiscussioninfo($discussionid)
{
	global $vbulletin;
	static $socialdiscussioncache;

	$discussionid = intval($discussionid);
	if (!isset($socialdiscussioncache[$discussionid]))
	{

		$socialdiscussioncache[$discussionid] = $vbulletin->db->query_first("
			SELECT discussion.discussionid, discussion.groupid, discussion.firstpostid, discussion.lastpostid, discussion.visible, discussion.subscribers,
					discussion.moderation, discussion.deleted " .
					($vbulletin->userinfo['userid'] ?
					', IF(subscribe.discussionid,1,0) AS subscribed, COALESCE(discussionread.readtime,0) AS readtime' : '') . "
				,firstpost.state, firstpost.postuserid, firstpost.title, firstpost.postusername
			FROM " . TABLE_PREFIX . "discussion AS discussion
			LEFT JOIN " . TABLE_PREFIX . "groupmessage AS firstpost
				ON (firstpost.gmid = discussion.firstpostid)" .
			($vbulletin->userinfo['userid'] ?
			 " LEFT JOIN " . TABLE_PREFIX . "subscribediscussion AS subscribe
			    ON (subscribe.userid = " . $vbulletin->userinfo['userid'] . "
			    AND subscribe.discussionid = discussion.discussionid)
			   LEFT JOIN " . TABLE_PREFIX . "discussionread AS discussionread
			    ON (discussionread.userid = " . $vbulletin->userinfo['userid'] . "
			    AND discussionread.discussionid = discussion.discussionid)" : '') . "
			WHERE discussion.discussionid = $discussionid
		");

		// check read marking
	 	if (!$socialdiscussioncache[$discussionid]['readtime'])
	 	{
	 		if (!($socialdiscussioncache[$discussionid]['readtime'] =
				fetch_bbarray_cookie('discussion_marking', $socialdiscussioncache[$discussionid]['discussionid'])))
			{
				$socialdiscussioncache[$discussionid]['readtime'] = $vbulletin->userinfo['lastvisit'];
			}
	 	}
	}

	if (!$socialdiscussioncache[$discussionid])
	{
		return false;
	}
	else
	{
		return $socialdiscussioncache[$discussionid];
	}
}

/**
 * Fetches group messages from a list matching the allowed states.
 *
 * @param array int $messageids					- The messages to match
 * @param boolean $visible						- Whether to match visible
 * @param boolean $moderated					- Whether to match moderated
 * @param boolean $deleted						- Whether to match deleted
 * @return array								- Array of messages and info
 */
function verify_messages($messageids, $visible = true, $moderated = false, $deleted = false)
{
	global $vbulletin;

	if (!sizeof($messageids) OR (!$visible AND !$moderated AND !$deleted))
	{
		return false;
	}

	$messageids = implode(',', $messageids);

	return $vbulletin->db->query_read_slave("
			SELECT gm.gmid, gm.state, gm.discussionid, gm.dateline, gm.postuserid, gm.postusername
			FROM " . TABLE_PREFIX . "groupmessage AS gm
			WHERE gmid IN ($messageids)
			 AND gm.state IN (" . ($visible ? "'visible'" : 'NULL') .
			 					  ($moderated ? ",'moderation'" : '') .
			 					  ($deleted ? ",'deleted'" : '') . ")
		");
}

/**
 * Fetches group discussions from a list matching the allowed states.
 *
 * @param array int $discussionids				- The messages to match
 * @param boolean $visible						- Whether to match visible
 * @param boolean $moderated					- Whether to match moderated
 * @param boolean $deleted						- Whether to match deleted
 * @return array								- Array of discussions and firstpost info
 */
function verify_discussions($discussionids, $visible = true, $moderated = false, $deleted = false)
{
	global $vbulletin;

	if (!count($discussionids) OR (!$visible AND !$moderated AND !$deleted))
	{
		return array();
	}

	$discussionids = implode(',', $discussionids);

	return $vbulletin->db->query_read_slave("
			SELECT gm.gmid, gm.state, gm.discussionid, gm.dateline, gm.postuserid, gm.postusername
			FROM " . TABLE_PREFIX . "discussion AS discussion
			INNER JOIN " . TABLE_PREFIX . "groupmessage AS gm
			  ON (gm.gmid = discussion.firstpostid)
			WHERE discussion.discussionid IN ($discussionids)
			 AND gm.state IN (" . ($visible ? "'visible'" : 'NULL') .
			 					  ($moderated ? ",'moderation'" : '') .
			 					  ($deleted ? ",'deleted'" : '') . ")
	");
}


/**
 * Gets group options for view.
 *
 * @param array mixed $group					- Group information
 * @param bool &$result							- Whether any options were set
 * @return array mixed							- The resolved options
 */
function fetch_groupoptions(&$group, &$result)
{
	global $vbphrase, $show;

	$result = false;
	foreach (array('join', 'leave', 'edit', 'delete', 'manage', 'managemembers', 'transfergroup') AS $groupoption)
	{
		switch ($groupoption)
		{
			case 'join':
			{
				$allowedtojoin = can_join_group($group);
				$groupoptions['join'] = $allowedtojoin ? 'alt1' : '';
				$result = $allowedtojoin ? true : $result;
			}
			break;
			case 'leave':
			{
				$allowedtoleave = can_leave_group($group);

				if ($allowedtoleave)
				{
					switch ($group['membertype'])
					{
						case 'member':
						{
							$groupoptions['leavephrase'] = $vbphrase['leave_social_group'];
						}
						break;

						case 'invited':
						{
							$groupoptions['leavephrase'] = $vbphrase['decline_join_invitation'];
						}
						break;

						case 'moderated':
						{
							$groupoptions['leavephrase'] = $vbphrase['cancel_join_request'];
						}
						break;
					}
				}

				$groupoptions['leave'] = $allowedtoleave ? 'alt1' : '';
				$result = $allowedtoleave ? true : $result;
			}
			break;

			case 'edit':
			{
				$allowedtoedit = can_edit_group($group);
				$groupoptions['edit'] = $allowedtoedit ? 'alt1' : '';
				$result = $allowedtoedit ? true : $result;
			}
			break;

			case 'delete':
			{
				$allowedtodelete = can_delete_group($group);
				$groupoptions['delete'] = $allowedtodelete ? 'alt1' : '';
				$result = $allowedtodelete ? true : $result;
			}
			break;

			case 'manage':
			{
				$allowedtomanage = fetch_socialgroup_modperm('caninvitemoderatemembers', $group);
				$groupoptions['manage'] = $allowedtomanage ? 'alt1' : '';
				$result = $allowedtomanage ? true : $result;
			}
			break;

			case 'managemembers':
			{
				$allowedtomanagemembers = fetch_socialgroup_modperm('canmanagemembers', $group);
				$groupoptions['managemembers'] = $allowedtomanagemembers ? 'alt1' : '';
				$result = $allowedtomanagemembers ? true : $result;
			}
			break;

			case 'transfergroup':
			{
				$allowedtotransfergroup = fetch_socialgroup_modperm('cantransfergroup', $group);
				$groupoptions['transfergroup'] = $allowedtotransfergroup ? 'alt1' : '';
				$result = $allowedtomanagemembers ? true : $result;
			}

		}
	}

	return $groupoptions;
}

/**
 * Returns whether the group is auto moderated based on options and permissions.
 *
 * @param array mixed $group					Information about the group
 * @return bool									Whether the group should be automoderated
 */
function fetch_group_auto_moderation($group)
{
	global $vbulletin;

	return (
				(
					$vbulletin->options['social_moderation']
					OR
					$group['is_automoderated']
					OR
					!fetch_socialgroup_perm('followforummoderation')
				)
				AND !fetch_socialgroup_modperm('canmoderategroupmessages', $group)
				AND !$group['is_owner']
			);
}

/**
 * Adds inline moderation to $show and $vbphrase where appropriate.
 *
 * @param unknown_type $group
 * @param unknown_type $show
 */
function show_group_inlinemoderation($group, &$show, $bdiscussions)
{
	global $vbphrase;

	// Inline moderation options
	if (!$bdiscussions)
	{
		$show['approve'] = fetch_socialgroup_modperm('canmoderategroupmessages', $group);
		$show['delete'] = (fetch_socialgroup_modperm('canremovegroupmessages', $group) OR fetch_socialgroup_modperm('candeletegroupmessages', $group));
		$show['undelete'] = fetch_socialgroup_modperm('canundeletegroupmessages', $group);
	}
	else
	{
		$show['approve'] = fetch_socialgroup_modperm('canmoderatediscussions', $group);
		$show['delete'] = (fetch_socialgroup_modperm('canremovediscussions', $group) OR fetch_socialgroup_modperm('candeletediscussions', $group));
		$show['undelete'] = fetch_socialgroup_modperm('canundeletediscussions', $group);
	}

	// JS phrases
	if ($show['inlinemod'] = ($show['approve'] OR $show['delete'] OR $show['undelete']))
	{
		$vbphrase['delete_messages_js'] = addslashes_js($vbphrase['delete_messages']);
		$vbphrase['undelete_messages_js'] = addslashes_js($vbphrase['undelete_messages']);
		$vbphrase['approve_messages_js'] = addslashes_js($vbphrase['approve_messages']);
		$vbphrase['unapprove_messages_js'] = addslashes_js($vbphrase['unapprove_messages']);
	}
}


/**
* Parse message content for preview
*
* @param	array		Message and disablesmilies options
*
* @return	string	Eval'd html for display as the preview message
*/
function process_group_message_preview($message)
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
 * Fetches information regarding a Social Group
 *
 * @param	integer	Group ID
 *
 * @return	array	Group Information
 *
 */
function fetch_socialgroupinfo($groupid)
{
	$groups = fetch_socialgroupinfo_array(array($groupid));
	if (count($groups))
	{
		return $groups[0];
	}
	else
	{
		return null;
	}
}


function fetch_socialgroupinfo_array($groupids)
{
	global $vbulletin;

	// This is here for when we are doing inline moderation - it takes away the need to repeatedly query the database
	// if we are deleting all the messages in the same group
	static $groupcache;

	if(!is_array($groupids) OR count($groupids) == 0)
	{
		return array();
	}

	$groups = array();
	$ids_to_lookup = array();

	foreach ($groupids AS $groupid)
	{
		if (isset($groupcache["$groupid"]) AND is_array($groupcache["$groupid"]))
		{
			$groups[] = $groupcache["$groupid"];
		}
		else
		{
			$ids_to_lookup[] = $groupid;
		}
	}

	if (count($ids_to_lookup))
	{
		$set = $vbulletin->db->query_read ("
			SELECT socialgroup.*,
				user.username AS creatorusername,
				sgc.title AS categoryname
				" . ($vbulletin->userinfo['userid'] ? ', socialgroupmember.type AS membertype, IF(subscribegroup.userid,1,0) AS subscribed ' .
				(($vbulletin->options['threadmarking']) ? ',groupread.readtime AS readtime' : '') : '') . "
				" . ($vbulletin->options['sg_enablesocialgroupicons'] ? ', socialgroupicon.dateline AS icondateline,
				socialgroupicon.width AS iconwidth, socialgroupicon.height AS iconheight,
				socialgroupicon.thumbnail_width AS iconthumb_width, socialgroupicon.thumbnail_height AS iconthumb_height' : '') . "
			FROM " . TABLE_PREFIX . "socialgroup AS socialgroup
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (socialgroup.creatoruserid = user.userid)
			" . ($vbulletin->userinfo['userid'] ?
				"LEFT JOIN " . TABLE_PREFIX . "socialgroupmember AS socialgroupmember ON
					(socialgroupmember.userid = " . $vbulletin->userinfo['userid'] . " AND socialgroupmember.groupid = socialgroup.groupid)
				LEFT JOIN " . TABLE_PREFIX . "subscribegroup AS subscribegroup ON
					(subscribegroup.groupid = socialgroup.groupid AND subscribegroup.userid = " . $vbulletin->userinfo['userid'] . ")
			" . (($vbulletin->options['threadmarking']) ?
				"LEFT JOIN " . TABLE_PREFIX . "groupread AS groupread ON
					(groupread.groupid = socialgroup.groupid AND groupread.userid = " . $vbulletin->userinfo['userid'] . ")
				" :
				'') :
				'') .
				($vbulletin->options['sg_enablesocialgroupicons'] ?
				"LEFT JOIN " . TABLE_PREFIX . "socialgroupicon AS socialgroupicon ON
					(socialgroupicon.groupid = socialgroup.groupid)" : '') . "
			LEFT JOIN " . TABLE_PREFIX . "socialgroupcategory AS sgc ON (sgc.socialgroupcategoryid = socialgroup.socialgroupcategoryid)
			WHERE socialgroup.groupid IN (" . implode(',', array_map('intval', $ids_to_lookup)) . ")
		");

		while ($record = $vbulletin->db->fetch_array($set))
		{
			$group = prepare_socialgroup($record);
			$groups[] = $group;
			$groupcache["$groupid"] = $group;
		}
	}

	return $groups;
}

/**
 * Fetches all social group categories
 *
 * @return	array	Assoc array of all group categories (id => array(socialgroupcategoryid, title, groupcount)
 */
function fetch_socialgroup_category_options($docount = true, $add_empty = false, $htmlise = true)
{
	global $vbulletin;

	$categories_result = $vbulletin->db->query_read("
		SELECT socialgroupcategory.socialgroupcategoryid, socialgroupcategory.title" . ($docount ? ', COUNT(socialgroup.groupid) AS groupcount' : '') . "
		FROM " . TABLE_PREFIX . "socialgroupcategory AS socialgroupcategory " .
		($docount ? 'LEFT JOIN ' . TABLE_PREFIX . 'socialgroup AS socialgroup ON (socialgroup.socialgroupcategoryid = socialgroupcategory.socialgroupcategoryid)
			GROUP BY socialgroupcategory.socialgroupcategoryid' : '') . "
		ORDER BY socialgroupcategory.title
	");

	$categories = array();
	while ($category = $vbulletin->db->fetch_array($categories_result))
	{
		$categories[$category['socialgroupcategoryid']]['socialgroupcategoryid'] = $category['socialgroupcategoryid'];
		$categories[$category['socialgroupcategoryid']]['title'] = ($htmlise ? htmlspecialchars_uni($category['title']) : $category['title']);
		$categories[$category['socialgroupcategoryid']]['groupcount'] = $category['groupcount'];
	}
	$vbulletin->db->free_result($categories_result);

	if ($add_empty)
	{
		array_unshift($categories, array('socialgroupcategoryid' => 0, 'title' => '', 'groupcount' => false));
	}

	return $categories;
}

/**
 * Takes information regardign a group, and prepares the information within it
 * for display
 *
 * @param	array	Group Array
 * @param	bool	Whether to fetch group members and avatars
 *
 * @return	array	Group Array with prepared information
 *
 */
function prepare_socialgroup($group, $fetchmembers = false)
{
	global $vbulletin;

	if (!is_array($group))
	{
		return array();
	}

	if ($fetchmembers)
	{
		$membersinfo = cache_group_members();
		$group['membersinfo'] = $membersinfo[$group['groupid']];
	}

	$group['joindate'] = (!empty($group['joindate']) ?
		vbdate($vbulletin->options['dateformat'], $group['joindate'], true) : '');
	$group['createtime'] = (!empty($group['createdate']) ?
		vbdate($vbulletin->options['timeformat'], $group['createdate'], true) : '');
	$group['createdate'] = (!empty($group['createdate']) ?
		vbdate($vbulletin->options['dateformat'], $group['createdate'], true) : '');

	$group['lastupdatetime'] = (!empty($group['lastupdate']) ?
		vbdate($vbulletin->options['timeformat'], $group['lastupdate'], true) : '');
	$group['lastupdatedate'] = (!empty($group['lastupdate']) ?
		vbdate($vbulletin->options['dateformat'], $group['lastupdate'], true) : '');

	$group['visible'] = vb_number_format($group['visible']);
	$group['moderation'] = vb_number_format($group['moderation']);

	$group['members'] = vb_number_format($group['members']);
	$group['moderatedmembers'] = vb_number_format($group['moderatedmembers']);

	$group['categoryname'] = htmlspecialchars_uni($group['categoryname']);
	$group['discussions'] = vb_number_format($group['discussions']);
	$group['lastdiscussion'] = fetch_word_wrapped_string(fetch_censored_text($group['lastdiscussion']));
	$group['trimdiscussion'] = fetch_trimmed_title($group['lastdiscussion']);

	if (!($group['options'] & $vbulletin->bf_misc_socialgroupoptions['enable_group_albums']))
	{
		// albums disabled in this group - force 0 pictures
		$group['picturecount'] = 0;
	}
	$group['rawpicturecount'] = $group['picturecount'];
	$group['picturecount'] = vb_number_format($group['picturecount']);

	$group['rawname'] = $group['name'];
	$group['rawdescription'] = $group['description'];

	$group['name'] = fetch_word_wrapped_string(fetch_censored_text($group['name']));

	if ($group['description'])
	{
 		$group['shortdescription'] = fetch_word_wrapped_string(fetch_censored_text(fetch_trimmed_title($group['description'], 185)));
	}
	else
	{
		$group['shortdescription'] = $group['name'];
	}

 	$group['mediumdescription'] = fetch_word_wrapped_string(fetch_censored_text(fetch_trimmed_title($group['description'], 1000)));
	$group['description'] = nl2br(fetch_word_wrapped_string(fetch_censored_text($group['description'])));

	$group['is_owner'] = ($group['creatoruserid'] == $vbulletin->userinfo['userid']);

	$group['is_automoderated'] = (
		$group['options'] & $vbulletin->bf_misc_socialgroupoptions['owner_mod_queue']
		AND $vbulletin->options['sg_allow_owner_mod_queue']
		AND !$vbulletin->options['social_moderation']
	);

	$group['canviewcontent'] = (
		(
			(
				!($group['options'] & $vbulletin->bf_misc_socialgroupoptions['join_to_view'])
				OR !$vbulletin->options['sg_allow_join_to_view']
			) // The above means that you dont have to join to view
			OR $group['membertype'] == 'member'
			// Or can moderate comments
			OR can_moderate(0, 'canmoderategroupmessages')
			OR can_moderate(0, 'canremovegroupmessages')
			OR can_moderate(0, 'candeletegroupmessages')
			OR fetch_socialgroup_perm('canalwayspostmessage')
			OR fetch_socialgroup_perm('canalwascreatediscussion')
		)
	);

 	$group['lastpostdate'] = vbdate($vbulletin->options['dateformat'], $group['lastpost'], true);
 	$group['lastposttime'] = vbdate($vbulletin->options['timeformat'], $group['lastpost']);

 	$group['lastposterid'] = $group['canviewcontent'] ? $group['lastposterid'] : 0;
 	$group['lastposter'] = $group['canviewcontent'] ? $group['lastposter'] : '';

 	// check read marking
	//remove notice and make readtime determination a bit more clear
	if (!empty($group['readtime']))
	{
		$readtime = $group['readtime'];
	}
	else
	{
		$readtime = fetch_bbarray_cookie('group_marking', $group['groupid']);
		if (!$readtime)
		{
			$readtime = $vbulletin->userinfo['lastvisit'];
		}
	}

 	// get thumb url
 	$group['iconurl'] = fetch_socialgroupicon_url($group, true);

 	// check if social group is moderated to join
 	$group['membermoderated'] = ('moderated' == $group['type']);

 	// posts older than markinglimit days won't be highlighted as new
	$oldtime = (TIMENOW - ($vbulletin->options['markinglimit'] * 24 * 60 * 60));
	$readtime = max((int)$readtime, $oldtime);
	$group['readtime'] = $readtime;
	$group['is_read'] = ($readtime >= $group['lastpost']);

	($hook = vBulletinHook::fetch_hook('group_prepareinfo')) ? eval($hook) : false;

	return $group;
}


/**
 * Fetches information regarding a specific group Picture
 *
 * @param	integer	Picture ID
 * @param	integer	Group ID
 *
 * @return	array	Picture information
 *
 */
function fetch_socialgroup_picture($attachmentid, $groupid)
{
	global $vbulletin;

	$picture = $vbulletin->db->query_first("
		SELECT
			a.attachmentid, a.userid, a.caption, a.reportthreadid, a.contentid AS groupid, a.dateline,
			fd.filesize, fd.width, fd.height, fd.thumbnail_filesize, IF (fd.thumbnail_filesize > 0, 1, 0) AS hasthumbnail, fd.filedataid,
			user.username
		FROM " . TABLE_PREFIX . "attachment AS a
		INNER JOIN " . TABLE_PREFIX . "filedata AS fd ON (a.filedataid = fd.filedataid)
		INNER JOIN " . TABLE_PREFIX . "socialgroupmember AS socialgroupmember ON
			(socialgroupmember.userid = a.userid AND socialgroupmember.groupid = $groupid AND socialgroupmember.type = 'member')
		INNER JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = a.userid)
		WHERE
			a.contentid = $groupid
				AND
			a.attachmentid = $attachmentid
	");

	($hook = vBulletinHook::fetch_hook('group_fetch_pictureinfo')) ? eval($hook) : false;

	return $picture;
}


/**
 * Rebuilds discussion counter info, including first and last post information.
 *
 * @param integer								The id of the discussion
 */
function build_discussion_counters($discussionid)
{
	global $vbulletin;

	if (!($discussionid = intval($discussionid)))
	{
		return;
	}

	// Get message counters
	$messages = $vbulletin->db->query_first("
		SELECT
			SUM(IF(state = 'visible', 1, 0)) AS visible,
			SUM(IF(state = 'deleted', 1, 0)) AS deleted,
			SUM(IF(state = 'moderation', 1, 0)) AS moderation
		FROM " . TABLE_PREFIX . "groupmessage
		WHERE discussionid = $discussionid
	");

	// Get last post info
	$lastpost = $vbulletin->db->query_first("
		SELECT user.username, gm.postuserid, gm.dateline, gm.gmid
		FROM " . TABLE_PREFIX . "groupmessage AS gm
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = gm.postuserid)
		WHERE gm.discussionid = $discussionid
		AND gm.state = 'visible'
		ORDER BY gm.dateline DESC
		LIMIT 1
	");

	$discussion = fetch_socialdiscussioninfo($discussionid);

	$dataman =& datamanager_init('Discussion', $vbulletin, ERRTYPE_ARRAY);
	$dataman->set_existing($discussion);

	if ($lastpost['gmid'])
	{
		$dataman->set('lastpost', $lastpost['dateline']);
		$dataman->set('lastposter', $lastpost['username']);
		$dataman->set('lastposterid', $lastpost['postuserid']);
		$dataman->set('lastpostid', $lastpost['gmid']);
	}

	$messages['visible'] = $messages['visible'] ? $messages['visible'] : 1;

	$dataman->set('visible', $messages['visible']);
	$dataman->set('deleted', $messages['deleted']);
	$dataman->set('moderation', $messages['moderation']);

	($hook = vBulletinHook::fetch_hook('discussion_build_counters')) ? eval($hook) : false;

	$dataman->save();
	unset($dataman);
}

/**
 * Rebuilds Group Counter information from Discussion Counter
 *
 * @param	integer	Group ID
 *
 */
function build_group_counters($groupid)
{
	global $vbulletin;

	if (!($groupid = intval($groupid)))
	{
		return;
	}

	// Get message counters
	$messages = $vbulletin->db->query_first("
		SELECT
			SUM(IF(state != 'visible', 0, visible)) AS visible,
			SUM(deleted) AS deleted,
			SUM(IF(state = 'deleted', 0, moderation)) AS moderation
		FROM " . TABLE_PREFIX . "discussion AS discussion
		LEFT JOIN " . TABLE_PREFIX . "groupmessage AS gm
			ON (gm.gmid = discussion.firstpostid)
		WHERE groupid = $groupid
	");

	// Get discussion counter
	$discussions = $vbulletin->db->query_first("
		SELECT
			SUM(IF(state = 'visible', 1, 0)) AS total
		FROM " . TABLE_PREFIX . "discussion AS discussion
		LEFT JOIN " . TABLE_PREFIX . "groupmessage AS gm
			ON (gm.gmid = discussion.firstpostid)
		WHERE groupid = $groupid
	");

	$lastpost = $vbulletin->db->query_first("
		SELECT discussion.lastposter, discussion.lastposterid, discussion.lastpost, discussion.lastpostid,
				discussion.discussionid, gm.title
		FROM " . TABLE_PREFIX . "discussion AS discussion
		LEFT JOIN " . TABLE_PREFIX . "groupmessage AS gm ON (gm.gmid = discussion.firstpostid)
		WHERE discussion.groupid = $groupid AND gm.state = 'visible'
		ORDER BY discussion.lastpost DESC
		LIMIT 1
	");

	$groupinfo = fetch_socialgroupinfo($groupid);

	$dataman =& datamanager_init('SocialGroup', $vbulletin, ERRTYPE_SILENT);
	$dataman->set_existing($groupinfo);

	$dataman->set('lastpost', $lastpost['lastpost']);
	$dataman->set('lastposter', $lastpost['lastposter']);
	$dataman->set('lastposterid', $lastpost['lastposterid']);
	$dataman->set('lastgmid', $lastpost['lastpostid']);
	$dataman->set('visible', $messages['visible']);
	$dataman->set('deleted', $messages['deleted']);
	$dataman->set('moderation', $messages['moderation']);
	$dataman->set('discussions', $discussions['total']);
	$dataman->set('lastdiscussion', $lastpost['title']);
	$dataman->set('lastdiscussionid', $lastpost['discussionid']);

	($hook = vBulletinHook::fetch_hook('group_build_counters')) ? eval($hook) : false;

	$dataman->save();
	unset($dataman);

	list($pendingcountforowner) = $vbulletin->db->query_first("
		SELECT SUM(moderation) FROM " . TABLE_PREFIX . "socialgroup
		WHERE creatoruserid = " . $groupinfo['creatoruserid']
	, DBARRAY_NUM);

	$vbulletin->db->query_write("
		UPDATE " . TABLE_PREFIX . "user
		SET gmmoderatedcount = " . intval($pendingcountforowner) . "
		WHERE userid = " . $groupinfo['creatoruserid']
	);
}

/**
* Updates the counter for the owner of the group that shows how many pending members
* they have awaiting them to deal with
*
* @param	integer	The userid of the owner of the group
*/
function update_owner_pending_gm_count($ownerid)
{
	global $vbulletin;

	list($pendingcountforowner) = $vbulletin->db->query_first("
		SELECT SUM(moderation) FROM " . TABLE_PREFIX . "socialgroup
		WHERE creatoruserid = " . intval($ownerid)
		, DBARRAY_NUM);

	$vbulletin->db->query_write("
		UPDATE " . TABLE_PREFIX . "user
		SET gmmoderatedcount = " . intval($pendingcountforowner) . "
		WHERE userid = " . intval($ownerid)
	);
}

/**
 * Whether the current logged in user can join the group
 *
 * @param	array	Group information
 *
 * @return	boolean
 *
 */
function can_join_group($group)
{
	global $vbulletin;

	return (
		$vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups']
		AND $vbulletin->userinfo['userid']
		AND $vbulletin->userinfo['permissions']['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canjoingroups']
		AND
		(
			$group['membertype'] == 'invited'
			OR
			(
				$group['type'] != 'inviteonly'
				AND empty($group['membertype'])
			)
		)

	);
}

/**
 * Whether the currently logged in user can leave the group
 *
 * @param	array	Group Information
 *
 * @return	boolean
 *
 */
function can_leave_group($group)
{
	global $vbulletin;

	return (
		$vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups']
		AND $vbulletin->userinfo['userid']
		AND !empty($group['membertype'])
		AND !$group['is_owner']
	);
}

/**
 * Whether the current user can edit the group discussion
 *
 * @param array	$discussion						The discussion to check
 * @param array	$group							In the group to check
 * @return boolean								Whether the user has permission
 */
function can_edit_group_discussion($discussion, $group = false)
{
	global $vbulletin;

	if (!$group)
	{
		$group = fetch_socialgroupinfo($discussion['groupid']);
	}

	return (
		$vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups']
		AND $vbulletin->userinfo['userid']
		AND
		(
			(
				$group['is_owner']
				AND fetch_socialgroup_perm('canmanageowngroups')
			)
			OR
			(
				($discussion['postuserid'] == $vbulletin->userinfo['userid'])
				AND fetch_socialgroup_perm('canmanagediscussions')
				AND (($discussion['visible'] == 1) OR ($discussion['moderation'] == 1))
				AND (($discussion['visible'] + $discussion['moderation'] + $discussion['deleted']) == 1)
				AND ($discussion['dateline'] + ($vbulletin->options['editthreadtitlelimit'] * 60) > TIMENOW)
			)
			OR can_moderate(0, 'caneditdiscussions')
		)
	);
}


/**
 * Whether the current user can create a new discussion in this group
 *
 * @param array $group							The group to check
 * @return boolean								Whether the user has permission
 */
function can_post_new_discussion($group)
{
	global $vbulletin;

	return (
		$vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups']
		AND $vbulletin->userinfo['userid']
		AND
		(
			$group['is_owner']
			OR fetch_socialgroup_perm('canalwayscreatediscussion')
			OR
			(
				(
					!$vbulletin->options['sg_enable_owner_only_discussions']
					OR !($group['options'] & $vbulletin->bf_misc_socialgroupoptions['only_owner_discussions'])
				)
				AND
				(
					$group['membertype'] == 'member'
					AND fetch_socialgroup_perm('cancreatediscussion')
				)
			)
		)
	);
}


/**
 * Whether the current user can create a new message in this group
 *
 * @param array $group							The group to check
 * @return boolean								Whether the user has permission
 */
function can_post_new_message($group)
{
	global $vbulletin;

	return (
		$vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups']
		AND $vbulletin->userinfo['userid']
		AND
		(
			$group['is_owner']
			OR fetch_socialgroup_perm('canalwayspostmessage')
			OR
			(
				$group['membertype'] == 'member'
				AND fetch_socialgroup_perm('canpostmessage')
			)
		)
	);
}


/**
 * Whether the currently logged in user can edit the group
 *
 * @param	array	Group Information
 *
 * @return	boolean
 *
 */

function can_edit_group($group)
{
	global $vbulletin;

	return (
		$vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups']
		AND $vbulletin->userinfo['userid']
		AND
		(
			(
				$group['is_owner']
				AND
				(
					fetch_socialgroup_perm('caneditowngroups')
					OR $group['members'] == 1
				)
			)
			OR can_moderate(0, 'caneditsocialgroups')
		)
	);
}

/**
 * Whether the currently logged in user can delete the group
 *
 * @param	array	Group Information
 *
 * @return	boolean
 *
 */
function can_delete_group($group)
{
	global $vbulletin;

	return (
		$vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups']
		AND $vbulletin->userinfo['userid']
		AND
		(
			(
				$group['is_owner']
				AND
				(
					fetch_socialgroup_perm('candeleteowngroups')
					OR
					(
						$group['members'] == 1
						AND $vbulletin->options['sg_allow_delete_empty_group']
					)
				)
			)
			OR can_moderate(0, 'candeletesocialgroups')
		)
	);
}


/**
 * Checks a single social group permission.
 *
 * @param	string	The permission to check
 *
 * @return	boolean	Whether or not the current user has the permission.
 */
function fetch_socialgroup_perm($perm)
{
	global $vbulletin;

	$userinfo = $vbulletin->userinfo;

	if (isset($vbulletin->bf_ugp_socialgrouppermissions["$perm"]))
	{
		return $userinfo['permissions']['socialgrouppermissions'] &
				$vbulletin->bf_ugp_socialgrouppermissions["$perm"];
	}

	return false;
}


/**
 * Fetches information regarding moderating permissions for a social group and
 * the logged in user
 *
 * @param	string	The Permission to be fetched
 * @param	array	Information regarding the group
 *
 * @return	boolean	Whether or not the logged in user has the permission
 * 					or not for the group specified
 */
function fetch_socialgroup_modperm($perm, $group = false)
{
	global $vbulletin;

	$userinfo = $vbulletin->userinfo;

	if (!$userinfo['userid'])
	{
		return false;
	}

	switch ($perm)
	{
		case 'canmoderategroupmessages':
		case 'canmoderatediscussions':
		{
			return (
				can_moderate(0, $perm)
				OR (
					$group
					AND $group['is_owner']
					AND fetch_socialgroup_perm('canmanageowngroups')
				)
			);
		}
		break;

		case 'candeletegroupmessages':
		{
			return can_moderate(0, 'candeletegroupmessages')
			OR (
				$group
				AND $group['is_owner']
				AND fetch_socialgroup_perm('canmanageowngroups')
			);
		}
		break;

		case 'candeletediscussions':
		{
			return can_moderate(0, 'candeletediscussions')
			OR (
				$group
				AND $group['is_owner']
				AND fetch_socialgroup_perm('canmanageowngroups')
			);
		}
		break;

		case 'canremovediscussions':
		{
			return can_moderate(0, 'canremovediscussions');
		}
		break;

		case 'canremovegroupmessages':
		{
			return can_moderate(0, 'canremovegroupmessages');
		}
		break;

		case 'canundeletegroupmessages':
		{
			return can_moderate(0, 'candeletegroupmessages') OR can_moderate(0, 'canremovegroupmessages');
		}
		break;

		case 'canundeletediscussions':
		{
			return can_moderate(0, 'candeletediscussions') OR can_moderate(0, 'canremovediscussions');
		}
		break;

		case 'canviewdeleted':
		{
			return (
				can_moderate(0, 'canmoderategroupmessages')
				OR (
					$group
					AND $group['is_owner']
					AND fetch_socialgroup_perm('canmanageowngroups')
				)
			);
		}
		break;

		case 'canremovepicture':
		{
			return (
				can_moderate(0, 'caneditalbumpicture')
				OR (
					$group
					AND $group['is_owner']
					AND fetch_socialgroup_perm('canmanageowngroups')
				)
			);
		}
		break;

		case 'caninvitemoderatemembers':
		{
			return (
				$group
				AND $group['is_owner']
				OR can_moderate(0, 'candeletesocialgroups')
			);
		}
		break;

		case 'canmanagemembers':
		{
			return (
				$group
				AND (
					   (
						   $group['is_owner']
						   AND fetch_socialgroup_perm('canmanageowngroups')
					   )
					   OR can_moderate(0, 'candeletesocialgroups')
				    )
				AND $group['members'] > 1
			);
		}
		break;

		case 'caneditdiscussions':
		{
			return (can_moderate('caneditdiscussions'));
		}
		break;

		case 'caneditgroupmessages':
		{
			return (can_moderate('caneditgroupmessages'));
		}
		break;

		case 'cantransfergroup':
		{
			return (
				$group
				AND (
						(
							$group['is_owner']
						)
						OR can_moderate(0, 'cantransfersocialgroups')
				)
			);
		}
	}

	return false;
}

/**
 * Determines whether we can edit a specific group message
 *
 * @param	array	Message Information
 * @param	array	Group Information
 *
 * @return	boolean
 */
function can_edit_group_message($messageinfo, $group)
{
	global $vbulletin;

	if (!$vbulletin->userinfo['userid'])
	{
		return false;
	}

	switch ($messageinfo['state'])
	{
		case 'deleted':
		{
			$canviewdeleted = (
				fetch_socialgroup_modperm('canundeletegroupmessages', $group)
				OR (
					$vbulletin->userinfo['userid'] == $messageinfo['postuserid']
					AND fetch_socialgroup_perm('canmanagemessages')
				)
			);

			if (!$canviewdeleted)
			{
				return false;
			}

			return fetch_socialgroup_modperm('canundeletegroupmessages', $group) AND can_moderate(0, 'caneditgroupmessages');

		}
		break;

		default:
		{
			if (
				$messageinfo['postuserid'] == $vbulletin->userinfo['userid']
				AND fetch_socialgroup_perm('canmanagemessages')
			)
			{
				return true;
			}
		}
	}

	return can_moderate(0, 'caneditgroupmessages');
}

/**
 * Marks a discussion or group as read.
 *
 * @param string $type						- 'group' or 'discussion'
 * @param int $userid						- The id of the user
 * @param int $itemid						- The id of the item to mark
 */
function exec_sg_mark_as_read($type, $itemid)
{
	global $vbulletin;

	if ($vbulletin->userinfo['userid'])
	{
		if ($vbulletin->options['threadmarking'])
		{
			$table = TABLE_PREFIX . (($type == 'group') ? 'groupread' : 'discussionread');
			$idcol = ($type == 'group') ? 'groupid' : 'discussionid';

			$vbulletin->db->query_write("REPLACE INTO $table (userid,$idcol,readtime)
										VALUES (" . intval($vbulletin->userinfo['userid']) . ", " . intval($itemid) . ", " . TIMENOW . ")");

			if ('discussion' == $type AND 2 == $vbulletin->options['threadmarking'])
			{
				// quite expensive check to see if there are any unread discussions
				if ($discussion = fetch_socialdiscussioninfo($itemid))
				{
					require_once(DIR . '/includes/class_groupmessage.php');

					// Create discussion collection
					$collection_factory = new vB_Group_Collection_Factory($vbulletin);
					$collection = $collection_factory->create('discussion', $discussion['groupid'], 0, 1, false, true);
					$collection->filter_show_read(false);

					if (!$collection->fetch_count())
					{
						exec_sg_mark_as_read('group', $discussion['groupid']);
					}
					unset($collection, $collection_factory);
				}
			}
		}
		else
		{
			// set read in cookie
			set_bbarray_cookie($type . '_marking', $itemid, TIMENOW);
		}
	}
	else
	{
		vbsetcookie('lastvisit', TIMENOW);
	}
}

/**
 * Sends email notifications for discussions.
 *
 * @param int		$discussion		- The discussion being updated
 * @param int		$messageid		- Id of the message that triggered the update
 * @param string	$postusername	- Optional username displayed on post
 */
function exec_send_sg_notification($discussionid, $gmid = false, $postusername = false)
{
	global $vbulletin;

	if (!$vbulletin->options['enableemail'])
	{
		return;
	}

	$discussion = fetch_socialdiscussioninfo($discussionid);

	// if there are no subscribers, no need to send notifications
	if (!$discussion['subscribers'])
	{
		return;
	}

	// if the discussion is moderated or deleted, don't send notification
	if ('deleted' == $discussion['state'] OR 'moderation' == $discussion['state'])
	{
		return;
	}

	$group = fetch_socialgroupinfo($discussion['groupid']);

	if (!$gmid)
	{
		// get last gmid from discussion
		$gmid = $vbulletin->db->query_first("
			SELECT MAX(gmid) AS gmid
			FROM " . TABLE_PREFIX . "groupmessage AS groupmessage
			WHERE discussionid = $discussion[discussionid]
				AND state = 'visible'
		");
		$gmid = $gmid['gmid'];
	}

	// get message details
	$gmessage = fetch_groupmessageinfo($gmid);
	if (!$gmessage)
	{
		return;
	}

	// get post time of previous message - if a user hasn't been active since then we won't resend a notification
	$lastposttime = (($lastposttime = $vbulletin->db->query_first("
			SELECT MAX(dateline) AS dateline
			FROM " . TABLE_PREFIX . "groupmessage AS groupmessage
			WHERE discussionid = $discussion[discussionid]
				AND dateline < $gmessage[dateline]
				AND state = 'visible'
	")) ? $lastposttime['dateline'] : $gmessage['dateline']);

	$discussion['title'] = unhtmlspecialchars($discussion['title']);
	$group['name'] = unhtmlspecialchars($group['name']);

	// temporarily use postusername in userinfo
	if (!$postusername)
	{
		// get current user name if user exists
		if ($gmessage['postuserid'] AND ($userinfo = fetch_userinfo($gmessage['postuserid'])))
		{
			$postusername = $userinfo['username'];
		}
		else
		{
			$postusername = $gmessage['postusername'];
		}
	}
	$postusername = unhtmlspecialchars($postusername);
	$userid = $gmessage['postuserid'];

	($hook = vBulletinHook::fetch_hook('newpost_sg_notification_start')) ? eval($hook) : false;

	$useremails = $vbulletin->db->query_read_slave("
		SELECT user.*, subscribediscussion.emailupdate, subscribediscussion.subscribediscussionid, IF(socialgroupmember.userid IS NOT NULL,1,0) ismember
		FROM " . TABLE_PREFIX . "subscribediscussion AS subscribediscussion
		INNER JOIN " . TABLE_PREFIX . "user AS user ON (subscribediscussion.userid = user.userid)
		LEFT JOIN " . TABLE_PREFIX . "usergroup AS usergroup ON (usergroup.usergroupid = user.usergroupid)
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON (usertextfield.userid = user.userid)
		LEFT JOIN " . TABLE_PREFIX . "socialgroupmember AS socialgroupmember ON (socialgroupmember.userid = user.userid AND socialgroupmember.groupid = $group[groupid])
		WHERE subscribediscussion.discussionid = $discussion[discussionid]
		 AND subscribediscussion.emailupdate = 1
		 AND " . ($gmessage['postuserid'] ? " CONCAT(' ', IF(usertextfield.ignorelist IS NULL, '', usertextfield.ignorelist), ' ') NOT LIKE ' " . intval($userid) . " '" : '') . "
		 AND user.usergroupid <> 3
		 AND user.userid <> " . intval($userid) . "
		 AND user.lastactivity >= " . intval($lastposttime) . "
		 AND (usergroup.genericoptions & " . $vbulletin->bf_ugp_genericoptions['isnotbannedgroup'] . ")
	");

	vbmail_start();

	// parser for plaintexting the message pagetext
	require_once(DIR . '/includes/class_bbcode_alt.php');
	$plaintext_parser = new vB_BbCodeParser_PlainText($vbulletin, fetch_tag_list());
	$pagetext_cache = array(); // used to cache the results per languageid for speed

	$evalemail = array();

	while ($touser = $vbulletin->db->fetch_array($useremails))
	{
		// check user can view discussion
		$permissions = cache_permissions($touser, false);

		if (!($vbulletin->usergroupcache["$touser[usergroupid]"]['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup'])
			OR !($permissions['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview'])
			OR !($permissions['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canviewgroups'])
			OR (($group['options'] & $vbulletin->bf_misc_socialgroupoptions['join_to_view'])
				AND !$touser['ismember']
				AND !($permissions['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canalwayscreatediscussion'])
				AND !($permissions['socialgrouppermissions'] & $vbulletin->bf_ugp_socialgrouppermissions['canalwayspostmessage'])))
		{
			continue;
		}

		$touser['username'] = unhtmlspecialchars($touser['username']);
		$touser['languageid'] = iif($touser['languageid'] == 0, $vbulletin->options['languageid'], $touser['languageid']);
		$touser['auth'] = md5($touser['userid'] . $touser['subscribediscussionid'] . $touser['salt'] . COOKIE_SALT);

		if (empty($evalemail))
		{
			$email_texts = $vbulletin->db->query_read_slave("
				SELECT text, languageid, fieldname
				FROM " . TABLE_PREFIX . "phrase
				WHERE fieldname IN ('emailsubject', 'emailbody') AND varname = 'notify_discussion'
			");

			while ($email_text = $vbulletin->db->fetch_array($email_texts))
			{
				$emails["$email_text[languageid]"]["$email_text[fieldname]"] = $email_text['text'];
			}

			require_once(DIR . '/includes/functions_misc.php');

			foreach ($emails AS $languageid => $email_text)
			{
				//lets cycle through our array of notify phrases
				$text_message = str_replace("\\'", "'", addslashes(iif(empty($email_text['emailbody']), $emails['-1']['emailbody'], $email_text['emailbody'])));
				$text_message = replace_template_variables($text_message);
				$text_subject = str_replace("\\'", "'", addslashes(iif(empty($email_text['emailsubject']), $emails['-1']['emailsubject'], $email_text['emailsubject'])));
				$text_subject = replace_template_variables($text_subject);

				$evalemail["$languageid"] = '
					$message = "' . $text_message . '";
					$subject = "' . $text_subject . '";
				';
			}
		}

		// parse the page text into plain text, taking selected language into account
		if (!isset($pagetext_cache["$touser[languageid]"]))
		{
			$plaintext_parser->set_parsing_language($touser['languageid']);
			$pagetext_cache["$touser[languageid]"] = $plaintext_parser->parse($gmessage['pagetext']);
		}

		//magic vars used in the phrase eval below.
		$pagetext = $pagetext_cache["$touser[languageid]"];
		$discussionlink = fetch_seo_url('groupdiscussion|nosession|bburl|js', $discussion, array('goto' => 'newpost'));
		$unsubscribelink = fetch_seo_url('grouphome|nosession|bburl|js', array(), array('do' => 'unsubscribe', 
			'subscriptionid' => $touser['subscribediscussionid'], 'auth' => $touser['auth']));
		$managegroupsubscriptionslink = fetch_seo_url('groupsub|nosession|bburl|js', array());
		$managesubscriptionslink = fetch_seo_url('subscription|nosession|bburl|js', array(), 
			array('do' => 'viewsubscription', 'folderid' => 'all'));

		($hook = vBulletinHook::fetch_hook('new_sg_message_notification_message')) ? eval($hook) : false;

		eval(iif(empty($evalemail["$touser[languageid]"]), $evalemail["-1"], $evalemail["$touser[languageid]"]));

		vbmail($touser['email'], $subject, $message);
	}
	$vbulletin->db->free_result($useremails);
	unset($plaintext_parser, $pagetext_cache);

	vbmail_end();
}


/**
 * Prepares the appropriate url for a group icon.
 * The url is based on whether fileavatars are in use, and whether a thumb is required.
 *
 * @param array mixed $groupinfo				- GroupInfo array of the group to fetch the icon for
 * @param boolean $thumb						- Whether to return a thumb url
 * @param boolean $path							- Whether to fetch the path or the url
 * @param boolean $force_file					- Always get the file path as if it existed
 */
function fetch_socialgroupicon_url($groupinfo, $thumb = false, $path = false, $force_file = false)
{
	global $vbulletin;

	$iconurl = false;

	if ($vbulletin->options['sg_enablesocialgroupicons'])
	{
		if (!$groupinfo['icondateline'])
		{
			return vB_Template_Runtime::fetchStyleVar('unknownsgicon');
		}

		if ($vbulletin->options['usefilegroupicon'] OR $force_file)
		{
			$iconurl = ($path ? $vbulletin->options['groupiconpath'] : $vbulletin->options['groupiconurl']) . ($thumb ? '/thumbs' : '') . '/socialgroupicon' . '_' . $groupinfo['groupid'] . '_' . $groupinfo['icondateline'] . '.gif';
		}
		else
		{
			$iconurl = 'image.php?' . $vbulletin->session->vars['sessionurl'] . 'groupid=' . $groupinfo['groupid'] . '&amp;dateline=' . $groupinfo['icondateline'] . ($thumb ? '&amp;type=groupthumb' : '');
		}
	}

	return $iconurl;
}

/**
 * Fetches groupinfo for a group that the specified user created.
 * If no groupid is specified, then the first group is retrieved
 *
 * @param integer $userid						Id of the owner of the group
 * @param integer $groupid						Optional integer of a group the owner created
 */
function fetch_owner_socialgroup($userid, $page = 0)
{
	global $vbulletin;

	$page = intval($page);

	if (!$userid)
	{
		return false;
	}

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
	($hook = vBulletinHook::fetch_hook('group_fetch_own')) ? eval($hook) : false;

	$sql = "SELECT SQL_CALC_FOUND_ROWS socialgroup.*, socialgroup.dateline AS createdate,
				groupicon.thumbnail_width AS iconthumb_width,
				groupicon.thumbnail_height AS iconthumb_height, groupicon.dateline AS icondateline " .
				(($userid == $vbulletin->userinfo['userid']) ? ", socialgroup.lastpost, groupread.readtime" : '') . "
				$hook_query_fields
			FROM " . TABLE_PREFIX . "socialgroup AS socialgroup
			LEFT JOIN " . TABLE_PREFIX . "socialgroupicon AS groupicon ON (groupicon.groupid = socialgroup.groupid)" .
				(($userid == $vbulletin->userinfo['userid']) ? "
			LEFT JOIN " . TABLE_PREFIX . "groupread AS groupread
			 ON (groupread.groupid = socialgroup.groupid
			 AND groupread.userid = " . $vbulletin->userinfo['userid'] . ")" : '') . "
			$hook_query_joins
			WHERE socialgroup.creatoruserid = " . intval($userid) . "
			$hook_query_where
			ORDER BY dateline DESC
			LIMIT $page, 1
	";
	$groupinfo = $vbulletin->db->query_first($sql);

	if (!$groupinfo)
	{
		return false;
	}

	//$groupinfo['iconurl'] = fetch_socialgroupicon_url($groupinfo, true);
	$groupinfo['page'] = $page;
	$groupinfo['total'] = current($vbulletin->db->query_first('SELECT FOUND_ROWS()'));

	$groupinfo['next'] = ($page < ($groupinfo['total'] - 1) ? ($page + 1) : false);
	$groupinfo['previous'] = ($page > 0) ? ($page - 1) : false;

	$groupinfo['shownext'] = (false !== $groupinfo['next']);
	$groupinfo['showprevious'] = (false !== $groupinfo['previous']);

	return $groupinfo;
}

/**
 * Fetches categories from cache, rebuilding it if necessary
 *
 * @param boolean $force_rebuild				Force rebuilding of the cache
 * @return array								Array of category info
 */
function fetch_socialgroup_category_cloud($force_rebuild = false)
{
	global $vbulletin;

	$categories = $vbulletin->sg_category_cloud;

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
	($hook = vBulletinHook::fetch_hook('group_fetch_own')) ? eval($hook) : false;

	if ($force_rebuild OR !is_array($categories))
	{
		$sql = "SELECT cat.socialgroupcategoryid AS categoryid, cat.title, COUNT(socialgroup.groupid) AS total
				$hook_query_fields
				FROM " . TABLE_PREFIX . "socialgroupcategory AS cat
				LEFT JOIN " . TABLE_PREFIX . "socialgroup AS socialgroup
				 ON (socialgroup.socialgroupcategoryid = cat.socialgroupcategoryid)
				$hook_query_joins
				WHERE socialgroup.groupid IS NOT NULL
				$hook_query_where
				GROUP BY cat.socialgroupcategoryid
				ORDER BY total
				LIMIT 0, " . intval($vbulletin->options['sg_category_cloud_size']);
		$category_result = $vbulletin->db->query_read_slave($sql);

		$categories = $totals = array();

		// fetch categories and their totals
		while ($category = $vbulletin->db->fetch_array($category_result))
		{
			$categories[$category['title']] = $category;
			$totals[$category['categoryid']] = $category['total'];
		}
		$vbulletin->db->free_result($category_result);

		// fetch the stddev levels
		$levels = fetch_standard_deviated_levels($totals, $vbulletin->options['tagcloud_levels']);

		// assign the levels back to the categories
		foreach ($categories AS $title => $category)
		{
			$categories[$title]['level'] = $levels[$category['categoryid']];
		}

		// sort the categories by title
		uksort($categories, 'strnatcasecmp');

		// build the cache
		build_datastore('sg_category_cloud', serialize($categories), 1);
	}

	return $categories;
}

/**
 * Fetches random social groups
 *
 * @return array								Array of groupinfos
 */
function fetch_socialgroup_random_groups()
{
	global $vbulletin;

	$total = $vbulletin->db->query_first("
		SELECT COUNT(*) AS total
		FROM " . TABLE_PREFIX . "socialgroup AS socialgroup
		LEFT JOIN " . TABLE_PREFIX . "socialgroupicon AS sgicon ON (sgicon.groupid = socialgroup.groupid)
	");

	if (!$total['total'])
	{
		return array();
	}

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
	($hook = vBulletinHook::fetch_hook('group_fetch_random')) ? eval($hook) : false;

	$sql = "
		SELECT socialgroup.*, socialgroup.dateline AS createdate
			,sgicon.dateline AS icondateline, sgicon.thumbnail_width AS iconthumb_width, sgicon.thumbnail_height AS iconthumb_height
			,sgc.title AS categoryname, sgc.socialgroupcategoryid AS categoryid
		FROM " . TABLE_PREFIX ."socialgroup AS socialgroup
		LEFT JOIN " . TABLE_PREFIX . "socialgroupicon AS sgicon ON sgicon.groupid = socialgroup.groupid
		INNER JOIN " . TABLE_PREFIX . "socialgroupcategory AS sgc ON (sgc.socialgroupcategoryid = socialgroup.socialgroupcategoryid)
		$hook_query_joins
		$hook_query_where
		LIMIT " . vbrand(0, max(--$total['total'],1)) . ", 10
	";

	$result = $vbulletin->db->query_read_slave($sql);

	$groups = array();
	while ($group = $vbulletin->db->fetch_array($result))
	{
		$group = prepare_socialgroup($group, true);

		$group['delete_group'] = can_delete_group($group);
		$group['edit_group'] = can_edit_group($group);
		$group['leave_group'] = can_leave_group($group);
		$group['group_options'] = ($group['delete_group'] OR $group['edit_group'] OR $group['leave_group']);

		$groups[] = $group;
	}
	$vbulletin->db->free_result($result);

	return $groups;
}

/**
 * Fetches newest groups from datastore or rebuilds the cache.
 *
 * @param boolean $force_rebuild				Force the cache to be rebuilt
 * @param boolean $without_icons				Fetch groups that have no icon
 * @return array								Array of groupinfos
 */
function fetch_socialgroup_newest_groups($force_rebuild = false, $without_icons = false, $listview = false)
{
	global $vbulletin;

	$groups = $vbulletin->sg_newest_groups;

	$hook_query_fields = $hook_query_joins = $hook_query_where = '';
	($hook = vBulletinHook::fetch_hook('group_fetch_newest')) ? eval($hook) : false;

	if ($force_rebuild OR !is_array($groups))
	{
		$sql = "SELECT
					socialgroup.*, socialgroup.dateline AS createdate, sgc.title AS categoryname, sgc.socialgroupcategoryid AS categoryid,
					socialgroup.groupid, socialgroup.name, socialgroup.description, socialgroup.dateline, sgicon.dateline AS icondateline,
					socialgroupmember.type AS membertype, socialgroupmember.dateline AS joindate,
					sgicon.thumbnail_width AS iconthumb_width, sgicon.thumbnail_height AS iconthumb_height
				$hook_query_fields
				FROM " . TABLE_PREFIX . "socialgroup AS socialgroup
				LEFT JOIN " . TABLE_PREFIX ."socialgroupmember AS socialgroupmember
					ON (socialgroup.groupid = socialgroupmember.groupid AND socialgroupmember.userid = " . $vbulletin->userinfo['userid'] . ")
				LEFT JOIN " . TABLE_PREFIX . "socialgroupicon AS sgicon ON (sgicon.groupid = socialgroup.groupid)
				INNER JOIN " . TABLE_PREFIX . "socialgroupcategory AS sgc ON (sgc.socialgroupcategoryid = socialgroup.socialgroupcategoryid)
				$hook_query_joins
				$hook_query_where
				ORDER BY socialgroup.dateline DESC
				LIMIT 0, " . ($vbulletin->options['sg_newgroups_count'] ? intval($vbulletin->options['sg_newgroups_count']) : 5) . "
		";

		$newgroups = $vbulletin->db->query_read_slave($sql);

		$groups = array();
		while ($group = $vbulletin->db->fetch_array($newgroups))
		{
			$groups[] = $group;
		}
		$vbulletin->db->free_result($newgroups);

		build_datastore('sg_newest_groups', serialize($groups), 1);
	}

	return $groups;
}

/**
 * Fetches groups that the current user is a member of
 * If iconview is false, then the info for the listview will be fetched.
 *
 * @param boolean $iconview						Selects info for the icon view
 * @return array								Array of groupinfos
 */
function fetch_socialgroups_mygroups($iconview = true)
{
	global $vbulletin;

	$result = $vbulletin->db->query_read_slave("
		SELECT socialgroup.*, socialgroup.dateline AS createdate,
			socialgroupmember.type AS membertype, socialgroupmember.dateline AS joindate,
			groupread.readtime
			,sgicon.dateline AS icondateline, sgicon.thumbnail_width AS iconthumb_width, sgicon.thumbnail_height AS iconthumb_height
			,sgc.title AS categoryname, sgc.socialgroupcategoryid AS categoryid
		FROM " . TABLE_PREFIX . "socialgroupmember AS socialgroupmember
		INNER JOIN " . TABLE_PREFIX ."socialgroup AS socialgroup ON (socialgroup.groupid = socialgroupmember.groupid)
		LEFT JOIN " . TABLE_PREFIX . "groupread AS groupread ON (groupread.groupid = socialgroup.groupid
										AND groupread.userid = " . $vbulletin->userinfo['userid'] . ")
		LEFT JOIN " . TABLE_PREFIX . "socialgroupicon AS sgicon ON sgicon.groupid = socialgroup.groupid
		INNER JOIN " . TABLE_PREFIX . "socialgroupcategory AS sgc ON (sgc.socialgroupcategoryid = socialgroup.socialgroupcategoryid)
		WHERE socialgroupmember.userid = " . $vbulletin->userinfo['userid'] . " AND socialgroupmember.type = 'member'
		ORDER BY socialgroupmember.dateline DESC
	");

	$groups = array();
	while ($group = $vbulletin->db->fetch_array($result))
	{
		if (!$iconview)
		{
			$group['delete_group'] = can_delete_group($group);
			$group['edit_group'] = can_edit_group($group);
			$group['leave_group'] = can_leave_group($group);
			$group['group_options'] = ($group['delete_group'] OR $group['edit_group'] OR $group['leave_group']);
		}

		$groups[] = $group;
	}
	$vbulletin->db->free_result($result);

	return $groups;
}

/**
 * Fetches groups recently updated
 *
 * @return array								Array of groupinfos
 */
function fetch_socialgroups_updatedgroups()
{
	global $vbulletin;

	$result = $vbulletin->db->query_read_slave("
		SELECT socialgroup.*, socialgroup.dateline AS createdate,
			socialgroupmember.type AS membertype, socialgroupmember.dateline AS joindate,
			groupread.readtime
			,sgicon.dateline AS icondateline, sgicon.thumbnail_width AS iconthumb_width, sgicon.thumbnail_height AS iconthumb_height
			,sgc.title AS categoryname, sgc.socialgroupcategoryid AS categoryid
		FROM " . TABLE_PREFIX . "socialgroup AS socialgroup
		LEFT JOIN " . TABLE_PREFIX ."socialgroupmember AS socialgroupmember
			ON (socialgroup.groupid = socialgroupmember.groupid AND socialgroupmember.userid = " . $vbulletin->userinfo['userid'] . ")
		LEFT JOIN " . TABLE_PREFIX . "groupread AS groupread ON (groupread.groupid = socialgroup.groupid
										AND groupread.userid = " . $vbulletin->userinfo['userid'] . ")
		LEFT JOIN " . TABLE_PREFIX . "socialgroupicon AS sgicon ON sgicon.groupid = socialgroup.groupid
		INNER JOIN " . TABLE_PREFIX . "socialgroupcategory AS sgc ON (sgc.socialgroupcategoryid = socialgroup.socialgroupcategoryid)
		ORDER BY socialgroup.lastupdate DESC
		LIMIT 0, 10
	");

	$groups = array();
	while ($group = $vbulletin->db->fetch_array($result))
	{
		$group['delete_group'] = can_delete_group($group);
		$group['edit_group'] = can_edit_group($group);
		$group['leave_group'] = can_leave_group($group);
		$group['group_options'] = ($group['delete_group'] OR $group['edit_group'] OR $group['leave_group']);

		$groups[] = $group;
	}
	$vbulletin->db->free_result($result);

	return $groups;
}

function cache_group_members($groupids = null, $force_rebuild = false)
{
	global $vbulletin;
	static $group_members;

	if ($group_members AND !$force_rebuild)
	{
		return $group_members;
	}

	if (!is_array($groupids) OR !$groupids)
	{
		return null;
	}

	/*
	 * Explain to this query:
	 * We only need to fetch at most 10 new joined member info for each group.
	 * Fetching the members for each group separately is faster than one query with a heavy subquery
	 */

	$group_members = array();
	foreach ($groupids AS $groupid) {
		$result = $vbulletin->db->query_read_slave("
			SELECT user.*, socialgroupmember.groupid
			" . ($vbulletin->options['avatarenabled'] ? ",avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
			FROM " . TABLE_PREFIX . "socialgroupmember AS socialgroupmember
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (socialgroupmember.userid = user.userid)
			" . ($vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
			WHERE socialgroupmember.groupid = $groupid AND socialgroupmember.type = 'member'
			ORDER BY socialgroupmember.dateline DESC
			LIMIT 10
				");
		while ($member = $vbulletin->db->fetch_array($result))
		{
			fetch_avatar_from_userinfo($member, true, true);
			$group_members[$member['groupid']][] = $member;
		}
	}	
	
	return $group_members;

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 44573 $
|| ####################################################################
\*======================================================================*/
