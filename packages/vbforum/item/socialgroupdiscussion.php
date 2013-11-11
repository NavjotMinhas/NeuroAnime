<?php if (!defined('VB_ENTRY')) die('Access denied.');
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

/**
 * Social Group Discussion item class.
 * @author Kevin Sours, vBulletin Development Team
 * @version 4.1.5 Patch Level 1
 * @since 1st Dec, 2008
 * @copyright vBulletin Solutions Inc.
 */
class vBForum_Item_SocialGroupDiscussion extends vB_Item
{
	/*InfoFlags=====================================================================*/

	/**
	 * The total flags for all info.
	 * This should be overridden by children based on the total of their info flags.
	 *
	 * @var int
	 */
	protected $INFO_ALL = 0x01;

	/*ModelProperties===============================================================*/
	protected $allow_no_itemid = true;

	/**
	 * Array of all valid item model properties.
	 * This is used to check if a class property can be set as a property.
	 *
	 * @var array string
	 */
	protected $item_properties = array(
		/*INFO_DISCUSSION=================*/
		'discussionid',
		'groupid',
		'firstpostid',
		'lastpostid',
		'lastpost',
		'lastposter',
		'lastposterid',
		'visible',
		'deleted',
		'moderation',
		'subscribers',

		'postuserid',
		'postusername',
		'dateline',
		'state',
		'title',
		'pagetext',
		'ipaddress',
		'allowsmilie',
		'reportthreadid',
	);

	protected $discussionid;
	protected $groupid;
	protected $userid;
	protected $firstpostid;
	protected $lastpostid;
	protected $lastpost;
	protected $lastposter;
	protected $lastposterid;
	protected $visible;
	protected $deleted;
	protected $moderation;
	protected $subscribers;

	protected $postuserid;
	protected $postusername;
	protected $dateline;
	protected $state;
	protected $title;
	protected $pagetext;
	protected $ipaddress;
	protected $allowsmilie;
	protected $reportthreadid;

	protected $readtimes = array();

	/**
	*	@var vB_Legacy_SocialGroup
	*/
	protected $social_group = null;


	/*LoadInfo======================================================================*/

	/**
	 * Applies the result of the load query.
	 * Child classes should extend or override to determine what was loaded based
	 * on $required_query and $required_info.
	 *
	 * This method should only ever be used directly after performing the queries so
	 * that $this->required_info accurately reflects the query result.
	 *
	 * @param resource $result					- The db result resource
	 * @param int $load_query					- The query that the result is from
	 */
	protected function applyLoad($result, $load_query)
	{
		return parent::applyLoad($result, $load_query);
	}


	/**
	 * Fetches the SQL for loading.
	 * $required_query is used to identify which query to build for classes that
	 * have multiple queries for fetching info.
	 *
	 * This can safely be based on $this->required_info as long as a consitent
	 * flag is used for identifying the query.
	 *
	 * @param int $required_query				- The required query
	 * @param bool $force_rebuild				- Whether to rebuild the string
	 *
	 * @return string
	 */
	protected function getLoadQuery($required_query, $force_rebuild = false)
	{
		// Hooks should check the required query before populating the hook vars
		$hook_query_fields = $hook_query_join = $hook_query_where = '';
		($hook = vBulletinHook::fetch_hook($this->query_hook)) ? eval($hook) : false;

		if (self::QUERY_BASIC == $required_query)
		{
			$select = array();
			foreach ($this->item_properties as $property)
			{
				$select[] = "discussion.$property";
			}

			return
				"SELECT
					discussion.*,
					firstpost.postuserid,
					firstpost.postusername,
					firstpost.dateline,
					firstpost.state,
					firstpost.title,
					firstpost.pagetext,
					firstpost.ipaddress,
					firstpost.allowsmilie,
					firstpost.reportthreadid " .
					$hook_query_fields . "
				FROM " . TABLE_PREFIX . "discussion AS discussion JOIN " .
					TABLE_PREFIX . "groupmessage AS firstpost ON discussion.firstpostid = firstpost.gmid
					INNER JOIN " . TABLE_PREFIX . "socialgroup AS socialgroup ON socialgroup.groupid = discussion.groupid " .
				$hook_query_join . "
				WHERE discussion.discussionid = " . intval($this->itemid) . "
				$hook_query_where";
		}

		throw (new vB_Exception_Model('Invalid query id \'' . htmlspecialchars($required_query) .
			'\'specified for node item: ' . htmlspecialchars($query)));
	}

	/**
	 *	Get the Social Group object
	 *
	 *	return legacy object to avoid rewriting existing social group stuff
	 */
	public function getSocialGroup()
	{
		if (!$this->social_group)
		{
			require_once(DIR . '/vb/legacy/socialgroup.php');
			$this->social_group = vB_Legacy_SocialGroup::create_from_id($this->getGroupId());
		}

		return $this->social_group;
	}

/*
//this would be a very nice thing to have
//however it gets complicated because the full logic relies on
//some stuff associated with the current user.  However, as we don't
//actually need it for the moment, we'll skip it.
	public function getReadTime($userid)
	{
	}
*/
	/**
	*	Return just the threadmarking readtime
	*/
	protected function getReadTimeInternal($userid)
	{
		//This is ugly but...
		//I don't want to auto load data for the magic current user deep
		//in the model class and I can't think of a good way to pass the
		//user context to the LoadQuery function.  This gets the job done.

		if (!vB::$vbulletin->options['threadmarking'] OR !$userid)
		{
			return null;
		}

		if (isset($this->readtimes[$userid]))
		{
			return $this->readtimes[$userid];
		}

		$sql = "
			SELECT readtime
			FROM "  . TABLE_PREFIX . "discussionread AS discussionread
			WHERE discussionread.discussionid = " . intval($this->getId()) . "
						AND discussionread.userid = " . intval($userid);

		$result = ($this->important ? vB::$db->query_read($sql) : vB::$db->query_read_slave($sql));
		$row = vB::$db->fetch_row($result);

		if (!$row)
		{
			$this->readtimes[$userid] = $row['readtime'];
		}
		else
		{
			$this->readtimes[$userid] = null;
		}
		vB::$db->free_result($result);
		return $this->readtimes[$userid];
	}

	//getters for discussion table fields
	public function getGroupId() { $this->Load(); return $this->groupid;}
	public function getUserId() { $this->Load(); return $this->userid;}
	public function getFirstPostId() { $this->Load(); return $this->firstpostid;}
	public function getLastPostId() { $this->Load(); return $this->lastpostid;}
	public function getLastPost() { $this->Load(); return $this->lastpost;}
	public function getLastPoster() { $this->Load(); return $this->lastposter;}
	public function getLastPosterId() { $this->Load(); return $this->lastposterid;}
	public function getVisible() { $this->Load(); return $this->visible;}
	public function getDeleted() { $this->Load(); return $this->deleted;}
	public function getModeration() { $this->Load(); return $this->moderation;}
	public function getSubscribers() { $this->Load(); return $this->subscribers;}

	//getters for firstpost data fields
	public function getPostUserId() {$this->Load(); return $this->postuserid;}
	public function getPostUserName() {$this->Load(); return $this->postusername;}
	public function getDateline() {$this->Load(); return $this->dateline;}
	public function getState() {$this->Load(); return $this->state;}
	public function getTitle() {$this->Load(); return $this->title;}
	public function getPageText() {$this->Load(); return $this->pagetext;}
	public function getIpAddress() {$this->Load(); return $this->ipaddress;}
	public function getAllowSmilie() {$this->Load(); return $this->allowsmilie;}
	public function getReportThreadId() {$this->Load(); return $this->reportthreadid;}

	public function canBe($permission, $user)
	{
		$group = $this->getSocialGroup();

		if (!$group or !$group->can_view($user))
		{
			return false;
		}

		return true;
	}

	/**
	 * Return the socal group discussion info array expected by legacy code
	 *
	 * Does not include user fields or derived fields
   */
	public function getInfo($userid)
	{
		$item = array();
		$item['type'] = 'discussion';
		$item['discussionid'] = $this->getId();
		$item['groupid'] = $this->getGroupId();
		$item['firstpostid'] = $this->getFirstPostId();
		$item['lastpostid'] = $this->getLastPostId();
		$item['lastpost'] = $this->getLastPost();
		$item['lastposter'] = $this->getLastPoster();
		$item['lastposterid'] = $this->getLastPosterId();
		$item['visible'] = $this->getVisible();
		$item['deleted'] = $this->getDeleted();
		$item['moderation'] = $this->getModeration();
		$item['subscribers'] = $this->getSubscribers();
		$item['title'] = $this->getTitle();

		$item['readtime'] = $this->getReadTimeInternal($userid);
		$item['is_read'] = ($item['readtime'] > $item['lastpost']);

		$item['gmid'] = $this->getFirstPostId();
		$item['state'] = $this->getState();
		$item['postuserid'] = $this->getPostUserId();
		$item['postusername'] = $this->getPostUsername();
		$item['allowsmilie'] = $this->getAllowSmilie();
		$item['pagetext'] = $this->getPageText();
		$item['ipaddress'] = $this->getIpAddress();
		$item['itemipaddress'] = $this->getIpAddress();
		$item['dateline'] = $this->getDateline();
		return $item;
	}

	public function getTemplate()
	{
	}

	public function getDeletionLogArray()
	{
		global $vbulletin;

		$blank = array('userid' => null, 'username' => null, 'reason' => null);
		if ($this->getVisible() == 1)
		{
			return $blank;
		}

		$log = $vbulletin->db->query_first("
			SELECT deletionlog.userid, deletionlog.username, deletionlog.reason
			FROM " . TABLE_PREFIX . "deletionlog as deletionlog
			WHERE deletionlog.primaryid = " . intval($this->getFirstPostId()) . " AND
				deletionlog.type = 'groupmessage'
		");

		if (!$log)
		{
			return $blank;
		}
		else
		{
			return $log;
		}
	}
}
/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/