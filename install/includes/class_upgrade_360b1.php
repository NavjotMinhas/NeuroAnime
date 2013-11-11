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

class vB_Upgrade_360b1 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '360b1';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '3.6.0 Beta 1';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '3.5.4+';

	/**
	* Beginning version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_STARTS = '3.5.4';

	/**
	* Ending version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_ENDS   = '3.5.99';

	/**
	* Step #1
	*
	*/
	function step_1()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'post', 1, 1),
			'post',
			'infraction',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #2
	*
	*/
	function step_2()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'thread', 1, 1),
			'thread',
			'deletedcount',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #3
	*
	*/
	function step_3()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'post', 1, 1),
			'post',
			'reportthreadid',
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
			sprintf($this->phrase['core']['altering_x_table'], 'thread', 1, 1),
			'thread',
			'lastpostid',
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
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'setting', 1, 2),
			"ALTER TABLE " . TABLE_PREFIX . "setting CHANGE datatype datatype ENUM('free', 'number', 'boolean', 'bitfield', 'username') NOT NULL DEFAULT 'free'"
		);
	}

	/**
	* Step #6
	*
	*/
	function step_6()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'setting', 2, 2),
			'setting',
			'blacklist',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #7
	*
	*/
	function step_7()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'forum', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "forum CHANGE childlist childlist TEXT"
		);
	}

	/**
	* Step #8
	*
	*/
	function step_8()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "externalcache"),
			"CREATE TABLE " . TABLE_PREFIX . "externalcache (
				cachehash CHAR(32) NOT NULL default '',
				text MEDIUMTEXT,
				headers MEDIUMTEXT,
				dateline INT UNSIGNED NOT NULL default '0',
				PRIMARY KEY (cachehash),
				KEY dateline (dateline, cachehash)
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
		// Go medieval on phrases
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'phrase', 1, 7),
			'phrase',
			'fieldname',
			'varchar',
			array('length' => 20, 'attributes' => self::FIELD_DEFAULTS)
		);
	}

	/**
	* Step #10
	*
	*/
	function step_10()
	{
		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'phrase', 2, 7),
			'phrase',
			'languageid'
		);
	}

	/**
	* Step #11
	*
	*/
	function step_11()
	{
		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'phrase', 3, 7),
			'phrase',
			'name_lang_type'
		);
	}

	/**
	* Step #12
	*
	*/
	function step_12()
	{
		if ($this->field_exists('phrase', 'phrasetypeid'))
		{
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'phrase', 4, 7),
				"UPDATE " . TABLE_PREFIX . "phrase AS phrase, " . TABLE_PREFIX . "phrasetype AS phrasetype
					SET phrase.fieldname = phrasetype.fieldname
				WHERE phrase.phrasetypeid = phrasetype.phrasetypeid"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #13
	*
	*/
	function step_13()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'phrase', 5, 7),
			'phrase',
			'languageid',
			array('languageid', 'fieldname')
		);
	}

	/**
	* Step #14
	*
	*/
	function step_14()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'phrase', 6, 7),
			"ALTER IGNORE TABLE " . TABLE_PREFIX . "phrase ADD UNIQUE INDEX
				name_lang_type (varname, languageid, fieldname)",
			self::MYSQL_ERROR_KEY_EXISTS
		);
	}

	/**
	* Step #15
	*
	*/
	function step_15()
	{
		$this->drop_field(
			sprintf($this->phrase['core']['altering_x_table'], 'phrase', 7, 7),
			'phrase',
			'phrasetypeid'
		);
	}

	/**
	* Step #16
	*
	*/
	function step_16()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'phrasetype', 1, 5),
			'phrasetype',
			'special',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #17
	*
	*/
	function step_17()
	{
		if ($this->field_exists('phrasetype', 'phrasetypeid'))
		{
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'phrasetype', 2, 5),
				"UPDATE " . TABLE_PREFIX . "phrasetype SET special = 1 WHERE phrasetypeid >= 1000"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #18
	*
	*/
	function step_18()
	{
		$this->drop_field(
			sprintf($this->phrase['core']['altering_x_table'], 'phrasetype', 3, 5),
			'phrasetype',
			'phrasetypeid'
		);
	}

	/**
	* Step #19
	*
	*/
	function step_19()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'phrasetype', 4, 5),
			"ALTER IGNORE TABLE " . TABLE_PREFIX . "phrasetype ADD PRIMARY KEY (fieldname)",
			self::MYSQL_ERROR_PRIMARY_KEY_EXISTS
		);
	}

	/**
	* Step #20
	*
	*/
	function step_20()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'phrasetype', 5, 5),
			"DELETE FROM " . TABLE_PREFIX . "phrasetype WHERE fieldname = ''"
		);
	}

	/**
	* Step #21
	*
	*/
	function step_21()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'product', 1, 2),
			'product',
			'url',
			'varchar',
			array('length' => 250, 'attributes' => self::FIELD_DEFAULTS)
		);
	}

	/**
	* Step #22
	*
	*/
	function step_22()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'product', 2, 2),
			'product',
			'versioncheckurl',
			'varchar',
			array('length' => 250, 'attributes' => self::FIELD_DEFAULTS)
		);
	}

	/**
	* Step #23
	*
	*/
	function step_23()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "productdependency"),
			"CREATE TABLE " . TABLE_PREFIX . "productdependency (
				productdependencyid INT NOT NULL AUTO_INCREMENT,
				productid varchar(25) NOT NULL DEFAULT '',
				dependencytype varchar(25) NOT NULL DEFAULT '',
				parentproductid varchar(25) NOT NULL DEFAULT '',
				minversion varchar(50) NOT NULL DEFAULT '',
				maxversion varchar(50) NOT NULL DEFAULT '',
				PRIMARY KEY (productdependencyid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #24
	*
	*/
	function step_24()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'plugin', 1, 1),
			'plugin',
			'executionorder',
			'smallint',
			array('null' => false, 'default' => 5)
		);
	}

	/**
	* Step #25
	*
	*/
	function step_25()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'event', 1, 2),
			'event',
			'dst',
			'smallint',
			array('attributes' => 'UNSIGNED', 'null' => false, 'default' => 1)
		);
	}

	/**
	* Step #26
	*
	*/
	function step_26()
	{
		// now we need to update the actual entry
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'event', 2, 2),
			"UPDATE " . TABLE_PREFIX . "event SET
				dst = 0
			WHERE utc = 0"
		);
	}

	/**
	* Step #27
	*
	*/
	function step_27()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'subscription', 1, 1),
			'subscription',
			'adminoptions',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #28
	*
	*/
	function step_28()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'user', 1, 2),
			'user',
			'adminoptions',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #29
	*
	*/
	function step_29()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'user', 2, 2),
			'user',
			'lastpostid',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #30
	*
	*/
	function step_30()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'cron', 1, 6),
			'cron',
			'active',
			'smallint',
			array('attributes' => 'UNSIGNED', 'null' => false, 'default' => 1)
		);
	}

	/**
	* Step #31
	*
	*/
	function step_31()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'cron', 2, 6),
			'cron',
			'varname',
			'varchar',
			array('length' => 100, 'attributes' => self::FIELD_DEFAULTS)
		);
	}

	/**
	* Step #32
	*
	*/
	function step_32()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'cron', 3, 6),
			'cron',
			'volatile',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #33
	*
	*/
	function step_33()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'cron', 4, 6),
			'cron',
			'product',
			'varchar',
			array('length' => 25, 'attributes' => self::FIELD_DEFAULTS)
		);
	}

	/**
	* Step #34
	*
	*/
	function step_34()
	{
		if ($this->field_exists('cron', 'title'))
		{
			$updates = array();
			$cronfiles = array(
				'dailycleanup', 'birthday', 'threadviews', 'promotion', 'digestdaily', 'digestweekly', 'subscriptions',
				'cleanup', 'attachmentviews', 'activate', 'removebans', 'cleanup2', 'stats', 'reminder', 'infractions', 'ccbill', 'rssposter'
			);
			$cron = $this->db->query_read("
				SELECT cronid, filename, title
				FROM " . TABLE_PREFIX . "cron
				WHERE varname = ''
			");
			while ($croninfo = $this->db->fetch_array($cron))
			{
				$create_cron_phrases = true;

				$has_file_match = preg_match('#([a-z0-9_]+)\.php$#si', $croninfo['filename'], $match);
				if ($has_file_match AND in_array(strtolower($match[1]), $cronfiles))
				{
					$croninfo['varname'] = strtolower($match[1]);
					$croninfo['volatile'] = 1;

					// phrases are the XML already, don't need to create them
					$create_cron_phrases = false;
				}
				else if ($has_file_match)
				{
					// have a filename, that's a good way to prepend
					$croninfo['varname'] = strtolower($match[1]) . $croninfo['cronid'];
					$croninfo['volatile'] = 0;
				}
				else
				{
					$croninfo['varname'] = 'task' . $croninfo['cronid'];
					$croninfo['volatile'] = 0;
				}

				if ($create_cron_phrases)
				{
					$title = 'task_' . $this->db->escape_string($croninfo['varname']) . '_title';
					$desc = 'task_' . $this->db->escape_string($croninfo['varname']) . '_desc';
					$log = 'task_' . $this->db->escape_string($croninfo['varname']) . '_log';

					$this->run_query(
						sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "phrase"),
						"REPLACE INTO " . TABLE_PREFIX . "phrase
							(languageid,  fieldname, varname, text, product)
						VALUES
							(0, 'cron', '$title', '" . $this->db->escape_string($croninfo['title']) . "', 'vbulletin'),
							(0, 'cron', '$desc', '', 'vbulletin'),
							(0, 'cron', '$log', '', 'vbulletin')"
					);
				}

				// now we need to update the actual entry
				$this->run_query(
					$this->phrase['version']['360b1']['updating_cron'],
					"UPDATE " . TABLE_PREFIX . "cron SET
						varname = '" . $this->db->escape_string($croninfo['varname']) . "',
						volatile = $croninfo[volatile]
					WHERE cronid = $croninfo[cronid]"
				);
			}
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
		$this->drop_field(
			sprintf($this->phrase['core']['altering_x_table'], 'cron', 5, 6),
			'cron',
			'title'
		);
	}

	/**
	* Step #36
	*
	*/
	function step_36()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'cron', 6, 6),
			"ALTER IGNORE TABLE " . TABLE_PREFIX . "cron ADD UNIQUE INDEX varname (varname)",
			self::MYSQL_ERROR_KEY_EXISTS
		);
	}

	/**
	* Step #37
	*
	*/
	function step_37()
	{
		$this->add_cronjob(
			array(
				'varname'  => 'dailycleanup',
				'nextrun'  => 1053533100,
				'weekday'  => -1,
				'day'      => -1,
				'hour'     => 0,
				'minute'   => 'a:1:{i:0;i:10;}',
				'filename' => './includes/cron/dailycleanup.php',
				'loglevel' => 0,
				'volatile' => 1,
				'product'  => 'vbulletin'
			)
		);
	}

	/**
	* Step #38
	*
	*/
	function step_38()
	{
		$this->add_cronjob(
			array(
				'varname'  => 'rssposter',
				'nextrun'  => 0,
				'weekday'  => -1,
				'day'      => -1,
				'hour'     => -1,
				'minute'   => 'a:6:{i:0;i:0;i:1;i:10;i:2;i:20;i:3;i:30;i:4;i:40;i:5;i:50;}',
				'filename' => './includes/cron/rssposter.php',
				'loglevel' => 1,
				'volatile' => 1,
				'product'  => 'vbulletin'
			)
		);
	}

	/**
	* Step #39
	*
	*/
	function step_39()
	{
		$this->add_cronjob(
			array(
				'varname'  => 'infractions',
				'nextrun'  => 1053533100,
				'weekday'  => -1,
				'day'      => -1,
				'hour'     => -1,
				'minute'   => 'a:2:{i:0;i:20;i:1;i:50;}',
				'filename' => './includes/cron/infractions.php',
				'loglevel' => 1,
				'volatile' => 1,
				'product'  => 'vbulletin'
			)
		);
	}

	/**
	* Step #40
	*
	*/
	function step_40()
	{
		$this->add_cronjob(
			array(
				'varname'  => 'ccbill',
				'nextrun'  => 1053533100,
				'weekday'  => -1,
				'day'      => -1,
				'hour'     => -1,
				'minute'   => 'a:1:{i:0;i:10;}',
				'filename' => './includes/cron/ccbill.php',
				'loglevel' => 1,
				'volatile' => 1,
				'product'  => 'vbulletin'
			)
		);
	}

	/**
	* Step #41
	*
	*/
	function step_41()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'cronlog', 1, 5),
			'cronlog',
			'type',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #42
	*
	*/
	function step_42()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'cronlog', 2, 5),
			'cronlog',
			'varname',
			'varchar',
			array('length' => 100, 'attributes' => self::FIELD_DEFAULTS)
		);
	}

	/**
	* Step #43
	*
	*/
	function step_43()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'cronlog', 3, 5),
			'cronlog',
			'varname',
			'varname'
		);
	}

	/**
	* Step #44
	*
	*/
	function step_44()
	{
		if ($this->field_exists('cronlog', 'cronid'))
		{
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'cronlog', 4, 5),
				"UPDATE " . TABLE_PREFIX . "cronlog AS cronlog, " . TABLE_PREFIX . "cron AS cron SET
					cronlog.varname = cron.varname
				WHERE cronlog.cronid = cron.cronid"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #45
	*
	*/
	function step_45()
	{
		$this->drop_field(
			sprintf($this->phrase['core']['altering_x_table'], 'cronlog', 5, 5),
			'cronlog',
			'cronid'
		);
	}

	/**
	* Step #46
	*
	*/
	function step_46()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'announcement', 1, 1),
			'announcement',
			'enddate',
			array('enddate', 'forumid', 'startdate')
		);
	}

	/**
	* Step #47
	*
	*/
	function step_47()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "announcementread"),
			"CREATE TABLE " . TABLE_PREFIX . "announcementread (
				announcementid INT UNSIGNED NOT NULL DEFAULT '0',
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (announcementid, userid),
				KEY userid (userid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #48
	*
	*/
	function step_48()
	{
		if (!$this->field_exists('search', 'announceids'))
		{
			// this must only be run once, so make sure the query that follows hasn't been run
			$this->run_query(
				$this->phrase['version']['360b1']['invert_banned_flag'],
				"UPDATE " . TABLE_PREFIX . "usergroup
					SET genericoptions = IF(genericoptions & 32, genericoptions - 32, genericoptions + 32)"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #49
	*
	*/
	function step_49()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'search', 1, 1),
			'search',
			'announceids',
			'mediumtext',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #50
	*
	*/
	function step_50()
	{
		if ($this->field_exists('subscription', 'description'))
		{
			// Phrasing for Subscriptions
			$subs = $this->db->query_read("
				SELECT subscriptionid, title, description
				FROM " . TABLE_PREFIX . "subscription
			");
			while ($sub = $this->db->fetch_array($subs))
			{
				$title = 'sub' . $sub['subscriptionid'] . '_title';
				$desc = 'sub' . $sub['subscriptionid'] . '_desc';

				$this->run_query(
					$this->phrase['version']['360b1']['updating_subscriptions'],
					"REPLACE INTO " . TABLE_PREFIX . "phrase
						(languageid, fieldname, varname, text, product)
					VALUES
						(0, 'subscription', '$title', '" . $this->db->escape_string($sub['title']) . "', 'vbulletin'),
						(0, 'subscription', '$desc', '" . $this->db->escape_string($sub['description']) . "', 'vbulletin')"
				);
			}

			if (!$this->db->num_rows($subs))
			{
				$this->skip_message();
			}
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #51
	*
	*/
	function step_51()
	{
		$this->drop_field(
			sprintf($this->phrase['core']['altering_x_table'], 'subscription', 1, 2),
			'subscription',
			'title'
		);
	}

	/**
	* Step #52
	*
	*/
	function step_52()
	{
		$this->drop_field(
			sprintf($this->phrase['core']['altering_x_table'], 'subscription', 2, 2),
			'subscription',
			'description'
		);
	}

	/**
	* Step #53
	*
	*/
	function step_53()
	{
		if ($this->field_exists('holiday', 'varname'))
		{
			// Phrase changes for Holidays (remove varname, simplify)
			$holidays = $this->db->query_read("
				SELECT holidayid, varname
				FROM " . TABLE_PREFIX . "holiday
			");
			while ($holiday = $this->db->fetch_array($holidays))
			{
				$this->run_query(
					'', // only output one message per holiday
					"UPDATE IGNORE " . TABLE_PREFIX . "phrase
						SET varname = 'holiday" . $holiday['holidayid'] . "_title'
					WHERE varname = 'holiday_title_" . $this->db->escape_string($holiday['varname']) . "'"
				);

				$this->run_query(
					$this->phrase['version']['360b1']['updating_holidays'],
					"UPDATE IGNORE " . TABLE_PREFIX . "phrase
						SET varname = 'holiday" . $holiday['holidayid'] . "_desc'
					WHERE varname = 'holiday_event_" . $this->db->escape_string($holiday['varname']) . "'"
				);
			}
			if (!$this->db->num_rows($holidays))
			{
				$this->skip_message();
			}
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #54
	*
	*/
	function step_54()
	{
		$this->drop_field(
			sprintf($this->phrase['core']['altering_x_table'], 'holiday', 1, 1),
			'holiday',
			'varname'
		);
	}

	/**
	* Step #55
	*
	*/
	function step_55()
	{
		if ($this->field_exists('profilefield', 'description'))
		{
			// Phrasing for custom profilefields
			$fields = $this->db->query_read("
				SELECT title, description, profilefieldid
				FROM " . TABLE_PREFIX . "profilefield
			");
			while ($field = $this->db->fetch_array($fields))
			{
				$title = 'field' . $field['profilefieldid'] . '_title';
				$desc = 'field' . $field['profilefieldid'] . '_desc';

				$this->run_query(
					$this->phrase['version']['360b1']['updating_profilefields'],
					"REPLACE INTO " . TABLE_PREFIX . "phrase
						(languageid, fieldname, varname, text, product)
					VALUES
						(0, 'cprofilefield', '$title', '" . $this->db->escape_string($field['title']) . "', 'vbulletin'),
						(0, 'cprofilefield', '$desc', '" . $this->db->escape_string($field['description']) . "', 'vbulletin')"
				);
			}

			if (!$this->db->num_rows($fields))
			{
				$this->skip_message();
			}
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #56
	*
	*/
	function step_56()
	{
		$this->drop_field(
			sprintf($this->phrase['core']['altering_x_table'], 'profilefield', 1, 2),
			'profilefield',
			'title'
		);
	}

	/**
	* Step #57
	*
	*/
	function step_57()
	{
		$this->drop_field(
			sprintf($this->phrase['core']['altering_x_table'], 'profilefield', 2, 2),
			'profilefield',
			'description'
		);
	}

	/**
	* Step #58
	*
	*/
	function step_58()
	{
		if ($this->field_exists('reputationlevel', 'level'))
		{
			// Phrasing for Reputation Levels
			$levels = $this->db->query_read("
				SELECT level, reputationlevelid
				FROM " . TABLE_PREFIX . "reputationlevel
			");
			while ($level = $this->db->fetch_array($levels))
			{
				$desc = 'reputation' . $level['reputationlevelid'];

				$this->run_query(
					$this->phrase['version']['360b1']['updating_reputationlevels'],
					"REPLACE INTO " . TABLE_PREFIX . "phrase
						(languageid, fieldname, varname, text, product)
					VALUES
						(0, 'reputationlevel', '$desc', '" . $this->db->escape_string($level['level']) . "', 'vbulletin')"
				);
			}
			if (!$this->db->num_rows($levels))
			{
				$this->skip_message();
			}
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #59
	*
	*/
	function step_59()
	{
		$this->drop_field(
			sprintf($this->phrase['core']['altering_x_table'], 'reputationlevel', 1, 1),
			'reputationlevel',
			'level'
		);
	}

	/**
	* Step #60
	*
	*/
	function step_60()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'language', 1, 2),
			'language',
			'phrasegroup_cprofilefield',
			'mediumtext',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #61
	*
	*/
	function step_61()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'language', 2, 2),
			'language',
			'phrasegroup_reputationlevel',
			'mediumtext',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #62
	*
	*/
	function step_62()
	{
		// update phrase group list
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "phrasetype"),
			"INSERT IGNORE INTO " . TABLE_PREFIX . "phrasetype
				(title, editrows, fieldname)
			VALUES
				('{$phrasetype['cprofilefield']}', 3, 'cprofilefield'),
				('{$phrasetype['reputationlevel']}', 3, 'reputationlevel')"
		);
	}

	/**
	* Step #63
	*
	*/
	function step_63()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "podcast"),
			"CREATE TABLE " . TABLE_PREFIX . "podcast (
				forumid INT UNSIGNED NOT NULL DEFAULT '0',
				author VARCHAR(255) NOT NULL DEFAULT '',
				category VARCHAR(255) NOT NULL DEFAULT '',
				image VARCHAR(255) NOT NULL DEFAULT '',
				explicit SMALLINT NOT NULL DEFAULT '0',
				enabled SMALLINT NOT NULL DEFAULT '1',
				keywords VARCHAR(255) NOT NULL DEFAULT '',
				owneremail VARCHAR(255) NOT NULL DEFAULT '',
				ownername VARCHAR(255) NOT NULL DEFAULT '',
				subtitle VARCHAR(255) NOT NULL DEFAULT '',
				summary MEDIUMTEXT,
				categoryid SMALLINT NOT NULL DEFAULT '0',
				PRIMARY KEY  (forumid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #64
	*
	*/
	function step_64()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'announcement', 1, 5),
			'announcement',
			'announcementoptions',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #65
	*
	*/
	function step_65()
	{
		if ($this->field_exists('announcement', 'allowsmilies'))
		{
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'announcement', 2, 5),
				"UPDATE " . TABLE_PREFIX . "announcement
					SET announcementoptions = 0
						+ IF(allowbbcode, 1, 0)
						+ IF(allowhtml, 2, 0)
						+ IF(allowsmilies, 4, 0)
						+ 8  # parseurl = yes
						+ 16 # signature = yes"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #66
	*
	*/
	function step_66()
	{
		$this->drop_field(
			sprintf($this->phrase['core']['altering_x_table'], 'announcement', 3, 5),
			'announcement',
			'allowbbcode'
		);
	}

	/**
	* Step #67
	*
	*/
	function step_67()
	{
		$this->drop_field(
			sprintf($this->phrase['core']['altering_x_table'], 'announcement', 4, 5),
			'announcement',
			'allowhtml'
		);
	}

	/**
	* Step #68
	*
	*/
	function step_68()
	{
		$this->drop_field(
			sprintf($this->phrase['core']['altering_x_table'], 'announcement', 5, 5),
			'announcement',
			'allowsmilies'
		);
	}

	/**
	* Step #69
	*
	*/
	function step_69()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'faq', 1, 1),
			'faq',
			'product',
			'varchar',
			array('length' => 25, 'attributes' => self::FIELD_DEFAULTS)
		);
	}

	/**
	* Step #70
	*
	*/
	function step_70()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'forum', 1, 1),
			'forum',
			'lastpostid',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #71
	*
	*/
	function step_71()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'tachythreadpost', 1, 1),
			'tachythreadpost',
			'lastpostid',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #72
	*
	*/
	function step_72()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'tachyforumpost', 1, 1),
			'tachyforumpost',
			'lastpostid',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #73
	*
	*/
	function step_73()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "sigpic"),
			"CREATE TABLE " . TABLE_PREFIX . "sigpic (
				  userid int unsigned NOT NULL default '0',
				  filedata mediumblob,
				  dateline int unsigned NOT NULL default '0',
				  filename varchar(100) NOT NULL default '',
				  visible smallint NOT NULL default '1',
				  filesize int unsigned NOT NULL default '0',
				  width smallint unsigned NOT NULL default '0',
				  height smallint unsigned NOT NULL default '0',
				  PRIMARY KEY  (userid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #74
	*
	*/
	function step_74()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 1, 9),
			'usergroup',
			'signaturepermissions',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #75
	*
	*/
	function step_75()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 2, 9),
			'usergroup',
			'sigpicmaxwidth',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #76
	*
	*/
	function step_76()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 3, 9),
			'usergroup',
			'sigpicmaxheight',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #77
	*
	*/
	function step_77()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 4, 9),
			'usergroup',
			'sigpicmaxsize',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #78
	*
	*/
	function step_78()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 5, 9),
			'usergroup',
			'sigmaximages',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #79
	*
	*/
	function step_79()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 6, 9),
			'usergroup',
			'sigmaxsizebbcode',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #80
	*
	*/
	function step_80()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 7, 9),
			'usergroup',
			'sigmaxchars',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #81
	*
	*/
	function step_81()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 8, 9),
			'usergroup',
			'sigmaxrawchars',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #82
	*
	*/
	function step_82()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 9, 9),
			'usergroup',
			'sigmaxlines',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #83
	*
	*/
	function step_83()
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
	* Step #84
	*
	*/
	function step_84()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "sigparsed"),
			"CREATE TABLE " . TABLE_PREFIX . "sigparsed (
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				styleid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				languageid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				signatureparsed MEDIUMTEXT,
				hasimages SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (userid, styleid, languageid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #85
	*
	*/
	function step_85()
	{
		if (!$this->field_exists('setting', 'validationcode'))
		{
			// give any group that has sig perms permission to use the appropriate bbcodes, etc
			// the "can have signature" perm has existed for a while, so that will take precedence over these settings
			$sig_perm_bits =(
				(($this->registry->options['allowedbbcodes'] & 1) ? 1 : 0) + // basic bb codes
				(($this->registry->options['allowedbbcodes'] & 2) ? 2 : 0) + // color bb code
				(($this->registry->options['allowedbbcodes'] & 4) ? 4 : 0) + // size bb code
				(($this->registry->options['allowedbbcodes'] & 8) ? 8 : 0) + // font bb code
				(($this->registry->options['allowedbbcodes'] & 16) ? 16 : 0) + // align bb codes
				(($this->registry->options['allowedbbcodes'] & 32) ? 32 : 0) + // list bb code
				(($this->registry->options['allowedbbcodes'] & 64) ? 64 : 0) + // link bb codes
				(($this->registry->options['allowedbbcodes'] & 128) ? 128 : 0) + // code bb code
				(($this->registry->options['allowedbbcodes'] & 256) ? 256 : 0) + // php bb code
				(($this->registry->options['allowedbbcodes'] & 512) ? 512 : 0) + // html bb code
				1024 + // quote is always allowed
				($this->registry->options['allowbbimagecode'] ? 2048 : 0) + // images
				($this->registry->options['allowsmilies'] ? 4096 : 0) + // smilies
				($this->registry->options['allowhtml'] ? 8192 : 0) + // html
				// 16384 isn't used
				// 32768 = sig pics, handled in query itself
				// 65536 = can upload animated sig pics, handled in query
				($this->registry->options['allowbbcode'] ? 131072 : 0) // global bbcode switch
			);

			$can_cp_sql = "adminpermissions & " . $this->registry->bf_ugp_adminpermissions['cancontrolpanel'];

			// this has been removed from vbulletin-settings.xml so may possibly be missing if they used the new xml file before the upgrade
			$this->registry->options['sigmax'] = (isset($this->registry->options['sigmax']) ? $this->registry->options['sigmax'] : 500);

			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 1, 1),
				"UPDATE " . TABLE_PREFIX . "usergroup SET
					signaturepermissions = $sig_perm_bits
						+ IF(genericpermissions & " . $this->registry->bf_ugp_genericpermissions['canuseavatar'] . ", 32768, 0) # sig pic
						+ IF(genericpermissions & " . $this->registry->bf_ugp_genericpermissions['cananimateavatar'] . ", 65536, 0), # animated sig pic
					sigmaxrawchars = IF($can_cp_sql, 0, " . intval(2 * $this->registry->options['sigmax']) . "),
					sigmaxchars = IF($can_cp_sql, 0, " . intval($this->registry->options['sigmax']) . "),
					sigmaxlines = 0,
					sigmaxsizebbcode = 7,
					sigmaximages = IF($can_cp_sql, 0, " . intval($this->registry->options['maximages']) . "),
					sigpicmaxwidth = 500,
					sigpicmaxheight = 100,
					sigpicmaxsize = 20000
				"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #86
	*
	*/
	function step_86()
	{
		// add validation code to settings table
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'setting', 1, 1),
			'setting',
			'validationcode',
			'text',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #87
	*
	*/
	function step_87()
	{
		// rename the post_parsed table so none of our tables have underscores
		$this->run_query(
			 sprintf($this->phrase['version']['360b1']['rename_post_parsed'], TABLE_PREFIX),
			 "ALTER TABLE " . TABLE_PREFIX . "post_parsed RENAME " . TABLE_PREFIX . "postparsed",
			 self::MYSQL_ERROR_TABLE_MISSING
		);
	}

	/**
	* Step #88
	*
	*/
	function step_88()
	{
		// update thread redirects to have TIMENOW for dateline
		$this->run_query(
			$this->phrase['version']['360b1']['updating_thread_redirects'],
			"UPDATE " . TABLE_PREFIX . "thread
				SET dateline = " . TIMENOW . "
			WHERE open = 10
				AND pollid > 0"
		);
	}

	/**
	* Step #89
	*
	*/
	function step_89()
	{
		// set canignorequota for usergroups 5, 6 and 7
		if (!$this->field_exists('forum', 'showprivate'))
		{
			$this->run_query(
				$this->phrase['version']['360b1']['install_canignorequota_permission'],
				"UPDATE " . TABLE_PREFIX . "usergroup
					SET pmpermissions = pmpermissions + 4
				 WHERE usergroupid IN (5, 6, 7) AND NOT (pmpermissions & 4)"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #90
	*
	*/
	function step_90()
	{
		// add per-forum setting to show/hide private forums
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'forum', 1, 3),
			'forum',
			'showprivate',
			'tinyint',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #91
	*
	*/
	function step_91()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'forum', 2, 3),
			'forum',
			'defaultsortfield',
			'varchar',
			array('length' => 50, 'null' => false, 'default' => 'lastpost')
		);
	}

	/**
	* Step #92
	*
	*/
	function step_92()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'forum', 3, 3),
			'forum',
			'defaultsortorder',
			'enum',
			array('attributes' => "('asc', 'desc')", 'null' => false, 'default' => 'desc')
		);
	}
	/**
	* Step #93
	*
	*/
	function step_93()
	{
		// Infraction Table
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "infraction"),
			"CREATE TABLE " . TABLE_PREFIX . "infraction (
				infractionid INT UNSIGNED NOT NULL AUTO_INCREMENT ,
				infractionlevelid INT UNSIGNED NOT NULL DEFAULT '0',
				postid INT UNSIGNED NOT NULL DEFAULT '0',
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				whoadded INT UNSIGNED NOT NULL DEFAULT '0',
				points INT UNSIGNED NOT NULL DEFAULT '0',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				note varchar(255) NOT NULL DEFAULT '',
				action SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				actiondateline INT UNSIGNED NOT NULL DEFAULT '0',
				actionuserid INT UNSIGNED NOT NULL DEFAULT '0',
				actionreason VARCHAR(255) NOT NULL DEFAULT '',
				expires INT UNSIGNED NOT NULL DEFAULT '0',
				threadid INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (infractionid),
				KEY expires (expires, action),
				KEY userid (userid, action),
				KEY infractonlevelid (infractionlevelid),
				KEY postid (postid),
				KEY threadid (threadid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #94
	*
	*/
	function step_94()
	{
		// Infraction Groups Table
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "infractiongroup"),
			"CREATE TABLE " . TABLE_PREFIX . "infractiongroup (
				infractiongroupid INT UNSIGNED NOT NULL AUTO_INCREMENT ,
				usergroupid INT NOT NULL DEFAULT '0',
				orusergroupid SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				pointlevel INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (infractiongroupid),
				KEY usergroupid (usergroupid, pointlevel)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #95
	*
	*/
	function step_95()
	{
		// Infraction Level Table
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "infractionlevel"),
			"CREATE TABLE " . TABLE_PREFIX . "infractionlevel (
				infractionlevelid INT UNSIGNED NOT NULL AUTO_INCREMENT ,
				points INT UNSIGNED NOT NULL DEFAULT '0',
				expires INT UNSIGNED NOT NULL DEFAULT '0',
				period ENUM('H','D','M','N') DEFAULT 'H' NOT NULL,
				warning SMALLINT UNSIGNED DEFAULT '0',
				PRIMARY KEY (infractionlevelid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #96
	*
	*/
	function step_96()
	{
		// Add new language Groups
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'language', 1, 2),
			'language',
			'phrasegroup_infraction',
			'mediumtext',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #97
	*
	*/
	function step_97()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'language', 2, 2),
			'language',
			'phrasegroup_infractionlevel',
			'mediumtext',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #98
	*
	*/
	function step_98()
	{
		// Add new phrase groups
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "phrasetype"),
			"INSERT IGNORE INTO " . TABLE_PREFIX . "phrasetype
				(fieldname , title , editrows, special)
			VALUES
				('infraction', '{$phrasetype['infraction']}', 3, 0),
				('infractionlevel', '{$phrasetype['infractionlevel']}', 3, 0)"
		);
	}

	/**
	* Step #99
	*
	*/
	function step_99()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "infractionlevel"),
			"INSERT IGNORE INTO " . TABLE_PREFIX . "infractionlevel
				(infractionlevelid, points, expires, period, warning)
			VALUES
				(1, 1, 10, 'D', 1),
				(2, 1, 10, 'D', 1),
				(3, 1, 10, 'D', 1),
				(4, 1, 10, 'D', 1)"
		);
	}

	/**
	* Step #100
	*
	*/
	function step_100()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "phrase"),
			"REPLACE INTO " . TABLE_PREFIX . "phrase
				(languageid, fieldname, varname, text, product)
			VALUES
				(0, 'infractionlevel', 'infractionlevel1_title', '" . $this->db->escape_string($this->phrase['version']['360b1']['infractionlevel1_title']) . "', 'vbulletin'),
				(0, 'infractionlevel', 'infractionlevel2_title', '" . $this->db->escape_string($this->phrase['version']['360b1']['infractionlevel2_title']) . "', 'vbulletin'),
				(0, 'infractionlevel', 'infractionlevel3_title', '" . $this->db->escape_string($this->phrase['version']['360b1']['infractionlevel3_title']) . "', 'vbulletin'),
				(0, 'infractionlevel', 'infractionlevel4_title', '" . $this->db->escape_string($this->phrase['version']['360b1']['infractionlevel4_title']) . "', 'vbulletin')"
		);
	}

	/**
	* Step #101
	*
	*/
	function step_101()
	{
		// only do these perm updates once
		if (!$this->field_exists('user', 'ipoints'))
		{
			// Make sure to zero out permissions from possible past usage
			$newperms = array(
				'genericpermissions' => array(
					$this->registry->bf_ugp_genericpermissions['canreverseinfraction'],
					$this->registry->bf_ugp_genericpermissions['canseeinfraction'],
					$this->registry->bf_ugp_genericpermissions['cangiveinfraction'],
					$this->registry->bf_ugp_genericpermissions['canemailmember'],
			));

			foreach ($newperms AS $permission => $permissions)
			{
				$this->run_query(
					sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "usergroup"),
					"UPDATE " . TABLE_PREFIX . "usergroup SET $permission = $permission & ~" . (array_sum($permissions))
				);
			}

			$infractionperms = $this->registry->bf_ugp_genericpermissions['cangiveinfraction'] + $this->registry->bf_ugp_genericpermissions['canseeinfraction'];
			// Set infraction permissions for admins, mods and super mods
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "usergroup"),
				"UPDATE " . TABLE_PREFIX . "usergroup
					SET genericpermissions = genericpermissions | $infractionperms
				WHERE adminpermissions & " . $this->registry->bf_ugp_adminpermissions['cancontrolpanel'] . "
					OR adminpermissions & " . $this->registry->bf_ugp_adminpermissions['ismoderator'] . "
					OR usergroupid = 7"
			);

			// give infraction reversal perms to admins
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "usergroup"),
				"UPDATE " . TABLE_PREFIX . "usergroup
					SET genericpermissions = genericpermissions | " . $this->registry->bf_ugp_genericpermissions['canreverseinfraction'] ."
				WHERE adminpermissions & " . $this->registry->bf_ugp_adminpermissions['cancontrolpanel']
			);

			// Set can email member's permissions
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "usergroup"),
				"UPDATE " . TABLE_PREFIX . "usergroup
					SET genericpermissions = genericpermissions | " . $this->registry->bf_ugp_genericpermissions['canemailmember'] . "
				WHERE usergroupid NOT IN (1,3,4) AND genericoptions & " . $this->registry->bf_ugp_genericoptions['isnotbannedgroup']
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #102
	*
	*/
	function step_102()
	{
		// Alter User Table
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'user', 1, 4),
			'user',
			'ipoints',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #103
	*
	*/
	function step_103()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'user', 2, 4),
			'user',
			'infractions',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #104
	*
	*/
	function step_104()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'user', 3, 4),
			'user',
			'warnings',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #105
	*
	*/
	function step_105()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'deletionlog', 1, 2),
			'deletionlog',
			'dateline',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #106
	*
	*/
	function step_106()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'deletionlog', 2, 2),
			'deletionlog',
			'type',
			array('type', 'dateline')
		);
	}

	/**
	* Step #107
	*
	*/
	function step_107()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'moderation', 1, 3),
			'moderation',
			'dateline',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #108
	*
	*/
	function step_108()
	{
		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'moderation', 2, 3),
			'moderation',
			'type'
		);
	}

	/**
	* Step #109
	*
	*/
	function step_109()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'moderation', 3, 3),
			'moderation',
			'type',
			array('type', 'dateline')
		);
	}

	/**
	* Step #110
	*
	*/
	function step_110()
	{
		if (!$this->field_exists('user', 'infractiongroupids'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "deletionlog"),
				"UPDATE " . TABLE_PREFIX . "deletionlog AS deletionlog, " . TABLE_PREFIX . "thread AS thread
					SET deletionlog.dateline = thread.lastpost
				WHERE deletionlog.primaryid = thread.threadid
					AND deletionlog.type = 'thread'"
			);

			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "post"),
				"UPDATE " . TABLE_PREFIX . "deletionlog AS deletionlog, " . TABLE_PREFIX . "post AS post
					SET deletionlog.dateline = post.dateline
				WHERE deletionlog.primaryid = post.postid
					AND deletionlog.type = 'post'"
			);

			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "moderation"),
				"UPDATE " . TABLE_PREFIX . "moderation AS moderation, " . TABLE_PREFIX . "post AS post
					SET moderation.dateline = post.dateline
				WHERE moderation.postid = post.postid"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #111
	*
	*/
	function step_111()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'user', 4, 4),
			'user',
			'infractiongroupids',
			'varchar',
			array('length' => 255, 'attributes' => self::FIELD_DEFAULTS)
		);
	}

	/**
	* Step #112
	*
	*/
	function step_112()
	{
		// drop usergroup.pmforwardmax
		$this->drop_field(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 1, 1),
			'usergroup',
			'pmforwardmax'
		);
	}

	/**
	* Step #113
	*
	*/
	function step_113()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'profilefield', 1, 1),
			'profilefield',
			'perline',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #114
	*
	*/
	function step_114()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "profilefield"),
			"UPDATE " . TABLE_PREFIX . "profilefield
				SET perline = def
			WHERE type = 'checkbox'"
		);
	}

	/**
	* Step #115
	*
	*/
	function step_115()
	{
		if ($this->field_exists('adminhelp', 'optionname'))
		{
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'adminhelp', 1, 1),
				"ALTER TABLE " . TABLE_PREFIX . "adminhelp CHANGE optionname optionname VARCHAR(100) NOT NULL DEFAULT ''"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #116
	*
	*/
	function step_116()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'session', 1, 1),
			'session',
			'profileupdate',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #117
	*
	*/
	function step_117()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'phrase', 1, 3),
			'phrase',
			'username',
			'varchar',
			array('length' => 100, 'attributes' => self::FIELD_DEFAULTS)
		);
	}

	/**
	* Step #118
	*
	*/
	function step_118()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'phrase', 2, 3),
			'phrase',
			'dateline',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #119
	*
	*/
	function step_119()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'phrase', 3, 3),
			'phrase',
			'version',
			'varchar',
			array('length' => 30, 'attributes' => self::FIELD_DEFAULTS)
		);
	}

	/**
	* Step #120
	*
	*/
	function step_120()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "subscriptionpermission"),
			"CREATE TABLE " . TABLE_PREFIX . "subscriptionpermission (
				subscriptionpermissionid int(10) unsigned NOT NULL auto_increment,
				subscriptionid int(10) unsigned NOT NULL default '0',
				usergroupid int(10) unsigned NOT NULL default '0',
				PRIMARY KEY  (subscriptionpermissionid),
				UNIQUE KEY subscriptionid (subscriptionid,usergroupid),
				KEY usergroupid (usergroupid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #121
	*
	*/
	function step_121()
	{
		if (!$this->db->query_first("SELECT * FROM " . TABLE_PREFIX . "paymentapi WHERE classname = 'ccbill'"))
		{
			$ccbill_settings =  array(
				'clientAccnum' => array(
					'type' => 'text',
					'value' => '',
					'validate' => 'string'
				),
				'clientSubacc' => array(
					'type' => 'text',
					'value' => '',
					'validate' => 'string'
				),
				'formName' => array(
					'type' => 'text',
					'value' => '',
					'validate' => 'string'
				),
				'secretword' => array(
					'type' => 'text',
					'value' => '',
					'validate' => 'string'
				),
				'username' => array(
					'type' => 'text',
					'value' => '',
					'validate' => 'string'
				),
				'password' => array(
					'type' => 'text',
					'value' => '',
					'validate' => 'string'
				)
			);

			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "paymentapi"),
				"INSERT INTO " . TABLE_PREFIX . "paymentapi
					(title, currency, recurring, classname, active, settings)
				VALUES
					('CCBill', 'usd', 0, 'ccbill', 0, '" . $this->db->escape_string(serialize($ccbill_settings)) . "')"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #122
	*
	*/
	function step_122()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'paymentinfo', 1, 1),
			'paymentinfo',
			'hash',
			'hash'
		);
	}

	/**
	* Step #123
	*
	*/
	function step_123()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'paymenttransaction', 1, 7),
			'paymenttransaction',
			'dateline',
			'int',
			 self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #124
	*
	*/
	function step_124()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'paymenttransaction', 2, 7),
			'paymenttransaction',
			'paymentapiid',
			'int',
			 self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #125
	*
	*/
	function step_125()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'paymenttransaction', 3, 7),
			'paymenttransaction',
			'request',
			'mediumtext',
			 self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #126
	*
	*/
	function step_126()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'paymenttransaction', 4, 7),
			'paymenttransaction',
			'reversed',
			'int',
			 self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #127
	*
	*/
	function step_127()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'paymenttransaction', 5, 7),
			'paymenttransaction',
			'dateline',
			'dateline'
		);
	}

	/**
	* Step #128
	*
	*/
	function step_128()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'paymenttransaction', 6, 7),
			'paymenttransaction',
			'transactionid',
			'transactionid'
		);
	}

	/**
	* Step #129
	*
	*/
	function step_129()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'paymenttransaction', 7, 7),
			'paymenttransaction',
			'paymentapiid',
			'paymentapiid'
		);
	}

	/**
	* Step #130
	*
	*/
	function step_130()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'subscriptionlog', 1, 2),
			'subscriptionlog',
			'userid',
			array('userid', 'subscriptionid')
		);
	}

	/**
	* Step #131
	*
	*/
	function step_131()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'subscriptionlog', 2, 2),
			'subscriptionlog',
			'subscriptionid',
			'subscriptionid'
		);
	}

	/**
	* Step #132
	*
	*/
	function step_132()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'attachmenttype', 1, 1),
			'attachmenttype',
			'enabled',
			'enabled'
		);
	}

	/**
	* Step #133
	*
	*/
	function step_133()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "attachmentpermission"),
			"CREATE TABLE " . TABLE_PREFIX . "attachmentpermission (
				attachmentpermissionid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				extension VARCHAR(20) BINARY NOT NULL DEFAULT '',
				usergroupid INT UNSIGNED NOT NULL,
				size INT UNSIGNED NOT NULL,
				width SMALLINT UNSIGNED NOT NULL,
				height SMALLINT UNSIGNED NOT NULL,
				attachmentpermissions INT UNSIGNED NOT NULL,
				PRIMARY KEY  (attachmentpermissionid),
				UNIQUE KEY extension (extension,usergroupid),
				KEY usergroupid (usergroupid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #134
	*
	*/
	function step_134()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'datastore', 1, 1),
			'datastore',
			'unserialize',
			'smallint',
			array('attributes' => 'UNSIGNED', 'null' => false, 'default' => 2)
		);
	}

	/**
	* Step #135
	*
	*/
	function step_135()
	{
		// create rssfeed table
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "rssfeed"),
			"CREATE TABLE " . TABLE_PREFIX . "rssfeed (
				rssfeedid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				title VARCHAR(250) NOT NULL,
				url VARCHAR(250) NOT NULL,
				port SMALLINT UNSIGNED NOT NULL DEFAULT '80',
				ttl SMALLINT UNSIGNED NOT NULL DEFAULT '1500',
				maxresults SMALLINT NOT NULL DEFAULT '0',
				userid INT UNSIGNED NOT NULL,
				forumid SMALLINT UNSIGNED NOT NULL,
				iconid SMALLINT UNSIGNED NOT NULL,
				titletemplate MEDIUMTEXT NOT NULL,
				bodytemplate MEDIUMTEXT NOT NULL,
				searchwords MEDIUMTEXT NOT NULL,
				itemtype ENUM('thread','announcement') NOT NULL DEFAULT 'thread',
				threadactiondelay SMALLINT UNSIGNED NOT NULL,
				endannouncement INT UNSIGNED NOT NULL,
				options INT UNSIGNED NOT NULL,
				lastrun INT UNSIGNED NOT NULL,
				PRIMARY KEY  (rssfeedid),
				KEY lastrun (lastrun)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #136
	*
	*/
	function step_136()
	{
		// create rsslog table
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "rsslog"),
			"CREATE TABLE " . TABLE_PREFIX . "rsslog (
				rssfeedid INT UNSIGNED NOT NULL,
				itemid INT UNSIGNED NOT NULL,
				itemtype ENUM('thread','announcement') NOT NULL DEFAULT 'thread',
				uniquehash CHAR(32) NOT NULL,
				contenthash CHAR(32) NOT NULL,
				dateline INT UNSIGNED NOT NULL,
				threadactiontime INT UNSIGNED NOT NULL,
				threadactioncomplete TINYINT UNSIGNED NOT NULL,
				PRIMARY KEY (rssfeedid,itemid,itemtype),
				UNIQUE KEY uniquehash (uniquehash)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #137
	*
	*/
	function step_137()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "datastore"),
			"UPDATE " . TABLE_PREFIX . "datastore
				SET unserialize = 1
				WHERE title IN (
					'options', 'forumcache', 'languagecache', 'stylecache', 'bbcodecache',
					'smiliecache', 'wol_spiders', 'usergroupcache', 'attachmentcache',
					'maxloggedin', 'userstats', 'birthdaycache', 'eventcache', 'iconcache',
					'products', 'pluginlist', 'pluginlistadmin', 'bitfields', 'ranks',
					'noavatarperms', 'acpstats', 'profilefield'
				)
			"
		);
	}

	/**
	* Step #138
	*
	*/
	function step_138()
	{
		$moderator_permissions = array_sum($this->registry->bf_misc_moderatorpermissions) - ($this->registry->bf_misc_moderatorpermissions['newthreademail'] + $this->registry->bf_misc_moderatorpermissions['newpostemail']);

		$supergroups = $this->db->query_read("
			SELECT user.*, usergroup.usergroupid
			FROM " . TABLE_PREFIX . "usergroup AS usergroup
			INNER JOIN " . TABLE_PREFIX . "user AS user ON(user.usergroupid = usergroup.usergroupid OR FIND_IN_SET(usergroup.usergroupid, user.membergroupids))
			LEFT JOIN " . TABLE_PREFIX . "moderator AS moderator ON(moderator.userid = user.userid AND moderator.forumid = -1)
			WHERE (usergroup.adminpermissions & " . $this->registry->bf_ugp_adminpermissions['ismoderator'] . ") AND moderator.forumid IS NULL
			GROUP BY user.userid
		");
		while ($supergroup = $this->db->fetch_array($supergroups))
		{
			$this->run_query(
				sprintf($this->phrase['version']['360b1']['super_moderator_x_updated'], $supergroup['username']),
				"INSERT INTO " . TABLE_PREFIX . "moderator
					(userid, forumid, permissions)
				VALUES
					($supergroup[userid], -1, $moderator_permissions)"
			);
		}
	}

	/**
	* Step #139
	*
	*/
	function step_139()
	{
		$this->db->query_write("TRUNCATE TABLE " . TABLE_PREFIX . "postparsed");

		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'postparsed', 1, 8),
			'postparsed',
			'styleid',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #140
	*
	*/
	function step_140()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'postparsed', 2, 8),
			'postparsed',
			'languageid',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #141
	*
	*/
	function step_141()
	{
		$this->drop_field(
			sprintf($this->phrase['core']['altering_x_table'], 'postparsed', 3, 8),
			'postparsed',
			'styleid_code'
		);
	}

	/**
	* Step #142
	*
	*/
	function step_142()
	{
		$this->drop_field(
			sprintf($this->phrase['core']['altering_x_table'], 'postparsed', 4, 8),
			'postparsed',
			'styleid_html'
		);
	}

	/**
	* Step #143
	*
	*/
	function step_143()
	{
		$this->drop_field(
			sprintf($this->phrase['core']['altering_x_table'], 'postparsed', 5, 8),
			'postparsed',
			'styleid_php'
		);
	}

	/**
	* Step #144
	*
	*/
	function step_144()
	{
		$this->drop_field(
			sprintf($this->phrase['core']['altering_x_table'], 'postparsed', 6, 8),
			'postparsed',
			'styleid_quote'
		);
	}

	/**
	* Step #145
	*
	*/
	function step_145()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'postparsed', 7, 8),
			"ALTER TABLE " . TABLE_PREFIX . "postparsed DROP PRIMARY KEY",
			self::MYSQL_ERROR_DROP_KEY_COLUMN_MISSING
		);
	}

	/**
	* Step #146
	*
	*/
	function step_146()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'postparsed', 8, 8),
			"ALTER IGNORE TABLE " . TABLE_PREFIX . "postparsed ADD PRIMARY KEY (postid, styleid, languageid)",
			self::MYSQL_ERROR_PRIMARY_KEY_EXISTS
		);
	}

	/**
	* Step #147
	*
	*/
	function step_147()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'attachment', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "attachment CHANGE extension extension VARCHAR(20) BINARY NOT NULL DEFAULT ''"
		);
	}

	/**
	* Step #148
	*
	*/
	function step_148()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'attachmenttype', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "attachmenttype CHANGE extension extension VARCHAR(20) BINARY NOT NULL DEFAULT ''"
		);
	}

	/**
	* Step #149
	*
	*/
	function step_149()
	{
		$this->show_message($this->phrase['core']['cache_update']);
		// Update hidden profile cache to handle hidden AND required fields
		require_once(DIR . '/includes/adminfunctions_profilefield.php');
		build_profilefield_cache();
		$this->db->query_write("DELETE FROM " . TABLE_PREFIX . "datastore WHERE title = 'hidprofilecache'");

		//require_once(DIR . '/includes/adminfunctions_attachment.php');
		//build_attachment_permissions();

		vBulletinHook::build_datastore($this->db);
		build_product_datastore();
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
