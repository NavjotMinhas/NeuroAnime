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
require_once(DIR . '/includes/functions_album.php');

/**
* Class for displaying a vBulletin Album Picture
*
* @package 		vBulletin
* @version		$Revision: 29983 $
* @date 		$Date: 2009-03-20 16:22:30 -0700 (Fri, 20 Mar 2009) $
*
*/
class vB_Attachment_Display_Single_vBForum_Album extends vB_Attachment_Display_Single
{
	/**
	* Verify permissions of a single attachment
	*
	* @return	bool
	*/
	public function verify_attachment()
	{
		if (!($this->registry->options['socnet'] & $this->registry->bf_misc_socnet['enable_albums']))
		{
			return false;
		}

		$hook_query_fields = $hook_query_joins = $hook_query_where = '';
		($hook = vBulletinHook::fetch_hook('attachment_start')) ? eval($hook) : false;

		$selectsql = array(
			"album.state AS albumstate, album.albumid, album.userid AS albumuserid",
			"pbp.requirement AS privacy_requirement",
		);

		$joinsql = array(
			"LEFT JOIN " . TABLE_PREFIX . "album AS album ON (album.albumid = a.contentid)",
			"LEFT JOIN " . TABLE_PREFIX . "profileblockprivacy AS pbp ON (pbp.userid = a.userid AND pbp.blockid = 'albums')",
		);

		if (!$this->verify_attachment_specific('vBForum_Album', $selectsql, $joinsql))
		{
			return false;
		}

/*	TODO
		$this->browsinginfo = array(
			'bloginfo' => array(
				'blogid' => $this->attachmentinfo['blogid'],
			),
			'userinfo' => array(
				'userid' => $this->attachmentinfo['userid'],
			),
		);
*/
		require_once(DIR . '/includes/functions_user.php');
		if ($this->attachmentinfo['contentid'] == 0)
		{
			// there may be a condition where certain moderators could benefit by seeing these, I just don't know of any conditions at present
			if ($this->registry->userinfo['userid'] != $this->attachmentinfo['userid'])
			{
				return false;
			}
		}
		else if (
			!$this->attachmentinfo['albumid']
				OR
			$this->attachmentinfo['albumuserid'] != $this->attachmentinfo['userid']
				OR
			(
				$this->attachmentinfo['state'] == 'moderation'
					AND
				$this->attachmentinfo['userid'] != $this->registry->userinfo['userid']
					AND
				!can_moderate(0, 'canmoderatepictures')
					AND
				!can_moderate(0, 'caneditalbumpicture')
			)
				OR
			(
				$this->attachmentinfo['privacy_requirement']
					AND
				fetch_user_relationship($this->attachmentinfo['userid'], $this->registry->userinfo['userid']) < $this->attachmentinfo['privacy_requirement']
			)
				OR
			(
				$this->attachmentinfo['albumstate'] != 'profile'
					AND
				!($this->registry->userinfo['permissions']['albumpermissions'] & $this->registry->bf_ugp_albumpermissions['canviewalbum'])
			)
				OR
			(
				$this->attachmentinfo['albumstate'] == 'private'
					AND
				!can_view_private_albums($this->attachmentinfo['userid'])
			)
		)
		{
			// echo clear.gif, not permissions error. This may only be needed for 'albumstate' == 'profile'
			return 0;
		}

		return true;
	}
}

/**
* Class for display of multiple vBulletin Album attachments
*
* @package 		vBulletin
* @version		$Revision: 29983 $
* @date 		$Date: 2009-03-20 16:22:30 -0700 (Fri, 20 Mar 2009) $
*
*/
class vB_Attachment_Display_Multiple_vBForum_Album extends vB_Attachment_Display_Multiple
{
	/**
	* Constructor
	*
	* @param	vB_Registry
	* @param	integer			Unique id of this contenttype (forum post, blog entry, etc)
	*
	* @return	void
	*/
	public function __construct(&$registry, $contenttypeid)
	{
		parent::__construct($registry);
		$this->contenttypeid = $contenttypeid;
		if (!($this->registry->options['socnet'] & $this->registry->bf_misc_socnet['enable_albums']))
		{
			$this->usable = false;
		}
	}

	/**
	* Return content specific information that relates to the ownership of attachments
	*
	* @param	array		List of attachmentids to query
	*
	* @return	void
	*/
	public function fetch_sql($attachmentids)
	{
		$selectsql = array(
			"album.title, album.albumid",
			"user.username",
		);

		$joinsql = array(
			"INNER JOIN " . TABLE_PREFIX . "album AS album ON (album.albumid = a.contentid)",
			"LEFT JOIN " . TABLE_PREFIX . "user AS user ON (a.userid = user.userid)",
		);

		return $this->fetch_sql_specific($attachmentids, $selectsql, $joinsql);
	}

	/**
	* Fetches the SQL to be queried as part of a UNION ALL od an attachment query, verifying read permissions
	*
	* @param	string	SQL WHERE criteria
	* @param	string	Contents of the SELECT portion of the main query
	*
	* @return	string
	*/
	protected function fetch_sql_ids($criteria, $selectfields)
	{
		$joinsql = array(
			"INNER JOIN " . TABLE_PREFIX . "album AS album ON (album.albumid = a.contentid)",
			"LEFT JOIN " . TABLE_PREFIX . "profileblockprivacy AS pbp ON (pbp.userid = a.userid AND pbp.blockid = 'albums')",
			"LEFT JOIN " . TABLE_PREFIX . "user AS user ON (a.userid = user.userid)",
		);

		$subwheresql = array();

		if (
			!can_moderate(0, 'canmoderatepictures')
				AND
			!can_moderate(0, 'caneditalbumpicture')
		)
		{
			$subwheresql[] = "(
				a.state <> 'moderation'
					OR
				a.userid = {$this->registry->userinfo['userid']}
			)";
		}

		if (!($this->registry->userinfo['permissions']['albumpermissions'] & $this->registry->bf_ugp_albumpermissions['canviewalbum']))
		{
			$subwheresql[] = "album.state = 'profile'";
		}

		// Get a list of our buddies so that we can query for private albums properly
		if (!$this->registry->userinfo['userid'])
		{
			$subwheresql[] = "album.state <> 'private'";
		}
		else if (!can_moderate(0, 'caneditalbumpicture') AND !can_moderate(0, 'candeletealbumpicture'))
		{
			$buddylist = array($this->registry->userinfo['userid']);
			$buddies = $this->registry->db->query_read_slave("
				SELECT userid
				FROM " . TABLE_PREFIX . "userlist
				WHERE
					relationid = {$this->registry->userinfo['userid']}
						AND
					type = 'buddy'
			");
			while ($buddy = $this->registry->db->fetch_array($buddies))
			{
				$buddylist[] = $buddy['userid'];
			}
			$subwheresql[] =
			"(
				album.state <> 'private'
					OR
				album.userid IN (" . implode(",", $buddylist) . ")
			)";
		}

		if (!can_moderate())
		{
			$friends = array();
			$ignores = array();
			$buddies = array();
			$contacts = $this->registry->db->query_read_slave("
				SELECT
					type, friend, userid
				FROM " . TABLE_PREFIX . "userlist
				WHERE
					relationid = {$this->registry->userinfo['userid']}
			");
			while ($contact = $this->registry->db->fetch_array($contacts))
			{
				if ($contact['friend'] == 'yes')
				{
					$friends[] = $contact['userid'];
				}
				else if ($contact['type'] == 'ignore')
				{
					$ignores[] = $contact['userid'];
				}
				else if ($contact['type'] == 'buddy')
				{
					$buddies[] = $contact['userid'];
				}
			}
			$suborsql = array(
				"a.userid = {$this->registry->userinfo['userid']}",
				"pbp.requirement IS NULL",
				"pbp.requirement = 0",
			);

			if ($this->registry->userinfo['userid'])
			{
				$suborsql[] = "pbp.requirement = 1";
			}

			if (!empty($friends) OR !empty($buddies))
			{
				$suborsql[] = "
				(
					pbp.requirement = 2
						AND
					a.userid IN (" . implode(",", array_merge($friends, $buddies)) . ")
				)
				";
			}

			if (!empty($friends))
			{
				$suborsql[] = "
				(
					pbp.requirement = 3
						AND
					a.userid IN (" . implode(",", $friends) . ")
				)
				";
			}

			$subwheresql[] = "(" . implode(" OR ", $suborsql) . ")";

			if (!empty($ignores))
			{
				$subwheresql[] = "a.userid NOT IN (" . implode(",", $ignores) . ")";
			}
		}

		return $this->fetch_sql_ids_specific($this->contenttypeid, $criteria, $selectfields, $subwheresql, $joinsql);
	}

	/**
	* Formats $albuminfo content for display.  The SQL fields in $albuminfo are set in fetch_sql() above
	*
	* @param	array		Entry information
	*
	* @return	array
	*/
	public function process_attachment_template($albuminfo, $showthumbs = false)
	{
		global $show, $vbphrase;

		$show['thumbnail'] = ($albuminfo['hasthumbnail'] == 1 AND $showthumbs);
		$show['inprogress'] = $albuminfo['inprogress'];

		$show['candelete'] = false;
		$show['canmoderate'] = can_moderate(0, 'canmoderatepictures');
		if (
			$albuminfo['inprogress']
				OR
			$this->registry->userinfo['userid'] == $albuminfo['userid']
				OR
			can_moderate(0, 'candeletealbumpicture')
				OR
			(
				$albuminfo['state'] == 'visible'
					OR
				can_moderate(0, 'canmoderatepictures')
			)
		)
		{
			$show['candelete'] = true;
		}

		return array(
			'template' => 'album',
			'album'    => $albuminfo,
		);
	}

	/**
	* Return Album specific url to the owner an attachment
	*
	* @param	array		Content information
	*
	* @return	string
	*/
	protected function fetch_content_url_instance($contentinfo)
	{
		return "album.php?" . $this->registry->session->vars['sessionurl'] . 'albumid=' . $contentinfo['contentid'];
	}
}

// #######################################################################
// ############################# STORAGE #################################
// #######################################################################

/**
* Class for storing a vBulletin album attachment
*
* @package 		vBulletin
* @version		$Revision: 29983 $
* @date 		$Date: 2009-03-20 16:22:30 -0700 (Fri, 20 Mar 2009) $
*
*/
class vB_Attachment_Store_vBForum_Album extends vB_Attachment_Store
{
	/**
	*	Albuminfo
	*
	* @var	array
	*/
	protected $albuminfo = array();

	/**
	*	Uploadcount
	*
	* @var	int
	*/
	protected $uploadcount = 0;

	/**
	*	Override the vboption for thumbnails
	*
	* @var	boolean
	*/
	public $forcethumbnail = true;

	/**
	* Constructor
	*
	* @return	void
	*/
	public function __construct(&$registry, $contenttypeid, $categoryid, $values)
	{
		parent::__construct($registry, $contenttypeid, $categoryid, $values);
	}

	/**
	* Given an attachmentid, retrieve values that verify_permissions needs
	*
	* @param	int	Attachmentid
	*
	* @return	array
	*/
	public function fetch_associated_contentinfo($attachmentid)
	{
		return $this->registry->db->query_first("
			SELECT
				al.albumid
			FROM " . TABLE_PREFIX . "attachment AS a
			INNER JOIN " . TABLE_PREFIX . "album AS al ON (al.albumid = a.contentid)
			WHERE
				a.attachmentid = " . intval($attachmentid) . "
		");
	}

	/**
	* Verifies permissions to attach content to albums
	*
	* @param	array	Contenttype information - bypass reading environment settings
	*
	* @return	boolean
	*/
	public function verify_permissions($info = array())
	{
		global $show;

		if ($info['albumid'])
		{
			$this->values['albumid'] = $info['albumid'];
		}
		else
		{
			$this->values['albumid'] = intval($this->values['albumid']);
		}
		
		if (!($this->albuminfo = fetch_albuminfo($this->values['albumid'])))
		{
			return false;
		}

		if ($this->albuminfo['userid'] != $this->registry->userinfo['userid'])
		{
			return false;
		}

		if ($this->registry->userinfo['permissions']['albummaxpics'])
		{
			// assume we are uploading 1 pic (at least)
			$this->totalpics_overage = fetch_count_overage($this->registry->userinfo['userid'], $this->registry->userinfo['permissions']['albummaxpics'], 0);
			if ($this->totalpics_overage >= 0)
			{
				standard_error(fetch_error('upload_total_album_pics_countfull', vb_number_format($this->totalpics_overage)));
			}
		}

		if ($this->registry->options['album_maxpicsperalbum'])
		{
			$this->albumpics_overage = ($this->albuminfo['visible'] + $this->albuminfo['moderation'] - $this->registry->options['album_maxpicsperalbum']);
			if ($this->albumpics_overage >= 0)
			{
				standard_error(fetch_error('upload_album_pics_countfull', vb_number_format($this->albumpics_overage)));
			}
		}

		if ($this->registry->userinfo['permissions']['albummaxsize'])
		{
			// we don't know the size of the image yet, so ignore it and error if we have 0 bytes (or less) remaining
			$size_overage = fetch_size_overage($this->registry->userinfo['userid'], $this->registry->userinfo['permissions']['albummaxsize'], 0);
			if ($size_overage >= 0)
			{
				standard_error(fetch_error('upload_album_sizefull', vb_number_format($size_overage, 0, true)));
			}
		}

		return true;
	}

	/**
	* Verify amount of attachments has not exceed maximum number based on permissions for albums
	*
	* @param	array		Attachment information
	*
	* @return	bool	true if we are under the limit
	*/
	protected function verify_max_attachments($attachment)
	{
		// verify the global max pics per album setting
		if	($this->registry->options['album_maxpicsperalbum'] AND $this->attachcount >= $this->registry->options['album_maxpicsperalbum'])
		{
			$this->errors[] = array(
				'error'    => fetch_error('upload_album_pics_countfull', vb_number_format($this->attachcount + 1 - $this->registry->options['album_maxpicsperalbum'])),
				'filename' => $attachment['name'],
			);
			return false;
		}

		// verify the usergroup max pics per album setting
		if ($this->registry->userinfo['permissions']['albummaxpics'] AND $this->attachcount >= $this->registry->userinfo['permissions']['albummaxpics'])
		{
			$this->errors[] = array(
				'error'    => fetch_error('upload_album_pics_countfull', vb_number_format($this->attachcount + 1 - $this->registry->userinfo['permissions']['albummaxpics'])),
				'filename' => $attachment['name'],
			);
			return false;
		}

		// under the limit
		return true;
	}

	/**
	* Verifies permissions to attach content to albums
	*
	* @param	object	vB_Upload
	* @param	array		Information about uploaded attachment
	*
	* @return	integer
	*/
	protected function process_upload($upload, $attachment, $imageonly = false)
	{
		
		$this->contentid = $this->values['albumid'];
		// fetches the attachment count, code above removed
		$this->fetch_attachcount();
		$this->contentid = 0;

		// these values are negative (non-overage), so we need to flip them around for a "remaining" value
		if (isset($this->totalpics_overage) AND $this->totalpics_overage >= 0)
		{
			standard_error(fetch_error('upload_album_pics_countfull', vb_number_format(-1 * $this->albumpics_overage)));
		}

		$moderatedpictures = (
			(
				$this->registry->options['albums_pictures_moderation']
					OR
				!($this->registry->userinfo['permissions']['albumpermissions'] & $this->registry->bf_ugp_albumpermissions['picturefollowforummoderation'])
			)
				AND
			!can_moderate(0, 'canmoderatepictures')
		);

		$this->uploadcount++;

		if (!($attachmentid = parent::process_upload($upload, $attachment, $imageonly)))
		{
			$this->uploadcount--;
			return false;
		}
		// add to updated list
		if (
			can_moderate(0, 'canmoderatepictures')
				OR
			(
				!$this->registry->options['albums_pictures_moderation']
		 			AND
		 		$this->registry->userinfo['permissions']['albumpermissions'] & $this->registry->bf_ugp_albumpermissions['picturefollowforummoderation']
			)
		)
		{
			exec_album_updated($this->registry->userinfo, $this->albuminfo);
		}

		if (!$moderatedpictures AND !$this->albuminfo['coverattachmentid'])
		{
			$this->albuminfo['coverattachmentid'] = $attachmentid;
			// no cover -> set cover to the first pic uploaded
			$this->registry->db->query_write("
				UPDATE " . TABLE_PREFIX . "album
				SET
					coverattachmentid = $attachmentid
				WHERE
					albumid = {$this->albuminfo['albumid']}
			");
		}

		return $attachmentid;
	}

	/**
	* Set attachment to moderated if need be, functions as pre_save()
	*
	* @return	object
	*/
	protected function &fetch_attachdm()
	{
		$attachdata =& parent::fetch_attachdm();
		$attachdata->set_info('contentid', $this->values['albumid']);

		$should_moderate = (
			$this->registry->options['albums_pictures_moderation']
				OR
			!($this->registry->userinfo['permissions']['albumpermissions'] & $this->registry->bf_ugp_albumpermissions['picturefollowforummoderation'])
		);

		$state = ($should_moderate AND !can_moderate(0, 'canmoderatepictures')) ? 'moderation' : 'visible';
		$attachdata->set('state', $state);

		return $attachdata;
	}
}

/**
* Class for deleting a vBulletin album picture attachment
*
* @package 		vBulletin
* @version		$Revision: 29983 $
* @date 		$Date: 2009-03-20 16:22:30 -0700 (Fri, 20 Mar 2009) $
*
*/
class vB_Attachment_Dm_vBForum_Album extends vB_Attachment_Dm
{
/**
	* Operations following a save
	*
	* @return
	*/
	public function post_save($attachdm)
	{
		$attachmentid = $attachdm->fetch_field('attachmentid');
		if (!$attachdm->condition AND !empty($attachdm->info['albums']))
		{
			$dateline = (!$attachdm->info['dateline'] ? TIMENOW : $$attachdm->info['dateline']);
			$albumids = array();
			foreach ($attachdm->info['albums'] AS $album)
			{
				$albumids[] = intval($album['albumid']);
			}

			$this->registry->db->query_write("
				UPDATE " . TABLE_PREFIX . "album
				SET " . ($attachdm->fetch_field('state') == 'visible'
						?	"visible = visible + 1, lastpicturedate = IF($dateline > lastpicturedate, $dateline, lastpicturedate)"
						: "moderation = moderation + 1") . "
				WHERE albumid IN (" . implode(',', $albumids) . ")
			");
		}

		if (
			$attachdm->condition
				AND
			$attachdm->fetch_field('contentid') != $attachdm->existing('contentid')
				AND
			$attachdm->info['albuminfo']['userid'] == $this->registry->userinfo['userid']
		)
		{
			if (
				$attachdm->info['albuminfo']['state'] == 'private'
					AND
				$attachdm->info['destination']
					AND
				$attachdm->info['destination']['state'] != 'private'
			)
			{
				if ($attachdm->fetch_field('state') != 'private' AND $attachdm->existing['state'] == 'private')
				{
					$this->registry->db->query_write("
						DELETE FROM " . TABLE_PREFIX . "usercss
						WHERE
							property = 'background_image'
								AND
							value = '{$attachdm->info['albuminfo']['albumid']},$attachmentid'
								AND
							userid = {$attachdm->info['albuminfo']['userid']}
					");
				}
				else
				{
					$oldvalue = "{$attachdm->info['albuminfo']['albumid']},$attachmentid";
					$newvalue = "{$attachdm->info['destination']['albumid']},$attachmentid";
					$this->registry->db->query_write("
						UPDATE " . TABLE_PREFIX . "usercss
						SET
							value = '" . $this->registry->db->escape_string($newvalue) . "'
						WHERE
							property = 'background_image'
								AND
							value = '" . $this->registry->db->escape_string($oldvalue) . "'
								AND
							userid = {$attachdm->albuminfo['userid']}
					");
				}
			}

			require_once(DIR . '/includes/class_usercss.php');
			$usercss = new vB_UserCSS($vbulletin, $this->info['albuminfo']['userid'], false);
			$usercss->update_css_cache();
		}
	}

	/**
	* pre_approve function - extend if the contenttype needs to do anything
	*
	* @param	array		list of moderated attachment ids to approve
	* @param	boolean	verify permission to approve
	*
	* @return	boolean
	*/
	public function pre_approve($list, $checkperms = true)
	{
		@ignore_user_abort(true);

		// init lists
		$this->lists = array(
			'albumlist'   => array(),
		);

		if ($checkperms)
		{
			// Verify that we have permission to view these attachmentids
			$attachmultiple = new vB_Attachment_Display_Multiple($this->registry);
			$attachments = $attachmultiple->fetch_results("a.attachmentid IN (" . implode(", ", $list) . ")");

			if (count($list) != count($attachments))
			{
				return false;
			}
		}

		$ids = $this->registry->db->query_read("
			SELECT
				a.attachmentid, a.userid, IF(a.contentid = 0, 1, 0) AS inprogress, a.caption,
				album.userid, album.title, album.albumid,
				user.username
			FROM " . TABLE_PREFIX . "attachment AS a
			LEFT JOIN " . TABLE_PREFIX . "album AS album ON (a.contentid = album.albumid)
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (a.userid = user.userid)
			WHERE
				a.attachmentid IN (" . implode(", ", $list) . ")
		");
		while ($id = $this->registry->db->fetch_array($ids))
		{
			if ($checkperms AND !can_moderate(0, 'canmoderatepictures'))
			{
				return false;
			}

			if ($id['albumid'])
			{
				$this->lists['albumlist']["{$id['albumid']}"]["{$id['attachmentid']}"] = 1;
			}
		}
		return true;
	}

	/**
	* post_unapprove function - extend if the contenttype needs to do anything
	*
	* @return	void
	*/
	public function post_unapprove(&$attachdm)
	{
		// Update something in the album table...
		if (!empty($this->lists['albumlist']))
		{
			$albums = $this->registry->db->query_read("
				SELECT *
				FROM " . TABLE_PREFIX . "album
				WHERE albumid IN (" . implode(",", array_keys($this->lists['albumlist'])) . ")
			");
			while ($album = $this->registry->db->fetch_array($albums))
			{
				$albumdata =& datamanager_init('Album', $this->registry, ERRTYPE_SILENT);
				$albumdata->set_existing($album);
				$albumdata->rebuild_counts();

				// Set a new coverattachmentid here, will need to probably query
				//if (!$album['coverattachmentid'])
				//{
				//	$albumdata->set('coverattachmentid', reset(array_keys($this->lists['albumlist']["$album[albumid]"])));
				//}

				$albumdata->save();
			}
		}
	}

	/**
	* post_approve function - extend if the contenttype needs to do anything
	*
	* @return	void
	*/
	public function post_approve(&$attachdm)
	{
		// Update something in the album table...
		if (!empty($this->lists['albumlist']))
		{
			$albums = $this->registry->db->query_read("
				SELECT *
				FROM " . TABLE_PREFIX . "album
				WHERE albumid IN (" . implode(",", array_keys($this->lists['albumlist'])) . ")
			");
			while ($album = $this->registry->db->fetch_array($albums))
			{
				$albumdata =& datamanager_init('Album', $this->registry, ERRTYPE_SILENT);
				$albumdata->set_existing($album);
				$albumdata->rebuild_counts();

				if (!$album['coverattachmentid'])
				{
					$albumdata->set('coverattachmentid', reset(array_keys($this->lists['albumlist']["$album[albumid]"])));
				}

				$albumdata->save();
			}
		}
	}

	/**
	* pre_delete function - extend if the contenttype needs to do anything
	*
	* @param	array		list of deleted attachment ids to delete
	* @param	boolean	verify permission to delete
	*
	* @return	boolean
	*/
	public function pre_delete($list, $checkperms = true)
	{
		@ignore_user_abort(true);

		// init lists
		$this->lists = array(
			'albumlist'   => array(),
		);

		if ($checkperms)
		{
			// Verify that we have permission to view these attachmentids
			$attachmultiple = new vB_Attachment_Display_Multiple($this->registry);
			$attachments = $attachmultiple->fetch_results("a.attachmentid IN (" . implode(", ", $list) . ")");

			if (count($list) != count($attachments))
			{
				return false;
			}
		}

		$ids = $this->registry->db->query_read("
			SELECT
				a.attachmentid, a.userid, IF(a.contentid = 0, 1, 0) AS inprogress, a.caption,
				album.userid, album.title, album.albumid,
				user.username
			FROM " . TABLE_PREFIX . "attachment AS a
			LEFT JOIN " . TABLE_PREFIX . "album AS album ON (a.contentid = album.albumid)
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (a.userid = user.userid)
			WHERE
				a.attachmentid IN (" . implode(", ", $list) . ")
		");
		while ($id = $this->registry->db->fetch_array($ids))
		{
			if ($checkperms AND !$id['inprogress'] AND $id['userid'] != $this->registry->userinfo['userid'] AND !can_moderate(0, 'candeletealbumpicture'))
			{
				return false;
			}

			if ($id['albumid'])
			{
				$this->lists['albumlist']["{$id['albumid']}"]["{$id['attachmentid']}"] = 1;

				if ($this->log)
				{
					if ($id['userid'] != $this->registry->userinfo['userid'])
					{
						// TODO : What does $picture want and fix modlog display in the admincp as it does not filter attachmentid properly on contenttype
						$picture = array();
						require_once(DIR . '/includes/functions_log_error.php');
						log_moderator_action($picture, 'picture_x_in_y_by_z_deleted', array(fetch_trimmed_title($id['caption'], 50), $id['title'], $id['username'])
					);
					}
				}
			}
		}
		return true;
	}

	/**
	* post_delete function - extend if the contenttype needs to do anything
	*
	* @return	void
	*/
	public function post_delete(&$attachdm)
	{
		$users = array();
		// Update something in the album table...
		if (!empty($this->lists['albumlist']))
		{
			$del_usercss = array();
			$albums = $this->registry->db->query_read("
				SELECT *
				FROM " . TABLE_PREFIX . "album
				WHERE albumid IN (" . implode(",", array_keys($this->lists['albumlist'])) . ")
			");
			while ($album = $this->registry->db->fetch_array($albums))
			{
				$users["$album[userid]"] = 1;
				$albumdata =& datamanager_init('Album', $this->registry, ERRTYPE_SILENT);
				$albumdata->set_existing($album);
				$albumdata->rebuild_counts();

				if (in_array($album['coverattachmentid'], array_keys($this->lists['albumlist']["$album[albumid]"])))
				{
					if ($album['visible'] - 1 > 0)
					{
						$new_cover = $this->registry->db->query_first("
							SELECT
								attachment.attachmentid
							FROM " . TABLE_PREFIX . "attachment AS attachment
							WHERE
								attachment.contentid = $album[albumid]
									AND
								attachment.state = 'visible'
									AND
								contenttypeid = " . intval($attachdm->fetch_field('contenttypeid')) . "
							ORDER BY
								attachment.dateline ASC
							LIMIT 1
						");
					}
					$albumdata->set('coverattachmentid', ($new_cover['attachmentid'] ? $new_cover['attachmentid']: 0));
				}

				$albumdata->save();
			}

			foreach ($this->lists['albumlist'] AS $albumid => $values)
			{
				foreach ($values AS $attachmentid => $foo)
				{
					$del_usercss[] = "$albumid,$attachmentid";
				}
			}

			$this->registry->db->query_write("
				DELETE FROM " . TABLE_PREFIX . "usercss
				WHERE
					property = 'background_image'
						AND
					value IN ('" . implode('\',\'', $del_usercss) . "')
			");
			$attachdm->set_info('have_updated_usercss', ($this->registry->db->affected_rows() > 0));

			require_once(DIR . '/includes/functions_picturecomment.php');
			foreach ($users AS $userid => $foo)
			{
				build_picture_comment_counters($userid);
				($hook = vBulletinHook::fetch_hook('picturedata_delete')) ? eval($hook) : false;
			}
		}
	}
}

class vB_Attachment_Upload_Displaybit_vBForum_Album extends vB_Attachment_Upload_Displaybit
{
	/**
	*	Parses the appropiate template for contenttype that is to be updated on the calling window during an upload
	*
	* @param	array	Attachment information
	* @param	array	Values array pertaining to contenttype
	* @param	boolean	Disable template comments
	* @return	string
	*/
	public function process_display_template($picture, $values = array(), $disablecomment = false)
	{
		global $show, $vbphrase;

		$languageid = $this->registry->userinfo['languageid'] ? $this->registry->userinfo['languageid'] : $this->registry->options['languageid'];
		$phraseinfo = $this->registry->db->query_first_slave("
			SELECT phrasegroup_album
			FROM " . TABLE_PREFIX . "language
			WHERE languageid = $languageid
		");

		$tmp = unserialize($phraseinfo['phrasegroup_album']);
		if (is_array($tmp))
		{
			$vbphrase = array_merge($vbphrase, $tmp);
		}

		$show['album_cover'] = ($attachment['state'] == 'visible' OR can_moderate(0, 'canmoderatepictures'));

		$album_options = '';
		$album_result = $this->registry->db->query_read("
			SELECT albumid, title
			FROM " . TABLE_PREFIX . "album
			WHERE userid = {$this->registry->userinfo['userid']}
		");

		if ($this->registry->db->num_rows($album_result) > 1)
		{
			while ($album = $this->registry->db->fetch_array($album_result))
			{
				$optiontitle = $album['title'];
				$optionvalue = $album['albumid'];
				$optionselected = ($album['albumid'] == $values['albumid']) ? 'selected="selected"' : '';
				$album_options .= render_option_template($optiontitle, $optionvalue, $optionselected, $optionclass);
			}

			$show['move_to_album'] = true;
		}

		if (($ext_pos = strrpos($picture['filename'], '.')) !== false)
		{
			$picture['caption'] = substr($picture['filename'], 0, $ext_pos);
		}
		else
		{
			$picture['caption'] = $picture['filename'];
		}
		$picture['caption'] = str_replace(array('_', '-'), ' ', $picture['caption']);
		$picture['caption_preview'] = fetch_censored_text(fetch_trimmed_title(
			$picture['caption'],
			$this->registry->options['album_captionpreviewlen']
		));

		$picture['thumburl'] = ($picture['hasthumbnail']);
		$picture['dimensions'] = ($picture['thumbnail_width'] ? "width=\"$picture[thumbnail_width]\" height=\"$picture[thumbnail_height]\"" : '');

		$templater = vB_Template::create('album_picture_editbit');
			$templater->register('picture', $picture);
			$templater->register('album_options', $album_options);
			$templater->register('albuminfo', array('albumid' => $values['albumid']));
		return $templater->render($disablecomment);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 29983 $
|| ####################################################################
\*======================================================================*/
?>