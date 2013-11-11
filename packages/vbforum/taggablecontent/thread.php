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

require_once(DIR . '/includes/class_taggablecontent.php');
/**
* Handle thread specific logic
*
*	Internal class, should not be directly referenced
* use vB_Taggable_Content_Item::create to get instances
*	see vB_Taggable_Content_Item for method documentation
*/
class vBForum_TaggableContent_Thread extends vB_Taggable_Content_Item
{
	protected function load_content_info()
	{
		return verify_id('thread', $this->contentid, 1, 1);
	}

	public function can_delete_tag($taguserid)
	{
		$contentinfo = $this->fetch_content_info();

		//the user can delete his own tag associations
		if ($taguserid == $this->registry->userinfo['userid'])
		{
			return true;
		}

		if ($this->can_moderate_tag())
		{
			return true;
		}

		$forumperms = fetch_permissions($contentinfo['forumid']);
		return ($forumperms & $this->registry->bf_ugp_forumpermissions['candeletetagown']) AND
			$this->is_owned_by_current_user();
	}

	public function can_moderate_tag()
	{
		$contentinfo = $this->fetch_content_info();
		return can_moderate($contentinfo['forumid'], 'caneditthreads');
	}

	public function can_add_tag()
	{
		$contentinfo = $this->fetch_content_info();

		//forum lookups should be cached and therefore inexpensive
		$foruminfo = fetch_foruminfo($contentinfo['forumid']);
		$forumperms = fetch_permissions($contentinfo['forumid']);
		if (!$foruminfo['allowposting'] OR (!$contentinfo['open'] AND !can_moderate($contentinfo['forumid'], 'canopenclose')))
		{
			return false;
		}
		else
		{
			return (
				(($forumperms & $this->registry->bf_ugp_forumpermissions['cantagown']) AND $this->is_owned_by_current_user())
				OR ($forumperms & $this->registry->bf_ugp_forumpermissions['cantagothers'])
			);
		}
	}

	public function can_manage_tag()
	{
		$contentinfo = $this->fetch_content_info();

		//forum lookups should be cached and therefore inexpensive
		$foruminfo = fetch_foruminfo($contentinfo['forumid']);
		$forumperms = fetch_permissions($contentinfo['forumid']);
		if (!$foruminfo['allowposting'] OR (!$contentinfo['open'] AND !can_moderate($contentinfo['forumid'], 'canopenclose')))
		{
			return $this->can_moderate_tag();
		}
		else
		{
			return (
				$this->can_add_tag()
				OR (($forumperms & $this->registry->bf_ugp_forumpermissions['candeletetagown']) AND $this->is_owned_by_current_user())
				OR $this->can_moderate_tag()
			);
		}
	}

	public function is_owned_by_current_user()
	{
		$contentinfo = $this->fetch_content_info();
		return ($contentinfo['postuserid'] == $this->registry->userinfo['userid']);
	}

	public function fetch_content_type_diplay()
	{
		global $vbphrase;
		return $vbphrase['thread'];
	}

	public function rebuild_content_tags()
	{
		$contentinfo = $this->fetch_content_info();

		if (!$contentinfo)
		{
			return '';
		}

		// The thread dm needs the first post's page text and the tags in order to build the thread's meta tags
		$pagetext = $this->registry->db->query_first_slave("
			SELECT pagetext
			FROM " . TABLE_PREFIX . "post
			WHERE
				threadid = " . intval($contentinfo['threadid']) . "
					AND
				postid = " . intval($contentinfo['firstpostid']) . "
		");

		$tags = $this->fetch_existing_tag_list();
		$taglist = implode(', ', $tags);
		$dataman =& datamanager_init('Thread', $this->registry, ERRTYPE_SILENT, 'threadpost');
		$dataman->set_existing($contentinfo);
		$dataman->set_info('pagetext', $pagetext['pagetext']);
		$dataman->set('taglist', $taglist);
		$dataman->save();


		return $taglist;
	}

	public function is_cloud_cachable()
	{
		return ($this->registry->options['tagcloud_usergroup'] != -1);
	}

	public function fetch_tag_cloud_query_bits()
	{
		$forums = array();

		//limit cloud based on forum permissions
		$perm_limit = (boolean) $this->registry->options['tagcloud_usergroup'];
		if ($perm_limit)
		{
			foreach ($this->registry->forumcache AS $forumid => $forum)
			{
				//live permission checking
				if ($this->registry->options['tagcloud_usergroup'] == -1)
				{
					$perm_array = $this->registry->userinfo['forumpermissions']["$forumid"];
				}
				//usergroup permission checking
				else
				{
					$perm_array = $forum['permissions'][$this->registry->options['tagcloud_usergroup']];
				}

				if ($perm_array & $this->registry->bf_ugp_forumpermissions['canview']
					AND $perm_array & $this->registry->bf_ugp_forumpermissions['canviewthreads']
					AND $perm_array & $this->registry->bf_ugp_forumpermissions['canviewothers']
				)
				{
					$forums[] = intval($forumid);
				}
			}
		}

		if (!$perm_limit OR count($forums))
		{
			$contenttype = vB_Types::instance()->getContentTypeID("vBForum_Thread");
			$join['thread'] = "INNER JOIN " . TABLE_PREFIX . "thread AS thread ON (tagcontent.contentid = thread.threadid)";
			$where[] = "thread.open <> 10";
			$where[] = "thread.visible = 1";

			if(count($forums))
			{
				$where[] = "thread.forumid IN (" . implode(',', $forums) . ")";
			}

			return array('join' => $join, 'where' => $where);
		}
		else
		{
			return false;
		}
	}

	public function fetch_return_url()
	{
		$url = parent::fetch_return_url();
		if(!$url)
		{
			$url =  fetch_seo_url('thread', $this->fetch_content_info()) . '#taglist';
		}
		return $url;
	}

	public function fetch_page_nav()
	{
		global $vbphrase;
		$contentinfo = $this->fetch_content_info();

		// navbar and output
		$navbits = array();

		$foruminfo = fetch_foruminfo($contentinfo['forumid']);
		$parentlist = array_reverse(explode(',', substr($foruminfo['parentlist'], 0, -3)));
		foreach ($parentlist AS $forumID)
		{
			$forumTitle = $this->registry->forumcache[$forumID]['title'];
			$navbits[fetch_seo_url('forum', array('forumid' => $forumID, 'title' => $forumTitle))] = $forumTitle;
		}
		$navbits[fetch_seo_url('thread', $contentinfo)] = $contentinfo['prefix_plain_html'] . ' ' . $contentinfo['title'];

		$navbits[''] = $vbphrase['tag_management'];
		$navbits = construct_navbits($navbits);
		return $navbits;
	}


	public function verify_ui_permissions()
	{
		global $vbulletin;
		if (!$vbulletin->options['threadtagging'])
		{
			print_no_permission();
		}

		global $vbphrase;
		$threadinfo = $this->fetch_content_info();

		// *********************************************************************************
		// check for visible / deleted thread
		if (
			((!$threadinfo['visible'] AND !can_moderate($threadinfo['forumid'], 'canmoderateposts'))) OR
			($threadinfo['isdeleted'] AND !can_moderate($threadinfo['forumid']))
		)
		{
			eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $this->registry->options['contactuslink'])));
		}

		// *********************************************************************************
		// jump page if thread is actually a redirect
		if ($threadinfo['open'] == 10)
		{
			$destthreadinfo = fetch_threadinfo($threadinfo['pollid']);
			exec_header_redirect('thread|js', $destthreadinfo);
		}

		// *********************************************************************************
		// Tachy goes to coventry
		if (in_coventry($threadinfo['postuserid']) AND !can_moderate($threadinfo['forumid']))
		{
			eval(standard_error(fetch_error('invalidid', $vbphrase['thread'], $this->registry->options['contactuslink'])));
		}

		// *********************************************************************************
		// get forum info
		$foruminfo = fetch_foruminfo($threadinfo['forumid']);

		// *********************************************************************************
		// check forum permissions
		$forumperms = fetch_permissions($threadinfo['forumid']);
		if (!($forumperms & $this->registry->bf_ugp_forumpermissions['canview']) OR
			!($forumperms & $this->registry->bf_ugp_forumpermissions['canviewthreads'])
		)
		{
			print_no_permission();
		}
		if (!($forumperms & $this->registry->bf_ugp_forumpermissions['canviewothers']) AND
			($threadinfo['postuserid'] != $this->registry->userinfo['userid'] OR $this->registry->userinfo['userid'] == 0)
		)
		{
			print_no_permission();
		}

		// *********************************************************************************
		// check if there is a forum password and if so, ensure the user has it set
		verify_forum_password($foruminfo['forumid'], $foruminfo['password']);
//		return $show;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 27657 $
|| ####################################################################
\*======================================================================*/
