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

class vB_Upgrade_370b5 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '370b5';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '3.7.0 Beta 5';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '3.7.0 Beta 4';

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
			sprintf($this->phrase['core']['altering_x_table'], 'socialgroup', 1, 4),
			'socialgroup',
			'visible',
			'visible'
		);
	}

	/**
	* Step #2
	*
	*/
	function step_2()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'socialgroup', 2, 4),
			'socialgroup',
			'picturecount',
			'picturecount'
		);
	}

	/**
	* Step #3
	*
	*/
	function step_3()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'socialgroup', 3, 4),
			'socialgroup',
			'members',
			'members'
		);
	}

	/**
	* Step #4
	*
	*/
	function step_4()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'socialgroup', 4, 4),
			'socialgroup',
			'lastpost',
			'lastpost'
		);
	}

	/**
	* Step #5
	*
	*/
	function step_5()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'template', 1, 2),
			"UPDATE IGNORE " . TABLE_PREFIX . "template SET title = '.inlinemod' WHERE title = 'td.inlinemod'"
		);
	}

	/**
	* Step #6
	*
	*/
	function step_6()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'template', 2, 2),
			"DELETE FROM " . TABLE_PREFIX . "template WHERE title = 'td.inlinemod'"
		);
	}

	/**
	* Step #7
	*
	*/
	function step_7()
	{
		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'pmreceipt', 1, 2),
			'pmreceipt',
			'userid'
		);
	}

	/**
	* Step #8
	*
	*/
	function step_8()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'pmreceipt', 2, 2),
			'pmreceipt',
			'userid',
			array('userid', 'readtime')
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
