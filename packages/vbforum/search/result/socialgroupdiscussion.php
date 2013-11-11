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
require_once (DIR . '/packages/vbforum/item/socialgroupdiscussion.php');

/**
 * Enter description here...
 *
 * @package vBulletin
 * @subpackage Search
 */
class vBForum_Search_Result_SocialGroupDiscussion extends vB_Search_Result
{
	/**
	 * Enter description here...
	 *
	 * @param unknown_type $id
	 */
	public static function create($id)
	{
		return self::create_from_discussion(new vBForum_Item_SocialGroupDiscussion($id));
	}

	public static function create_from_discussion($discussion)
	{
		return self::create_from_object($discussion);
	}

	public static function create_from_object($discussion)
	{
		if ($discussion->isValid())
		{
			$item = new vBForum_Search_Result_SocialGroupDiscussion();
			$item->discussion = $discussion;
			return $item;
		}
		else
		{
			return new vB_Search_Result_Null();
		}
	}

	protected function __construct() {}

	public function get_contenttype()
	{
		return vB_Search_Core::get_instance()->get_contenttypeid('vBForum', 'SocialGroupDiscussion');
   }

	/**
	 * Enter description here...
	 *
	 * @param unknown_type $user
	 * @return unknown
	 */
	public function can_search($user)
	{
		return $this->discussion->canBe('searched', $user);
	}

	/**
	 * Enter description here...
	 *
	 * @return unknown
	 */
	public function render($current_user, $criteria, $template_name = '')
	{
		require_once(DIR . "/includes/functions_socialgroup.php");
		require_once(DIR . "/includes/class_groupmessage.php");

		$user = vB_Legacy_User::createFromIdCached($this->discussion->getUserId());
		$item = $this->discussion->getInfo($current_user->get_field('userid'));
		$item = array_merge($item, $user->get_record());
		$item['issearch'] = true;

		$group = $this->discussion->getSocialGroup();
		if ($group->has_modperm('canviewdeleted', $current_user))
		{
			$dellog = $this->discussion->getDeletionLogArray();
			$item['del_username'] = $dellog['username'];
			$item['del_userid'] = $dellog['userid'];
			$item['del_reason'] = $dellog['reason'];
		}

		//I'm not sure what these are for, but they exist in the template so we should
		//make sure they are set.  The main message display code does not appear to
		//set them.
		global $show;
		$show['group'] = false;

		global $vbulletin;
		$factory = new vB_Group_Bit_Factory($vbulletin);
		$bit = $factory->create($item, $this->discussion->getSocialGroup()->get_record());

		//unfortunately the bit render can set the $show['inlinemod'] variable which we are
		//currently using across all templates to handle the inline mod settings.  We should
		//probably rely on something a little less global but that's not in the cards right now.
		//make sure that we honor the starting value and restore it when we are done.
		$inlinemod =  $show['inlinemod'];
		$bit->show_moderation_tools($inlinemod);
		$text = $bit->construct();
		$show['inlinemod'] = $inlinemod;

		//todo this is ugly and invalid html.  We need to get the message_list
		//id out of here and convert the css to use a class for that formatting
		//but lets wait until we figure out what the new look and feel is going
		//do to or for us.
		return $text;
	}

	public function get_discussion()
	{
		return $this->discussion;
	}


	/*** Returns the primary id. Allows us to cache a result item.
	 *
	 * @result	integer
	 ***/
	public function get_id()
	{
		if (isset($this->discussion))
		{
			if ($item = $this->discussion->getInfo(vB::$vbulletin->userinfo['userid']))
			{
				return $item['discussionid'];
			}
			
		}
		return false;
	}

	private $discussion;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/