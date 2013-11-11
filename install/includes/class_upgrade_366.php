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

class vB_Upgrade_366 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '366';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '3.6.6';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '3.6.5';

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
			sprintf($this->phrase['core']['altering_x_table'], 'avatar', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "avatar CHANGE minimumposts minimumposts INT UNSIGNED NOT NULL DEFAULT '0'"
		);
	}

	/**
	* Step #2
	*
	*/
	function step_2()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'ranks', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "ranks CHANGE minposts minposts INT UNSIGNED NOT NULL DEFAULT '0'"
		);
	}

	/**
	* Step #3
	*
	*/
	function step_3()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'usertitle', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "usertitle CHANGE minposts minposts INT UNSIGNED NOT NULL DEFAULT '0'"
		);
	}

	/**
	* Step #4
	*
	*/
	function step_4()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'calendar', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "calendar CHANGE neweventemail neweventemail TEXT"
		);
	}

	/**
	* Step #5
	*
	*/
	function step_5()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'forum', 1, 2),
			"ALTER TABLE " . TABLE_PREFIX . "forum CHANGE newpostemail newpostemail TEXT"
		);
	}

	/**
	* Step #6
	*
	*/
	function step_6()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'forum', 2, 2),
			"ALTER TABLE " . TABLE_PREFIX . "forum CHANGE newthreademail newthreademail TEXT"
		);
	}

	/**
	* Step #7
	*
	*/
	function step_7()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'datastore', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "datastore CHANGE title title VARCHAR(50) NOT NULL DEFAULT ''"
		);
	}

	/**
	* Step #8
	*
	*/
	function step_8()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "userlist"),
			"CREATE TABLE " . TABLE_PREFIX . "userlist (
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				relationid INT UNSIGNED NOT NULL DEFAULT '0',
				type ENUM('buddy', 'ignore') NOT NULL DEFAULT 'buddy',
				PRIMARY KEY (userid, relationid, type),
				KEY userid (relationid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #9
	*
	*/
	function step_9()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "profilefieldcategory"),
			"CREATE TABLE " . TABLE_PREFIX . "profilefieldcategory (
				profilefieldcategoryid SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
				displayorder SMALLINT UNSIGNED NOT NULL,
				PRIMARY KEY (profilefieldcategoryid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #10
	*
	*/
	function step_10()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'profilefield', 1, 2),
			'profilefield',
			'profilefieldcategoryid',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #11
	*
	*/
	function step_11()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'profilefield', 2, 2),
			'profilefield',
			'profilefieldcategoryid',
			'profilefieldcategoryid'
		);
	}

	/**
	* Step #12
	*
	*/
	function step_12()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'externalcache', 1, 2),
			'externalcache',
			'forumid',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #13
	*
	*/
	function step_13()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'externalcache', 2, 2),
			'externalcache',
			'forumid',
			'forumid'
		);
	}

	/**
	* Step #14
	*
	*/
	function step_14()
	{
		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'template', 1, 2),
			'template',
			'title'
		);
	}

	/**
	* Step #15
	*
	*/
	function step_15()
	{
		$skip = true;
		/* this deals with the older templates */
		$badtemplates = $this->db->query_read("
			SELECT styleid, title, templatetype, MAX(dateline) AS newest, COUNT(*) AS total
			FROM " . TABLE_PREFIX . "template
			GROUP BY styleid, title, templatetype
			HAVING total > 1
		");
		while ($template = $this->db->fetch_array($badtemplates))
		{
			$skip = false;
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "user"),
				"DELETE FROM " . TABLE_PREFIX . "template
				WHERE styleid = $template[styleid]
					AND title = '" . $this->db->escape_string($template['title']) . "'
					AND templatetype = '" . $this->db->escape_string($template['templatetype']) . "'
					AND dateline < " . intval($template['newest'])
			);
		}
		if ($skip)
		{
			$this->skip_message();
		}
	}

	/**
	* Step #16
	*
	*/
	function step_16()
	{
		$skip = true;
		/* now to deal with those that have the same date */
		$badtemplates = $this->db->query_read("
			SELECT styleid, title, templatetype, MAX(templateid) AS newest, COUNT(*) AS total
			FROM " . TABLE_PREFIX . "template
			GROUP BY styleid, title, templatetype
			HAVING total > 1
		");
		while ($template = $this->db->fetch_array($badtemplates))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "user"),
				"DELETE FROM " . TABLE_PREFIX . "template
				WHERE styleid = $template[styleid]
					AND title = '" . $this->db->escape_string($template['title']) . "'
					AND templatetype = '" . $this->db->escape_string($template['templatetype']) . "'
					AND templateid <> " . intval($template['newest'])
			);
			$skip = false;
		}
		if ($skip)
		{
			$this->skip_message();
		}
	}

	/**
	* Step #17
	*
	*/
	function step_17()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'template', 2, 2),
			'template',
			'title',
			array('title', 'styleid', 'templatetype'),
			'unique'
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
