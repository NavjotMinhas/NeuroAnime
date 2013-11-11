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

class vB_Upgrade_370b4 extends vB_Upgrade_Version
{
	/*Constants=====================================================================*/

	/*Properties====================================================================*/

	/**
	* The short version of the script
	*
	* @var	string
	*/
	public $SHORT_VERSION = '370b4';

	/**
	* The long version of the script
	*
	* @var	string
	*/
	public $LONG_VERSION  = '3.7.0 Beta 4';

	/**
	* Versions that can upgrade to this script
	*
	* @var	string
	*/
	public $PREV_VERSION = '3.7.0 Beta 3';

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
		if (!isset($this->registry->bf_misc_moderatorpermissions2['caneditpicturecomments']))
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
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'album', 1, 3),
			'album',
			'state',
			'enum',
			array('attributes' => "('public', 'private', 'profile')", 'null' => false, 'default' => 'public')
		);
	}

	/**
	* Step #3
	*
	*/
	function step_3()
	{
		if ($this->field_exists('album', 'public'))
		{
			$this->run_query(
				sprintf($this->phrase['core']['altering_x_table'], 'album', 2, 3),
				"UPDATE " . TABLE_PREFIX . "album SET state = 'private' WHERE public = 0"
			);

			$this->drop_field(
				sprintf($this->phrase['core']['altering_x_table'], 'album', 3, 3),
				'album',
				'public'
			);
		}
		else
		{
			$this->skip_message();
		}
	}

	/**
	* Step #4
	*
	*/
	function step_4()
	{
		// Change the extension field to binary - all extension fields must be binary
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'picture', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "picture CHANGE extension extension VARCHAR(20) BINARY NOT NULL DEFAULT ''"
		);
	}

	/**
	* Step #5
	*
	*/
	function step_5()
	{
		$this->run_query(
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'picturecomment_hash'),
			"CREATE TABLE " . TABLE_PREFIX . "picturecomment_hash (
				postuserid INT UNSIGNED NOT NULL DEFAULT '0',
				pictureid INT UNSIGNED NOT NULL DEFAULT '0',
				dupehash VARCHAR(32) NOT NULL DEFAULT '',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				KEY postuserid (postuserid, dupehash),
				KEY dateline (dateline)
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
			sprintf($this->phrase['vbphrase']['create_table'], TABLE_PREFIX . 'picturecomment'),
			"CREATE TABLE " . TABLE_PREFIX . "picturecomment (
				commentid INT UNSIGNED NOT NULL AUTO_INCREMENT,
				pictureid INT UNSIGNED NOT NULL DEFAULT '0',
				postuserid INT UNSIGNED NOT NULL DEFAULT '0',
				postusername varchar(100) NOT NULL DEFAULT '',
				dateline INT UNSIGNED NOT NULL DEFAULT '0',
				state ENUM('visible','moderation','deleted') NOT NULL DEFAULT 'visible',
				title VARCHAR(255) NOT NULL DEFAULT '',
				pagetext MEDIUMTEXT,
				ipaddress INT UNSIGNED NOT NULL DEFAULT '0',
				allowsmilie SMALLINT NOT NULL DEFAULT '1',
				reportthreadid INT UNSIGNED NOT NULL DEFAULT '0',
				messageread SMALLINT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (commentid),
				KEY pictureid (pictureid, dateline, state),
				KEY postuserid (postuserid, pictureid, state)
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
			sprintf($this->phrase['core']['altering_x_table'], 'deletionlog', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "deletionlog CHANGE type type ENUM('post', 'thread', 'visitormessage', 'groupmessage', 'picturecomment') NOT NULL DEFAULT 'post'"
		);
	}

	/**
	* Step #8
	*
	*/
	function step_8()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'moderation', 1, 1),
			"ALTER TABLE " . TABLE_PREFIX . "moderation CHANGE type type ENUM('thread', 'reply', 'visitormessage', 'groupmessage', 'picturecomment') NOT NULL DEFAULT 'thread'"
		);
	}

	/**
	* Step #9
	*
	*/
	function step_9()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'user', 1, 2),
			'user',
			'pcunreadcount',
			'int',
			self::FIELD_DEFAULTS
		);
	}

	/**
	* Step #10
	*
	*/
	function step_10()
	{
		$this->add_field(
			sprintf($this->phrase['core']['altering_x_table'], 'user', 2, 2),
			'user',
			'pcmoderatedcount',
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
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'poll', 1, 1),
			"DELETE pollvote, poll
			FROM " . TABLE_PREFIX . "poll AS poll
			LEFT JOIN " . TABLE_PREFIX . "pollvote AS pollvote ON (poll.pollid = pollvote.pollid)
			LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (poll.pollid = thread.pollid)
			WHERE thread.threadid IS NULL"
		);
	}

	/**
	* Step #12
	*
	*/
	function step_12()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'calendarcustomfield', 1, 1),
			"DELETE {$needprefix}calendarcustomfield
			FROM " . TABLE_PREFIX . "calendarcustomfield AS calendarcustomfield
			LEFT JOIN " . TABLE_PREFIX . "calendar AS calendar ON (calendar.calendarid = calendarcustomfield.calendarid)
			WHERE calendar.calendarid IS NULL"
		);
	}

	/**
	* Step #13
	*
	*/
	function step_13()
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
					('Google',      1, 40, 'bookmarksite_google.gif',      'http://www.google.com/bookmarks/mark?op=edit&amp;output=popup&amp;bkmk={URL}&amp;annotation={TITLE}')
				"
			);
		}
		else
		{
				$this->skip_message();
		}
	}

	/**
	* Step #14
	*
	*/
	function step_14()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'usergroup', 1, 1),
			"UPDATE " . TABLE_PREFIX . "usergroup SET
				albumpermissions = albumpermissions |
					IF(forumpermissions & " . $this->registry->bf_ugp_forumpermissions['canreplyothers'] . ", " . $this->registry->bf_ugp_albumpermissions['canpiccomment'] . ", 0) |
					IF(forumpermissions & " . $this->registry->bf_ugp_forumpermissions['caneditpost'] . ", " . $this->registry->bf_ugp_albumpermissions['caneditownpiccomment'] . ", 0) |
					IF(forumpermissions & " . $this->registry->bf_ugp_forumpermissions['candeletepost'] . ", " . $this->registry->bf_ugp_albumpermissions['candeleteownpiccomment'] . ", 0) |
					IF(forumpermissions & " . $this->registry->bf_ugp_forumpermissions['followforummoderation'] . ", " . $this->registry->bf_ugp_albumpermissions['commentfollowforummoderation'] . ", 0) |
					IF(forumpermissions & " . $this->registry->bf_ugp_forumpermissions['caneditpost'] . "
						OR forumpermissions & " . $this->registry->bf_ugp_forumpermissions['candeletepost'] . ", " . $this->registry->bf_ugp_albumpermissions['canmanagepiccomment'] . ", 0)
			"
		);
	}

	/**
	* Step #15
	*
	*/
	function step_15()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'moderator', 1, 1),
			"UPDATE " . TABLE_PREFIX . "moderator SET
				permissions2 = permissions2 |
					IF(permissions & " . $this->registry->bf_misc_moderatorpermissions['caneditposts'] . ", " . $this->registry->bf_misc_moderatorpermissions2['caneditpicturecomments'] . ", 0) |
					IF(permissions & " . $this->registry->bf_misc_moderatorpermissions['candeleteposts'] . ", " . $this->registry->bf_misc_moderatorpermissions2['candeletepicturecomments'] . ", 0) |
					IF(permissions & " . $this->registry->bf_misc_moderatorpermissions['canremoveposts'] . ", " . $this->registry->bf_misc_moderatorpermissions2['canremovepicturecomments'] . ", 0) |
					IF(permissions & " . $this->registry->bf_misc_moderatorpermissions['canmoderateposts'] . ", " . $this->registry->bf_misc_moderatorpermissions2['canmoderatepicturecomments'] . ", 0)
			"
		);
	}

	/**
	* Step #16
	*
	*/
	function step_16()
	{
		$this->run_query(
			sprintf($this->phrase['core']['altering_x_table'], 'setting', 1, 1),
			"UPDATE " . TABLE_PREFIX . "setting SET
				value = '1'
			WHERE varname = 'contactustype' AND value = '2'"
		);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35750 $
|| ####################################################################
\*======================================================================*/
