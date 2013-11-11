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

class vB_Upgrade_400a1 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '400a1';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '4.0.0 Alpha 1';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '3.8.0+';

	/**
	* Beginning version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_STARTS = '3.8.0';

	/**
	* Ending version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_ENDS   = '3.8.99';

	/**
	* Step #1
	*
	*/
	function step_1()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'reputation', 1, 4),
			"ALTER TABLE " . TABLE_PREFIX . "reputation CHANGE postid postid INT UNSIGNED NOT NULL DEFAULT '1'"
		);
	}

	/**
	* Step #2
	*
	*/
	function step_2()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'reputation', 2, 4),
			"ALTER TABLE " . TABLE_PREFIX . "reputation CHANGE userid userid INT UNSIGNED NOT NULL DEFAULT '1'"
		);
	}

	/**
	* Step #3
	*
	*/
	function step_3()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'reputation', 3, 4),
			"ALTER TABLE " . TABLE_PREFIX . "reputation CHANGE whoadded whoadded INT UNSIGNED NOT NULL DEFAULT '0'"
		);
	}

	/**
	* Step #4
	*
	*/
	function step_4()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'reputation', 4, 4),
			"ALTER TABLE " . TABLE_PREFIX . "reputation CHANGE dateline dateline INT UNSIGNED NOT NULL DEFAULT '0'"
		);
	}

	/**
	* Step #5
	*
	*/
	function step_5()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'subscribegroup', 1, 1),
			'subscribegroup',
			'emailupdate',
			'enum',
			array('attributes' => "('daily', 'weekly', 'none')", 'null' => false, 'default' => 'none')
		);
	}

	/**
	* Step #6
	*
	*/
	function step_6()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'thread', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "thread CHANGE lastposter lastposter VARCHAR(100) NOT NULL default ''"
		);
	}

	/**
	* Step #7
	*
	*/
	function step_7()
	{
		//4.0 table changes.
		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'reputation', 1, 2),
			'reputation',
			'whoadded_postid'
		);
	}

	/**
	* Step #8
	*
	*/
	function step_8()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'reputation', 2, 2),
			"ALTER IGNORE TABLE " . TABLE_PREFIX . "reputation ADD UNIQUE INDEX whoadded_postid (whoadded, postid)",
			self::MYSQL_ERROR_KEY_EXISTS
		);
	}

	/**
	* Step #9
	*
	*/
	function step_9()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'subscribegroup', 1, 1),
			'subscribegroup',
			'emailupdate',
			'enum',
			array('attributes' => "('daily', 'weekly', 'none')", 'null' => false, 'default' => 'none')
		);
	}

	/**
	* Step #10
	*
	*/
	function step_10()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'forum', 1, 1),
			'forum',
			'lastposterid',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #11
	*
	*/
	function step_11()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'thread', 1, 2),
			'thread',
			'lastposterid',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #12
	*
	*/
	function step_12()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'thread', 2, 2),
			'thread',
			'keywords',
			'mediumtext',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #13
	*
	*/
	function step_13()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'tachyforumpost', 1, 1),
			'tachyforumpost',
			'lastposterid',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #14
	*
	*/
	function step_14()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'tachythreadpost', 1, 1),
			'tachythreadpost',
			'lastposterid',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #15
	*
	*/
	function step_15()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'style', 1, 1),
			'style',
			'newstylevars',
			'mediumtext',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #16
	*
	*/
	function step_16()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'template', 1, 1),
			"ALTER  TABLE  " . TABLE_PREFIX . "template ADD mergestatus ENUM('none', 'merged', 'conflicted') NOT NULL DEFAULT 'none'",
			self::MYSQL_ERROR_COLUMN_EXISTS
		);
	}

	/**
	* Step #17
	*
	*/
	function step_17()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'templatemerge'),
			"CREATE TABLE " . TABLE_PREFIX . "templatemerge (
				templateid INT UNSIGNED NOT NULL DEFAULT '0',
				template MEDIUMTEXT,
				version VARCHAR(30) NOT NULL DEFAULT '',
				savedtemplateid INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (templateid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #18
	*
	*/
	function step_18()
	{
		$this->add_adminmessage(
			'after_upgrade_40_update_thread_counters',
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
	* Step #19
	*
	*/
	function step_19()
	{
		$this->add_adminmessage(
			'after_upgrade_40_add_thread_keywords',
			array(
				'dismissable' => 1,
				'script'      => 'misc.php',
				'action'      => 'addmissingkeywords',
				'execurl'     => 'misc.php?do=addmissingkeywords',
				'method'      => 'get',
				'status'      => 'undone',
			)
		);
	}

	/**
	* Step #20
	*
	*/
	function step_20()
	{
		$this->add_adminmessage(
			'after_upgrade_40_rebuild_search_index',
			array(
				'dismissable' => 1,
				'script'      => 'misc.php',
				'action'      => 'doindextypes',
				'execurl'     => 'misc.php?do=doindextypes&pp=250&autoredirect=1',
				'method'      => 'get',
				'status'      => 'undone',
			)
		);
	}

	/**
	* Step #21
	*
	*/
	function step_21()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'tag', 1, 1),
			'tag',
			'canonicaltagid',
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
		$this->add_index(
			sprintf($this->phrase['core']['create_index_x_on_y'], 'canonicaltagid', TABLE_PREFIX . 'tag'),
			'tag',
			'canonicaltagid',
			array('canonicaltagid')
		);
	}

	/**
	* Step #23
	*
	*/
	function step_23()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "phrasetype"),
				"ALTER TABLE " . TABLE_PREFIX . "tag ENGINE={$this->hightrafficengine} ",
				sprintf($this->phrase['vbphrase']['alter_table'], 'tag')
		);
	}

	/**
	* Step #24
	* note -- any changes to the type datamodel in later releases need to be reflected here if they break the core type module.
	* Otherwise the upgrade will not correctly run.  The changes should also be put in the later release upgrade for people
	* upgrading from releases later than 4.0a1 in a way that will not break if they changes were already made (the basic
	* add_field function handles this).
	*/
	function step_24()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'contenttype'),
			"CREATE TABLE " . TABLE_PREFIX . "contenttype (
				contenttypeid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				class VARBINARY(50) NOT NULL,
				packageid INT UNSIGNED NOT NULL,
				canplace ENUM('0','1') DEFAULT  '0',
				cansearch ENUM('0','1') DEFAULT '0',
				cantag ENUM('0','1') DEFAULT '0',
				canattach ENUM('0','1') DEFAULT '0',
				isaggregator ENUM('0','1') NOT NULL DEFAULT '0',
				PRIMARY KEY (contenttypeid),
				UNIQUE KEY package (packageid, class)
			) ENGINE={$this->hightrafficengine}
			",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #25
	*
	*/
	function step_25()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "contenttype"),
			"INSERT IGNORE INTO " . TABLE_PREFIX . "contenttype
				(contenttypeid, class, packageid, canplace, cansearch, cantag, canattach)
			VALUES
				(1, 'Post', 1, '0', '1', '0', '1'),
				(2, 'Thread', 1, '0', '0', '1', '0'),
				(3, 'Forum', 1, '0', '1', '0', '0'),
				(4, 'Announcement', 1, '0', '0', '0', '0'),
				(5, 'SocialGroupMessage', 1, '0', '1', '0', '0'),
				(6, 'SocialGroupDiscussion', 1, '0', '0', '0', '0'),
				(7, 'SocialGroup', 1, '0', '1', '0', '1'),
				(8, 'Album', 1, '0', '0', '0', '1'),
				(9, 'Picture', 1, '0', '0', '0', '0'),
				(10, 'PictureComment', 1, '0', '0', '0', '0'),
				(11, 'VisitorMessage', 1, '0', '1', '0', '0'),
				(12, 'User', 1, '0', '0', '0', '0'),
				(13, 'Event', 1, '0', '0', '0', '0'),
				(14, 'Calendar', 1, '0', '0', '0', '0')
		");
	}

	/**
	* Step #26
	*
	*/
	function step_26()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'tagcontent'),
			"CREATE TABLE " . TABLE_PREFIX . "tagcontent (
				tagid INT UNSIGNED NOT NULL DEFAULT 0,
				contenttypeid INT UNSIGNED NOT NULL,
				contentid INT UNSIGNED NOT NULL DEFAULT '0',
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY tag_type_cid (tagid, contenttypeid, contentid),
				KEY id_type_user (contentid, contenttypeid, userid),
				KEY user (userid),
				KEY dateline (dateline)
			) ENGINE={$this->hightrafficengine}
			",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #27
	*
	*/
	function step_27()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'package'),
			"CREATE TABLE " . TABLE_PREFIX . "package (
				packageid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				productid VARCHAR(25) NOT NULL,
				class VARBINARY(50) NOT NULL,
				PRIMARY KEY  (packageid),
				UNIQUE KEY class (class)
			)
			",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #28
	*
	*/
	function step_28()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "package"),
			"INSERT IGNORE INTO " . TABLE_PREFIX . "package (packageid, productid, class)
				VALUES
			(1, 'vbulletin', 'vBForum')"
		);
	}

	/**
	* Step #29
	*
	*/
	function step_29()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'route'),
			"CREATE TABLE " . TABLE_PREFIX . "route (
				routeid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				userrequest VARCHAR(50) NOT NULL,
				packageid INT UNSIGNED NOT NULL,
				class VARBINARY(50) NOT NULL,
				PRIMARY KEY (routeid),
				UNIQUE KEY (userrequest),
				UNIQUE KEY(packageid, class)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #30
	*
	*/
	function step_30()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "route"),
			"INSERT IGNORE INTO " . TABLE_PREFIX . "route
				(routeid, userrequest, packageid, class)
			VALUES
				(1, 'error', 1, 'Error')"
		);
	}

	/**
	* Step #31
	*
	*/
	function step_31()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "tagcontent"),
			"INSERT INTO " . TABLE_PREFIX . "tagcontent
				(tagid, contenttypeid, contentid, userid, dateline)
			SELECT tagid, 2, threadid, userid, dateline
			FROM " . TABLE_PREFIX . "tagthread
			ON DUPLICATE KEY UPDATE contenttypeid = 2",
			self::MYSQL_ERROR_TABLE_MISSING
		);
	}

	/**
	* Step #32
	*
	*/
	function step_32()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "phrasetype"),
			"INSERT IGNORE INTO " . TABLE_PREFIX . "phrasetype
				(title, editrows, fieldname, special)
			VALUES
				('{$this->phrase['phrasetype']['tagscategories']}', 3, 'tagscategories', 0),
				('{$this->phrase['phrasetype']['contenttypes']}', 3, 'contenttypes', 0)
			"
		);
	}

	/**
	* Step #33
	*
	*/
	function step_33()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'language', 1, 2),
			'language',
			'phrasegroup_tagscategories',
			'mediumtext',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #34
	*
	*/
	function step_34()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'language', 1, 2),
			'language',
			'phrasegroup_contenttypes',
			'mediumtext',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #35
	*
	*/
	function step_35()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'cache'),
			"CREATE TABLE " . TABLE_PREFIX . "cache (
				cacheid VARBINARY(64) NOT NULL,
				expires INT UNSIGNED NOT NULL,
				created INT UNSIGNED NOT NULL,
				locktime INT UNSIGNED NOT NULL,
				serialized ENUM('0','1') NOT NULL DEFAULT '0',
				data BLOB,
				PRIMARY KEY (cacheid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #36
	*
	*/
	function step_36()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'cacheevent'),
			"CREATE TABLE " . TABLE_PREFIX . "cacheevent (
				cacheid VARBINARY(64) NOT NULL,
				event VARBINARY(50) NOT NULL,
				PRIMARY KEY (cacheid, event)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #37
	*
	*/
	function step_37()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'action'),
			"CREATE TABLE " . TABLE_PREFIX . "action (
				actionid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				routeid INT UNSIGNED NOT NULL,
				packageid INT UNSIGNED NOT NULL,
				controller VARBINARY(50) NOT NULL,
				useraction VARCHAR(50) NOT NULL,
				classaction VARBINARY(50) NOT NULL,
				PRIMARY KEY (actionid),
				UNIQUE KEY useraction (routeid, useraction),
				UNIQUE KEY classaction (packageid, controller, classaction)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #38
	*
	*/
	function step_38()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'contentpriority'),
			"CREATE TABLE " . TABLE_PREFIX . "contentpriority (
				contenttypeid varchar(20) NOT NULL,
		  		sourceid INT(10) UNSIGNED NOT NULL,
		  		prioritylevel DOUBLE(2,1) UNSIGNED NOT NULL,
		  		PRIMARY KEY (contenttypeid, sourceid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #39
	*
	*	Add cron job for sitemap
	*/
	function step_39()
	{
		$this->add_cronjob(
			array(
				'varname'  => 'sitemap',
				'nextrun'  => 1232082000,
				'weekday'  => -1,
				'day'      => -1,
				'hour'     => 5,
				'minute'   => 'a:1:{i:0;i:0;}',
				'filename' => './includes/cron/sitemap.php',
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
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'searchcore'),
			"CREATE TABLE " . TABLE_PREFIX . "searchcore (
				searchcoreid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				contenttypeid INT UNSIGNED NOT NULL,
				primaryid INT UNSIGNED  NOT NULL,
				groupcontenttypeid INT UNSIGNED NOT NULL,
				groupid INT UNSIGNED NOT NULL DEFAULT 0,
				dateline INT UNSIGNED NOT NULL DEFAULT 0,
				userid INT UNSIGNED NOT NULL DEFAULT 0,
				username VARCHAR(100) NOT NULL,
				ipaddress INT UNSIGNED NOT NULL,
				searchgroupid INT UNSIGNED NOT NULL,
				PRIMARY KEY (searchcoreid),
				UNIQUE KEY contentunique (contenttypeid, primaryid),
				KEY groupid (groupcontenttypeid, groupid),
				KEY ipaddress (ipaddress),
				KEY dateline (dateline),
				KEY userid (userid),
				KEY searchgroupid (searchgroupid)
			) ENGINE={$this->hightrafficengine}
			", self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #41
	*
	*/
	function step_41()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'searchcore_text'),
			"CREATE TABLE " . TABLE_PREFIX . "searchcore_text (
				searchcoreid INT UNSIGNED NOT NULL,
				keywordtext MEDIUMTEXT,
				title VARCHAR(255) NOT NULL DEFAULT '',
				PRIMARY KEY (searchcoreid),
				FULLTEXT KEY text (title, keywordtext)
			) ENGINE=MyISAM
			", self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #42
	*
	*/
	function step_42()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'searchgroup'),
			"CREATE TABLE " . TABLE_PREFIX . "searchgroup (
				searchgroupid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				contenttypeid INT UNSIGNED NOT NULL,
				groupid INT UNSIGNED  NOT NULL,
				dateline INT UNSIGNED NOT NULL DEFAULT 0,
				userid INT UNSIGNED NOT NULL DEFAULT 0,
				username VARCHAR(100) NOT NULL,
				PRIMARY KEY (searchgroupid),
				UNIQUE KEY groupunique (contenttypeid, groupid),
				KEY dateline (dateline),
				KEY userid (userid)
			) ENGINE={$this->hightrafficengine}
			", self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #43
	*
	*/
	function step_43()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'searchgroup_text'),
			"CREATE TABLE " . TABLE_PREFIX . "searchgroup_text (
				searchgroupid INT UNSIGNED NOT NULL,
				title VARCHAR(255) NOT NULL,
				PRIMARY KEY (searchgroupid),
				FULLTEXT KEY grouptitle (title)
			) ENGINE=MyISAM
			", self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #44
	*
	*/
	function step_44()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'searchlog'),
			"CREATE TABLE " . TABLE_PREFIX . "searchlog (
				searchlogid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				ipaddress VARCHAR(15) NOT NULL DEFAULT '',
				searchhash VARCHAR(32) NOT NULL,
				sortby VARCHAR(15) NOT NULL DEFAULT '',
				sortorder ENUM('asc','desc') NOT NULL DEFAULT 'asc',
				searchtime FLOAT UNSIGNED NOT NULL DEFAULT '0',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				completed SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				criteria TEXT NOT NULL,
				results MEDIUMBLOB,
				PRIMARY KEY (searchlogid),
				KEY search (userid, searchhash, sortby, sortorder),
				KEY userfloodcheck (userid, dateline),
				KEY ipfloodcheck (ipaddress, dateline)
			) ENGINE={$this->hightrafficengine}
			", self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #45
	*
	*/
	function step_45()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'indexqueue'),
			"CREATE TABLE " . TABLE_PREFIX . "indexqueue (
					queueid INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
					contenttype VARCHAR(45) NOT NULL,
					newid INTEGER UNSIGNED NOT NULL,
					id2 INTEGER UNSIGNED NOT NULL,
					package VARCHAR(64) NOT NULL,
					operation VARCHAR(64) NOT NULL,
					data TEXT NOT NULL,
					PRIMARY KEY (queueid)
				)
			", self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #46
	*
	*/
	function step_46()
	{
		$this->run_query(
			sprintf($this->phrase['core']['dropping_old_table_x'], TABLE_PREFIX . "search"),
			"DROP TABLE IF EXISTS " . TABLE_PREFIX . "search"
		);
	}

	/**
	* Step #47
	*
	*/
	function step_47()
	{
		$this->run_query(
			sprintf($this->phrase['core']['dropping_old_table_x'], TABLE_PREFIX . "word"),
			"DROP TABLE IF EXISTS " . TABLE_PREFIX . "word"
		);
	}

	/**
	* Step #48
	*
	*/
	function step_48()
	{
		$this->run_query(
			sprintf($this->phrase['core']['dropping_old_table_x'], TABLE_PREFIX . "tagthread"),
			"DROP TABLE IF EXISTS " . TABLE_PREFIX . "tagthread"
		);
	}

	/**
	* Step #49
	*
	*/
	function step_49()
	{
		if (!$this->field_exists('attachment', 'filedataid') AND $this->field_exists('filedata', 'filedataid'))
		{
			// We have a vb3 attachment table and a vb4 filedata table which causes a problem so move the vb4 filedata table
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "filedata"),
				"RENAME TABLE " . TABLE_PREFIX . "filedata TO " . TABLE_PREFIX . "filedata" . vbrand(0, 1000000),
				self::MYSQL_ERROR_TABLE_EXISTS
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #50
	*
	*/
	function step_50()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "attachment"),
			"RENAME TABLE " . TABLE_PREFIX . "attachment TO " . TABLE_PREFIX . "filedata",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #51
	*
	*/
	function step_51()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'attachment'),
			"CREATE TABLE " . TABLE_PREFIX . "attachment (
				attachmentid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				contenttypeid INT UNSIGNED NOT NULL DEFAULT '0',
				contentid INT UNSIGNED NOT NULL DEFAULT '0',
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				filedataid INT UNSIGNED NOT NULL DEFAULT '0',
				state ENUM('visible', 'moderation') NOT NULL DEFAULT 'visible',
				counter INT UNSIGNED NOT NULL DEFAULT '0',
				posthash VARCHAR(32) NOT NULL DEFAULT '',
				filename VARCHAR(100) NOT NULL DEFAULT '',
				caption TEXT,
				reportthreadid INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (attachmentid),
				KEY contenttypeid (contenttypeid, contentid),
				KEY contentid (contentid),
				KEY userid (userid, contenttypeid),
				KEY posthash (posthash, userid),
				KEY filedataid (filedataid, userid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #52
	*
	*/
	function step_52()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'attachmentcategory'),
			"CREATE TABLE " . TABLE_PREFIX . "attachmentcategory (
				categoryid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				title VARCHAR(255) NOT NULL DEFAULT '',
				parentid INT UNSIGNED NOT NULL DEFAULT '0',
				displayorder INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (categoryid),
				KEY userid (userid, parentid, displayorder)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #53
	*
	*/
	function step_53()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'attachmentcategoryuser'),
			"CREATE TABLE " . TABLE_PREFIX . "attachmentcategoryuser (
				filedataid INT UNSIGNED NOT NULL DEFAULT '0',
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				categoryid INT UNSIGNED NOT NULL DEFAULT '0',
				filename VARCHAR(100) NOT NULL DEFAULT '',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (filedataid, userid),
				KEY categoryid (categoryid, userid, filedataid),
				KEY userid (userid, categoryid, dateline)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #54
	*
	*/
	function step_54()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'picturelegacy'),
			"CREATE TABLE " . TABLE_PREFIX . "picturelegacy (
				type ENUM('album', 'group') NOT NULL DEFAULT 'album',
				primaryid INT UNSIGNED NOT NULL DEFAULT '0',
				pictureid INT UNSIGNED NOT NULL DEFAULT '0',
				attachmentid INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (type, primaryid, pictureid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #55
	*
	*/
	function step_55()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'stylevar'),
			"CREATE TABLE " . TABLE_PREFIX . "stylevar (
				stylevarid varchar(250) NOT NULL,
				styleid SMALLINT NOT NULL DEFAULT '-1',
				value MEDIUMBLOB NOT NULL,
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				username VARCHAR(100) NOT NULL DEFAULT '',
				UNIQUE KEY stylevarinstance (stylevarid, styleid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #56
	*
	*/
	function step_56()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'stylevardfn'),
			"CREATE TABLE " . TABLE_PREFIX . "stylevardfn (
				stylevarid varchar(250) NOT NULL,
				styleid SMALLINT NOT NULL DEFAULT '-1',
				parentid SMALLINT NOT NULL,
				parentlist varchar(250) NOT NULL DEFAULT '0',
				stylevargroup varchar(250) NOT NULL,
				product varchar(25) NOT NULL default 'vbulletin',
				datatype varchar(25) NOT NULL default 'string',
				validation varchar(250) NOT NULL,
				failsafe MEDIUMBLOB NOT NULL,
				units enum('','%','px','pt','em','ex','pc','in','cm','mm') NOT NULL default '',
				uneditable tinyint(3) unsigned NOT NULL default '0',
				PRIMARY KEY (stylevarid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #57
	*
	*/
	function step_57()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'user', 1, 1),
			'user',
			'assetposthash',
			'varchar',
			array('length' => 32, 'attributes' => self::FIELD_DEFAULTS)
		);
	}

	/**
	* Step #58
	*
	*/
	function step_58()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'user'),
			"UPDATE " . TABLE_PREFIX . "user SET options = options | " . $this->registry->bf_misc_useroptions['vbasset_enable']
		);
	}

	/**
	* Step #59
	*
	* Enable asset manager as a default for new users
	*/
	function step_59()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'setting'),
			"UPDATE " . TABLE_PREFIX . "setting SET
				value = value | " . $this->registry->bf_misc_regoptions['vbasset_enable'] . "
			WHERE varname = 'defaultregoptions'"
		);
	}

	/**
	* Step #60
	* Update attachments
	*
	* @param	array	contains id to startat processing at
	*
	* @return	mixed
	*/
	function step_60($data = null)
	{
		$startat = intval($data['startat']);

		if ($this->field_exists('filedata', 'attachmentid'))
		{
			if ($startat == 0)
			{
				$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'attachment'));
			}

			$max = $this->db->query_first("
				SELECT MAX(attachmentid) AS maxid
				FROM " . TABLE_PREFIX . "filedata
			");

			$maxattachmentid = $max['maxid'];

			$count = $this->db->query_first("
				SELECT COUNT(*) AS count
				FROM " . TABLE_PREFIX . "filedata
				WHERE attachmentid < $startat
			");

			$done = $count['count'];

			$perpage = 50;
			$files = $this->db->query_read("
				SELECT attachmentid, postid, userid, dateline, visible, counter, filename
				FROM " . TABLE_PREFIX . "filedata
				WHERE attachmentid > $startat
				ORDER BY attachmentid ASC
				" . ($this->limitqueries ? "LIMIT 0, $perpage" : "") . "
			");

			$totalattach = $this->db->num_rows($files);
			if ($totalattach)
			{
				$lastid = 0;
				$sql = $sql2 = array();
				$count = 0;
				$processed = 0;
				while ($file = $this->db->fetch_array($files))
				{
					$count++;
					$sql[] = "(
						$file[attachmentid],
						1,
						$file[postid],
						$file[userid],
						$file[dateline],
						$file[attachmentid],
						'" . ($file['visible'] ? 'visible' : 'moderation') . "',
						$file[counter],
						'" . $this->db->escape_string($file['filename']) . "'
					)";

					$sql2[] = "(
						$file[attachmentid],
						$file[userid],
						0,
						'" . $this->db->escape_string($file['filename']) . "',
						$file[dateline]
					)";
					$lastid = $file['attachmentid'];

					$processed++;
					// Keep the amount of data inserted in one query low -- max_packet!
					if ($processed == $perpage OR $count == $totalattach)
					{
						$this->db->query_write("
							INSERT IGNORE INTO " . TABLE_PREFIX . "attachment
								(attachmentid, contenttypeid, contentid, userid, dateline, filedataid, state, counter, filename)
							VALUES
								" . implode(",\r\n\t\t", $sql) . "
						");
						$this->db->query_write("
							INSERT IGNORE INTO " . TABLE_PREFIX . "attachmentcategoryuser
								(filedataid, userid, categoryid, filename, dateline)
							VALUES
								" . implode(",\r\n\t\t", $sql2) . "
						");
						$sql = $sql2 = array();
						$processed = 0;
					}
				}
				if ($lastid)
				{
					$this->show_message(sprintf($this->phrase['version']['400a1']['convert_attachment'], $startat + 1, $lastid, $maxattachmentid), true);
				}
				else if ($startat)
				{
					$this->skip_message();
				}
				return array('startat' => $lastid);
			}
			else
			{
				$this->show_message($this->phrase['version']['400a1']['update_attachments_complete']);
			}
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #61
	*
	*/
	function step_61()
	{
		if ($this->field_exists('filedata', 'attachmentid'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['alter_table'], 'filedata'),
				"ALTER TABLE " . TABLE_PREFIX . "filedata CHANGE attachmentid filedataid INT UNSIGNED NOT NULL AUTO_INCREMENT"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #62
	*
	*/
	function step_62()
	{
		$this->add_field(
			sprintf($this->phrase['vbphrase']['alter_table'], 'filedata'),
			'filedata',
			'width',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #63
	*
	*/
	function step_63()
	{
		$this->add_field(
			sprintf($this->phrase['vbphrase']['alter_table'], 'filedata'),
			'filedata',
			'height',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #64
	*
	*/
	function step_64()
	{
		$this->add_field(
			sprintf($this->phrase['vbphrase']['alter_table'], 'filedata'),
			'filedata',
			'thumbnail_width',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #65
	*
	*/
	function step_65()
	{
		$this->add_field(
			sprintf($this->phrase['vbphrase']['alter_table'], 'filedata'),
			'filedata',
			'thumbnail_height',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #66
	*
	*/
	function step_66()
	{
		$this->add_field(
			sprintf($this->phrase['vbphrase']['alter_table'], 'filedata'),
			'filedata',
			'refcount',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #67
	*
	*/
	function step_67()
	{
		if (!$this->field_exists('filedata', 'refcount'))
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['alter_table'], 'filedata'),
				"UPDATE " . TABLE_PREFIX . "filedata SET refcount = 1"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #68
	*
	*/
	function step_68()
	{
		$this->add_index(
			sprintf($this->phrase['vbphrase']['alter_table'], 'filedata'),
			'filedata',
			'refcount',
			array('refcount', 'dateline')
		);
	}

	/**
	* Step #69
	*
	*/
	function step_69()
	{
		$this->drop_field(
			sprintf($this->phrase['vbphrase']['alter_table'], 'filedata'),
			'filedata',
			'filename'
		);
	}

	/**
	* Step #70
	*
	*/
	function step_70()
	{
		$this->drop_field(
			sprintf($this->phrase['vbphrase']['alter_table'], 'filedata'),
			'filedata',
			'counter'
		);
	}

	/**
	* Step #71
	*
	*/
	function step_71()
	{
		$this->drop_field(
			sprintf($this->phrase['vbphrase']['alter_table'], 'filedata'),
			'filedata',
			'visible'
		);
	}

	/**
	* Step #72
	*
	*/
	function step_72()
	{
		$this->drop_field(
			sprintf($this->phrase['vbphrase']['alter_table'], 'filedata'),
			'filedata',
			'postid'
		);
	}

	/**
	* Step #73
	*
	*/
	function step_73()
	{
		$this->drop_index(
			sprintf($this->phrase['vbphrase']['alter_table'], 'filedata'),
			'filedata',
			'posthash'
		);
	}

	/**
	* Step #74
	*
	*/
	function step_74()
	{
		$this->drop_field(
			sprintf($this->phrase['vbphrase']['alter_table'], 'filedata'),
			'filedata',
			'posthash'
		);
	}

	/**
	* Step #75
	*
	*/
	function step_75()
	{
		$this->add_field(
			sprintf($this->phrase['vbphrase']['alter_table'], 'filedata'),
			'attachmenttype',
			'contenttypes',
			'mediumtext',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #76
	*
	*/
	function step_76()
	{
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'attachmenttype'));
		$extensions = $this->db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "attachmenttype
		");
		while ($ext = $this->db->fetch_array($extensions))
		{
			if (isset($ext['enabled']))
			{
				$cache = array(
					1 => array(
						'n' => $ext['newwindow'],
						'e' => $ext['enabled'],
					),
					2 => array(
						'n' => $ext['newwindow'],
						'e' => in_array($ext['extension'], array('gif','jpe','jpeg','jpg','png','bmp')) ? 1 : 0
					)
				);
				$this->db->query_write("
					UPDATE " . TABLE_PREFIX . "attachmenttype
					SET contenttypes = '" . $this->db->escape_string(serialize($cache)) . "'
					WHERE extension = '" . $this->db->escape_string($ext['extension']) . "'
				");
			}
		}
	}

	/**
	* Step #77
	*
	*/
	function step_77()
	{
		$this->drop_field(
			sprintf($this->phrase['core']['altering_x_table'], 'attachmenttype', 1, 3),
			'attachmenttype',
			'enabled'
		);
	}

	/**
	* Step #78
	*
	*/
	function step_78()
	{
		$this->drop_field(
			sprintf($this->phrase['core']['altering_x_table'], 'attachmenttype', 2, 3),
			'attachmenttype',
			'newwindow'
		);
	}

	/**
	* Step #79
	*
	*/
	function step_79()
	{
		$this->drop_field(
			sprintf($this->phrase['core']['altering_x_table'], 'attachmenttype', 3, 3),
			'attachmenttype',
			'thumbnail'
		);
	}

	/**
	* Step #80
	*
	*/
	function step_80()
	{
		if ($this->field_exists('albumpicture', 'pictureid'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'albumpicture', 1, 1),
				'albumpicture',
				'attachmentid',
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
	* Step #81
	*
	*/
	function step_81()
	{
		if ($this->field_exists('albumpicture', 'pictureid'))
		{
			$this->add_field(
				sprintf($this->phrase['core']['altering_x_table'], 'socialgrouppicture', 1, 1),
				'socialgrouppicture',
				'attachmentid',
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
	* Step #82
	*
	*/
	function step_82()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'usercss', 1, 1),
			'usercss',
			'converted',
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
			sprintf($this->phrase['core']['altering_x_table'], 'picturecomment', 1, 2),
			'picturecomment',
			'filedataid',
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
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'picturecomment', 2, 2),
			'picturecomment',
			'userid',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #85
	*
	*/
	function step_85()
	{
		if ($this->field_exists('picturecomment_hash', 'pictureid'))
		{
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'picturecomment_hash', 1, 2),
				"ALTER TABLE " . TABLE_PREFIX . "picturecomment_hash CHANGE pictureid filedataid INT UNSIGNED NOT NULL DEFAULT '0'"
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
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'picturecomment_hash', 2, 2),
			'picturecomment_hash',
			'userid',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #87
	*
	*/
	function step_87()
	{
		if ($this->field_exists('album', 'coverpictureid'))
		{
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'album', 1, 1),
				"ALTER TABLE " . TABLE_PREFIX . "album CHANGE coverpictureid coverattachmentid INT UNSIGNED NOT NULL DEFAULT '0'"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #88
	*
	*/
	function step_88()
	{
		$this->drop_field(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 1, 1),
			'usergroup',
			'albumpicmaxsize'
		);
	}

	/**
	* Step #89 - Convert Albums
	*
	* @param	array	contains id to startat processing at
	*
	* @return	mixed	Startat value for next go round
	*/
	function step_89($data = null)
	{
		$startat = intval($data['startat']);
		$perpage = 25;
		$users = array();
		// Convert Albums
		$db_alter = new vB_Database_Alter_MySQL($this->db);
		if ($db_alter->fetch_table_info('albumpicture'))
		{
			$pictures = $this->db->query_read("
				SELECT
					albumpicture.albumid, albumpicture.dateline,
					picture.*
				FROM " . TABLE_PREFIX . "albumpicture AS albumpicture
				INNER JOIN " . TABLE_PREFIX . "picture AS picture ON (albumpicture.pictureid = picture.pictureid)
				WHERE
					albumpicture.pictureid > $startat
						AND
					albumpicture.attachmentid = 0
				ORDER BY albumpicture.pictureid ASC
				" . ($this->limitqueries ? "LIMIT 0, $perpage" : "") . "
			");
			if ($this->db->num_rows($pictures))
			{
				$lastid = 0;
				while ($picture = $this->db->fetch_array($pictures))
				{
					$this->show_message(sprintf($this->phrase['version']['400a1']['convert_picture'], $picture['pictureid']), true);
					$lastid = $picture['pictureid'];

					if ($this->registry->options['album_dataloc'] == 'db')
					{
						$thumbnail =& $picture['thumbnail'];
						$filedata =& $picture['filedata'];
					}
					else
					{
						$attachpath = $this->registry->options['album_picpath'] . '/' . floor($picture['pictureid'] / 1000) . "/$picture[pictureid].picture";
						if ($this->registry->options['album_dataloc'] == 'fs_directthumb')
						{
							$attachthumbpath = $this->registry->options['album_thumbpath'] . '/' . floor($picture['pictureid'] / 1000);
						}
						else
						{
							$attachthumbpath = $this->registry->options['album_picpath'] . '/' . floor($picture['pictureid'] / 1000);
						}
						$attachthumbpath .= "/$picture[idhash]_$picture[pictureid].$picture[extension]";

						$thumbnail = @file_get_contents($attachthumbpath);
						$filedata = @file_get_contents($attachpath);
						if ($filedata === false)
						{
							$this->show_message(sprintf($this->phrase['version']['400a1']['could_not_find_file'], $attachpath));
							continue;
						}
					}

					$attachdm =& datamanager_init('AttachmentFiledata', $this->registry, ERRTYPE_CP, 'attachment');
					$attachdm->set('contenttypeid', 8);
					$attachdm->set('contentid', $picture['albumid']);
					$attachdm->set('filename', $picture['pictureid'] . '.' . $picture['extension']);
					$attachdm->set('width', $picture['width']);
					$attachdm->set('height', $picture['height']);
					$attachdm->set('state', $picture['state']);
					$attachdm->set('reportthreadid', $picture['reportthreadid']);
					$attachdm->set('userid', $picture['userid']);
					$attachdm->set('caption', $picture['caption']);
					$attachdm->set('dateline', $picture['dateline']);
					$attachdm->set('thumbnail_dateline', $picture['thumbnail_dateline']);
					$attachdm->setr('filedata', $filedata);
					$attachdm->setr('thumbnail', $thumbnail);

					if ($attachmentid = $attachdm->save())
					{
						$this->db->query_write("
							UPDATE " . TABLE_PREFIX . "albumpicture
							SET
								attachmentid = $attachmentid
							WHERE
								pictureid = $picture[pictureid]
									AND
								albumid = $picture[albumid]
						");

						$this->db->query_write("
							INSERT IGNORE INTO " . TABLE_PREFIX . "picturelegacy
								(type, primaryid, pictureid, attachmentid)
							VALUES
								('album', $picture[albumid], $picture[pictureid], $attachmentid)
						");

						$this->db->query_write("
							UPDATE " . TABLE_PREFIX . "picturecomment
							SET
								filedataid = " . $attachdm->fetch_field('filedataid') . ",
								userid = $picture[userid]
							WHERE
								pictureid = $picture[pictureid]
						");

						$this->db->query_write("
							UPDATE " . TABLE_PREFIX . "album
							SET coverattachmentid = $attachmentid
							WHERE
								coverattachmentid = $picture[pictureid]
									AND
								albumid = $picture[albumid]
						");

						$oldvalue = "$picture[albumid],$picture[userid]";
						$newvalue = "$picture[albumid],$attachmentid]";
						$this->db->query_write("
							UPDATE " . TABLE_PREFIX . "usercss
							SET
								value = '" . $this->db->escape_string($newvalue) . "',
								converted = 1
							WHERE
								property = 'background_image'
									AND
								value = '" . $this->db->escape_string($oldvalue) . "'
									AND
								userid = $picture[userid]
									AND
								converted = 0
						");
						if ($this->db->affected_rows())
						{
							$users["$picture[userid]"] = 1;
						}
					}
					else
					{
						//will print errors and die.
						$attachdm->has_errors(true);
					}
				}

				if (!empty($users))
				{
					require_once(DIR . '/includes/class_usercss.php');
					foreach(array_keys($users) AS $userid)
					{
						$usercss = new vB_UserCSS($this->registry, $userid, false);
						$usercss->update_css_cache();
					}
				}

				return array('startat' => $lastid);
			}
			else
			{
				$this->show_message($this->phrase['version']['400a1']['update_albums_complete']);
			}
		}
		else
		{
			$this->show_message($this->phrase['version']['400a1']['update_albums_complete']);
		}
	}

	/**
	* Step #90 - Convert Social Groups
	*
	* @param	int	id to startat processing at
	*
	* @return	mixed	Startat value for next go round
	*/
	function step_90($data = null)
	{
		$startat = intval($data['startat']);
		$perpage = 25;
		$db_alter = new vB_Database_Alter_MySQL($this->db);
		if ($db_alter->fetch_table_info('albumpicture'))
		{
			$pictures = $this->db->query_read("
				SELECT
					sgp.groupid, sgp.dateline,
					picture.*
				FROM " . TABLE_PREFIX . "socialgrouppicture AS sgp
				INNER JOIN " . TABLE_PREFIX . "picture AS picture ON (sgp.pictureid = picture.pictureid)
				WHERE
					sgp.pictureid > $startat
						AND
					sgp.attachmentid = 0
				ORDER BY sgp.pictureid ASC
				" . ($this->limitqueries ? "LIMIT 0, $perpage" : "") . "
			");
			if ($this->db->num_rows($pictures))
			{
				$lastid = 0;
				while ($picture = $this->db->fetch_array($pictures))
				{
					$this->show_message(sprintf($this->phrase['version']['400a1']['convert_picture'], $picture['pictureid']), 1);
					$lastid = $picture['pictureid'];

					if ($this->registry->options['album_dataloc'] == 'db')
					{
						$thumbnail =& $picture['thumbnail'];
						$filedata =& $picture['filedata'];
					}
					else
					{
						$attachpath = $this->registry->options['album_picpath'] . '/' . floor($picture['pictureid'] / 1000) . "/$picture[pictureid].picture";
						if ($this->registry->options['album_dataloc'] == 'fs_directthumb')
						{
							$attachthumbpath = $this->registry->options['album_thumbpath'] . '/' . floor($picture['pictureid'] / 1000);
						}
						else
						{
							$attachthumbpath = $this->registry->options['album_picpath'] . '/' . floor($picture['pictureid'] / 1000);
						}
						$attachthumbpath .= "/$picture[idhash]_$picture[pictureid].$picture[extension]";

						$thumbnail = @file_get_contents($attachthumbpath);
						$filedata = @file_get_contents($attachpath);

						if ($filedata === false)
						{
							$this->show_message(sprintf($this->phrase['version']['400a1']['could_not_find_file'], $attachpath));
							continue;
						}
					}

					$attachdm =& datamanager_init('AttachmentFiledata', $this->registry, ERRTYPE_CP, 'attachment');
					$attachdm->set('contenttypeid', 7);
					$attachdm->set('contentid', $picture['groupid']);
					$attachdm->set('filename', $picture['pictureid'] . '.' . $picture['extension']);
					$attachdm->set('width', $picture['width']);
					$attachdm->set('height', $picture['height']);
					$attachdm->set('state', $picture['state']);
					$attachdm->set('reportthreadid', $picture['reportthreadid']);
					$attachdm->set('userid', $picture['userid']);
					$attachdm->set('caption', $picture['caption']);
					$attachdm->set('dateline', $picture['dateline']);
					$attachdm->set('thumbnail_dateline', $picture['thumbnail_dateline']);
					$attachdm->setr('filedata', $filedata);
					$attachdm->setr('thumbnail', $thumbnail);
					if ($attachmentid = $attachdm->save())
					{
						$this->db->query_write("
							UPDATE " . TABLE_PREFIX . "socialgrouppicture
							SET
								attachmentid = $attachmentid
							WHERE
								pictureid = $picture[pictureid]
									AND
								groupid = $picture[groupid]
						");

						$this->db->query_write("
							INSERT IGNORE INTO " . TABLE_PREFIX . "picturelegacy
								(type, primaryid, pictureid, attachmentid)
							VALUES
								('group', $picture[groupid], $picture[pictureid], $attachmentid)
						");
					}
					else
					{
						//will print errors and die.
						$attachdm->has_errors(true);
					}
				}
				return array('startat' => $lastid);
			}
			else
			{
				$this->show_message($this->phrase['version']['400a1']['update_groups_complete']);
			}
		}
		else
		{
			$this->show_message($this->phrase['version']['400a1']['update_groups_complete']);
		}
	}

	/**
	* Step #91
	*
	*/
	function step_91()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'moderator', 1, 1),
			"UPDATE " . TABLE_PREFIX . "moderator SET
				permissions2 = permissions2 |
					IF(permissions2 & " . $this->registry->bf_misc_moderatorpermissions2['caneditalbumpicture'] . ", " . $this->registry->bf_misc_moderatorpermissions2['caneditgrouppicture'] . ", 0) |
					IF(permissions2 & " . $this->registry->bf_misc_moderatorpermissions2['candeletealbumpicture'] . ", " . $this->registry->bf_misc_moderatorpermissions2['candeletegrouppicture'] . ", 0) |
					IF(permissions2 & " . $this->registry->bf_misc_moderatorpermissions2['canmoderatepictures'] . ", " . $this->registry->bf_misc_moderatorpermissions2['canmoderategrouppicture'] . ", 0)
			"
		);
	}

	/**
	* Step #92
	*
	*/
	function step_92()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 1, 1),
			"UPDATE " . TABLE_PREFIX . "usergroup SET
				socialgrouppermissions = socialgrouppermissions |
					IF(albumpermissions & " . $this->registry->bf_ugp_albumpermissions['canalbum'] . ", " . $this->registry->bf_ugp_socialgrouppermissions['canupload'] . ", 0) |
					IF(albumpermissions & " . $this->registry->bf_ugp_albumpermissions['picturefollowforummoderation'] . ", " . $this->registry->bf_ugp_socialgrouppermissions['groupfollowforummoderation'] . ", 0)
			"
		);
	}

	/**
	* Step #93
	*
	*/
	function step_93()
	{
		$this->run_query(
			sprintf($this->phrase['core']['dropping_old_table_x'], TABLE_PREFIX . "albumpicture"),
			"DROP TABLE IF EXISTS " . TABLE_PREFIX . "albumpicture"
		);
	}

	/**
	* Step #94
	*
	*/
	function step_94()
	{
		$this->run_query(
			sprintf($this->phrase['core']['dropping_old_table_x'], TABLE_PREFIX . "socialgrouppicture"),
			"DROP TABLE IF EXISTS " . TABLE_PREFIX . "socialgrouppicture"
		);
	}

	/**
	* Step #95
	*
	*/
	function step_95()
	{
		$this->run_query(
			sprintf($this->phrase['core']['dropping_old_table_x'], TABLE_PREFIX . "picture"),
			"DROP TABLE IF EXISTS " . TABLE_PREFIX . "picture"
		);
	}

	/**
	* Step #96
	*
	*/
	function step_96()
	{
		$this->drop_field(
			sprintf($this->phrase['core']['altering_x_table'], 'picturecomment', 1, 6),
			'picturecomment',
			'pictureid'
		);
	}

	/**
	* Step #97
	*
	*/
	function step_97()
	{
		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'picturecomment', 2, 6),
			'picturecomment',
			'pictureid'
		);
	}

	/**
	* Step #98
	*
	*/
	function step_98()
	{
		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'picturecomment', 3, 6),
			'picturecomment',
			'postuserid'
		);
	}

	/**
	* Step #99
	*
	*/
	function step_99()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'picturecomment', 4, 6),
			'picturecomment',
			'filedataid',
			array('filedataid', 'userid', 'dateline', 'state')
		);
	}

	/**
	* Step #100
	*
	*/
	function step_100()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'filedata', 5, 6),
			'picturecomment',
			'postuserid',
			array('postuserid', 'filedataid', 'userid', 'state')
		);
	}

	/**
	* Step #101
	*
	*/
	function step_101()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'filedata', 6, 6),
			'picturecomment',
			'userid',
			array('userid')
		);
	}

	/**
	* Step #102
	*
	*/
	function step_102()
	{
		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'attachment'));

		require_once(DIR . '/includes/adminfunctions_attachment.php');
		build_attachment_permissions();

		// Kill duplicate files in the filedata table
		$files = $this->db->query_read("
			SELECT count(*) AS count, filehash, filesize
			FROM " . TABLE_PREFIX . "filedata
			GROUP BY filehash, filesize
			HAVING count > 1
		");
		while ($file = $this->db->fetch_array($files))
		{
			$refcount = 0;
			$filedataid = 0;
			$killfiles = array();
			$files2 = $this->db->query("
				SELECT
					filedataid, refcount, userid
				FROM " . TABLE_PREFIX . "filedata
				WHERE
					filehash = '$file[filehash]'
						AND
					filesize = $file[filesize]
			");
			while ($file2 = $this->db->fetch_array($files2))
			{
				$refcount += $file2['refcount'];
				if (!$filedataid)
				{
					$filedataid = $file2['filedataid'];
				}
				else
				{
					$killfiles[$file2['filedataid']] = $file2['userid'];
				}
			}

			$this->db->query_write("UPDATE " . TABLE_PREFIX . "filedata SET refcount = $refcount WHERE filedataid = $filedataid");
			$this->db->query_write("UPDATE " . TABLE_PREFIX . "attachment SET filedataid = $filedataid WHERE filedataid IN (" . implode(",", array_keys($killfiles)) . ")");
			$this->db->query_write("DELETE FROM " . TABLE_PREFIX . "filedata WHERE filedataid IN (" . implode(",", array_keys($killfiles)) . ")");
			foreach ($killfiles AS $filedataid => $userid)
			{
				if ($this->registry->GPC['attachtype'] == ATTACH_AS_FILES_NEW)
				{
					$path = $this->registry->options['attachpath'] . '/' . implode('/', preg_split('//', $userid,  -1, PREG_SPLIT_NO_EMPTY));
				}
				else
				{
					$path = $this->registry->options['attachpath'] . '/' . $userid;
				}
				@unlink($path . '/' . $filedataid . '.attach');
				@unlink($path . '/' . $filedataid . '.thumb');
			}
		}
	}

	/**
	* Step #103
	*
	*/
	function step_103()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'setting'),
			"UPDATE " . TABLE_PREFIX . "setting
			SET value = 'ssl'
			WHERE varname = 'smtp_tls' AND value = '1'"
		);

		$this->long_next_step();
	}

	/**
	* Step #104
	*
	*/
	function step_104()
	{
		$this->add_index(
			sprintf($this->phrase['core']['create_index_x_on_y'], 'threadid_visible_dateline', TABLE_PREFIX . 'post'),
			'post',
			'threadid_visible_dateline',
			array('threadid', 'visible', 'dateline', 'userid', 'postid')
		);

		$this->long_next_step();
	}

	/**
	* Step #105
	*
	*/
	function step_105()
	{
		$this->add_index(
			sprintf($this->phrase['core']['create_index_x_on_y'], 'ipaddress', TABLE_PREFIX . 'post'),
			'post',
			'ipaddress',
			array('ipaddress')
		);

		$this->long_next_step();
	}

	/**
	* Step #106
	*
	*/
	function step_106()
	{
		$this->add_index(
			sprintf($this->phrase['core']['create_index_x_on_y'], 'dateline', TABLE_PREFIX . 'post'),
			'post',
			'dateline',
			array('dateline')
		);

		$this->long_next_step();
	}

	/**
	* Step #107
	*
	*/
	function step_107()
	{
		$this->add_index(
			sprintf($this->phrase['core']['create_index_x_on_y'], 'forumid_lastpost', TABLE_PREFIX . 'thread'),
			'thread',
			'forumid_lastpost',
			array('forumid', 'lastpost')
		);

		$this->long_next_step();
	}

	/**
	* Step #108
	*
	*/
	function step_108()
	{
		$this->drop_index(
			sprintf($this->phrase['core']['drop_index_x_on_y'], 'lastpost', TABLE_PREFIX . 'post'),
			'thread',
			'lastpost'
		);

		$this->long_next_step();
	}

	/**
	* Step #109
	*
	*/
	function step_109()
	{
		$this->add_index(
			sprintf($this->phrase['core']['create_index_x_on_y'], 'lastpost', TABLE_PREFIX . 'post'),
			'thread',
			'lastpost',
			array('lastpost')
		);

		$this->long_next_step();
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
