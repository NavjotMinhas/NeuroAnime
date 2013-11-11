<?php
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

if (!class_exists('vB_DataManager', false))
{
	exit;
}

/**
* Class to do data save/delete operations for EVENTS
*
*
* @package	vBulletin
* @version	$Revision: 35376 $
* @date		$Date: 2010-02-09 12:29:22 -0800 (Tue, 09 Feb 2010) $
*/
class vB_DataManager_Event extends vB_DataManager
{
	/**
	* Array of recognized and required fields for events
	*
	* @var	array
	*/
	var $validfields = array(
		'eventid'       => array(TYPE_UINT,      REQ_INCR, 'return ($data > 0);'),
		'userid'        => array(TYPE_UINT,      REQ_YES,  VF_METHOD),
		'event'         => array(TYPE_STR,       REQ_YES,  VF_METHOD),
		'title'         => array(TYPE_STR,       REQ_YES,  VF_METHOD),
		'allowsmilies'  => array(TYPE_UINT,      REQ_YES),
		'recurring'     => array(TYPE_UINT,      REQ_YES),
		'recuroption'   => array(TYPE_STR,       REQ_YES),
		'calendarid'    => array(TYPE_UINT,      REQ_YES,  VF_METHOD),
		'customfields'  => array(TYPE_ARRAY_STR, REQ_NO,   VF_METHOD, 'verify_serialized'),
		'visible'       => array(TYPE_UINT,      REQ_NO),
		'utc'           => array(TYPE_NUM,       REQ_YES,  VF_METHOD),
		'dst'           => array(TYPE_UINT,      REQ_NO),
		'dateline'      => array(TYPE_UNIXTIME,  REQ_AUTO),
		'dateline_from' => array(TYPE_UINT,      REQ_YES),
		'dateline_to'   => array(TYPE_UINT,      REQ_YES),
	);

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'event';

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('eventid = %1$d', 'eventid');

	/**
	* Condition for verifying date. Set to false if you wish to update data without verifying if the date/time is valid
	*
	* @var bool
	*/
	var $verify_datetime = true;

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Event(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('eventdata_start')) ? eval($hook) : false;
	}

	/**
	* Verifies that the specified calendar exists
	*
	* @param	integer	Calendar ID
	*
	* @return 	boolean	Returns true if calendar exists
	*/
	function verify_calendarid(&$calendarid)
	{
		if ($this->dbobject->query_first("SELECT calendarid FROM " . TABLE_PREFIX . "calendar WHERE calendarid = $calendarid"))
		{
			return true;
		}
		else
		{
			$this->error('no_calendars_matched_your_query');
			return false;
		}

	}

	/**
	* Verifies that the title is valid
	*
	* @param	String	Title
	*
	* @return 	boolean	Returns true if title is valid
	*/
	function verify_title(&$title)
	{

		$title = fetch_censored_text($title);

		// replace html-encoded spaces with actual spaces
		$title = preg_replace('/&#(0*32|x0*20);/', ' ', $title);

		// do word wrapping
		if ($this->registry->options['wordwrap'] != 0)
		{
			$title = fetch_word_wrapped_string($title);
		}

		// remove all caps subjects
		require_once(DIR . '/includes/functions_newpost.php');
		$title = fetch_no_shouting_text($title);

		$title = trim($title);

		if (empty($title))
		{
			$this->error('invalid_title_specified');
			return false;
		}

		return true;
	}

	/**
	* Verifies that a valid timezoneoffset has been specified
	*
	* @param	float	Event timezoneoffset
	*
	* @return	boolean	Returns true if the timezone is valid
	*/
	function verify_utc(&$timezoneoffset)
	{
		require_once(DIR . '/includes/functions_misc.php');
		if (fetch_timezone($timezoneoffset))
		{
			return true;
		}
		else
		{
			$this->error('invalid_timezone_specified');
			return false;
		}
	}

	/**
	* Verifies the page text is valid and sets it up for saving.
	*
	* @param	string	Page text
	*
	* @param	bool	Whether the text is valid
	*/
	function verify_event(&$pagetext)
	{
		if ($this->registry->options['postmaxchars'] != 0 AND ($postlength = vbstrlen($pagetext)) > $this->registry->options['postmaxchars'])
		{
			$this->error('toolong', $postlength, $this->registry->options['postmaxchars']);
			return false;
		}

		return $this->verify_pagetext($pagetext);
	}

	/**
	* Selected values for custom fields defined for the calendar that contains this event
	*
	* @param	array	Customfield data from $_POST
	*/
	function set_userfields(&$userfields)
	{
		if (!($calendarid = $this->fetch_field('calendarid')))
		{
			trigger_error('Calendarid must be set before userfields.', E_USER_ERROR);
		}

		$customcalfields = $this->dbobject->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "calendarcustomfield
			WHERE calendarid = $calendarid
			ORDER BY calendarcustomfieldid
		");
		$customfields = array();

		while ($custom = $this->dbobject->fetch_array($customcalfields))
		{
			$customfield =& $userfields["f$custom[calendarcustomfieldid]"];
			$optional = vbchop($userfields["o$custom[calendarcustomfieldid]"], $custom['length'] ? $custom['length'] : 255);

			if ($custom['allowentry'] AND !empty($optional))
			{
				$option =& $optional;
			}
			else
			{
				$option =& $customfield;
			}

			if ($custom['required'] AND !$option)
			{
				$this->error('requiredfieldmissing', $custom['title']);
				return false;
			}

			$custom['options'] = unserialize($custom['options']);
			unset($chosenoption);
			if (is_array($custom['options']))
			{
				foreach ($custom['options'] AS $index => $value)
				{
					if ($index == $option)
					{
						$chosenoption = $value;
						break;
					}
				}
			}
			if ($chosenoption == '' AND $custom['allowentry'])
			{
				$chosenoption = htmlspecialchars_uni($optional);
			}
			$customfields["{$custom['calendarcustomfieldid']}"] = $chosenoption;
		}

		$this->set('customfields', $customfields);
	}

	/**
	* Any checks to run immediately before saving. If returning false, the save will not take place.
	*
	* @param	boolean	Do the query?
	*
	* @return	boolean	True on success; false if an error occurred
	*/
	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if (!$this->verify_image_count('event', 'allowsmilies', 'calendar'))
		{
			return false;
		}

		if ($this->verify_datetime)
		{
			if (!checkdate($this->info['fromdate']['month'], $this->info['fromdate']['day'], $this->info['fromdate']['year'])
				OR ($this->info['type'] != 'single' AND !checkdate($this->info['todate']['month'], $this->info['todate']['day'], $this->info['todate']['year'])))
			{
				$this->error('calendarbaddate');
				return false;
			}

			if ($this->info['type'] != 'single')
			{
				// extract the relevant info from from_time and to_time

				$time_re = '#^(0?[1-9]|1[012])\s*[:.]\s*([0-5]\d)(\s*[AP]M)?|([01]\d|2[0-3])\s*[:.]\s*([0-5]\d)$#i';

				// match text in field for a valid time
				if (preg_match($time_re, $this->info['fromtime'], $matches))
				{
					if (count($matches) == 3)
					{
						$from_hour = intval($matches[1]);
						$from_minute = intval($matches[2]);
						$from_ampm = $matches[1] == '12' ? 'PM' : 'AM';
					}
					else if (count($matches) == 4)
					{
						$from_hour = intval($matches[1]);
						$from_minute = intval($matches[2]);
						$from_ampm = strtoupper(trim($matches[3]));
					}
					else // 24hr time
					{
						$from_hour = intval($matches[4]);
						$from_minute = intval($matches[5]);
						$from_ampm = ($from_hour <= 11) ? 'AM' : 'PM';
					}
				}
				else
				{
					$this->error('calendarbadtime');
					return false;
				}

				// preg match text in field for a valid time
				if (preg_match($time_re, $this->info['totime'], $matches))
				{
					if (count($matches) == 3)
					{
						$to_hour = intval($matches[1]);
						$to_minute = intval($matches[2]);
						$to_ampm = $matches[1] == '12' ? 'PM' : 'AM';
					}
					else if (count($matches) == 4)
					{
						$to_hour = intval($matches[1]);
						$to_minute = intval($matches[2]);
						$to_ampm = strtoupper(trim($matches[3]));
					}
					else // 24hr time
					{
						$to_hour = intval($matches[4]);
						$to_minute = intval($matches[5]);
						$to_ampm = ($to_hour <= 11) ? 'AM' : 'PM';
					}
				}
				else
				{
					$this->error('calendarbadtime');
					return false;
				}

				if (($pos = strpos($this->registry->options['timeformat'], 'H')) === false)
				{
					if ($to_ampm == 'PM')
					{
						if ($to_hour >= 1 AND $to_hour <= 11)
						{
							$to_hour += 12;
						}
					}
					else
					{
						if ($to_hour == 12)
						{
							$to_hour = 0;
						}
					}

					if ($from_ampm == 'PM')
					{
						if ($from_hour >= 1 AND $from_hour <= 11)
						{
							$from_hour += 12;
						}
					}
					else
					{
						if ($from_hour == 12)
						{
							$from_hour = 0;
						}
					}
				}

				$min_offset = $this->fetch_field('utc') - intval($this->fetch_field('utc'));

				$from_hour   -= intval($this->fetch_field('utc'));
				$from_minute -= intval($min_offset * 60);

				$to_hour   -= intval($this->fetch_field('utc'));
				$to_minute -= intval($min_offset * 60);

				$dateline_to = gmmktime($to_hour, $to_minute, 0, $this->info['todate']['month'], $this->info['todate']['day'], $this->info['todate']['year']);
				$dateline_from = gmmktime($from_hour, $from_minute, 0, $this->info['fromdate']['month'], $this->info['fromdate']['day'], $this->info['fromdate']['year']);

				if ($dateline_to < $dateline_from)
				{
					$this->error('calendartodate');
					return false;
				}

				require_once(DIR . '/includes/functions_misc.php');
				$this->set_info('occurdate', vbgmdate('Y-n-j', $dateline_from + $this->registry->userinfo['timezoneoffset'] * 3600, false, false));
			}
			else // single day event
			{
				$dateline_to = 0;
				$dateline_from = gmmktime(0, 0, 0, $this->info['fromdate']['month'], $this->info['fromdate']['day'], $this->info['fromdate']['year']);

				require_once(DIR . '/includes/functions_misc.php');
				$this->set_info('occurdate', $occurdate = vbgmdate('Y-n-j', $dateline_from, false, false));
			}

			$this->set('dateline_to', $dateline_to);
			$this->set('dateline_from', $dateline_from);

			$recuroption = '';

			if ($this->info['type'] == 'recur')
			{
				$checkevent = array(
					'eventid'            => 1,
					'dateline_from'      => $dateline_from,
					'dateline_to'        => $dateline_to,
					'dateline_from_user' => $dateline_from + $this->registry->userinfo['timezoneoffset'] * 3600,
					'dateline_to_user'   => $dateline_to + $this->registry->userinfo['timezoneoffset'] * 3600,
					'recurring'          => $this->fetch_field('recurring'),
					'utc'                => $this->fetch_field('utc'),
				);

				$startday = gmmktime(0, 0, 0, gmdate('n', $checkevent['dateline_from_user']), gmdate('j', $checkevent['dateline_from_user']), gmdate('Y', $checkevent['dateline_from_user']));
				$endday = gmmktime(0, 0, 0, gmdate('n', $checkevent['dateline_to_user']), gmdate('j', $checkevent['dateline_to_user']), gmdate('Y', $checkevent['dateline_to_user']));

				if ($this->info['recur']['pattern'] == 1)
				{
					$recuroption = $this->info['recur']['dailybox'];
				}
				else if ($this->info['recur']['pattern'] == 3)
				{
					if ($this->info['recur']['weeklysun'])
					{
						$daybit = 1;
					}
					if ($this->info['recur']['weeklymon'])
					{
						$daybit += 2;
					}
					if ($this->info['recur']['weeklytue'])
					{
						$daybit += 4;
					}
					if ($this->info['recur']['weeklywed'])
					{
						$daybit += 8;
					}
					if ($this->info['recur']['weeklythu'])
					{
						$daybit += 16;
					}
					if ($this->info['recur']['weeklyfri'])
					{
						$daybit += 32;
					}
					if ($this->info['recur']['weeklysat'])
					{
						$daybit += 64;
					}
					$recuroption = $this->info['recur']['weeklybox'] . '|' . $daybit;
				}
				else if ($this->info['recur']['pattern'] == 4)
				{
					$recuroption = $this->info['recur']['monthly1'] . '|' . $this->info['recur']['monthlybox1'];
				}
				else if ($this->info['recur']['pattern'] == 5)
				{
					$recuroption = $this->info['recur']['monthly2'] . '|' . $this->info['recur']['monthly3'] . '|' . $this->info['recur']['monthlybox2'];
				}
				else if ($this->info['recur']['pattern'] == 6)
				{
					$recuroption = $this->info['recur']['yearly1'] . '|' . $this->info['recur']['yearly2'];
				}
				else if ($this->info['recur']['pattern'] == 7)
				{
					$recuroption = $this->info['recur']['yearly3'] . '|' . $this->info['recur']['yearly4'] . '|' . $this->info['recur']['yearly5'];
				}
				$checkevent['recuroption'] = $recuroption;
				$foundevent = false;
				while ($startday <= $endday)
				{
					$temp = explode('-', gmdate('n-j-Y', $startday));
					if (cache_event_info($checkevent, $temp[0], $temp[1], $temp[2], 0))
					{
						$foundevent = true;
						break;
					}
					$startday += 86400;
				}
				if (!$foundevent)
				{
					$this->error('calendarnorecur');
					return false;
				}
			}

			$this->set('recuroption', $recuroption);

			if ($this->condition === null) # Insert
			{
				if ($query = $this->dbobject->query_first("
					SELECT eventid
					FROM " . TABLE_PREFIX . "event
					WHERE userid = " . intval($this->fetch_field('userid')) . "
						AND dateline_from = " . intval($this->fetch_field('dateline_from')) . "
						AND dateline_to = " . intval($this->fetch_field('dateline_to')) . "
						AND event = '" . $this->dbobject->escape_string($this->fetch_field('event')) . "'
						AND title = '" . $this->dbobject->escape_string($this->fetch_field('title')) . "'
						AND calendarid = " . intval($this->fetch_field('calendarid')) . "
				"))
				{
					$this->error('calendareventexists');
					return false;
				}
				if (!$this->fetch_field('dateline'))
				{
					$this->set('dateline', TIMENOW);
				}
			}
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('eventdata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	* In batch updates, is executed for each record updated.
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each($doquery = true)
	{

		if ($this->condition AND ($this->fetch_field('dateline_from') - $this->existing['dateline_from']) >= 82800)
		{
			// Start date has been pushed up at least 23 hours (this is a bit arbitrary) so reset any reminders that have already been sent
			$this->dbobject->query_write("
				UPDATE " . TABLE_PREFIX . "subscribeevent
				SET lastreminder = 0
				WHERE eventid = " . intval($this->fetch_field('eventid')) . "
			");
		}

		($hook = vBulletinHook::fetch_hook('eventdata_postsave')) ? eval($hook) : false;

		return true;
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	* In batch updates, is executed once after all records are updated.
	*
	* @param	boolean	Do the query?
	*/
	function post_save_once($doquery = true)
	{
		require_once(DIR . '/includes/functions_calendar.php');
		build_events();

		return parent::post_save_once($doquery);
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{

		$this->dbobject->query_write("DELETE FROM " . TABLE_PREFIX . "subscribeevent WHERE eventid = " . intval($this->fetch_field('eventid')));

		require_once(DIR . '/includes/functions_calendar.php');
		build_events();

		($hook = vBulletinHook::fetch_hook('eventdata_delete')) ? eval($hook) : false;

		return true;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35376 $
|| ####################################################################
\*======================================================================*/
?>
