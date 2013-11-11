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
* Class to do data save/delete operations for Social Groups
*
* @package	vBulletin
* @version	$Revision: 32878 $
* @date		$Date: 2009-10-28 11:38:49 -0700 (Wed, 28 Oct 2009) $
*/
class vB_DataManager_SocialGroupMember extends vB_DataManager
{
	/**
	* Array of recognised and required fields for users, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'userid'		=> array(TYPE_UINT,       REQ_YES,  VF_METHOD, 'verify_nonzero'),
		'groupid'		=> array(TYPE_UINT,       REQ_YES,  VF_METHOD, 'verify_nonzero'),
		'dateline'	    => array(TYPE_UNIXTIME,   REQ_AUTO),
		'type'		    => array(TYPE_STR,        REQ_NO,   VF_METHOD),
	);

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'socialgroupmember';

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('userid = %1$d AND groupid = %2$d', 'userid', 'groupid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_SocialGroupMember(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('socgroupmemberdata_start')) ? eval($hook) : false;
	}

	/**
	* Verifies whether the type of membership passed in is valid
	*
	* @param	string
	*
	* @return	boolean
	*/
	function verify_type(&$type)
	{
		return in_array($type, array(
			'member',
			'moderated',
			'invited'
		));
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

		// It's an insert but the user is already a member, turn it into an update
		if ($this->condition === null AND $this->registry->db->query_first("SELECT * FROM " . TABLE_PREFIX . "socialgroupmember WHERE userid = " . $this->fetch_field('userid') . " AND groupid = " . $this->fetch_field('groupid')))
		{
			$this->set_condition("userid = " . $this->fetch_field('userid') . " AND groupid = " . $this->fetch_field('groupid'));
		}

		if ($this->condition AND (($this->existing['type'] == 'invited' AND $this->fetch_field('type') == 'moderated') OR ($this->existing['type'] == 'moderated' AND $this->fetch_field('type') == 'invited')))
		{
				$this->set('type', 'member');
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('socgroupmemberdata_presave')) ? eval($hook) : false;

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

		if ((($this->condition AND $this->existing['type'] != $this->fetch_field('type')) OR !$this->condition) AND ($this->fetch_field('type') == 'member' OR $this->fetch_field('type') == 'moderated'))
		{
			$group = $this->registry->db->query_first("SELECT * FROM " . TABLE_PREFIX . "socialgroup WHERE groupid = " . $this->fetch_field('groupid'));

			if ($group)
			{
				$groupdm =& datamanager_init("SocialGroup", $this->registry, ERRTYPE_STANDARD);
				$groupdm->set_existing($group);

				$groupdm->rebuild_membercounts();
				$groupdm->rebuild_picturecount();
				$groupdm->save();

				$this->update_owner_pending_member_count($group['creatoruserid']);
			}
		}

		if (($this->condition AND $this->existing['type'] = 'invited') OR (!$this->condition AND $this->fetch_field('type') == 'invited'))
		{
			$this->update_user_invite_count();
		}

		($hook = vBulletinHook::fetch_hook('socgroupmemberdata_postsave')) ? eval($hook) : false;
	}

	/**
	* Additional data to update after a delete call (such as denormalized values in other tables).
	*
	* @param	boolean	Do the query?
	*/
	function post_delete($doquery = true)
	{
		if ($this->fetch_field('type') == 'member' OR $this->fetch_field('type') == 'moderated')
		{
			$group = $this->registry->db->query_first("SELECT * FROM " . TABLE_PREFIX . "socialgroup WHERE groupid = " . $this->fetch_field('groupid'));

			if ($group)
			{
				$groupdm =& datamanager_init("SocialGroup", $this->registry, ERRTYPE_STANDARD);
				$groupdm->set_existing($group);

				$groupdm->rebuild_membercounts();
				$groupdm->rebuild_picturecount();
				$groupdm->save();

				$this->update_owner_pending_member_count($group['creatoruserid']);
			}
		}

		if ($this->fetch_field('type') == 'invited')
		{
			$this->update_user_invite_count();
		}

		($hook = vBulletinHook::fetch_hook('socgroupmemberdata_delete')) ? eval($hook) : false;
	}

	/**
	* Updates the counter for the owner of the group that shows how many pending members
	* they have awaiting them to deal with
	*
	* @param	integer	The userid of the owner of the group
	*/
	function update_owner_pending_member_count($ownerid)
	{
		list($pendingcountforowner) = $this->registry->db->query_first("
			SELECT SUM(moderatedmembers) FROM " . TABLE_PREFIX . "socialgroup
			WHERE creatoruserid = " . $ownerid
		, DBARRAY_NUM);

		$this->registry->db->query_write("
			UPDATE " . TABLE_PREFIX . "user
			SET socgroupreqcount = " . intval($pendingcountforowner) . "
			WHERE userid = " . $ownerid
		);
	}

	/**
	* Updates the users "Group Invites" count
	*
	*/
	function update_user_invite_count()
	{
		list($invitedgroups) = $this->registry->db->query_first("
			SELECT COUNT(*) FROM " . TABLE_PREFIX . "socialgroupmember WHERE
			userid = " . $this->fetch_field('userid') . "
			AND TYPE = 'invited'
		", DBARRAY_NUM);

		$this->registry->db->query_write("
			UPDATE " . TABLE_PREFIX . "user SET
			socgroupinvitecount = " . intval($invitedgroups) . "
			WHERE userid = " . $this->fetch_field('userid')
		);
	}
}


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>