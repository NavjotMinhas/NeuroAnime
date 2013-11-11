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
require_once (DIR . '/vb/legacy/event.php');
require_once (DIR . '/includes/functions_calendar.php');
require_once (DIR . '/includes/functions_user.php');

/**
 * @package vBulletin
 * @subpackage Search
 */
class vBForum_Search_Result_Event extends vB_Search_Result
{
	public static function create($id)
	{
		return self::create_from_object(vB_Legacy_Event::create_from_id($id));
	}

	public static function create_array($ids)
	{
		$events = array();
		foreach (vB_Legacy_Event::create_array($ids) as $item)
		{
			$events[$item->get_field('eventid')] = self::create_from_object($item);
		}
		return $events;
	}

	public static function create_from_object($object)
	{
		if ($object)
		{
			$item = new vBForum_Search_Result_Event();
			$item->event = $object;
			return $item;
		}
		{
			return new vB_Search_Result_Null();
		}
	}

	protected function __construct() {}

	public function get_contenttype()
	{
		return vB_Search_Core::get_instance()->get_contenttypeid('vBForum', 'Event');
	}

	public function can_search($user)
	{
		return $this->event->can_search($user);
	}

	public function render($current_user, $criteria, $template = '')
	{
		//ensure that the phrases used by events are in the phrase list.
	 	$phrase = new vB_Legacy_Phrase();
	 	$phrase->add_phrase_groups(array('calendar', 'holiday', 'timezone', 'posting', 'user'));

		global $show, $bgclass;

		//a bunch of crap set by fetch_event_date_time that we send to the template
		global $date1, $date2, $time1, $time2, $recurcriteria, $eventdate;

		//a bunch of crap set by fetch_event_date_time that I can't find any use of.
		//leaving it in for now in case I missed some use of globals.
		global $titlecolor, $allday;

		$eventinfo = $this->event->get_record();

		//event['dst'] means "don't adjust event time for dst"
		$offset = $current_user->getTimezoneOffset(!$eventinfo['dst']);
		$eventinfo['dateline_from_user'] = $eventinfo['dateline_from'] + $offset * 3600;
		$eventinfo['dateline_to_user'] = $eventinfo['dateline_to'] + $offset * 3600;

		$eventinfo = fetch_event_date_time($eventinfo);
		fetch_musername($eventinfo);

		if (!$eventinfo['holidayid'])
		{
			$bgclass = 'alt2';

			//Custom fields are a little weird.  By default we don't show a blank value, but
			//the conditional is in the template so its possible that the user by change that.
			//We don't show the text block unless we have at least one set field, but again
			//the user can change that.  I'm not sure why somebody would want to do these
			//things but its not worth altering the behavior.  It does mean that we can't
			//rely on either field_text being blank when there are no set fields
			$custom_fields = $this->event->get_custom_fields();
			$custom_field_text = $this->render_custom_fields($custom_fields);
			$show['customfields'] = $this->have_set_customfield($custom_fields);

			$show['holiday'] = false;
			$show['caneditevent'] = $this->event->can_edit($current_user);
			$show['subscribed'] = !empty($eventinfo['subscribed']) ? true : false;
			$show['subscribelink'] = ($eventinfo['subscribed'] OR $this->event->can_subscribe($current_user));
		}
		else
		{
			$custom_field_text = '';
			$show['holiday'] = true;
			$show['caneditevent'] = false;
			$show['subscribed'] = false;
			$show['subscribelink'] = false;
		}

		exec_switch_bg();
		if (!$eventinfo['singleday'] AND
			gmdate('w', $eventinfo['dateline_from_user']) !=
				gmdate('w', $eventinfo['dateline_from'] + ($eventinfo['utc'] * 3600))
		)
		{
			$show['adjustedday'] = true;
			$eventinfo['timezone'] = str_replace('&nbsp;', ' ', $vbphrase[fetch_timezone($eventinfo['utc'])]);
		}
		else
		{
			$show['adjustedday'] = false;
		}

		$show['ignoredst'] = ($eventinfo['dst'] AND !$eventinfo['singleday']) ? true : false;
		$show['postedby'] = !empty($eventinfo['userid']) ? true : false;
		$show['singleday'] = !empty($eventinfo['singleday']) ? true : false;
		if (($show['candeleteevent'] OR $show['canmoveevent'] OR $show['caneditevent']) AND !$show['holiday'])
		{
			$show['eventoptions'] = true;
		}

		$eventinfo = array_merge($eventinfo, convert_bits_to_array($eventinfo['options'], vB::$vbulletin->bf_misc_useroptions));
		$eventinfo = array_merge($eventinfo, convert_bits_to_array($eventinfo['adminoptions'], vB::$vbulletin->bf_misc_adminoptions));
		cache_permissions($eventinfo, false);

		//we already have the avatar info, no need to refetch.
		fetch_avatar_from_userinfo($eventinfo);

		// prepare the member action drop-down menu
		$memberaction_dropdown = construct_memberaction_dropdown($eventinfo);		
		
		$calendarinfo = $this->event->get_calendar()->get_record();

		($hook = vBulletinHook::fetch_hook('calendar_getday_event')) ? eval($hook) : false;

		//some globals registered for the template's sake
		global $gobutton, $spacer_open, $spacer_close;
		$templater = vB_Template::create('calendar_showeventsbit');
		$templater->register('calendarinfo', $calendarinfo);
		$templater->register('customfields', $custom_field_text);
		$templater->register('date1', $date1);
		$templater->register('date2', $date2);
		$templater->register('time1', $time1);
		$templater->register('time2', $time2);
		$templater->register('eventdate', $eventdate);
		$templater->register('eventinfo', $eventinfo);
		$templater->register('gobutton', $gobutton);
		$templater->register('memberaction_dropdown', $memberaction_dropdown);		
		$templater->register('recurcriteria', $recurcriteria);
		$templater->register('spacer_close', $spacer_close);
		$templater->register('spacer_open', $spacer_open);

		return $templater->render();
	}

	private function have_set_customfield($custom_fields)
	{
		foreach ($custom_fields as $field)
		{
			if ($field['value'] != '')
			{
				return true;
			}
		}

		return false;
	}

	private function render_custom_fields($custom_fields)
	{
		global $show;

		$customfields = '';
		foreach ($custom_fields as $field)
		{
			$show['customoption'] = ($field['value'] == '') ? false : true;
			exec_switch_bg();

			$templater = vB_Template::create('calendar_showeventsbit_customfield');
			$templater->register('customoption', $field['value']);
			$templater->register('customtitle', $field['title']);

			$customfields .= $templater->render();
		}
		return $customfields;
	}


	/*** Returns the primary id. Allows us to cache a result item.
	 *
	 * @result	integer
	 ***/
	public function get_id()
	{
		if (isset($this->event) AND ($eventid = $this->event->get_field('eventid')))
		{
			return $eventid;
		}
		return false;
	}

	private static $calender_info_cache = array();
	private $event;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/