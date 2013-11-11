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
* Class to do data save/delete operations for deleted threads/posts
*
* @package	vBulletin
* @version	$Revision: 32878 $
* @date		$Date: 2009-10-28 11:38:49 -0700 (Wed, 28 Oct 2009) $
*/

class vB_DataManager_DeletionLog extends vB_DataManager
{
	/**
	* Array of recognised and required fields for deletionlog, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'primaryid' => array(TYPE_UINT,       REQ_YES, VF_METHOD, 'verify_nonzero'),
		'type'      => array(TYPE_STR,        REQ_NO,  VF_METHOD),
		'userid'    => array(TYPE_UINT,       REQ_NO),
		'username'  => array(TYPE_NOHTMLCOND, REQ_NO),
		'reason'    => array(TYPE_NOHTMLCOND, REQ_NO),
		'dateline'  => array(TYPE_UNIXTIME,   REQ_AUTO),
	);

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = '';

	/**
	* Valid types for 'type'. If type is unset, the first element of this array will be used
	* @var	array
	*
	*/
	var $types = array();

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('primaryid = %1$d AND type = \'%2$s\'', 'primaryid', 'type');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Deletionlog(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		if (!is_subclass_of($this, 'vB_DataManager_Deletionlog'))
		{
			trigger_error('Direct Instantiation of vB_DataManager_Deletionlog prohibited.', E_USER_ERROR);
			return NULL;
		}

		parent::vB_DataManager($registry, $errtype);
	}
	/**
	* Saves the data from the object into the specified database tables
	*
	* @param	boolean	Do the query?
	* @param	mixed		Whether to run the query now; see db_update() for more info
	* @param bool 		Whether to return the number of affected rows.
	*
	* @return	mixed	If this was an INSERT query, the INSERT ID is returned
	*/

	function save($doquery = true, $delayed = false, $affected_rows = false)
	{
		// Specify a REPLACE INTO insert with the 'true' parameter at the end
		return parent::save($doquery, $delayed, $affected_rows, true);
	}

	/**
	* Verify the type field
	*
	* @param		string
	*/
	function verify_type(&$type)
	{
		if (!in_array($type, $this->types))
		{
			$type = $type[0];
		}

		return true;
	}

	/**
	* Fix up the reason field
	*
	* @param		string
	*/
	function verify_reason(&$reason)
	{
		$reason = fetch_censored_text($reason);
		return true;
	}
}

class vB_DataManager_Deletionlog_ThreadPost extends vB_DataManager_Deletionlog
{
	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'deletionlog';

	/**
	* Valid types for 'type'. If type is unset, the first element of this array will be used
	* @var	array
	*
	*/
	var $types = array('post', 'thread');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Deletionlog(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager_Deletionlog($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('deletionlogdata_start')) ? eval($hook) : false;
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

		if (!$this->fetch_field('dateline') AND !$this->condition)
		{
			$this->set('dateline', TIMENOW);
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('deletionlogdata_presave')) ? eval($hook) : false;

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
		($hook = vBulletinHook::fetch_hook('deletionlogdata_postsave')) ? eval($hook) : false;
		return true;
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		($hook = vBulletinHook::fetch_hook('deletionlogdata_delete')) ? eval($hook) : false;
		return true;
	}
}

class vB_DataManager_Deletionlog_VisitorMessage extends vB_DataManager_Deletionlog_ThreadPost
{
	/**
	* Valid types for 'type'. If type is unset, the first element of this array will be used
	* @var	array
	*
	*/
	var $types = array('visitormessage');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Deletionlog(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager_Deletionlog_ThreadPost($registry, $errtype);

	}
}

class vB_DataManager_Deletionlog_GroupMessage extends vB_DataManager_Deletionlog_ThreadPost
{
	/**
	* Valid types for 'type'. If type is unset, the first element of this array will be used
	* @var	array
	*
	*/
	var $types = array('groupmessage');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Deletionlog(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager_Deletionlog_ThreadPost($registry, $errtype);

	}
}

class vB_DataManager_Deletionlog_PictureComment extends vB_DataManager_Deletionlog_ThreadPost
{
	/**
	* Valid types for 'type'. If type is unset, the first element of this array will be used
	* @var	array
	*
	*/
	var $types = array('picturecomment');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Deletionlog_PictureComment(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager_Deletionlog_ThreadPost($registry, $errtype);

	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>