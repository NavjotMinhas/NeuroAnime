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

class vB_Upgrade_400rc1 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '400rc1';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '4.0.0 Release Candidate 1';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '4.0.0 Beta 5';

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
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'block'),
			"CREATE TABLE " . TABLE_PREFIX . "block (
				blockid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				blocktypeid INT NOT NULL DEFAULT '0',
				title VARCHAR(255) NOT NULL DEFAULT '',
				description MEDIUMTEXT,
				url VARCHAR(100) NOT NULL DEFAULT '',
				cachettl INT NOT NULL DEFAULT '0',
				displayorder SMALLINT NOT NULL DEFAULT '0',
				active SMALLINT NOT NULL DEFAULT '0',
				configcache MEDIUMBLOB,
				PRIMARY KEY (blockid),
				KEY blocktypeid (blocktypeid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #2
	*
	*/
	function step_2()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'blockconfig'),
			"CREATE TABLE " . TABLE_PREFIX . "blockconfig (
				blockid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				name VARCHAR(255) NOT NULL DEFAULT '',
				value MEDIUMTEXT,
				serialized TINYINT NOT NULL DEFAULT '0',
				PRIMARY KEY (blockid, name)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #3
	*
	*/
	function step_3()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'blocktype'),
			"CREATE TABLE " . TABLE_PREFIX . "blocktype (
				blocktypeid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				productid VARCHAR(25) NOT NULL DEFAULT '',
				name VARCHAR(50) NOT NULL DEFAULT '',
				title VARCHAR(255) NOT NULL DEFAULT '',
				description MEDIUMTEXT,
				allowcache TINYINT NOT NULL DEFAULT '0',
				PRIMARY KEY (blocktypeid),
				UNIQUE KEY (name),
				KEY productid (productid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #4 - New phrase types
	*
	*/
	function step_4()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'language', 1, 1),
			'language',
			'phrasegroup_vbblock',
			'mediumtext',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #5
	*
	*/
	function step_5()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'language', 1, 1),
			'language',
			'phrasegroup_vbblocksettings',
			'mediumtext',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #6
	*
	*/
	function step_6()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "phrasetype"),
			"INSERT IGNORE INTO " . TABLE_PREFIX . "phrasetype
				(title, editrows, fieldname, special)
			VALUES
				('{$this->phrase['phrasetype']['vbblock']}', 3, 'vbblock', 0),
				('{$this->phrase['phrasetype']['vbblocksettings']}', 3, 'vbblocksettings', 0)
			"
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
