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

class vB_Upgrade_370b2 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '370b2';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '3.7.0 Beta 2';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '3.6.8+';

	/**
	* Beginning version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_STARTS = '3.6.8';

	/**
	* Ending version compatibility
	*
	* @var	string
	*/
	public $VERSION_COMPAT_ENDS   = '3.6.99';

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
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'userchangelog'),
			"CREATE TABLE " . TABLE_PREFIX . "userchangelog (
				changeid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				fieldname VARCHAR(250) NOT NULL DEFAULT '',
				newvalue VARCHAR(250) NOT NULL DEFAULT '',
				oldvalue VARCHAR(250) NOT NULL DEFAULT '',
				adminid INT UNSIGNED NOT NULL DEFAULT '0',
				change_time INT UNSIGNED NOT NULL DEFAULT '0',
				change_uniq VARCHAR(32) NOT NULL DEFAULT '',
				PRIMARY KEY  (changeid),
				KEY userid (userid,change_time),
				KEY change_time (change_time),
				KEY change_uniq (change_uniq),
				KEY fieldname (fieldname,change_time),
				KEY adminid (adminid,change_time)
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
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "forumprefix"),
			"CREATE TABLE " . TABLE_PREFIX . "forumprefixset (
				forumid INT UNSIGNED NOT NULL DEFAULT '0',
				prefixsetid VARCHAR(25) NOT NULL DEFAULT '',
				PRIMARY KEY (forumid, prefixsetid)
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
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "socialgroup"),
			"CREATE TABLE " . TABLE_PREFIX . "socialgroup (
				groupid INT unsigned NOT NULL auto_increment,
				name VARCHAR(255) NOT NULL DEFAULT '',
				description TEXT,
				creatoruserid INT unsigned NOT NULL DEFAULT '0',
				dateline INT unsigned NOT NULL DEFAULT '0',
				members INT unsigned NOT NULL DEFAULT '0',
				picturecount INT unsigned NOT NULL DEFAULT '0',
				lastpost INT unsigned NOT NULL DEFAULT '0',
				lastposter VARCHAR(255) NOT NULL DEFAULT '',
				lastposterid INT UNSIGNED NOT NULL DEFAULT '0',
				lastgmid INT UNSIGNED NOT NULL DEFAULT '0',
				visible INT UNSIGNED NOT NULL DEFAULT '0',
				deleted INT UNSIGNED NOT NULL DEFAULT '0',
				moderation INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY  (groupid),
				KEY creatoruserid (creatoruserid),
				KEY dateline (dateline),
				FULLTEXT KEY name (name, description)
			) ENGINE=MyISAM",
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
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "socialgroupmember"),
			"CREATE TABLE " . TABLE_PREFIX . "socialgroupmember (
				userid INT unsigned NOT NULL DEFAULT '0',
				groupid INT unsigned NOT NULL DEFAULT '0',
				dateline INT unsigned NOT NULL DEFAULT '0',
				PRIMARY KEY (groupid, userid),
				KEY userid (userid)
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
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "socialgrouppicture"),
			"CREATE TABLE " . TABLE_PREFIX . "socialgrouppicture (
				groupid INT UNSIGNED NOT NULL DEFAULT '0',
				pictureid INT UNSIGNED NOT NULL DEFAULT '0',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (groupid, pictureid),
				KEY groupid (groupid, dateline),
				KEY pictureid (pictureid)
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
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "prefix"),
			"CREATE TABLE " . TABLE_PREFIX . "prefix (
				prefixid VARCHAR(25) NOT NULL DEFAULT '',
				prefixsetid VARCHAR(25) NOT NULL DEFAULT '',
				displayorder INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (prefixid),
				KEY prefixsetid (prefixsetid, displayorder)
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
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . "prefixset"),
			"CREATE TABLE " . TABLE_PREFIX . "prefixset (
				prefixsetid VARCHAR(25) NOT NULL DEFAULT '',
				displayorder INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (prefixsetid)
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
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'notice'),
			"CREATE TABLE " . TABLE_PREFIX . "notice (
				noticeid INT UNSIGNED NOT NULL auto_increment,
				title VARCHAR(250) NOT NULL DEFAULT '',
				displayorder INT UNSIGNED NOT NULL DEFAULT '0',
				persistent SMALLINT UNSIGNED NOT NULL default '0',
				active SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (noticeid),
				KEY active (active)
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
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'noticecriteria'),
			"CREATE TABLE " . TABLE_PREFIX . "noticecriteria (
				noticeid INT UNSIGNED NOT NULL DEFAULT '0',
				criteriaid VARCHAR(250) NOT NULL DEFAULT '',
				condition1 VARCHAR(250) NOT NULL DEFAULT '',
				condition2 VARCHAR(250) NOT NULL DEFAULT '',
				condition3 VARCHAR(250) NOT NULL DEFAULT '',
				PRIMARY KEY (noticeid,criteriaid)
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
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'postlog'),
			"CREATE TABLE " . TABLE_PREFIX . "postlog (
				postid INT UNSIGNED NOT NULL DEFAULT '0',
				useragent CHAR(100) NOT NULL DEFAULT '',
				ip INT UNSIGNED NOT NULL DEFAULT '0',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (postid),
				KEY dateline (dateline),
				KEY ip (ip)
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
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'spamlog'),
			"CREATE TABLE " . TABLE_PREFIX . "spamlog (
				postid INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (postid)
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
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'bookmarksite'),
			"CREATE TABLE " . TABLE_PREFIX . "bookmarksite (
				bookmarksiteid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				title VARCHAR(250) NOT NULL DEFAULT '',
				iconpath VARCHAR(250) NOT NULL DEFAULT '',
				active  SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				displayorder INT UNSIGNED NOT NULL DEFAULT '0',
				url VARCHAR(250) NOT NULL DEFAULT '',
				PRIMARY KEY (bookmarksiteid)
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
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'tag'),
			"CREATE TABLE " . TABLE_PREFIX . "tag (
				tagid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				tagtext VARCHAR(100) NOT NULL DEFAULT '',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (tagid),
				UNIQUE KEY tagtext (tagtext)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #15
	*
	*/
	function step_15()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'tagthread'),
			"CREATE TABLE " . TABLE_PREFIX . "tagthread (
				tagid INT UNSIGNED NOT NULL DEFAULT '0',
				threadid INT UNSIGNED NOT NULL DEFAULT '0',
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (tagid, threadid),
				KEY threadid (threadid, userid),
				KEY dateline (dateline)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #16
	*
	*/
	function step_16()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'tagsearch'),
			"CREATE TABLE " . TABLE_PREFIX . "tagsearch (
				tagid INT UNSIGNED NOT NULL DEFAULT '0',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				KEY (tagid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #17
	*
	*/
	function step_17()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'postedithistory'),
			"CREATE TABLE " . TABLE_PREFIX . "postedithistory (
				postedithistoryid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				postid INT UNSIGNED NOT NULL DEFAULT '0',
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				username VARCHAR(100) NOT NULL DEFAULT '',
				title VARCHAR(250) NOT NULL DEFAULT '',
				iconid INT UNSIGNED NOT NULL DEFAULT '0',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				reason VARCHAR(200) NOT NULL DEFAULT '',
				original SMALLINT NOT NULL DEFAULT '0',
				pagetext MEDIUMTEXT,
				PRIMARY KEY  (postedithistoryid),
				KEY postid (postid,userid)
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
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'usercss'),
			"CREATE TABLE " . TABLE_PREFIX . "usercss (
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				selector VARCHAR(30) NOT NULL DEFAULT '',
				property VARCHAR(30) NOT NULL DEFAULT '',
				value VARCHAR(255) NOT NULL DEFAULT '',
				PRIMARY KEY (userid, selector, property),
				KEY property (property, userid, value(20))
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #19
	*
	*/
	function step_19()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'usercsscache'),
			"CREATE TABLE " . TABLE_PREFIX . "usercsscache (
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				cachedcss TEXT,
				buildpermissions INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (userid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #20
	*
	*/
	function step_20()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'paymentapi'),
			"UPDATE " . TABLE_PREFIX . "paymentapi SET currency = 'usd,gbp,eur,aud,cad' WHERE classname = 'moneybookers'"
		);
	}

	/**
	* Step #21
	*
	*/
	function step_21()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'tachyforumcounter'),
			"CREATE TABLE " . TABLE_PREFIX . "tachyforumcounter (
				userid int(10) unsigned NOT NULL default '0',
				forumid smallint(5) unsigned NOT NULL default '0',
				threadcount mediumint(8) unsigned NOT NULL default '0',
				replycount int(10) unsigned NOT NULL default '0',
				PRIMARY KEY  (userid,forumid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #22
	*
	*/
	function step_22()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'tachythreadcounter'),
			"CREATE TABLE " . TABLE_PREFIX . "tachythreadcounter (
				userid int(10) unsigned NOT NULL default '0',
				threadid int(10) unsigned NOT NULL default '0',
				replycount int(10) unsigned NOT NULL default '0',
				PRIMARY KEY  (userid,threadid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #23
	*
	*/
	function step_23()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'profilevisitor'),
			"CREATE TABLE " . TABLE_PREFIX . "profilevisitor (
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				visitorid INT UNSIGNED NOT NULL DEFAULT '0',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				visible SMALLINT UNSIGNED NOT NULL DEFAULT '1',
				PRIMARY KEY (visitorid, userid),
				KEY userid (userid, visible, dateline)
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
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'visitormessage'),
			"CREATE TABLE " . TABLE_PREFIX . "visitormessage (
			  vmid INT UNSIGNED NOT NULL auto_increment,
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				postuserid INT UNSIGNED NOT NULL DEFAULT '0',
				postusername VARCHAR(100) NOT NULL  DEFAULT '',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				state ENUM('visible','moderation','deleted') NOT NULL default 'visible',
				title VARCHAR(255) NOT NULL DEFAULT '',
				pagetext MEDIUMTEXT,
				ipaddress INT UNSIGNED NOT NULL DEFAULT '0',
				allowsmilie SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				reportthreadid INT UNSIGNED NOT NULL DEFAULT '0',
				messageread SMALLINT UNSIGNED NOT NULL DEFAULT '0',
			  PRIMARY KEY  (vmid),
				KEY postuserid (postuserid, userid, state),
				KEY userid (userid, dateline, state)
			)",
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
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'visitormessage_hash'),
			"CREATE TABLE " . TABLE_PREFIX . "visitormessage_hash (
				postuserid INT UNSIGNED NOT NULL DEFAULT '0',
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				dupehash VARCHAR(32) NOT NULL DEFAULT '',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				KEY postuserid (postuserid, dupehash),
				KEY dateline (dateline)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #26
	*
	*/
	function step_26()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'groupmessage'),
			"CREATE TABLE " . TABLE_PREFIX . "groupmessage (
				gmid INT UNSIGNED NOT NULL auto_increment,
				groupid INT UNSIGNED NOT NULL DEFAULT '0',
				postuserid INT UNSIGNED NOT NULL DEFAULT '0',
				postusername VARCHAR(100) NOT NULL DEFAULT '',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				state ENUM('visible','moderation','deleted') NOT NULL default 'visible',
				title VARCHAR(255) NOT NULL DEFAULT '',
				pagetext MEDIUMTEXT,
				ipaddress INT UNSIGNED NOT NULL DEFAULT '0',
				allowsmilie SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				reportthreadid INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY  (gmid),
				KEY postuserid (postuserid, groupid, state),
				KEY groupid (groupid, dateline, state)
			)",
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
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'groupmessage_hash'),
			"CREATE TABLE " . TABLE_PREFIX . "groupmessage_hash (
				postuserid INT UNSIGNED NOT NULL DEFAULT '0',
				groupid INT UNSIGNED NOT NULL DEFAULT '0',
				dupehash VARCHAR(32) NOT NULL DEFAULT '',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				KEY postuserid (postuserid, dupehash),
				KEY dateline (dateline)
			)",
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
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'album'),
			"CREATE TABLE " . TABLE_PREFIX . "album (
				albumid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				createdate INT UNSIGNED NOT NULL DEFAULT '0',
				lastpicturedate INT UNSIGNED NOT NULL DEFAULT '0',
				picturecount INT UNSIGNED NOT NULL DEFAULT '0',
				title VARCHAR(100) NOT NULL DEFAULT '',
				description TEXT,
				public SMALLINT UNSIGNED NOT NULL DEFAULT '1',
				coverpictureid INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (albumid),
				KEY userid (userid, lastpicturedate)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #29
	*
	*/
	function step_29()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'albumpicture'),
			"CREATE TABLE " . TABLE_PREFIX . "albumpicture (
				albumid INT UNSIGNED NOT NULL DEFAULT '0',
				pictureid INT UNSIGNED NOT NULL DEFAULT '0',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (albumid, pictureid),
				KEY albumid (albumid, dateline),
				KEY pictureid (pictureid)
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
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'picture'),
			"CREATE TABLE " . TABLE_PREFIX . "picture (
				pictureid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				caption TEXT,
				extension VARCHAR(20) NOT NULL DEFAULT '',
				filedata MEDIUMBLOB,
				filesize INT UNSIGNED NOT NULL DEFAULT '0',
				width SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				height SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				thumbnail MEDIUMBLOB,
				thumbnail_filesize INT UNSIGNED NOT NULL DEFAULT '0',
				thumbnail_width SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				thumbnail_height SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				thumbnail_dateline INT UNSIGNED NOT NULL DEFAULT '0',
				idhash VARCHAR(32) NOT NULL DEFAULT '',
				reportthreadid INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (pictureid),
				KEY userid (userid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #31
	*
	*/
	function step_31()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'humanverify'),
			"CREATE TABLE " . TABLE_PREFIX . "humanverify (
				hash CHAR(32) NOT NULL DEFAULT '',
				answer MEDIUMTEXT,
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				viewed SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				KEY hash (hash),
				KEY dateline (dateline)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #32
	*
	*/
	function step_32()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'hvanswer'),
			"CREATE TABLE " . TABLE_PREFIX . "hvanswer (
	 			answerid INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
	 			questionid INT NOT NULL DEFAULT '0',
	 			answer VARCHAR(255) NOT NULL DEFAULT '',
	 			dateline INT UNSIGNED NOT NULL DEFAULT '0',
	 			INDEX (questionid)
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #33
	*
	*/
	function step_33()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'hvquestion'),
			"CREATE TABLE " . TABLE_PREFIX . "hvquestion (
	 			questionid INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
	 			regex VARCHAR(255) NOT NULL DEFAULT '',
	 			dateline INT UNSIGNED NOT NULl DEFAULT '0'
			)",
			self::MYSQL_ERROR_TABLE_EXISTS
		);
	}

	/**
	* Step #34
	*
	*/
	function step_34()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'thread', 1, 2),
			'thread',
			'prefixid',
			'varchar',
			array('length' => 25, 'attributes' => self::FIELD_DEFAULTS)
		);
	}

	/**
	* Step #35
	*
	*/
	function step_35()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'thread', 2, 3),
			'thread',
			'prefixid',
			array('prefixid', 'forumid')
		);
	}

	/**
	* Step #36
	*
	*/
	function step_36()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'thread', 3, 3),
			'thread',
			'taglist',
			'mediumtext',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #37
	*
	*/
	function step_37()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'deletionlog', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "deletionlog CHANGE type type ENUM('post', 'thread', 'visitormessage', 'groupmessage') NOT NULL DEFAULT 'post'"
		);
	}

	/**
	* Step #38
	*
	*/
	function step_38()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'customavatar', 1, 3),
			'customavatar',
			'filedata_thumb',
			'mediumblob',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #39
	*
	*/
	function step_39()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'customavatar', 2, 3),
			'customavatar',
			'width_thumb',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #40
	*
	*/
	function step_40()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'customavatar', 3, 3),
			'customavatar',
			'height_thumb',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #41
	*
	*/
	function step_41()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'forum', 1, 2),
			'forum',
			'lastprefixid',
			'varchar',
			array('length' => 25, 'attributes' => self::FIELD_DEFAULTS)
		);
	}

	/**
	* Step #42
	*
	*/
	function step_42()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'forum', 2, 2),
			'forum',
			'imageprefix',
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
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'tachyforumpost', 1, 1),
			'tachyforumpost',
			'lastprefixid',
			'varchar',
			array('length' => 25, 'attributes' => self::FIELD_DEFAULTS)
		);
	}

	/**
	* Step #44
	*
	*/
	function step_44()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'rssfeed', 1, 1),
			'rssfeed',
			'prefixid',
			'varchar',
			array('length' => 25, 'attributes' => self::FIELD_DEFAULTS)
		);
	}

	/**
	* Step #45
	*
	*/
	function step_45()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'search', 1, 1),
			'search',
			'prefixchoice',
			'mediumtext',
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
			sprintf($this->phrase['core']['altering_x_table'], 'language', 1, 5),
			'language',
			'phrasegroup_prefix',
			'mediumtext',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #47
	*
	*/
	function step_47()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'language', 2, 5),
			'language',
			'phrasegroup_socialgroups',
			'mediumtext',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #48
	*
	*/
	function step_48()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'language', 3, 5),
			'language',
			'phrasegroup_prefixadmin',
			'mediumtext',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #49
	*
	*/
	function step_49()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'language', 4, 5),
			'language',
			'phrasegroup_notice',
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
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'language', 5, 5),
			'language',
			'phrasegroup_album',
			'mediumtext',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #51
	*
	*/
	function step_51()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'editlog', 1, 1),
			'editlog',
			'hashistory',
			'SMALLINT',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #52
	*
	*/
	function step_52()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'userlist', 1, 4),
			"ALTER  TABLE  " . TABLE_PREFIX . "userlist ADD friend ENUM('yes', 'no', 'pending', 'denied') NOT NULL DEFAULT 'no'",
			self::MYSQL_ERROR_COLUMN_EXISTS
		);
	}

	/**
	* Step #53
	*
	*/
	function step_53()
	{
		$this->drop_index(
			sprintf($this->phrase['core']['altering_x_table'], 'userlist', 2, 4),
			'userlist',
			'relationid'
		);
	}

	/**
	* Step #54
	*
	*/
	function step_54()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'userlist', 3, 4),
			'userlist',
			'relationid',
			array('relationid', 'type', 'friend')
		);
	}

	/**
	* Step #55
	*
	*/
	function step_55()
	{
		$this->add_index(
			sprintf($this->phrase['core']['altering_x_table'], 'userlist', 4, 4),
			'userlist',
			'userid',
			array('userid', 'type', 'friend')
		);
	}

	/**
	* Step #56
	*
	*/
	function step_56()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'user', 1, 5),
			'user',
			'profilevisits',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #57
	*
	*/
	function step_57()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'user', 2, 5),
			'user',
			'friendcount',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #58
	*
	*/
	function step_58()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'user', 3, 5),
			'user',
			'friendreqcount',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #59
	*
	*/
	function step_59()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'user', 4, 5),
			'user',
			'vmunreadcount',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #60
	*
	*/
	function step_60()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'user', 5, 5),
			'user',
			'vmmoderatedcount',
			'int',
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
			sprintf($this->phrase['core']['altering_x_table'], 'bbcode', 1, 1),
			'bbcode',
			'options',
			'int',
			array('default' => 1, 'attributes' => self::FIELD_DEFAULTS)
		);
	}

	/**
	* Step #62
	*
	*/
	function step_62()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'moderator', 1, 1),
			'moderator',
			'permissions2',
			'int',
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
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 1, 10),
			'usergroup',
			'visitormessagepermissions',
			'INT',
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
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 2, 10),
			'usergroup',
			'socialgrouppermissions',
			'INT',
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
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 3, 10),
			'usergroup',
			'usercsspermissions',
			'int',
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
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 4, 10),
			'usergroup',
			'albumpermissions',
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
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 5, 10),
			'usergroup',
			'albumpicmaxwidth',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #68
	*
	*/
	function step_68()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 6, 10),
			'usergroup',
			'albumpicmaxheight',
			'smallint',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #69
	*
	*/
	function step_69()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 7, 10),
			'usergroup',
			'albumpicmaxsize',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #70
	*
	*/
	function step_70()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 8, 10),
			'usergroup',
			'albummaxpics',
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
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 9, 10),
			'usergroup',
			'albummaxsize',
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
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 10, 10),
			'usergroup',
			'genericpermissions2',
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
			sprintf($this->phrase['core']['altering_x_table'], 'moderation', 1, 6),
			"ALTER TABLE " . TABLE_PREFIX . "moderation CHANGE type type ENUM('thread', 'reply', 'visitormessage', 'groupmessage') NOT NULL DEFAULT 'thread'"
		);
	}

	/**
	* Step #74
	*
	*/
	function step_74()
	{
		if ($this->field_exists('moderation', 'threadid'))
		{
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'moderation', 2, 6),
				"ALTER TABLE " . TABLE_PREFIX . "moderation CHANGE threadid primaryid INT UNSIGNED NULL DEFAULT '0'"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #75
	*
	*/
	function step_75()
	{
		if ($this->field_exists('moderation', 'postid'))
		{
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'moderation', 3, 6),
				"UPDATE " . TABLE_PREFIX . "moderation SET primaryid = postid WHERE type = 'reply'"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #76
	*
	*/
	function step_76()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'moderation', 4, 6),
			"ALTER TABLE " . TABLE_PREFIX . "moderation DROP PRIMARY KEY",
			self::MYSQL_ERROR_DROP_KEY_COLUMN_MISSING
		);
	}

	/**
	* Step #77
	*
	*/
	function step_77()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'moderation', 5, 6),
			"ALTER IGNORE TABLE " . TABLE_PREFIX . "moderation ADD PRIMARY KEY (primaryid, type)",
			self::MYSQL_ERROR_KEY_EXISTS
		);
	}

	/**
	* Step #78
	*
	*/
	function step_78()
	{
		$this->drop_field(
			sprintf($this->phrase['core']['altering_x_table'], 'moderation', 6, 6),
			'moderation',
			'postid'
		);
	}

	/**
	* Step #79
	*
	*/
	function step_79()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'profilefieldcategory', 1, 1),
			'profilefieldcategory',
			'location',
			'varchar',
			array('length' => 25, 'attributes' => self::FIELD_DEFAULTS)
		);
	}

	/**
	* Step #80
	*
	*/
	function step_80()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'rssfeed', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "rssfeed CHANGE url url TEXT"
		);
	}

	/**
	* Step #81
	*
	*/
	function step_81()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'regimage', 1, 1),
			"DROP TABLE IF EXISTS " . TABLE_PREFIX . "regimage"
		);
	}

	/**
	* Step #82
	*
	*/
	function step_82()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'setting', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "setting CHANGE datatype datatype ENUM('free', 'number', 'boolean', 'bitfield', 'username', 'integer') NOT NULL DEFAULT 'free'"
		);
	}

	/**
	* Step #83
	*
	*/
	function step_83()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'search', 1, 1),
			'search',
			'completed',
			'smallint',
			array('null' => false, 'default' => 1)
		);
	}

	/**
	* Step #84
	*
	*/
	function step_84()
	{
		$bookmarkcount = $this->db->query_first("
			SELECT COUNT(*) AS total
			FROM " . TABLE_PREFIX . "bookmarksite
		");
		if ($bookmarkcount['total'] == 0)
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "bookmarksite"),
				"INSERT INTO " . TABLE_PREFIX . "bookmarksite
					(title, active, displayorder, iconpath, url)
				VALUES
					('Digg',        1, 10, 'bookmarksite_digg.gif',        'http://digg.com/submit?phrase=2&amp;url={URL}&amp;title={TITLE}'),
					('del.icio.us', 1, 20, 'bookmarksite_delicious.gif',   'http://del.icio.us/post?url={URL}&amp;title={TITLE}'),
					('StumbleUpon', 1, 30, 'bookmarksite_stumbleupon.gif', 'http://www.stumbleupon.com/submit?url={URL}&amp;title={TITLE}'),
					('Google',      1, 40, 'bookmarksite_google.gif',      'http://www.google.com/bookmarks/mark?op=edit&amp;output=popup&amp;bkmk={URL}&amp;title={TITLE}')
				"
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #85
	*
	*/
	function step_85()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "phrasetype"),
			"INSERT IGNORE INTO " . TABLE_PREFIX . "phrasetype
				(title, editrows, fieldname, special)
			VALUES
				('{$this->phrase['phrasetype']['prefix']}', 3, 'prefix', 0),
				('{$this->phrase['phrasetype']['prefixadmin']}', 3, 'prefixadmin', 0),
				('{$this->phrase['phrasetype']['socialgroups']}', 3, 'socialgroups', 0),
				('{$this->phrase['phrasetype']['notice']}', 3, 'notice', 0),
				('{$this->phrase['phrasetype']['hvquestion']}', 3, 'hvquestion', 1),
				('{$this->phrase['phrasetype']['album']}', 3, 'album', 0)"
		);
	}

	/**
	* Step #86
	*
	*/
	function step_86()
	{
		if (trim($this->registry->options['globalignore']) != '')
		{
			$this->add_adminmessage(
				'after_upgrade_37_update_counters',
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
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #87 - Check for modified Additional CSS template
	*
	*/
	function step_87()
	{
		if ($this->db->query_first("
			SELECT template.styleid
			FROM " . TABLE_PREFIX . "template AS template
			INNER JOIN " . TABLE_PREFIX . "style AS style USING (styleid)
			WHERE
				template.title = 'EXTRA' AND
				template.templatetype = 'css' AND
				template.product IN ('', 'vbulletin') AND
				template.styleid <> -1
			LIMIT 1
		"))
		{
			$rows = $this->db->query_first("SELECT COUNT(*) AS count FROM " . TABLE_PREFIX . "adminmessage WHERE varname = 'after_upgrade_37_modified_css'");
			if ($rows['count'] == 0)
			{
				$this->add_adminmessage(
					'after_upgrade_37_modified_css',
					array(
						'dismissable' => 1,
						'script'      => 'template.php',
						'action'      => 'modify',
						'execurl'     => 'template.php?do=modify',
						'method'      => 'get',
						'status'      => 'undone',
					)
				);
			}
			else
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
	* Step #88
	*
	*/
	function step_88()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 1, 1),
			"UPDATE " . TABLE_PREFIX . "usergroup SET
				forumpermissions = forumpermissions |
					IF(forumpermissions & " . $this->registry->bf_ugp_forumpermissions['canpostnew'] . ", " . $this->registry->bf_ugp_forumpermissions['cantagown'] . ", 0) |
					IF(forumpermissions & " . $this->registry->bf_ugp_forumpermissions['canreplyothers'] . ", " . $this->registry->bf_ugp_forumpermissions['cantagothers'] . ", 0) |
					IF(forumpermissions & " . $this->registry->bf_ugp_forumpermissions['candeletethread'] . ", " . $this->registry->bf_ugp_forumpermissions['candeletetagown'] . ", 0),
				genericpermissions = genericpermissions |
					IF(forumpermissions & " . $this->registry->bf_ugp_forumpermissions['canreplyothers'] . "
						OR forumpermissions & " . $this->registry->bf_ugp_forumpermissions['canpostnew'] . ", " . $this->registry->bf_ugp_genericpermissions['cancreatetag'] . ", 0),

				genericpermissions2 = genericpermissions2 |
					IF(genericpermissions & " . $this->registry->bf_ugp_genericpermissions['canseeprofilepic'] . "
						OR genericpermissions & " . $this->registry->bf_ugp_genericpermissions['canemailmember'] . ", " . $this->registry->bf_ugp_genericpermissions2['canusefriends'] . ", 0),

				albumpermissions = albumpermissions |
					IF(genericpermissions & " . $this->registry->bf_ugp_genericpermissions['canmodifyprofile'] . "
						AND genericpermissions & " . $this->registry->bf_ugp_genericpermissions['canprofilepic'] . ", " . $this->registry->bf_ugp_albumpermissions['canalbum'] . ", 0) |
					IF(genericpermissions & " . $this->registry->bf_ugp_genericpermissions['canviewmembers'] . ", " . $this->registry->bf_ugp_albumpermissions['canviewalbum'] . ", 0),
				albumpicmaxwidth = 600,
				albumpicmaxheight = 600,
				albumpicmaxsize = 100000,
				albummaxpics = 100,
				albummaxsize = 0,

				usercsspermissions = usercsspermissions |
					IF(genericpermissions & " . $this->registry->bf_ugp_genericpermissions['canmodifyprofile'] . "
						AND signaturepermissions & " . $this->registry->bf_ugp_signaturepermissions['canbbcodefont'] . ", " . $this->registry->bf_ugp_usercsspermissions['caneditfontfamily'] . ", 0) |
					IF(genericpermissions & " . $this->registry->bf_ugp_genericpermissions['canmodifyprofile'] . "
						AND signaturepermissions & " . $this->registry->bf_ugp_signaturepermissions['canbbcodefont'] . ", " . $this->registry->bf_ugp_usercsspermissions['caneditfontsize'] . ", 0) |
					IF(genericpermissions & " . $this->registry->bf_ugp_genericpermissions['canmodifyprofile'] . "
						AND signaturepermissions & " . $this->registry->bf_ugp_signaturepermissions['canbbcodecolor'] . ", " . $this->registry->bf_ugp_usercsspermissions['caneditcolors'] . ", 0) |
					IF(genericpermissions & " . $this->registry->bf_ugp_genericpermissions['canmodifyprofile'] . "
						AND genericpermissions & " . $this->registry->bf_ugp_genericpermissions['canprofilepic'] . "
						AND signaturepermissions & " . $this->registry->bf_ugp_signaturepermissions['canbbcodecolor'] . ", " . $this->registry->bf_ugp_usercsspermissions['caneditbgimage'] . ", 0) |
					IF(genericpermissions & " . $this->registry->bf_ugp_genericpermissions['canmodifyprofile'] . "
						AND signaturepermissions & " . $this->registry->bf_ugp_signaturepermissions['canbbcodecolor'] . ", " . $this->registry->bf_ugp_usercsspermissions['caneditborders'] . ", 0),

				visitormessagepermissions = visitormessagepermissions |
					IF(forumpermissions & " . $this->registry->bf_ugp_forumpermissions['canreplyothers'] . "
						OR forumpermissions & " . $this->registry->bf_ugp_forumpermissions['canreplyown'] . ", " . $this->registry->bf_ugp_visitormessagepermissions['canmessageownprofile'] . ", 0) |
					IF(forumpermissions & " . $this->registry->bf_ugp_forumpermissions['canreplyothers'] . ", " . $this->registry->bf_ugp_visitormessagepermissions['canmessageothersprofile'] . ", 0) |
					IF(forumpermissions & " . $this->registry->bf_ugp_forumpermissions['caneditpost'] . ", " . $this->registry->bf_ugp_visitormessagepermissions['caneditownmessages'] . ", 0) |
					IF(forumpermissions & " . $this->registry->bf_ugp_forumpermissions['candeletepost'] . ", " . $this->registry->bf_ugp_visitormessagepermissions['candeleteownmessages'] . ", 0) |
					IF(forumpermissions & " . $this->registry->bf_ugp_forumpermissions['followforummoderation'] . ", " . $this->registry->bf_ugp_visitormessagepermissions['followforummoderation'] . ", 0) |
					IF(forumpermissions & " . $this->registry->bf_ugp_forumpermissions['caneditpost'] . "
						OR forumpermissions & " . $this->registry->bf_ugp_forumpermissions['candeletepost'] . ", " . $this->registry->bf_ugp_visitormessagepermissions['canmanageownprofile'] . ", 0),

				socialgrouppermissions = socialgrouppermissions |
					IF(genericpermissions & " . $this->registry->bf_ugp_genericpermissions['canviewmembers'] . ", " . $this->registry->bf_ugp_socialgrouppermissions['canjoingroups'] . ", 0) |
					IF(forumpermissions & " . $this->registry->bf_ugp_forumpermissions['canpostnew'] . ", " . $this->registry->bf_ugp_socialgrouppermissions['cancreategroups'] . ", 0) |
					IF(forumpermissions & " . $this->registry->bf_ugp_forumpermissions['caneditpost'] . ", " . $this->registry->bf_ugp_socialgrouppermissions['caneditowngroups'] . ", 0) |
					IF(forumpermissions & " . $this->registry->bf_ugp_forumpermissions['candeletethread'] . ", " . $this->registry->bf_ugp_socialgrouppermissions['candeleteowngroups'] . ", 0) |
					IF(genericpermissions & " . $this->registry->bf_ugp_genericpermissions['canviewmembers'] . ", " . $this->registry->bf_ugp_socialgrouppermissions['canviewgroups'] . ", 0) |
					IF(forumpermissions & " . $this->registry->bf_ugp_forumpermissions['caneditpost']  . "
						OR forumpermissions & " . $this->registry->bf_ugp_forumpermissions['candeletepost'] . ", " . $this->registry->bf_ugp_socialgrouppermissions['canmanagemessages'] . ", 0) |
					IF(adminpermissions & " . $this->registry->bf_ugp_adminpermissions['ismoderator'] . ", " . $this->registry->bf_ugp_socialgrouppermissions['canalwayspostmessage'] . ", 0) |
					" . $this->registry->bf_ugp_socialgrouppermissions['followforummoderation'] . "
			"
		);
	}

	/**
	* Step #89
	*
	*/
	function step_89()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'forumpermission', 1, 1),
			"UPDATE " . TABLE_PREFIX . "forumpermission SET
				forumpermissions = forumpermissions |
					IF(forumpermissions & " . $this->registry->bf_ugp_forumpermissions['canpostnew'] . ", " . $this->registry->bf_ugp_forumpermissions['cantagown'] . ", 0) |
					IF(forumpermissions & " . $this->registry->bf_ugp_forumpermissions['canreplyothers'] . ", " . $this->registry->bf_ugp_forumpermissions['cantagothers'] . ", 0) |
					IF(forumpermissions & " . $this->registry->bf_ugp_forumpermissions['candeletethread'] . ", " . $this->registry->bf_ugp_forumpermissions['candeletetagown'] . ", 0)
			"
		);
	}

	/**
	* Step #90
	*
	*/
	function step_90()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'moderator', 1, 1),
			"UPDATE " . TABLE_PREFIX . "moderator SET
				permissions2 = permissions2 |
					IF(permissions & " . $this->registry->bf_misc_moderatorpermissions['caneditposts'] . ", " . $this->registry->bf_misc_moderatorpermissions2['caneditvisitormessages'] . ", 0) |
					IF(permissions & " . $this->registry->bf_misc_moderatorpermissions['candeleteposts'] . ", " . $this->registry->bf_misc_moderatorpermissions2['candeletevisitormessages'] . ", 0) |
					IF(permissions & " . $this->registry->bf_misc_moderatorpermissions['canremoveposts'] . ", " . $this->registry->bf_misc_moderatorpermissions2['canremovevisitormessages'] . ", 0) |
					IF(permissions & " . $this->registry->bf_misc_moderatorpermissions['canmoderateposts'] . ", " . $this->registry->bf_misc_moderatorpermissions2['canmoderatevisitormessages'] . ", 0) |
					IF(permissions & " . $this->registry->bf_misc_moderatorpermissions['caneditavatar'] . ", " . $this->registry->bf_misc_moderatorpermissions2['caneditalbumpicture'] . ", 0) |
					IF(permissions & " . $this->registry->bf_misc_moderatorpermissions['caneditavatar'] . ", " . $this->registry->bf_misc_moderatorpermissions2['candeletealbumpicture'] . ", 0) |
					IF(permissions & " . $this->registry->bf_misc_moderatorpermissions['caneditposts'] . ", " . $this->registry->bf_misc_moderatorpermissions2['caneditsocialgroups'] . ", 0) |
					IF(permissions & " . $this->registry->bf_misc_moderatorpermissions['candeleteposts'] . ", " . $this->registry->bf_misc_moderatorpermissions2['candeletesocialgroups'] . ", 0) |
					IF(permissions & " . $this->registry->bf_misc_moderatorpermissions['candeleteposts'] . ", " . $this->registry->bf_misc_moderatorpermissions2['candeletegroupmessages'] . ", 0) |
					IF(permissions & " . $this->registry->bf_misc_moderatorpermissions['canremoveposts'] . ", " . $this->registry->bf_misc_moderatorpermissions2['canremovegroupmessages'] . ", 0) |
					IF(permissions & " . $this->registry->bf_misc_moderatorpermissions['canmoderateposts'] . ", " . $this->registry->bf_misc_moderatorpermissions2['canmoderategroupmessages'] . ", 0) |
					IF(permissions & " . $this->registry->bf_misc_moderatorpermissions['caneditposts'] . ", " . $this->registry->bf_misc_moderatorpermissions2['caneditgroupmessages'] . ", 0)
			"
		);
	}

	/**
	* Step #91
	*
	*/
	function step_91()
	{
		$this->add_adminmessage(
			'after_upgrade_37_moderator_permissions',
			array(
				'dismissable' => 1,
				'script'      => 'moderator.php',
				'action'      => 'showlist',
				'execurl'     => 'moderator.php?do=showlist',
				'method'      => 'get',
				'status'      => 'undone',
			)
		);
	}

	/**
	* Step #92
	*
	*/
	function step_92()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'user'),
			"UPDATE " . TABLE_PREFIX . "user SET options = options | " . $this->registry->bf_misc_useroptions['showusercss'] . " | " . $this->registry->bf_misc_useroptions['receivefriendemailrequest']
		);
	}

	/**
	* Step #93
	*
	*/
	function step_93()
	{
		if ($this->registry->options['thumbquality'] <= 70)
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "setting"),
				"UPDATE " . TABLE_PREFIX . "setting SET value = '65' WHERE varname = 'thumbquality'"
			);
		}
		else if ($this->registry->options['thumbquality'] >= 90)
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "setting"),
				"UPDATE " . TABLE_PREFIX . "setting SET value = '95' WHERE varname = 'thumbquality'"
			);
		}
		else if ($this->registry->options['thumbquality'] >= 80)
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "setting"),
				"UPDATE " . TABLE_PREFIX . "setting SET value = '95' WHERE varname = 'thumbquality'"
			);
		}
		else
		{
			$this->run_query(
				sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . "setting"),
				"UPDATE " . TABLE_PREFIX . "setting SET value = '75' WHERE varname = 'thumbquality'"
			);
		}
	}

	/**
	* Step #94 - add YUI to headinclude so things don't suddenly just stop working
	*
	*/
	function step_94()
	{
		require_once(DIR . '/includes/adminfunctions_template.php');
		$skip = true;

		$templates = $this->db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "template
			WHERE styleid > 0
				AND title IN ('headinclude')
		");
		while ($template = $this->db->fetch_array($templates))
		{
			if (strpos($template['template_un'], '$stylevar[yuipath]') !== false)
			{
				continue;
			}

			$template['template_un'] = str_replace(
				'<script type="text/javascript">' . "\r\n" . '<!--' . "\r\n" . 'var SESSIONURL = "$session[sessionurl_js]";',
				'<script type="text/javascript" src="$stylevar[yuipath]/yahoo-dom-event/yahoo-dom-event.js?v=$vboptions[simpleversion]"></script>' . "\n" . '<script type="text/javascript" src="$stylevar[yuipath]/connection/connection-min.js?v=$vboptions[simpleversion]"></script>'  . "\n" . '<script type="text/javascript">' . "\n" . '<!--' . "\n" . 'var SESSIONURL = "$session[sessionurl_js]";',
				$template['template_un']
			);

			// check in case it was newlines only
			if (strpos($template['template_un'], '$stylevar[yuipath]') === false)
			{
				$template['template_un'] = str_replace(
					'<script type="text/javascript">' . "\n" . '<!--' . "\n" . 'var SESSIONURL = "$session[sessionurl_js]";',
					'<script type="text/javascript" src="$stylevar[yuipath]/yahoo-dom-event/yahoo-dom-event.js?v=$vboptions[simpleversion]"></script>' . "\n" . '<script type="text/javascript" src="$stylevar[yuipath]/connection/connection-min.js?v=$vboptions[simpleversion]"></script>'  . "\n" . '<script type="text/javascript">' . "\n" . '<!--' . "\n" . 'var SESSIONURL = "$session[sessionurl_js]";',
					$template['template_un']
				);
			}

			$compiled_template = compile_template($template['template_un']);

			$this->run_query(
				sprintf($this->phrase['vbphrase']['apply_critical_template_change_to_x'], $template['title'], $template['styleid']),
				"UPDATE " . TABLE_PREFIX . "template SET
					template = '" . $this->db->escape_string($compiled_template) . "',
					template_un = '" . $this->db->escape_string($template['template_un']) . "'
				WHERE templateid = $template[templateid]
			");

			$skip = false;
		}

		if ($skip)
		{
			$this->skip_message();
		}
	}

	/**
	* Step #95
	*
	*/
	function step_95()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'setting', 1, 1),
			"UPDATE " . TABLE_PREFIX . "setting SET value = 'GDttf' WHERE varname = 'regimagetype' AND value = 'GD'"
		);
	}

	/**
	* Step #96
	*
	*/
	function step_96()
	{
		if ($this->db->query_first("SELECT varname FROM " . TABLE_PREFIX . "setting WHERE varname = 'regimagetype' AND value IN ('GDttf', 'GD')"))
		{
			require_once(DIR . '/includes/adminfunctions_options.php');
			$gdinfo = fetch_gdinfo();
			if ($gdinfo['freetype'] != 'freetype')
			{
				// they won't be able to use the simple text version and they don't have FreeType support, so no image verification
				$this->run_query(
					sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'setting'),
					"INSERT IGNORE INTO " . TABLE_PREFIX . "setting
						(varname, grouptitle, value, volatile, product)
					VALUES
						('hv_type', 'version', '0', 1, 'vbulletin')"
				);

				$this->add_adminmessage(
					'after_upgrade_37_image_verification_disabled',
					array(
						'dismissable' => 1,
						'script'      => 'verify.php',
						'action'      => '',
						'execurl'     => 'verify.php',
						'method'      => 'get',
						'status'      => 'undone',
					)
				);
			}
			else
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
	* Step #97
	*
	* @param	array	contains id to startat processing at
	*
	* @return	mixed
	*/
	function step_97($data = null)
	{
		$startat = intval($data['startat']);

		$this->show_message(sprintf($this->phrase['vbphrase']['update_table'], TABLE_PREFIX . 'userlist'));
		$perpage = 100;
		$users = $this->db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "user AS user
			LEFT JOIN ". TABLE_PREFIX . "usertextfield AS usertextfield ON (usertextfield.userid = user.userid)
			WHERE user.userid > $startat AND (usertextfield.ignorelist <> '' OR usertextfield.buddylist <> '')
			ORDER BY user.userid ASC
			" . ($this->limitqueries ? "LIMIT 0, $perpage" : "") . "
		");

		// check to see if we have some results...
		if ($this->db->num_rows($users))
		{
			$lastid = 0;
			while ($user = $this->db->fetch_array($users))
			{
				$this->show_message(sprintf($this->phrase['version']['370b2']['build_userlist'], $user['userid']));

				$buddylist = preg_split('/( )+/', trim($user['buddylist']), -1, PREG_SPLIT_NO_EMPTY);
				$ignorelist = preg_split('/( )+/', trim($user['ignorelist']), -1, PREG_SPLIT_NO_EMPTY);

				if (!empty($buddylist))
				{
					$buddylist = array_map('intval', $buddylist);
					foreach ($buddylist AS $buddyid)
					{
						$this->db->query_write("INSERT IGNORE INTO " . TABLE_PREFIX . "userlist (userid, relationid, type, friend) VALUES (" . $user['userid'] . ", " . $buddyid . ", 'buddy', 'no')");
					}
				}

				if (!empty($ignorelist))
				{
					$ignorelist = array_map('intval', $ignorelist);
					foreach ($ignorelist AS $ignoreid)
					{
						$this->db->query_write("INSERT IGNORE INTO " . TABLE_PREFIX . "userlist (userid, relationid, type, friend) VALUES (" . $user['userid'] . ", " . $ignoreid . ", 'ignore', 'no')");
					}
				}
				$lastid = $user['userid'];
			}
			return array('startat' => $lastid);
		}
		else
		{
			$this->show_message($this->phrase['version']['370b2']['build_userlist_complete']);
		}

		require_once(DIR . '/includes/adminfunctions_bookmarksite.php');
		build_bookmarksite_datastore();
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/