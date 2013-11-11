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

if (!class_exists('vB_Search_Core', false))
{
	exit;
}
require_once (DIR . '/vb/search/indexcontroller.php');
require_once (DIR . '/vb/legacy/post.php');
require_once (DIR . '/vb/search/core.php');

/**
 * @package vBulletin
 * @subpackage Search
 * @author Kevin Sours, vBulletin Development Team
 * @version $Revision: 28678 $
 * @since $Date: 2008-12-03 16:54:12 +0000 (Wed, 03 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */

/**
 * Index controller for posts
 *
 * @package vBulletin
 * @subpackage Search
 */
class vBForum_Search_IndexController_Post extends vB_Search_IndexController
{
	//We need to set the content types. This is available in a static method as below
	public function __construct()
	{
		$this->contenttypeid = vB_Search_Core::get_instance()->get_contenttypeid("vBForum", "Post");
		$this->groupcontenttypeid = vB_Search_Core::get_instance()->get_contenttypeid("vBForum", "Thread");
	}

	public function get_max_id()
	{
		global $vbulletin;
		$row = $vbulletin->db->query_first_slave("
			SELECT max(postid) AS max FROM " . TABLE_PREFIX . "post"
		);
		return $row['max'];
	}

	/**
	 * Index the post
	 *
	 * @param int $id
	 */
	public function index($id)
	{
		global $vbulletin;
		$post = vB_Legacy_Post::create_from_id($id, true);
		if ($post)
		{
			//We need to see whether this forum is set to not index.
			$thread = $vbulletin->db->query_first("SELECT forumid FROM " . TABLE_PREFIX .
				"thread WHERE threadid = " . $post->get_field('threadid'));
			if ($thread)
			{
				$forum = fetch_foruminfo($thread['forumid']);
				//The forum object has the necessary information
				if ($forum['indexposts'])
				{
					$indexer = vB_Search_Core::get_instance()->get_core_indexer();
					$fields = $this->post_to_indexfields($post);
					$indexer->index($fields);
				}
			}
		}
	}

	/**
	 * Index a range of posts
	 *
	 * @param unknown_type $start
	 * @param unknown_type $end
	 */
	public function index_id_range($start, $end)
	{
		global $vbulletin;
		$indexer = vB_Search_Core::get_instance()->get_core_indexer();

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

		$set = $vbulletin->db->query("
			SELECT " . implode(', ', $select) . "
			FROM " . TABLE_PREFIX . "post as p join " . TABLE_PREFIX . "thread as t ON p.threadid = t.threadid
			WHERE p.postid >= " . intval($start) . " AND p.postid <= " . intval($end));

		while ($row = $vbulletin->db->fetch_row($set))
		{
			//The assumption that cached thread lookups were fast enough seems to have been good.
			//however the memory requirements for long ranges added up fast, so we'll try pulling
			//the thread data with the posts.

			//Now we need to honor the "indexposts" setting in the forum.
			$forum = fetch_foruminfo($row[count($post_fields) + 3]);
			
			//The forum object has the necessary information
			if (!$forum['indexposts'])
			{
				continue;
			}
			$post_data = array_combine($post_fields, array_slice($row, 0, count($post_fields)));
			$thread_data = array_combine($thread_fields, array_slice($row, count($post_fields)));

			$post = vB_Legacy_Post::create_from_record($post_data, $thread_data);
			$fields = $this->post_to_indexfields($post);
			if ($fields)
			{
				$indexer->index($fields);
				$this->range_indexed++;
			}
		}
		$vbulletin->db->free_result($set);
	}

	/**
	 * Delete a range of posts
	 *
	 * @param int $start
	 * @param int $end
	 */
	public function delete_id_range($start, $end)
	{
		$indexer = vB_Search_Core::get_instance()->get_core_indexer();
		for ($i = $start; $i <= $end; $i++)
		{
			$indexer->delete($this->get_contentypeid(), $id);
		}
	}


	/**
	 * Index a thread
	 *
	 * By default this will look up all of the posts in a thread and calls the core
	 * indexer for each one
	 *
	 * @param int $id the thread id
	 */
	public function index_thread($id)
	{
		throw new Exception ('should not be here');
		global $vbulletin;

		$thread = vB_Legacy_Thread::create_from_id($id);

		$set = $vbulletin->db->query_read("
			SELECT post.* FROM " . TABLE_PREFIX . "post AS post WHERE threadid = " . intval($id)
		);

		$indexer = vB_Search_Core::get_instance()->get_core_indexer();
		while ($row = $vbulletin->db->fetch_array($set))
		{
			$post = vB_Legacy_Post::create_from_record($row, $thread);
			$fields = $this->post_to_indexfields($post);
			if ($fields)
			{
				$indexer->index($fields);
			}
		}
	}

	/**
	*	Reindex a the the thread data for posts in that thread
	*
	*	By default, this calls index_thread.  This is included so that search
	* implementations can potentially implement a more efficient approach when
	* they know that post data hasn't changed.
	*
	*	@param thread id
	*/
	public function thread_data_change($id)
	{
		return $this->index_thread($id);
	}

	public function group_data_change($id)
	{
		return $this->index_thread($id);
	}

	/**
	 * Merge one or more threads into a new thread id.
	 *
	 * By default, this simply calls index_thread on the new thread.
	 *
	 * @param int $oldid the old thread ids that were merged
	 * @param int $newid the thread id the threads where merged to
	 */
	public function merge_group($oldid, $newid)
	{
		//all of the posts from the old thread should be in the
		//new thread.  As a result, if we ignore the old threads entirely
		//and reindex the new thread the index will be updated.
		$this->index_thread($newid);
	}

	/**
	 * Delete all of the posts in a thread.
	 *
	 * By default this looks up all of the post ids in a thread and
	 * calls delete for each one
	 *
	 * @param int $id the thread id
	 */
	public function delete_thread($id)
	{
		global $vbulletin;
		$set = $vbulletin->db->query_read(
			"SELECT postid FROM " . TABLE_PREFIX . "post
			WHERE threadid = " . intval($id)
		);
		while ($row = $vbulletin->db->fetch_array($set))
		{
			$this->delete($row ['postid']);
		}
	}

	/**
	 * Index all of the posts in a forum.
	 *
	 * By default this looks up all of the thread ids in a forum and calls index_thread on each one.
	 *
	 * @param int $id the forum id
	 */
	public function index_forum($id)
	{
		global $vbulletin;
		$set = $vbulletin->db->query_read("
			SELECT threadid FROM " . TABLE_PREFIX . "thread WHERE forumid = " . intval($id));
		while ($row = $vbulletin->db->fetch_array($set))
		{
			$this->index_thread($row ['threadid']);
		}
	}

	/**
	 * Handle reindexing for a forum merge
	 *
	 * By default this reindexes the new forum remaining after the merge
	 *
	 * @param array(int) $oldids The forums eliminated due to the merge
	 * @param unknown_type $newid The forum remaining after the merge
	 */
	public function merge_forums($oldids, $newid)
	{
		$this->index_forum($newid);
	}

	/**
	 * Delete all posts for a forum.
	 *
	 * By default this fetches all of the thread ids for a forum and calls delete_thread
	 * on each.
	 *
	 * @param unknown_type $id
	 */
	public function delete_forum($id)
	{
		global $vbulletin;
		$set = $vbulletin->db->query_read("
			SELECT threadid FROM " . TABLE_PREFIX . "thread WHERE forumid = " . intval($id));
		while ($row = $vbulletin->db->fetch_array($set))
		{
			$this->delete_thread($row['threadid']);
		}
	}

	//*********************************************************************************
	//Private functions

	/**
	 * Convert a post object into the fieldset for the indexer
	 *
	 * @todo document fields passed to indexer
	 * @param post object
	 * @return array the index fields
	 */
	protected function post_to_indexfields($post)
	{
		//don't try to index inconsistant records
		$thread = $post->get_thread();
		if (!$thread)
		{
			return false;
		}

		$forum = $thread->get_forum();
		if (!$forum)
		{
			return false;
		}

		//common fields
		$fields['contenttypeid'] = $this->get_contenttypeid();
		$fields['id'] = $post->get_field('postid');
		$fields['dateline'] = $post->get_field('dateline');
		$fields['groupdateline'] = $thread->get_field('lastpost');
		$fields['defaultdateline'] = $fields['groupdateline'];
		$fields['grouptitle'] = $thread->get_field('title');
		$fields['userid'] = $post->get_field('userid');
		$fields['groupuserid'] = $thread->get_field('postuserid');
		$fields['defaultuserid'] = 	$fields['groupuserid'];
		$fields['username'] = $post->get_field('username');
		$fields['groupusername'] = $thread->get_field('postusername');
		$fields['defaultusername'] = 	$fields['groupusername'];
		$fields['ipaddress'] = $post->get_iplong();
		$fields['keywordtext'] = $post->get_field('title') . " " . $post->get_field('pagetext');

		$fields['groupcontenttypeid'] = $this->groupcontenttypeid;

		$fields['groupid'] = $post->get_field('threadid');

		//additional post fields
		$fields['visible'] = $post->get_field('visible');

		//thread fields
		$fields['threadid'] = $thread->get_field('threadid');
		$fields['replycount'] = $thread->get_field('replycount');
		$fields['lastpost'] = $thread->get_field('lastpost');

		$fields['threadstart'] = $thread->get_field('dateline');
		$fields['threadvisible'] = $thread->get_field('visible');
		$fields['open'] = $thread->get_field('open');

		//forum fields
		$fields['forumid'] = $forum->get_field('forumid');
		$fields['forumtitle'] = $forum->get_field('title');

		return $fields;
	}

	protected $contenttypeid;
	protected $groupcontenttypeid;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/