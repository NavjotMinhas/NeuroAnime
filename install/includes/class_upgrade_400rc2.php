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
/*
if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}
*/

class vB_Upgrade_400rc2 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '400rc2';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '4.0.0 Release Candidate 2';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '4.0.0 Release Candidate 1';

	/**
	* Beginning version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_STARTS = '';

	/**
	* Ending version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_ENDS   = '';

	/**
	* Step #1
	*
	*/
	function step_1()
	{
		$this->add_index(
			sprintf($this->phrase['core']['create_index_x_on_y'], 'user_activity', TABLE_PREFIX . 'session'),
			'session',
			'user_activity',
			array('userid', 'lastactivity')
		);
	}

	/**
	* Step #2
	*
	*/
	function step_2()
	{
		$this->add_index(
			sprintf($this->phrase['core']['create_index_x_on_y'], 'guest_lookup', TABLE_PREFIX . 'session'),
			'session',
			'guest_lookup',
			array('idhash', 'host', 'userid')
		);
	}

	/**
	* Step #3
	*
	*/
	function step_3()
	{
		$this->add_index(
			sprintf($this->phrase['core']['create_index_x_on_y'], 'styleid', TABLE_PREFIX . 'template'),
			'template',
			'styleid',
			array('styleid')
		);
	}

	/**
	* Step #4
	*
	*/
	function step_4()
	{
		$profile_field_category_locations = array(
			'profile_left_first'  => 'profile_tabs_first',
			'profile_left_last'   => 'profile_tabs_last',
			'profile_right_first' => 'profile_sidebar_first',
			'profile_right_mini'  => 'profile_sidebar_stats',
			'profile_right_album' => 'profile_sidebar_albums',
			'profile_right_last'  => 'profile_sidebar_last',
		);

		foreach ($profile_field_category_locations AS $old_category_location => $new_category_location)
		{
			$this->run_query(
				$this->phrase['version']['400rc2']['updating_profile_field_category_data'],
				"UPDATE " . TABLE_PREFIX . "profilefieldcategory
					SET location = '$new_category_location'
					WHERE location = '$old_category_location'"
			);
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
