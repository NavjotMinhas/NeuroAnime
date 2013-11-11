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

class vB_Upgrade_370b3 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '370b3';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '3.7.0 Beta 3';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '3.7.0 Beta 2';

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
		if (!isset($this->registry->bf_misc_moderatorpermissions2['caneditvisitormessages']))
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
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'phrasetype', 1, 1),
			"UPDATE " . TABLE_PREFIX . "phrasetype SET special = 1 WHERE fieldname = 'hvquestion'"
		);
	}

	/**
	* Step #3
	*
	*/
	function step_3()
	{
		// support for limited social groups
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'user', 1, 2),
			'user',
			'socgroupinvitecount',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #4
	*
	*/
	function step_4()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'user', 2, 2),
			'user',
			'socgroupreqcount',
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
			sprintf($this->phrase['core']['altering_x_table'], 'socialgroup', 1, 2),
			'socialgroup',
			'type',
			'enum',
			array('attributes' => "('public', 'moderated', 'inviteonly')", 'null' => false, 'default' => 'public')
		);
	}

	/**
	* Step #6
	*
	*/
	function step_6()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'socialgroup', 2, 2),
			'socialgroup',
			'moderatedmembers',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #7
	*
	*/
	function step_7()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'socialgroupmember', 1, 4),
			'socialgroupmember',
			'type',
			'enum',
			array('attributes' => "('member', 'moderated', 'invited')", 'null' => false, 'default' => 'member')
		);
	}

	/**
	* Step #8
	*
	*/
	function step_8()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'socialgroupmember', 2, 4),
			'socialgroupmember',
			'groupid',
			array('groupid', 'type')
		);
	}

	/**
	* Step #9
	*
	*/
	function step_9()
	{
		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'socialgroupmember', 3, 4),
			'socialgroupmember',
			'userid'
		);
	}

	/**
	* Step #10
	*
	*/
	function step_10()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'socialgroupmember', 4, 4),
			'socialgroupmember',
			'userid',
			array('userid', 'type')
		);
	}

	/**
	* Step #11
	*
	*/
	function step_11()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'socialgrouppicture', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "socialgrouppicture
				CHANGE groupid groupid INT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE pictureid pictureid INT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE dateline dateline INT UNSIGNED NOT NULL DEFAULT '0'
		");
	}

	/**
	* Step #12
	*
	*/
	function step_12()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'notice', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "notice
				CHANGE title title VARCHAR(250) NOT NULL DEFAULT '',
				CHANGE displayorder displayorder INT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE active active SMALLINT UNSIGNED NOT NULL DEFAULT '0'
		");
	}

	/**
	* Step #13
	*
	*/
	function step_13()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'noticecriteria', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "noticecriteria
				CHANGE noticeid noticeid INT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE criteriaid criteriaid VARCHAR(250) NOT NULL DEFAULT '',
				CHANGE condition1 condition1 VARCHAR(250) NOT NULL DEFAULT '',
				CHANGE condition2 condition2 VARCHAR(250) NOT NULL DEFAULT '',
				CHANGE condition3 condition3 VARCHAR(250) NOT NULL DEFAULT ''
		");
	}

	/**
	* Step #14
	*
	*/
	function step_14()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'tag', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "tag
				CHANGE tagtext tagtext VARCHAR(100) NOT NULL DEFAULT '',
				CHANGE dateline dateline INT UNSIGNED NOT NULL DEFAULT '0'
		");
	}

	/**
	* Step #15
	*
	*/
	function step_15()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'tagthread', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "tagthread
				CHANGE tagid tagid INT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE threadid threadid INT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE userid userid INT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE dateline dateline INT UNSIGNED NOT NULL DEFAULT '0'
		");
	}

	/**
	* Step #16
	*
	*/
	function step_16()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'tagsearch', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "tagsearch
				CHANGE tagid tagid INT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE dateline dateline INT UNSIGNED NOT NULL DEFAULT '0'
		");
	}

	/**
	* Step #17
	*
	*/
	function step_17()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'postedithistory', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "postedithistory
				CHANGE postid postid INT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE userid userid INT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE username username VARCHAR(100) NOT NULL DEFAULT '',
				CHANGE title title VARCHAR(250) NOT NULL DEFAULT '',
				CHANGE iconid iconid INT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE dateline dateline INT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE reason reason VARCHAR(200) NOT NULL DEFAULT '',
				CHANGE original original SMALLINT NOT NULL DEFAULT '0'
		");
	}

	/**
	* Step #18
	*
	*/
	function step_18()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'usercsscache', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "usercsscache
				CHANGE cachedcss cachedcss TEXT
		");
	}

	/**
	* Step #19
	*
	*/
	function step_19()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'visitormessage', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "visitormessage
				CHANGE userid userid INT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE postuserid postuserid INT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE postusername postusername VARCHAR(100) NOT NULL DEFAULT '',
				CHANGE dateline dateline INT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE ipaddress ipaddress INT UNSIGNED NOT NULL DEFAULT '0'
		");
	}

	/**
	* Step #20
	*
	*/
	function step_20()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'groupmessage', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "groupmessage
				CHANGE groupid groupid INT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE postuserid postuserid INT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE postusername postusername VARCHAR(100) NOT NULL DEFAULT '',
				CHANGE dateline dateline INT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE ipaddress ipaddress INT UNSIGNED NOT NULL DEFAULT '0'
		");
	}

	/**
	* Step #21
	*
	*/
	function step_21()
	{
		if ($this->field_exists('album', 'picturecount'))
		{
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'album', 1, 1),
				"ALTER TABLE " . TABLE_PREFIX . "album
					CHANGE userid userid INT UNSIGNED NOT NULL DEFAULT '0',
					CHANGE createdate createdate INT UNSIGNED NOT NULL DEFAULT '0',
					CHANGE lastpicturedate lastpicturedate INT UNSIGNED NOT NULL DEFAULT '0',
					CHANGE picturecount picturecount INT UNSIGNED NOT NULL DEFAULT '0',
					CHANGE title title VARCHAR(100) NOT NULL DEFAULT '',
					CHANGE description description TEXT,
					CHANGE coverpictureid coverpictureid INT UNSIGNED NOT NULL DEFAULT '0'
			");
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #22
	*
	*/
	function step_22()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'albumpicture', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "albumpicture
				CHANGE albumid albumid INT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE pictureid pictureid INT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE dateline dateline INT UNSIGNED NOT NULL DEFAULT '0'
		");
	}

	/**
	* Step #23
	*
	*/
	function step_23()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'picture', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "picture
				CHANGE userid userid INT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE caption caption TEXT,
				CHANGE extension extension VARCHAR(20) NOT NULL DEFAULT '',
				CHANGE filedata filedata MEDIUMBLOB,
				CHANGE filesize filesize INT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE width width SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE height height SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE thumbnail thumbnail MEDIUMBLOB,
				CHANGE thumbnail_filesize thumbnail_filesize INT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE thumbnail_width thumbnail_width SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE thumbnail_height thumbnail_height SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE thumbnail_dateline thumbnail_dateline INT UNSIGNED NOT NULL DEFAULT '0',
				CHANGE idhash idhash VARCHAR(32) NOT NULL DEFAULT '',
				CHANGE reportthreadid reportthreadid INT UNSIGNED NOT NULL DEFAULT '0'
		");
	}

	/**
	* Step #24 - For MySQL 5 compat, TEXT fields do not have NOT NULL or DEFAULT
	*
	*/
	function step_24()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'rssfeed', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "rssfeed CHANGE url url TEXT"
		);
	}

	/**
	* Step #25
	*
	*/
	function step_25()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'socialgroup', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "socialgroup CHANGE description description TEXT"
		);
	}

	/**
	* Step #26
	*
	*/
	function step_26()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'usercsscache', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "usercsscache CHANGE cachedcss cachedcss TEXT"
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
