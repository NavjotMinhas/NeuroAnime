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

class vB_Upgrade_400b1 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '400b1';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '4.0.0 Beta 1';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '4.0.0 Alpha 6';

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
		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'attachment', 1, 2),
			'attachment',
			'contenttypeid'
		);
	}

	/**
	* Step #2
	*
	*/
	function step_2()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'attachment', 2, 2),
			'attachment',
			'contenttypeid',
			array('contenttypeid', 'contentid', 'attachmentid')
		);
	}

	/**
	* Step #3
	*
	*/
	function step_3()
	{
		$row = $this->db->query_first("
			SELECT COUNT(*) AS count FROM " . TABLE_PREFIX . "notice WHERE title = 'default_guest_message'
		");

		if ($row['count'] == 0)
		{
			$this->show_message('Adding a notice');
			require_once(DIR . '/includes/adminfunctions_notice.php');

			$criteria = array();
			$criteria['in_usergroup_x'] = array('active' => 1, 'condition1' => 1);

			require_once(DIR . '/includes/class_bootstrap_framework.php');
			vB_Bootstrap_Framework::init();
			try
			{
				save_notice(null, 'default_guest_message', $this->phrase['install']['default_guest_message'], 10, 1, 1, 1, $criteria, 'System', $this->LONG_VERSION);
			}
			catch(vB_Exception_AdminStopMessage $e)
			{
				$this->add_error($e, self::PHP_TRIGGER_ERROR, true);
			}
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
