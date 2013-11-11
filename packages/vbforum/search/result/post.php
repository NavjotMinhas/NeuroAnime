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
require_once (DIR . '/packages/vbforum/search/result/thread.php');

/**
 * Enter description here...
 *
 * @package vBulletin
 * @subpackage Search
 */
class vBForum_Search_Result_Post extends vB_Search_Result
{
	public static function create($id)
	{
		return vBForum_Search_Result_Post::create_from_object(vB_Legacy_Post::create_from_id($id, true));
	}

	public static function create_from_object($post)
	{
		if ($post)
		{
			$item = new vBForum_Search_Result_Post($post);
			return $item;
		}
		else
		{
			return new vB_Search_Result_Null();
		}
	}

	protected function __construct($post = null)
	{
		if (!empty($post))
		{
			$this->post = $post;
		}
	}

	public function get_contenttype()
	{
		return vB_Search_Core::get_instance()->get_contenttypeid('vBForum', 'Post');
	}

	public function can_search($user)
	{
		return $this->post->can_search($user);
	}

	public function get_group_item()
	{
		return vBForum_Search_Result_Thread::create_from_thread($this->post->get_thread());
	}

	//set reply data
	private function set_replydata($threadid, $postid, $current_user)
	{
		global $vbulletin;

		$readtime = $vbulletin->db->query_first("SELECT 
			MAX(tr.readtime) AS readtime FROM " . TABLE_PREFIX . "threadread AS tr WHERE
			tr.threadid = $threadid AND tr.userid = " . $current_user->get_field('userid'));
		
		if($vbulletin->options['showdots'])
		{
			$mylastpost = $vbulletin->db->query_first("SELECT
				MAX(p.dateline) AS mylastpost FROM " . TABLE_PREFIX . "post AS p
				WHERE p.parentid = $postid AND p.userid = " .
				$current_user->get_field('userid') );
		}
		else
		{
			$mylastpost['mylastpost'] = 0;
		}
		
		$this->replydata=array(
			'readtime' => $readtime['readtime'],
			'mylastpost' => $mylastpost['mylastpost']
		);
	}

	public function render($current_user, $criteria, $template_name = '')
	{
		global $vbulletin, $vbphrase, $show;
		require_once (DIR . '/includes/functions_forumdisplay.php');
		require_once (DIR . '/includes/functions_misc.php');
		require_once(DIR . '/includes/functions_user.php');

		fetch_phrase_group('search');


		if (!strlen($template_name)) {
			$template_name = 'search_results_postbit';
		}

		/*
			Post is not a good name for this array, however its what it used to be called
			(when it wasn't such a bad name) and changing it makes it certain that a lot of
			hooks are going to break.
		*/
		$post = array();
		$thread = $this->post->get_thread();

		$forum = $thread->get_forum();
		$this->set_replydata($this->post->get_field('threadid'), $this->post->get_field('postid'), $current_user);

		if ($this->replydata['mylastpost'] > 0)
		{
			$post_statusicon[] = 'dot';
		}

		if (!$thread->get_field('open'))
		{
			$post_statusicon[] = 'lock';
		}

		if ($this->replydata['lastread'] < $thread->get_field('lastpost'))
		{
			$post_statusicon[] = 'new';
		}

		if (! count($post_statusicon))
		{
			$post_statusicon[] = 'old';
		}

		$post_statusicon = implode('_', $post_statusicon);

		$post['postid'] = $this->post->get_field('postid');
		$post['postdateline'] = $this->post->get_field('dateline');
		$post['posttitle'] = vB_Search_Searchtools::stripHtmlTags(htmlspecialchars_decode($this->post->get_display_title()));
		if(empty($post['posttitle'])) 
		{ 
			$post['posttitle'] = $vbphrase['view_post']; 
		}
		$post['visible'] = $this->post->get_field('visible');
		$post['attach'] = $this->post->get_field('attach');

		$post['highlight'] = $criteria->get_highlights();
		$post['userid'] = $this->post->get_field('userid');
		if ($post['userid'] == 0)
		{
			$post['username'] = $this->post->get_field('username');
		}
		//making sure the user exists in the database
		elseif ($user = $this->post->get_user() AND $user->has_field('username'))
		{
			$post['username'] = $user->get_field('username');
		}
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

		$post['posticonid'] = $this->post->get_field('iconid');
		$post['allowicons'] = $forum->allow_icons();
		$post['posticonpath'] = $this->post->get_icon_path();
		$post['posticontitle'] = $this->post->get_icon_title();
		$post['posticon'] = $post ['allowicons'] and $post ['posticonpath'];

		$lastread = $forum->get_last_read_by_current_user($current_user);

		if ($current_user->hasForumPermission($forum->get_field('forumid'), 'canviewthreads'))
		{
			if (defined('VB_API') AND VB_API === true)
			{
				$post['pagetext'] = $this->post->get_field('pagetext');
				$post['message_plain'] = build_message_plain($post['pagetext']);
			}
			else
			{
				$post['pagetext'] = nl2br($this->post->get_summary(200));
			}
		}

		$show['deleted'] = false;
		if ($current_user->isModerator())
		{
			$log = $this->post->get_deletion_log_array();
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
		($hook = vBulletinHook::fetch_hook('search_results_postbit')) ? eval($hook) : false;

		$template = vB_Template::create($template_name);
		$template->register('post', $post);
		$template->register('userinfo', fetch_userinfo($this->post->get_field('userid')));
		$template->register('threadinfo', $thread->get_record());
		$template->register('lastpostdate', vbdate($vbulletin->options['dateformat'], $thread->get_field('lastpost'), true));
		$template->register('lastpostdatetime', vbdate($vbulletin->options['timeformat'], $thread->get_field('lastpost')));
		$template->register('dateformat', $vbulletin->options['dateformat']);
		$template->register('timeformat',$vbulletin->options['default_timeformat']);
		$template->register('dateline', $this->post->get_field('dateline'));

		if ($vbulletin->options['avatarenabled'])
		{
			$template->register('avatar', fetch_avatar_url($this->post->get_field('userid')));
		}


		$template->register('dateline', $this->post->get_field('dateline'));


		$pageinfo_thread = array();
		$pageinfo_post = array('p' => $post['postid']);
		if (!empty($post['highlight']))
		{
			$pageinfo_post['highlight'] = urlencode(implode(' ', $post['highlight']));
			$pageinfo_thread['highlight'] = urlencode(implode(' ', $post['highlight']));
		}

		$template->register('pageinfo_post', $pageinfo_post);
		$template->register('pageinfo_thread', $pageinfo_thread);
		$template->register('post_statusicon', $post_statusicon);

		return $template->render();
	}

	/**
	* Determine if a post is new in the context of a user
	*
	*	This feels like it should be moved to the post object itself, however,
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
	private function is_post_new($post, $user)
	{
		global $vbulletin;

		// do post folder icon
		if ($vbulletin->options['threadmarking'] AND !$user->isGuest())
		{
			//avoid calling get_lastread if possible
			$thread = $post->get_thread();
			return (($post->get_field('dateline') >
				$vbulletin->forumcache[$thread->get_field('forumid')]['forumread']) AND
				($post->get_field('dateline') > $thread->get_lastread($user))
			);
		}
		else
		{
			return ($post->get_field('dateline') > $vbulletin->userinfo['lastvisit']);
		}
	}

	public function get_post()
	{
		return $this->post;
	}


	/*** Returns the primary id. Allows us to cache a result item.
	 *
	 * @result	integer
	 ***/
	public function get_id()
	{
		if (isset($this->post) AND ($postid = $this->post->get_field('postid')))
		{
			return $postid;
		}
		return false;
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
		$forumid = $this->post->get_thread()->get_field('forumid');
		return (
			$user->canModerateForum($forumid, 'canmanagethreads') OR
			$user->canModerateForum($forumid, 'candeleteposts') OR
			$user->canModerateForum($forumid, 'canremoveposts') OR
			$user->canModerateForum($forumid, 'canmoderateposts') OR
			$user->canModerateForum($forumid, 'canmoderateattachments')
		);
	}
	private $post;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/