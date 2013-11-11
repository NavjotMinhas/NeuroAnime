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
* Class to do data save/delete operations for pollvotes
*
* @package	vBulletin
* @version	$Revision: 32878 $
* @date		$Date: 2009-10-28 11:38:49 -0700 (Wed, 28 Oct 2009) $
*/
class vB_DataManager_PollVote extends vB_DataManager
{
	/**
	* Array of recognised and required fields for poll, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'pollvoteid' => array(TYPE_UINT, REQ_INCR, VF_METHOD, 'verify_nonzero'),
		'pollid'     => array(TYPE_UINT, REQ_YES),
		'userid'     => array(TYPE_UINT, REQ_YES),
		'votedate'   => array(TYPE_UINT, REQ_AUTO),
		'voteoption' => array(TYPE_UINT, REQ_NO),
		'votetype'   => array(TYPE_UINT, REQ_NO),
	);

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('pollid = %1$d', 'pollid');

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'pollvote';

	/**
	* Array to store stuff to save to poll table
	*
	* @var	array
	*/
	var $pollvote = array();

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_PollVote(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('pollvotedata_start')) ? eval($hook) : false;
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
		($hook = vBulletinHook::fetch_hook('pollvotedata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	function post_save_each($doquery = true)
	{
		if (!$this->condition)
		{
			$pollinfo = $this->dbobject->query_first("
				SELECT *
				FROM " . TABLE_PREFIX . "poll
				WHERE pollid = " . $this->fetch_field('pollid')
			);

			$old_votes_array = explode('|||', $pollinfo['votes']);

			$old_votes_array[$this->fetch_field('voteoption') - 1]++;

			$new_votes_array = implode('|||', $old_votes_array);

			if ($this->fetch_field('votedate') > $pollinfo['lastvote'])
			{
				$lastvote_sql = ", lastvote = " . intval($this->fetch_field('votedate'));
			}
			else
			{
				$lastvote_sql = '';
			}

			if (!$this->info['skip_voters'])
			{
				$voters = ', voters = voters + 1';
			}
			else
			{
				$voters = '';
			}

			$this->dbobject->query_write("
				UPDATE " . TABLE_PREFIX . "poll
				SET
					votes = '" . $new_votes_array . "'
					$voters
					$lastvote_sql
				WHERE pollid = " . $this->fetch_field('pollid')
			);
		}

		($hook = vBulletinHook::fetch_hook('pollvotedata_postsave')) ? eval($hook) : false;
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		($hook = vBulletinHook::fetch_hook('pollvotedata_delete')) ? eval($hook) : false;
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