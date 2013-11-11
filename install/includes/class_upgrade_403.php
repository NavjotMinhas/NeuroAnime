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

class vB_Upgrade_403 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '403';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '4.0.3';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '4.0.2';

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
	* Step #1 - give all admins tags perms if they have thread perms
	*
	*/
	function step_1()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'administrator'),
			"UPDATE " . TABLE_PREFIX . "administrator SET
				adminpermissions = adminpermissions | " . $this->registry->bf_ugp_adminpermissions['canadmintags'] . "
			WHERE
				adminpermissions & " . $this->registry->bf_ugp_adminpermissions['canadminthreads']
		);
	}

	/**
	* Step #2
	*
	*/
	function step_2()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'attachment', 1, 1),
			'attachment',
			'displayorder',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #3
	*
	*/
	function step_3()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'attachment'),
			"UPDATE " . TABLE_PREFIX . "attachment SET displayorder = attachmentid
		");
	}

	/**
	* Step #4 - correctly store master style template history records
	*
	*/
	function step_4()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'templatehistory', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "templatehistory CHANGE styleid styleid SMALLINT NOT NULL DEFAULT '0'"
		);
	}

	/**
	* Step #5
	*
	*/
	function step_5()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'templatehistory'),
			"UPDATE " . TABLE_PREFIX . "templatehistory SET styleid = -1 WHERE styleid = 0"
		);
	}

	/**
	* Step #6 - Make sure there's a cron job to do queued cache updates
	*
	*/
	function step_6()
	{
		$this->add_cronjob(
			array(
				'varname'  => 'queueprocessor',
				'nextrun'  => 1232082000,
				'weekday'  => -1,
				'day'      => -1,
				'hour'     => -1,
				'minute'   => 'a:6:{i:0;i:0;i:1;i:10;i:2;i:20;i:3;i:30;i:4;i:40;i:5;i:50;}',
				'filename' => './includes/cron/queueprocessor.php',
				'loglevel' => 1,
				'volatile' => 1,
				'product'  => 'vbulletin'
			)
		);
	}

	/**
	* Step #7
	*
	*/
	function step_7()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'post', 1, 1),
			'post',
			'htmlstate',
			'enum',
			array('attributes' => "('off', 'on', 'on_nl2br')", 'null' => false, 'default' => 'on_nl2br')
		);
	}

	/**
	* Step #8
	*
	*/
	function step_8()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'bookmarksite', 1, 1),
			'bookmarksite',
			'utf8encode',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #9 - widen attachment filenames
	*
	*/
	function step_9()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'attachment', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "attachment CHANGE filename filename VARCHAR(255) NOT NULL DEFAULT ''"
		);
	}

	/**
	* Step #10
	*
	*/
	function step_10()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'attachmentcategoryuser', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "attachmentcategoryuser CHANGE filename filename VARCHAR(255) NOT NULL DEFAULT ''"
		);
	}

	/**
	* Step #11 - widen the user salt
	*
	*/
	function step_11()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'user', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "user MODIFY salt CHAR(30) NOT NULL DEFAULT ''"
		);
	}

	/**
	* Step #12 - rebuild ads that use the is_date criterion #36100 or browsing forum criteria #34416
	*
	*/
	function step_12()
	{
		$this->show_message($this->phrase['version']['403']['rebuilding_ad_criteria']);

		$ad_result = $this->db->query_read("
			SELECT ad.*
			FROM " . TABLE_PREFIX . "ad AS ad
			LEFT JOIN " . TABLE_PREFIX . "adcriteria AS adcriteria ON(adcriteria.adid = ad.adid)
			WHERE adcriteria.criteriaid IN('is_date', 'browsing_forum_x', 'browsing_forum_x_and_children')
		");
		if ($this->db->num_rows($ad_result) > 0)
		{
			$ad_cache = array();
			$ad_locations = array();

			while ($ad = $this->db->fetch_array($ad_result))
			{
				$ad_cache["$ad[adid]"] = $ad;
				$ad_locations[] = $ad['adlocation'];
			}

			require_once(DIR . '/includes/functions_ad.php');
			require_once(DIR . '/includes/adminfunctions_template.php');

			foreach($ad_locations AS $location)
			{
				$template = wrap_ad_template(build_ad_template($location), $location);

				$template_un = $template;
				$template = compile_template($template);

				$this->run_query(
					sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'templatehistory'),
					"
						UPDATE " . TABLE_PREFIX . "template SET
							template = '" . $this->db->escape_string($template) . "',
							template_un = '" . $this->db->escape_string($template_un) . "',
							dateline = " . TIMENOW . ",
							username = '" . $this->db->escape_string($this->registry->userinfo['username']) . "'
						WHERE
							title = 'ad_" . $this->db->escape_string($location) . "'
							AND styleid IN (-1,0)
					"
				);
			}

			build_all_styles();
		}
	}

	/**
	* Step #13 - add the facebook userid to the user table
	*
	*/
	function step_13()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'user', 1, 4),
			'user',
			'fbuserid',
			'VARCHAR',
			array(
				'length' => 255
			)
		);
	}

	/**
	* Step #14 - add index to facebook userid
	*
	*/
	function step_14()
	{
		$this->add_index(
			sprintf($this->phrase['core']['create_index_x_on_y'], 'fbuserid', TABLE_PREFIX . 'user'),
			'user',
			'fbuserid',
			array('fbuserid')
		);
	}

	/**
	* Step #15 - add facebook join date to the user table
	*
	*/
	function step_15()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'user', 2, 4),
			'user',
			'fbjoindate',
			'INT',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #16 - add the facebook name to the user table
	*
	*/
	function step_16()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'user', 3, 4),
			'user',
			'fbname',
			'VARCHAR',
			array(
				'length' => 255
			)
		);
	}

	/**
	* Step #17
	*
	*/
	function step_17()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'user', 4, 4),
			'user',
			'logintype',
			'enum',
			array('attributes' => "('vb', 'fb')", 'null' => false, 'default' => 'vb')
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
