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

class vB_Upgrade_380a2 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '380a2';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '3.8.0 Alpha 2';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '3.7.1+';

	/**
	* Beginning version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_STARTS = '3.7.1';

	/**
	* Ending version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_ENDS   = '3.7.99';

	/**
	* Step #1
	*
	*/
	function step_1()
	{
		if (!isset($this->registry->bf_ugp_socialgrouppermissions['canuploadgroupicon']))
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
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'prefixpermission'),
			"CREATE TABLE " . TABLE_PREFIX . "prefixpermission (
				prefixid VARCHAR(25) NOT NULL,
				usergroupid SMALLINT UNSIGNED NOT NULL,
				KEY prefixusergroup (prefixid, usergroupid)
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
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'albumupdate'),
			"CREATE TABLE " . TABLE_PREFIX . "albumupdate (
				albumid INT UNSIGNED NOT NULL DEFAULT '0',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (albumid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #4
	*
	*/
	function step_4()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'pmthrottle'),
			"CREATE TABLE " . TABLE_PREFIX . "pmthrottle (
				userid INT unsigned NOT NULL,
				dateline INT unsigned NOT NULL,
				KEY userid (userid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #5
	*
	*/
	function step_5()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'discussion'),
			"CREATE TABLE " . TABLE_PREFIX . "discussion (
				discussionid INT unsigned NOT NULL auto_increment,
				groupid INT unsigned NOT NULL,
				firstpostid INT unsigned NOT NULL,
				lastpostid INT unsigned NOT NULL,
				lastpost INT unsigned NOT NULL,
				lastposter VARCHAR(255) NOT NULL,
				lastposterid INT unsigned NOT NULL,
				visible INT unsigned NOT NULL default '0',
				deleted INT unsigned NOT NULL default '0',
				moderation INT unsigned NOT NULL default '0',
				subscribers ENUM('0', '1') default '0',
				PRIMARY KEY  (discussionid),
				KEY groupid (groupid, lastpost)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #6
	*
	*/
	function step_6()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'groupread'),
			"CREATE TABLE " . TABLE_PREFIX . "groupread (
				userid INT unsigned NOT NULL,
				groupid INT unsigned NOT NULL,
				readtime INT unsigned NOT NULL,
				PRIMARY KEY  (userid, groupid),
				KEY readtime (readtime)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #7
	*
	*/
	function step_7()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'discussionread'),
			"CREATE TABLE " . TABLE_PREFIX . "discussionread (
				userid INT unsigned NOT NULL,
				discussionid INT unsigned NOT NULL,
				readtime INT unsigned NOT NULL,
				PRIMARY KEY (userid, discussionid),
				KEY readtime (readtime)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #8
	*
	*/
	function step_8()
	{
	 	$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'socialgroupcategory'),
			"CREATE TABLE " . TABLE_PREFIX . "socialgroupcategory (
				 socialgroupcategoryid INT unsigned NOT NULL auto_increment,
				 creatoruserid INT unsigned NOT NULL,
				 title VARCHAR(250) NOT NULL,
				 description TEXT NOT NULL,
				 displayorder INT unsigned NOT NULL,
				 lastupdate INT unsigned NOT NULL,
				 groups INT unsigned default '0',
				 PRIMARY KEY  (socialgroupcategoryid),
				 KEY displayorder (displayorder)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #9
	*
	*/
	function step_9()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'subscribegroup'),
			"CREATE TABLE " . TABLE_PREFIX . "subscribegroup (
				subscribegroupid INT unsigned NOT NULL auto_increment,
				userid INT unsigned NOT NULL,
				groupid INT unsigned NOT NULL,
				PRIMARY KEY  (subscribegroupid),
				UNIQUE KEY usergroup (userid, groupid),
				KEY groupid (groupid)
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
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'subscribediscussion'),
			"CREATE TABLE " . TABLE_PREFIX . "subscribediscussion (
				subscribediscussionid INT unsigned NOT NULL auto_increment,
				userid INT unsigned NOT NULL,
				discussionid INT unsigned NOT NULL,
				emailupdate SMALLINT unsigned NOT NULL default '0',
				PRIMARY KEY (subscribediscussionid),
				UNIQUE KEY userdiscussion (userid, discussionid),
				KEY discussionid (discussionid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #11
	*
	*/
	function step_11()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'socialgroupicon'),
			"CREATE TABLE " . TABLE_PREFIX . "socialgroupicon (
				groupid INT unsigned NOT NULL default '0',
				userid INT unsigned default '0',
				filedata mediumblob,
				extension VARCHAR(20) NOT NULL default '',
				dateline INT unsigned NOT NULL default '0',
				width INT unsigned NOT NULL default '0',
				height INT unsigned NOT NULL default '0',
				thumbnail_filedata mediumblob,
				thumbnail_width INT unsigned NOT NULL default '0',
				thumbnail_height INT unsigned NOT NULL default '0',
				PRIMARY KEY  (groupid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #12
	*
	*/
	function step_12()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'profileblockprivacy'),
			"CREATE TABLE " . TABLE_PREFIX . "profileblockprivacy (
				userid INT UNSIGNED NOT NULL,
				blockid varchar(255) NOT NULL,
				requirement SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (userid, blockid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #13
	*
	*/
	function step_13()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'noticedismissed'),
			"CREATE TABLE " . TABLE_PREFIX . "noticedismissed (
				noticeid INT UNSIGNED NOT NULL DEFAULT '0',
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (noticeid,userid),
				KEY userid (userid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #14
	*
	*/
	function step_14()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'event', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "event CHANGE utc utc DECIMAL(4,2) NOT NULL DEFAULT '0.0'"
		);
	}

	/**
	* Step #15
	*
	*/
	function step_15()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'prefix', 1, 1),
			'prefix',
			'options',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #16
	*
	*/
	function step_16()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'pm', 1, 1),
			'pm',
			'parentpmid',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #17
	*
	*/
	function step_17()
	{
		$this->add_field(
			$this->phrase['version']['380a2']['updating_profile_categories'],
			'profilefieldcategory',
			'allowprivacy',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #18
	*
	*/
	function step_18()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'socialgroup', 1, 5),
			'socialgroup',
			'lastdiscussionid',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #19
	*
	*/
	function step_19()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'socialgroup', 2, 5),
			'socialgroup',
			'discussions',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #20
	*
	*/
	function step_20()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'socialgroup', 3, 5),
			'socialgroup',
			'lastdiscussion',
			'varchar',
			array('length' => 255, 'attributes' => self::FIELD_DEFAULTS)
		);
	}

	/**
	* Step #21
	*
	*/
	function step_21()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'socialgroup', 4, 5),
			'socialgroup',
			'lastupdate',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #22
	*
	*/
	function step_22()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'socialgroup', 5, 5),
			'socialgroup',
			'transferowner',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #23
	*
	*/
	function step_23()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 1, 3),
			'usergroup',
			'pmthrottlequantity',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #24
	*
	*/
	function step_24()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 2, 3),
			'usergroup',
			'groupiconmaxsize',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #25
	*
	*/
	function step_25()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 3, 3),
			'usergroup',
			'maximumsocialgroups',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #26
	*
	*/
	function step_26()
	{
		$this->add_index(
			sprintf($this->phrase['version']['380a2']['create_index_on_x'], TABLE_PREFIX . 'usernote'),
			'usernote',
			'posterid',
			array('posterid')
		);
	}

	/**
	* Step #27
	*
	*/
	function step_27()
	{
		$this->drop_index(
			sprintf($this->phrase['version']['380a2']['alter_index_on_x'], TABLE_PREFIX . 'moderator'),
			'moderator',
			'userid'
		);
	}

	/**
	* Step #28
	*
	*/
	function step_28()
	{
		$this->run_query(
			sprintf($this->phrase['version']['380a2']['create_index_on_x'], TABLE_PREFIX . 'moderator'),
			"ALTER IGNORE TABLE " . TABLE_PREFIX . "moderator ADD UNIQUE INDEX userid_forumid (userid, forumid)",
			array(self::MYSQL_ERROR_KEY_EXISTS, self::MYSQL_ERROR_UNIQUE_CONSTRAINT)
		);
	}

	/**
	* Step #29
	*
	*/
	function step_29()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'groupmessage'),
			"UPDATE " . TABLE_PREFIX . 'socialgroup
			SET lastupdate = ' . TIMENOW
		);
	}

	/**
	* Step #30
	*
	*/
	function step_30()
	{
		if ($this->field_exists('groupmessage', 'groupid'))
		{
			$this->drop_index(
				sprintf($this->phrase['version']['380a2']['alter_index_on_x'], TABLE_PREFIX . 'groupmessage'),
				'groupmessage',
				'groupid'
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #31
	*
	*/
	function step_31()
	{
		if ($this->field_exists('groupmessage', 'groupid'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'groupmessage', 1, 1),
				'groupmessage',
				'discussionid',
				'int',
				self::FIELD_DEFAULTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #32
	*
	*/
	function step_32()
	{
		if ($this->field_exists('groupmessage', 'groupid'))
		{
			$this->add_index(
				sprintf($this->phrase['version']['380a2']['create_index_on_x'], TABLE_PREFIX . 'groupmessage'),
				'groupmessage',
				'discussionid',
				array('discussionid', 'dateline', 'state')
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #33
	*
	*/
	function step_33()
	{
		if ($this->field_exists('groupmessage', 'groupid'))
		{
			$this->add_index(
				sprintf($this->phrase['version']['380a2']['fulltext_index_on_x'], TABLE_PREFIX . 'groupmessage'),
				'groupmessage',
				'gm_ft',
				array('title', 'pagetext'),
				'fulltext'
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #34
	*
	*/
	function step_34()
	{
		if ($this->field_exists('groupmessage', 'groupid'))
		{
			$this->run_query(
				$this->phrase['version']['380a2']['convert_messages_to_discussion'],
				"REPLACE INTO " . TABLE_PREFIX . "discussion (groupid, firstpostid, lastpostid)
				SELECT gm.groupid, MIN(gm.gmid) AS firstpostid, MAX(gm.gmid) AS lastpostid
				FROM " . TABLE_PREFIX . "groupmessage AS gm
				LEFT JOIN " . TABLE_PREFIX . "socialgroup AS sg
				 ON sg.groupid = gm.groupid
				GROUP BY gm.groupid
			");
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #35
	*
	*/
	function step_35()
	{
		if ($this->field_exists('groupmessage', 'groupid'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'groupmessage'),
				"UPDATE " . TABLE_PREFIX . "groupmessage AS gm, " . TABLE_PREFIX . "discussion as gd
				SET gm.discussionid = gd.discussionid
				WHERE gm.groupid = gd.groupid
			");
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #36
	*
	*/
	function step_36()
	{
		if ($this->field_exists('groupmessage', 'groupid'))
		{
			$this->run_query(
				$this->phrase['version']['380a2']['set_discussion_titles'],
				"UPDATE " . TABLE_PREFIX . "groupmessage gm
				INNER JOIN " . TABLE_PREFIX . "discussion d
				 ON gm.gmid = d.firstpostid
				INNER JOIN " . TABLE_PREFIX . "socialgroup sg
				 ON sg.groupid = d.groupid
				SET gm.title = IF(gm.title='',sg.name,gm.title)
			");
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #37
	*
	*/
	function step_37()
	{
		if ($this->field_exists('groupmessage', 'groupid'))
		{
			$this->run_query(
				$this->phrase['version']['380a2']['update_last_post'],
				"UPDATE " . TABLE_PREFIX . "discussion d
				INNER JOIN " . TABLE_PREFIX . "groupmessage gm
				 ON gm.gmid = d.lastpostid
				SET d.lastpost = gm.dateline,
				    d.lastposter = gm.postusername,
				    d.lastposterid = gm.postuserid
			");
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #38
	*
	*/
	function step_38()
	{
		if ($this->field_exists('groupmessage', 'groupid'))
		{
			// Get discussion counters
			$temptable = TABLE_PREFIX . 'discussion_temp_' . TIMENOW;

			$this->run_query($this->phrase['version']['380a2']['update_discussion_counters'],
				"CREATE TABLE $temptable (
					discussionid INT unsigned NOT NULL,
					visible INT unsigned DEFAULT '0',
					moderation INT unsigned DEFAULT '0',
					deleted INT unsigned DEFAULT '0',
					PRIMARY KEY(discussionid)
				)"
			);

			$this->run_query($this->phrase['version']['380a2']['update_discussion_counters'],
				"REPLACE INTO $temptable (discussionid, visible, moderation, deleted)
				SELECT discussionid,
					SUM(IF(state = 'visible', 1, 0)) AS visible,
					SUM(IF(state = 'deleted', 1, 0)) AS deleted,
					SUM(IF(state = 'moderation', 1, 0)) AS moderation
				FROM " . TABLE_PREFIX . "groupmessage
				GROUP BY discussionid
			");

			$this->run_query($this->phrase['version']['380a2']['update_discussion_counters'],
				"UPDATE " . TABLE_PREFIX . "discussion AS d
				INNER JOIN $temptable AS temp
				 ON temp.discussionid = d.discussionid
				SET d.visible = temp.visible,
					d.moderation = temp.moderation,
					d.deleted = temp.deleted
			");

			$this->run_query($this->phrase['version']['380a2']['update_discussion_counters'],
				"DROP TABLE $temptable"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #39
	*
	*/
	function step_39()
	{
		if ($this->field_exists('groupmessage', 'groupid'))
		{
			$temptable = TABLE_PREFIX . "socialgroup" . TIMENOW;

			$this->run_query($this->phrase['version']['380a2']['update_group_message_counters'],
				"CREATE TABLE $temptable (
					groupid INT unsigned NOT NULL,
					visible INT unsigned DEFAULT '0',
					moderation INT unsigned DEFAULT '0',
					deleted INT unsigned DEFAULT '0',
					discussions INT unsigned DEFAULT '0',
					PRIMARY KEY (groupid)
				)
			");

			$this->run_query($this->phrase['version']['380a2']['update_group_message_counters'],
				"REPLACE INTO $temptable (groupid, visible, moderation, deleted, discussions)
				SELECT discussion.groupid,
						SUM(IF(state != 'visible',0,visible)) AS visible,
						SUM(deleted) AS deleted,
						SUM(moderation) AS moderation,
						SUM(IF(state = 'visible', 1, 0)) AS discussions
				FROM " . TABLE_PREFIX . "discussion AS discussion
				LEFT JOIN " . TABLE_PREFIX . "groupmessage AS gm
					ON gm.gmid = discussion.firstpostid
				GROUP BY discussion.groupid
			");

			$this->run_query($this->phrase['version']['380a2']['update_group_message_counters'],
				"UPDATE " . TABLE_PREFIX . "socialgroup AS sg
				INNER JOIN $temptable AS temp
				 ON temp.groupid = sg.groupid
				SET sg.visible = temp.visible,
					sg.moderation = temp.moderation,
					sg.deleted = temp.deleted,
					sg.discussions = temp.discussions
			");

			$this->run_query($this->phrase['version']['380a2']['update_group_message_counters'],
				"DROP TABLE $temptable"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #40
	*
	*/
	function step_40()
	{
		if ($this->field_exists('groupmessage', 'groupid'))
		{
			$this->drop_field(
				sprintf($this->phrase['core']['altering_x_table'], 'groupmessage', 1, 1),
				'groupmessage',
				'groupid'
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #41
	*
	*/
	function step_41()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'socialgroup', 1, 1),
			'socialgroup',
			'socialgroupcategoryid',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #42
	*
	*/
	function step_42()
	{
		$this->add_index(
			sprintf($this->phrase['version']['380a2']['create_index_on_x'], TABLE_PREFIX . 'socialgroup'),
			'socialgroup',
			'socialgroupcategoryid',
			array('socialgroupcategoryid')
		);
	}

	/**
	* Step #43
	*
	*/
	function step_43()
	{
		$this->run_query(
			$this->phrase['version']['380a2']['creating_default_group_category'],
			"REPLACE INTO " . TABLE_PREFIX . "socialgroupcategory
				(socialgroupcategoryid, creatoruserid, title, description, displayorder, lastupdate)
			VALUES
				(1, 1, '" . $this->db->escape_string($this->phrase['version']['380a2']['uncategorized']) . "',
				'" . $this->db->escape_string($this->phrase['version']['380a2']['uncategorized_description']) . "', 1, " . TIMENOW . ")
		");
	}

	/**
	* Step #44
	*
	*/
	function step_44()
	{
		$this->run_query(
			$this->phrase['version']['380a2']['move_groups_to_default_category'],
			"UPDATE " . TABLE_PREFIX . "socialgroup
			SET socialgroupcategoryid = 1
		");
	}

	/**
	* Step #45
	*
	*/
	function step_45()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'pmtext', 1, 1),
			'pmtext',
			'reportthreadid',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #46
	*
	*/
	function step_46()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'notice', 1, 1),
			'notice',
			'dismissible',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #47
	*
	*/
	function step_47()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'useractivation', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "useractivation CHANGE activationid activationid VARCHAR(40) NOT NULL DEFAULT ''"
		);
	}

	/**
	* Step #48
	*
	*/
	function step_48()
	{
		// Human Verification options and permissions
		if ($this->registry->options['hvcheck_registration']
			OR $this->registry->options['hvcheck_post']
			OR $this->registry->options['hvcheck_search']
			OR $this->registry->options['hvcheck_contactus']
			OR $this->registry->options['hvcheck_lostpw'])
		{
			$hvcheck = 0;
			$hvcheck += ($this->registry->options['hvcheck_registration'] ? $this->registry->bf_misc_hvcheck['register'] : 0);
			$hvcheck += ($this->registry->options['hvcheck_post'] ? $this->registry->bf_misc_hvcheck['post'] : 0);
			$hvcheck += ($this->registry->options['hvcheck_search'] ? $this->registry->bf_misc_hvcheck['search'] : 0);
			$hvcheck += ($this->registry->options['hvcheck_contactus'] ? $this->registry->bf_misc_hvcheck['contactus'] : 0);
			$hvcheck += ($this->registry->options['hvcheck_lostpw'] ? $this->registry->bf_misc_hvcheck['lostpw'] : 0);

			$this->run_query(
				$this->phrase['version']['380a2']['updating_usergroup_permissions'],
				"UPDATE " . TABLE_PREFIX . "usergroup SET
					genericpermissions = genericpermissions | " . $this->registry->bf_ugp_genericoptions['requirehvcheck'] . "
				 WHERE usergroupid = 1"
			);
		}
		else
		{
			$hvcheck = array_sum($this->registry->bf_misc_hvcheck);
		}

		$this->run_query(
			$this->phrase['version']['380a2']['update_hv_options'],
			"REPLACE INTO " . TABLE_PREFIX . "setting
				(varname, grouptitle, value, volatile, product)
			VALUES ('hvcheck', 'humanverification', $hvcheck, 1, 'vbulletin')"
		);
	}

	/**
	* Step #49
	*
	*/
	function step_49()
	{
		$this->run_query(
			sprintf($this->phrase['version']['380a2']['granting_permissions'], 'usergroup', 1, 1),
			"UPDATE " . TABLE_PREFIX . "usergroup SET
				usercsspermissions = usercsspermissions |
					IF(forumpermissions & " . $this->registry->bf_ugp_genericpermissions['canmodifyprofile'] . ", " . $this->registry->bf_ugp_usercsspermissions['caneditprivacy'] . ", 0),
				forumpermissions = forumpermissions |
					IF(forumpermissions & " . $this->registry->bf_ugp_forumpermissions['cangetattachment'] . ", " . $this->registry->bf_ugp_forumpermissions['canseethumbnails'] . ", 0),
				genericoptions = genericoptions |
					IF(usergroupid = 1," . $this->registry->bf_ugp_genericoptions['requirehvcheck'] . ", 0),
				socialgrouppermissions = socialgrouppermissions |
					IF(forumpermissions & " . $this->registry->bf_ugp_forumpermissions['canreplyown'] . ", " . $this->registry->bf_ugp_socialgrouppermissions['canpostmessage'] . ", 0) |
					IF(adminpermissions & " . $this->registry->bf_ugp_adminpermissions['ismoderator'] . ", " . $this->registry->bf_ugp_socialgrouppermissions['canalwayspostmessage'] . ", 0) |
					IF(forumpermissions & " . $this->registry->bf_ugp_forumpermissions['canpostnew'] . ", " . $this->registry->bf_ugp_socialgrouppermissions['cancreatediscussion'] . ", 0) |
					IF(adminpermissions & " . $this->registry->bf_ugp_adminpermissions['ismoderator'] . ", " . $this->registry->bf_ugp_socialgrouppermissions['canalwayscreatediscussion'] . ", 0) |
					IF(forumpermissions & " . $this->registry->bf_ugp_forumpermissions['canopenclose'] . ", " . $this->registry->bf_ugp_socialgrouppermissions['canlimitdiscussion'] . ", 0) |
					IF(forumpermissions & " . $this->registry->bf_ugp_forumpermissions['candeletethread'] . ", " . $this->registry->bf_ugp_socialgrouppermissions['canmanagediscussions'] . ", 0) |
					IF(genericpermissions & " . $this->registry->bf_ugp_genericpermissions['canprofilepic'] . ", " . $this->registry->bf_ugp_socialgrouppermissions['canuploadgroupicon'] . ", 0) |
					IF(genericpermissions & " . $this->registry->bf_ugp_genericpermissions['cananimateprofilepic'] . ", " . $this->registry->bf_ugp_socialgrouppermissions['cananimategroupicon'] . ", 0),
				groupiconmaxsize = profilepicmaxsize,
				pmthrottlequantity = 0,
				maximumsocialgroups = 5
			"
		);
	}

	/**
	* Step #50
	*
	*/
	function step_50()
	{
		$this->run_query(
			sprintf($this->phrase['version']['380a2']['granting_permissions'], 'forumpermission', 1, 1),
			"UPDATE " . TABLE_PREFIX . "forumpermission SET
				forumpermissions = forumpermissions |
					IF(forumpermissions & " . $this->registry->bf_ugp_forumpermissions['cangetattachment'] . ", " . $this->registry->bf_ugp_forumpermissions['canseethumbnails'] . ", 0)
			"
		);
	}

	/**
	* Step #51
	*
	*/
	function step_51()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'moderator', 1, 1),
			"UPDATE " . TABLE_PREFIX . "moderator SET
				permissions2 = permissions2 |
					IF(permissions & " . $this->registry->bf_misc_moderatorpermissions2['caneditgroupmessages'] . ", " . $this->registry->bf_misc_moderatorpermissions2['caneditsocialgroups'] . ", 0) |
					IF(permissions & " . $this->registry->bf_misc_moderatorpermissions2['candeletegroupmessages'] . ", " . $this->registry->bf_misc_moderatorpermissions2['candeletediscussions'] . ", 0) |
					IF(permissions & " . $this->registry->bf_misc_moderatorpermissions2['candeletesocialgroups'] . ", " . $this->registry->bf_misc_moderatorpermissions2['cantransfersocialgroups'] . ", 0) |
					IF(permissions & " . $this->registry->bf_misc_moderatorpermissions2['canremovegroupmessages'] . ", " . $this->registry->bf_misc_moderatorpermissions2['canremovediscussions'] . ", 0) |
					IF(permissions & " . $this->registry->bf_misc_moderatorpermissions2['canmoderategroupmessages'] . ", " . $this->registry->bf_misc_moderatorpermissions2['canmoderatediscussions'] . ", 0)
			"
		);
	}

	/**
	* Step #52
	*
	*/
	function step_52()
	{
		$this->show_message($this->phrase['version']['380a2']['update_album_update_counters']);
		require_once(DIR . '/includes/functions_album.php');
		$this->registry->options['album_recentalbumdays'] = 7;
		exec_rebuild_album_updates();

		$this->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "phrase
			WHERE varname LIKE 'notice\_%\_title'
				AND fieldname = 'global'
		");

		require_once(DIR . '/includes/adminfunctions_prefix.php');
		build_prefix_datastore();
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/