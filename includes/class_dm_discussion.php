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
 * DataManager for manipulating discussions.
 * 
 * @package	vBulletin
 * @version	$Revision: 26097 $
 * @date	$Date: 03-14 11:35:29 +0000 (Fri, 14 Mar 2008) $
 */
class vB_DataManager_Discussion extends vB_DataManager
{
	/**
	* Array of recognised and required fields for users, and their types
	*
	* @access protected
	* @var	array
	*/
	var $validfields = array(
		'discussionid'     => array(TYPE_UINT,       REQ_INCR, VF_METHOD, 'verify_nonzero'),
		'groupid'          => array(TYPE_NOHTMLCOND, REQ_YES,  VF_METHOD, 'verify_nonzero'),
		'firstpostid'      => array(TYPE_UINT,       REQ_NO),
		'lastpostid'       => array(TYPE_UINT,       REQ_NO,   VF_METHOD, 'verify_nonzero'),
		'lastpost'         => array(TYPE_UNIXTIME,   REQ_AUTO),
		'lastposter'       => array(TYPE_NOHTMLCOND, REQ_NO),
		'lastposterid'     => array(TYPE_UINT,       REQ_NO),
		'visible'          => array(TYPE_UINT,       REQ_NO),
		'deleted'          => array(TYPE_UINT,       REQ_NO),
		'moderation'       => array(TYPE_UINT,       REQ_NO)
	);	
	
	/**
	* Condition for update query
	*
	* @access protected
	* @var	array
	*/
	var $condition_construct = array('discussionid = %1$s', 'discussionid');
	
	/**
	* The main table this class deals with
	*
	* @access protected
	* @var	string
	*/
	var $table = 'discussion';
	
	// #######################################################################
	
	/**
	* Constructor - checks that the registry object has been passed correctly.
	* 
	* @access public
	* 
	* @param vB_Registry $registry					Instance of the vBulletin data registry object - expected to have 
	* 												the database object as one of its $this->db member.
	* @param integer $errtype						One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Discussion(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('discussiondata_start')) ? eval($hook) : false;
	}
	
	
	/**
	* Any checks to run immediately before saving. If returning false, the save will not take place.
	*
	* @access protected
	* 
	* @param boolean $doquery					Do the query?
	* @return boolean							Success
	*/
	function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('discussiondata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	
	/**
	* Runs any extra actions after saving all.
	* 
	* @access protected
	* 
	* @param boolean $doquery					Do the query?
	*/
	function post_save_once($doquery = true)
	{
		$discussionid = intval($this->fetch_field('discussionid'));
		$groupid = intval($this->fetch_field('groupid'));

		$new_discussion = (!$this->condition AND $this->fetch_field('lastpostid') == $this->fetch_field('firstpostid'));
		
		// If new discussion and info['updatefirstpost'] then update the firstpost to this discussion
		if ($new_discussion)
		{
			// set discussionid on new (now saved and existing) message
			$this->registry->db->query_write("
				UPDATE " . TABLE_PREFIX . "groupmessage
				SET discussionid = " . $discussionid . "
				WHERE gmid = " . intval($this->fetch_field('firstpostid')) . " 
				AND discussionid = 0" 
			);
		}

		$update_counters = (isset($this->rawfields['visible']) OR isset($this->rawfields['moderation']) OR isset($this->rawfields['deleted']));
		
		// If last post has changed, update parent group
		if ($update_counters OR (isset($this->setfields['lastpost']) AND isset($this->info['group']['groupid'])))
		{
			$dataman =& datamanager_init('SocialGroup', $this->registry, ERRTYPE_SILENT);
			$dataman->set_existing($this->info['group']);

			if (isset($this->setfields['lastpost']) AND ($this->fetch_field('lastpost') == TIMENOW))
			{
				$dataman->set('lastpost', TIMENOW);
				$dataman->set('lastgmid', $this->fetch_field('lastpostid'));
				$dataman->set('lastposter', $this->fetch_field('lastposter'));
				$dataman->set('lastposterid', $this->fetch_field('lastposterid'));
				$dataman->set('lastdiscussionid', $this->fetch_field('discussionid'));
				$dataman->set('lastupdate', TIMENOW);
				
				if (isset($this->info['lastposttitle']))
				{
					$dataman->set('lastdiscussion', $this->info['lastposttitle']);
				}
			}

			if ($this->condition)
			{
				// check if moderated, deleted or visible was delta modified (visible = visible + 1) and repro on the group
				foreach (array('visible', 'moderation', 'deleted') AS $counter)
				{
					if (isset($this->rawfields[$counter]) AND isset($this->setfields[$counter]) AND isset($this->{$this->table}[$counter]))
					{
						$dataman->set($counter, $this->{$this->table}[$counter], false);
					}
				}
			}
			else if ($new_discussion)
			{
				if($this->setfields['visible'] == 1)
				{
					$dataman->set('discussions', 'discussions + 1', false);
					$dataman->set('visible', 'visible + 1', false);
					$dataman->set('lastupdate', TIMENOW);
				}
				else 
				{
					$dataman->set('moderation', 'moderation + 1', false);
				}
			}

			$dataman->save();
			unset($dataman);
		}

		// Update pending count for group owner
		if ($this->info['group'])
		{
			$this->update_owner_pending_gm_count($this->info['group']['creatoruserid']);
		}

		($hook = vBulletinHook::fetch_hook('groupmessagedata_postsave')) ? eval($hook) : false;
	}
	
	
	/**
	* Updates the counter for the owner of the group that shows how many pending messages
	* they have awaiting them to deal with
	*
	* @param integer $ownerid					The userid of the owner of the group
	*/
	function update_owner_pending_gm_count($ownerid)
	{
		list($pendingcountforowner) = $this->registry->db->query_first("
			SELECT SUM(moderation) FROM " . TABLE_PREFIX . "socialgroup
			WHERE creatoruserid = " . $ownerid
		, DBARRAY_NUM);

		$this->registry->db->query_write("
			UPDATE " . TABLE_PREFIX . "user
			SET gmmoderatedcount = " . intval($pendingcountforowner) . "
			WHERE userid = " . $ownerid
		);
	}
	
	
	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	*
	* @access protected
	* 
	* @param boolean $doquery					Do the query?
	*/
	function post_save_each($doquery = true)
	{
		($hook = vBulletinHook::fetch_hook('discussiondata_postsave')) ? eval($hook) : false;
	}
	
	
	/**
	* Any code to run after deleting
	*
	* @access protected
	* 
	* @param boolean $doquery					Do the query?
	*/
	function post_delete($doquery = true)
	{
		if (empty($this->info['group']))
		{
			$this->info['group'] = fetch_socialgroupinfo($this->fetch_field('groupid'));
		}
		
		if ($this->info['hard_delete'])
		{
			// prepare table names for MySQL 4.0.x
			$discussionid = intval($this->existing['discussionid']);
			$groupmessage_table = TABLE_PREFIX . 'groupmessage';
			$deletionlog_table = TABLE_PREFIX . 'deletionlog';
			$moderation_table = TABLE_PREFIX . 'moderation';

			/*
			 
			KEVIN :  Need to add SocialGroup ? So this can all be done in one ?
			 
			// Search index maintenance - Remove for a hard delete.
			require_once(DIR . '/vb/search/core.php');
			$indexer = vB_Search_Core::get_instance()->get_index_controller('vBForum', 'SocialGroup');			
			$indexer->delete($discussionid);		
			*/
			
			require_once(DIR . '/vb/search/core.php');
			$indexer = vB_Search_Core::get_instance()->get_index_controller('vBForum', 'SocialGroupMessage');
			
			$result = $this->registry->db->query("SELECT gmid FROM " . TABLE_PREFIX . "groupmessage WHERE discussionid={$discussionid}");
			 
			while ($row = $this->registry->db->fetch_row($result))
			{
				$indexer->delete($row[0]);
			}
			
			// delete all messages and their deletion logs and moderation
			$this->registry->db->query_write(
				"DELETE $groupmessage_table, $deletionlog_table, $moderation_table 
				 FROM $groupmessage_table   
				 LEFT JOIN $deletionlog_table  
				  ON $deletionlog_table.primaryid = $groupmessage_table.gmid 
				  AND $deletionlog_table.type = 'groupmessage' 
				 LEFT JOIN $moderation_table 
				  ON $moderation_table.primaryid = $groupmessage_table.gmid 
				  AND $moderation_table.type = 'groupmessage' 
				 WHERE $groupmessage_table.discussionid = $discussionid"
			);
			
			if (!$this->info['skip_build_counters'])
			{
				require_once(DIR . '/includes/functions_socialgroup.php');
				build_group_counters($this->existing['groupid']);
			}

			// delete read marking
			$this->registry->db->query_write("
				DELETE FROM " . TABLE_PREFIX . "discussionread 
				WHERE " . TABLE_PREFIX . "discussionread.discussionid = $discussionid
			");
			
			// delete subscriptions
			$this->registry->db->query_write("
				DELETE FROM " . TABLE_PREFIX . "subscribediscussion 
				WHERE " . TABLE_PREFIX . "subscribediscussion.discussionid = $discussionid
			");
			
			// update moderation count for owner
			update_owner_pending_gm_count($this->info['group']['creatoruserid']);
		
 			($hook = vBulletinHook::fetch_hook('discussiondata_delete')) ? eval($hook) : false;
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 26681 $
|| ####################################################################
\*======================================================================*/
?>