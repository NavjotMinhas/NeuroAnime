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
/**
 * Class representing the results of a search
 *
 * @package vBulletin
 * @subpackage Search
 */
class vB_Search_Results
{
	/**
	 * Create the results based on a list of ids return from the search implmentation
	 *
	 * @param vB_Current_User $user
	 * @param vB_Search_Criteria criteria for the search
	 * @return vB_Search_Results
	 */
	public static function create_from_criteria($user, $criteria, $searchcontroller = null)
	{
		global $vbulletin;
		$results = new vB_Search_Results();
		$results->user = $user;
		$results->criteria = $criteria;
		$start = microtime();

		if (is_null($searchcontroller))
		{
			$searchcontroller = vB_Search_Core::get_instance()->get_search_controller();
			$searchcontroller->clear();
		}

		$results->results = $searchcontroller->get_results($user, $criteria);

		//move log_search call after get_results to allow for any changes to the $criteria
		//object that might be made by the searchcontroller
		$results->searchid = $results->log_search();
		$results->dateline = TIMENOW;
		$results->cache_results();

		$searchtime = fetch_microtime_difference($start);
		$results->searchtime = $searchtime;
		$results->complete_search($searchtime);


		//log tag search
		$filter = $criteria->get_filters('tag');
		if (isset($filter[vB_Search_Core::OP_EQ]))
		{
			$dm = datamanager_init('tag', $vbulletin, ERRTYPE_ARRAY);
			$dm->log_tag_search($filter[vB_Search_Core::OP_EQ]);
		}

		return $results;
	}

	/**
	 * Create the results based on a list of ids return from the search implmentation
	 *
	 * @param vB_Current_User $user
	 * @param vB_Search_Criteria criteria for the search
	 * @return vB_Search_Results
	 */
	public static function create_from_array($user, $result_array)
	{
		global $vbulletin;

		$results = new vB_Search_Results();
		$results->user = $user;
		$results->criteria = vB_Search_Core::create_criteria(vB_Search_Core::SEARCH_ADVANCED);
		$results->criteria->set_grouped(vB_Search_Core::GROUP_NO);

		$sanitized_results = array ();
		foreach ($result_array as $result)
		{
			//if we only have the type and the id, add a dummy group id.
			//we won't use it, but the code expects it.
			if (count($result) == 2)
			{
				$result[] = 0;
			}
			$sanitized_results[] = $result;
		}

		$results->results = $sanitized_results;

		//move log_search call after get_results to allow for any changes to the $criteria
		//object that might be made by the searchcontroller
		$results->searchid = $results->log_search();
		$results->dateline = TIMENOW;
		$results->cache_results();

		$searchtime = 0;
		//todo: do we need to set $results->searchtime here as well?
		$results->searchtime = $searchtime;
		$results->complete_search($searchtime);

		return $results;
	}

	//for now we will not try to rework sort order.  We aren't guarenteed that we
	//have a full result.  The items we are missing should be irrelevant for
	//the requested sort order (missing page 10043 is only so problematic)
	//but it might be a problem if the results are resorted.
	public static function create_from_cache($user, $criteria)
	{
		global $vbulletin;
		$db = $vbulletin->db;
		$set = $db->query($q = "
			SELECT searchlog.*
			FROM " . TABLE_PREFIX . "searchlog AS searchlog
			WHERE searchhash = '" . $db->escape_string($criteria->get_hash()) . "' AND
				sortby =  '" . $db->escape_string($criteria->get_sort()) . "' AND
				sortorder =  '" . $db->escape_string($criteria->get_sort_direction()) . "' AND
				dateline > " . (TIMENOW - (60*60)) . " AND
				userid = " . intval($user->get_field('userid')) . " AND
				completed = 1
			ORDER BY dateline DESC
			LIMIT 1
		");

		while ($row = $db->fetch_array($set))
		{
/*
			if ($user->isGuest())
			{
//				if (IPADDRESS == $row['ipaddress'])
//				{
				return self::create_from_record($user, $row);
//				}
			}
			else
			{
*/
				return self::create_from_record($user, $row);
//			}
		}

		return null;
	}

	/**
	 * Load a cached resultset by id.
	 *
	 * @param vB_Current_User $user
	 * @param int $searchid The id of the search the results are for
	 * @return vB_Search_Results
	 */
	public static function create_from_searchid($user, $searchid)
	{
		global $vbulletin;
		$db = $vbulletin->db;
		$sql ="
			SELECT *
			FROM " . TABLE_PREFIX . "searchlog
			WHERE userid = " . intval($user->get_field('userid')) . " AND
				searchlogid = " . intval($searchid) . " AND
				completed = 1";

		$row = $db->query_first($sql);
		return self::create_from_record($user, $row);
	}

	public static function clean()
	{
		global $vbulletin;
		//searches expire after one hour
		$vbulletin->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "searchlog
			WHERE dateline < " . (TIMENOW - 3600) . "
		");
	}

	/**
	 *	Create a resultset from a result record.
	 */
	private static function create_from_record($user, $row)
	{
		if (isset($row['results']) AND isset($row['criteria']))
		{
			$results = new vB_Search_Results();

			$data = unserialize($row['results']);
			$results->results = $data[0];
			$results->confirmed = $data[1];
			$results->groups_seen = $data[2];
			$results->groups_rejected = $data[3];
			$results->searchid = $row['searchlogid'];
			$results->criteria = unserialize($row['criteria']);
			$results->searchtime = $row['searchtime'];
			$results->dateline = $row['dateline'];
			$results->user = $user;

			return $results;
		}
		else
		{
			return null;
		}
	}


	/**
	*	Don't allow direct instantiation of this object.
	*/
	protected function __construct() {}

	/**
	 * Get a page of results from the result set
	 *
	 * This function generates the requested page from the raw results.  It handles
	 * filtering out objects based on permissons and does page calcualtions.
	 * Filtering is done on an as needed basis with the results being saved after each
	 * pass.
	 *
	 * In addition to collecting the items needed for a page, we also check permissions
	 * for the requested window.  This ensures that a certain page count actually exists
	 * so that we can show a page jump.
	 *
	 * @param int $page Page number (one based)
	 * @param int $pagelength Number of items to display the page
	 * @param int $window The number of pages in the "window".
	 * @return array(vB_Search_Result_Item) The items on the page
	 */
	public function get_page($page, $pagelength, $window)
	{
		// Note that $start/$end are zero based, however page count is
		// one based.
		$start = (($page - 1) * $pagelength);
		$end = $start + $pagelength;
		$window_size = ($pagelength * $window);
		$end_window = ($end + $window_size);

		// loop through the confirmed results, stopping when we get to
		// either the last result requested, the last confirmed result
		// or the last result in the resultset, whichever we hit first.
		$last = min($end, $this->confirmed, count($this->results) - 1);
		$items = array();
/*
		for ($i = $start; $i <= $last; $i++)
		{
			list($contenttype, $id, $groupid) = $this->results[$i];

			$type = vB_Search_Core::get_instance()->get_search_type_from_id($contenttype);
			$item = $type->create_item($id);
			if ($this->should_group($contenttype, $type))
			{
				$item = $item->get_group_item();
			}

			$items[] = $item;
		}
*/
		$i = $start;
		$remaining = $pagelength - count($items);
		if (($remaining > 0 OR $this->confirmed < $end_window) AND $i < count($this->results))
		{
//			$chunk_start =  $this->confirmed + 1;
			$chunk_start =  $i;

			//get the count now since its going to change on us.
			$last_index = count($this->results) - 1;
			$requested_count = 20;
			while (($remaining > 0 OR $this->confirmed < $end_window) AND $chunk_start <= $last_index)
			{
				list($chunk, $last_checked_index) = $this->confirm_next_chunk($chunk_start, $last_index,
					$requested_count, $start, $remaining);

				if (count($chunk))
				{
					$items = array_merge($items, $chunk);
					$remaining -= count($chunk);
					assert($remaining >= 0);
				}

				$chunk_start = $last_checked_index + 1;
			}

			$this->results = array_values($this->results);
			$this->cache_results();
		}
		return $items;
	}

	private function confirm_next_chunk($chunk_start, $last_index, $requested_count, $page_start, $remaining)
	{
		$instance = vB_Search_Core::get_instance();
		$actual_count = 0;

		$gids = array();
		$ids = array();

		for ($i = $chunk_start; $actual_count < $requested_count AND $i <= $last_index; $i++)
		{
			list($contenttype, $id, $groupid) = $this->results[$i];

			if (!isset($this->groups_rejected[$contenttype]))
			{
				$this->groups_rejected[$contenttype] = array();
			}

			$type = $instance->get_search_type_from_id($contenttype);
			if ($i > $this->confirmed AND $this->is_duplicate($type, $id, $groupid))
			{
				continue;
			}

			if (in_array($groupid, $this->groups_rejected[$contenttype]))
			{
				continue;
			}
			if (!isset($ids[$contenttype]))
			{
				$ids[$contenttype] = array();
				$gids[$contenttype] = array();
			}

			$ids[$contenttype][] = $id;
			$gids[$contenttype][] = $groupid;

			$actual_count++;
		}

		$last_checked_index = $i - 1;

		foreach ($ids as $contenttype => $typeids)
		{
			$type = vB_Search_Core::get_instance()->get_search_type_from_id($contenttype);
			$result = $type->fetch_validated_list($this->user, $typeids, $gids[$contenttype]);

			if (!isset($result['list']))
			{
				$ids[$contenttype] = $result;
			}
			else
			{
				$ids[$contenttype] = $result['list'];
				if (is_array($result['groups_rejected']))
				{
					$this->groups_rejected[$contenttype] =
						array_merge($this->groups_rejected[$contenttype], $result['groups_rejected']);
				}
			}
		}

		$items = array();
		for ($i = $chunk_start; $i <= $last_checked_index; $i++)
		{
			list($contenttype, $id, $groupid) = $this->results[$i];

			//we trimmed this out in the group logic above, skip it
			if (empty($ids[$contenttype][$id]))
			{
				unset($this->results[$i]);
				continue;
			}

			$type = $instance->get_search_type_from_id($contenttype);
			if ($i > $this->confirmed AND $this->is_duplicate($type, $id, $groupid))
			{
				unset($this->results[$i]);
				continue;
			}

			if ($this->should_group($contenttype, $type))
			{
				//convert the item to the group item
				$ids[$contenttype][$id] =  $ids[$contenttype][$id]->get_group_item();

				//mark the group seen
				$this->groups_seen[$type->get_groupcontenttypeid()][] = $groupid;
			}
			else
			{
				//mark the item seen
				$this->groups_seen[$contenttype][] = $id;
			}

			//add to the confirmed count and, if in the page range, add to the result set.
			if ($i > $this->confirmed)
			{
				$this->confirmed++;
			}

			if (($this->confirmed >= $page_start) and $remaining > 0)
			{
				$items[] = $ids[$contenttype][$id];
				$remaining--;
			}
		}
		return array($items, $last_checked_index);
	}

	private function is_duplicate($type, $id, $groupid)
	{
		$contenttype = $type->get_contenttypeid();
		if ($this->should_group($contenttype, $type))
		{
			$group_contenttype = $type->get_groupcontenttypeid();
			if (
				isset($this->groups_seen[$group_contenttype]) AND
				in_array($groupid, $this->groups_seen[$group_contenttype])
			)
			{
				return true;
			}
		}
		else
		{
			if (
				isset($this->groups_seen[$contenttype]) AND
				in_array($id, $this->groups_seen[$contenttype])
			)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Enter description here...
	 *
	 * @return unknown
	 */
	public function get_searchid()
	{
		return $this->searchid;
	}


	public function get_searchtime()
	{
		return vb_number_format($this->searchtime, 2);
	}

	public function get_dateline()
	{
		return $this->dateline;
	}

	public function get_criteria()
	{
		return $this->criteria;
	}
	public function get_user()
	{
		return $this->user;
	}


	/**
	 * Enter description here...
	 *
	 * @return unknown
	 */
	public function get_confirmed_count()
	{
		return $this->confirmed + 1;
	}

	//todo -- look at moving the resultset to some kind of unified cache.
	/**
	 * Place the results of this query into the cache
	 *
	 */
	private function cache_results()
	{
		global $vbulletin;
		$result_data = serialize(array($this->results, $this->confirmed, $this->groups_seen, $this->groups_rejected));
		$vbulletin->db->query_write($q = "
			UPDATE " . TABLE_PREFIX . "searchlog
			SET results = '" . $vbulletin->db->escape_string($result_data) . "'
			WHERE searchlogid = " . intval($this->searchid));
	}

	private function log_search()
	{
		global $vbulletin;
		$db = $vbulletin->db;

		$fields['userid'] = intval($this->user->get_field('userid'));
		$fields['ipaddress'] =  "'" . $db->escape_string(IPADDRESS) . "'";
		$fields['searchhash'] = "'" . $db->escape_string($this->criteria->get_hash()) . "'";
		$fields['sortby'] = "'" . $db->escape_string($this->criteria->get_sort()) . "'";
		$fields['sortorder'] = "'" . $db->escape_string($this->criteria->get_sort_direction()) . "'";
		$fields['searchtime'] = 0;
		$fields['dateline'] = TIMENOW;
		$fields['completed'] = 0;
		$fields['criteria'] = "'" . $db->escape_string(serialize($this->criteria)) . "'";
		$fields['results'] = "''";

		$db->query_write($q = "
			REPLACE INTO " . TABLE_PREFIX . "searchlog
				(" . implode(',', array_keys($fields)) . ")
			VALUES
				(" . implode(',', $fields) . ")
		");
		return $db->insert_id();
	}

	/**
	 * Log the completion of the search.
	 *
	 * @param int $searchid The id of the search that we just completed
	 * @param int $searchtime search time in seconds
	 */
	private function complete_search($searchtime)
	{
		global $vbulletin;
		$vbulletin->db->query_write("
			UPDATE " . TABLE_PREFIX . "searchlog
			SET searchtime = " . number_format($searchtime, 5, '.', '') . ",
				completed = 1
			WHERE searchlogid = " . intval($this->searchid));
	}


	private function should_group($contenttype, $type)
	{
		$grouped = $this->criteria->get_grouped();
		if ($grouped == vB_Search_Core::GROUP_NO)
		{
			return false;
		}

		if (isset($this->should_group[$contenttype]))
		{
			return $this->should_group[$contenttype];
		}

		if ($grouped == vB_Search_Core::GROUP_YES OR $type->group_by_default())
		{
			$this->should_group[$contenttype] = $type->can_group();
		}
		else
		{
			$this->should_group[$contenttype] = false;
		}

		return $this->should_group[$contenttype];
	}

	//other transient data
	private $user = null;
	private $criteria;
	private $should_group;

	//stored variables
	private $searchid = -1;
	private $searchtime;
	private $dateline;

	private $results = array();
	private $confirmed = -1;
	private $groups_seen;
	private $groups_rejected;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/