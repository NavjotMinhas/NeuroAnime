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

if (!class_exists('vB_DataManager', false))
{
	exit;
}

/**
* Class to do data save/delete operations for RSS Feeds
*
* @package	vBulletin
* @version	$Revision: 32878 $
* @date		$Date: 2009-10-28 11:38:49 -0700 (Wed, 28 Oct 2009) $
*/
class vB_DataManager_RSSFeed extends vB_DataManager
{
	/**
	* Array of recognised and required fields for RSS feeds, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'rssfeedid'         => array(TYPE_UINT, REQ_INCR, 'return ($data > 0);'),
		'title'             => array(TYPE_STR, REQ_YES),
		'url'               => array(TYPE_STR, REQ_YES),
		'ttl'               => array(TYPE_UINT, REQ_YES, VF_METHOD),
		'maxresults'        => array(TYPE_UINT, REQ_NO),
		'userid'            => array(TYPE_UINT, REQ_YES, VF_METHOD),
		'forumid'           => array(TYPE_UINT, REQ_YES, VF_METHOD),
		'iconid'            => array(TYPE_UINT, REQ_NO),
		'titletemplate'     => array(TYPE_STR, REQ_YES),
		'bodytemplate'      => array(TYPE_STR, REQ_YES),
		'searchwords'       => array(TYPE_STR, REQ_NO),
		'itemtype'          => array(TYPE_STR, REQ_YES, VF_METHOD),
		'threadactiondelay' => array(TYPE_UINT, REQ_NO),
		'endannouncement'   => array(TYPE_UINT, REQ_NO),
		'searchwords'       => array(TYPE_STR, REQ_NO),
		'lastrun'           => array(TYPE_UINT, REQ_AUTO),
		'options'           => array(TYPE_NOCLEAN, REQ_NO),
		'prefixid'          => array(TYPE_NOHTML, REQ_NO)
	);

	/**
	* Array of field names that are bitfields, together with the name of the variable in the registry with the definitions.
	*
	* @var	array
	*/
	var $bitfields = array('options' => 'bf_misc_feedoptions');

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'rssfeed';

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('rssfeedid = %1$d', 'rssfeedid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_RSSFeed(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('rssfeeddata_start')) ? eval($hook) : false;
	}

	/**
	* Verifies that the specified forumid is valid
	*
	* @param	integer	Forum ID (allow -1 = all forums)
	*
	* @return	boolean
	*/
	function verify_forumid(&$forumid)
	{
		if ($forumid != -1 AND empty($this->registry->forumcache["$forumid"]))
		{
			$this->error('invalid_forum_specified');
			return false;
		}
		else if (!($this->registry->forumcache["$forumid"]['options'] & $this->registry->bf_misc_forumoptions['cancontainthreads']))
		{
			$this->error('forum_is_a_category_allow_posting');
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	* Accepts a username and converts it into the appropriate user id
	*
	* @param	string	Username
	*
	* @return	boolean
	*/
	function set_user_by_name($username)
	{
		if ($username != '' AND $user = $this->dbobject->query_first("SELECT userid, username FROM " . TABLE_PREFIX . "user WHERE username = '" . $this->dbobject->escape_string($username) . "'"))
		{
			$this->do_set('userid', $user['userid']);
			return true;
		}
		else
		{
			$this->error('invalid_user_specified');
			return false;
		}
	}

	/**
	* Verifies that a user id is valid and exists
	*
	* @param	integer	User ID
	*
	* @return	boolean
	*/
	function verify_userid(&$userid)
	{
		if ($userid AND $user = $this->dbobject->query_first("SELECT userid, username FROM " . TABLE_PREFIX . "user WHERE userid = " . intval($userid)))
		{
			return true;
		}
		else
		{
			$this->error('invalid_user_specified');
			return false;
		}
	}

	/**
	* Ensures that the given TTL (time to live) value is sane
	*
	* @param	integer	TTL in seconds
	*
	* @return	boolean
	*/
	function verify_ttl(&$ttl)
	{
		switch ($ttl)
		{
			case 43200: // every 12 hours
			case 36000: // every 10 hours
			case 28800: // every 8 hours
			case 21600: // every 6 hours
			case 14400: // every 4 hours
			case 7200: // every 2 hours
			case 3600: // every hour
			case 1800: // every half-hour
			case 1200: // every 20 minutes
			case 600: // every 10 minutes
				return true;

			default:
				$ttl = 1800;
				return true;
		}
	}

	/**
	* Ensures that the given itemtype is acceptable
	*
	* @param	string	Item Type
	*
	* @return	boolean
	*/
	function verify_itemtype(&$itemtype)
	{
		switch ($itemtype)
		{
			case 'thread':
			case 'announcement':
				return true;

			default:
				$itemtype = 'thread';
				return true;
		}
	}

	/**
	* Any checks to run immediately before saving. If returning false, the save will not take place.
	*
	* @param	boolean	Do the query?
	*
	* @return	boolean	True on success; false if an error occurred
	*/
	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('rssfeeddata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	* In batch updates, is executed for each record updated.
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each($doquery = true)
	{
		($hook = vBulletinHook::fetch_hook('rssfeeddata_postsave')) ? eval($hook) : false;

		return true;
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		$this->db_delete(TABLE_PREFIX, 'rsslog', 'rssfeedid = ' . $this->existing['rssfeedid']);
		($hook = vBulletinHook::fetch_hook('rssfeeddata_delete')) ? eval($hook) : false;
		return true;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>