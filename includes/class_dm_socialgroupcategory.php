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
* Class to do data save/delete operations for Social Group Categories
*
* @package	vBulletin
* @version	$Revision: 26097 $
* @date		$Date: 2008-03-14 11:35:29 +0000 (Fri, 14 Mar 2008) $
*/
class vB_DataManager_SocialGroupCategory extends vB_DataManager
{
	/**
	* Array of recognised and required fields for social group categories, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'socialgroupcategoryid' => array(TYPE_UINT,       REQ_INCR, VF_METHOD, 'verify_nonzero'),
		'creatoruserid'         => array(TYPE_UINT,       REQ_NO,   VF_METHOD, 'verify_nonzero'),
		'title'                 => array(TYPE_STR, REQ_YES),
		'description'           => array(TYPE_STR, REQ_NO),
		'lastupdate'            => array(TYPE_UNIXTIME,   REQ_AUTO)
	);

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'socialgroupcategory';

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('socialgroupcategoryid = %1$d', 'socialgroupcategoryid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_SocialGroupCategory(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

 		($hook = vBulletinHook::fetch_hook('socgroupcatdata_start')) ? eval($hook) : false;
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

		$this->set('lastupdate', TIMENOW);

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('socgroupcatdata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each($doquery = true)
	{
		($hook = vBulletinHook::fetch_hook('socgroupcatdata_postsave')) ? eval($hook) : false;

		fetch_socialgroup_category_cloud(true);

		return true;
	}

	/**
	* Any code to run after deleting
	*
	* @param	Boolean Do the query?
	*/
	function post_delete($doquery = true)
	{
		($hook = vBulletinHook::fetch_hook('socgroupcatdata_delete')) ? eval($hook) : false;

		return true;
	}
}