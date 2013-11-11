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
 * Social Group Message item class.
 * @author Kevin Sours, vBulletin Development Team
 * @version 4.1.5 Patch Level 1
 * @since 1st Dec, 2008
 * @copyright vBulletin Solutions Inc.
 */
class vBForum_Item_SocialGroupMessage extends vB_Item
{
	/*InfoFlags=====================================================================*/

	/**
	 * Flags for required item info.
	 * These are used for $required_info and $loaded_info.
	 */
//	const INFO_DISCUSSION = 2;

	/**
	 * The total flags for all info.
	 * This should be overridden by children based on the total of their info flags.
	 *
	 * @var int
	 */
	protected $INFO_ALL = 0x01;

	/**
	 * Map of query => info.
	 *
	 * @var array int => int
	 */


	/*ModelProperties===============================================================*/

	/**
	 * Array of all valid item model properties.
	 * This is used to check if a class property can be set as a property.
	 *
	 * @var array string
	 */
	protected $item_properties = array(
		/*INFO_BASIC==================*/
		'discussionid',
		'postuserid',
		'postusername',
		'dateline',
		'state',
		'title',
		'pagetext',
		'ipaddress',
		'allowsmilie',
		'reportthreadid',
		'discussion'
	);

	//db fields
	protected $discussionid;
	protected $postuserid;
	protected $postusername;
	protected $dateline;
	protected $state;
	protected $title;
	protected $pagetext;
	protected $ipaddress;
	protected $allowsmilie;
	protected $reportthreadid;

	protected $discussion;

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
			return
				"SELECT 
					groupmessage.discussionid,
					groupmessage.postuserid,
					groupmessage.postusername,
					groupmessage.dateline,
					groupmessage.state,
					groupmessage.title,
					groupmessage.pagetext,
					groupmessage.ipaddress,
					groupmessage.allowsmilie,
					groupmessage.reportthreadid " .
					$hook_query_fields . "
				FROM " . TABLE_PREFIX . "groupmessage AS groupmessage " .
					$hook_query_join . "
				WHERE gmid = " . intval($this->itemid) . "
					$hook_query_where";
		}

		throw (new vB_Exception_Model('Invalid query id \'' . htmlspecialchars($required_query) . 
			'\'specified for node item: ' . htmlspecialchars($query)));
	}

	public function getDiscussion()
	{
		if (!$this->discussion)
		{
			$this->discussion = new vBForum_Item_SocialGroupDiscussion($this->getDiscussionId());
			$this->discussion->setImportant($this->important);
		}

		return $this->discussion;
	}

	public function setDiscussion($discussion)
	{
		if ($discussion->getId() != $this->getDiscussionId())
		{
			throw (new vB_Exception_Model('Discussion object id ' . $dicussion->getDiscussionId() .
				'does not match discussion id' . $this->getDiscussionId() . 'for this message'));
		}
		$this->discussion = $discussion;
	}

	public function getDiscussionId() {$this->Load(); return $this->discussionid;}
	public function getPostUserId() {$this->Load(); return $this->postuserid;}
	public function getPostUserName() {$this->Load(); return $this->postusername;}
	public function getDateline() {$this->Load(); return $this->dateline;}
	public function getState() {$this->Load(); return $this->state;}
	public function getTitle() {$this->Load(); return $this->title;}
	public function getPageText() {$this->Load(); return $this->pagetext;}
	public function getIpAddress() {$this->Load(); return $this->ipaddress;}
	public function getAllowSmilie() {$this->Load(); return $this->allowsmilie;}
	public function getReportThreadId() {$this->Load(); return $this->reportthreadid;}

	/**
	 * Return the socal group message info array expected by legacy code
	 *
	 * Does not include user fields or derived fields
   */
	public function getInfo()
	{
		$item = array();
		$item['type'] = 'message';
		$item['goto'] = false;
		$item['gmid'] = $this->getId();
		$item['discussionid'] = $this->getDiscussionId();
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

	public function getDeletionLogArray()
	{
		global $vbulletin;

		$log = $vbulletin->db->query_first("
			SELECT deletionlog.userid, deletionlog.username, deletionlog.reason
			FROM " . TABLE_PREFIX . "deletionlog as deletionlog 
			WHERE deletionlog.primaryid = " . intval($this->getID()) . " AND 
				deletionlog.type = 'groupmessage'
		");

		if (!$log)
		{
			return array('userid' => null, 'username' => null, 'reason' => null);
		}
		else 
		{
			return $log;
		}
	}

	public function getTemplate()
	{
	}


	/*Permissions===============================================================*/
	public function canBe($permission, $user)
	{
		$discussion = $this->getDiscussion();
		if (!$discussion->canBe($permission, $user))
		{
			return false;
		}

		switch($permission)
		{
			//for now these are the same
			case 'viewed':
			case 'searched':
				return $this->canBeViewed($user);
				break;
		}
	}

	private function canBeViewed($user)
	{
		$state = $this->getState('state');
		$group = $this->getDiscussion()->getSocialGroup();

		if ($state == 'moderation' AND !$group->has_modperm('canmoderategroupmessages', $user))
		{
			return false;
		}

		if ($state == 'deleted' AND !$group->has_modperm('canviewdeleted', $user))
		{
			return false;
		}

		return true;
	}
}
/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/
