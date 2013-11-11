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
 * @package vBulletin
 * @subpackage Search
 * @author Kevin Sours, vBulletin Development Team
 * @version $Revision: 28678 $
 * @since $Date: 2008-12-03 16:54:12 +0000 (Wed, 03 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */

require_once (DIR . '/vb/search/result.php');

/**
 * Enter description here...
 *
 * @package vBulletin
 * @subpackage Search
 */
class vBForum_Search_Result_Announcement extends vB_Search_Result
{
	public static function create($id)
	{
		$items = self::create_array(array($id));
		if (count($items))
		{
			return array_shift($items);
		}
		else
		{
			return new vB_Search_Result_Null();
		}
	}

	public static function create_array($ids)
	{
		global $vbulletin;

		$set = $vbulletin->db->query_read_slave("
			SELECT announcementid, startdate, title, announcement.views, forumid,
				user.username, user.userid, user.usertitle, user.customtitle, user.usergroupid,
				IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid, infractiongroupid
			FROM " . TABLE_PREFIX . "announcement AS announcement
			LEFT JOIN " . TABLE_PREFIX . "user AS user USING (userid)
			WHERE announcementid IN (" . implode(',', array_map('intval', $ids)) . ")
		");

		$items = array();
		while ($record = $vbulletin->db->fetch_array($set))
		{
			fetch_musername($record);
			$record['title'] = fetch_censored_text($record['title']);
			$record['postdate'] = vbdate($vbulletin->options['dateformat'], $record['startdate']);
			$record['statusicon'] = 'new';
			$record['views'] = vb_number_format($record['views']);
			$record['forumtitle'] = $vbulletin->forumcache["$record[forumid]"]['title'];
			$show['forumtitle'] = ($record['forumid'] == -1) ? false : true;

			$announcement = new vBForum_Search_Result_Announcement();
			$announcement->record = $record;
			$items[$record['announcementid']] = $announcement;
		}
		return $items;
	}

	protected function __construct() {}

	public function get_contenttype()
	{
		return vB_Search_Core::get_instance()->get_contenttypeid('vBForum', 'Announcement');
	}

	public function can_search($user)
	{
		if ($this->record['forumid'] == -1)
		{
			return true;
		}
		else
		{
			return !in_array($this->record['forumid'], $user->getUnsearchableForums());
		}
	}

	public function render($current_user, $criteria, $template = '')
	{
		$template = vB_Template::create('search_results_announcement');
		$template->register('announcecolspan', 6);
		$template->register('announcement', $this->record);
		$template->register('announcementidlink', '&amp;a=' . $this->record['announcementid']);

		//this is actually how the legacy search code does it, since the foruminfo
		//value it passes isn't set properly.  Its only used to set the forum id
		//on the link which is ignored if the announcementid is also set
		$template->register('foruminfo', array());
		return $template->render();
	}


	/*** Returns the primary id. Allows us to cache a result item.
	 *
	 * @result	integer
	 ***/
	public function get_id()
	{
		if (isset($this->$record) AND isset($this->$record['announcementid']) )
		{
			$this->$record['announcementid'];
		}
		return false;
	}

	private $record;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/