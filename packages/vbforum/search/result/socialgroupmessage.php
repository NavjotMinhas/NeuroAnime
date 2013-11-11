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
require_once (DIR . '/packages/vbforum/item/socialgroupmessage.php');
require_once (DIR . '/packages/vbforum/search/result/socialgroupdiscussion.php');
require_once (DIR . '/includes/class_bbcode.php');
require_once (DIR . '/includes/functions.php');

/**
 * Enter description here...
 *
 * @package vBulletin
 * @subpackage Search
 */
class vBForum_Search_Result_SocialGroupMessage extends vB_Search_Result
{
	/** Parser, needed to get preview text **/
	protected $bbcode_parser = false;

	/**
	 * Enter description here...
	 *
	 * @param unknown_type $id
	 */
	public static function create($id)
	{
		return self::create_from_object(new vBForum_Item_SocialGroupMessage($id));
	}

	public static function create_from_object($message)
	{
		if ($message->isValid())
		{
			$item = new vBForum_Search_Result_SocialGroupMessage();
			$item->message = $message;
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
		return vB_Search_Core::get_instance()->get_contenttypeid('vBForum', 'SocialGroupMessage');
  }

	public function can_search($user)
	{
		return $this->message->canBe('searched', $user);
	}

	public function get_group_item()
	{
		return vBForum_Search_Result_SocialGroupDiscussion::create_from_object($this->message->getDiscussion());
	}



	public function render($current_user, $criteria, $template_name = '')
	{
		require_once(DIR . "/includes/functions_socialgroup.php");
		require_once(DIR . "/includes/class_groupmessage.php");
		global $vbulletin;

		$user = vB_Legacy_User::createFromIdCached($this->message->getPostUserId(), FETCH_USERINFO_AVATAR);
		$item = $this->message->getInfo();
		if (strlen($template_name))
		{
			if ($record = vB::$vbulletin->db->query_first("SELECT gm.*, gr.groupid,
			disc.discussionid, starter.postuserid AS poststarterid, starter.postusername AS poststarter,
			disc.firstpostid, starter.title AS firsttitle, gr.groupid, gr.name AS groupname
			" . (vB::$vbulletin->options['avatarenabled'] ? ", avatar.avatarpath, user.userid, user.username,
				customavatar.userid AS hascustomavatar, customavatar.dateline AS avatardateline,
				customavatar.width AS avwidth,customavatar.height AS avheight" : "") .
				" FROM " . TABLE_PREFIX .
				"groupmessage AS gm\n INNER JOIN " . TABLE_PREFIX .
				"discussion AS disc ON disc.discussionid = gm.discussionid\n INNER JOIN " . TABLE_PREFIX .
				"socialgroup AS gr ON gr.groupid = disc.groupid\n INNER JOIN " . TABLE_PREFIX .
				"groupmessage AS starter ON starter.gmid = disc.firstpostid
				" . (vB::$vbulletin->options['avatarenabled'] ? "\nLEFT JOIN " . TABLE_PREFIX .
				"user AS user ON (user.userid =starter.postuserid) \nLEFT JOIN " . TABLE_PREFIX .
				"avatar AS avatar ON (avatar.avatarid = user.avatarid) \nLEFT JOIN " . TABLE_PREFIX .
				"customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
				WHERE gm.gmid = " . $item['gmid'] ))
			{
				$item = array_merge($item, $record);
			}

			if (!$this->bbcode_parser)
			{
				$this->bbcode_parser = new vB_BbCodeParser(vB::$vbulletin, fetch_tag_list());
			}
			$item['previewtext'] = fetch_censored_text($this->bbcode_parser->get_preview($item['pagetext'], 200));
			$template = vB_Template::create($template_name);
			$template->register('item', $item);
			$template->register('dateformat', $vbulletin->options['dateformat']);
			$template->register('timeformat', $vbulletin->options['default_timeformat']);
			
			if ($vbulletin->options['sg_enablesocialgroupicons'])
			{
				$template->register('socialgroupicon_url', fetch_socialgroupicon_url($item['groupid'], true));
			}
		
			if (vB::$vbulletin->options['avatarenabled'])
			{
				$template->register('avatar', fetch_avatar_from_record($item));
			}
			return $template->render();
		}

		$item = array_merge($item, $user->get_record());
		$item['hascustom'] = $item['hascustomavatar'];
		$item['issearch'] = true;

		$group = $this->message->getDiscussion()->getSocialGroup();
		if ($group->has_modperm('canviewdeleted', $current_user))
		{
			$dellog = $this->message->getDeletionLogArray();
			$item['del_username'] = $dellog['username'];
			$item['del_userid'] = $dellog['userid'];
			$item['del_reason'] = $dellog['reason'];
		}

		//I'm not sure what these are for, but they exist in the template so we should
		//make sure they are set.  The main message display code does not appear to
		//set them.
		global $show;
		$show['group'] = false;
		$show['discussion'] = false;

		//If we want to create our own template, then we need

		$factory = new vB_Group_Bit_Factory($vbulletin);
		//this gets lookup up by the bit anyway.  Not sure why we need to pass it.
		$group = array();
		$bit = $factory->create($item, $group);

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

	public function get_message()
	{
		return $this->message;
	}


	/*** Returns the primary id. Allows us to cache a result item.
	 *
	 * @result	integer
	 ***/
	public function get_id()
	{
		if (isset($this->message))
		{
			if ($item = $this->message->getInfo())
			{
				return $item['gmid'];
			}
		}
		return false;
	}

	private $message;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/