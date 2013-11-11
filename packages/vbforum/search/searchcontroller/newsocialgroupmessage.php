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

class vBForum_Search_SearchController_NewSocialGroupMessage extends vB_Search_SearchController
{
	public function get_results($user, $criteria)
	{
		global $vbulletin;
		$db = $vbulletin->db;

		$range_filters = $criteria->get_range_filters();

		//get thread/post results.
		$lastpost_where = array();
		$post_lastpost_where = array();
		if (!empty($range_filters['markinglimit'][0]))
		{
			$cutoff = $range_filters['markinglimit'][0];

			$marking_join = "
				LEFT JOIN " . TABLE_PREFIX . "discussionread AS discussionread ON
					(discussionread.discussionid = discussion.discussionid AND discussionread.userid = " . $vbulletin->userinfo['userid'] . ")
			";

			$lastpost_where[] = "discussion.lastpost > IF(discussionread.readtime IS NULL,
				$cutoff, discussionread.readtime)";
			$lastpost_where[] = "discussion.lastpost > $cutoff";

			$post_lastpost_where[] = "groupmessage.dateline > IF(discussionread.readtime IS NULL,
				$cutoff, discussionread.readtime)";
			$post_lastpost_where[] = "groupmessage.dateline > $cutoff";
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
			$lastpost_where[] = "discussion.lastpost >= $datecut";
			$post_lastpost_where[] = "groupmessage.dateline >= $datecut";
		}

		$this->process_orderby($criteria);
		if ($criteria->get_grouped() == vB_Search_Core::GROUP_NO)
		{
			$where = array_merge($lastpost_where, $post_lastpost_where);
			$contenttypeid = vB_Search_Core::get_instance()->get_contenttypeid('vBForum', 'SocialGroupMessage');
			$set = $db->query_read_slave("
				SELECT groupmessage.gmid, discussion.discussionid
				FROM " . TABLE_PREFIX . "groupmessage AS groupmessage
				INNER JOIN " . TABLE_PREFIX . "discussion AS discussion ON
					(discussion.discussionid = groupmessage.discussionid)
				$marking_join
				" . implode("\n", $this->orderby_join) . "
				WHERE " . implode (' AND ', $where) . "
				ORDER BY {$this->orderby}
				LIMIT " . intval($vbulletin->options['maxresults'])
			);

			while ($row = $db->fetch_array($set))
			{
				$results[] = array($contenttypeid, $row['gmid'], $row['discussionid']);
			}
		}
		else
		{
			$contenttypeid = vB_Search_Core::get_instance()->get_contenttypeid('vBForum', 'SocialGroupDiscussion');
			$set = $db->query_read_slave("
				SELECT discussion.discussionid
				FROM " . TABLE_PREFIX . "discussion AS discussion
				$marking_join
				" . implode("\n", $this->orderby_join) . "
				WHERE " . implode (' AND ', $lastpost_where) . "
				ORDER BY {$this->orderby}
				LIMIT " . intval($vbulletin->options['maxresults'])
			);

			while ($row = $db->fetch_array($set))
			{
				$results[] = array($contenttypeid, $row['discussionid'], $row['discussionid']);
			}
		}
		
		return $results;
	}

	private function process_orderby($criteria)
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
			//honor this for backwards compatibility
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
				$table = 'groupmessage';
			}
			else
			{
				$table = 'discussion';
			}

			$this->orderby = "$table.$sort_map[$sort] $direction";
			if (!$date_sort)
			{
				$this->orderby .= ", groupmessage.dateline DESC";
			}
		}
		else
		{
			$table = 'discussion';
			if ($sort_map[$sort] == 'postusername')
			{
				$table = 'firstpost';
				$this->orderby_join['firstpost'] = "JOIN " . TABLE_PREFIX . "groupmessage AS firstpost ON
					discussion.firstpostid = firstpost.gmid
				";
			}

			$this->orderby = "discussion.$sort_map[$sort] $direction";

			if (!$date_sort)
			{
				$this->orderby .= ", discussion.dateline DESC";
			}
		}
	}

	private $orderby_join = array();
	private $orderby = "";

}
/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28694 $
|| ####################################################################
\*======================================================================*/
