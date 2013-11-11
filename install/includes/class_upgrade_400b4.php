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

class vB_Upgrade_400b4 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '400b4';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '4.0.0 Beta 4';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '4.0.0 Beta 3';

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
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "ad"),
			"CREATE TABLE " . TABLE_PREFIX . "ad (
				adid INT UNSIGNED NOT NULL auto_increment,
				title VARCHAR(250) NOT NULL DEFAULT '',
				adlocation VARCHAR(250) NOT NULL DEFAULT '',
				displayorder INT UNSIGNED NOT NULL DEFAULT '0',
				active SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				snippet MEDIUMTEXT,
				PRIMARY KEY (adid),
				KEY active (active)
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
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "adcriteria"),
			"CREATE TABLE " . TABLE_PREFIX . "adcriteria (
				adid INT UNSIGNED NOT NULL DEFAULT '0',
				criteriaid VARCHAR(250) NOT NULL DEFAULT '',
				condition1 VARCHAR(250) NOT NULL DEFAULT '',
				condition2 VARCHAR(250) NOT NULL DEFAULT '',
				condition3 VARCHAR(250) NOT NULL DEFAULT '',
				PRIMARY KEY (adid,criteriaid)
			)
			",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #3
	*
	*/
	function step_3()
	{
		if (!$this->field_exists('language', 'phrasegroup_advertising'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "advertising"),
				"ALTER TABLE " . TABLE_PREFIX . "language ADD phrasegroup_advertising mediumtext not null"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #4
	*
	*/
	function step_4()
	{
		if (!$this->db->query_first("SELECT * FROM " . TABLE_PREFIX . "phrasetype WHERE fieldname = 'advertising'"))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "phrasetype"),
				"INSERT INTO " . TABLE_PREFIX . "phrasetype
				VALUES
					('advertising', 'Advertising', 3, '', 0)
				"
			);
		}
		else
		{
			$this->skip_message();
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
