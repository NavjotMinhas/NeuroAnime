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
* Class to do data save/delete operations for thread prefix sets
*
* @package	vBulletin
* @version	$Revision: 32878 $
* @date		$Date: 2009-10-28 11:38:49 -0700 (Wed, 28 Oct 2009) $
*/
class vB_DataManager_PrefixSet extends vB_DataManager
{
	/**
	* Array of recognised and required fields for prefix sets, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'prefixsetid'	 => array(TYPE_STR,  REQ_YES, VF_METHOD),
		'displayorder' => array(TYPE_UINT, REQ_YES),
	);

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('prefixsetid = \'%1$s\'', 'prefixsetid');

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'prefixset';

	/**
	* Array to store stuff to save to prefixset table
	*
	* @var	array
	*/
	var $prefixset = array();

	/**
	* Array to store information
	*
	* @var	array
	*/
	var $info = array(
		'title' => null,
	);

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_PrefixSet(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('prefixsetdata_start')) ? eval($hook) : false;
	}

	/**
	* Verify that the prefix set is specified and meets the correct format.
	*
	* @param	string	Prefix set ID
	*
	* @return	boolean
	*/
	function verify_prefixsetid(&$prefixsetid)
	{
		if ($prefixsetid === '')
		{
			$this->error('please_complete_required_fields');
			return false;
		}

		if (!preg_match('#^[a-z0-9_]+$#i', $prefixsetid) OR $prefixsetid === '0')
		{
			$this->error('invalid_string_id_alphanumeric');
			return false;
		}

		if ($this->registry->db->query_first("SELECT prefixsetid FROM " . TABLE_PREFIX . "prefixset WHERE prefixsetid = '" . $this->registry->db->escape_string($prefixsetid) . "'"))
		{
			$this->error('there_is_already_prefix_set_named_x', $prefixsetid);
			return false;
		}

		return true;
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

		// if (new insert or a new title specified) and the title is empty -> error
		if ((!$this->condition OR $this->info['title'] !== null) AND strval($this->info['title']) === '')
		{
			$this->error('please_complete_required_fields');
			$this->presave_called = false;
			return false;
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('prefixsetdata_presave')) ? eval($hook) : false;

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
		// update phrase
		$db =& $this->registry->db;
		$vbulletin =& $this->registry;

		if (strval($this->info['title']) !== '')
		{
			$db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "phrase
					(languageid, fieldname, varname, text, product, username, dateline, version)
				VALUES
					(
						0,
						'prefix',
						'" . $db->escape_string('prefixset_' . $this->fetch_field('prefixsetid') . '_title') . "',
						'" . $db->escape_string($this->info['title']) . "',
						'vbulletin',
						'" . $db->escape_string($vbulletin->userinfo['username']) . "',
						" . TIMENOW . ",
						'" . $db->escape_string($vbulletin->templateversion) . "'
					)
			");
		}

		require_once(DIR . '/includes/adminfunctions_language.php');
		build_language();

		require_once(DIR . '/includes/adminfunctions_prefix.php');
		build_prefix_datastore();

		($hook = vBulletinHook::fetch_hook('prefixsetdata_postsave')) ? eval($hook) : false;
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		$db =& $this->registry->db;

		// need to rebuild last post info in forums that use this prefix
		require_once(DIR . '/includes/functions_databuild.php');

		$forums = $db->query_read("
			SELECT forumid
			FROM " . TABLE_PREFIX . "forumprefixset
			WHERE prefixsetid = '" . $db->escape_string($this->fetch_field('prefixsetid')) . "'
		");
		while ($forum = $db->fetch_array($forums))
		{
			build_forum_counters($forum['forumid']);
		}

		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "forumprefixset
			WHERE prefixsetid = '" . $db->escape_string($this->fetch_field('prefixsetid')) . "'
		");

		// delete this set's phrases
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "phrase
			WHERE varname = '" . $db->escape_string('prefixset_' .  $this->fetch_field('prefixsetid') . '_title') . "'
				AND fieldname = 'prefix'
		");

		// now find all the phrases for child prefixes to remove
		$prefix_phrases = array();
		$prefixids = array();

		$prefix_sql = $db->query_read("
			SELECT prefixid
			FROM " . TABLE_PREFIX . "prefix
			WHERE prefixsetid = '" . $db->escape_string($this->fetch_field('prefixsetid')) . "'
		");
		while ($prefix = $db->fetch_array($prefix_sql))
		{
			$prefix_phrases[] = "'" . $db->escape_string("prefix_$prefix[prefixid]_title_plain") . "'";
			$prefix_phrases[] = "'" . $db->escape_string("prefix_$prefix[prefixid]_title_rich") . "'";

			$prefixids[] = "'" . $db->escape_string($prefix['prefixid']) . "'";
		}

		if ($prefix_phrases)
		{
			$db->query_write("
				DELETE FROM " . TABLE_PREFIX . "phrase
				WHERE varname IN (" . implode(',', $prefix_phrases) . ")
					AND fieldname = 'global'
			");

			$db->query_write("
				UPDATE " . TABLE_PREFIX . "thread SET
					prefixid = ''
				WHERE prefixid IN (" . implode(',', $prefixids) . ")
			");
		}

		// now delete the child prefixes themselves
		$db->query_write("
			DELETE FROM " . TABLE_PREFIX . "prefix
			WHERE prefixsetid = '" . $db->escape_string($this->fetch_field('prefixsetid')) . "'
		");

		require_once(DIR . '/includes/adminfunctions_language.php');
		build_language();

		require_once(DIR . '/includes/adminfunctions_prefix.php');
		build_prefix_datastore();

		($hook = vBulletinHook::fetch_hook('prefixsetdata_delete')) ? eval($hook) : false;
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