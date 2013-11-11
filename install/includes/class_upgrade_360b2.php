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

class vB_Upgrade_360b2 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '360b2';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '3.6.0 Beta 2';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '3.6.0 Beta 1';

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
		if (!$this->field_exists('adminmessage', 'adminmessageid'))
		{
			// Make sure to zero out permissions from possible past usage
			$newperms = array(
				'genericpermissions' => array(
					$this->registry->bf_ugp_genericpermissions['cangivearbinfraction'],
			));

			foreach ($newperms AS $permission => $permissions)
			{
				$this->run_query(
					sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "usergroup"),
					"UPDATE " . TABLE_PREFIX . "usergroup SET $permission = $permission & ~" . (array_sum($permissions))
				);
			}
			// give arbitrary infraction perms to admins
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "usergroup"),
				"UPDATE " . TABLE_PREFIX . "usergroup
					SET genericpermissions = genericpermissions | " . $this->registry->bf_ugp_genericpermissions['cangivearbinfraction'] ."
				WHERE adminpermissions & " . $this->registry->bf_ugp_adminpermissions['cancontrolpanel']
			);
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
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "adminmessage"),
			"CREATE TABLE " . TABLE_PREFIX . "adminmessage (
				adminmessageid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				varname varchar(250) NOT NULL DEFAULT '',
				dismissable SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				script varchar(50) NOT NULL DEFAULT '',
				action varchar(20) NOT NULL DEFAULT '',
				execurl mediumtext NOT NULL,
				method enum('get','post') NOT NULL DEFAULT 'post',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				status enum('undone','done','dismissed') NOT NULL default 'undone',
				statususerid INT UNSIGNED NOT NULL DEFAULT '0',
				args MEDIUMTEXT,
				PRIMARY KEY (adminmessageid),
				KEY script_action (script, action)
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
		$this->add_adminmessage(
			'after_upgrade_36_update_counters',
			array(
				'dismissable' => 1,
				'script'      => 'misc.php',
				'action'      => 'updatethread',
				'execurl'     => 'misc.php?do=updatethread',
				'method'      => 'get',
				'status'      => 'undone',
			)
		);
	}


	/**
	* Step #4
	*
	*/
	function step_4()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'adminlog', 1, 1),
			'adminlog',
			'script_action',
			array('script', 'action')
		);
	}

	/**
	* Step #5
	*
	*/
	function step_5()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'attachment', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "attachment CHANGE extension extension VARCHAR(20) BINARY NOT NULL DEFAULT ''"
		);
	}

	/**
	* Step #6
	*
	*/
	function step_6()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'attachmenttype', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "attachmenttype CHANGE extension extension VARCHAR(20) BINARY NOT NULL DEFAULT ''"
		);
	}

	/**
	* Step #7
	*
	*/
	function step_7()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'attachmentpermission', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "attachmentpermission CHANGE extension extension VARCHAR(20) BINARY NOT NULL DEFAULT ''"
		);
	}

	/**
	* Step #8
	*
	*/
	function step_8()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'user', 1, 1),
			'user',
			'sigpicrevision',
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
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'infraction', 1, 1),
			'infraction',
			'customreason',
			'varchar',
			array('length' => 255, 'attributes' => self::FIELD_DEFAULTS)
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
