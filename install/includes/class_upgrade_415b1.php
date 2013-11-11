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

class vB_Upgrade_415b1 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '415b1';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '4.1.5 Beta 1';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '4.1.4';

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
	* Step #1 - Add api post log table
	*
	*/
	function step_1()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'apipost'),
			"CREATE TABLE " . TABLE_PREFIX . "apipost (
			  apipostid INT UNSIGNED NOT NULL AUTO_INCREMENT,
			  userid INT UNSIGNED NOT NULL DEFAULT '0',
			  contenttypeid INT UNSIGNED NOT NULL DEFAULT '0',
			  contentid INT UNSIGNED NOT NULL DEFAULT '0',
			  clientname VARCHAR(250) NOT NULL DEFAULT '',
			  clientversion VARCHAR(50) NOT NULL DEFAULT '',
			  platformname VARCHAR(250) NOT NULL DEFAULT '',
			  platformversion VARCHAR(50) NOT NULL DEFAULT '',
			  PRIMARY KEY (apipostid),
			  KEY contenttypeid (contenttypeid, contentid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}
	
	/**
	* Step #2 - VBIV-7754, increase field size
	*
	*/
	function step_2()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'searchlog', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "searchlog CHANGE criteria criteria MEDIUMTEXT NOT NULL"
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
