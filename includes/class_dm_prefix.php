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
* Class to do data save/delete operations for thread prefixes
*
* @package	vBulletin
* @version	$Revision: 39292 $
* @date		$Date: 2010-09-28 15:58:46 -0700 (Tue, 28 Sep 2010) $
*/
class vB_DataManager_Prefix extends vB_DataManager
{
	/**
	* Array of recognised and required fields for prefixes, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'prefixid'		=> array(TYPE_STR,  REQ_YES, VF_METHOD),
		'prefixsetid'	=> array(TYPE_STR,  REQ_YES),
		'displayorder'	=> array(TYPE_UINT, REQ_YES),
		'options'	=> array(TYPE_UINT, REQ_NO),
	);

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('prefixid = \'%1$s\'', 'prefixid');

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'prefix';

	/**
	* Array to store stuff to save to prefixes table
	*
	* @var	array
	*/
	var $prefix = array();

	/**
	* Array to store information
	*
	* @var	array
	*/
	var $info = array(
		'title_plain' => null,
		'title_rich' => null
	);

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Prefix(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('prefixdata_start')) ? eval($hook) : false;
	}

	/**
	* Verify that the prefix is specified and meets the correct format.
	*
	* @param	string	Prefix ID
	*
	* @return	boolean
	*/
	function verify_prefixid(&$prefixid)
	{
		if ($prefixid === '')
		{
			$this->error('please_complete_required_fields');
			return false;
		}

		if (!preg_match('#^[a-z0-9_]+$#i', $prefixid) OR $prefixid === '0')
		{
			$this->error('invalid_string_id_alphanumeric');
			return false;
		}

		if ($this->registry->db->query_first("SELECT prefixid FROM " . TABLE_PREFIX . "prefix WHERE prefixid = '" . $this->registry->db->escape_string($prefixid) . "'"))
		{
			$this->error('there_is_already_prefix_named_x', $prefixid);
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

		// if (new insert or a new plain title specified) and the title is empty -> error
		if ((!$this->condition OR $this->info['title_plain'] !== null) AND strval($this->info['title_plain']) === '')
		{
			$this->error('please_complete_required_fields');
			$this->presave_called = false;
			return false;
		}

		// if (new insert or a new rich title specified) and the title is empty -> error
		if ((!$this->condition OR $this->info['title_rich'] !== null) AND strval($this->info['title_rich']) === '')
		{
			$this->error('please_complete_required_fields');
			$this->presave_called = false;
			return false;
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('prefixdata_presave')) ? eval($hook) : false;

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

		if (strval($this->info['title_plain']) !== '')
		{
			$db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "phrase
					(languageid, fieldname, varname, text, product, username, dateline, version)
				VALUES
					(
						0,
						'global',
						'" . $db->escape_string('prefix_' . $this->fetch_field('prefixid') . '_title_plain') . "',
						'" . $db->escape_string($this->info['title_plain']) . "',
						'vbulletin',
						'" . $db->escape_string($vbulletin->userinfo['username']) . "',
						" . TIMENOW . ",
						'" . $db->escape_string($vbulletin->templateversion) . "'
					)
			");
		}

		if (strval($this->info['title_rich']) !== '')
		{
			$db->query_write("
				REPLACE INTO " . TABLE_PREFIX . "phrase
					(languageid, fieldname, varname, text, product, username, dateline, version)
				VALUES
					(
						0,
						'global',
						'" . $db->escape_string('prefix_' .  $this->fetch_field('prefixid') . '_title_rich') . "',
						'" . $db->escape_string($this->info['title_rich']) . "',
						'vbulletin',
						'" . $db->escape_string($vbulletin->userinfo['username']) . "',
						" . TIMENOW . ",
						'" . $db->escape_string($vbulletin->templateversion) . "'
					)
			");
		}

		if (!empty($this->existing['prefixsetid']) AND $this->existing['prefixsetid'] != $this->fetch_field('prefixsetid'))
		{
			// updating the prefix set. We need to determine where we used
			// to be able to use this set but can't any more.
			$old_set = $this->existing['prefixsetid'];
			$new_set = $this->fetch_field('prefixsetid');
			$allowed_forums = array(
				$old_set => array(),
				$new_set => array()
			);

			// find all forums where the new and old sets are usable
			$allowed_forums_sql = $db->query_read("
				SELECT prefixsetid, forumid
				FROM " . TABLE_PREFIX . "forumprefixset
				WHERE prefixsetid IN (
					'" . $db->escape_string($old_set) . "',
					'" . $db->escape_string($new_set) . "'
				)
			");
			while ($allowed_forum = $db->fetch_array($allowed_forums_sql))
			{
				$allowed_forums["$allowed_forum[prefixsetid]"][] = $allowed_forum['forumid'];
			}

			// remove this prefix from any threads in forums that were removed
			$removed_forums = array_diff($allowed_forums["$old_set"], $allowed_forums["$new_set"]);
			if ($removed_forums)
			{
				require_once(DIR . '/includes/adminfunctions_prefix.php');
				remove_prefixes_forum($this->fetch_field('prefixid'), $removed_forums);
			}
		}

		require_once(DIR . '/includes/adminfunctions_language.php');
		build_language();

		require_once(DIR . '/includes/adminfunctions_prefix.php');
		build_prefix_datastore();

		($hook = vBulletinHook::fetch_hook('prefixdata_postsave')) ? eval($hook) : false;
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		$db =& $this->registry->db;

		$db->query_write("
			UPDATE " . TABLE_PREFIX . "thread SET
				prefixid = ''
			WHERE prefixid = '" . $db->escape_string($this->fetch_field('prefixid')) . "'
		");

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
			DELETE FROM " . TABLE_PREFIX . "phrase
			WHERE varname IN (
					'" . $db->escape_string('prefix_' .  $this->fetch_field('prefixid') . '_title_plain') . "',
					'" . $db->escape_string('prefix_' .  $this->fetch_field('prefixid') . '_title_rich') . "'
				)
				AND fieldname = 'global'
		");

		require_once(DIR . '/includes/adminfunctions_language.php');
		build_language();

		require_once(DIR . '/includes/adminfunctions_prefix.php');
		build_prefix_datastore();

		($hook = vBulletinHook::fetch_hook('prefixdata_delete')) ? eval($hook) : false;
		return true;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 39292 $
|| ####################################################################
\*======================================================================*/
?>