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

class vB_Upgrade_370rc1 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '370rc1';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '3.7.0 Release Candidate 1';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '3.7.0 Beta 6';

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
		if (!isset($this->registry->bf_misc_useroptions['vm_enable']))
		{
			$this->add_error($this->phrase['core']['wrong_bitfield_xml'], self::PHP_TRIGGER_ERROR, true);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #2
	*
	*/
	function step_2()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'bookmarksite', 1, 1),
			"UPDATE " . TABLE_PREFIX . "bookmarksite
			SET url = 'http://www.google.com/bookmarks/mark?op=edit&amp;output=popup&amp;bkmk={URL}&amp;title={TITLE}'
			WHERE url = 'http://www.google.com/bookmarks/mark?op=edit&amp;output=popup&amp;bkmk={URL}&amp;annotation={TITLE}' AND title = 'Google'
			"
		);
	}

	/**
	* Step #3
	*
	*/
	function step_3()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'user', 1, 1),
			'user',
			'referrerid',
			array('referrerid')
		);

		require_once(DIR . '/includes/adminfunctions_bookmarksite.php');
		build_bookmarksite_datastore();
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
