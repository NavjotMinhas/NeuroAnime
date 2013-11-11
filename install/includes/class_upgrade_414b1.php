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

class vB_Upgrade_414b1 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '414b1';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '4.1.4 Beta 1';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '4.1.3';

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
	* Step #1 - Add phrasegroup to language table
	*
	*/
	function step_1()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'language', 1, 2),
			'language',
			'phrasegroup_ckeditor',
			'mediumtext',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #2 - Add phrasegroupinfo to language
	*
	*/
	function step_2()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'language', 2, 2),
			'language',
			'phrasegroupinfo',
			'mediumtext',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #3
	*
	*/
	function step_3()
	{
		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'phrase', 1, 2),
			'phrase',
			'languageid'
		);
	}

	/**
	* Step #4
	*
	*/
	function step_4()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'phrase', 2, 2),
			'phrase',
			'languageid',
			array('languageid', 'fieldname', 'dateline')
		);
	}

	/**
	* Step #5 - Add phrasetype for CKEditor phrases
	*
	*/
	function step_5()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "phrasetype"),
			"INSERT IGNORE INTO " . TABLE_PREFIX . "phrasetype
				(title, editrows, fieldname, special)
			VALUES
				('" . $this->db->escape_string($this->phrase['phrasetype']['ckeditor']) . "', 3, 'ckeditor', 0)
			"
		);
	}	

	/**
	* Step #6 - Add autosave table
	*
	*/
	function step_6()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'autosave'),
			"CREATE TABLE " . TABLE_PREFIX . "autosave (
				contenttypeid VARBINARY(100) NOT NULL DEFAULT '',
				parentcontentid INT UNSIGNED NOT NULL DEFAULT '0',
				contentid INT UNSIGNED NOT NULL DEFAULT '0',
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				pagetext MEDIUMTEXT,
				title MEDIUMTEXT,
				posthash CHAR(32) NOT NULL DEFAULT '',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (contentid, parentcontentid, contenttypeid, userid),
				KEY userid (userid),
				KEY contenttypeid (contenttypeid, userid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #7 - Add New Contenttypes
	*
	*/
	function step_7()
	{
		$this->add_contenttype('vbulletin', 'vBForum', 'PrivateMessage');
	}

	/**
	* Step #8 - Add New Contenttypes
	*
	*/
	function step_8()
	{
		$this->add_contenttype('vbulletin', 'vBForum', 'Infraction');
	}

	/**
	* Step #9 - Add New Contenttypes
	*
	*/
	function step_9()
	{
		$this->add_contenttype('vbulletin', 'vBForum', 'Signature');
	}

	/**
	* Step #10 - Add New Contenttypes
	*
	*/
	function step_10()
	{
		$this->add_contenttype('vbulletin', 'vBForum', 'UserNote');
	}

	/**
	 * Step #11
	 *
	 */
	function step_11()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'session', 1, 1),
			'session',
			'isbot',
			'tinyint',
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