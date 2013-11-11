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

require_once(DIR . '/vb/search/searchcontroller.php');
require_once(DIR . '/vb/search/core.php');

/**
 * @package vbdbsearch
 * @author Kevin Sours, vBulletin Development Team
 * @version $Revision: 28678 $
 * @since $Date: 2008-12-03 16:54:12 +0000 (Wed, 03 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 *
 */

/**
 * Return the search results for a mysql db search.
 *
 * In theory this is very simple.  In practice its going to be a pain to follow and
 * understand.
 *
 * The basic idea is to build the query up based on the filters configured in the
 * the criteria object.
 * a) Each filter gets appended to the "where" array.
 *
 * b) If the field to be filtered exists in a table other than searchcore then we can add the
 * join for that table to the "join".  These are named so that if multiple fields add the
 * same join we only keep the one.
 *
 * c) We add the sort in the same way, joining the tables as necesary to get the field into the
 * query to sort on.
 *
 * After all of the filters are processed, we build the query by concatenating the joins and the
 * where array (The assumpton is that all filters in the array are connected by "ands").
 *
 * Pretty basic.
 *
 */
require_once (DIR."/vb/search/core.php");

class vBDBSearch_CoreSearchController extends vB_Search_SearchController
{
	/**
	 *
	 */
	public function get_supported_filters($contenttype)
	{
	}

	/**
	 *
	 */
	public function get_supported_sorts($contenttype)
	{
	}

	/**
	 * Get the results for the requested search
	 *
	 * @param vB_Legacy_Current_User $user user requesting the search
	 * @param vB_Search_Criteria $criteria search criteria to process
	 *
	 * @return array result set.
	 */
	public function get_results($user, $criteria)
	{
		//reset any existing state
		$this->clear();

		global $vbulletin;
		$this->process_keywords_filters($user, $criteria);

		$filters = $criteria->get_equals_filters();

		$advanced_type = null;
		if ($criteria->get_advanced_typeid())
		{
			$advanced_type = vB_Search_Core::get_instance()->get_search_type_from_id($criteria->get_advanced_typeid());
		}

		//contenttype is special
		$types = array();
		if (isset($filters['contenttype']))
		{
			$types = $filters['contenttype'];
			unset($filters['contenttype']);
		}

		//handle equals filters
		$this->process_filters($criteria, $types, $filters, 'make_equals_filter', $advanced_type);

		//handle notequals filters
		$this->process_filters($criteria, $types, $criteria->get_notequals_filters(), 'make_notequals_filter', $advanced_type);

		//handle range filters
		$this->process_filters($criteria, $types, $criteria->get_range_filters(), 'make_range_filter', $advanced_type);

		//an empty array means all types.
		if (count($types) > 0)
		{
			if ($criteria->get_grouped() == vB_Search_Core::GROUP_NO)
			{
				$this->where[] = $this->make_equals_filter('searchcore', 'contenttypeid', $types);
			}
			else
			{
				$this->join['searchgroup'] = sprintf(self::$group_join, TABLE_PREFIX);
				$this->where[] = $this->make_equals_filter('searchgroup', 'contenttypeid', $this->get_groups($types));
			}
		}

		$this->process_sort($types, $criteria, $advanced_type);

		return $this->get_query_results($criteria);
	}

	/**
	*	When we reuse the search controller we need to clear the arrays.
	*/
	public function clear()
	{
		$this->needcore = false;
		$this->rawlimit = "";
		$this->corejoin = array();
		$this->groupjoin = array();
		$this->join = array();
		$this->where = array();
	}

	public function get_similar_threads($threadtitle, $threadid = 0)
	{
		global $vbulletin;

		//we'll leave the join stuff in from the existing hook, though its
		//likely to break any existing code because we've changed the query
		$hook_query_joins = $hook_query_where = '';
		$similarthreads = null;
		($hook = vBulletinHook::fetch_hook('search_similarthreads_fulltext')) ? eval($hook) : false;

		if ($similarthreads !== null)
		{
			return $similarthreads;
		}

		$contenttypeid = vB_Types::instance()->getContentTypeId('vBForum_Thread');

		$safetitle = $vbulletin->db->escape_string($threadtitle);
		$threads = $vbulletin->db->query_read_slave("
			SELECT searchgroup.groupid, MATCH(searchgroup_text.title) AGAINST ('$safetitle') AS score
			FROM " . TABLE_PREFIX . "searchgroup AS searchgroup JOIN " . TABLE_PREFIX . "searchgroup_text AS searchgroup_text ON
				(searchgroup.searchgroupid = searchgroup_text.searchgroupid)
			$hook_query_joins
			WHERE MATCH(searchgroup_text.title) AGAINST ('$safetitle') AND
				searchgroup.contenttypeid = $contenttypeid
				" . ($threadid ? " AND searchgroup.groupid <> $threadid" : "") . "
				$hook_query_where
			HAVING score > 4
			LIMIT 5
		");

		$similarthreads = array();
		while ($thread = $vbulletin->db->fetch_array($threads))
		{
			$similarthreads[] = $thread['groupid'];
		}
		$vbulletin->db->free_result($threads);
		return $similarthreads;
	}

	/**
	 *	Handle processing for the equals / range filters
	 * @param array $types content types -- used to handle default split fields
	 * @param array $filters an array of "searchfields" => values to process
	 * @param array $filter_method string The name of the method to call to create a
	 *		where snippet for this kind of filter (currently equals and range -- not planning
	 *		to add more).  This should be the name of a private method on this class.
	 */
	private function process_filters($criteria, $types, $filters, $filter_method, $advanced_type)
	{
		foreach ($filters as $field => $value)
		{
			//if this is a tag filter we call process_tag_filter
			if ($field == 'tag')
			{
				$this->process_tag_filters($value);
				continue;
			}

			if (isset(self::$field_map[$field]))
			{

				$dbfield = self::$field_map[$field];
				$type = self::$field_type_map[$field];

				//hack for columns in both tables, avoids doing a join when not necesary
				if ($type == 'x')
				{
					if ($criteria->get_grouped() == vB_Search_Core::GROUP_NO)
					{
						$type = 'i';
					}
					else 
					{
						$type = 'g';
					}
				}

				if ($type == 'i' OR $type == 'r')
				{
						$this->needcore = true;
						$this->where[] = $this->$filter_method('searchcore', $dbfield, $value);
				}

				else if ($type == 'g' or $type == 'd')
				{
						$this->join['searchgroup'] = sprintf(self::$group_join, TABLE_PREFIX);
						//$this->join['searchcore_text'] = sprintf(self::$searchcore_text_join, TABLE_PREFIX);
						$this->where[] = $this->$filter_method('searchgroup', $dbfield, $value);
				}
			}

			else if ($advanced_type)
			{
				$info = $advanced_type->get_db_query_info($field);
				if ($info)
				{
					if (isset($info['groupjoin']))
					{
						$this->groupjoin = array_merge($this->groupjoin, $info['groupjoin']);
					}

					if (isset($info['corejoin']))
					{
						$this->corejoin = array_merge($this->corejoin, $info['corejoin']);
					}

					if (isset($info['join']))
					{
						$this->join = array_merge($this->join, $info['join']);
					}
					$this->where[] = $this->$filter_method($info['table'], $info['field'], $value);
				}
			}
		}
	}

	private function make_equals_filter($table, $field, $value)
	{
		global $vbulletin;
		$value = $this->quote_smart($vbulletin->db, $value);
		if (is_array($value))
		{
			return "$table.$field IN (" . implode(',', $value) . ")";
		}
		else
		{
			return "$table.$field = $value";
		}
	}

	private function make_notequals_filter($table, $field, $value)
	{
		global $vbulletin;
		$value = $this->quote_smart($vbulletin->db, $value);
		if (is_array($value))
		{
			return "$table.$field NOT IN (" . implode(',', $value) . ")";
		}
		else
		{
			return "$table.$field <> $value";
		}
	}

	private function make_range_filter($table, $field, $values)
	{
		global $vbulletin;

		//null mean infinity in a given direction
		if (!is_null($values[0]) AND !is_null($values[1]))
		{
			$values = $this->quote_smart($vbulletin->db, $values);
			return "($table.$field BETWEEN $values[0] AND $values[1])";
		}

		else if (!is_null($values[0]))
		{
			$value = $this->quote_smart($vbulletin->db, $values[0]);
			return "$table.$field >= $value";
		}

		else if (!is_null($values[1]))
		{
			$value = $this->quote_smart($vbulletin->db, $values[1]);
			return "$table.$field <= $value";
		}
	}

	/**
	 *	Function to turn a php variable into a database constant
	 *
	 *	Checks the type of the variable and handles accordingly.
	 * numeric types are left unaffected, they don't need special handling.
	 * booleans are converted to 0/1
	 * strings are escaped and quoted
	 * nulls are converted to the string 'null'
	 * arrays are recursively quoted and returned as an array.
	 *
	 *	@param $db object, used for quoting strings
	 * @param $value value to be quoted.
	 */
	protected function quote_smart($db, $value)
	{
		global $vbulletin;
		if (is_string($value))
		{
			return "'" . $vbulletin->db->escape_string($value) . "'";
		}

		//numeric types are safe.
		else if (is_int($value) OR is_float($value))
		{
			return $value;
		}

		else if (is_null($value))
		{
			return 'null';
		}

		else if (is_bool($value))
		{
			return $value ?  1 : 0;
		}

		else if (is_array($value))
		{
			foreach ($value as $key => $item)
			{
				$value[$key] = $this->quote_smart($db, $item);
			}
			return $value;
		}

		//unhandled type
		//this is likely to cause as sql error and unlikely to cause db corruption
		//might be better to throw an exception.
		else
		{
			return false;
		}
	}

	/**
	 * Process the filters for the query string
	 *
	 * @param vB_Legacy_Current_User $user user requesting the search
	 * @param vB_Search_Criteria $criteria search criteria to process
	 */
	protected function process_keywords_filters($user, $criteria)
	{
		$search_text = $this->get_search_text($user, $criteria);
		if (!$search_text)
		{
			return;
		}

		$db = $GLOBALS['vbulletin']->db;
		$q_search_text = $q_search_text_NOBOOL = "'" . $db->escape_string($search_text) . "'";

		if ($user->hasPermission('genericpermissions', 'cansearchft_bool'))
		{
			$q_search_text .= ' IN BOOLEAN MODE';
		}
		else
		{
			//if in natural language search mode limit the presorted search results
			//this is a hack for speed at the expense of search quality and follows the
			//3.x search logic
			$this->rawlimit = "LIMIT " . $GLOBALS['vbulletin']->options['maxresults'];
		}

		//match title/keywords in searchcore
		if ($criteria->is_title_only())
		{
			$this->join['searchgroup'] = sprintf(self::$group_join, TABLE_PREFIX);
			$this->join['searchgroup_text'] = sprintf(self::$searchgroup_text_join, TABLE_PREFIX);

			$this->where[] = "MATCH(searchgroup_text.title) AGAINST ($q_search_text)";
			// we don't want boolean mode for getting score column, 
			// because we get a more precise relevancy rating
			$this->ranksort = "MATCH(searchgroup_text.title) AGAINST ($q_search_text_NOBOOL) AS score";
		}
		else
		{
			$this->needcore = true;
			$this->join['searchcore_text'] = sprintf(self::$searchcore_text_join, TABLE_PREFIX);
			$this->where[] = "MATCH(searchcore_text.title, searchcore_text.keywordtext) AGAINST ($q_search_text)";
			// we don't want boolean mode for getting score column, 
			// because we get a more precise relevancy rating
			$this->ranksort = "MATCH(searchcore_text.title, searchcore_text.keywordtext) AGAINST ($q_search_text_NOBOOL) AS score";
		}
	}

	/**
	 * Get the search query string in the mysql full text format
	 *
	 * Built to produce the same search strings as the search.php file.
	 * The natural language hack is from search.php
	 *
	 * @param vB_Legacy_Current_User $user user requesting the search
	 * @param vB_Search_Criteria $criteria search criteria to process
	 */
	protected function get_search_text($user, $criteria)
	{
		//	If the user doesn't have permission to search full text boolean mode,
		// use natural language mode.
		if ($user->hasPermission('genericpermissions', 'cansearchft_bool'))
		{
			$words = $criteria->get_keywords();
			$search_text = "";

			$word_count = 0;
			foreach ($words AS $word_item)
			{
				$word_count++;

				//The value of the first term is ambiguous.  If the second term is
				//an "or" both the the first and second terms should be treated as
				//ors.  If the second term is an "and" or "not" then the first term
				//should be an and.
				if ($word_count == 2 AND $word_item['joiner'] != 'OR')
				{
					$search_text = "+$search_text";
				}

				$word = $word_item['word'];
				switch ($word_item['joiner'])
				{
					case 'OR':
						// OR is no operator
						$search_text .= " $word";
						break;

					case 'NOT':
						$search_text .= " -$word";
						break;

					case 'AND':
					// if we didn't have a joiner, default to and
					default:
						if ($search_text)
						{
							$search_text .= " +$word";
						}
						else
						{
							//if this is the first token added, then we don't want to assume any
							//join logic. We need to figure that out on the second term.
							$search_text = $word;
						}
						break;
				}
			}
			//not 100% sure about this, but it matches the results in search.php
			$search_text = str_replace('"', '\"', trim($search_text));
		}
		else
		{
			$search_text = $criteria->get_raw_keywords();
			//if we are using the raw search text, use the whole string as the display text
			$criteria->set_keyword_display_string('<b><u>' . htmlspecialchars_uni($search_text) . '</u></b>');
			$criteria->set_highlights(array($search_text));
		}

		return $search_text;
	}


	/**
	 * The word build up is taken from the socialgroup/blog implementation
	 * The natural language hack is from search.php
	 * This follows the newer socialgroup/blog search method of constructing the
	 * BOOLEAN mode search string.  It's more sophisticated, but its not clear
	 * if it performs as well or that its any better than the old way of doing things.
	 */
	protected function get_search_text_old($user, $criteria)
	{
		//	If the user doesn't have permission to search full text boolean mode,
		// use natural language mode.
		if ($user->hasPermission('genericpermissions', 'cansearchft_bool'))
		{
			$words = $criteria->get_keywords();
			$search_text = "";

			foreach ($words AS $word_item)
			{
				$word = $word_item['word'];
				switch ($word_item['joiner'])
				{
					case 'OR':
						// OR is no operator
						$search_text .= " $word";
						break;

					case 'NOT':
						// NOT this, but everything before it
						if ($search_text)
						{
							$search_text = "($search_text) ";
						}
						$search_text .= "-$word";
						break;

					case 'AND':
						// if we didn't have a joiner, default to and
					default:
						if ($search_text)
						{
							$search_text = "+($search_text) +$word";
						}
						else
						{
							//if this is the first token added, then we don't want to assume any
							//join logic.
							$search_text = $word;
						}
						break;
				}
			}
		}
		else
		{
			$search_text = $criteria->get_raw_keywords();
			//if we are using the raw search text, use the whole string as the display text
			$criteria->set_keyword_display_string("<b><u>$search_text</u></b>");
			$criteria->set_highlights(array($search_text));
		}

		return $search_text;
	}

	/**
	 *	Process the filters for the requested tag
	 *
	 *	This processing makes the assumption that if the type is groupable the tags
	 *	will apply only to the group
	 *
	 *	@param int $tagid the id of the tag to filter on.
	 */
	protected function process_tag_filters($tagid)
	{
		$this->corejoin['tag'] = "JOIN " . TABLE_PREFIX . "tagcontent AS tagcontent ON
			(searchcore.groupcontenttypeid = tagcontent.contenttypeid AND searchcore.groupid = tagcontent.contentid)";

		$this->groupjoin['tag'] = "JOIN " . TABLE_PREFIX . "tagcontent AS tagcontent ON
			(searchgroup.contenttypeid = tagcontent.contenttypeid AND searchgroup.groupid = tagcontent.contentid)";

		$this->where[] = $this->make_equals_filter('tagcontent', 'tagid', $tagid);
	}

	protected function process_sort($types, $criteria, $advanced_type)
	{
		$sort = $criteria->get_sort();
		$direction = strtolower($criteria->get_sort_direction()) == 'desc' ? 'desc' : 'asc';

		$sort_map = array
		(
			'user' => 'username',
			'dateline' => 'dateline',

			'groupuser' => 'username',
			'groupdateline' => 'dateline',

			'defaultdateline' => 'dateline',
			'defaultuser' => 'username',

			'title'  => 'title',			

			'rank'  => 'score',
			'relevance'  => 'score',
		);

		$sort_type_map = array
		(
			'user' => 'i',
			'dateline' => 'i',

			'rank'  => 'r',
			'relevance'  => 'r',

			'groupuser' => 'g',
			'groupdateline' => 'g',

			'defaultdateline' => 'd',
			'defaultuser' => 'd',

			'title'  => 'g',
		);
		
		// if we don't have a sort, or we have an unrecognized sort type
		// without an advanced type, default to dateline descending
		if (!$sort OR (!isset($sort_map[$sort]) AND !$advanced_type))
		{
			$sort = 'dateline';
			$direction = 'desc';
		}

		//look for a core sort option
		if (isset($sort_map[$sort]))
		{
			$sort_field = $sort_map[$sort];
			$field_type = $sort_type_map[$sort];

			if ($field_type == 'i')
			{
				$this->needcore = true;
				// If we are sorting by title, then we need to use the searchcore_text table since the fulltext indices exist there
				/*
				if($sort_field == 'title')
				{
					$this->sort = "searchcore_text.$sort_field";
				}
				else
				{
					$this->sort = "searchcore.$sort_field";
				}
				*/
				$this->sort = "searchcore.$sort_field";
			}

			else if ($field_type == 'g' OR $field_type == 'd')
			{
				//if we are sorting on a group field then we implicitly filter any items
				//without groups
				$this->join['searchgroup'] = sprintf(self::$group_join, TABLE_PREFIX);
				// If we are sorting by title, then we need to use the searchgroup_text table since the fulltext indices exist there
				if($sort_field == 'title')
				{
					$this->join['searchgroup_text'] = sprintf(self::$searchgroup_text_join, TABLE_PREFIX);
					$this->sort = "searchgroup_text.$sort_field";
				}
				else
				{
					$this->sort = "searchgroup.$sort_field";
				}

			}

			// process rank sortings
			else if ($field_type == 'r')
			{
				$this->needcore = true;
				$this->sort = $this->ranksort;
			}

			$this->direction = $direction;
		}

		//if we don't recognize the sort, check for an advanced type
		else if ($advanced_type)
		{
			$info = $advanced_type->get_db_query_info($sort);
			if ($info)
			{
				if (isset($info['groupjoin']))
				{
					$this->groupjoin = array_merge($this->groupjoin, $info['groupjoin']);
				}

				if (isset($info['corejoin']))
				{
					$this->corejoin = array_merge($this->corejoin, $info['corejoin']);
				}

				if (isset($info['join']))
				{
					$this->join = array_merge($this->join, $info['join']);
				}

				$this->sort = "$info[table].$info[field]";
				$this->direction = $direction;
			}
		}
	}

	protected function get_query_results($criteria)
	{
		global $vbulletin;
		$query = $this->get_query($criteria);
		$query .= " " . $this->rawlimit;
		$set = $vbulletin->db->query_read_slave($query);

		$results = array();
		while ($row = $vbulletin->db->fetch_row($set))
		{
			$results[$row[0]] = $row[1];
		}
		$vbulletin->db->free_result($set);

		if(!$results)
		{
			return array();
		}

		//pulling down the entire result list and sorting inexplicibly shows better
		//concurrent performance than sorting and doing the limit on the main query
		//of course that causes problems with the memory size of the webserver process
		//but there is only so much one can do.
		//we only pull the internal key and do a second lookup for the actual resultset
		//values to mitigate the memory usage.  PHP memory usage for arrays of arrays
		//gets brutal.

		if ($this->direction == 'asc')
		{
			asort($results);
		}
		else
		{
			$test = arsort($results);
		}

		if ($vbulletin->options['maxresults'] > 0 AND count($results) > $vbulletin->options['maxresults'])
		{
			//array_splice doesn't maintain key associations correctly so we'll use array_slice
			$results = array_slice($results, 0, $vbulletin->options['maxresults'], true);
		}

		//actually get the data.
		if ($criteria->get_grouped() == vB_Search_Core::GROUP_NO)
		{
			$id_index = 3;
			$query = "SELECT searchcore.contenttypeid, searchcore.primaryid, searchcore.groupid, searchcore.searchcoreid
				FROM " . TABLE_PREFIX . "searchcore AS searchcore
				WHERE searchcore.searchcoreid IN (%s)";
		}
		else
		{
			$id_index = 2;
			$query = "SELECT searchgroup.contenttypeid, searchgroup.groupid, searchgroup.searchgroupid
				FROM " . TABLE_PREFIX . "searchgroup AS searchgroup
				WHERE searchgroup.searchgroupid IN (%s)";
		}

		$query = sprintf($query, implode(",", array_keys($results)));
		$set = $vbulletin->db->query_read_slave($query);
		unset($query);
		while ($row = $vbulletin->db->fetch_row($set))
		{
			$results[$row[$id_index]] = $row;
			unset($results[$row[$id_index]][$id_index]);
		}
		$vbulletin->db->free_result($set);

		return array_values($results);
	}

/*
	//this is the position of the sort field.
	protected function compare_results($a, $b)
	{
		//1 is the ordinal for the sort column
		if ($a[2] == $b[2])
		{
			return 0;
		}
		return ($a[2] < $b[2]) ? -1 : 1;
	}
*/

	protected function get_query($criteria)
	{
		global $vbulletin;

		if (($criteria->get_grouped() == vB_Search_Core::GROUP_NO) OR $this->needcore)
		{
			//unset($this->join['searchgroup_text']);
			if ($criteria->get_grouped() == vB_Search_Core::GROUP_NO)
			{
				$fields = "searchcore.searchcoreid" . ($this->sort ? ', ' . $this->sort : '' );
			}
			else
			{
				$fields = "searchcore.searchgroupid" . ($this->sort ? ', ' . $this->sort : '' );
			}

			$query = "
				SELECT $fields
				FROM " . TABLE_PREFIX . "searchcore AS searchcore
			";

			//need to be in this order to keep the tables the right way.
			$this->join = array_merge($this->corejoin, $this->join);
		}
		else
		{

			//if we don't need the core table, use the group table directly.
			//this will dramatically improve our efficiency.
			unset($this->join["searchgroup"]);
			unset($this->join['searchcore_text']);
			unset($this->groupjoin['searchgroup']);
			$fields = "searchgroup.searchgroupid " . ($this->sort ? ', ' . $this->sort : '' );
			$query = "
				SELECT $fields
				FROM " . TABLE_PREFIX . "searchgroup AS searchgroup
			";

			//need to be in this order to keep the tables the right way.
			$this->join = array_merge($this->groupjoin, $this->join);
		}

		if (count($this->join))
		{
			$query .= implode("\n", $this->join) . "\n";
		}

		if (count($this->where))
		{
			$query .= "\nWHERE " . implode(" AND ", $this->where);
		}
		//print $query;die;
		return $query;
	}

	private function get_groups($types)
	{
		//no types filters
		if (!$types)
		{
			return array();
		}

		//sort types into group by default/item by default buckets
		$search  = vB_Search_Core::get_instance();

		$group_types = array();
		$item_types = array();
		foreach ($types as $typeid)
		{
			$type = $search->get_search_type_from_id($typeid);
			if ($type->can_group())
			{
				$group_types[] = $type->get_groupcontenttypeid();
			}
			else
			{
				$group_types[] = $typeid;
			}
		}

		return $group_types;
	}

	protected $needcore = false;
	protected $rawlimit = "";

	protected $corejoin = array();
	protected $groupjoin = array();
	protected $join = array();
	protected $where = array();

	protected $sort = "";
	protected $ranksort = "";
	protected $direction = "";

	private static $group_join =
		"JOIN %ssearchgroup AS searchgroup ON (searchgroup.searchgroupid = searchcore.searchgroupid)";
	private static $searchcore_text_join =
		"JOIN %ssearchcore_text AS searchcore_text ON (searchcore_text.searchcoreid = searchcore.searchcoreid)";
	private static $searchgroup_text_join =
		"JOIN %ssearchgroup_text AS searchgroup_text ON (searchgroup_text.searchgroupid = searchgroup.searchgroupid)";

	private static $field_map = array
	(
		'user' => 'userid',
		'dateline' => 'dateline',
		'groupid' => 'groupid',
		'threadid' => 'groupid',
		'contenttypeid' => 'contenttypeid',

		'defaultdateline' => 'dateline',
		'groupdateline' => 'dateline',

		'defaultuser' => 'userid',
		'groupuser'   => 'userid',

		'rank'  => 'score',
		'relevance'  => 'score'
	);

	private static $field_type_map = array
	(
		'groupid' => 'x',
		
		'user' => 'i',
		'dateline' => 'i',
		'threadid' => 'i',
		'contenttypeid' => 'i',

		'defaultdateline' => 'd',
		'defaultuser' => 'd',

		'rank'  => 'r',
		'relevance'  => 'r',

		'groupdateline' => 'g',
		'groupuser'   => 'g'
	);
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/
