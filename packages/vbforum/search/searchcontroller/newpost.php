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
 * @author vBulletin Development Team
 * @version $Revision: 28678 $
 * @copyright vBulletin Solutions Inc.
 */

require_once(DIR . '/vb/search/searchcontroller.php');

class vBForum_Search_SearchController_NewPost extends vB_Search_SearchController
{
	public function get_results($user, $criteria)
	{
		global $vbulletin;
		$db = $vbulletin->db;

		$range_filters = $criteria->get_range_filters();
		$equals_filters = $criteria->get_equals_filters();
		$notequals_filter = $criteria->get_notequals_filters();

		//handle forums
		if (isset($equals_filters['forumid']))
		{
			$forumids = $equals_filters['forumid'];
		}
		else
		{
			$forumids = array_keys($vbulletin->forumcache);
		}

		$excluded_forumids = array();
		if (isset($notequals_filter['forumid']))
		{
			$excluded_forumids = $notequals_filter['forumid'];
		}

		$forumids = array_diff($forumids, $excluded_forumids, $user->getUnsearchableForums());

		$results = array();

		if (empty($forumids))
		{
			return $results;
		}

		//get announcements
		if (!$user->isGuest())
		{
			$contenttypeid = vB_Search_Core::get_instance()->get_contenttypeid('vBForum', 'Announcement');

			$announcements = array();
			$basetime = TIMENOW;
			$mindate = $basetime - 2592000; // 30 days
			$announcements = $db->query_read_slave("
				SELECT announcement.announcementid
				FROM " . TABLE_PREFIX . "announcement AS announcement
				LEFT JOIN " . TABLE_PREFIX . "announcementread AS ar ON
					(announcement.announcementid = ar.announcementid AND ar.userid = " . $user->get_field('userid') . ")
				WHERE
					ISNULL(ar.userid) AND
					startdate < $basetime AND
					startdate > $mindate AND
					enddate > $basetime AND
					forumid IN(-1, " . implode(', ', $forumids) . ")
			");

			while ($row = $db->fetch_array($announcements))
			{
				$results[] = array($contenttypeid, $row['announcementid'], $row['announcementid']);
			}
		}

		//get thread/post results.
		if (!empty($range_filters['markinglimit'][0]))
		{
			$cutoff = $range_filters['markinglimit'][0];

			$marking_join = "
				LEFT JOIN " . TABLE_PREFIX . "threadread AS threadread ON
					(threadread.threadid = thread.threadid AND threadread.userid = " . $vbulletin->userinfo['userid'] . ")
				INNER JOIN " . TABLE_PREFIX . "forum AS forum ON (forum.forumid = thread.forumid)
				LEFT JOIN " . TABLE_PREFIX . "forumread AS forumread ON
					(forumread.forumid = forum.forumid AND forumread.userid = " . $vbulletin->userinfo['userid'] . ")
			";

			$lastpost_where = "
				AND thread.lastpost > IF(threadread.readtime IS NULL, $cutoff, threadread.readtime)
				AND thread.lastpost > IF(forumread.readtime IS NULL, $cutoff, forumread.readtime)
				AND thread.lastpost > $cutoff
			";

			$post_lastpost_where = "
				AND post.dateline > IF(threadread.readtime IS NULL, $cutoff, threadread.readtime)
				AND post.dateline > IF(forumread.readtime IS NULL, $cutoff, forumread.readtime)
				AND post.dateline > $cutoff
			";
		}
		else
		{
			//get date cut -- but only if we're not using the threadmarking filter
			if (isset($range_filters['datecut']))
			{
				//ignore any upper limit
				$datecut = $range_filters['datecut'][0];
			}
			else
			{
				return $results;
			}

			$marking_join = '';
			$lastpost_where = "AND thread.lastpost >= $datecut";
			$post_lastpost_where = "AND post.dateline >= $datecut";
		}

		$orderby = $this->get_orderby($criteria);

		//This doesn't actually work -- removing.
		//even though showresults would filter thread.visible=0, thread.visible remains in these 2 queries
		//so that the 4 part index on thread can be used.

		if ($criteria->get_grouped() == vB_Search_Core::GROUP_NO)
		{
			$contenttypeid = vB_Search_Core::get_instance()->get_contenttypeid('vBForum', 'Post');
			$posts = $db->query_read_slave($q = "
				SELECT post.postid, post.threadid
				FROM " . TABLE_PREFIX . "post AS post
				INNER JOIN " . TABLE_PREFIX . "thread AS thread ON (thread.threadid = post.threadid)
				$marking_join
				WHERE thread.forumid IN(" . implode(', ', $forumids) . ")
					$lastpost_where
					$post_lastpost_where
				ORDER BY $orderby
				LIMIT " . intval($vbulletin->options['maxresults'])
			);

			while ($post = $db->fetch_array($posts))
			{
				$results[] = array($contenttypeid, $post['postid'], $post['threadid']);
			}
		}
		else
		{
			$contenttypeid = vB_Search_Core::get_instance()->get_contenttypeid('vBForum', 'Thread');
			$threads = $db->query_read_slave($q = "
				SELECT thread.threadid
				FROM " . TABLE_PREFIX . "thread AS thread
				$marking_join
				WHERE thread.forumid IN(" . implode(', ', $forumids) . ")
					$lastpost_where
					AND thread.open <> 10
				ORDER BY $orderby
				LIMIT " . intval($vbulletin->options['maxresults'])
			);

			while ($thread = $db->fetch_array($threads))
			{
				$results[] = array($contenttypeid, $thread['threadid'], $thread['threadid']);
			}
		}

		return $results;
	}

	private function get_orderby($criteria)
	{
		$sort = $criteria->get_sort();
		$direction = strtolower($criteria->get_sort_direction()) == 'desc' ? 'desc' : 'asc';

		$sort_map = array
		(
			'user' => 'postusername',
			'dateline' => 'dateline',
			'groupuser' => 'postusername',
			'groupdateline' => 'lastpost',
			'defaultdateline' => 'lastpost',
			'defaultuser' => 'username',
			'views' => 'views',
			'replycount' => 'replycount',
			'threadstart' => 'dateline'
		);

		if (!isset($sort_map[$sort]))
		{
			$sort = ($criteria->get_grouped() == vB_Search_Core::GROUP_NO) ? 'dateline' : 'groupdateline';
		}

		//if its a non group field and we aren't grouping, use the post table
		$nongroup_field = in_array($sort, array ('user', 'dateline'));

		//if a field is a date, don't add the secondary sort by the "dateline" field
		$date_sort = in_array($sort,
			array ('dateline', 'groupdateline', 'defaultdateline', 'threadstart')
		);

		if ($criteria->get_grouped() == vB_Search_Core::GROUP_NO)
		{
			if ($nongroup_field)
			{
				$table = 'post';
			}
			else
			{
				$table = 'thread';
			}

			$orderby = "$table.$sort_map[$sort] $direction";
			if (!$date_sort)
			{
				$orderby .= ", post.dateline DESC";
			}
		}
		else
		{
			$orderby = "thread.$sort_map[$sort] $direction";
			if (!$date_sort)
			{
				$orderby .= ", thread.dateline DESC";
			}
		}

		return $orderby;
	}
}
/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28694 $
|| ####################################################################
\*======================================================================*/
