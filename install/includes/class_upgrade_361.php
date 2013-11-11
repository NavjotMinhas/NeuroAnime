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

class vB_Upgrade_361 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '361';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '3.6.1';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '3.6.0';

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
		if (!$this->field_exists('infractionlevel', 'extend'))
		{
			$avatarids = array();
			$avatars = $this->db->query_read("
				SELECT userid
				FROM " . TABLE_PREFIX . "customavatar
			");
			while ($avatar = $this->db->fetch_array($avatars))
			{
				$avatarids[] = $avatar['userid'];
			}

			$profilepicids = array();
			$profilepics = $this->db->query_read("
				SELECT userid
				FROM " . TABLE_PREFIX . "customprofilepic
			");
			while ($profilepic = $this->db->fetch_array($profilepics))
			{
				$profilepicids[] = $profilepic['userid'];
			}

			if ($avatarids)
			{
				$this->run_query(
					sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "user"),
					"UPDATE " . TABLE_PREFIX . "user
					SET adminoptions = adminoptions | " . $this->registry->bf_misc_adminoptions['adminavatar'] . "
					WHERE userid IN (" . implode(',', $avatarids) . ")"
				);
			}

			if ($profilepicids)
			{
				$this->run_query(
					sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "user"),
					"UPDATE " . TABLE_PREFIX . "user
					 SET adminoptions = adminoptions | " . $this->registry->bf_misc_adminoptions['adminprofilepic'] . "
					 WHERE userid IN (" . implode(',', $profilepicids) . ")"
				);
			}

			if (!$avatarids AND !$profilepicids)
			{
				$this->skip_message();
			}
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
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'infractionlevel', 1, 1),
			'infractionlevel',
			'extend',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #3
	*
	*/
	function step_3()
	{
		if (!$this->field_exists('podcastitem', 'explicit'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'podcasturl', 1, 4),
				'podcasturl',
				'explicit',
				'smallint',
				self::FIELD_DEFAULTS
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
		if (!$this->field_exists('podcastitem', 'explicit'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'podcasturl', 2, 4),
				'podcasturl',
				'keywords',
				'varchar',
				array('length' => 255, 'attributes' => self::FIELD_DEFAULTS)
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #5
	*
	*/
	function step_5()
	{
		if (!$this->field_exists('podcastitem', 'explicit'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'podcasturl', 3, 4),
				'podcasturl',
				'subtitle',
				'varchar',
				array('length' => 255, 'attributes' => self::FIELD_DEFAULTS)
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #6
	*
	*/
	function step_6()
	{
		if (!$this->field_exists('podcastitem', 'explicit'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'podcasturl', 4, 4),
				'podcasturl',
				'author',
				'varchar',
				array('length' => 255, 'attributes' => self::FIELD_DEFAULTS)
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #7
	*
	*/
	function step_7()
	{
		if (!$this->field_exists('podcastitem', 'explicit'))
		{
			$this->run_query(
				 sprintf($this->phrase['version']['361']['rename_podcasturl'], TABLE_PREFIX),
				 "ALTER TABLE " . TABLE_PREFIX . "podcasturl RENAME " . TABLE_PREFIX . "podcastitem",
				 self::MYSQL_ERROR_TABLE_MISSING
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #8
	*
	*/
	function step_8()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'administrator', 1, 1),
			'administrator',
			'dismissednews',
			'text',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #9
	*
	*/
	function step_9()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "infractionban"),
			"CREATE TABLE " . TABLE_PREFIX . "infractionban (
				infractionbanid int unsigned NOT NULL auto_increment,
				usergroupid int NOT NULL DEFAULT '0',
				banusergroupid int unsigned NOT NULL DEFAULT '0',
				amount int unsigned NOT NULL DEFAULT '0',
				period char(5) NOT NULL DEFAULT '',
				method enum('points','infractions') NOT NULL default 'infractions',
				PRIMARY KEY (infractionbanid),
				KEY usergroupid (usergroupid)
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
		$changed_strip = false;

		if (strpos($this->registry->options['blankasciistrip'], 'u8204') === false)
		{
			$this->registry->options['blankasciistrip'] .= ' u8204 u8205';
			$changed_strip = true;
		}
		if (strpos($this->registry->options['blankasciistrip'], 'u8237') === false)
		{
			$this->registry->options['blankasciistrip'] .= ' u8237 u8238';
			$changed_strip = true;
		}

		if ($changed_strip)
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "setting"),
				"UPDATE " . TABLE_PREFIX . "setting SET
					value = '" . $this->db->escape_string($this->registry->options['blankasciistrip']) . "'
				WHERE varname = 'blankasciistrip'"
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
