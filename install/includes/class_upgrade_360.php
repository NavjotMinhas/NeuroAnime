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

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

class vB_Upgrade_360 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '360';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '3.6.0';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '3.6.0 Release Candidate 3';

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
		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'reputation', 1, 2),
			'reputation',
			'whoadded'
		);
	}

	/**
	* Step #2
	*
	*/
	function step_2()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'reputation', 2, 2),
			"ALTER IGNORE TABLE " . TABLE_PREFIX . "reputation ADD UNIQUE INDEX
				whoadded_postid (whoadded, postid)",
			self::MYSQL_ERROR_KEY_EXISTS
		);
	}

	/**
	* Step #3
	*
	*/
	function step_3()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'useractivation', 1, 1),
			'useractivation',
			'emailchange',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
