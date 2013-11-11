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
* Class to do data save/delete operations for THREAD RATINGS
*
* Example usage (inserts a new thread rating):
*
* $t =& datamanager_init('ThreadRate', $vbulletin, ERRTYPE_STANDARD);
* $t->set_info('threadid', 12);
* $t->set_info('userid', 4);
* $t->set_info('vote', 3);
* $t->save();
*
* @package	vBulletin
* @version	$Revision: 32878 $
* @date		$Date: 2009-10-28 11:38:49 -0700 (Wed, 28 Oct 2009) $
*/
class vB_DataManager_ThreadRate extends vB_DataManager
{
	/**
	* Array of recognised and required fields for threadrate, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'threadrateid' => array(TYPE_UINT, REQ_INCR, VF_METHOD, 'verify_nonzero'),
		'threadid'     => array(TYPE_UINT, REQ_YES),
		'userid'       => array(TYPE_UINT, REQ_YES,  VF_METHOD, 'verify_userid'),
		'vote'         => array(TYPE_INT,  REQ_YES,  VF_METHOD, 'verify_vote'), # TYPE_INT to allow negative rating
		'ipaddress'    => array(TYPE_STR,  REQ_AUTO, VF_METHOD, 'verify_ipaddress')
	);

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('threadrateid = %1$s', 'threadrateid');

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'threadrate';

	/**
	* The maximum vote
	*
	* @var	int
	*/
	var $max_vote = 5;

	/**
	* Array to store stuff to save to threadrate table
	*
	* @var	array
	*/
	var $threadrate = array();

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_ThreadRate(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('threadratedata_start')) ? eval($hook) : false;
	}

	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		if (!$this->condition AND $this->fetch_field('userid') == $this->registry->userinfo['userid'] AND !$this->fetch_field('ipaddress'))
		{
			$this->set('ipaddress', IPADDRESS);
		}

		if (!$this->condition AND empty($this->info['skip_dupe_check']))
		{
			if ($userid = intval($this->fetch_field('userid')))
			{
				$exists = $this->dbobject->query_first("
					SELECT *
					FROM " . TABLE_PREFIX . "threadrate
					WHERE userid = $userid
						AND threadid = " . intval($this->threadrate['threadid'])
				);
			}
			else if ($ipaddress = $this->fetch_field('ipaddress'))
			{
				$exists = $this->dbobject->query_first("
					SELECT *
					FROM " . TABLE_PREFIX . "threadrate
					WHERE userid = 0
						AND threadid = " . intval($this->threadrate['threadid']) . "
						AND ipaddress = '" . $this->dbobject->escape_string($ipaddress) . "'
				");
			}

			if ($exists)
			{
				$this->set_existing($exists);
			}
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('threadratedata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}


	/**
	* Removing 1 from the vote count for that thread
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		if ($this->info['thread'])
		{
			$threadinfo =& $this->info['thread'];
		}
		else
		{
			$threadinfo = fetch_threadinfo($this->fetch_field('threadid'));
		}

		$threadman =& datamanager_init('Thread', $this->registry, ERRTYPE_SILENT, 'threadpost');
		$threadman->set_existing($threadinfo);
		$threadman->set('votetotal', 'votetotal - ' . intval($this->fetch_field('vote')), false);
		$threadman->set('votenum', 'votenum - 1', false);
		$threadman->save();

		($hook = vBulletinHook::fetch_hook('threadratedata_delete')) ? eval($hook) : false;

		return true;
	}


	/**
	* Updating the votecount for that thread
	*
	* @param	boolean	Do the query?
	*/
	function post_save_each($doquery = true)
	{
		// Are we handeling a multi DM
		if (!$this->condition OR $this->existing['vote'] != $this->fetch_field('vote'))
		{
			if ($this->info['thread'])
			{
				$threadinfo =& $this->info['thread'];
			}
			else
			{
				$threadinfo = fetch_threadinfo($this->fetch_field('threadid'));
			}

			if (!$this->condition)
			{
				// Increment the vote count for the thread that has just been voted on
				$threadman =& datamanager_init('Thread', $this->registry, ERRTYPE_SILENT, 'threadpost');
				$threadman->set_existing($threadinfo);
				$threadman->set('votetotal', "votetotal + " . intval($this->fetch_field('vote')), false);
				$threadman->set('votenum', 'votenum + 1', false);
				$threadman->save();
			}
			else
			{
				// this is an update
				$votediff = $this->fetch_field('vote') - $this->existing['vote'];

				$threadman =& datamanager_init('Thread', $this->registry, ERRTYPE_SILENT, 'threadpost');
				$threadman->set_existing($threadinfo);
				$threadman->set('votetotal', "votetotal + $votediff", false);
				$threadman->save();
			}

			if ($this->fetch_field('userid') == $this->registry->userinfo['userid'])
			{
				set_bbarray_cookie('thread_rate', $this->fetch_field('threadid'), $this->fetch_field('vote'), 1);
			}
		}

		($hook = vBulletinHook::fetch_hook('threadratedata_postsave')) ? eval($hook) : false;
	}


	/**
	* Verifies that the specified user exists
	*
	* @param	integer	User ID
	*
	* @return 	boolean	Returns true if user exists
	*/
	function verify_userid(&$userid)
	{
		if ($userid == 0 OR $userid == $this->registry->userinfo['userid'] OR $this->dbobject->query_first("SELECT * FROM " . TABLE_PREFIX . "user WHERE userid = $userid"))
		{
			return true;
		}
		else
		{
			global $vbphrase;
			$this->error('invalidid', $vbphrase['user'], $this->registry->options['contactuslink']);
			return false;
		}
	}


	/**
	* Checks that the vote is between 0 and 5
	*
	* @param	integer	The vote
	*
	* @return	boolean	Returns true on success
	*/
	function verify_vote(&$vote)
	{
		if (is_int($vote) AND $vote >= 0 AND $vote <= $this->max_vote)
		{
			return true;
		}
		else
		{
			return false;
		}
	}
}

/**
* Class to do data update operations for multiple THREADRATE simultaneously
*
* @package	vBulletin
* @version	$Revision: 32878 $
* @date		$Date: 2009-10-28 11:38:49 -0700 (Wed, 28 Oct 2009) $
*/
class vB_DataManager_ThreadRate_Multiple extends vB_DataManager_Multiple
{
	/**
	* The name of the class to instantiate for each matching. It is assumed to exist!
	* It should be a subclass of vB_DataManager.
	*
	* @var	string
	*/
	var $class_name = 'vB_DataManager_ThreadRate';

	/**
	* The name of the primary ID column that is used to uniquely identify records retrieved.
	* This will be used to build the condition in all update queries!
	*
	* @var string
	*/
	var $primary_id = 'threadrateid';

	/**
	* Builds the SQL to run to fetch records. This must be overridden by a child class!
	*
	* @param	string	Condition to use in the fetch query; the entire WHERE clause
	* @param	integer	The number of records to limit the results to; 0 is unlimited
	* @param	integer	The number of records to skip before retrieving matches.
	*
	* @return	string	The query to execute
	*/
	function fetch_query($condition, $limit = 0, $offset = 0)
	{
		$query = "SELECT * FROM " . TABLE_PREFIX . "threadrate AS threadrate";
		if ($condition)
		{
			$query .= " WHERE $condition";
		}

		$limit = intval($limit);
		$offset = intval($offset);
		if ($limit)
		{
			$query .= " LIMIT $offset, $limit";
		}

		return $query;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>