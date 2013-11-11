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
 * @author Ed Brown, vBulletin Development Team
 * @version $Id: visitormessage.php 30597 2009-04-30 22:25:07Z ksours $
 * @since $Date: 2009-04-30 15:25:07 -0700 (Thu, 30 Apr 2009) $
 * @copyright vBulletin Solutions Inc.
 */

require_once (DIR . '/vb/search/result.php');
require_once (DIR . '/vb/search/indexcontroller/null.php');
/**
 * Result Implementation for Visitor Messages
 *
 * @see vB_Search_Result
 * @package vBulletin
 * @subpackage Search
 */
class vBForum_Search_Result_VisitorMessage extends vB_Search_Result
{
// ###################### Start create ######################
	/**
	 * vBForum_Search_Result_VisitorMessage::create()
	 *
	 * @param integer $id
	 * @return result object
	 */
	public function create($id)
	{
		$items = self::create_array(array($id));
		if (count($items))
		{
			return array_shift($items);
		}
		else
		{
			return new vB_Search_Result_Null();
		}
	}

	public function create_array($ids)
	{
		global $vbulletin;

		$set = $vbulletin->db->query_read_slave ($sql = "
			SELECT visitormessage.*, user.username, user.options AS useroptions, 
				ifnull(profileblockprivacy.requirement, 0) AS requirement " .
			($vbulletin->options['avatarenabled'] ? ", avatar.avatarpath,
			customavatar.userid AS hascustomavatar, customavatar.dateline AS avatardateline,
			customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
			FROM ". TABLE_PREFIX . "visitormessage AS visitormessage JOIN	" . 
				TABLE_PREFIX . "user AS user ON visitormessage.userid = user.userid LEFT JOIN " .
				TABLE_PREFIX . "profileblockprivacy AS profileblockprivacy ON visitormessage.userid = profileblockprivacy.userid AND 
					profileblockprivacy.blockid = 'visitor_messaging' " .
				(vB::$vbulletin->options['avatarenabled'] ? 
				"LEFT JOIN " . TABLE_PREFIX .	"user AS poster ON(poster.userid = visitormessage.postuserid) LEFT JOIN " . 
				TABLE_PREFIX .	"avatar AS avatar ON(avatar.avatarid = poster.avatarid) LEFT JOIN " . 
				TABLE_PREFIX .	"customavatar AS customavatar ON(customavatar.userid = poster.userid)" : "") . "
			WHERE vmid IN (" . implode(',', array_map('intval', $ids)) . ")
		");

		$items = array();
		while ($row = $vbulletin->db->fetch_array($set))
		{
			$item = new vBForum_Search_Result_VisitorMessage();
			$item->message = $row;
			$items[$row['vmid']] = $item;
		}

		return $items;
	}


// ###################### Start __construct ######################
	/**
	 * vBForum_Search_Result_VisitorMessage::__construct()
	 *
	 */
	protected function __construct() {}

	/**
	 * vBForum_Search_Result_VisitorMessage::get_contenttype()
	 *
	 * @return integer contenttypeid
	 */
	public function get_contenttype()
	{
		return vB_Search_Core::get_instance()->get_contenttypeid('vBForum', 'VisitorMessage');
	}

	// ###################### Start can_search ######################
	/**
	 * vBForum_Search_Result_VisitorMessage::can_search()
	 *
	 * @param mixed $user: the id of the user requesting access
	 * @return bool true
	 */
	public function can_search($user)

	{
		global $vbulletin;
		require_once( DIR . '/includes/functions_visitormessage.php');
		require_once( DIR . '/includes/functions_user.php');

		//if visitor messages are turned off don't display anything.
		if (!($vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_visitor_messaging']))
		{
			return false;
		}

		//if the user can't view member profiles at all, they can't see visitor messages.
		if (!$user->hasPermission('genericpermissions', 'canviewmembers'))
		{
			return false;
		}

		//do we have permissions to view this visitor message based on our permissions.
		if (!fetch_visitor_message_perm('canviewvisitormessages', $this->message,  $this->message))
		{
			//We have a function fetch_visitor_message_perm in functions_visitormessage
			// that tells whether we can see this message. It needs
			// $perm, &$userinfo, $message. $perm is 'canviewvisitormessages',
			// $userinfo is $vbulletin->userinfo, and $message is an array which,
			// as far as I can see, must have state and postuserid. The comment
			// says it's the result of a call to fetch_messageinfo(), but we don't have
			// any such function.
			//So.. if we just pass $message twice, we have all the necessary parameters.
			return false;
		}

		//If this is a message on the current user's profile or the current user is a mod we can skip some checks.
		if (!($this->message['userid'] == $user->getField('userid') OR can_moderate(0,'canmoderatevisitormessages')))
		{
			//if the user has disabled their visitor messages then don't show them.
			//this is under the main user options rather than the profile privacy
			if (!($this->message['useroptions'] & $vbulletin->bf_misc_useroptions['vm_enable']))
			{
				return false;
			}
		}

		//do we have permissions to view this user's visitor messages based on privacy settings.
		//do this last because it's the most likely to result in an extra query.
		$relationship_level = fetch_user_relationship($this->message['userid'], $user->getField('userid'));
		if ($relationship_level < $this->message['requirement'])
		{
			return false;
		}

		//for some reason, in addition to the permission settings under "profile privacy" there is an option to
		//limit visitor messages to "contacts only" in the main user option settings.  The level for "contact" is 
		//2 -- anything higher than that should be considered a contact.
		if (($this->message['useroptions'] & $vbulletin->bf_misc_useroptions['vm_contactonly']) AND $relationship_level < 2)
		{
			return false;
		}

		return true;
	}
	// ###################### Start getUserName ######################
	/**
	 * vBForum_Search_IndexController_VisitorMessage::getUserName()
	 *
	 * @param integer $userid
	 * @return string username : name of the user with that id.
	 */
	 /*
	private function getUserName($userid)
	{
		global $vbulletin;

	*/

	// ###################### Start render ######################
	/**
	 * vBForum_Search_Result_VisitorMessage::render()
	 *
	 * @param string $current_user
	 * @param object $criteria
	 * @return
	 */
	public function render($current_user, $criteria, $template_name = '')
	{
		global $vbulletin;
		require_once DIR . '/includes/functions_user.php';
		
		if (!strlen($template_name)) {
			$template_name = 'search_results_visitormessage';
		}

		//TODO- create a template and pass it the necessary information
		//TODO- check vbphrase and see what we have to add.
		//TODO- figure if we are passing the right parameters. I suspect not.
		global $show;
		$template = vB_Template::create($template_name);
		$template->register('messagetext',
			vB_Search_Searchtools::getSummary($this->message['pagetext'], 100));
		//The template is out with the variables fromid and toid. It should just be
		// from and to, but we need to get out a simple patch.

		$from = array('userid' => $this->message['postuserid'], 'username' => $this->message['postusername']);
		$to = array('userid' => $this->message['userid'], 'username' => $this->message['username']);
		$template->register('vmid', $this->message['vmid']);
		$template->register('to', $this->message['username']);
		$template->register('from', $this->message['postusername']);
		$template->register('fromid', $from);
		$template->register('toid', $to);
		$template->register('sent', vbdate($vbulletin->options['dateformat']. ' '
			. $vbulletin->options['timeformat'], $this->message['dateline']));
		$template->register('dateline', $this->message['dateline']);
		$template->register('dateformat', $vbulletin->options['dateformat']);
		$template->register('timeformat', $vbulletin->options['timeformat']);

		if (vB::$vbulletin->options['avatarenabled'])
		{
			$template->register('avatar', fetch_avatar_from_record($this->message));
		}
		return $template->render();
	}

	/*** Returns the primary id. Allows us to cache a result item.
	*
	* @result	integer
	***/
	public function get_id()
	{
		if (isset($this->message) AND isset($this->message['vmid']) )
		{
			return $this->message['vmid'];
		}
		return false;
	}

	private $message;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 30597 $
|| ####################################################################
\*======================================================================*/
