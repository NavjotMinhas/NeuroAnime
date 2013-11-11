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

class vB_Upgrade_404 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '404';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '4.0.4';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '4.0.3';

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
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'userchangelog', 1, 1),
			'userchangelog',
			'ipaddress',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #2 - remove orphaned stylevars
	*
	*/
	function step_2()
	{
		$this->show_message($this->phrase['version']['404']['checking_orphaned_stylevars']);

		$skipstyleids = '-1';
		$style_result = $this->db->query_read("SELECT styleid FROM " . TABLE_PREFIX . "style");
		while ($style_row = $this->db->fetch_array($style_result))
		{
			$skipstyleids .= ',' . intval($style_row['styleid']);
		}
		$this->db->query_write("DELETE FROM " . TABLE_PREFIX . "stylevar WHERE styleid NOT IN($skipstyleids)");

		$orphaned_stylevar_count = $this->db->affected_rows();
		if ($orphaned_stylevar_count > 0)
		{
			$this->show_message(sprintf($this->phrase['version']['404']['removed_x_orphaned_stylevars'], $orphaned_stylevar_count));
		}
		else
		{
			$this->show_message($this->phrase['version']['404']['no_orphaned_stylevars']);
		}
	}

	/**
	* Step #3
	*
	*/
	function step_3()
	{
		$smilies_to_change = array(
			'smile', 'redface', 'biggrin', 'wink', 'tongue', 'cool',
			'rolleyes', 'mad', 'eek', 'confused', 'frown'
		);

		//change the standard icons to the new png images.
		$i = 0;
		foreach ($smilies_to_change as $smilie)
		{
			$i++;
			$this->run_query(
				sprintf($this->phrase['version']['404']['update_smilie'], $i, count($smilies_to_change)),
				"UPDATE " . TABLE_PREFIX . "smilie SET smiliepath = 'images/smilies/$smilie.png'
				WHERE smiliepath = 'images/smilies/$smilie.gif' AND imagecategoryid = 1"
			);
		}
	}

	/**
	* Step #4
	*
	*/
	function step_4()
	{
		require_once(DIR . '/includes/adminfunctions.php');
		build_image_cache('smilie');

		$this->drop_field(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 1, 1),
			'usergroup',
			'albumpicmaxsize'
		);
	}

	/**
	* Step #5
	*
	*/
	function step_5()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'usertitle', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "usertitle CHANGE usertitleid usertitleid INT UNSIGNED NOT NULL AUTO_INCREMENT"
		);
	}

	/**
	* Step #6
	*
	*/
	function step_6()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'album', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "album CHANGE description description MEDIUMTEXT"
		);
	}

	/**
	* Step #7 - The default on this field is not relevant since this value is determined at user creation but let's match what mysql-schema has
	*
	*/
	function step_7()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'user', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "user CHANGE options options INT UNSIGNED NOT NULL DEFAULT '33570831'"
		);
	}

	/**
	* Step #8
	*
	*/
	function step_8()
	{
		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'contenttype', 1, 4),
			'contenttype',
			'package'
		);
	}

	/**
	* Step #9
	*
	*/
	function step_9()
	{
		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'contenttype', 2, 4),
			'contenttype',
			'packageclass'
		);
	}

	/**
	* Step #10
	*
	*/
	function step_10()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'contenttype', 3, 4),
			'contenttype',
			'packageclass',
			array('packageid', 'class'),
			'unique'
		);
	}

	/**
	* Step #11
	*
	*/
	function step_11()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'contenttype', 4, 4),
				"ALTER TABLE " . TABLE_PREFIX . "contenttype ENGINE={$this->hightrafficengine}"
		);
	}

	/**
	* Step #12
	*
	*/
	function step_12()
	{
		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'prefixpermission', 1, 3),
			'prefixpermission',
			'prefixsetid'
		);
	}

	/**
	* Step #13
	*
	*/
	function step_13()
	{
		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'prefixpermission', 2, 3),
			'prefixpermission',
			'prefixusergroup'
		);
	}

	/**
	* Step #14
	*
	*/
	function step_14()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'prefixpermission', 3, 3),
			'prefixpermission',
			'prefixsetid',
			array('prefixid', 'usergroupid')
		);
	}

	/**
	* Step #15
	*
	*/
	function step_15()
	{
		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'groupmessage', 1, 2),
			'groupmessage',
			'postuserid'
		);
	}

	/**
	* Step #16
	*
	*/
	function step_16()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'groupmessage', 2, 2),
			'groupmessage',
			'postuserid',
			array('postuserid', 'discussionid', 'state')
		);
	}

	/**
	* Step #17
	*
	*/
	function step_17()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'editlog', 1, 1),
				"ALTER TABLE " . TABLE_PREFIX . "editlog CHANGE hashistory hashistory SMALLINT UNSIGNED NOT NULL DEFAULT '0'"
		);
	}

	/**
	* Step #18
	*
	*/
	function step_18()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'profilevisitor', 1, 1),
				"ALTER TABLE " . TABLE_PREFIX . "profilevisitor CHANGE visible visible SMALLINT UNSIGNED NOT NULL DEFAULT '1'"
		);
	}

	/**
	* Step #19
	*
	*/
	function step_19()
	{
		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'groupmessage', 1, 1),
			'groupmessage',
			'gm_ft'
		);

		$this->long_next_step();
	}

	/**
	* Step #20
	*
	*/
	function step_20()
	{
		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'socialgroup', 1, 1),
			'socialgroup',
			'name'
		);

		$this->long_next_step();
	}

	/**
	* Step #21
	*
	*/
	function step_21()
	{
		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'post', 1, 1),
			'post',
			'title'
		);

		$this->long_next_step();
	}

	/**
	* Step #22 - Set viewattachedimages = 3 when we had thumbnails disabled and view full images enabled
	*
	*/
	function step_22()
	{
		if (!$this->registry->options['attachthumbs'] AND $this->registry->options['viewattachedimages'] == 1)
		{
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'setting', 1, 1),
					"UPDATE " . TABLE_PREFIX . "setting SET value = 3 WHERE varname = 'viewattachedimages'"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #23 - add the facebook name to the user table
	*
	*/
	function step_23()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'user', 1, 2),
			'user',
			'fbaccesstoken',
			'VARCHAR',
			array(
				'length' => 255
			)
		);
	}

	/**
	* Step #24 - add the facebook name to the user table
	*
	*/
	function step_24()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'user', 2, 2),
			'user',
			'fbprofilepicurl',
			'VARCHAR',
			array(
				'length' => 100
			)
		);
	}

	/**
	* Step #25
	*
	*/
	function step_25()
	{
		if (trim($this->registry->options['facebookapikey']) != '' OR $this->registry->options['enablefacebookconnect'])
		{
			$this->add_adminmessage(
				'after_upgrade_404_update_facebook',
				array(
					'dismissable' => 1,
					'script'      => '',
					'action'      => '',
					'execurl'     => '',
					'method'      => '',
					'status'      => 'undone',
				)
			);
		}
		else
		{
			$this->skip_message();
		}

		require_once(DIR . '/includes/class_bootstrap_framework.php');
		vB_Bootstrap_Framework::init();

		//clear the cache.  There are some values that are incorrect because of code changes,
		//make sure that we don't allow those values to be used after the upgrade.
		vB_Cache::instance()->clean(false);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
