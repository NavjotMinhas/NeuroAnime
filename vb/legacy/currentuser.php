<?php if (!defined('VB_ENTRY')) die('Access denied.');
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
 * @package vBulletin
 * @subpackage Legacy
 * @author Kevin Sours, vBulletin Development Team
 * @version $Revision: 28678 $
 * @since $Date: 2008-12-03 16:54:12 +0000 (Wed, 03 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */

require_once (DIR . "/vb/legacy/user.php");

/**
 * Representation of the currently logged in user.
 *
 * Ideally we should collapse this into vB_Legacy_User once we figure out
 * how to do correctly load vB_Legacy_User to ensure that all information is
 * available for all user objects (or to correctly handle error
 * conditions for partly initialized user objects that are used incorrectly)
 */
class vB_Legacy_CurrentUser extends vB_Legacy_User
{

	/**
	 * Contructor
	 */
	public function __construct()
	{
		$this->record = $GLOBALS ['vbulletin']->userinfo;
		parent::__construct();
	}

	/**
	 * Get a user specific has value  
	 *
	 * Based on creation time, userid, and user specific salt value
	 *
	 * @param int $time timestamp value to use for the hash
	 * @return string hash key value
	 */
	public function getPostHash($time)
	{
		return md5($time . $this->get_field('userid') . $this->record['salt']);
	}

	/**
	 * Return any unsaved attachements this user may have for an unsaved post
	 *
	 * @param string $posthash hash value to identify the post
	 * @return array array of attachment records
	 */
	public function getUnsavedAttachments($posthash)
	{
		if (is_null($this->attachments))
		{
			$currentattaches = $this->registry->db->query_read_slave("
				SELECT dateline, filename, filesize, attachmentid
				FROM " . TABLE_PREFIX . "attachment
				WHERE posthash = '" . $this->registry->db->escape_string($posthash) . "'
					AND userid = " . intval($this->record ['userid']) . "
				ORDER BY attachmentid
			");

			$attachments = array();
			while ($attach = $this->registry->db->fetch_array($currentattaches))
			{
				$attachments [] = $attach;
			}

			$this->attachments = $attach;
		}
		return $this->attachments;
	}

	public function getSearchPrefs()
	{
		//guests don't have search prefs or even a field.
		if ($this->isGuest())
		{
			return array();
		}

		$stored_prefs = $this->get_field('searchprefs');
		if ($stored_prefs)
		{
			$stored_prefs = unserialize($stored_prefs);
		}
		else 
		{
			$stored_prefs = array();
		}

		//a hack that indicates that the prefs is a legacy value. in which case we'll 
		//apply the values to common. this should work, but largely by magic 
		//
		//Common fields are a subset of the legacy post fields.
		//Unset prefs will default to common search.
		//
		//If any fields get changed we need to handle it here.
		if (isset($stored_prefs['titleonly']))
		{
			$stored_prefs = array(vB_Search_Core::TYPE_COMMON => $stored_prefs);
		}

		return $stored_prefs;
	}

	//*********************************************************************************
	// Basic Permission Functions

	/**
	 * Does this use have the requested system permissions
	 *
	 * @param string $group Permission group the permission is in
	 * @param string $permission Name of permission
	 * @return boolean
	 */
	public function hasPermission($group, $permission)
	{
		return (bool) ($this->record['permissions'][$group] & 
			$this->registry->{'bf_ugp_' . $group}[$permission]);
	}

	/**
	 * Does the user have the requested permission on this forum.
	 *
	 * @param int $forumid
	 * @param string $permission Name of permission
	 * @return boolean
	 */
	public function hasForumPermission($forumid, $permission)
	{
		//should be cached and therefore not too expensive to look up on every
		//permissions call.
		$perms = fetch_permissions($forumid);
		return (bool) ($perms & $this->registry->bf_ugp_forumpermissions[$permission]);
	}

	public function hasCalendarPermission($calendarid, $permission)
	{
		if (is_null($this->registry->userinfo['calendarpermissions']))
		{
			cache_calendar_permissions($this->registry->userinfo);
		}

		return $this->registry->userinfo['calendarpermissions'][$calendarid] & 
			$this->registry->bf_ugp_calendarpermissions[$permission];
	}

	/**
	 * Does the user have moderation permissions on a forum
	 *
	 * @param int $forumid Need to look up forumid = 0
	 * @param unknown_type $do Permission to check (need to look up default value)
	 * @return boolean
	 */
	public function canModerateForum($forumid = 0, $do = '')
	{
		return (bool) can_moderate($forumid, $do, $this->get_field('userid'));
	}

	public function isModerator()
	{
		return (bool) can_moderate();
	}

	public function isSuperModerator()
	{
		return $this->hasPermission('adminpermissions', 'ismoderator');
	}

	/**
	* Get forums the user is unable to view.
	*
	*	Need to verify that this makes sense in general code stolen from search
	* logic and search specific param removed.
	*
	*	This value is calculated once and the list is returned on subsequent calls
	*
	*	@return array(int) list of hidden forum ids 
	*/
	public function getHiddenForums()
	{
		if (is_null($this->hidden_forums))
		{
			$this->hidden_forums = array();
			foreach ($this->registry->userinfo['forumpermissions'] AS $forumid => $fperms)
			{
				$forum = fetch_foruminfo($forumid);
				if (
					!$this->hasForumPermission($forumid, 'canview') OR 
					!verify_forum_password($forumid, $forum['password'], false)  
				)
				{
					$this->hidden_forums[] = $forumid;
				}
			}
		}
		return $this->hidden_forums;
	}

	/**
	* Get forums the user is unable to search.
	*
	*	This value is calculated once and the list is returned on subsequent calls
	*
	*	@return array(int) list of unsearchable forum ids 
	*/
	public function getUnsearchableForums()
	{
		if (is_null($this->unsearchable_forums))
		{
			$this->unsearchable_forums = $this->getHiddenForums();
			foreach ($this->registry->userinfo['forumpermissions'] AS $forumid => $fperms)
			{
				if (!in_array($forumid, $this->unsearchable_forums))
				{
					if (
						!$this->hasForumPermission($forumid, 'cansearch')
						/*
						This checks to see if the forum is currently 
						OR
						!($this->registry->forumcache["$forumid"]['options'] & 
							$this->registry->bf_misc_forumoptions['indexposts'])
						*/
					)
					{
						$this->unsearchable_forums[] = $forumid;
					}
				}
			}
		}
		return $this->unsearchable_forums;
	}

	public function getTimezoneOffset($adjust_for_dst)
	{
		return $adjust_for_dst ? $this->get_field('tzoffset') : $this->get_field('timezoneoffset');
	}

	//*********************************************************************************
	//	Extended Permission Functions
	/*
	 *	todo: these should move to the specific data objects rather than live here.
	 *
	 * These map to higher level actions in the system and take into account
	 * not only the user permissions but also the data values and configured
	 * options.  The goal is to centralize and regularize permissions checking
	 * so that it is more obvious what needs to be checked and so that permissions
	 * are calculated exactly the same way across the system.
	 */

	/**
	 * Can the user post an attachment in the given forum.
	 *
	 * @param int $forumid
	 * @return boolean
	 */
	public function canPostAttachment($forumid)
	{
		return $this->hasForumPermission($forumid, 'canpostattachment') AND 
			!$this->isGuest() AND !$this->get_attachment_extensions();
	}

	/*
	 * holder variables for lazy loading
	 */
	protected $attachments;
	protected $hidden_forums = null;
	protected $unsearchable_forums = null;
}
/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/
