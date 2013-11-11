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
 * @package vbForum
 * @subpackage Search
 * @author Ed Brown, vBulletin Development Team
 * @version $Revision: 29554 $
 * @since $Date: 2009-02-17 16:54:12 +0000 (Wed, 03 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */



require_once(DIR . '/vb/search/itemindexer.php');

/**
 * @package vbdbsearch
 * @author Kevin Sours, vBulletin Development Team
 * @version $Revision: 28678 $
 * @since $Date: 2008-12-03 16:54:12 +0000 (Wed, 03 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */

/**
 * Core indexer.
 *
 * Do we need to subclass this by content type?  Chances are if we are doing
 * anything complex enough to warrant that, we'll be subclassing the index controllers
 * and we should be putting type specific logic there (potentially bypassing this
 * class entirely in the implementation.
 */
class vBDBSearch_Indexer extends vB_Search_ItemIndexer
{


	/**
	 * Enter description here...
	 *
	 * @param unknown_type $fields
	 *   this is an array of field-value pairs. We need to have at least
	 *   primaryid and contenttype. The id may come as primaryid or id.
	 */

	//let's store some information that lets us go from contentid to type:

	public function index($fields)
	{
		global $vbulletin;
		$db = $vbulletin->db;

		$string_fields = array('username');
		$int_fields = array('contenttypeid', 'primaryid', 'groupcontenttypeid', 'groupid', 'ipaddress', 'userid',
			'dateline', 'searchgroupid');
		$keyword_fields = array('title', 'keywordtext');
		$dbfields = array();

		if (array_key_exists('id',$fields) AND !(array_key_exists('primaryid',$fields)))
		{
			$fields['primaryid']= $fields['id'];
			unset($fields['id']);
		}

		if (!array_key_exists('primaryid', $fields) OR !array_key_exists('contenttypeid', $fields))
		{
			return false;
		}

		if (empty($fields['contenttypeid']) OR empty($fields['primaryid']))
		{
			return false;
		}

		// If the item cannot be grouped, add a dummy group. Essentially, for a non grouped type the group is a copy of the item.

		if(!$fields['groupcontenttypeid'] AND !$fields['groupid'])
		{
			$fields = $this->create_dummy_group($fields);

		}

		$fields['searchgroupid'] = $this->save_group_data($fields);

		foreach ($string_fields as $key)
		{
			if (isset($fields[$key]))
			{
				$dbfields[$key] =  "'" . $vbulletin->db->escape_string($fields[$key]) . "'";
			}
		}

		foreach ($int_fields as $key)
		{
			if (isset($fields[$key]))
			{
				$dbfields[$key] = intval($fields[$key]);
			}
		}
		$db->query_write($this->make_save_query('searchcore', $dbfields));

		// $db->insert_id() does not seem to be working for ON DUPLICATE KEY UPDATE queries when a row is updated, so need the lookup
		$searchcore = $db->query_first("SELECT searchcoreid FROM " . TABLE_PREFIX . "searchcore WHERE contenttypeid = " . $dbfields['contenttypeid'] . " AND primaryid = " . $dbfields['primaryid']);

		$searchcoreid = $searchcore['searchcoreid'];
		$searchcoreid = intval($searchcoreid);
		/*
		 * We decided to move the full text fields in the searchcore table to a table called searchcore_text
		 */
		if($searchcoreid)
		{
			unset($dbfields);

			$dbfields['searchcoreid'] = $searchcoreid;
			foreach ($keyword_fields as $key)
			{
				if (isset($fields[$key]))
				{
					$dbfields[$key] =  "'" . $vbulletin->db->escape_string($this->filter_keywords($fields[$key]))  . "'";
				}
			}
			$db->query_write($this->make_save_query('searchcore_text', $dbfields));
		}

		//efficency hack.
		//Store the group fileds separately to avoid having to reindex multiple items
		//when the group data changes.
		//
		//The title is also imporant for searching.  The original implementation naively used
		//the thread title for the post title.  With so many indentical values in the full text
		//index performance proved to be poor.  Spliting the text into



	}

	private function create_dummy_group($fields)
	{
		$fields['groupcontenttypeid'] = $fields['contenttypeid'];
		$fields['groupid'] = $fields['primaryid'];
		$fields['groupuserid'] = $fields['userid'];
		$fields['groupusername'] = $fields['username'];
		$fields['grouptitle'] = $fields['title'];
		$fields['groupdateline'] = $fields['dateline'];

		return $fields;
	}

	private function save_group_data($fields)
	{
		global $vbulletin;
		$db = $vbulletin->db;

		$group_int_fields = array (
			'groupcontenttypeid' => 'contenttypeid',
			'groupid' => 'groupid',
			'groupuserid' => 'userid',
			'groupdateline' => 'dateline',
		);

		$group_string_fields = array (
			'groupusername' => 'username',
		);

		$group_keyword_fields = array (
			'grouptitle' => 'title'
		);

		$dbfields = array();
		foreach ($group_string_fields as $key => $dbkey)
		{
			if (isset($fields[$key]))
			{
				$dbfields[$dbkey] =  "'" . $db->escape_string($fields[$key]) . "'";
			}
		}

		foreach ($group_int_fields as $key => $dbkey)
		{
			if (isset($fields[$key]))
			{
				$dbfields[$dbkey] = intval($fields[$key]);
			}
		}



		if (count($dbfields) AND isset($fields['groupid']) AND $fields['groupid'])
		{
			$db->query_write($this->make_save_query('searchgroup', $dbfields));

			// $db->insert_id() does not seem to be working for ON DUPLICATE KEY UPDATE queries when a row is updated, so need the lookup
			$searchgroup = $db->query_first("SELECT searchgroupid FROM " . TABLE_PREFIX . "searchgroup WHERE contenttypeid = " . $dbfields['contenttypeid'] . " AND groupid = " . $dbfields['groupid']);
			$searchgroupid = $searchgroup['searchgroupid'];
			$searchgroupid = intval($searchgroupid);
			/*
			 * We decided to move the full text fields in the searchgroup table to a table called searchgroup_text
			 */
			if($searchgroupid)
			{
				unset($dbfields);

				$dbfields['searchgroupid'] = $searchgroupid;
				foreach ($group_keyword_fields as $key => $dbkey)
				{
					if (isset($fields[$key]))
					{
						$dbfields[$dbkey] =  "'" . $db->escape_string($this->filter_keywords($fields[$key]))  . "'";
					}
				}
				$db->query_write($this->make_save_query('searchgroup_text', $dbfields));
				return $searchgroupid;
			}

		}
	}

	private function make_save_query($table, $dbfields)
	{
		//There could be a substantial performance penalty for using
		// REPLACE INTO. Therefore we will use a non-standard
		// MySQL enhancement as shown.
		$sql = "INSERT INTO " . TABLE_PREFIX . "$table (" . implode(', ', array_keys($dbfields)) . ")
			VALUES ( " . implode(', ', $dbfields) . " )
			ON DUPLICATE KEY UPDATE ";
		$first = true;

		foreach ($dbfields as $key => $value)
		{
			if ($first)
			{
				$sql .= " $key = VALUES($key)";
				$first = false;
			}
			else
			{
				$sql .= ", $key = VALUES($key)";
			}
		}

		return $sql;
	}


	/**
	 * Delete the record from the index
	 *
	 * If its the last record in its group, then nuke the group record too.
	 *
	 * @param unknown_type $contenttype
	 * @param unknown_type $id
	 */
	public function delete($contenttypeid, $id)
	{
		$safe_contenttypeid = intval($contenttypeid);

		global $vbulletin;
		//look up the groupid, we'll need it later
		$existing = $vbulletin->db->query_first("
			SELECT searchcore.groupcontenttypeid, searchcore.groupid
			FROM " . TABLE_PREFIX . "searchcore AS searchcore
			WHERE contenttypeid = $safe_contenttypeid AND
				primaryid = " . intval($id)
		);

		if ($existing)
		{
			$groupcontenttypeid = $existing['groupcontenttypeid'];
			$groupid = $existing['groupid'];

			//nuke the record
			$vbulletin->db->query_write("
				DELETE " . TABLE_PREFIX . "searchcore, " . TABLE_PREFIX . "searchcore_text
				FROM " . TABLE_PREFIX . "searchcore
				LEFT JOIN " . TABLE_PREFIX . "searchcore_text ON (" . TABLE_PREFIX . "searchcore_text.searchcoreid = " . TABLE_PREFIX . "searchcore.searchcoreid)
				WHERE
					" . TABLE_PREFIX . "searchcore.contenttypeid = $safe_contenttypeid AND
					" . TABLE_PREFIX . "searchcore.primaryid = " . intval($id) . "
			");


			//if we don't have a group, then skip the group delete
			if ($groupcontenttypeid)
			{
				//nuke the group record if its the last one
				//can't use table alias for deletes so we get to fully qualify the table
				//name each time.
				$vbulletin->db->query_write("
					DELETE " . TABLE_PREFIX . "searchgroup, " . TABLE_PREFIX . "searchgroup_text
					FROM " . TABLE_PREFIX . "searchgroup
					LEFT JOIN " . TABLE_PREFIX . "searchgroup_text ON (" . TABLE_PREFIX . "searchgroup_text.searchgroupid = " . TABLE_PREFIX . "searchgroup.searchgroupid)
					WHERE
						" . TABLE_PREFIX . "searchgroup.contenttypeid = $groupcontenttypeid AND
						" . TABLE_PREFIX . "searchgroup.groupid = $groupid AND
						NOT EXISTS (
							SELECT 1
							FROM " . TABLE_PREFIX . "searchcore AS searchcore
							WHERE
								searchcore.groupcontenttypeid = " . TABLE_PREFIX . "searchgroup.contenttypeid AND
								searchcore.groupid = " . TABLE_PREFIX . "searchgroup.groupid
						)
				");
			}
		}
	}

	public function empty_index()
	{
		//nuke everything.
		global $vbulletin;
		$vbulletin->db->query_write("TRUNCATE TABLE " . TABLE_PREFIX . "searchcore");
		$vbulletin->db->query_write("TRUNCATE TABLE " . TABLE_PREFIX . "searchcore_text");
		$vbulletin->db->query_write("TRUNCATE TABLE " . TABLE_PREFIX . "searchgroup");
		$vbulletin->db->query_write("TRUNCATE TABLE " . TABLE_PREFIX . "searchgroup_text");
		$vbulletin->db->query_write("TRUNCATE TABLE " . TABLE_PREFIX . "indexqueue");
	}

	public function merge_group($groupcontenttypeid, $oldid, $newid)
	{
		global $vbulletin;

		$max_group_size = 1000;
		$chunk_size = 500;

		$safe_groupcontenttypeid = intval($groupcontenttypeid);
		$safe_oldid = intval($oldid);
		$safe_newid = intval($newid);

		$count = $vbulletin->db->query_first ("
			SELECT count(*) as count
			FROM " . TABLE_PREFIX . "searchcore
			WHERE groupcontenttypeid = $safe_groupcontenttypeid AND
				groupid = $safe_oldid
		");
		$count = $count['count'];

		//for small groups, update in place
		if ($count < $max_group_size)
		{
			$vbulletin->db->query_write ("
				UPDATE " . TABLE_PREFIX . "searchcore
				SET groupid = $safe_newid
				WHERE groupcontenttypeid = $safe_groupcontenttypeid AND groupid = $safe_oldid
			");
		}

		//for large groups we want to break the query up.
		//this isn't as efficient, but it will (hopefully) cause fewer locking
		//problems.
		else
		{
			$set = $vbulletin->db->query("
				SELECT contenttypeid, primaryid
				FROM " . TABLE_PREFIX . "searchcore
				WHERE groupcontenttypeid = $safe_groupcontenttypeid AND
					groupid = $safe_oldid
			");

			$ids = array();
			$count=0;
			while ($row = $vbulletin->db->fetch_array($set))
			{
				$ids[$row['contenttypeid']][] = $row['primaryid'];
				$count++;

				//handle any partial chunk size.
				if ($count == $chunk_size)
				{
					$this->update_group_chunk($ids, $safe_oldid, $safe_newid);
					$ids = array();
					$count=0;
				}
			}

			//handle any partial chunks at the end.
			if ($count != 0)
			{
				$this->update_group_chunk($ids, $safe_oldid, $safe_newid);
				$ids = array();
				$count=0;
			}
		}

		//remove the now empty group record.
		$vbulletin->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "searchgroup
			WHERE contenttypeid = $groupcontenttypeid AND
				groupid = $safe_oldid"
		);
	}

	public function group_data_change($groupdata)
	{
		$this->save_group_data($groupdata);
	}

	private function update_group_chunk($itemids, $safe_oldid, $safe_newid)
	{
		global $vbulletin;

		$type_group = array();
		foreach ($itemids as $typeid => $ids)
		{
			//This is equivilent to (contenttypeid=$typeid AND primaryid=$id1) OR
			//(contenttypeid=$typeid AND primaryid=$id2) OR ... since each part uses the
			//same index this ends up being an indexable query
			$type_group[] = "(contenttypeid = $typeid AND primaryid IN (" . implode(',', $ids) . "))";
		}

		//note that mysql is smart enough to use the index even for this relatively complex OR
		//query because each part of the OR uses the same index
		$vbulletin->db->query_write ("
			UPDATE " . TABLE_PREFIX . "searchcore
			SET groupid = $safe_newid
			WHERE " . implode(" OR ", $type_group)
		);
	}

	public function delete_group($groupcontenttypeid, $groupid)
	{
		global $vbulletin;

		$safe_groupcontenttypeid = intval($groupcontenttypeid);
		$safe_groupid = intval($groupid);

		$vbulletin->db->query_write("
		 	DELETE searchcore, searchcore_text
			FROM " . TABLE_PREFIX ."searchcore AS searchcore JOIN
				" . TABLE_PREFIX ."searchcore_text AS searchcore_text ON
					searchcore.searchcoreid = searchcore_text.searchcoreid
			WHERE searchcore.groupcontenttypeid = $safe_groupcontenttypeid AND
				searchcore.groupid = $safe_groupid"
		);

		$vbulletin->db->query_write("
		 	DELETE searchgroup, searchgroup_text
			FROM " . TABLE_PREFIX ."searchgroup AS searchgroup JOIN
				" . TABLE_PREFIX ."searchgroup_text AS searchgroup_text ON
					searchgroup.searchgroupid = searchgroup_text.searchgroupid
			WHERE searchgroup.contenttypeid = $safe_groupcontenttypeid AND
				searchgroup.groupid = $safe_groupid"
		);
	}

	/**
	 * Do any necessary manipulation of the keyword text
	 *
	 * do any keywords related filtering -- such as handling non whitespace
	 * tokenization or other manipulation
	 *
	 * Primarily intended for subclasses for odd language sets
	 *
	 * @param string $text
	 * @return string
	 */
	protected function filter_keywords($text)
	{
		return $text;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/