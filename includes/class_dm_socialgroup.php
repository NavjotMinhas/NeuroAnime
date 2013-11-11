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
require_once(DIR . '/vb/search/indexcontroller/queue.php');
/**
* Class to do data save/delete operations for Social Groups
*
* @package	vBulletin
* @version	$Revision: 45121 $
* @date		$Date: 2011-06-27 09:55:51 -0700 (Mon, 27 Jun 2011) $
*/
class vB_DataManager_SocialGroup extends vB_DataManager
{
	/**
	* Array of recognised and required fields for social groups, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'groupid'          => array(TYPE_UINT,       REQ_INCR, VF_METHOD, 'verify_nonzero'),
		'socialgroupcategoryid' => array(TYPE_UINT,  REQ_YES,  VF_METHOD, 'verify_nonzero'),
		'name'             => array(TYPE_NOHTMLCOND, REQ_YES,  VF_METHOD),
		'description'      => array(TYPE_NOHTMLCOND, REQ_NO,   VF_METHOD),
		'creatoruserid'    => array(TYPE_UINT,       REQ_NO,   VF_METHOD, 'verify_nonzero'),
		'dateline'         => array(TYPE_UNIXTIME,   REQ_AUTO),
		'members'          => array(TYPE_UINT,       REQ_NO),
		'picturecount'     => array(TYPE_UINT,       REQ_NO),
		'lastposter'       => array(TYPE_NOHTMLCOND, REQ_NO),
		'lastposterid'     => array(TYPE_UINT,       REQ_NO),
		'lastpost'         => array(TYPE_UINT,       REQ_NO),
		'lastgmid'         => array(TYPE_UINT,       REQ_NO),
		'lastdiscussion'   => array(TYPE_NOHTMLCOND, REQ_NO),
		'lastdiscussionid' => array(TYPE_UINT,       REQ_NO),
		'visible'          => array(TYPE_UINT,       REQ_NO),
		'deleted'          => array(TYPE_UINT,       REQ_NO),
		'moderation'       => array(TYPE_UINT,       REQ_NO),
		'discussions'      => array(TYPE_UINT,       REQ_NO),
		'type'             => array(TYPE_STR,        REQ_NO, VF_METHOD),
		'moderatedmembers' => array(TYPE_UINT,       REQ_NO),
		'options'          => array(TYPE_UINT,       REQ_NO),
		'lastupdate'       => array(TYPE_UINT,       REQ_NO)
	);

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'socialgroup';

	/**
	* Things that are bitfields
	*
	* @var	array
	*/
	var $bitfields = array(
		'options'      => 'bf_misc_socialgroupoptions',
	);

	/**
	* Condition for update query
	*
	* @var	array
	*/
	var $condition_construct = array('groupid = %1$d', 'groupid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_SocialGroup(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

 		($hook = vBulletinHook::fetch_hook('socgroupdata_start')) ? eval($hook) : false;
	}

	/**
	* Verify that the name doesn't already exists
	*
	* @param	string	Group Name
	*
	* @return	boolean
	*/
	function verify_name(&$name)
	{
		// replace html-encoded spaces with actual spaces
		$name = preg_replace('/&#(0*32|x0*20);/', ' ', $name);
		$name = trim($name);

		if (!$this->condition OR $name != $this->existing['name'])
		{
			$dupegroup = $this->registry->db->query_first("
				SELECT *
				FROM " . TABLE_PREFIX . "socialgroup
				WHERE name = '" . $this->registry->db->escape_string($name) . "'
					AND groupid <> " . intval($this->fetch_field('groupid'))
			);
			if ($dupegroup)
			{
				$this->error('group_already_exists_view_x', fetch_seo_url('group', $dupegroup));
				return false;
			}
		}

		if (empty($name))
		{
			$this->error('must_enter_group_name');
			return false;
		}

		if (vbstrlen($name, true) > $this->registry->options['sg_name_maxchars'])
		{
			$this->error('name_too_long_max_x', $this->registry->options['sg_name_maxchars']);
			return false;
		}

		return true;
	}

	/**
	 * Verifies that the description is not too long
	 *
	 * @param string $description
	 * @return boolean
	 */
	function verify_description(&$description)
	{
		if (($currentlength = vbstrlen($description, true)) > $this->registry->options['sg_maxdescriptionchars'])
		{
			$this->error('description_toolong_max_x', $currentlength, $this->registry->options['sg_maxdescriptionchars']);
			return false;
		}

		return true;
	}

	/**
	* Verifies the type of the group is valid
	*
	* @param	string
	*
	* @return	boolean
	*/
	function verify_type(&$type)
	{
		return in_array($type, array(
			'public',
			'moderated',
			'inviteonly'
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

		if (!$this->fetch_field('lastpost') AND !$this->condition)
		{
			$this->set('lastpost', TIMENOW);
		}

		if (!$this->fetch_field('lastupdate'))
		{
			$this->set('lastupdate', TIMENOW);
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('socgroupdata_presave')) ? eval($hook) : false;

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
		// When creating a group, the creator needs to be come a user automatically
		if (empty($this->condition))
		{
			$socialgroupmemberdm = datamanager_init('SocialGroupMember', $this->registry, ERRTYPE_STANDARD);

			$socialgroupmemberdm->set('userid', $this->fetch_field('creatoruserid'));
			$socialgroupmemberdm->set('groupid', $this->fetch_field('groupid'));
			$socialgroupmemberdm->set('dateline', $this->fetch_field('dateline'));
			$socialgroupmemberdm->set('type', 'member');

			$socialgroupmemberdm->save();
			unset($socialgroupmemberdm);

			exec_sg_mark_as_read('group', $this->fetch_field('groupid'));
		}
		($hook = vBulletinHook::fetch_hook('socgroupdata_postsave')) ? eval($hook) : false;

		if ($this->fetch_field('socialgroupcategoryid') != $this->existing['socialgroupcategoryid'])
		{
			fetch_socialgroup_category_cloud(true);
		}

		if (($this->fetch_field('name') != $this->existing['name']) OR ($this->fetch_field('description') != $this->existing['description']))
		{
			fetch_socialgroup_newest_groups(true, false, !$this->registry->options['sg_enablesocialgroupicons']);
		}
		vb_Search_Indexcontroller_Queue::indexQueue('vBForum', 'SocialGroup', 'index',
			  $this->fetch_field('groupid'));
	}

	/**
	* Any code to run after deleting
	*
	* @param	Boolean Do the query?
	*/
	function post_delete($doquery = true)
	{
		if (!defined('MYSQL_VERSION'))
		{
			$mysqlversion = $this->registry->db->query_first("SELECT version() AS version");
			define('MYSQL_VERSION', $mysqlversion['version']);
		}
		$enginetype = (version_compare(MYSQL_VERSION, '4.0.18', '<')) ? 'TYPE' : 'ENGINE';
		$tabletype = (version_compare(MYSQL_VERSION, '4.1', '<')) ? 'HEAP' : 'MEMORY';
		$aggtable = 'aaggregate_temp_' . $this->registry->userinfo['userid'] . '_' . $this->fetch_field('groupid') . '_' . TIMENOW;

		$this->registry->db->query_write("
			CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "$aggtable (
				userid INT UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (userid)
			) $enginetype = $tabletype
		");

		if ($this->registry->options['usemailqueue'] == 2)
		{
			$this->registry->db->lock_tables(array(
				$aggtable           => 'WRITE',
				'socialgroupmember' => 'WRITE'
			));
		}

		$this->registry->db->query_write("
			INSERT IGNORE INTO " . TABLE_PREFIX . "$aggtable
			SELECT userid
			FROM " . TABLE_PREFIX . "socialgroupmember
			WHERE groupid = " . $this->fetch_field('groupid') . "
				AND type = 'invited'
		");

		// A user's 'type' doesn't seem to change when a group's type is changed. Why is this?
		// e.g. a 'moderated' user doesn't become a member if a moderated group is made public
		$result = array();
		if ($this->fetch_field('creatoruserid'))
		{
			$result = $this->registry->db->query_first("
				SELECT COUNT(*) AS count
				FROM " . TABLE_PREFIX . "socialgroupmember
				WHERE groupid = " . $this->fetch_field('groupid') . "
					AND type = 'moderated'
			");
		}

		if ($this->registry->options['usemailqueue'] == 2)
		{
			$this->registry->db->unlock_tables();
		}

		$this->registry->db->query_write("DELETE FROM " . TABLE_PREFIX . "socialgroupmember WHERE groupid = " . $this->fetch_field('groupid'));

		$this->registry->db->query_write(
			"UPDATE " . TABLE_PREFIX . "user AS user,". TABLE_PREFIX . "$aggtable AS aggregate
			SET socgroupinvitecount = IF(socgroupinvitecount > 0, socgroupinvitecount - 1, 0)
			WHERE user.userid = aggregate.userid
		");
		if ($result['count'])
		{
			$this->registry->db->query_write("
				UPDATE " . TABLE_PREFIX . "user
				SET socgroupreqcount = IF(socgroupreqcount >= $result[count], socgroupreqcount - $result[count], 0)
				WHERE userid = " . $this->fetch_field('creatoruserid') . "
			");
		}

		$this->registry->db->query_write("DROP TABLE IF EXISTS " . TABLE_PREFIX . $aggtable);

		require_once(DIR . '/includes/class_bootstrap_framework.php');
		vB_Bootstrap_Framework::init();

		// delete picture references
		$contenttypeid = vB_Types::instance()->getContentTypeID('vBForum_SocialGroup');
		$this->registry->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "attachment
			WHERE contentid = " . intval($this->fetch_field('groupid')) . " AND
				contenttypeid = $contenttypeid
		");

		vb_Search_Indexcontroller_Queue::indexQueue('vBForum', 'SocialGroup', 'delete',
			$this->fetch_field('groupid'));

		$gms_to_delete = array();

		$gmids = $this->registry->db->query_read(
			"SELECT gmid FROM " . TABLE_PREFIX . "discussion AS discussion
			 INNER JOIN " . TABLE_PREFIX . "groupmessage AS gm
			  ON (gm.discussionid = discussion.discussionid)
			 WHERE discussion.groupid = " . intval($this->fetch_field('groupid'))
		);

		while ($gmid = $this->registry->db->fetch_array($gmids))
		{
			$gms_to_delete[] = $gmid['gmid'];
			vb_Search_Indexcontroller_Queue::indexQueue('vBForum', 'SocialGroupMessage', 'delete',	$gmid['gmid']);
		}

		$this->registry->db->free_result($gmids);

		if (!empty($gms_to_delete))
		{
			$this->registry->db->query_write("
				DELETE FROM " . TABLE_PREFIX . "moderation
				WHERE type = 'groupmessage'
					AND primaryid IN (" . implode(', ', $gms_to_delete) . ")
			");

			$this->registry->db->query_write("
				DELETE FROM " . TABLE_PREFIX . "deletionlog
				WHERE type = 'groupmessage'
					AND primaryid IN (" . implode(', ', $gms_to_delete) . ")
			");

			$this->registry->db->query_write("DELETE " . TABLE_PREFIX . "groupmessage
											FROM " . TABLE_PREFIX . "groupmessage
											LEFT JOIN " . TABLE_PREFIX . "discussion
											 ON (" . TABLE_PREFIX . "discussion.discussionid = " . TABLE_PREFIX . "groupmessage.discussionid)
											WHERE " . TABLE_PREFIX . "discussion.groupid = " . $this->fetch_field('groupid'));
		}

		// delete subscribed discussions
		$this->registry->db->query_write("
			DELETE " . TABLE_PREFIX. "subscribediscussion
			FROM " . TABLE_PREFIX . "subscribediscussion
			INNER JOIN " . TABLE_PREFIX . "discussion
			 ON (" . TABLE_PREFIX . "discussion.discussionid = " . TABLE_PREFIX . "subscribediscussion.discussionid)
			 AND " . TABLE_PREFIX . "discussion.groupid = " . intval($this->fetch_field('groupid')) . "
		");

		// delete discussion readmarking
		$this->registry->db->query_write("
			DELETE " . TABLE_PREFIX. "discussionread
			FROM " . TABLE_PREFIX . "discussionread
			INNER JOIN " . TABLE_PREFIX . "discussion
			 ON (" . TABLE_PREFIX . "discussion.discussionid = " . TABLE_PREFIX . "discussionread.discussionid)
			 AND " . TABLE_PREFIX . "discussion.groupid = " . intval($this->fetch_field('groupid')) . "
		");

		// delete discussions
		$this->registry->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "discussion
			WHERE groupid = " . intval($this->fetch_field('groupid')));

		// delete group subscriptions
		$this->registry->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "subscribegroup
			WHERE " . TABLE_PREFIX . "subscribegroup.groupid = " . intval($this->fetch_field('groupid')) . "
		");

		// delete readmarking
		$this->registry->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "groupread
			WHERE " . TABLE_PREFIX . "groupread.groupid = " . intval($this->fetch_field('groupid')) . "
		");

		// delete group icon
		$groupicon =& datamanager_init('SocialGroupIcon', $this->registry, ERRTYPE_STANDARD);
		$groupicon->condition = "groupid = " . intval($this->fetch_field('groupid'));
		$groupicon->delete();
		unset($groupicon);

		// update moderation count for owner
		update_owner_pending_gm_count($this->fetch_field('creatoruserid'));

		// update category cloud
		fetch_socialgroup_category_cloud(true);

		// update newest groups
		fetch_socialgroup_newest_groups(true, false, !$this->registry->options['sg_enablesocialgroupicons']);

 		($hook = vBulletinHook::fetch_hook('socgroupdata_delete')) ? eval($hook) : false;
	}

	/**
	* Rebuilds the Count of the pictures in a group
	*
	*/
	function rebuild_picturecount()
	{
		if ($this->fetch_field('groupid'))
		{
		require_once(DIR . '/includes/class_bootstrap_framework.php');
		require_once(DIR . '/vb/types.php');
		vB_Bootstrap_Framework::init();
		$types = vB_Types::instance();
		$contenttypeid = intval($types->getContentTypeID('vBForum_SocialGroup'));

			$picturecount = $this->registry->db->query_first("
				SELECT COUNT(*) AS count
				FROM " . TABLE_PREFIX . "attachment AS a
				INNER JOIN " . TABLE_PREFIX . "socialgroupmember AS socialgroupmember ON
					(socialgroupmember.userid = a.userid AND socialgroupmember.groupid = " . $this->fetch_field('groupid') . " AND socialgroupmember.type = 'member')
				WHERE
					a.contentid = " . $this->fetch_field('groupid') . "
						AND
					a.contenttypeid = $contenttypeid
			");
			$this->set('picturecount', $picturecount['count']);
			$this->set('lastupdate', TIMENOW);
		}
	}

	/**
	* Rebuilds the member counters for the group#
	*
	*/
	function rebuild_membercounts()
	{
		if ($this->fetch_field('groupid'))
		{
			$memberstats = $this->registry->db->query_read("
				SELECT COUNT(*) AS count, type
				FROM " . TABLE_PREFIX . "socialgroupmember
				WHERE groupid = " . $this->fetch_field('groupid') . "
				GROUP BY type
			");

			$hasmoderatedmembers = false;

			while($memberstat = $this->registry->db->fetch_array($memberstats))
			{
				switch ($memberstat['type'])
				{
					case 'member':
					{
						$this->set('members', $memberstat['count']);
					}
					break;

					case 'moderated':
					{
						$this->set('moderatedmembers', $memberstat['count']);
						$hasmoderatedmembers = true;
					}
					break;
				}
			}
			$this->registry->db->free_result($memberstats);

			if (!$hasmoderatedmembers)
			{
				$this->set('moderatedmembers', 0);
			}

			$this->set('lastupdate', TIMENOW);
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 45121 $
|| ####################################################################
\*======================================================================*/
?>
