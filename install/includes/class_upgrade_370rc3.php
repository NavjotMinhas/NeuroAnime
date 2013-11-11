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

class vB_Upgrade_370rc3 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '370rc3';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '3.7.0 Release Candidate 3';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '3.7.0 Release Candidate 2';

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
	* Step #1 - give all admins notices permissions by default
	*
	*/
	function step_1()
	{
		if (!isset($this->registry->bf_ugp_adminpermissions['canadminnotices']))
		{
			$this->add_error($this->phrase['core']['wrong_bitfield_xml'], self::PHP_TRIGGER_ERROR, true);
		}

		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'administrator'),
			"UPDATE " . TABLE_PREFIX . "administrator SET
				adminpermissions = adminpermissions | " .
					($this->registry->bf_ugp_adminpermissions['canadminnotices'] + $this->registry->bf_ugp_adminpermissions['canadminmodlog'])
		);

		require_once(DIR . '/includes/functions_databuild.php');
		build_birthdays();
	}

	/**
	* Step #2
	*
	*/
	function step_2()
	{
		$tables = $this->db->query_write("SHOW TABLES");
		$skip = true;
		while ($table = $this->db->fetch_array($tables, DBARRAY_NUM))
		{
			if (strpos($table[0], TABLE_PREFIX . 'aaggregate_temp_') !== false OR strpos($table[0], TABLE_PREFIX . 'taggregate_temp_') !== false)
			{
				if (!preg_match('/_(\d+)$/siU', $table[0], $matches))
				{
					continue;
				}

				if ($matches[1] > TIMENOW - 3600)
				{
					continue;
				}

				$skip = false;
				$this->run_query(
					sprintf($this->phrase['core']['dropping_old_table_x'], $table[0]),
					"DROP TABLE IF EXISTS " . $table[0]
				);
			}
		}

		if ($skip)
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
