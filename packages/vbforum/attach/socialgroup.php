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
require_once(DIR . '/includes/functions_socialgroup.php');

/**
* Class for displaying a vBulletin Group Attachment
*
* @package 		vBulletin
* @version		$Revision: 29983 $
* @date 		$Date: 2009-03-20 16:22:30 -0700 (Fri, 20 Mar 2009) $
*
*/
class vB_Attachment_Display_Single_vBForum_SocialGroup extends vB_Attachment_Display_Single
{
	/**
	* Verify permissions of a single attachment
	*
	* @return	bool
	*/
	public function verify_attachment()
	{
		if (
			!($this->registry->options['socnet'] & $this->registry->bf_misc_socnet['enable_groups'])
				OR
			!($this->registry->userinfo['permissions']['socialgrouppermissions'] & $this->registry->bf_ugp_socialgrouppermissions['canviewgroups'])
				OR
			!($this->registry->options['socnet_groups_pictures_enabled'])
		)
		{
			return false;
		}

		$hook_query_fields = $hook_query_joins = $hook_query_where = '';
		($hook = vBulletinHook::fetch_hook('attachment_start')) ? eval($hook) : false;

		$selectsql = array(
			"'public' AS albumstate",
			"sg.options AS groupoptions",
			"sgm.type AS ownermembertype",
		);

		$joinsql = array(
			"LEFT JOIN " . TABLE_PREFIX . "socialgroupmember AS sgm ON (sgm.userid = a.userid AND sgm.groupid = a.contentid AND sgm.type = 'member')",
			"LEFT JOIN " . TABLE_PREFIX . "socialgroup AS sg ON (sg.groupid = a.contentid)",
		);
		if (!$vbulletin->GPC['thumb'] AND !can_moderate(0, 'caneditgrouppicture'))
		{
			$selectsql[] = "bm.type AS browsermembertype";
			$joinsql[] = "LEFT JOIN " . TABLE_PREFIX . "socialgroupmember AS bm ON
				(bm.userid = " . $this->registry->userinfo['userid'] . " AND bm.groupid = a.contentid)";
		}

		if (!$this->verify_attachment_specific('vBForum_SocialGroup', $selectsql, $joinsql))
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

		if ($this->attachmentinfo['contentid'] == 0)
		{
			// there may be a condition where certain moderators could benefit by seeing these, I just don't know of any conditions at present
			if ($this->registry->userinfo['userid'] != $this->attachmentinfo['userid'])
			{
				return false;
			}
		}
		else
		{
			if (
				(
					isset($this->attachmentinfo['browsermembertype'])
						AND
					empty($this->attachmentinfo['browsermembertype'])
				)
					OR
				$this->attachmentinfo['ownermembertype'] != 'member'
			)
			{
				return false;
			}

			if (
				$this->attachmentinfo['state'] == 'moderation'
					AND
				$this->attachmentinfo['userid'] != $this->registry->userinfo['userid']
					AND
				!can_moderate(0, 'canmoderategrouppicture')
			)
			{	// I am not aware of a need to ever return clear.gif for a picture viewed in the group setting.
				return false;
			}

			if (!($this->attachmentinfo['groupoptions'] & $this->registry->bf_misc_socialgroupoptions['enable_group_albums']))
			{
				return false;
			}
		}

		return true;
	}
}

/**
* Class for display of multiple vBulletin group attachments
*
* @package 		vBulletin
* @version		$Revision: 29983 $
* @date 		$Date: 2009-03-20 16:22:30 -0700 (Fri, 20 Mar 2009) $
*
*/
class vB_Attachment_Display_Multiple_vBForum_SocialGroup extends vB_Attachment_Display_Multiple
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

		if (
			!($this->registry->options['socnet'] & $this->registry->bf_misc_socnet['enable_groups'])
				OR
			!($this->registry->userinfo['permissions']['socialgrouppermissions'] & $this->registry->bf_ugp_socialgrouppermissions['canviewgroups'])
				OR
			!($this->registry->options['socnet_groups_pictures_enabled'])
		)
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
			"sg.name, sg.groupid, sg.type, IF(sg.creatoruserid = {$this->registry->userinfo['userid']}, 1, 0) AS is_owner",
			"user.username",
		);

		$joinsql = array(
			"LEFT JOIN " . TABLE_PREFIX . "socialgroup AS sg ON (sg.groupid = a.contentid)",
			"LEFT JOIN " . TABLE_PREFIX . "user AS user ON (a.userid = user.userid)",
		);

		return $this->fetch_sql_specific($attachmentids, $selectsql, $joinsql);
	}

	/**
	* Fetches the SQL to be queried as part of a UNION ALL of an attachment query, verifying read permissions
	*
	* @param	string	SQL WHERE criteria
	* @param	string	Contents of the SELECT portion of the main query
	*
	* @return	string
	*/
	protected function fetch_sql_ids($criteria, $selectfields)
	{
		$joinsql = array(
			"INNER JOIN " . TABLE_PREFIX . "socialgroupmember AS sgm ON (sgm.userid = a.userid AND sgm.groupid = a.contentid AND sgm.type = 'member')",
			"INNER JOIN " . TABLE_PREFIX . "socialgroup AS sg ON (sg.groupid = a.contentid)",
			"LEFT JOIN " . TABLE_PREFIX . "user AS user ON (a.userid = user.userid)",
		);

		$subwheresql = array(
			"sg.options & {$this->registry->bf_misc_socialgroupoptions['enable_group_albums']}",
		);

		if (!can_moderate(0, 'canmoderategrouppicture'))
		{
			$subwheresql[] = "(
				a.state <> 'moderation'
					OR
				a.userid = {$this->registry->userinfo['userid']}
			)";
		}

		return $this->fetch_sql_ids_specific($this->contenttypeid, $criteria, $selectfields, $subwheresql, $joinsql);
	}

	/**
	* Formats $groupinfo content for display. The SQL fields in $groupinfo are set in fetch_sql() above
	*
	* @param	array		Entry information
	*
	* @return	array
	*/
	public function process_attachment_template($groupinfo, $showthumbs = false)
	{
		global $show, $vbphrase;

		$show['thumbnail'] = ($groupinfo['hasthumbnail'] == 1 AND $showthumbs);
		$show['inprogress'] = $groupinfo['inprogress'];

		$show['canmoderate'] = can_moderate(0, 'canmoderategrouppicture');
		$show['candelete'] = false;
		if (
			$this->registry->options['socnet_groups_pictures_enabled']
				AND
			(
				$groupinfo['inprogress']
					OR
				fetch_socialgroup_modperm('canremovepicture', $groupinfo)
					OR
				$this->registry->userinfo['userid'] == $groupinfo['userid']
			)
				AND
			(
				$groupinfo['state'] == 'visible'
					OR
				can_moderate(0, 'canmoderategrouppicture')
			)
		)
		{
			$show['candelete'] = true;
		}

		return array(
			'template' => 'group',
			'group'    => $groupinfo,
		);
	}

	/**
	* Return group specific url to the owner an attachment
	*
	* @param	array		Content information
	*
	* @return	string
	*/
	protected function fetch_content_url_instance($contentinfo)
	{
		return fetch_seo_url('group', $contentinfo, array(), 'contentid');
	}
}

// #######################################################################
// ############################# STORAGE #################################
// #######################################################################

/**
* Class for storing a vBulletin group attachment
*
* @package 		vBulletin
* @version		$Revision: 29983 $
* @date 		$Date: 2009-03-20 16:22:30 -0700 (Fri, 20 Mar 2009) $
*
*/
class vB_Attachment_Store_vBForum_SocialGroup extends vB_Attachment_Store
{
	/**
	*	Groupinfo
	*
	* @var	array
	*/
	protected $groupinfo = array();

	/**
	*	Override the vboption for thumbnails
	*
	* @var	boolean
	*/
	public $forcethumbnail = true;

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
				sg.groupid
			FROM " . TABLE_PREFIX . "attachment AS a
			INNER JOIN " . TABLE_PREFIX . "socialgroup AS sg ON (sg.groupid = a.contentid)
			WHERE
				a.attachmentid = " . intval($attachmentid) . "
		");
	}

	/**
	* Verifies permissions to attach content to groups
	*
	* @param	array	Contenttype information - bypass reading environment settings
	*
	* @return	boolean
	*/
	public function verify_permissions($info = array())
	{
		global $show;

		if ($info['groupid'])
		{
			$this->values['groupid'] = $info['groupid'];
		}
		else
		{
			$this->values['groupid'] = intval($this->values['groupid']);
		}
		
		if (
			!($group = fetch_socialgroupinfo($this->values['groupid']))
				OR
			$group['membertype'] != 'member'
				OR
			!($group['options'] & $this->registry->bf_misc_socialgroupoptions['enable_group_albums'])
				OR
			!($this->registry->options['socnet'] & $this->registry->bf_misc_socnet['enable_groups'])
				OR
			!($this->registry->userinfo['permissions']['socialgrouppermissions'] & $this->registry->bf_ugp_socialgrouppermissions['canviewgroups'])
				OR
			!($this->registry->options['socnet_groups_pictures_enabled'])
		)
		{
			return false;
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
		// third argument forces thumbnails on for social groups
		return parent::process_upload($upload, $attachment, $imageonly);
	}

	/**
	* Set contentid here as an info instead of in verify_permissions so that the editor doesn't load all of the contents existing attachments
	*
	* @return	object
	*/
	protected function &fetch_attachdm()
	{
		$attachdata =& parent::fetch_attachdm();
		$attachdata->set_info('contentid', $this->values['groupid']);

		$should_moderate = (
			$this->registry->options['groups_pictures_moderation']
				OR
			!($this->registry->userinfo['permissions']['socialgrouppermissions'] & $this->registry->bf_ugp_socialgrouppermissions['groupfollowforummoderation'])
		);

		$state = ($should_moderate AND !can_moderate(0, 'canmoderatepictures')) ? 'moderation' : 'visible';
		$attachdata->set('state', $state);

		return $attachdata;
	}
}

/**
* Class for deleting a vBulletin group attachment
*
* @package 		vBulletin
* @version		$Revision: 29983 $
* @date 		$Date: 2009-03-20 16:22:30 -0700 (Fri, 20 Mar 2009) $
*
*/
class vB_Attachment_Dm_vBForum_SocialGroup extends vB_Attachment_Dm
{
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
			'grouplist'   => array(),
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
				sg.name, sg.groupid, IF(sg.creatoruserid = {$this->registry->userinfo['userid']}, 1, 0) AS is_owner,
				user.username
			FROM " . TABLE_PREFIX . "attachment AS a
			LEFT JOIN " . TABLE_PREFIX . "socialgroup AS sg ON (a.contentid = sg.groupid)
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (a.userid = user.userid)
			WHERE
				a.attachmentid IN (" . implode(", ", $list) . ")
		");
		while ($id = $this->registry->db->fetch_array($ids))
		{
			if ($checkperms AND !can_moderate(0, 'canmoderategrouppicture'))
			{
				return false;
			}

			if ($id['groupid'])
			{
				$this->lists['grouplist']["{$id['groupid']}"]["{$id['attachmentid']}"] = 1;
			}
		}
		return true;
	}

	/**
	* post_approve function - extend if the contenttype needs to do anything
	*
	* @return	void
	*/
	public function post_approve($attachdm)
	{
		// Update something in the socialgroup table...
		if (!empty($this->lists['grouplist']))
		{
			$groups = $this->registry->db->query_read("
				SELECT *
				FROM " . TABLE_PREFIX . "socialgroup
				WHERE groupid IN (" . implode(",", array_keys($this->lists['grouplist'])) . ")
			");
			while ($group = $this->registry->db->fetch_array($groups))
			{
				$groupdm =& datamanager_init('SocialGroup', $this->registry, ERRTYPE_SILENT);
				$groupdm->set_existing($group);
				$groupdm->rebuild_picturecount();
				$groupdm->save();
			}
		}
	}

	/**
	* post_unapprove function - extend if the contenttype needs to do anything
	*
	* @return	void
	*/
	public function post_unapprove($attachdm)
	{
		// does the same thing as approve
		$this->post_approve($attachdm);
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
			'grouplist'   => array(),
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
				sg.name, sg.groupid, IF(sg.creatoruserid = {$this->registry->userinfo['userid']}, 1, 0) AS is_owner,
				user.username
			FROM " . TABLE_PREFIX . "attachment AS a
			LEFT JOIN " . TABLE_PREFIX . "socialgroup AS sg ON (a.contentid = sg.groupid)
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON (a.userid = user.userid)
			WHERE
				a.attachmentid IN (" . implode(", ", $list) . ")
		");
		while ($id = $this->registry->db->fetch_array($ids))
		{
			if ($checkperms AND !$id['inprogress'] AND $id['userid'] != $this->registry->userinfo['userid'] AND !fetch_socialgroup_modperm('canremovepicture', $id))
			{
				return false;
			}

			if ($id['groupid'])
			{
				$this->lists['grouplist']["{$id['groupid']}"]["{$id['attachmentid']}"] = 1;

				if ($this->log)
				{
					if (!$id['is_owner'] AND $id['userid'] != $this->registry->userinfo['userid'])
					{
						// TODO : What does $picture want and fix modlog display in the admincp as it does not filter attachmentid properly on contenttype
						$picture = array();
						require_once(DIR . '/includes/functions_log_error.php');
						log_moderator_action($picture, 'social_group_picture_x_in_y_removed', array(fetch_trimmed_title($id['caption'], 50), $id['name']));
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
	public function post_delete($attachdm)
	{
		// Update something in the socialgroup table...
		if (!empty($this->lists['grouplist']))
		{
			$groups = $this->registry->db->query_read("
				SELECT *
				FROM " . TABLE_PREFIX . "socialgroup
				WHERE groupid IN (" . implode(",", array_keys($this->lists['grouplist'])) . ")
			");
			while ($group = $this->registry->db->fetch_array($groups))
			{
				$groupdm =& datamanager_init('SocialGroup', $this->registry, ERRTYPE_SILENT);
				$groupdm->set_existing($group);
				$groupdm->rebuild_picturecount();
				$groupdm->save();
			}
		}
	}
}

class vB_Attachment_Upload_Displaybit_vBForum_SocialGroup extends vB_Attachment_Upload_Displaybit
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

		$picture['dimensions'] = ($picture['thumbnail_width'] ? "width=\"$picture[thumbnail_width]\" height=\"$picture[thumbnail_height]\"" : '');

		$templater = vB_Template::create('socialgroups_picture_editbit');
			$templater->register('picture', $picture);
			$templater->register('group', array('groupid' => $values['groupid']));
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
