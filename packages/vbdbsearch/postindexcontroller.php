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


require_once (DIR . '/packages/vbforum/search/indexcontroller/post.php');
require_once (DIR . '/packages/vbdbsearch/indexer.php');

/**
 * @package vbdbsearch
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
class vBDBSearch_PostIndexController extends vBForum_Search_IndexController_Post
{
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
			$indexer->delete($this->get_contenttypeid(), $id);
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
	public function thread_data_change($id)
	{
		$this->group_data_change($id);
	}

	public function group_data_change($id)
	{
		$thread = vB_Legacy_Thread::create_from_id($id);
		if (!$thread)
		{
			//skip non existant threads.
			return;
		}

		$fields['groupdateline'] = $thread->get_field('lastpost');
		$fields['grouptitle'] = $thread->get_field('title');
		$fields['groupuserid'] = $thread->get_field('postuserid');
		$fields['groupusername'] = $thread->get_field('postusername');
		$fields['groupcontenttypeid'] = $this->groupcontenttypeid;
		$fields['groupid'] = $thread->get_field('threadid');

		$indexer = vB_Search_Core::get_instance()->get_core_indexer();
		$indexer->group_data_change($fields);
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
		$indexer = vB_Search_Core::get_instance()->get_core_indexer();
		$indexer->delete_group($this->groupcontenttypeid, $id);
	}

	/**
	* We just pass this to the core indexer, which knows how to do this.
	*/
	public function merge_group($oldid, $newid)
	{
		$indexer = vB_Search_Core::get_instance()->get_core_indexer();
		$indexer->merge_group($this->groupcontenttypeid, $oldid, $newid);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/
