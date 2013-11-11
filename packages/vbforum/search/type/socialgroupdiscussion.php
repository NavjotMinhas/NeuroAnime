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
require_once (DIR . '/packages/vbforum/search/result/socialgroupdiscussion.php');

/**
 * Enter description here...
 *
 * @package vBulletin
 * @subpackage Search
 */
class vBForum_Search_Type_SocialGroupDiscussion extends vB_Search_Type
{
	public function fetch_validated_list($user, $ids, $gids)
	{
		$list = array();
		foreach (new vBForum_Collection_SocialGroupDiscussion($ids) as $id => $discussion)
		{
			$item = vBForum_Search_Result_SocialGroupDiscussion::create_from_object($discussion);

			if ($item->can_search($user))
			{
				$list[$id] = $item;
			}
		}

		return array('list' => $list, 'groups_rejected' => array());
	}

	public function is_enabled()
	{
		global $vbulletin;
		return (($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_groups']) AND
			$vbulletin->options['socnet_groups_msg_enabled']);
	}

	/**
	 * Enter description here...
	 *
	 * @param unknown_type $id
	 */
	public function create_item($id)
	{
		return vBForum_Search_Result_SocialGroupDiscussion::create($id);
	}

	/**
	 * You can create from an array also
	 *
	 * @param integer $id
	 * @return object
	 */
	public function create_array($ids)
	{
		return vBForum_Search_Result_SocialGroupDiscussion::create_array($ids);
	}

	public function additional_header_text()
	{
		return '';
	}

	public function get_display_name()
	{
		return new vB_Phrase('search', 'searchtype_social_group_discussions');
	}

	public function prepare_render($user, $results)
	{
		$phrase = new vB_Legacy_Phrase();
		$phrase->add_phrase_groups(array('user', 'socialgroups'));

		foreach ($results AS $result)
		{
			$group = $result->get_discussion()->getSocialGroup();
			$privs = array();
			show_group_inlinemoderation($group->get_record(), $privs, true);

			//if we have a right for any item in the result set we have that right
			foreach($privs as $key => $priv)
			{
				$this->mod_rights[$key] = ($this->mod_rights[$key] OR (bool) $priv);
			}
		}
	}

	public function get_inlinemod_options()
	{
		global $vbphrase;
		$options = array();

		$mod_options = array();

		if ($this->mod_rights['delete'])
		{
			$mod_options[$vbphrase['delete_discussions']] = 'inlinedelete';
		}

		if ($this->mod_rights['undelete'])
		{
			$mod_options[$vbphrase['undelete_discussions']] = 'inlineundelete';
		}

		if ($this->mod_rights['approve'])
		{
			$mod_options[$vbphrase['approve_discussions']] = 'inlineapprove';
			$mod_options[$vbphrase['unapprove_discussions']] = 'inlineunapprove';
		}

		//if we have any mod options then we add the rest
		if ($mod_options)
		{
			$options[$vbphrase['option']] = $mod_options;
			$basic_options = array();
			$basic_options[$vbphrase['deselect_all_discussions']] = 'clearmessage';
			$options ["____________________"] = $basic_options;
		}
		return $options;
	}

	public function get_inlinemod_type()
	{
		return 'gdiscussion';
	}

	public function get_inlinemod_action()
	{
		global $vbulletin;
		$base = '';
		if ($vbulletin->options['vbforum_url'])
		{
			$base = $vbulletin->options['vbforum_url'] . '/';
		}
		return $base . 'group_inlinemod.php?inline_discussion=1';
	}

	protected $mod_rights = array();

	protected $package = "vBForum";
	protected $class = "SocialGroupDiscussion";
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/
