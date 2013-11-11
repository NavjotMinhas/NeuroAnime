<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin Project Tools 4.1.5 Patch Level 1 - Licence Number VBF1F15E74
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2011 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

define('SG_SEARCHGEN_CRITERIA_ADDED', 1);
define('SG_SEARCHGEN_CRITERIA_FAILED', 2);
define('SG_SEARCHGEN_CRITERIA_UNNECESSARY', 3);

/**
* Performs Social Group Searches
*
* @package 		vBulletin
* @copyright 	http://www.vbulletin.com/license.html
*/
class vB_SGSearch
{
	/**
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* Object that will be used to generate the search query
	*
	* @var	vB_SGSearchGenerator
	*/
	var $generator = null;

	/**
	* The effective sort method, to be used in the query. Needs a table name.
	*
	* @var	string
	*/
	var $sort = 'socialgroup.lastpost';

	/**
	* The raw sort method that was passed in. Used to save the sort method for later use
	*
	* @var	string
	*/
	var $sort_raw = 'lastpost';

	/**
	* The effective sort order that is to be used
	*
	* @var	string
	*/
	var $sortorder = 'desc';

	/**
	* The raw sort order that was passed in
	*
	* @var	string
	*/
	var $sortorder_raw = 'desc';

	/**
	 * Sort Criteria
	 *
	 * @var string
	 */
	var $sort_criteria = 'socialgroup.lastpost desc, socialgroup.dateline desc';

	/**
	* Raw criteria searched for.
	*
	* @var	array	Key: criteria name, value: criteria filter
	*/
	var $criteria_raw = array();

	/**
	* Search result from an executed query without a limit
	*
	* @var	Resource
	*/
	var $search_result = null;

	/**
	 * Whether to get read details.
	 *
	 * @var bool
	 */
	var $check_read = true;

	/**
	 * Whether to get subscribed details
	 *
	 * @var bool
	 */
	var $check_subscribed = false;

	/**
	* Constructor.
	*
	* @param	vB_Registry
	*/
	function vB_SGSearch(&$registry)
	{
		$this->registry =& $registry;
		$this->generator = new vB_SGSearchGenerator($registry);
	}

	/**
	* Adds a search criteria
	*
	* @param	string	Name of criteria
	* @param	mixed	How to restrict the criteria
	*
	* @return	boolean	True on success
	*/
	function add($name, $value)
	{
		$raw = $value;
		$genval = $this->generator->add($name, $value);
		if ($genval == SG_SEARCHGEN_CRITERIA_ADDED)
		{
			$this->criteria_raw["$name"] = $raw;
			return true;
		}
		else if ($genval == SG_SEARCHGEN_CRITERIA_UNNECESSARY)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	* Adds a limit to the search criteria
	*
	* @param	integer	Offset for the LIMIT clause
	* @param	integer	Limit for the LIMIT clause
	*/
	function limit($limitoffset, $limit)
	{
		$this->generator->limit($limitoffset, $limit);
	}

	/**
	* Do we have criteria that we're searching on? Necessary to do a search
	*
	* @return	bool
	*/
	function has_criteria()
	{
		return (sizeof($this->criteria_raw) > 0);
	}

	/**
	* Set the sorting method. Looked up in generator's sort array
	*
	* @param	string	Type of sorting to do
	* @param	string	Direction of sorting (asc/desc)
	*/
	function set_sort($sort, $sortorder)
	{
		if ($this->generator->verify_sort($sort, $sortorder, $sort_raw, $sortorder_raw, $sort_criteria))
		{
			$this->sort = $sort;
			$this->sort_raw = $sort_raw;
			$this->sortorder = $sortorder;
			$this->sortorder_raw = $sortorder_raw;
			$this->sort_criteria = $sort_criteria;
		}
	}

	/**
	 * Whether to check readmarking
	 *
	 * @access public
	 *
	 * @param	boolean
	 */
	function check_read($check)
	{
		$this->check_read = $check;
	}

	/**
	* Determines whether the current search has errors
	*
	* @return	boolean
	*/
	function has_errors()
	{
		return $this->generator->has_errors();
	}

	/**
	* Executes the current search.
	*
	* @param	boolean			Skip results
	*
	* @return	false|integer	False on failure to execute, integer with number of rows
	*/
	function execute($count_only)
	{
		if ($this->has_errors())
		{
			return false;
		}

		$count = $this->perform_search($count_only);


		if (!$count)
		{
			$this->generator->error('searchnoresults', '');
			return false;
		}

		if ($count_only)
		{
			return $count;
		}

		/* we only get this far if there was a result */
		$ids = $this->fetch_results();

		return $ids;
	}

	/**
	* Performs the actual search
	*
	* @param	string	Search permissions in query form
	*
	* @return	array	Array of matched IDs
	*/
	function perform_search($count_only = false)
	{
		$db =& $this->registry->db;

		if ($this->registry->userinfo['userid']
			AND empty($this->generator->joins['left_socialgroupmember'])
			AND empty($this->generator->joins['inner_socialgroupmember'])
		)
		{
			$this->generator->joins['left_socialgroupmember'] = trim("
				LEFT JOIN " . TABLE_PREFIX . "socialgroupmember AS socialgroupmember ON
					(socialgroupmember.userid = " . $this->registry->userinfo['userid'] . " AND socialgroupmember.groupid = socialgroup.groupid)
			");
		}

		$criteria = $this->generator->generate();

		if (!$criteria['where'])
		{
			$criteria['where'] = '1=1';
		}

		($hook = vBulletinHook::fetch_hook('group_search_perform')) ? eval($hook) : false;

		if ($count_only)
		{
			$search_result = $db->query_first_slave("
				SELECT COUNT(*) AS count
				FROM " . TABLE_PREFIX . "socialgroup AS socialgroup
				" . $criteria['joins'] . "
				WHERE " . $criteria['where'] . "
			");

			return $search_result['count'];
		}
		else
		{
			$this->search_result = $db->query_read_slave("
				SELECT socialgroup.*, socialgroup.dateline AS createdate, user.username AS creatorusername
					" . ($this->registry->userinfo['userid'] ? ', socialgroupmember.type AS membertype': '') . ",
					sgc.title AS categoryname, sgc.socialgroupcategoryid AS categoryid,
					socialgroupicon.dateline AS icondateline, socialgroupicon.thumbnail_width AS iconthumb_width,
					socialgroupicon.thumbnail_height AS iconthumb_height
					" . ($this->check_read ? ', groupread.readtime AS readtime' : '') . "
					" . (!empty($this->generator->joins['inner_subscribegroup']) ? ', subscribegroup.emailupdate' : '') . "
					$criteria[columns]
				FROM " . TABLE_PREFIX . "socialgroup AS socialgroup
				" . $criteria['joins'] . "
				LEFT JOIN " . TABLE_PREFIX . "socialgroupicon AS socialgroupicon ON socialgroupicon.groupid = socialgroup.groupid
				LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = socialgroup.creatoruserid)" .
				 ($this->check_read ? "
				 LEFT JOIN " . TABLE_PREFIX . "groupread AS groupread
				  ON (groupread.groupid = socialgroup.groupid
				      AND groupread.userid = " . intval($this->registry->userinfo['userid']) . ")" : '') . "
				INNER JOIN " . TABLE_PREFIX . "socialgroupcategory AS sgc ON (sgc.socialgroupcategoryid = socialgroup.socialgroupcategoryid)
				WHERE " . $criteria['where'] . "
				ORDER BY " . $this->sort_criteria . "
				" . $criteria['limit'] . "
			");

			return $db->num_rows($this->search_result);
		}
	}

	/**
	* Fetch the results within the limit, it assumes that perform_search() has already been executed
	*
	* @return	array	Array of matched IDs
	*/
	function fetch_results()
	{
		$db =& $this->registry->db;

		if ($this->search_result === null)
		{
			$this->perform_search();
		}

		$ids = array();
		while ($result = $db->fetch_array($this->search_result))
		{
			$ids[] = $result;
		}
		$db->free_result($this->search_result);

		return $ids;
	}
}


/**
* Generates issue search criteria. Atom is issue.issueid. That table must be available in the final query.
*
* @package 		vBulletin Project Tools
* @copyright 	http://www.vbulletin.com/license.html
*/
class vB_SGSearchGenerator
{
	/**
	* List of valid criteria names. Key: criteria name, value: add method
	*
	* @var	array
	*/
	var $valid_fields = array(
		'text'            => 'add_text',
	    'category'        => 'add_category',
		'date_gteq'       => 'add_date_gteq',
		'date_lteq'       => 'add_date_lteq',
		'groupid'         => 'add_groupid',
		'members_gteq'    => 'add_members_gteq',
		'members_lteq'    => 'add_members_lteq',
		'discussion_gteq' => 'add_discussion_gteq',
		'discussion_lteq' => 'add_discussion_lteq',
		'message_gteq'    => 'add_messages_gteq',
		'message_lteq'    => 'add_messages_lteq',
		'picture_gteq'    => 'add_pictures_gteq',
		'picture_lteq'    => 'add_pictures_lteq',
		'member'          => 'add_member',
		'membertype'      => 'add_membertype',
		'creator'         => 'add_creator',
		'pending'         => 'add_pending',
		'type'            => 'add_type',
		'moderatedgms'    => 'add_moderatedgms',
		'subscribed'      => 'add_subscribed'
	);

	/**
	* List of valid sorting fields.
	* Key is the unique ID (from the form), value is the column name
	*
	* @var	array
	*/
	var $valid_sort = array(
		'members'     => 'socialgroup.members',
		'created'     => 'socialgroup.dateline',
		'name'        => 'socialgroup.name',
		'category'    => 'sgc.title',
		'pictures'    => 'socialgroup.picturecount', // see constructor: this is modified
		'messages'    => 'socialgroup.visible',
		'lastpost'    => 'socialgroup.lastpost',
		'discussions' => 'socialgroup.discussions'
	);

	/**
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* List of errors (DM style)
	*
	* @var	array
	*/
	var $errors = array();

	/**
	* Where clause pieces. Will be ANDed together
	*
	* @var	array
	*/
	var $where = array();

	/**
	* List of joins necessary
	*
	* @var	array
	*/
	var $joins = array();

	/**
	* The Query Limit offset
	*
	* @var	integer
	*/
	var $limitoffset = 0;

	/**
	* The Query Limit
	*
	* @var	integer
	*/
	var $limit = 0;

	/**
	* Constructor.
	*
	* @param	vB_Registry
	*/
	function vB_SGSearchGenerator(&$registry)
	{
		$this->registry =& $registry;

		// need to update the picture count
		if (isset($this->valid_sort['pictures']))
		{
			$this->valid_sort['pictures'] = 'IF(socialgroup.options & ' . $registry->bf_misc_socialgroupoptions['enable_group_albums'] . ', socialgroup.picturecount, 0)';
		}
	}

	/**
	* Determines whether the current search has errors
	*
	* @return	boolean
	*/
	function has_errors()
	{
		return !empty($this->errors);
	}

	/**
	* Verifies the sorting field and grabs the necessary data
	*
	* @param	string	(In/Out) The specified sort field, translated to the column name
	* @param	string	(In/Out) The specified sort order, translated to the appropriate safe value
	* @param	string	(Output) Raw sort field passed in
	* @param	string	(Output) Raw sort order passed in
	*
	* @return	bool	Returns true unless something fails. (Always returns true right now)
	*/
	function verify_sort(&$sort, &$sortorder, &$sort_raw, &$sortorder_raw, &$sort_criteria)
	{
		$sort_raw = $sort;
		$sortorder_raw = $sortorder;

		if (!isset($this->valid_sort["$sort"]))
		{
			$sort = 'socialgroup.dateline';
			$sort_raw = 'dateline';
		}
		else
		{
			$sort = $this->valid_sort["$sort"];
		}

		switch (strtolower($sortorder))
		{
			case 'asc':
			case 'desc':
				break;
			default:
				$sortorder = 'DESC';
				$sortorder_raw = 'DESC';
		}

		$sort_criteria = $sort . ' ' . $sortorder;

		if ($sort_raw != 'created')
		{
			$newsort = 'created';
			$newsort_order = $sortorder;
			$this->verify_sort($newsort, $newsort_order, $newsort_raw, $newsort_order_raw, $newsort_criteria);
			$sort_criteria .= ', ' . $newsort_criteria;
		}

		return true;
	}

	/**
	* Adds a search criteria
	*
	* @param	string	Name of criteria
	* @param	mixed	How to restrict the criteria
	*
	* @return	boolean	True on success
	*/
	function add($name, $value)
	{
		if (!isset($this->valid_fields["$name"]))
		{
			$this->error('sg_search_field_x_unknown', htmlspecialchars_uni($name));
			return SG_SEARCHGEN_CRITERIA_FAILED;
		}

		$raw = $value;
		$add_method = $this->valid_fields["$name"];
		return $this->$add_method($name, $value);
	}

	/**
	* Adds an error to the list, phrased for the current user. 1 or more arguments
	*
	* @param	string	Error phrase name
	*/
	function error($errorphrase)
	{
		$args = func_get_args();

		if (is_array($errorphrase))
		{
			$error = fetch_error($errorphrase);
		}
		else
		{
			$error = call_user_func_array('fetch_error', $args);
		}

		$this->errors[] = $error;
	}

	/**
	* Generates the search query bits
	*
	* @return	array|false	False if error, array consisting of joins and where clause otherwise
	*/
	function generate()
	{
		if (!$this->has_errors())
		{
			if (!empty($this->limit))
			{
				$limit = "LIMIT " . (!empty($this->limitoffset) ? $this->limitoffset . ',' : '') . $this->limit;
			}
			else
			{
				$limit = '';
			}

			return array(
				'columns' => '',
				'joins'   => implode("\n", $this->joins),
				'where'   => implode("\nAND ", $this->where),
				'limit'   => $limit
			);
		}
		else
		{
			return false;
		}
	}

	/**
	* Prepares a criteria that may either be a scalar or an array
	*
	* @param	mixed		Value to process
	* @param	callback	Callback function to call on each value
	* @param	string		Text to implode the array with
	*
	* @return	mixed		Returns true if the array is empty, otherwise the processed values
	*/
	function prepare_scalar_array($value, $callback = '', $array_splitter = ',')
	{
		if (is_array($value))
		{
			if ($callback)
			{
				$value = array_map($callback, $value);
			}

			$value = array_values($value);
			if (count($value) == 0 OR (count($value) == 1 AND empty($value[0])))
			{
				return call_user_func($callback, '');
			}
			else
			{
				return implode($array_splitter, $value);
			}
		}
		else if ($callback)
		{
			return call_user_func($callback, $value);
		}
		else
		{
			return $value;
		}
	}

	/**
	* Adds group ID criteria
	*
	* @param	string
	* @param	integer|array
	*
	* @return	boolean	True on success
	*/
	function add_groupid($name, $value)
	{
		$id = $this->prepare_scalar_array($value, 'intval', ',');
		if (!$id)
		{
			return SG_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$this->where['groupid'] = "socialgroup.groupid IN ($id)";
		return SG_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds criteria for a member record
	*
	* @param	string
	* @param	integer|array
	*
	* @return	boolean	True on success
	*/
	function add_member($name, $value)
	{
		$this->joins['inner_socialgroupmember'] = trim("
			INNER JOIN " . TABLE_PREFIX . "socialgroupmember AS socialgroupmember ON
				(socialgroupmember.userid = " . intval($value) . " AND socialgroupmember.groupid = socialgroup.groupid)
		");
		unset($this->joins['left_socialgroupmember']);

		return SG_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	*  Enter description here...
	*
	* @param	string
	* @param	integer|array
	*/
	function add_category($name, $value)
	{
		$this->where['socialgroupcategoryid'] = "socialgroup.socialgroupcategoryid = " . intval($value);
	}

	/**
	* Adds group owner criteria
	*
	* @param	string
	* @param	integer|array
	*
	* @return	boolean	True on success
	*/
	function add_creator($name, $value)
	{
		$this->where['owner'] = "socialgroup.creatoruserid = " . intval($value);
	}

	/**
	* Adds criteria for groups with pending members
	*
	* @param	string
	* @param	integer|array
	*
	* @return	boolean	True on success
	*/
	function add_pending($name, $value)
	{
		$this->where['pending'] = "socialgroup.moderatedmembers > 0";
	}

	/**
	* Adds criteria for groups with moderated messages
	*
	* @param	string
	* @param	integer|array
	*
	*/
	function add_moderatedgms($name, $value)
	{
		$this->where['moderatedgms'] = "socialgroup.moderation > 0";
	}

	/**
	* Adds member type criteria
	*
	* @param	string
	* @param	string
	*
	* @return	boolean	True on success
	*/
	function add_membertype($name, $value)
	{
		if (preg_match('/^(member|invited|moderated)$/', $value))
		{
			if (empty($this->joins['inner_socialgroupmember']))
			{
				// if it's already there leave it -- we don't know the user id that's desired
				$this->joins['inner_socialgroupmember'] = trim("
					INNER JOIN " . TABLE_PREFIX . "socialgroupmember AS socialgroupmember ON
						(socialgroupmember.userid = " . $this->registry->userinfo['userid'] . " AND socialgroupmember.groupid = socialgroup.groupid)
				");

				unset($this->joins['left_socialgroupmember']);
			}

			$this->where['membertype'] = "socialgroupmember.type = '" . $value . "'";

			return SG_SEARCHGEN_CRITERIA_ADDED;
		}
		else
		{
			return SG_SEARCHGEN_CRITERIA_FAILED;
		}

	}

	/**
	* Adds group type criteria
	*
	* @param	string
	* @param	string
	*
	* @return	boolean	True on success
	*/
	function add_type($name, $value)
	{
		if (preg_match('/^(public|inviteonly|moderated)$/', $value))
		{
			$this->where['type'] = "socialgroup.type = '" . $value ."'";

			return SG_SEARCHGEN_CRITERIA_ADDED;
		}
		else
		{
			return SG_SEARCHGEN_CRITERIA_FAILED;
		}

	}

	/**
	* Adds criteria for subscribed groups
	*
	* @param	string
	* @param	integer|array
	*
	* @return	boolean	True on success
	*/
	function add_subscribed($name, $value)
	{
		if (!$value AND isset($this->joins['inner_subscribegroup']))
		{
			unset($this->joins['inner_subscribegroup']);
		}
		else
		{
			$this->joins['inner_subscribegroup'] = trim("
				INNER JOIN " . TABLE_PREFIX . "subscribegroup AS subscribegroup ON
					(subscribegroup.userid = " . intval($value) . " AND subscribegroup.groupid = socialgroup.groupid)
			");
		}

		return SG_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Prepares the search text for use in a full-text query
	*
	* @param	string	Raw query text with AND, OR, and NOT
	* @param	array	(Output) Array of errors
	*
	* @return	string	Full-text query
	*/
	function prepare_search_text($query_text, &$errors)
	{
		global $vbulletin;

		// look for entire words that consist of "&#1234;". MySQL boolean
		// search will tokenize them seperately. Wrap them in quotes if they're
		// not already to emulate search for exactly that word.
		$query = explode('"', $query_text);
		$query_part_count = count($query);

		$query_text = '';
		for ($i = 0; $i < $query_part_count; $i++)
		{
			// exploding by " means the 0th, 2nd, 4th... entries in the array
			// are outside of quotes
			if ($i % 2 == 1)
			{
				// 1st, 3rd.. entry = in quotes
				$query_text .= '"' . $query["$i"] . '"';
			}
			else
			{
				// look for words that are entirely &#1234;
				$query_text .= preg_replace(
					'/(?<=^|\s)((&#[0-9]+;)+)(?=\s|$)/',
					'"$1"',
					$query["$i"]
				);
			}
		}

		$query_text = preg_replace(
			'#"([^"]+)"#sie',
			"stripslashes(str_replace(' ' , '*', '\\0'))",
			$query_text
		);

		require_once(DIR . '/includes/functions_search.php');
		$query_text = sanitize_search_query($query_text, $errors);

		if (!$errors)
		{
			// a tokenizing based approach to building a search query
			preg_match_all('#("[^"]*"|[^\s]+)#', $query_text, $matches, PREG_SET_ORDER);
			$new_query_text = '';
			$token_joiner = null;
			foreach ($matches AS $match)
			{
				if ($match[1][0] == '-')
				{
					// NOT has already been converted
					$new_query_text = "($new_query_text) $match[1]";
					continue;
				}

				switch (strtoupper($match[1]))
				{
					case 'OR':
					case 'AND':
					case 'NOT':
						// this isn't a searchable word, but a joiner
						$token_joiner = strtoupper($match[1]);
						break;

					default:
						verify_word_allowed($match[1]);

						if ($new_query_text !== '')
						{
							switch ($token_joiner)
							{
								case 'OR':
									// OR is no operator
									$new_query_text .= " $match[1]";
									break;

								case 'NOT':
									// NOT this, but everything before it
									$new_query_text = "($new_query_text) -$match[1]";
									break;

								case 'AND':
								default:
									// if we didn't have a joiner, default to and
									$new_query_text = "+($new_query_text) +$match[1]";
									break;
							}
						}
						else
						{
							$new_query_text = $match[1];
						}

						$token_joiner = null;
				}
			}

			$query_text = $new_query_text;

		}

		return trim($query_text);
	}

	/**
	* Adds fulltext search criteria
	*
	* @param	string
	* @param	string
	*
	* @return	boolean	True on success
	*/
	function add_text($name, $value)
	{
		$value = strval($value);
		if (!$value)
		{
			return SG_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$value = $this->prepare_search_text($value, $errors);
		if ($errors)
		{
			foreach ($errors AS $error)
			{
				$this->error($error);
			}
			return SG_SEARCHGEN_CRITERIA_FAILED;
		}

		$value = $this->registry->db->escape_string($value);

		$this->where['text'] = trim("
			(MATCH(socialgroup.name, socialgroup.description) AGAINST ('$value' IN BOOLEAN MODE) )
		");

		return SG_SEARCHGEN_CRITERIA_ADDED;
	}


	/**
	* Adds member count >= criteria
	*
	* @param	string
	* @param	integer
	*
	* @return	boolean	True on success
	*/
	function add_members_gteq($name, $value)
	{
		$value = intval($value);
		if ($value <= 0)
		{
			return SG_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$this->where['members_gteq'] = trim("
			socialgroup.members >= $value
		");

		return SG_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds member count <= criteria
	*
	* @param	string
	* @param	integer
	*
	* @return	boolean	True on success
	*/
	function add_members_lteq($name, $value)
	{
		$value = intval($value);
		if ($value < 0)
		{
			return SG_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$this->where['members_lteq'] = trim("
			socialgroup.members <= $value
		");

		return SG_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds discussion count >= criteria
	*
	* @param	string
	* @param	integer
	*
	* @return	boolean	True on success
	*/
	function add_discussion_gteq($name, $value)
	{
		$value = intval($value);
		if ($value <= 0)
		{
			return SG_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$this->where['discussion_gteq'] = trim("
			socialgroup.discussions >= $value
		");

		return SG_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds discussion count <= criteria
	*
	* @param	string
	* @param	integer
	*
	* @return	boolean	True on success
	*/
	function add_discussion_lteq($name, $value)
	{
		$value = intval($value);
		if ($value < 0)
		{
			return SG_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$this->where['discussion_lteq'] = trim("
			socialgroup.discussions <= $value
		");

		return SG_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds message count >= criteria
	*
	* @param	string
	* @param	integer
	*
	* @return	boolean	True on success
	*/
	function add_messages_gteq($name, $value)
	{
		$value = intval($value);
		if ($value <= 0)
		{
			return SG_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$this->where['messages_gteq'] = trim("
			socialgroup.visible >= $value
		");

		return SG_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds message count <= criteria
	*
	* @param	string
	* @param	integer
	*
	* @return	boolean	True on success
	*/
	function add_messages_lteq($name, $value)
	{
		$value = intval($value);
		if ($value < 0)
		{
			return SG_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$this->where['messages_lteq'] = trim("
			socialgroup.visible <= $value
		");

		return SG_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds picture count >= criteria
	*
	* @param	string
	* @param	integer
	*
	* @return	boolean	True on success
	*/
	function add_pictures_gteq($name, $value)
	{
		$value = intval($value);
		if ($value <= 0)
		{
			return SG_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$this->where['pictures_gteq'] = trim("
			socialgroup.picturecount >= $value
		");

		return SG_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds picture count <= criteria
	*
	* @param	string
	* @param	integer
	*
	* @return	boolean	True on success
	*/
	function add_pictures_lteq($name, $value)
	{
		$value = intval($value);
		if ($value < 0)
		{
			return SG_SEARCHGEN_CRITERIA_UNNECESSARY;
		}

		$this->where['pictures_lteq'] = trim("
			socialgroup.picturecount <= $value
		");

		return SG_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds date >= criteria. -1 means last visit
	*
	* @param	string
	* @param	integer
	*
	* @return	boolean	True on success
	*/
	function add_date_gteq($name, $value)
	{
		$value = intval($value);

		if ($value == 0 OR $value < -1)
		{
			return SG_SEARCHGEN_CRITERIA_UNNECESSARY;
		}
		else if ($value == -1)
		{
			$value = intval($this->registry->userinfo['lastvisit']);
		}

		$this->where['dateline_from'] = trim("
			socialgroup.dateline >= $value
		");

		return SG_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds search date <= criteria. -1 means last visit
	*
	* @param	string
	* @param	integer
	*
	* @return	boolean	True on success
	*/
	function add_date_lteq($name, $value)
	{
		$value = intval($value);

		if ($value == 0 OR $value < -1)
		{
			return SG_SEARCHGEN_CRITERIA_UNNECESSARY;
		}
		else if ($value == -1)
		{
			$value = intval($this->registry->userinfo['lastvisit']);
		}

		$this->where['dateline_to'] = trim("
			socialgroup.dateline <= $value
		");

		return SG_SEARCHGEN_CRITERIA_ADDED;
	}

	/**
	* Adds a limit to the search criteria
	*
	* @param	integer	Offset for the LIMIT clause
	* @param	integer	Limit for the LIMIT clause
	*/
	function limit($offset, $limit)
	{
		if ($offset)
		{
			$this->limitoffset = intval($offset);
		}

		if ($limit)
		{
			$this->limit = intval($limit);
		}
		else
		{
			$this->limit = 20;
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # RCS: $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>
