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
* Fetches album information from the database. Also does some preparation
* on the data for display.
*
* @param	integer	ID of the album
*
* @return	array	Array of album info
*/
function fetch_albuminfo($albumid)
{
	global $vbulletin;

	$albuminfo = $vbulletin->db->query_first("
		SELECT album.*, user.username
		FROM " . TABLE_PREFIX . "album AS album
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON (album.userid = user.userid)
		WHERE album.albumid = " . intval($albumid)
	);
	if (!$albuminfo)
	{
		return array();
	}

	$albuminfo['title_html'] = fetch_word_wrapped_string(fetch_censored_text($albuminfo['title']));
	$albuminfo['description_html'] = nl2br(fetch_word_wrapped_string(fetch_censored_text($albuminfo['description'])));
	$albuminfo['picturecount'] = (!can_moderate(0, 'canmoderatepictures') AND $albuminfo['userid'] != $vbulletin->userinfo['userid']) ?
		$albuminfo['visible'] :
		$albuminfo['visible'] + $albuminfo['moderation']
	;

	($hook = vBulletinHook::fetch_hook('album_fetch_albuminfo')) ? eval($hook) : false;

	return $albuminfo;
}

/**
* Fetches picture info for the specified picture/album combination. That is,
* the picture must be in the specified album. Also does some preperation on
* the data for display.
*
* @param	integer	ID of picture
* @param	integer	ID of album
*
* @return	array	Array of picture information
*/
function fetch_pictureinfo($attachmentid, $albumid)
{
	global $vbulletin;

	$pictureinfo = $vbulletin->db->query_first("
		SELECT
			a.attachmentid, a.userid, a.caption, a.reportthreadid, a.state, a.dateline, a.contentid AS albumid,
			fd.filedataid, fd.filesize, fd.width, fd.height, fd.thumbnail_filesize, IF(fd.thumbnail_filesize > 0, 1, 0) AS hasthumbnail
		FROM " . TABLE_PREFIX . "attachment AS a
		INNER JOIN " . TABLE_PREFIX . "filedata AS fd ON (a.filedataid = fd.filedataid)
		INNER JOIN " . TABLE_PREFIX . "album AS album ON (a.contentid = album.albumid)
		WHERE
			a.attachmentid = " . intval($attachmentid) . "
				AND
			album.albumid = " . intval($albumid) . "
	");

	if (!$pictureinfo)
	{
		return array();
	}

	$pictureinfo['caption_html'] = nl2br(fetch_word_wrapped_string(fetch_censored_text($pictureinfo['caption'])));

	($hook = vBulletinHook::fetch_hook('album_fetch_pictureinfo')) ? eval($hook) : false;

	return $pictureinfo;
}

/**
* Prepares a picture array for thumbnail display.
*
* @param	array	Array of picture info
*
* @return	array	Array of picture info modified
*/
function prepare_pictureinfo_thumb($pictureinfo)
{
	global $vbulletin;

	$pictureinfo['caption_preview'] = fetch_censored_text(fetch_trimmed_title(
		$pictureinfo['caption'],
		$vbulletin->options['album_captionpreviewlen']
	));

	$pictureinfo['dimensions'] = ($pictureinfo['thumbnail_width'] ? "width=\"$pictureinfo[thumbnail_width]\" height=\"$pictureinfo[thumbnail_height]\"" : '');
	$pictureinfo['date'] = vbdate($vbulletin->options['dateformat'], $pictureinfo['dateline'], true);
	$pictureinfo['time'] = vbdate($vbulletin->options['timeformat'], $pictureinfo['dateline']);

	($hook = vBulletinHook::fetch_hook('album_prepare_thumb')) ? eval($hook) : false;

	return $pictureinfo;
}

/**
* Determines information about this picture's location in the specified
* album or group. Returns an array with information about the first, previous,
* next, and/or last pictures.
*
* @param	resource	DB query result
* @param	integer		Current picture ID
*
* @return	array		Information about previous/next pictures (including phrases)
*/
function fetch_picture_location_info($navpictures_sql, $attachmentid)
{
	global $vbphrase, $show, $vbulletin;

	$navpictures = array();
	$output = array();
	$cur_pic_position = -1;

	$key = 0;
	while ($navpicture = $vbulletin->db->fetch_array($navpictures_sql))
	{
		$navpictures["$key"] = $navpicture['attachmentid'];

		if ($navpicture['attachmentid'] == $attachmentid)
		{
			$cur_pic_position = $key;
		}

		if ($cur_pic_position > 0 AND $key == $cur_pic_position + 1)
		{
			// we've matched at something other than the first entry,
			// and we're one past this current key, which means that we have a next and prev.
			// If we matched at key 0, we need to get the last entry, so keep going.
			break;
		}

		$key++;
	}

	$show['picture_nav'] = ($cur_pic_position > -1 AND sizeof($navpictures) > 1);

	if ($show['picture_nav'])
	{
		if (isset($navpictures[$cur_pic_position - 1]))
		{
			// have a previous pic
			$output['prev_attachmentid'] = $navpictures[$cur_pic_position - 1];
			$output['prev_text'] = $vbphrase['previous_picture'];
			$output['prev_text_short'] = $vbphrase['prev_picture_short'];
		}
		else
		{
			// go to end
			$output['prev_attachmentid'] = end($navpictures);
			$output['prev_text'] = $vbphrase['last_picture'];
			$output['prev_text_short'] = $vbphrase['last_picture_short'];
		}

		if (isset($navpictures[$cur_pic_position + 1]))
		{
			// have a next pic
			$output['next_attachmentid'] = $navpictures[$cur_pic_position + 1];
			$output['next_text'] = $vbphrase['next_picture'];
			$output['next_text_short'] = $vbphrase['next_picture_short'];
		}
		else
		{
			// go to beginning
			$output['next_attachmentid'] = $navpictures[0];
			$output['next_text'] = $vbphrase['first_picture'];
			$output['next_text_short'] = $vbphrase['first_picture_short'];
		}
	}

	$output['pic_position'] = $cur_pic_position + 1; // make it start at 1, instead of 0

	return $output;
}

/**
* Fetches the overage value for total number of pictures for a user.
*
* @param	integer	User ID to look for
* @param	integer	Maximum number of pics allowed; 0 means no limit
* @param	integer	Number of images they are currently uploading that aren't in the DB yet
*
* @return	integer	Amount of overage; <= 0 means no overage
*/
function fetch_count_overage($userid, $albumid, $maxpics, $upload_count = 1)
{
	global $vbulletin;

	if (!$maxpics)
	{
		// never over
		return -1;
	}

	require_once(DIR . '/includes/class_bootstrap_framework.php');
	require_once(DIR . '/vb/types.php');
	vB_Bootstrap_Framework::init();
	$types = vB_Types::instance();
	$contenttypeid = intval($types->getContentTypeID('vBForum_Album'));

	$count = $vbulletin->db->query_first("
		SELECT 
			COUNT(*) AS total
		FROM " . TABLE_PREFIX . "attachment AS a
		WHERE
			a.contentid =  " . intval($albumid) . "
				AND
			a.contenttypeid = $contenttypeid
				AND
			a.userid = " . intval($userid) . "
	");

	return $count['total'] + $upload_count - $maxpics;
}

/**
* Fetches the overage value for total filesize of pictures for a user.
*
* @param	integer	User ID to look for
* @param	integer	Maximum total filesize allowed; 0 means no limit
* @param	integer	Number of bytes they are currently uploading that aren't in the DB yet
*
* @return	integer	Amount of overage; <= 0 means no overage
*/
function fetch_size_overage($userid, $maxsize, $upload_bytes = 0)
{
	global $vbulletin;

	if (!$maxsize)
	{
		// never over
		return -1;
	}

	require_once(DIR . '/includes/class_bootstrap_framework.php');
	require_once(DIR . '/vb/types.php');
	vB_Bootstrap_Framework::init();
	$types = vB_Types::instance();
	$contenttypeid = intval($types->getContentTypeID('vBForum_Album'));

	$size = $vbulletin->db->query_first("
		SELECT SUM(fd.filesize) AS totalsize
		FROM " . TABLE_PREFIX . "attachment AS a
		INNER JOIN " . TABLE_PREFIX . "filedata AS fd ON (a.filedataid = fd.filedataid)
		WHERE
			a.userid = " . intval($userid) . "
				AND
			a.contenttypeid = $contenttypeid
	");

	return $size['totalsize'] + $upload_bytes - $maxsize;
}

/**
* Determines whether the current browsing user can see private albums
* for the specified album owner.
*
* @param	integer	Album owner user ID
*
* @return	boolean True if yes
*/
function can_view_private_albums($albumuserid)
{
	global $vbulletin;
	static $albumperms_cache = array();

	$albumuserid = intval($albumuserid);
	if (isset($albumperms_cache["$albumuserid"]))
	{
		return $albumperms_cache["$albumuserid"];
	}

	if ($vbulletin->userinfo['userid'] == $albumuserid)
	{
		$can_see_private = true;
	}
	else if ($vbulletin->userinfo['userid'] == 0)
	{
		$can_see_private = false;
	}
	else if (can_moderate(0, 'caneditalbumpicture') OR can_moderate(0, 'candeletealbumpicture'))
	{
		$can_see_private = true;
	}
	else
	{
		$friend_record = $vbulletin->db->query_first("
			SELECT userid
			FROM " . TABLE_PREFIX . "userlist
			WHERE userid = $albumuserid
				AND relationid = " . $vbulletin->userinfo['userid'] . "
				AND type = 'buddy'
		");
		$can_see_private = ($friend_record ? true : false);
	}

	($hook = vBulletinHook::fetch_hook('album_can_see_private')) ? eval($hook) : false;

	$albumperms_cache["$albumuserid"] = $can_see_private;
	return $can_see_private;
}

/**
* Determines whether the current browsing user can see profile albums
* for the specified album owner.
*
* @param	integer	Album owner user ID
*
* @return	boolean True if yes
*/
function can_view_profile_albums($albumuserid)
{
	global $vbulletin;

	$albumuserid = intval($albumuserid);

	return ($vbulletin->userinfo['userid'] == $albumuserid OR can_moderate(0, 'caneditalbumpicture') OR can_moderate(0, 'candeletealbumpicture')) ? true : false;
}

/**
* Checks an album and adds it to the recently updated albums list
* if it is generally viewable by other users.
*
* @param array mixed $userinfo					Info array of the album owner
* @param array mixed $albuminfo					Info array of the album
*/
function exec_album_updated($userinfo, $albuminfo)
{
	global $vbulletin;

	cache_permissions($userinfo);

	if (4 == $userinfo['usergroupid'])
	{
		return;
	}

	if (!($userinfo['permissions']['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canalbum']))
	{
		return;
	}

	if (!$albuminfo['albumid'])
	{
		return;
	}

	$vbulletin->db->query_write("
		REPLACE INTO " . TABLE_PREFIX . "albumupdate
			(albumid, dateline)
		VALUES
			(" . intval($albuminfo['albumid']) . ", " . TIMENOW . ")
	");
}

/**
 * Rebuilds Album Update cache
 */
function exec_rebuild_album_updates()
{
	global $vbulletin;

	$vbulletin->db->query_write("TRUNCATE " . TABLE_PREFIX . "albumupdate");

	if (!$vbulletin->options['album_recentalbumdays'])
	{
		return;
	}

	$results = $vbulletin->db->query_read("
		SELECT album.albumid, album.userid, album.lastpicturedate, user.usergroupid, user.infractiongroupids, user.infractiongroupid
		FROM " . TABLE_PREFIX . "album AS album
		INNER JOIN " . TABLE_PREFIX . "user AS user ON (album.userid = user.userid)
		WHERE lastpicturedate > " . (TIMENOW - $vbulletin->options['album_recentalbumdays'] * 86400) . "
			AND state = 'public'
			AND visible > 0
	");

	$recent_updates = array();
	while ($result = $vbulletin->db->fetch_array($results))
	{
		cache_permissions($result, false);

		if ((4 != $result['permissions']['usergroupid']) AND (4 != $result['infractiongroupid']) AND ($result['permissions']['albumpermissions'] & $vbulletin->bf_ugp_albumpermissions['canalbum']))
		{
			$recent_updates[] = "($result[albumid], $result[lastpicturedate])";
		}
	}
	$vbulletin->db->free_result($results);

	if (sizeof($recent_updates))
	{
		$vbulletin->db->query_write("
			INSERT INTO " . TABLE_PREFIX . "albumupdate
				(albumid, dateline)
			VALUES
				" . implode (',', $recent_updates) . "
		");
	}
}
/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 34703 $
|| ####################################################################
\*======================================================================*/
?>