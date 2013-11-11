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
require_once (DIR . "/vb/legacy/calendar.php");

/**
 *
 */
class vB_Legacy_Event extends vB_Legacy_Dataobject
{
	/**
	 * Create object from and existing record
	 *
	 * @param int $foruminfo
	 * @return vB_Legacy_Thread
	 */
	public static function create_from_record($record)
	{
		$event = new vB_Legacy_Event();
		$event->set_record($record);
		return $event;
	}

	/**
	 * Load object from an id
	 *
	 * @param int $id
	 * @return vB_Legacy_Thread
	 */
	public static function create_from_id($id)
	{
		$items = self::create_array(array($id));
		if (count($items))
		{
			return array_shift($items);
		}
		else 
		{
			return null;
		}
	}

	public static function create_array($ids)
	{
		$set = vB::$vbulletin->db->query_read_slave($q = "
			SELECT event.*, user.username, user.usertitle, IF(dateline_to = 0, 1, 0) AS singleday,
				IF(user.displaygroupid = 0, user.usergroupid, user.displaygroupid) AS displaygroupid, infractiongroupid,
				user.adminoptions, user.usergroupid, user.membergroupids, user.infractiongroupids, IF(options & " . vB::$vbulletin->bf_misc_useroptions['hasaccessmask'] . ", 1, 0) AS hasaccessmask
				" . (vB::$vbulletin->userinfo['userid'] ? ", subscribeevent.eventid AS subscribed" : "") . "
				" . (vB::$vbulletin->options['avatarenabled'] ? ", user.avatarrevision, avatar.avatarpath,
				(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,
				customavatar.width AS avwidth,customavatar.height AS avheight" : "") . "
			FROM " . TABLE_PREFIX . "event AS event
			LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = event.userid)
			" . (vB::$vbulletin->userinfo['userid'] ? "LEFT JOIN " . TABLE_PREFIX . "subscribeevent AS subscribeevent ON(subscribeevent.eventid = event.eventid AND subscribeevent.userid = " . vB::$vbulletin->userinfo['userid'] . ")" : "") . "
			" . (vB::$vbulletin->options['avatarenabled'] ? "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)" : "") . "
			WHERE event.eventid IN (" . implode(',', array_map('intval', $ids)) . ")
		");
		
		$items = array();
		while ($record = vB::$vbulletin->db->fetch_array($set))
		{
			$items[] = self::create_from_record($record);
		}

		return $items;
	}
	
	

	/**
	*	Get the custom fields for a given calendar.
	*
	*	This should probably be on a calendar object, but I'm not sure I need one and
	* 
	*/
	private static function get_calendar_custom_fields($calendarid)
	{
		global $vbulletin;
		if (!isset(self::$custom_field_defs[$calendarid]))
		{
			$customcalfields = $vbulletin->db->query_read_slave("
				SELECT calendarcustomfieldid, title, options, allowentry, description
				FROM " . TABLE_PREFIX . "calendarcustomfield AS calendarcustomfield
				WHERE calendarid = " . intval($calendarid) . "
				ORDER BY calendarcustomfieldid
			");

			self::$custom_field_defs[$calendarid] = array();
			while ($custom = $vbulletin->db->fetch_array($customcalfields))
			{
				self::$custom_field_defs[$calendarid][] = $custom;
			}
		}

		return self::$custom_field_defs[$calendarid];
	}


	private static $custom_field_defs;

	/**
	 * constructor -- protectd to force use of factory methods.
	 */
	protected function __construct() {}

	//*********************************************************************************
	// Derived getters
	
	//*********************************************************************************
	// Related data

	public function get_calendar()
	{
		return vB_Legacy_Calendar::create_from_id_cached($this->get_field('calendarid'));
	}

	public function get_custom_fields()
	{
		require_once(DIR . '/includes/functions_newpost.php');

		$fielddefs = self::get_calendar_custom_fields($this->get_field("calendarid"));
		$customfields = unserialize($this->get_field('customfields'));
		
		$field_data = array();
		foreach ($fielddefs AS $fielddef)
		{
			$fielddef['options'] = unserialize($fielddef['options']);
			$optionval =  $customfields["{$fielddef['calendarcustomfieldid']}"];

			// Skip this value if a user entered entry exists but no longer allowed
			if (!$fielddef['allowentry'])
			{
				if (!(is_array($fielddef['options']) AND in_array($optionval, $fielddef['options'])))
				{
					continue;
				}
			}

			$customoption = parse_calendar_bbcode(convert_url_to_bbcode(unhtmlspecialchars($optionval)));

			$field_data[] = array (
				'title' =>  $fielddef['title'],
//				'description' =>  $fielddef['description'],
				'value' => $customoption
			);
		}
		return $field_data;
	}

	public function is_subscribed($user)
	{
		global $vbulletin;
		if (!isset($this->user_data[$user->get_field('userid')]['subscribed']))
		{
			$subscribed = $vbulletin->db->query_first_slave("
				SELECT 1 
				FROM " . TABLE_PREFIX . "subscribeevent 
				WHERE eventid = " . intval($this->get_field('eventid')) . " AND
					userid = " . intval($user->get_field('userid'))
			);

			$this->user_data[$user->get_field('userid')]['subscribed'] = (bool) $subscribed;
		}

		return $this->user_data[$user->get_field('userid')]['subscribed'];
	}

	//*********************************************************************************
	//	High level permissions
	public function can_view($user)
	{
		if (!$user->hasCalendarPermission($this->get_field('calendarid'), 'canviewcalendar'))
		{
			return false;
		}

		//usually this is qualified with other permissions (is mod, can I view my own 
		//deleted stuff) but I'm just not seeing it for events.  Might have missed 
		//something.
		if (!$this->get_field('visible'))
		{
			return false;
		}

		//I'm not entirely sure why we have this permission.  Perhaps so that a single calendar 
		//can serve as a private calendar for all users?  
		if (
			($user->get_field('userid') != $this->get_field('userid')) AND 
			!$user->hasCalendarPermission($this->get_field('calendarid'), 'canviewothersevent')
		)
		{
			return false;
		}

		return true;
	}
	

	public function can_search($user)
	{
		return $this->can_view($user);
	}


	public function can_edit($user)
	{
		if (can_moderate_calendar($this->get_field('calendarid'), 'caneditevents'))
		{
			return true;
		}

		if ($this->get_field('userid') == $user->get_field('userid'))
		{
			return true;
		}

		if ($user->hasCalendarPermission($this->get_field('calendarid'), 'caneditevent'))
		{
			return true;
		}

		return false;
	}

	public function can_subscribe($user)
	{
		if ($user->isGuest())
		{
			return false;
		}
		
		if ($this->get_field('singleday'))
		{
			return (TIMENOW <= $this->get_field('dateline_from'));
		}
		else
		{
			return (TIMENOW <= $this->get_field('dateline_to'));
		}
	}


	private $user_data;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/
