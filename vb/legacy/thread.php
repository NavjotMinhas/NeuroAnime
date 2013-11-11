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

require_once (DIR . "/vb/legacy/dataobject.php");
require_once (DIR . "/vb/legacy/forum.php");
require_once (DIR . "/vb/legacy/currentuser.php");

/**
 * Enter description here...
 *
 */
class vB_Legacy_Thread extends vB_Legacy_Dataobject
{

	/**
	 * Enter description here...
	 *
	 * @return unknown
	 */
	public static function get_field_names()
	{
		return array(
			'threadid', 'title', 'lastpost', 'forumid', 'pollid', 'open', 'replycount',
			'postusername', 'postuserid', 'lastposter', 'dateline', 'views', 'iconid', 'notes',
			'visible', 'sticky', 'votenum', 'votetotal', 'attach', 'firstpostid', 'similar',
			'hiddencount', 'deletedcount', 'lastpostid', 'prefixid', 'taglist', 'lastposterid', 'keywords'
		);
	}

	/**
	 * Create object from and existing record
	 *
	 * @param int $foruminfo
	 * @return vB_Legacy_Thread
	 */
	public static function create_from_record($threadinfo)
	{
		$thread = new vB_Legacy_Thread();
		$current_user = new vB_Legacy_CurrentUser();

		if (array_key_exists('subscribethreadid', $threadinfo))
		{
			$thread->subscribed[$current_user->getField('userid')] = (bool) $threadinfo['subscribethreadid'];
			unset($threadinfo['subscribethreadid']);
		}

		if (array_key_exists('readtime', $threadinfo))
		{
			$thread->lastread[$current_user->getField('userid')] = $threadinfo['readtime'];
			unset($threadinfo['readtime']);
		}

		$thread->set_record($threadinfo);
		return $thread;
	}

	/**
	 * Load object from an id
	 *
	 * @param int $id
	 * @return vB_Legacy_Thread
	 */
	public static function create_from_id($id)
	{
		$list = array_values(self::create_array(array($id)));
		if (!count($list))
		{
			return null;
		}
		else
		{
			return array_shift($list);
		}
	}

	public static function create_array($ids)
	{
		global $vbulletin;

		$current_user = new vB_Legacy_CurrentUser();

		$select = array();
		$joins = array();
		$where = array();

		$select[] = "thread.*";
		$where[] = "thread.threadid IN (" . implode(',', array_map('intval', $ids)) . ")";

		// always add the thread preview field to the results, unless it is disabled.
		if($vbulletin->options['threadpreview'] > 0)
		{
			$select[] = "post.pagetext AS preview";
			$joins['threadpreview'] = "LEFT JOIN " . TABLE_PREFIX . "post AS post ON(post.postid = thread.firstpostid)";
		}
		else 
		{
			//being a chicken here and making sure we don't remove the preview item 
			//from the array in case it matters
			$select[] = "'' as preview";
		}

		if (!$current_user->isGuest())
		{
			//join in thread subscriptions if enabled
			if ($vbulletin->options['threadsubscribed'])
			{
				$select[] = "subscribethread.subscribethreadid";
				$joins['subscribethread'] = "LEFT JOIN " . TABLE_PREFIX . "subscribethread AS subscribethread ON
					subscribethread.threadid = thread.threadid AND
					subscribethread.userid =  " . intval($current_user->getField('userid'));
			}

			if ($vbulletin->options['threadmarking'])
			{
				$select[] = "threadread.readtime";
				$joins['threadread'] = "LEFT JOIN " . TABLE_PREFIX . "threadread AS threadread ON
					threadread.threadid = thread.threadid AND
					threadread.userid =  " . intval($current_user->getField('userid'));
			}
		}

		$set = $vbulletin->db->query($q = "
			SELECT " . implode(",", $select) . "
			FROM " . TABLE_PREFIX . "thread AS thread
				" . implode("\n", $joins) . "
			WHERE " . implode (' AND ', $where) . "
		");

		$threads = array();
		while ($threadinfo = $vbulletin->db->fetch_array($set))
		{
			$threads[$threadinfo['threadid']] = self::create_from_record($threadinfo);
		}

		return $threads;
	}



	/**
	 * constructor -- protectd to force use of factory methods.
	 */
	protected function __construct() {}

	//*********************************************************************************
	// Derived getters

	/**
	 * Get the url for the thread page
	 *
	 * @return string
	 */
	public function get_url()
	{
		return fetch_seo_url('thread', $this->record);
	}

	/**
	 *	Does the thread display an icon?
	 */
	public function has_icon()
	{
		global $vbulletin;

		if (!$this->get_forum()->allow_icons())
		{
			return false;
		}

		return ($this->get_field('iconid') OR $this->get_field('pollid') OR $vbulletin->options['showdeficon']);
	}

	//*********************************************************************************
	// Related data

	/**
	 * Returns the forum containing the thread
	 *
	 * @return vB_Legacy_Forum
	 */
	public function get_forum()
	{
		if (is_null($this->forum))
		{
			$this->forum = vB_Legacy_Forum::create_from_id($this->record ['forumid']);
		}
		return $this->forum;
	}

	/**
	 * Get the last read time for the thread for the user.
	 *
	 * @param vB_Legacy_User
	 * @return int|NULL The date the thread was last read, null if not set
	 */
	public function get_lastread($user)
	{
		global $vbulletin;

		//only do the lookup if last read by thread is enabled
		//and we aren't checking the guest user
		if (!$vbulletin->options['threadmarking'] OR $user->isGuest())
		{
			return null;
		}

		$userid = $user->getField('userid');
		if (!array_key_exists($userid, $this->lastread))
		{
			$lastread = $vbulletin->db->query_first("
				SELECT readtime
				FROM " . TABLE_PREFIX . "threadread
				WHERE threadid = " . intval($this->get_field('threadid')) . " AND
					userid = " . intval($user->get_field('userid'))
			);

			$this->lastread[$userid] = ($lastread ? $lastread['readtime'] : null);
		}
		return $this->lastread[$userid];
	}

	public function get_deletion_log_array()
	{
		global $vbulletin;

		$blank = array('userid' => null, 'username' => null, 'reason' => null);
		if ($this->get_field('visible') == 1)
		{
			return $blank;
		}

		$log = $vbulletin->db->query_first("
			SELECT deletionlog.userid, deletionlog.username, deletionlog.reason
			FROM " . TABLE_PREFIX . "deletionlog as deletionlog
			WHERE deletionlog.primaryid = " . intval($this->get_field('threadid')) . " AND
				deletionlog.type = 'thread'
		");

		if (!$log)
		{
			return $blank;
		}
		else
		{
			return $log;
		}
	}

	/**
	 * Determine if the user is subscribed to this thread.
	 *
	 * @param vB_Legacy_User
	 * @return bool
	 */
	public function is_subscribed($user)
	{
		global $vbulletin;

		//only do the lookup if thread subscriptions are enabled.
		//and we aren't checking the guest user
		if (!$vbulletin->options['threadsubscribed'] OR $user->isGuest())
		{
			return false;
		}

		if (!isset($this->subscribed[$user->getField('userid')]))
		{
			$subscribed = $vbulletin->db->query_first("
				SELECT subscribethreadid
				FROM " . TABLE_PREFIX . "subscribethread
				WHERE canview = 1 AND threadid = " . intval($this->get_field('threadid')) . " AND
					userid = " . intval($user->get_field('userid'))
			);

			$this->subscribed[$user->getField('userid')] = (bool) $subscribed;
		}

		return $this->subscribed[$user->getField('userid')];
	}

	//*********************************************************************************
	//	High level permissions
	public function can_view($user)
	{
		global $vbulletin;

		if (in_array($this->get_field('forumid'), $user->getHiddenForums()))
		{
			return false;
		}

		// permission check to see if user can view other's threads in this forum,
		// and if not to make sure this thread was started by current user
		if (!$user->hasForumPermission($this->get_field('forumid'), 'canviewothers') AND
			($this->get_field('postuserid') != $user->get_field('userid') OR $user->get_field('userid') == 0))
		{
			return false;
		}

		if (!$user->canModerateForum($this->get_field('forumid')))
		{
			//this is cached.  Should be fast.
			require_once (DIR . "/includes/functions_bigthree.php");
			$conventry = fetch_coventry();

			if (in_array($this->get_field('postuserid'), $conventry))
			{
				return false;
			}

			// thread is deleted and we don't have permission to see it
			if ($this->get_field('visible') == 2)
			{
				return false;
			}
		}

		// thread is deleted by moderator and we don't have permission to see it
		if (!$this->get_field('visible') AND
			!$user->canModerateForum($this->get_field('forumid'), 'canmoderateposts')
		)
		{
			return false;
		}

		return true;
	}

	public function can_search($user)
	{
		if (in_array($this->get_field('forumid'), $user->getUnsearchableForums()))
		{
			return false;
		}

		return $this->can_view($user);
	}


	//*********************************************************************************
	//	Data Operation Functions

	/**
	 * Mark the thread deleted, but leave records in place
	 *
	 * @param vB_Legacy_User $user
	 * @param String $reason reason for deleting
	 * @param boolean $keepattachments
	 */
	public function soft_delete($user, $reason, $keepattachments)
	{
		$this->delete_internal(false, $user, $reason, $keepattachments);
	}

	/**
	 * Delete the thread from the database entirely
	 *
	 * @param vB_Legacy_User $user
	 * @param String $reason (May not be used but is passed internally, should investigate)
	 * @param boolean $keepattachments
	 */
	public function hard_delete($user, $reason, $keepattachments)
	{
		$this->delete_internal(true, $user, $reason, $keepattachments);
	}

	/**
	 * Enter description here...
	 *
	 * @param boolean $is_hard_delete
	 * @param vB_Legacy_User $user
	 * @param String $reason
	 * @param boolean $keepattachments
	 */
	protected function delete_internal($is_hard_delete, $user, $reason, $keepattachments)
	{
		global $vbulletin;
		$threadman = & datamanager_init('Thread', $vbulletin, ERRTYPE_STANDARD, 'threadpost');
		$threadman->set_existing($this->record);

		$threadman->delete($this->get_forum()->get_field['replycount'], $is_hard_delete,
			array('userid' => $user->get_field('userid'), 'username' => $user->get_field('username'),
				'reason' => $reason, 'keepattachments' => $keepattachments));
		unset($threadman);

		$forum = $this->get_forum();
		
		if ($forum->get_field('lastthreadid') != $this->get_field('threadid'))
		{
			$forum->decrement_threadcount();
		}
		else
		{
			// this thread is the one being displayed as the thread with the last post...
			// so get a new thread to display.
			build_forum_counters($this->get_field('forumid'));
		}
	}

	/**
	 * @var array
	 */
	private $forum = null;
	private $subscribed = array();
	private $lastread = array();
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/
