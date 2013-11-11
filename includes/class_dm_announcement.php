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
* Class to do data save/delete operations for ANNOUNCEMENTS
*
* @package	vBulletin
* @version	$Revision: 34547 $
* @date		$Date: 2009-12-16 13:17:19 -0800 (Wed, 16 Dec 2009) $
*/
class vB_DataManager_Announcement extends vB_DataManager
{
	/**
	* Array of recognised and required fields for announcements, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'announcementid'      => array(TYPE_UINT,     REQ_INCR, 'return ($data > 0);'),
		'forumid'             => array(TYPE_INT,      REQ_YES, VF_METHOD),
		'userid'              => array(TYPE_UINT,     REQ_YES, VF_METHOD),
		'title'               => array(TYPE_STR,      REQ_YES, 'return ($data != \'\');'),
		'pagetext'            => array(TYPE_STR,      REQ_YES, 'return ($data != \'\');'),
		'startdate'           => array(TYPE_UNIXTIME, REQ_YES),
		'enddate'             => array(TYPE_UNIXTIME, REQ_YES),
		'views'               => array(TYPE_UINT,     REQ_NO),
		'allowhtml'           => array(TYPE_BOOL,     REQ_NO),
		'allowbbcode'         => array(TYPE_BOOL,     REQ_NO),
		'allowsmilies'        => array(TYPE_BOOL,     REQ_NO),
		'announcementoptions' => array(TYPE_UINT,     REQ_NO)
	);

	/**
	* Array of field names that are bitfields, together with the name of the variable in the registry with the definitions.
	*
	* @var	array
	*/
	var $bitfields = array('announcementoptions' => 'bf_misc_announcementoptions');

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'announcement';

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('announcementid = %1$d', 'announcementid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Announcement(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('announcementdata_start')) ? eval($hook) : false;
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
		if ($forumid == -1 OR isset($this->registry->forumcache["$forumid"]))
		{
			return true;
		}
		else
		{
			$this->error('invalid_forum_specified');
			return false;
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

		if ($this->fetch_field('startdate') >= $this->fetch_field('enddate'))
		{
			$this->error('begin_date_after_end_date');
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('announcementdata_presave')) ? eval($hook) : false;

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
		($hook = vBulletinHook::fetch_hook('announcementdata_postsave')) ? eval($hook) : false;

		return true;
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		$announcementid = intval($this->existing['announcementid']);
		$db =& $this->registry->db;

		$db->query_write("DELETE FROM " . TABLE_PREFIX . "announcementread WHERE announcementid = $announcementid");

		($hook = vBulletinHook::fetch_hook('announcementdata_delete')) ? eval($hook) : false;
		return true;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 34547 $
|| ####################################################################
\*======================================================================*/
?>