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

class vB_Upgrade_400b3 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '400b3';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '4.0.0 Beta 3';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '4.0.0 Beta 2';

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
		$this->show_message($this->phrase['core']['updating_bbcode']);
		require_once(DIR . '/includes/functions_databuild.php');
		build_bbcode_video();
	}

	/**
	* Step #2 - retire existing styles
	*
	*/
	function step_2()
	{
		$this->run_query(
			$this->phrase['version']['400b3']['updating_styles'],
			"UPDATE " . TABLE_PREFIX . "style
			SET userselect = 0,
				displayorder = displayorder + 30000,
			    title =
			    	IF(title LIKE '%" . $this->db->escape_string_like($this->phrase['version']['400b3']['incompatible']) . "',
			    	title,
			    	CONCAT(title, '" . $this->db->escape_string($this->phrase['version']['400b3']['incompatible']) . "'))
		");
	}

	/**
	* Step #3 - disassociate styles with forums
	*
	*/
	function step_3()
	{
		$this->run_query(
			$this->phrase['version']['400b3']['updating_forum_styles'],
			"UPDATE " . TABLE_PREFIX . "forum
			SET styleid = 0
		");
	}

	/**
	* Step #4 - clear user style preferences
	*
	*/
	function step_4()
	{
		$this->run_query(
			$this->phrase['version']['400b3']['updating_user_styles'],
			"UPDATE " . TABLE_PREFIX . "user
			SET styleid = 0
		");
	}

	/**
	* Step #5 - clear blog style
	*
	*/
	function step_5()
	{
		$this->run_query(
			$this->phrase['version']['400b3']['updating_blog_styles'],
			"UPDATE " . TABLE_PREFIX . "setting
			SET value = '0'
			WHERE varname = 'vbblog_style'
		");
	}

	/**
	* Step #6 - Create new style
	*
	*/
	function step_6()
	{
		$this->db->query("
			INSERT INTO " . TABLE_PREFIX . "style
				(title,
				 parentid, userselect, displayorder)
			VALUES
				('" . $this->db->escape_string($this->phrase['version']['400b3']['default_style']) . "',
				 -1, 1, 1)
		");
		$styleid = $this->db->insert_id();

		$this->run_query(
			$this->phrase['version']['400b3']['updating_forum_styles'],
			"UPDATE " . TABLE_PREFIX . "style
			SET parentlist = '" . intval($styleid) . ",-1'
			WHERE styleid = " . intval($styleid)
		);

		$this->run_query(
			$this->phrase['version']['400b3']['updating_forum_styles'],
			"UPDATE " . TABLE_PREFIX . "setting
			SET value = '" . intval($styleid) . "'
			WHERE varname = 'styleid'
		");
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
