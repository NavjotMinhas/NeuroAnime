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

class vB_Upgrade_368 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '368';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '3.6.8';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '3.6.7';

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
		for ($x = 1; $x < 6; $x++)
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'moderatorlog', $x, 9),
				'moderatorlog',
				"id$x",
				'int',
				self::FIELD_DEFAULTS
			);
		}
	}

	/**
	* Step #2
	*
	*/
	function step_2()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'moderatorlog', 6, 9),
			'moderatorlog',
			'product',
			'varchar',
			array(
				'length'     => 25,
				'attributes' => self::FIELD_DEFAULTS
		));
	}

	/**
	* Step #3
	*
	*/
	function step_3()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'moderatorlog', 7, 9),
			'moderatorlog',
			'product',
			'product'
		);
	}


	/**
	* Step #4
	*
	*/
	function step_4()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'moderatorlog', 8, 9),
			'moderatorlog',
			'id1',
			'id1'
		);
	}

	/**
	* Step #5
	*
	*/
	function step_5()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'moderatorlog', 9, 9),
			'moderatorlog',
			'id2',
			'id2'
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
