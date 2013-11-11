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
 * @subpackage Search
 * @author Kevin Sours, vBulletin Development Team
 * @version $Revision: 28678 $
 * @since $Date: 2008-12-03 16:54:12 +0000 (Wed, 03 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */

require_once (DIR . '/vb/search/result.php');

/**
*
*/
class vBForum_Search_Result_Thread extends vB_Search_Result
{
	//We'll pull some data from post
	private $replydata = array();

	//for now do not allow creation via the normal way -- group item only
	public static function create($id)
	{
		require_once (DIR . '/vb/legacy/thread.php');
		if ($thread = vB_Legacy_Thread::create_from_id($id))
		{
			$item = new vBForum_Search_Result_Thread();
			$item->thread = $thread;
			$item->set_data($id);
			return($item);
		}
		//if we get here,  the id must be invalid.
		require_once (DIR . '/vb/search/result/null.php');
		return new vB_Search_Result_Null();
	}

	public static function create_from_thread($thread)
	{
		if ($thread)
		{
			$item = new vBForum_Search_Result_Thread();
			// if we just have an id, we need to create the
			//object
			$item->thread = $thread;
			return $item;
		}
		else
		{
			require_once (DIR . '/vb/search/result/null.php');
			return new vB_Search_Result_Null();
		}
	}

	//set reply data
	private function set_replydata($threadid, $current_user)
	{
		global $vbulletin;
		$this->replydata = $vbulletin->db->query_first("SELECT
			(SELECT MAX(tr.readtime) from " . TABLE_PREFIX . "threadread AS tr where
			tr.threadid = $threadid AND tr.userid = " . $current_user->get_field('userid'). ") AS readtime,
			MAX(p.dateline)FROM " . TABLE_PREFIX . "post AS p
			WHERE p.threadid = $threadid");
	}

	protected function __construct() {}

	public function get_contenttype()
	{
		return vB_Search_Core::get_instance()->get_contenttypeid('vBForum', 'Thread');
   }

	//we will use the legacy can_search and can_view, for now.
	public function can_search($user)
	{
		return $this->thread->can_search($user);
	}

	public function can_view($user)
	{
		//The user can search if they have one of the following:
		//
		return $this->thread->can_view($user);
	}

	public function render($current_user, $criteria, $template_name = '')
	{
		require_once(DIR . '/includes/functions_forumdisplay.php');
		require_once(DIR . '/includes/functions_user.php');
		global $vbulletin;
		global $show;

		if (!strlen($template_name)) {
			$template_name = 'search_threadbit';
		}


		$show['forumlink'] = true;

		// threadbit_deleted conditionals
		$show['threadtitle'] = true;
		$show['viewthread'] = true;
		$show['managethread'] = true;

		//thread isn't a great name for this, but it stays consistant with
		//previous use and what will be expected in the hook.
		$thread = $this->thread->get_record();
		$this->set_replydata($thread['threadid'], $current_user);

		// get info from thread
		$thread['postid'] = $thread['threadid'];
		$thread['threadtitle'] = $thread['title'];
		$thread['threadiconid'] = $thread['iconid'];
		$thread['postdateline'] = $thread['lastpost'];

		$thread['issubscribed'] = $this->thread->is_subscribed($current_user);
		$thread['threadread'] = $this->thread->get_lastread($current_user);

		/*
			This used to be precalculated by forum, but it doesn't look expensive enough to want to
			bother with that.  If that turns out to be wrong we'll need to do some kind of
			caching.
		*/
		$forum = $this->thread->get_forum();
		if (!$current_user->hasForumPermission($forum->get_field('forumid'), 'canviewthreads'))
		{
			unset($thread['preview']);
		}

		//set the correct status
		if ($this->replydata['mylastpost'] > 0)
		{
			$post_statusicon[] = 'dot';
		}

		if (!$thread['open'])
		{
			$post_statusicon[] = 'lock';
		}

		if ($this->replydata['lastread'] < $thread['lastpost'])
		{
			$post_statusicon[] = 'new';
		}

		if (! count($post_statusicon))
		{
				$post_statusicon[] = 'old';
		}
		$post_statusicon = implode('_', $post_statusicon);


		$show['deletereason'] = false;

		if ($thread['visible'] == 2)
		{
			$log = $this->thread->get_deletion_log_array();
			$thread['del_username'] = $log['username'];
			$thread['del_userid'] = $log['userid'];
			$thread['del_reason'] = $log['reason'];

			$thread['deletedcount']++;
			$show['deletereason'] = !empty($thread['del_reason']);
		}
		else if ($thread['visible'] == 0)
		{
			$thread['hiddencount']++;
		}

		$thread['highlight'] = $criteria->get_highlights();

		$show['moderated'] = ($thread['hiddencount'] > 0 AND
			$current_user->canModerateForum($thread['forumid'], 'canmoderateposts'));

		$show['deletedthread'] = ($thread['deletedcount'] > 0 AND
			($current_user->canModerateForum($thread['forumid']) OR
			$current_user->hasForumPermission($thread['forumid'], 'canseedelnotice')));

		$show['disabled'] = !$this->can_inline_mod($current_user);

		$lastread = $forum->get_last_read_by_current_user($current_user);
		$thread = process_thread_array($thread, $lastread);
		($hook = vBulletinHook::fetch_hook('search_results_threadbit')) ? eval($hook) : false;

		$pageinfo = $pageinfo_lastpost = $pageinfo_firstpost = $pageinfo_lastpage = array();
		if ($show['pagenavmore'])
		{
			$pageinfo_lastpage['page'] = $thread['totalpages'];
		}
		$pageinfo_lastpost['p'] = $thread['lastpostid'];
		$pageinfo_newpost['goto'] = 'newpost';

		$pageinfo_thread = array();
		if (!empty($thread['highlight']))
		{
			$pageinfo_thread['highlight'] = urlencode(implode(' ', $thread['highlight']));
			$pageinfo_newpost['highlight'] = urlencode(implode(' ', $thread['highlight']));
			$pageinfo_lastpost['highlight'] = urlencode(implode(' ', $thread['highlight']));
			$pageinfo_firstpost['highlight'] = urlencode(implode(' ', $thread['highlight']));
		}
		
		if ($vbulletin->options['avatarenabled'])
		{
			$thread['lastpost_avatar'] = fetch_avatar_url($thread['lastposterid']);
			$thread['post_avatar'] = fetch_avatar_url($thread['postuserid']);
		}

		$template = vB_Template::create($template_name);
		$template->register('post_statusicon', $post_statusicon);
		$template->register('pageinfo_firstpost', $pageinfo_firstpost);
		$template->register('pageinfo_lastpost', $pageinfo_lastpost);
		$template->register('pageinfo_lastpage', $pageinfo_lastpage);
		$template->register('pageinfo_newpost', $pageinfo_newpost);
		$template->register('pageinfo', $pageinfo_thread);
		$template->register('dateformat', $vbulletin->options['dateformat']);
		$template->register('timeformat', $vbulletin->options['default_timeformat']);
		$template->register('postdateline', $thread['lastpost']);
		$userinfo = array('userid' => $thread['postuserid'], 'username' => $thread['postusername']);
		$template->register('avatar', $thread['post_avatar']);
		$template->register('userinfo', $userinfo);
		$template->register('show', $show);
		$template->register('thread', $thread);




		return $template->render();
	}

	public function get_thread()
	{
		return $this->thread;
	}

	public function set_thread($thread)
	{
		$this->thread = $thread;
	}

	/**
	* Does the user have any inline mod privs for this results item?
	*
	* Might be a candidate to move to the thread object.  Kind of specific
	* to the search right now... depends on which options are in the options
	* list.  The privs don't quite break down the same way on the display
	* end as they are checked on the action end (in inlinemod.php) which is
	* a problem, but I'm not really inclined to try to untangle the checking
	* in inlinemod.
	*/
	private function can_inline_mod($user)
	{
		$forumid = $this->thread->get_field('forumid');
		return (
			$user->canModerateForum($forumid, 'canmanagethreads') OR
			$user->canModerateForum($forumid, 'candeleteposts') OR
			$user->canModerateForum($forumid, 'canremoveposts') OR
			$user->canModerateForum($forumid, 'canmoderateposts') OR
			$user->canModerateForum($forumid, 'canopenclose')
		);
	}

	/*** Returns the primary id. Allows us to cache a result item.
	 *
	 * @result	integer
	 ***/
	public function get_id()
	{
		if (isset($this->thread) AND isset($this->thread['threadid']) )
		{
			return $this->thread['vmid'];
		}
		return false;
	}
	
	private $thread;
}


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/
