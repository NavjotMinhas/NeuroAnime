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

require_once (DIR . '/vb/search/type.php');
require_once (DIR . '/packages/vbforum/search/result/thread.php');
require_once (DIR . '/includes/functions_forumdisplay.php');

/**
 * Enter description here...
 *
 * @package vBulletin
 * @subpackage Search
 */
class vBForum_Search_Type_Thread extends vB_Search_Type
{
	public function fetch_validated_list($user, $ids, $gids)
	{
		require_once(DIR . '/includes/functions_forumlist.php');
		cache_moderators();

		$list = array_fill_keys($ids, false);
		foreach (vB_Legacy_Thread::create_array($ids) as $key => $thread)
		{
			$item = vBForum_Search_Result_Thread::create_from_thread($thread);
			if($item->can_search($user))
			{
				$list[$key] = $item;
			}
		}

		return array('list' => $list, 'groups_rejected' => $rejected_groups);
	}

	public function prepare_render($user, $results)
	{
		global $show, $vbulletin;

		$this->mod_rights['movethread'] = false;
		$this->mod_rights['deletethread'] = false;
		$this->mod_rights['approvethread'] = false;
		$this->mod_rights['openthread'] = false;

		$ids = array();
		foreach ($results as $result)
		{
			$forumid = $result->get_thread()->get_field('forumid');

			$this->mod_rights['movethread'] = ($this->mod_rights['movethread'] OR 
				$user->canModerateForum($forumid, 'canmanagethreads'));

			$this->mod_rights['deletethread'] = ($this->mod_rights['deletethread'] OR
				($user->canModerateForum($forumid, 'candeleteposts') OR 
					$user->canModerateForum($forumid, 'canremoveposts')));

			$this->mod_rights['approvethread'] = ($this->mod_rights['approvethread'] OR 
				$user->canModerateForum($forumid, 'canmoderateposts'));

			$this->mod_rights['openthread'] = ($this->mod_rights['openthread'] OR 
				$user->canModerateForum($forumid, 'canopenclose'));
			
			//we need to know if any particular thread allows icons before we render any of them
			$thread = $result->get_thread();
			if ($thread->has_icon())
			{
				$show['threadicons'] = true;
			}

			$ids[] = $thread->get_field('threadid');
		}

		//this is used by process_thread_array in functions_forumdisplay.php
		//which is called from vBForum_Search_Result_Thread::render
		global $dotthreads;
		$dotthreads = fetch_dot_threads_array(implode(',', $ids));
	}

	public function get_display_name()
	{
		return new vB_Phrase('search', 'searchtype_threads');
	}

	public function create_item($id)
	{
		return vBForum_Search_Result_Thread::create($id);
	}

	public function can_search($user)
	{
		return true;
	}

	/**
	*	Get the inline moderation menu options
	*
	*	This gets called *after* prepare_render to allow adjustments based on 
	* what is in the resultset
	*/
	public function get_inlinemod_options()
	{
		global $vbphrase, $show;
		$options = array();

		$mod_options = array();

	
		if ($this->mod_rights['deletethread'])
		{
			$mod_options[$vbphrase['delete_threads']] = 'deletethread';
			$mod_options[$vbphrase['undelete_threads']] = 'undeletethread';
		}
		
		if ($this->mod_rights['deletethread'] OR $this->mod_rights['movethread'])
		{
			$mod_options[$vbphrase['delete_threads_as_spam']] = 'spamthread';
		}


		if ($this->mod_rights['openthread'])
		{
			$mod_options[$vbphrase['open_threads']] = 'open';
			$mod_options[$vbphrase['close_threads']] = 'close';
		}

		if ($this->mod_rights['approvethread'])
		{
			$mod_options[$vbphrase['approve_threads']] = 'approvethread';
			$mod_options[$vbphrase['unapprove_threads']] = 'unapprovethread';
		}

		if ($this->mod_rights['movethread'])
		{
			$mod_options[$vbphrase['stick_threads']] = 'stick';
			$mod_options[$vbphrase['unstick_threads']] = 'unstick';
			$mod_options[$vbphrase['move_threads']] = 'movethread';
			$mod_options[$vbphrase['merge_threads']] = 'mergethread';
		}
		
		//if we have any mod options then we add the rest
		if ($mod_options)
		{
			$options[$vbphrase['option']] = $mod_options;
			$basic_options = array();
			$basic_options[$vbphrase['view_selected_threads']] = 'viewthread';
			$basic_options[$vbphrase['clear_thread_list']] = 'clearthread';
			$options ["____________________"] = $basic_options;
		}
		return $options;
	}

	public function get_inlinemod_type()
	{
		return 'thread';
	}

	public function get_inlinemod_action()
	{
		global $vbulletin;
		$base = '';
		if ($vbulletin->options['vbforum_url'])
		{
			$base = $vbulletin->options['vbforum_url'] . '/';
		}

		return $base . 'inlinemod.php';
	}

	protected $mod_rights = array();

	protected $package = "vBForum";
	protected $class = "Thread";
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/

