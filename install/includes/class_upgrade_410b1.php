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

class vB_Upgrade_410b1 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '410b1';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '4.1.0 Beta 1';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '4.0.8';

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

	function step_1()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'apiclient'),
			"CREATE TABLE " . TABLE_PREFIX . "apiclient (
				apiclientid INT UNSIGNED NOT NULL auto_increment,
				secret VARCHAR(32) NOT NULL DEFAULT '',
				apiaccesstoken VARCHAR(32) NOT NULL DEFAULT '',
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				clienthash VARCHAR(32) NOT NULL DEFAULT '',
				clientname VARCHAR(250) NOT NULL DEFAULT '',
				clientversion VARCHAR(50) NOT NULL DEFAULT '',
				platformname VARCHAR(250) NOT NULL DEFAULT '',
				platformversion VARCHAR(50) NOT NULL DEFAULT '',
				uniqueid VARCHAR(250) NOT NULL DEFAULT '',
				initialipaddress VARCHAR(15) NOT NULL DEFAULT '',
				dateline INT UNSIGNED NOT NULL,
				lastactivity INT UNSIGNED NOT NULL,
				PRIMARY KEY  (apiclientid),
				KEY clienthash (uniqueid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);

	}

	function step_2()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'session', 1, 3),
			'session',
			'apiclientid',
			'INT',
			self::FIELD_DEFAULTS
		);
	}

	function step_3()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'session', 2, 3),
			'session',
			'apiaccesstoken',
			'VARCHAR',
			array('length' => 32, 'attributes' => self::FIELD_DEFAULTS)
		);
	}

	function step_4()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'session', 3, 3),
			'session',
			'apiaccesstoken',
			'apiaccesstoken'
		);
	}

	function step_5()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'apilog'),
			"CREATE TABLE " . TABLE_PREFIX . "apilog (
				apilogid INT UNSIGNED NOT NULL auto_increment,
				apiclientid INT UNSIGNED NOT NULL DEFAULT '0',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				method VARCHAR(32) NOT NULL DEFAULT '',
				paramget MEDIUMTEXT,
				parampost MEDIUMTEXT,
				ipaddress VARCHAR(15) NOT NULL DEFAULT '',
				PRIMARY KEY  (apilogid),
				KEY apiclientid (apiclientid, method, dateline)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
