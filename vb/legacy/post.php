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
require_once (DIR . "/vb/legacy/thread.php");

/**
 * Enter description here...
 *
 */
class vB_Legacy_Post extends vB_Legacy_Dataobject
{

	/**
	 * Get the fields that we should load with the post object
	 *
	 * @todo we should auto-gen this from the db schema somehow.
	 * @return unknown
	 */
	public static function get_field_names()
	{
		return array('postid', 'threadid', 'username', 'userid', 'title', 'dateline',
		'pagetext', 'allowsmilie', 'showsignature', 'ipaddress', 'iconid', 'visible',
		'parentid', 'attach', 'infraction', 'reportthreadid');
	}

	/**
	 * Public factory method to create a post object
	 *
	 * @param array A post record to set to this object.
	 * @param array|vB_Legacy_Thread If the thread for this post has already been loaded
	 * @return vB_Legacy_Post
	 */
	public static function create_from_record($record, $thread = null)
	{
		$post = new vB_Legacy_Post();
		$post->set_record($record);

		if (is_array($thread))
		{
			$post->set_thread(vB_Legacy_Thread::create_from_record($thread));
		}
		else if ($thread instanceof vB_Legacy_Thread)
		{
			$post->set_thread($thread);
		}
		return $post;
	}

	/**
	 * Public factory method to create a post object
	 *
	 * @param int $id
	 * @param boolean $autoloadthread if true, load the thread data on instantiation
	 * @return vB_Legacy_Post
	 */
	public static function create_from_id($id, $autoloadthread = false)
	{
		global $vbulletin;

		if (!$autoloadthread)
		{
			$post = $vbulletin->db->query_first_slave("
				SELECT * FROM " . TABLE_PREFIX . "post WHERE postid = " . intval($id));
			if (!$post)
			{
				return false;
			}
			else
			{
				return self::create_from_record($post);
			}
		}
		else
		{
			$post_fields = vB_Legacy_Post::get_field_names();
			$thread_fields = vB_Legacy_Thread::get_field_names();

			$select = array();
			foreach ($post_fields as $field)
			{
				$select [] = 'p.' . $field;
			}

			foreach ($thread_fields as $field)
			{
				$select [] = 't.' . $field;
			}

			$row = $vbulletin->db->query_first_slave($q = "
				SELECT " . implode(', ', $select) . "
				FROM " . TABLE_PREFIX . "post as p join " . TABLE_PREFIX . "thread as t ON p.threadid = t.threadid
			 	WHERE p.postid = " . intval($id), DBARRAY_NUM);

			if (!$row)
			{
				return null;
			}

			$post_data = array_combine($post_fields, array_slice($row, 0, count($post_fields)));
			$thread_data = array_combine($thread_fields, array_slice($row, count($post_fields)));

			return vB_Legacy_Post::create_from_record($post_data, $thread_data);
		}
	}

	public static function create_array($ids, $thread_map = array())
	{
		global $vbulletin;
		$set = $vbulletin->db->query("
			SELECT p.*
			FROM " . TABLE_PREFIX . "post AS p
			WHERE p.postid IN (" . implode(',', $ids) . ")
		");

		//ensure that $items is in the same order as $ids
		$items = array_fill_keys($ids, false);
		while ($row = $vbulletin->db->fetch_array($set))
		{
			$thread = isset($thread_map[$row['threadid']]) ? $thread_map[$row['threadid']] : null;
			$items[$row['postid']] = vB_Legacy_Post::create_from_record($row, $thread);
		}

		$items = array_filter($items);
		return $items;
	}



	/**
	 * constructor -- protectd to force use of factory methods.
	 */
	protected function __construct()
	{
		$this->registry = $GLOBALS['vbulletin'];
	}

	//*********************************************************************************
	// derived getters

	/**
	 * Is this the first post in the thread
	 *
	 * @return boolean
	 */
	public function is_first()
	{
		if (is_null($this->var_is_first))
		{
			// find out if first post
			$getpost = $this->registry->db->query_first("
				SELECT postid
				FROM " . TABLE_PREFIX . "post
				WHERE threadid = " . intval($this->record['threadid']) . "
				ORDER BY dateline
				LIMIT 1
			");
			$this->var_is_first = ($getpost['postid'] == $this->record["postid"]);
		}
		return $this->var_is_first;
	}

	/**
	 * Get the user for this post
	 *
	 * @return vB_Legacy_User
	 */
	public function get_user()
	{
		if (is_null($this->user))
		{
			return vB_Legacy_User::createFromId($this->record['userid']);
		}

		return $this->user;
	}

	/**
	 * Get the poster's ip address as a string
	 *
	 * @return sting
	 */
	public function get_ipstring()
	{
		return $this->record['ipaddress'];
	}

	/**
	 * Get the poster's ip address as an integer(long)
	 *
	 * @return int
	 */
	public function get_iplong()
	{
		return ip2long($this->record ['ipaddress']);
	}

	/**
	 * Get the summary text for the post
	 *
	 * @param int $length maximum length of the summary text
	 * @return string
	 */
	public function get_summary($length)
	{
		$strip_quotes = true;
		$page_text = $this->get_pagetext_noquote();

		// Deal with the case that quote was the only content of the post
		if (trim($page_text) == '')
		{
			$page_text = $this->get_field('pagetext');
			$strip_quotes = false;
		}

		return htmlspecialchars_uni(fetch_censored_text(
			trim(fetch_trimmed_title(strip_bbcode($page_text, $strip_quotes, false, false), $length))));
	}

	/**
	 * Enter description here...
	 *
	 * @return unknown
	 */
	public function get_pagetext_noquote()
	{
		//figure out how to handle the 'cancelwords'
		$display['highlight'] = array();
		return preg_replace('#\[quote(=(&quot;|"|\'|)??.*\\2)?\](((?>[^\[]*?|(?R)|.))*)\[/quote\]#siUe',
			"process_quote_removal('\\3', \$display['highlight'])", $this->get_field('pagetext'));
	}


	/**
	 * Return title string for display
	 *
	 * If there is no title given, we'll construct one from the post text
	 */
	public function get_display_title()
	{
		$title = $this->get_field('title');
		if ($title == '')
		{
			$title = fetch_trimmed_title(strip_bbcode($this->get_field('pagetext'), true, false, true), 50);
		}
		else
		{
			$title = fetch_censored_text($title);
		}

		return $title;
	}

	/**
	 * Get the path of the post icon
	 *
	 * @return string
	 */
	public function get_icon_path()
	{
		if (!$this->get_thread()->get_forum()->allow_icons())
		{
			return '';
		}
		$path = & $this->registry->iconcache[$this->get_field('iconid')]['iconpath'];
		if (!$path and !empty($this->registry->options['showdeficon']))
		{
			$path = $this->registry->options['showdeficon'];
		}

		return $path;
	}

	/**
	 * Get the title for the post icon
	 *
	 * @return string
	 */
	public function get_icon_title()
	{
		if (!$this->get_thread()->get_forum()->allow_icons())
		{
			return '';
		}

		return $this->registry->iconcache[$this->get_field('iconid')]['title'];
	}

	/**
	 * Return the post date formatted according to the options
	 *
	 * @return string
	 */
	public function get_formated_date()
	{
		return vbdate($this->registry->options['dateformat'], $this->record['dateline']);
	}

	/**
	 * Return the time formatted according to the options
	 *
	 * @return string
	 */
	public function get_formated_time()
	{
		return vbdate($this->registry->options['timeformat'], $this->record['dateline']);
	}

	/**
	 * Has the window of time in which a post my be edited normally expired?
	 *
	 * @todo figure this out, its not working (don't use until it gets fixed)
	 * @return boolean
	 */
	public function has_edit_threadtitle_time_expired()
	{
		return ($this->record ['dateline'] + $this->registry->options ['editthreadtitlelimit'] * 60) <= TIMENOW;
	}



	public function get_post_template_array($current_user, $summary_length, $highlight="")
	{
		global $vbulletin, $vbphrase, $show;

		require_once (DIR . '/includes/functions_forumdisplay.php');
		/*
			Post is not a good name for this array, however its what it used to be called
			(when it wasn't such a bad name) and changing it makes it certain that a lot of
			hooks are going to break.
		*/
		$post = array();
		$thread = $this->get_thread();
		$forum = $thread->get_forum();

		if ($this->is_post_new($current_user))
		{
			$post['post_statusicon'] = 'new';
			$post['post_statustitle'] = $vbphrase['unread'];
		}
		else
		{
			$post['post_statusicon'] = 'old';
			$post['post_statustitle'] = $vbphrase['old'];
		}

		$post['postid'] = $this->get_field('postid');
		$post['userid'] = $this->get_field('userid');
		$post['postdateline'] = $this->get_field('dateline');
		$post['posttitle'] = $this->get_display_title();
		$post['pagetext'] = nl2br($this->get_summary($summary_length));
		$post['visible'] = $this->get_field('visible');
		$post['attach'] = $this->get_field('attach');

		$post['highlight'] = $highlight;
		$post['username'] = $this->get_field('userid') == 0 ?
			$this->get_field('username') : $this->get_user()->get_field('username');

		$post['threadid'] = $thread->get_field('threadid');
		$post['threadtitle'] = $thread->get_field('title');
		$post['threadiconid'] = $thread->get_field('iconid');
		$post['replycount'] = $thread->get_field('replycount');
		$post['views'] = $thread->get_field('views') > 0 ?
			$thread->get_field('views') : $thread->get_field('replycount') + 1;
		$post['firstpostid'] = $thread->get_field('firstpostid');
		$post['prefixid'] = $thread->get_field('prefixid');
		$post['taglist'] = $thread->get_field('taglist');
		$post['pollid'] = $thread->get_field('pollid');
		$post['sticky'] = $thread->get_field('sticky');
		$post['open'] = $thread->get_field('open');
		$post['lastpost'] = $thread->get_field('lastpost');
		$post['forumid'] = $thread->get_field('forumid');
		$post['thread_visible'] = $thread->get_field('visible');

		$post['forumtitle'] = $forum->get_field('title');

		$post['posticonid'] = $this->get_field('iconid');
		$post['allowicons'] = $forum->allow_icons();
		$post['posticonpath'] = $this->get_icon_path();
		$post['posticontitle'] = $this->get_icon_title();
		$post['posticon'] = $post ['allowicons'] and $post ['posticonpath'];

		$lastread = $forum->get_last_read_by_current_user($current_user);

		$show['deleted'] = false;
		if ($current_user->isModerator())
		{
			$log = $this->get_deletion_log_array();
			if ($log['userid'])
			{
				$post['del_phrase'] = $vbphrase['message_deleted_by_x'];
			}
			else
			{
				$log = $thread->get_deletion_log_array();
				if (!$log['userid'])
				{
					$post['del_phrase'] = $vbphrase['thread_deleted_by_x'];
					$log = false;
				}
			}

			if ($log)
			{
				$post['del_username'] = $log['username'];
				$post['del_userid'] = $log['userid'];
				$post['del_reason'] = $log['reason'];
				$show['deleted'] = true;
			}
		}

		$post['prefixid'] = $thread->get_field('prefixid');
		if ($post['prefixid'])
		{
			$post['prefix_plain_html'] = htmlspecialchars_uni($vbphrase["prefix_$post[prefixid]_title_plain"]);
			$post['prefix_rich'] = $vbphrase["prefix_$post[prefixid]_title_rich"];
		}
		else
		{
			$post['prefix_plain_html'] = '';
			$post['prefix_rich'] = '';
		}

		$show['disabled'] = !$this->can_inline_mod($current_user);

		$post = process_thread_array($post, $lastread, $post['allowicons']);
		return $post;
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
		$forumid = $this->get_thread()->get_field('forumid');
		return (
			$user->canModerateForum($forumid, 'canmanagethreads') OR
			$user->canModerateForum($forumid, 'candeleteposts') OR
			$user->canModerateForum($forumid, 'canremoveposts') OR
			$user->canModerateForum($forumid, 'canmoderateposts') OR
			$user->canModerateForum($forumid, 'canmoderateattachments')
		);
	}

	/**
	* Determine if a post is new in the context of a user
	*
	*	This feels like it make this public, however,
	* it involves a potentially uncached query and logic that only works in the
	* context of the currently logged in user.  This seems a bit dangerous to
	* expose more generally and at present there is no need.
	*
	*	requires that cache_ordered_forums be called (this function should only be
	* called once)
	*
	*	@post vB_Legacy_Post
	* @post vB_Current_User
	* @return bool
	*/
	private function is_post_new($user)
	{
		global $vbulletin;

		// do post folder icon
		if ($vbulletin->options['threadmarking'] AND !$user->isGuest())
		{
			//avoid calling get_lastread if possible
			$thread = $this->get_thread();
			return (($this->get_field('dateline') >
				$vbulletin->forumcache[$thread->get_field('forumid')]['forumread']) AND
				($this->get_field('dateline') > $thread->get_lastread($user))
			);
		}
		else
		{
			return ($this->get_field('dateline') > $vbulletin->userinfo['lastvisit']);
		}
	}

	//*********************************************************************************
	// Permissions / Security

	/**
	 * Can the user delete this post?
	 *
	 * @param vB_Legacy_CurrentUser $user
	 * @return boolean
	 */
	public function can_delete($user)
	{
		/*
			There was an inconsistancy in permissions between the display logic
			and the delete action logic.  The delete logic checks the posts permissions
			for all posts while the display checks only the thread permissions for
			first posts.  We're using the more restrictive permissions checked on actual
			action.
		*/

		$is_user_owner = ($user->get_field('userid') == $this->record ['postid']);
		$forumid = $this->get_thread()->get_field('forumid');

		//we can't delete posts, return false
		if (!$user->canModerateForum($forumid, 'candeleteposts') AND
			!($is_user_owner AND $user->hasForumPermission($forumid, 'candeletepost'))
		)
		{
			return false;
		}

		//this is the first post and we can't delete threads
		if ($this->is_first())
		{
			if (!$user->canModerateForum($forumid, 'canmanagethreads') AND
				!($is_user_owner AND $user->hasForumPermission($forumid, 'candeletethread'))
			)
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Can the user view this post
	 *
	 * @param vB_Legacy_CurrentUser $user
	 * @return boolean
	 */
	public function can_view($user)
	{
		$thread = $this->get_thread();
		if (!$thread)
		{
			return false;
		}

		if (!$thread->can_view($user))
		{
			return false;
		}

		if (!$user->canModerateForum($thread->get_field('forumid')))
		{
			//this is cached.  Should be fast.
			require_once (DIR . "/includes/functions_bigthree.php");
			$conventry = fetch_coventry();

			if (in_array($this->get_field('userid'), $conventry))
			{
				return false;
			}

			// post/thread is deleted and we don't have permission to see it
			if ($this->get_field('visible') == 2)
			{
				return false;
			}
		}

		// post/thread is deleted by moderator and we don't have permission to see it
		if (!$this->get_field('visible') AND
			!$user->canModerateForum($thread->get_field('forumid'), 'canmoderateposts')
		)
		{
			return false;
		}

		return true;
	}

	/**
	 * Can the user view the post as a search result
	 *
	 * @param vB_Legacy_CurrentUser $user
	 * @return boolean
	 */
	public function can_search($user)
	{
		if (($thread = $this->get_thread()) AND in_array($thread->get_field('forumid'), $user->getUnsearchableForums()))
		{
			return false;
		}

		return $this->can_view($user);
	}

	//*********************************************************************************
	// Related data/data objects

	/**
	 * Get the thread containing the post
	 *
	 * @return vB_Legacy_Thread
	 */
	public function get_thread()
	{
		if (is_null($this->thread))
		{
			$this->thread = vB_Legacy_Thread::create_from_id($this->get_field('threadid'));
		}

		return $this->thread;
	}

	public function get_deletion_log_array()
	{
		global $vbulletin;
		$blank = array('userid' => null, 'username' => 'null', 'reason' => null);

		if ($this->getField('visible') == 1)
		{
			return $blank;
		}

		$log = $vbulletin->db->query_first("
			SELECT deletionlog.userid, deletionlog.username, deletionlog.reason
			FROM " . TABLE_PREFIX . "deletionlog as deletionlog
			WHERE deletionlog.primaryid = " . intval($this->get_field('postid')) . " AND
				deletionlog.type = 'post'
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
	 * Get the attachments for this post
	 *
	 * @return array The attachment records
	 */
	public function get_attachments()
	{
		if (is_null($this->attachments))
		{
			$currentattaches = $this->registry->db->query_read_slave("
				SELECT dateline, filename, filesize, attachmentid
				FROM " . TABLE_PREFIX . "attachment
				WHERE postid = " . intval($this->record ['postid']) . "
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

	//*********************************************************************************
	// Data Operation functions

	/**
	 * Mark post as deleted, but keep record in database.
	 *
	 * @param vB_Legacy_User $user
	 * @param string $reason Explanation for the deletion
	 * @param boolean $keepattachments
	 */
	public function soft_delete($user, $reason, $keepattachments)
	{
		$this->delete_internal(false, $user, $reason, $keepattachments);
	}

	/**
	 * Remove the post record from the database.
	 *
	 * @param vB_Legacy_User $user
	 * @param string $reason (may not be used, should research)
	 * @param boolean $keepattachments
	 */
	public function hard_delete($user, $reason, $keepattachments)
	{
		$this->delete_internal(true, $user, $reason, $keepattachments);
	}

	/**
	 * Process the actual deletes
	 *
	 * @param boolean $is_hard_delete
	 * @param vB_Legacy_User $user
	 * @param string $reason
	 * @param boolean $keepattachments
	 */
	protected function delete_internal($is_hard_delete, $user, $reason, $keepattachments)
	{
		global $vbulletin;
		$thread = $this->get_field('thread');
		$forum = $thread->get_field('forum');

		$postman = & datamanager_init('Post', $vbulletin, ERRTYPE_SILENT, 'threadpost');
		$postman->set_existing($this->record);
		$postman->delete($forum->get_countposts(), $thread->get_field('threadid'), $is_hard_delete,
			array('userid' => $user->get_field('userid'), 'username' => $user->get_field('username'),
				'reason' => $reason, 'keepattachments' => $keepattachments));
		unset($postman);

		build_thread_counters($threadinfo ['threadid']);

		if ($forum->get_field('lastthreadid') != $thread->get_field('threadid'))
		{
			$forum->decrement_replycount();
		}
		else
		{
			// this thread is the one being displayed as the thread with the last post...
			// need to get the lastpost datestamp and lastposter name from the thread.
			build_forum_counters($thread->get_field('forumid'));
		}
	}

	//*********************************************************************************
	// Internal Setters for initializer functions

	/**
	 * The thread containing the post
	 *
	 * @param vB_Legacy_Thread $thread
	 */
	protected function set_thread($thread)
	{
		$this->thread = $thread;
	}

	//*********************************************************************************
	// Data

	/**
	 * @var vB_Registry
	 */
	protected $registry = null;

	//some lazy loading storage.
	protected $var_is_first = null;
	protected $attachments = null;
	protected $user = null;
	protected $thread = null;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/
