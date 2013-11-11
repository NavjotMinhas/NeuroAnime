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

class vB_Upgrade_370b6 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '370b6';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '3.7.0 Beta 6';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '3.7.0 Beta 5';

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
		// Enable Visitor Messages for all users
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'user'),
			"UPDATE " . TABLE_PREFIX . "user SET options = options | " . $this->registry->bf_misc_useroptions['vm_enable']
		);
	}

	/**
	* Step #3
	*
	*/
	function step_3()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'setting'),
			"UPDATE " . TABLE_PREFIX . "setting SET
				value = value | " . $this->registry->bf_misc_regoptions['vm_enable'] . "
			WHERE varname = 'defaultregoptions'"
		);
	}

	/**
	* Step #4
	*
	*/
	function step_4()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'socialgroup', 1, 1),
			'socialgroup',
			'options',
			'int',
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
			sprintf($this->phrase['core']['altering_x_table'], 'user', 1, 1),
			'user',
			'gmmoderatedcount',
			'int',
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
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'socialgroup'),
			"UPDATE " . TABLE_PREFIX . "socialgroup SET
				options = options | " . ($this->registry->options['socnet_groups_albums_enabled'] ? $this->registry->bf_misc_socialgroupoptions['enable_group_albums'] : 0) . " | " . ($this->registry->options['socnet_groups_msg_enabled'] ? $this->registry->bf_misc_socialgroupoptions['enable_group_messages'] : 0)
		);
	}

	/**
	* Step #7
	*
	*/
	function step_7()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'picture', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "picture ADD state ENUM('visible', 'moderation') NOT NULL DEFAULT 'visible'",
			self::MYSQL_ERROR_COLUMN_EXISTS
		);
	}

	/**
	* Step #8
	*
	*/
	function step_8()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'album', 1, 2),
			'album',
			'moderation',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #9
	*
	*/
	function step_9()
	{
		if ($this->field_exists('album', 'picturecount'))
		{
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'album', 2, 2),
				"ALTER TABLE " . TABLE_PREFIX . "album CHANGE picturecount visible INT UNSIGNED NOT NULL DEFAULT '0'"
			);
		}
	}

	/**
	* Step #10
	*
	*/
	function step_10()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 1, 1),
			"UPDATE " . TABLE_PREFIX . "usergroup SET
				albumpermissions = albumpermissions | IF(forumpermissions & " . $this->registry->bf_ugp_forumpermissions['followforummoderation'] . ", " . $this->registry->bf_ugp_albumpermissions['picturefollowforummoderation'] . ", 0)
			"
		);
	}

	/**
	* Step #11
	*
	*/
	function step_11()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'moderator', 1, 1),
			"UPDATE " . TABLE_PREFIX . "moderator SET
				permissions2 = permissions2 | IF(permissions & " . $this->registry->bf_misc_moderatorpermissions2['canmoderatepicturecomments'] . ", " . $this->registry->bf_misc_moderatorpermissions2['canmoderatepictures'] . ", 0)
			"
		);
	}

	/**
	* Step #12
	*
	*/
	function step_12()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 1, 1),
			"UPDATE " . TABLE_PREFIX . "usergroup SET
				socialgrouppermissions = socialgrouppermissions |
					IF(visitormessagepermissions & " . $this->registry->bf_ugp_visitormessagepermissions['canmanageownprofile'] . ", " . $this->registry->bf_ugp_socialgrouppermissions['canmanageowngroups'] . ", 0)
			"
		);
	}

	/**
	* Step #13
	*
	*/
	function step_13()
	{
		$db =& $this->db;
		require_once(DIR . '/install/mysql-schema.php');

		// insert the updated 3.7.0 FAQ Structure
		$this->run_query(
			$this->phrase['version']['370b6']['inserting_vb37_faq_structure'],
			$schema['INSERT']['query']['faq']
		);
	}

	/**
	* Step #14
	*
	*/
	function step_14()
	{
		$this->add_adminmessage(
			'after_upgrade_37_update_faq',
			array(
				'dismissable' => 1,
				'script'      => 'faq.php',
				'action'      => 'updatefaq',
				'execurl'     => 'faq.php?do=updatefaq',
				'method'      => 'get',
				'status'      => 'undone',
			)
		);
	}

	/**
	* Step #15
	*
	*/
	function step_15()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'pollvote', 1, 5),
			"ALTER TABLE " . TABLE_PREFIX . "pollvote CHANGE userid userid INT UNSIGNED NULL DEFAULT NULL"
		);
	}

	/**
	* Step #16
	*
	*/
	function step_16()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'pollvote', 2, 5),
			"ALTER TABLE " . TABLE_PREFIX . "pollvote ADD votetype INT UNSIGNED NOT NULL DEFAULT '0'",
			self::MYSQL_ERROR_COLUMN_EXISTS
		);
	}

	/**
	* Step #17
	*
	*/
	function step_17()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'pollvote'),
			"UPDATE " . TABLE_PREFIX . "pollvote AS pollvote, " . TABLE_PREFIX . "poll AS poll
			SET pollvote.votetype = pollvote.voteoption
		 	WHERE pollvote.pollid = poll.pollid
		 		AND poll.multiple = 1
			"
		);
	}

	/**
	* Step #18
	*
	*/
	function step_18()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'pollvote'),
			"UPDATE " . TABLE_PREFIX . "pollvote SET userid = NULL WHERE userid = 0"
		);
	}

	/**
	* Step #19
	*
	*/
	function step_19()
	{
		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'pollvote', 3, 5),
			'pollvote',
			'pollid'
		);
	}

	/**
	* Step #20
	*
	*/
	function step_20()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'pollvote', 4, 5),
			"ALTER IGNORE TABLE " . TABLE_PREFIX . "pollvote ADD UNIQUE INDEX pollid (pollid,userid,votetype)",
			self::MYSQL_ERROR_KEY_EXISTS
		);
	}

	/**
	* Step #21
	*
	*/
	function step_21()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'pollvote', 5, 5),
			'pollvote',
			'userid',
			'userid'
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
