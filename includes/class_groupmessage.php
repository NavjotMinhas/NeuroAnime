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

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

require_once(DIR . '/includes/class_bbcode.php');
require_once(DIR . '/includes/functions_user.php');

/**
 * Collection Factory
 * Gets the appropriate collection handler for a content type.
 *
 * @package		vBulletin
 * @copyright	http://www.vbulletin.com/license
 */
class vB_Collection_Factory
{
	/**
	 * A reference to the application registry.
	 *
	 * @access protected
	 * @var vB_Registry
	 */
	var $registry;

	/**
	 * Array of acceptable content types that the factory can produce a collection
	 * handler for.
	 *
	 * @access protected
	 * @var array string
	 */
	var $types = array('album', 'groupcategory');

	/**
	 * Class prefix for created collections
	 *
	 * @access protected
	 * @var string
	 */
	var $class_prefix = 'vB_Collection_';

	// #######################################################################

	/**
	 * Constructor
	 * Note: No validation is done here as we know registry is reliably
	 * passed to vb_Collection which should always validate them.
	 *
	 * @access public
	 *
	 * @param vB_Registry $registry
	 * @return vB_Collection_Factory
	 */
	function vB_Collection_Factory(&$registry)
	{
		$this->registry =& $registry;
	}

	/**
	 * Instantiates and returns the appropriate collection handler for the given
	 * content type.
	 *
	 * @access protected
	 * @see vB_Legacy_Collection
	 *
	 * @param string $collection_type			The name of the content type
	 * @param integer $parent_id				Optional id of outer item
	 * @param integer $page						Optional starting index
	 * @param integer $quantity					Optional quantity
	 * @param boolean $descending				Whether to get results in descending order
	 * @param boolean $no_limit					Whether to get all results or not
	 * @return vB_Group_Collection				The appropriate collection
	 */
	function &create($collection_type, $parent_id = false, $page = 1, $quantity = false, $descending = true, $no_limit = false)
	{
		// Hook to add custom classes
		($hook = vBulletinHook::fetch_hook('collection_factory_create')) ? eval($hook) : false;

		// Ensure the type is valid
		if (!in_array($collection_type, $this->types))
		{
			trigger_error("vB_Collection_Factory::create(): Invalid type ", E_USER_ERROR);
		}

		$class_name = $this->class_prefix . ucfirst($collection_type);

		// Create the collection handler
		if (class_exists($class_name, false))
		{
			return $this->instantiate($class_name, $parent_id, $page, $quantity, $descending, $no_limit);
		}
		else
		{
			trigger_error('vB_Collection_Factory::create(): Invalid type ' . htmlspecialchars_uni($class_name) . '.', E_USER_ERROR);
		}
	}

	/**
	 * Instantiates the required collection object.
	 *
	 * @access protected
	 * @see vB_Legacy_Collection
	 *
	 * @param string $class_name				The resolved name of the class to instantate
	 * @param integer $parent_id				Optional id of outer item
	 * @param integer $page						Optional starting index
	 * @param integer $quantity					Optional quantity
	 * @param boolean $descending				Whether to get results in descending order
	 * @param boolean $no_limit					Whether to get all results or not
	 * @return vB_Legacy_Collection				The appropriate collection
	 */
	function instantiate($class_name, $parent_id = false, $page = 1, $quantity = false, $descending = true, $no_limit = false)
	{
		return new $class_name($this->registry, $parent_id, $page, $quantity, $descending, $no_limit);
	}
}


/**
 * Collection
 * Fetches a collection of content items with the given parent id,
 * start item and quantity of items to fetch.
 *
 * Also contains information for pagination.
 * @see vB_Legacy_Collection::fetch_counts()
 *
 * @package		vBulletin
 * @copyright	http://www.vbulletin.com/license.html
 *
 * @abstract
 */
class vB_Legacy_Collection
{
	/**
	 * Type identifier.
	 * Given to items so it can be checked whenever they are processed.
	 *
	 * @access protected
	 * @var string
	 */
	var $type;

	/**
	 * A reference to the application registry.
	 *
	 * @access protected
	 * @var vB_Registry
	 */
	var $registry;

	/**
	 * The id of the parent item that encapsulates the collection.
	 * This is optional and depends on the context.
	 *
	 * @access protected
	 * @var integer
	 */
	var $parent_id;

	/**
	 * The index of the results page to fetch
	 *
	 * @access protected
	 * @var integer
	 */
	var $page;

	/**
	 * Amount of items to fetch.
	 * Note: The result may be less than this.
	 *
	 * @access protected
	 * @var integer
	 */
	var $quantity;

	/**
	 * If this is true, the collection will be fetched in descending order.
	 *
	 * @access protected
	 * @var bool
	 */
	var $descending;

	/**
	 * If this is true, the collection will fetch all applicable items.
	 *
	 * @access protected
	 * @var bool
	 */
	var $no_limit;

	/**
	 * Cached result
	 *
	 * @access protected
	 * @var array
	 */
	var $collection;

	/**
	 * Name of a view query hook to execute to add to the collection query.
	 *
	 * @access protected
	 * @var string | boolean
	 */
	var $view_query_hook = false;

	/**
	 * The resolved start result index in the collection
	 *
	 * @access protected
	 * @var integer
	 */
	var $start;

	/**
	 * The resolved end result index in the collection
	 *
	 * @access protected
	 * @var integer
	 */
	var $end;

	/**
	 * The resolved total size of the collection
	 *
	 * @access protected
	 * @var integer
	 */
	var $total;

	/**
	 * Reference to the first item of the resolved collection
	 *
	 * @access protected
	 * @var array
	 */
	var $firstitem;

	/**
	 * Reference to the last item of the resolved collection
	 *
	 * @access protected
	 * @var array
	 */
	var $lastitem;

	/**
	 * Whether ignor emarking is allowed in this class.
	 *
	 * @access protected
	 * @var boolean
	 */
	var $allow_ignore_marking = false;

	/**
	 * Whether mark ignored items as ignored.
	 *
	 * @access protected
	 * @var array
	 */
	var $ignore_marking = true;

	/**
	 * The name of the userid field to be used for ignore marking
	 *
	 * @access protected
	 * @var string
	 */
	var $userid_field = 'userid';

	/**
	 * Optional id to fetch a specific item
	 *
	 * @access protected
	 * @var mixed filter_id
	 */
	var $filter_id;

	/**
	 * Only show items created after this many days old
	 * @see filter_days_prune()
	 *
	 * @access protected
	 * @var array
	 */
	var $daysprune;

	/**
	 * Field to sort by
	 * @see filter_sort_field()
	 *
	 * @access protected
	 * @var array
	 */
	var $sortfield;

	/**
	 * Name of the sort field hook for the item type.
	 *
	 * @access protected
	 * @var array
	 */
	var $sort_field_hook;

	/**
	 * Cache of the compiled collection sql
	 *
	 * @access protected
	 * @var string
	 */
	var $collection_sql;

	/**
	 * Cache of resolved query conditions based on user permission and message state
	 *
	 * @access protected
	 * @var string
	 */
	var $state_sql;

	/**
	 * Cache of compiled condition sql
	 *
	 * @access protected
	 * @var string
	 */
	var $condition_sql;

	// #######################################################################

	/**
	 * Constructor.
	 * Assigns and normalizes the required property members.
	 *
	 * @access public
	 *
	 * @param vB_Registry $registry				Global registry
	 * @param integer $parent_id				Optional parent item id
	 * @param integer $page						Optional page index
	 * @param integer $quantity					Optional quantity
	 * @param boolean $descending				Whether to get results in descending order
	 * @param boolean $no_limit					Whether to get all results or not
	 * @return vB_Group_Collection
	 */
	function vB_Legacy_Collection(&$registry, $parent_id = false, $page = 1, $quantity = false, $descending = true, $no_limit = false)
	{
		// Check the registry is valid
		if (is_object($registry))
		{
			$this->registry =& $registry;
		}
		else
		{
			trigger_error("vB_Legacy_Collection::Registry object is not an object", E_USER_ERROR);
		}

		$this->parent_id = intval($parent_id);
		$this->page = max(1, intval($page));
		$this->quantity = max(1, intval($quantity));
		$this->descending = $descending;
		$this->no_limit = $no_limit;
	}

	/**
	 * Finds and sets the results page based on a dateline.
	 * Must be extended by child classes to have any effect.
	 *
	 * @access public
	 *
	 * @var integer								The dateline of an item on the required page
	 * @return integer | boolean				The resolved page index or false
	 */
	function seek_item($dateline)
	{
		if (!is_numeric($dateline))
		{
			if (1 != $this->page)
			{
				$this->page = 1;
				$this->reset();
				return false;
			}
		}

		return true;
	}

	/**
	 * Fetch collection of items.
	 * Creates a multidimensional array of messages with all useful properties for
	 * each message.
	 *
	 * @access public
	 *
	 * @return array
	 */
	function fetch()
	{
		// check local cache
		if (isset($this->collection))
		{
			return $this->collection;
		}

		$this->ignore_marking = ($this->allow_ignore_marking AND $this->ignore_marking AND $this->userid_field);

		// create a new collection
		$collection = array();

		// while start is valid
		do
		{
			$this->start = ($this->page - 1) * $this->quantity;

			// get query
			if (!($sql = $this->collection_sql(true)))
			{
				return $collection;
			}

			// exec query
			$query = $this->registry->db->query_read($sql);

			// get total
			list($this->total) = $this->registry->db->query_first("SELECT FOUND_ROWS()", DBARRAY_NUM);

			// check start is valid
			if ($this->start >= $this->total)
			{
				$this->page = ceil($this->total / $this->quantity);
			}
		}
		while ($this->start >= $this->total AND $this->total);

		// save the pagination info for querying from the client code
		$this->start++;
		$this->end = min(($this->start-1) + $this->quantity, $this->total);

		if ($this->ignore_marking)
		{
			// get ignore list
			if ($this->registry->userinfo['userid'] AND !$this->registry->GPC['showignored'])
			{
				$ignorelist = preg_split('/( )+/', trim($this->registry->userinfo['ignorelist']), -1, PREG_SPLIT_NO_EMPTY);
			}
			else
			{
				$ignorelist = array();
			}
		}

		// build collection, get the first item and flag ignored messages
		$this->collection = array();
		while ($item = $this->registry->db->fetch_array($query))
		{
			if (!$this->firstitem)
			{
				$this->firstitem =& $item;
			}

			if ($this->ignore_marking AND $ignorelist AND in_array($item[$this->userid_field], $ignorelist))
			{
				$item['ignored'] = true;
			}

			// set the item type
			$item['type'] = $this->type;

			$this->collection[] = $item;
		}
		$this->registry->db->free_result($query);

		if (sizeof($this->collection))
		{
			$this->lastitem =& $this->collection[sizeof($this->collection)-1];
		}

		return $this->collection;
	}

	/**
	 * Fetches a single item at a time.
	 *
	 * @access public
	 *
	 * @return array							Single content item
	 */
	function fetch_item()
	{
		if (!$this->collection)
		{
			$this->fetch();
		}

		if ($item = current($this->collection))
		{
			next($this->collection);
			return $item;
		}

		return false;
	}

	/**
	 * Fetches the counts of the fetched collection.
	 *
	 * @access public
	 *
	 * @return integer
	 */
	function fetch_counts()
	{
		$this->fetch();

		return array('start' => $this->start, 'end' => $this->end, 'shown' => sizeof($this->collection), 'total' => $this->total);
	}

	/**
	 * Only fetches the total size of the collection.
	 *
	 * @access public
	 *
	 * @return integer;
	 */
	function fetch_count()
	{
		$this->fetch();

		return sizeof($this->collection);
	}

	/**
	 * Fetches the first item of the collection or a property of the first item.
	 *
	 * @access public
	 *
	 * @param string $property					Optional property key
	 */
	function fetch_firstitem($property = false)
	{
		// Ensure the collection is fetched
		$this->fetch();

		if ($property AND isset($this->firstitem["$property"]))
		{
			return $this->firstitem["$property"];
		}
		else if ($this->firstitem)
		{
			return $this->firstitem;
		}

		return false;
	}

	/**
	 * Fetches the last item of the collection or a property of the last item.
	 *
	 * @access public
	 *
	 * @param string $property					Optional property key
	 */
	function fetch_lastitem($property)
	{
		// Ensure the collection is fetched
		$this->fetch();

		if ($property AND isset($this->lastitem["$property"]))
		{
			return $this->lastitem["$property"];
		}
		else if ($this->lastitem)
		{
			return $this->lastitem;
		}

		return false;
	}

	/**
	 * Gets the resolved results page
	 *
	 * @access public
	 *
	 * @return integer
	 */
	function fetch_pagenumber()
	{
		$this->fetch();

		return $this->page ? $this->page : 0;
	}

	/**
	 * Gets the resolved per page quanitity
	 *
	 * @access public
	 *
	 * @return integer
	 */
	function fetch_quantity()
	{
		$this->fetch();

		return $this->quantity;
	}

	/**
	 * Unsets the fetched collection and parsed collection sql.
	 *
	 * @access public
	 */
	function reset()
	{
		unset($this->collection, $this->collection_sql, $this->state_sql, $this->condition_sql);
	}

	// #######################################################################

	/**
	 * Sets the hook for modifying the fetch query.
	 * @see fetch()
	 *
	 * @access public
	 */
	function set_query_hook($name)
	{
		$this->view_query_hook = $name;
	}

	/**
	 * Builds the query to get the collection.
	 * @see fetch()
	 *
	 * @access protected
	 *
	 * @param bool $force_refresh				Whether to ignore the cache
	 * @return string							The built SQL
	 */
	function collection_sql($force_refresh = false)
	{
		if ($this->collection_sql AND !$force_refresh)
		{
			return $this->collection_sql;
		}

		return $this->collection_sql = false;
	}

	/**
	 * Builds conditions for the message query based on the user's permissions.
	 * @see fetch()
	 *
	 * @access protected
	 *
	 * @return string							The built SQL
	 */
	function state_sql()
	{
		if (isset($this->state_sql))
		{
			return $this->state_sql;
		}

		return $this->state_sql = false;
	}

	/**
	 * Allows child collection classes to add further conditions to the fetch sql.
	 * @see fetch()
	 *
	 * @access protected
	 *
	 * return string
	 */
	function condition_sql()
	{
		if (isset($this->condition_sql))
		{
			return $this->condition_sql;
		}

		return $this->condition_sql = false;
	}

	// ##Filters##############################################################

	/**
	 * Sets whether to use ignore marking.
	 *
	 * @access public
	 *
	 * @param boolean $ignore_marking
	 */
	function set_ignore_marking($ignore_marking = true)
	{
		$this->ignore_marking = $ignore_marking;
	}

	/**
	 * Sets amount of days old to prune items.
	 * Items older than $this->daysprune days old will not be fetched.
	 *
	 * @access public
	 * @param int $days
	 */
	function filter_days_prune($days=0)
	{
		if ($days != $this->daysprune)
		{
			$this->daysprune = intval($days);
			$this->reset();
		}
	}

	/**
	 * Sets the sort field.
	 * Child classes should validate the field and prefix.* the appropriate table alias.
	 *
	 * @access public
	 * @param string $field						- The field to sort by
	 */
	function filter_sort_field($field)
	{
		$this->filter_sort_field_hook($field);
	}

	/**
	 * Allows hooks to evaluate sort field.
	 *
	 * @access protected
	 * @param string $field
	 */
	function filter_sort_field_hook($field)
	{
		$resolved_table = false;
		$resolved_field = false;

		($hook = vBulletinHook::fetch_hook($this->sort_field_hook)) ? eval($hook) : false;

		if ($resolved_table AND $resolved_field)
		{
			$sortfield = $resolved_table . '.' . $resolved_field;

			if ($sortfield != $this->sortfield)
			{
				$this->sortfield = $sortfield;
				$this->reset();
			}
		}
	}

	/**
	 * Specifies an item id to fetch
	 *
	 * @access protected
	 * @param mixed $id
	 */
	function filter_id($id)
	{
		$this->filter_id = $id;
		$this->reset();
	}
}


/**
 * Group Collection Factory
 * Gets the appropriate collection handler for a social group content type.
 *
 * @package		vBulletin
 * @copyright	http://www.vbulletin.com/license
 */
class vB_Group_Collection_Factory extends vB_Collection_Factory
{
	/**
	 * Information about the social group.
	 *
	 * @access protected
	 * @var array
	 */
	var $group;

	/**
	 * Array of acceptable content types that the factory can produce a collection
	 * handler for.
	 *
	 * @access protected
	 * @var array string
	 */
	var $types = array('discussion', 'message', 'recentmessage');

	/**
	 * Class prefix for created collections
	 *
	 * @access protected
	 * @var string
	 */
	var $class_prefix = 'vB_Group_Collection_';

	// #######################################################################

	/**
	 * Constructor
	 * Note: No validation is done here as we know registry and group are reliably
	 * passed to vb_Group_Collection which should always validate them.
	 *
	 * @access public
	 *
	 * @param vB_Registry $registry
	 * @param array $group						Information about the social group
	 * @return vB_Group_Collection_Factory
	 */
	function vB_Group_Collection_Factory(&$registry, $group = false)
	{
		parent::vB_Collection_Factory($registry);
		$this->group = $group;
	}

	/**
	 * Instantiates the required group collection object.
	 *
	 * @access protected
	 * @see vB_Group_Collection
	 *
	 * @param string $class_name				The resolved name of the class to instantate
	 * @param integer $parent_id				Optional id of outer item
	 * @param integer $page						Optional starting index
	 * @param integer $quantity					Optional quantity
	 * @param boolean $descending				Whether to get results in descending order
	 * @param boolean $no_limit					Whether to get all results or not
	 * @return vB_Group_Collection				The appropriate collection
	 */
	function instantiate($class_name, $parent_id = false, $page = 1, $quantity = false, $descending = true, $no_limit = false)
	{
		return new $class_name($this->registry, $this->group, $parent_id, $page, $quantity, $descending, $no_limit);
	}
}


/**
 * Group Collection
 * Fetches a collection of group content items with the given parent id,
 * start item and quantity of items to fetch.
 *
 * Note: All valid group items are expected to have the user fields of the author
 * as properties, and optionally a state('visible', 'moderation', 'deleted') and
 * a dateline.
 *
 * Also contains information for pagination.
 * @see vB_Group_Collection::fetch_counts()
 *
 * @package		vBulletin
 * @copyright	http://www.vbulletin.com/license.html
 *
 * @abstract
 */
class vB_Group_Collection extends vB_Legacy_Collection
{
	/**
	 * Information about the social group.
	 *
	 * @access protected
	 * @var array
	 */
	var $group;

	/**
	 * Text to search
	 * @see filter_searchtext
	 */
	var $filter_searchtext;

	// #######################################################################

	/**
	 * Constructor.
	 * Assigns and normalizes the required property members.
	 *
	 * @access public
	 *
	 * @param vB_Registry $registry				Global registry
	 * @param array $group						Information about the social group
	 * @param integer $parent_id				Optional parent item id
	 * @param integer $page						Optional page index
	 * @param integer $quantity					Optional quantity
	 * @return vB_Group_Collection
	 */
	function vB_Group_Collection(&$registry, $group = false, $parent_id = false, $page = 1, $quantity = false, $descending = true, $no_limit = false)
	{
		parent::vB_Legacy_Collection($registry, $parent_id, $page, $quantity, $descending, $no_limit);

		$this->group = $group;
	}

	/**
	 * Sets a string for searching
	 *
	 * @access public
	 * @param string $searchtext
	 */
	function filter_searchtext($text)
	{
		require_once(DIR . '/includes/class_socialgroup_search.php');
		$text = vB_SGSearchGenerator::prepare_search_text($text, $errors = false);

		if (!$errors AND ($text != $this->filter_searchtext))
		{
			$this->filter_searchtext = $text;
			$this->reset();
		}
	}
}


/**
 * Group Message Collection.
 *
 * Queries a collection of social messages.
 *
 * @package		vBulletin
 * @copyright 	http://www.vbulletin.com/license.html
 */
class vB_Group_Collection_Message extends vB_Group_Collection
{
	/**
	 * Type identifier.
	 * Given to items so it can be checked whenever they are processed.
	 *
	 * @access protected
	 * @var string
	 */
	var $type = 'message';

	/**
	 * Name of a view query hook to execute to add to the collection query.
	 *
	 * @access protected
	 * @var string | boolean
	 */
	var $view_query_hook = 'group_view_message_query';

	/**
	 * Whether to show deleted items that the user has permission to.
	 *
	 * @access protected
	 * @var boolean
	 */
	var $show_deleted = true;

	/**
	 * Whether to show visible items that the user has permission to.
	 *
	 * @access protected
	 * @var boolean
	 */
	var $show_visible = true;

	/**
	 * Whether to show moderated items that the user has permission to.
	 *
	 * @access protected
	 * @var boolean
	 */
	var $show_moderated = true;

	/**
	 * Cache of resolved query join based on user's permission to view deleted items
	 *
	 * @access protected
	 * @var string
	 */
	var $deleted_sql;

	/**
	 * Name of the sort field hook for the item type.
	 *
	 * @access protected
	 * @var array
	 */
	var $sort_field_hook = 'group_message_sort_field';

	/**
	 * Whether to fetch messages that are also the first post of a discussion.
	 *
	 * @access protected
	 * @var boolean
	 */
	var $show_discussions = true;

	/**
	 * The name of the userid field to be used for ignore marking
	 *
	 * @access protected
	 * @var string
	 */
	var $userid_field = 'postuserid';

	/**
	 * Whether ignor emarking is allowed in this class.
	 *
	 * @access protected
	 * @var boolean
	 */
	var $allow_ignore_marking = true;

	// #######################################################################

	/**
	 * Builds the query to get the collection.
	 * @see fetch()
	 *
	 * @access protected
	 *
	 * @param bool $force_refresh				Whether to ignore the cache
	 * @return string							The built SQL
	 */
	function collection_sql($force_refresh = false)
	{
		if ($this->collection_sql AND !$force_refresh)
		{
			return $this->collection_sql;
		}

		if (!$this->sortfield)
		{
			$this->filter_sort_field('dateline');
		}

		// hook to alter query.
		$group = $this->group;	$vbulletin =& $this->registry;
		$hook_query_fields = $hook_query_joins = $hook_query_where = '';
		($hook = vBulletinHook::fetch_hook($this->view_query_hook)) ? eval($hook) : false;

		// build query
		$sql = 		"SELECT SQL_CALC_FOUND_ROWS
						gm.*, user.*, gm.ipaddress AS itemipaddress";

		if ($deleted_sql = $this->deleted_sql())
		{
			$sql .= "	,deletionlog.userid AS del_userid, deletionlog.username AS del_username,
						deletionlog.reason AS del_reason";
		}

		if ($this->registry->options['avatarenabled'])
		{
			$sql .= "	,avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar,
						customavatar.dateline AS avatardateline,customavatar.width AS avwidth,
						customavatar.height AS avheight, customavatar.width_thumb AS avwidth_thumb,
						customavatar.height_thumb AS avheight_thumb, filedata_thumb,
						NOT ISNULL(customavatar.userid) AS hascustom";
		}

		$sql .= "		$hook_query_fields
					FROM " . TABLE_PREFIX . "groupmessage AS gm
					LEFT JOIN " . TABLE_PREFIX . "user AS user ON (gm.postuserid = user.userid)";

		if ($this->registry->options['avatarenabled'])
		{
			$sql .= " LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar
						ON (avatar.avatarid = user.avatarid)
					 LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar
						ON (customavatar.userid = user.userid)";
		}

		if (!$this->show_discussions)
		{
			$sql .= " INNER JOIN " . TABLE_PREFIX . "discussion AS discussion
						ON (discussion.discussionid = gm.discussionid
							AND discussion.firstpostid <> gm.gmid)";
		}

		$sql .= "	 $deleted_sql
					 $hook_query_joins
					WHERE 1=1";

		if ($this->parent_id)
		{
			$sql .= " AND gm.discussionid = $this->parent_id";
		}

		if ($this->daysprune)
		{
			$sql .= (($this->daysprune != -1) ? " AND gm.dateline >= " . (TIMENOW - ($this->daysprune * 86400)) : '');
		}

		if ($this->filter_id)
		{
			$sql .= " AND gm.gmid = " . intval($this->filter_id);
		}

		$sql .= 	" "	. $this->state_sql() .
					" " . $this->condition_sql() .
					"	$hook_query_where " .
					($this->filter_id ? '' : "ORDER BY " . $this->sortfield . " " . ($this->descending ? "DESC" : "ASC") . " " .
					($this->no_limit ? "" : "LIMIT {$this->start}, {$this->quantity}"));

		return $this->collection_sql = $sql;
	}

	/**
	 * Builds conditions for the message query based on the user's permissions.
	 * @see fetch()
	 *
	 * @access protected
	 *
	 * @return string							The built SQL
	 */
	function state_sql()
	{
		if (isset($this->state_sql))
		{
			return $this->state_sql;
		}

		// Build state conditions for query
		$state = array();
		$state_or = array();

		if ($this->show_visible)
		{
			$state = array('visible');
		}

		if ($this->show_moderated)
		{
			if (fetch_socialgroup_modperm('canmoderategroupmessages', $this->group))
			{
				$state[] = 'moderation';
			}
			else if ($this->registry->userinfo['userid'])
			{
				$state_or[] = "(gm.postuserid = " . $this->registry->userinfo['userid'] . " AND state = 'moderation')";
			}
		}

		if ($this->show_deleted)
		{
			if (fetch_socialgroup_modperm('canviewdeleted', $this->group))
			{
				$state[] = 'deleted';
			}
		}

		if (sizeof($state))
		{
			$state_or[] = "gm.state IN ('" . implode("','", $state) . "')";
		}

		if (sizeof($state_or))
		{
			return $this->state_sql = "AND (" . implode(" OR ", $state_or) . ")";
		}

		return '';
	}

	/**
	 * Builds SQL for deleted items based on whether the current user can view them.
	 * @see fetch()
	 *
	 * @access protected
	 *
	 * @return string							The created SQL join.
	 */
	function deleted_sql()
	{
		if (isset($this->deleted_sql))
		{
			return $this->deleted_sql;
		}

		// Check if user can view deleted items
		if ($this->show_deleted AND fetch_socialgroup_modperm('canviewdeleted', $this->group))
		{
			$sql = "LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog
									ON (gm.gmid = deletionlog.primaryid AND deletionlog.type = 'groupmessage')";
		}
		else
		{
			$sql = false;
		}

		return $this->deleted_sql = $sql;
	}

	// #######################################################################

	/**
	 * Finds the page index based on a given dateline
	 *
	 * @access public
	 *
	 * @return unknown
	 */
	function seek_item($dateline)
	{
		if (!parent::seek_item($dateline))
		{
			return false;
		}

		// Collection is stale
		$this->reset();

		// Find page
		$getpagenum = $this->registry->db->query_first("
			SELECT COUNT(*) AS comments
			FROM " . TABLE_PREFIX . "groupmessage AS gm
			WHERE discussionid = $this->parent_id " .
				$this->state_sql() . "
				AND dateline " . ($this->descending ? ">" : "<") . "= $dateline
		");

		// if page is full, adding 1 pushes to top of next page
		return $this->page = max(1, ceil(($getpagenum['comments']+1) / $this->quantity));
	}

	/**
	 * Unsets the fetched collection and parsed collection sql.
	 *
	 * @access public
	 */
	function reset()
	{
		parent::reset();
		unset($this->deleted_sql);
	}

	// #######################################################################

	/**
	 * Sets whether to show deleted items that the user has permission to.
	 *
	 * @access public
	 *
	 * @param boolean $show
	 */
	function filter_show_deleted($show = true)
	{
		if ($show != $this->show_deleted)
		{
			$this->show_deleted = $show;
			$this->reset();
		}
	}

	/**
	 * Sets whether to show visible items that the user has permission to.
	 *
	 * @access public
	 *
	 * @param boolean $show
	 */
	function filter_show_visible($show = true)
	{
		if ($show != $this->show_visible)
		{
			$this->show_visible = $show;
			$this->reset();
		}
	}

	/**
	 * Sets whether to show moderated items that the user has permission to.
	 *
	 * @access public
	 *
	 * @param boolean $show
	 */
	function filter_show_moderated($show = true)
	{
		if($show != $this->show_moderated)
		{
			$this->show_moderated = $show;
			$this->reset();
		}
	}

	/**
	 * Sets the sort field.
	 * Child classes should validate the field and prefix.* the appropriate table alias.
	 *
	 * @access public
	 *
	 * @param string $field						- The field to sort by
	 */
	function filter_sort_field($field)
	{
		$this->filter_sort_field_hook($field);

		if (!$this->sortfield)
		{
			$this->sortfield = 'gm.';
			$this->sortfield .= (('username' == $field) ? 'postusername' : 'dateline');
		}
	}

	/**
	 * Sets wheter to select discussion posts.
	 * If this is false then first posts will not be included.  Ideal for moderation.
	 * @see moderation.php
	 *
	 * @access public
	 *
	 * @param boolean $show
	 */
	function filter_show_discussions($show = true)
	{
		if ($show != $this->show_discussions)
		{
			$this->show_discussions = $show;
			$this->reset();
		}
	}
}


/**
 * Group Recent Message Collection
 * Fetches messages after a dateline including an optionally specified message.
 *
 * Note: This class is unable to provide an accurate total messages and essentially
 * ignores any specified page or per page quantity.
 *
 * @package		vBulletin
 * @copyright 	http://www.vbulletin.com/license.html
 *
 */
class vB_Group_Collection_RecentMessage extends vB_Group_Collection_Message
{
	/**
	 * The dateline to fetch messages since.
	 * Only messages after this dateline will be fetched.
	 *
	 * @access protected
	 * @var integer
	 */
	var $dateline;

	/**
	 * An optional gmid of a message to include.
	 * The messages will be fetched in the collection regardless of it's dateline.
	 *
	 * @access protected
	 * @var integer
	 */
	var $include_gmid;

	// #######################################################################

	/**
	 * Sets the dateline to show messages since.
	 *
	 * @access public
	 *
	 * @param integer $dateline					Dateline that all messages since will be fetched
	 * @param integer $gmid						An optional gmid to add to the collection
	 */
	function set_dateline($dateline, $include_gmid = false)
	{
		if (($dateline != $this->dateline) OR $include_gmid != $this->include_gmid)
		{
			$this->dateline = $dateline;
			$this->include_gmid = $include_gmid;
			$this->reset();
		}
	}

	/**
	 * Allows child collection classes to add further conditions to the fetch sql.
	 * @see fetch()
	 *
	 * @access protected
	 *
	 * return string
	 */
	function condition_sql()
	{
		if (isset($this->condition_sql))
		{
			return $this->condition_sql;
		}

		$this->dateline = intval($this->dateline);
		$this->include_gmid = intval($this->include_gmid);

		return $this->condition_sql = "AND ((gm.dateline > {$this->dateline}) OR gm.gmid = {$this->include_gmid})";
	}
}

/**
 * Group Discussion Collection.
 *
 * Queries a collection of social discussions.
 *
 * @package		vBulletin
 * @copyright 	http://www.vbulletin.com/license.html
 */
class vB_Group_Collection_Discussion extends vB_Group_Collection_Message
{
	/**
	 * Type identifier.
	 * Given to items so it can be checked whenever they are processed.
	 *
	 * @access protected
	 * @var string
	 */
	var $type = 'discussion';

	/**
	 * Name of a view query hook to execute to add to the collection query.
	 *
	 * @access protected
	 * @var string | boolean
	 */
	var $view_query_hook = 'group_view_discussion_query';

	/**
	 * Name of the sort field hook for the item type.
	 *
	 * @access protected
	 * @var array
	 */
	var $sort_field_hook = 'group_discussion_sort_field';

	/**
	 * Whether to check read marking.
	 * @see check_read()
	 *
	 * @access protected
	 * var bool
	 */
	var $check_read = true;

	/**
	 * Whether to hide read discussions.
	 * @see filter_show_read()
	 *
	 * @access protected
	 * var bool
	 */
	var $show_read = true;

	/**
	 * Whether to show only subscribed discussions.
	 * @see filter_show_unsubscribed()
	 *
	 * @access protected
	 * var bool
	 */
	var $show_unsubscribed = true;

	// #######################################################################

	/**
	 * Builds the query to get the collection.
	 * @see fetch()
	 *
	 * @access protected
	 *
	 * @return string							The built SQL
	 */
	function collection_sql($force_refresh = false)
	{
		if ($this->collection_sql)
		{
			return $this->collection_sql;
		}

		if (!$this->sortfield AND $this->filter_searchtext)
		{
			 $this->filter_sort_field('relevance');
		}
		else if (!$this->sortfield
				 OR ('subscribediscussion.emailupdate' == $this->sortfield AND $this->show_unsubscribed)
				 OR ('relevance' == $this->sortfield AND !$this->filter_searchtext))
		{
			$this->filter_sort_field('lastpost');
		}

		// hook to alter query.
		$group = $this->group;	$vbulletin =& $this->registry;
		$hook_query_fields = $hook_query_joins = $hook_query_where = '';
		($hook = vBulletinHook::fetch_hook($this->view_query_hook)) ? eval($hook) : false;

		// build query
		$sql = 		"SELECT SQL_CALC_FOUND_ROWS
						d.*, gm.*, user.*,
						gm.ipaddress AS itemipaddress, d.lastpost AS lastpost, d.lastpostid AS lastpostid";

		if ($deleted_sql = $this->deleted_sql())
		{
			$sql .= "	,deletionlog.userid AS del_userid, deletionlog.username AS del_username,
						deletionlog.reason AS del_reason";
		}

		if ($this->check_read AND $this->show_read)
		{
			$sql .= " ,IF(d.lastpost <= discussionread.readtime,1,0) AS is_read, discussionread.readtime AS readtime";
		}

		if (!$this->show_unsubscribed)
		{
			$sql .= " ,subscribediscussion.emailupdate";
		}

		if ($this->filter_searchtext AND 'relevance' == $this->sortfield)
		{
			$sql .= " ,(MATCH(gm.title, gm.pagetext) AGAINST ('" . $this->registry->db->escape_string($this->filter_searchtext) . "' IN BOOLEAN MODE) ) AS relevance";
		}

		$sql .= "		$hook_query_fields
					FROM " . TABLE_PREFIX . "discussion AS d
					INNER JOIN " . TABLE_PREFIX . "groupmessage AS gm
						ON (gm.gmid = d.firstpostid)
					LEFT JOIN " . TABLE_PREFIX . "user AS user ON (gm.postuserid = user.userid)";

		if ($this->check_read OR !$this->show_read)
		{
			$sql .= " LEFT JOIN " . TABLE_PREFIX . "discussionread AS discussionread
						ON (discussionread.discussionid = d.discussionid
						AND discussionread.userid = " . $this->registry->userinfo['userid'] . ')';
		}

		// if not getting read posts we need to check the group
		if (!$this->show_read)
		{
			$sql .= " LEFT JOIN " . TABLE_PREFIX . "groupread AS groupread
					    ON (groupread.groupid = d.groupid
					    AND groupread.userid = " . $this->registry->userinfo['userid'] . ')';
		}

		// if only getting subscriptions
		if (!$this->show_unsubscribed)
		{
			$sql .= " INNER JOIN " . TABLE_PREFIX . "subscribediscussion AS subscribediscussion
						ON (subscribediscussion.discussionid = d.discussionid
						AND subscribediscussion.userid = " . $this->registry->userinfo['userid'] . ')';
		}

		$sql .= "	$deleted_sql
					$hook_query_joins
					WHERE 1=1";

		if ($this->parent_id)
		{
			$sql .= " AND d.groupid = $this->parent_id";
		}

		if ($this->daysprune)
		{
			$sql .= (($this->daysprune != -1) ? " AND d.lastpost >= " . (TIMENOW - ($this->daysprune * 86400)) : '');
		}

		if (!$this->show_read)
		{
			// posts older than markinglimit days won't be highlighted as new
			$oldtime = TIMENOW - ($this->registry->options['markinglimit'] * 24 * 60 * 60);
			$sql .= " AND d.lastpost > $oldtime AND d.lastpost > COALESCE(groupread.readtime, 0) AND d.lastpost > COALESCE(discussionread.readtime, 0)";
		}

		if ($this->filter_searchtext)
		{
			$sql .= " AND (MATCH(gm.title, gm.pagetext) AGAINST ('" . $this->registry->db->escape_string($this->filter_searchtext) . "' IN BOOLEAN MODE) )";
		}

		if ($this->filter_id)
		{
			$sql .= " AND d.discussionid = " . intval($this->filter_id);
		}

		$sql .= " " . 	$this->state_sql() . " " .
						$this->condition_sql() . "
						$hook_query_where " .
					($this->filter_id ? '' : "ORDER BY " . $this->sortfield . " " . ($this->descending ? "DESC" : "ASC") . " " .
					($this->no_limit ? "" : "LIMIT {$this->start}, {$this->quantity}"));

		return $this->collection_sql = $sql;
	}

	/**
	 * Builds conditions for the message query based on the user's permissions.
	 * @see fetch()
	 *
	 * @access protected
	 *
	 * @return string							The built SQL
	 */
	function state_sql()
	{
		if (isset($this->state_sql))
		{
			return $this->state_sql;
		}

		// Build state conditions for query
		$state = array();
		$state_or = array();

		if ($this->show_visible)
		{
			$state = array('visible');
		}

		if ($this->show_moderated)
		{
			if (fetch_socialgroup_modperm('canmoderatediscussions', $this->group))
			{
				$state[] = 'moderation';
			}
			else if ($this->registry->userinfo['userid'])
			{
				$state_or[] = "(gm.postuserid = " . $this->registry->userinfo['userid'] . " AND state = 'moderation')";
			}
		}

		if ($this->show_deleted)
		{
			if (fetch_socialgroup_modperm('canviewdeleted', $this->group))
			{
				$state[] = 'deleted';
			}
		}

		if (sizeof($state))
		{
			$state_or[] = "gm.state IN ('" . implode("','", $state) . "')";
		}

		if (sizeof($state_or))
		{
			return $this->state_sql = "AND (" . implode(" OR ", $state_or) . ")";
		}

		return $this->state_sql = '';
	}

	/**
	 * Finds the page index based on a given dateline
	 *
	 * @access public
	 *
	 * @return unknown
	 */
	function seek_item($dateline)
	{
		if (!parent::seek_item($dateline))
		{
			return false;
		}

		// Collection is stale
		$this->reset();

		// Find page
		$getpagenum = $db->query_first("
			SELECT COUNT(*) AS comments
			FROM " . TABLE_PREFIX . "discussion AS d
			INNER JOIN " . TABLE_PREFIX . "groupmessage AS gm
				ON gm.gmid = d.firstpostid
			WHERE d.discussionid = $this->parentid " .
				$this->state_sql() . "
				AND dateline " . ($this->descending ? ">" : "<") . "= $dateline
		");

		// if page is full, adding 1 pushes to top of next page
		return $this->page = max(1, ceil(($getpagenum['comments']+1) / $this->quantity));
	}

	// #######################################################################

	/**
	 * Whether to check read marking when fetching.
	 *
	 * @param boolean $check
	 */
	function check_read($check = true)
	{
		if ($check != $this->check_read)
		{
			$this->check_read = ($check AND $this->registry->userinfo['userid']);
			$this->reset();
		}
	}

	/**
	 * Sets the sort field.
	 * Sort by creation dateline, lastpost dateline or creator username
	 *
	 * @access public
	 *
	 * @param string $field						- The field to sort by
	 */
	function filter_sort_field($field)
	{
		$this->filter_sort_field_hook($field);

		if ($field != $this->sortfield)
		{
			if (!$this->sortfield)
			{
				if ('title' == $field)
				{
					$this->sortfield = 'gm.title';
				}
				else if ('username' == $field OR ('author' == $field))
				{
					$this->sortfield = 'gm.postusername';
				}
				else if ('dateline' == $field)
				{
					$this->sortfield = 'gm.dateline';
				}
				else if ('messages' == $field OR 'replies' == $field)
				{
					$this->sortfield = 'd.visible';
				}
				else if ('subscription' == $field)
				{
					$this->sortfield = 'subscribediscussion.emailupdate';
				}
				else if ('relevance' == $field)
				{
					$this->sortfield = 'relevance';
				}
				else
				{
					$this->sortfield = 'd.lastpost';
				}
			}

			$this->reset();
		}
	}

	/**
	 * Sets whether read discussions are selected.
	 *
	 * @access public
	 *
	 * @param bool $show						- Whether to select read discussions
	 */
	function filter_show_read($show = true)
	{
		if ($show != $this->show_read)
		{
			$this->show_read = ($show OR !$this->registry->userinfo['userid']);
			$this->reset();
		}
	}

	/**
	 * Sets whether to show discussions the user is not subscribed to.
	 *
	 * @access public
	 *
	 * @param bool $show
	 */
	function filter_show_unsubscribed($show = true)
	{
		if ($show != $this->show_unsubscribed)
		{
			$this->show_unsubscribed = ($show OR !$this->registry->userinfo['userid']);
			$this->reset();
		}
	}
}

/**
 * Album Collection.
 *
 * Queries a collection of albums.
 *
 * @package		vBulletin
 * @copyright 	http://www.vbulletin.com/license.html
 */
class vB_Collection_Album extends vB_Legacy_Collection
{
	/**
	 * Type identifier.
	 * Given to items so it can be checked whenever they are processed.
	 *
	 * @access protected
	 * @var string
	 */
	var $type = 'album';

	/**
	 * Name of a view query hook to execute to add to the collection query.
	 *
	 * @access protected
	 * @var string | boolean
	 */
	var $view_query_hook = 'album_user_query';

	/**
	 * Whether to show moderated items that the user has permission to.
	 *
	 * @access protected
	 * @var boolean
	 */
	var $show_moderated = false;

	/**
	 * Name of the sort field hook for the item type.
	 *
	 * @access protected
	 * @var array
	 */
	var $sort_field_hook = 'album_sort_field';

	/**
	 * Whether to only get albums that have been auto approved.
	 * Albums are auto approved when a user updates and album and has permission
	 * to display albums at the time of the update.
	 * Updates are limited to a fixed number ($vbulletin->options['albumupdates'])
	 * and will eventually expire.
	 *
	 * @access protected
	 * @var boolean
	 */
	var $only_autoapproved = true;

	/**
	 * Whether ignor emarking is allowed in this class.
	 *
	 * @access protected
	 * @var boolean
	 */
	var $allow_ignore_marking = true;

	// #######################################################################

	/**
	 * Builds the query to get the collection.
	 * @see fetch()
	 *
	 * @access protected
	 *
	 * @param bool $force_refresh				Whether to ignore the cache
	 * @return string							The built SQL
	 */
	function collection_sql($force_refresh = false)
	{
		if ($this->collection_sql AND !$force_refresh)
		{
			return $this->collection_sql;
		}

		if (!$this->sortfield)
		{
			$this->filter_sort_field('lastpicturedate');
		}

		// hook to alter query.
		$vbulletin =& $this->registry;
		$hook_query_fields = $hook_query_joins = $hook_query_where = '';
		($hook = vBulletinHook::fetch_hook($this->view_query_hook)) ? eval($hook) : false;

		// build query
		$sql = "
					SELECT SQL_CALC_FOUND_ROWS
						album.*, user.*, album.lastpicturedate AS dateline,
						IF(filedata.thumbnail_filesize > 0, attachment.attachmentid, 0) AS attachmentid,
						filedata.thumbnail_dateline, filedata.thumbnail_width, filedata.thumbnail_height
						$hook_query_fields
					FROM " . TABLE_PREFIX . "album AS album
					LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = album.userid)
					LEFT JOIN " . TABLE_PREFIX . "profileblockprivacy AS profileblockprivacy ON
						(profileblockprivacy.userid = user.userid AND profileblockprivacy.blockid = 'albums')";

		if ($this->only_autoapproved)
		{
			$sql .= " INNER JOIN " . TABLE_PREFIX . "albumupdate AS albumupdate ON (albumupdate.albumid = album.albumid)";
		}

		$sql .= "	LEFT JOIN " . TABLE_PREFIX . "attachment AS attachment ON (album.coverattachmentid = attachment.attachmentid)
							LEFT JOIN " . TABLE_PREFIX . "filedata AS filedata ON (filedata.filedataid = attachment.filedataid)
					$hook_query_joins
					WHERE 1=1";

		// get albums for a specific user
		if ($this->parent_id)
		{
			$sql .= " AND album.userid = $this->parent_id";
		}
		else
		{
			// get public ones only
			$sql .= " AND (profileblockprivacy.requirement = 0 OR profileblockprivacy.requirement IS NULL)";
		}

		if ($this->daysprune)
		{
			$sql .= (($this->daysprune != -1) ? " AND album.lastpicturedate >= " . (TIMENOW - ($this->daysprune * 86400)) : '');
		}

		if ($this->filter_id)
		{
			$sql .= " AND album.albumid = " . intval($this->filter_id);
		}

		$sql .= 	" "	. $this->state_sql() .
					" " . $this->condition_sql() .
					"	$hook_query_where " .
					($this->filter_id ? '' : "ORDER BY " . $this->sortfield . " " . ($this->descending ? "DESC" : "ASC") . " " .
					($this->no_limit ? "" : "LIMIT {$this->start}, {$this->quantity}"));

		return $this->collection_sql = $sql;
	}

	/**
	 * Builds conditions for the message query based on the user's permissions.
	 * @see fetch()
	 *
	 * @access protected
	 *
	 * @return string							The built SQL
	 */
	function state_sql()
	{
		if (isset($this->state_sql))
		{
			return $this->state_sql;
		}

		// Build state conditions for query
		$state = array('public');

		if ($this->parent_id)
		{
			if (can_view_private_albums($this->parent_id))
			{
				$state[] = 'private';
			}
			if (can_view_profile_albums($this->parent_id))
			{
				$state[] = 'profile';
			}
		}

		$this->state_sql = "AND (album.state IN ('" . implode("','", $state) . "')";

		if ($this->show_moderated AND can_moderate(0, 'canmoderatepictures'))
		{
			$this->state_sql .= 'AND (album.visible > 0 OR album.moderation > 0)';
		}
		else
		{
			$this->state_sql .= 'AND album.visible > 0';
		}

		$this->state_sql .= ')';

		require_once(DIR . '/includes/functions_user.php');
		$privacy_requirement = fetch_user_relationship($this->parent_id, $this->registry->userinfo['userid']);
		$this->state_sql .= " AND (profileblockprivacy.requirement <= " . intval($privacy_requirement) . " OR profileblockprivacy.requirement IS NULL)";

		return $this->state_sql;
	}

	// #######################################################################

	/**
	 * Finds the page index based on a given dateline
	 *
	 * @access public
	 *
	 * @return unknown
	 */
	function seek_item($dateline)
	{
		if (!parent::seek_item($dateline))
		{
			return false;
		}

		// Collection is stale
		$this->reset();

		$sql = "SELECT COUNT(*) AS total
			FROM " . TABLE_PREFIX . "album AS album";

		if ($this->only_autoapproved)
		{
			$sql .= "INNER JOIN " . TABLE_PREFIX . "albumupdate AS albumupdate";
		}

		$sql .= " WHERE 1=1 ";

		if ($this->parent_id)
		{
			$sql .= " AND userid = $this->parent_id ";
		}

		$sql .=	$this->state_sql() . "
				AND lastpicturedate " . ($this->descending ? ">" : "<") . " = $dateline
		";

		$getpagenum = $this->registry->db->query_first($sql);

		// if page is full, adding 1 pushes to top of next page
		return $this->page = max(1, ceil(($getpagenum['total']+1) / $this->quantity));
	}

	// #######################################################################

	/**
	 * Sets whether to show moderated items that the user has permission to.
	 *
	 * @access public
	 *
	 * @param boolean $show
	 */
	function filter_show_moderated($show = true)
	{
		if($show != $this->show_moderated)
		{
			$this->show_moderated = $show;
			$this->reset();
		}
	}

	/**
	 * Sets the sort field.
	 * Child classes should validate the field and prefix.* the appropriate table alias.
	 *
	 * @access public
	 *
	 * @param string $field						- The field to sort by
	 */
	function filter_sort_field($field)
	{
		$this->filter_sort_field_hook($field);

		if (!$this->sortfield)
		{
			$this->sortfield = ($this->only_autoapproved ? 'albumupdate.dateline' : 'album.lastpicturedate');
			$this->sortfield = (('username' == $field) ? 'user.username' : $this->sortfield);
		}
	}

	/**
	 * Whether albums must have been autoapproved.
	 *
	 * @param boolean $show
	 */
	function filter_auto_approved($show = true)
	{
		if ($show != $this->only_autoapproved)
		{
			$this->only_autoapproved = $show;
			$this->reset();
		}
	}
}

/**
 * Group Category Collection.
 *
 * Queries a collection of social group categories.
 *
 * @package		vBulletin
 * @copyright 	http://www.vbulletin.com/license.html
 */
class vB_Collection_GroupCategory extends vB_Legacy_Collection
{
	/**
	 * Type identifier.
	 * Given to items so it can be checked whenever they are processed.
	 *
	 * @access protected
	 * @var string
	 */
	var $type = 'groupcategory';

	/**
	 * Name of a view query hook to execute to add to the collection query.
	 *
	 * @access protected
	 * @var string | boolean
	 */
	var $view_query_hook = 'group_view_categories_query';

	/**
	 * Name of the sort field hook for the item type.
	 *
	 * @access protected
	 * @var array
	 */
	var $sort_field_hook = 'group_category_sort_field';

	// #######################################################################

	/**
	 * Builds the query to get the collection.
	 * @see fetch()
	 *
	 * @access protected
	 *
	 * @param bool $force_refresh				Whether to ignore the cache
	 * @return string							The built SQL
	 */
	function collection_sql($force_refresh = false)
	{
		if ($this->collection_sql AND !$force_refresh)
		{
			return $this->collection_sql;
		}

		if (!$this->sortfield)
		{
			$this->filter_sort_field('title');
		}

		// hook to alter query.
		$vbulletin =& $this->registry;
		$hook_query_fields = $hook_query_joins = $hook_query_where = '';
		($hook = vBulletinHook::fetch_hook($this->view_query_hook)) ? eval($hook) : false;

		// build query
		$sql = "SELECT SQL_CALC_FOUND_ROWS
				cat.socialgroupcategoryid AS categoryid, cat.title, cat.description, COUNT(socialgroup.groupid) AS groups
				$hook_query_fields
			FROM " . TABLE_PREFIX . "socialgroupcategory AS cat
			LEFT JOIN " . TABLE_PREFIX . "socialgroup AS socialgroup
			 ON (socialgroup.socialgroupcategoryid = cat.socialgroupcategoryid)
			 $hook_query_joins
			WHERE socialgroup.groupid IS NOT NULL " .
			 ($this->filter_id ? " AND cat.socialgroupcategoryid = " . intval($this->filter_id) : '') .
			 $this->state_sql() .
			 $this->condition_sql() . "
			 $hook_query_where
			GROUP BY cat.socialgroupcategoryid " .
			($this->filter_id ? '' : "ORDER BY " . $this->sortfield . " " . ($this->descending ? "DESC" : "ASC") . " " .
			($this->no_limit ? "" : "LIMIT {$this->start}, {$this->quantity}"));

		return $this->collection_sql = $sql;
	}

	// #######################################################################

	/**
	 * Sets the sort field.
	 * Child classes should validate the field and prefix.* the appropriate table alias.
	 *
	 * @access public
	 *
	 * @param string $field						- The field to sort by
	 */
	function filter_sort_field($field)
	{
		$this->filter_sort_field_hook($field);

		if (!$this->sortfield)
		{
			$this->sortfield = ('groups' == $field ? 'groups' : 'cat.title');
		}
	}
}


// #######################################################################


/**
* Bit Factory.
* Fetches the appropriate class for rendering a content item's template bit.
*
* @package 		vBulletin
* @copyright 	http://www.vbulletin.com/license.html
*/
class vB_Bit_Factory
{
	/**
	* Registry object
	*
	* @access protected
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* BB code parser object (if necessary)
	*
	* @access protected
	* @var	vB_BbCodeParser
	*/
	var $bbcode = null;

	/**
	* Permission cache for various users.
	*
	* @access protected
	* @var	array
	*/
	var $perm_cache = array();

	/**
	 * Item types that the factory supports.
	 *
	 * @access protected
	 * @var array
	 */
	var $types = array('album', 'groupcategory');

	/**
	 * Class prefix for created collections
	 *
	 * @access protected
	 * @var string
	 */
	var $class_prefix = 'vB_Bit_';

	// #######################################################################

	/**
	* Constructor, sets up the object.
	*
	* @access public
	*
	* @param	vB_Registry
	*/
	function vB_Bit_Factory(&$registry)
	{
		if (is_object($registry))
		{
			$this->registry =& $registry;
		}
		else
		{
			trigger_error("vB_Bit_Factory::Registry object is not an object", E_USER_ERROR);
		}
	}

	/**
	* Create a bit renderer for the specified item.
	*
	* @access public
	*
	* @param	array							item information
	* @return	vB_Bit
	*/
	function create_instance($item)
	{
		// Hook to allow custom items
		($hook = vBulletinHook::fetch_hook('bit_factory_create')) ? eval($hook) : false;

		if (empty($item['type']))
		{
			trigger_error("vB_Bit_Factory::create(): Given item does not have a type set", E_USER_ERROR);
		}

		if (!in_array($item['type'], $this->types))
		{
			trigger_error("vB_Bit_Factory::create(): Item type not recognised", E_USER_ERROR);
		}

		$class_name = $this->get_class_name($item);

		// Create the collection handler
		if (class_exists($class_name, false))
		{
			return $this->instantiate($class_name, $item);
		}
		else
		{
			trigger_error('vB_Collection_Factory::create(): Invalid type ' . htmlspecialchars_uni($class_name) . '.', E_USER_ERROR);
		}
	}

	/**
	 * Resolves the appropriate class name for the required Bit object
	 *
	 * @param	mixed array $item				Item info array
	 * @return	string
	 */
	function get_class_name($item)
	{
		return 'vB_Bit_' . ucfirst($item['type']);
	}

	/**
	 * Instantiates the required bit object.
	 *
	 * @access protected
	 * @see vB_Bit
	 *
	 * @param string $class_name				The resolved name of the class to instantate
	 * @param array mixed $item					Information about the item being rendered
	 * @return vB_Bit							The appropriate bit handler
	 */
	function instantiate($class_name, $item)
	{
		return new $class_name($this->registry, $this, $item);
	}
}


/**
* Generic bit class.
* Bit classes render the template bit for the given content item.
* Use the bit factory to resolve and fetch the appropriate bit class for a
* content item.
* @see vB_Bit_Factory
*
* @package 		vBulletin
* @copyright 	http://www.vbulletin.com/license.html
*
* @abstract
*/
class vB_Bit
{
	/**
	* Registry object
	*
	* @access protected
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* Factory object that created this object. Used for permission caching.
	*
	* @access protected
	* @var	vB_Bit_Factory
	*/
	var $factory = null;

	/**
	* BB code parser object (if necessary)
	*
	* @access protected
	* @var	vB_BbCodeParser
	*/
	var $bbcode = null;

	/**
	* Cached information from the BB code parser
	*
	* @access protected
	* @var	array
	*/
	var $parsed_cache = array();

	/**
	* Information about the content item
	*
	* @access protected
	* @var	array
	*/
	var $item = array();

	/**
	* The template that will be used for outputting
	*
	* @access protected
	* @var	string
	*/
	var $template = '';

	/**
	 * Hook run at start of template bit
	 *
	 * @access protected
	 * @var string
	 */
	var $hook_display_start;

	/**
	 * Hook run at end of template bit
	 *
	 * @access protected
	 * @var string
	 */
	var $hook_display_complete;

	/**
	 * Whether to include information about the item user
	 *
	 * @access protected
	 * @var boolean
	 */
	var $process_user = true;

	/**
	 * Whether to fetch avatar info for the item's author
	 *
	 * @access protected
	 * @var boolean
	 */
	var $use_avatar;

	/**
	 * Whether to show moderation details.
	 * If this is false, edit links and inlinemod will be hidden.
	 * @see show_moderation()
	 *
	 * @access protected
	 * @var boolean
	 */
	var $show_moderation_tools = true;

	/**
	 * The variable name used in templates for the item
	 */
	var $template_item_var;

	/**
	 *	Some additional data to push to the template
	 */
	var $template_data_vars = array();

	/**
	 * Whether to force inline tools.
	 * This is useful when checkboxes are required for something other than inline
	 * moderation, such as selecting items to change subscription settings on.
	 *
	 * @access protected
	 * @var bool
	 */
	var $force_inline_selection = false;

	// #######################################################################

	/**
	* Constructor, sets up the object.
	*
	* @access public
	*
	* @param	vB_Registry
	* @param	vB_BbCodeParser
	* @param	vB_Group_MessagFactory
	* @param	array							User info
	* @param	array							Message info
	*/
	function vB_Bit(&$registry, &$factory, $item)
	{
		if (!is_subclass_of($this, 'vB_Bit'))
		{
			trigger_error('Direct instantiation of vB_Bit class prohibited. Use the vB_Bit_Factory class.', E_USER_ERROR);
		}

		if (is_object($registry))
		{
			$this->registry =& $registry;
		}
		else
		{
			trigger_error("vB_Bit::Registry object is not an object", E_USER_ERROR);
		}

		$this->factory =& $factory;
		$this->item = $item;

		$this->bbcode = new vB_BbCodeParser($registry, fetch_tag_list());
	}

	/**
	* Template method that does all the work to render the item, including processing the template
	*
	* @access public
	*
	* @return	string	Templated note output
	*/
	function construct()
	{
		if ($this->hook_display_start)
		{
			($hook = vBulletinHook::fetch_hook($this->hook_display_start)) ? eval($hook) : false;
		}

		// preparation for display...
		$this->prepare_start();

		if ($this->process_user)
		{
			if ($this->item['userid'])
			{
				$this->process_registered_user();
			}
			else
			{
				$this->process_unregistered_user();
			}
		}

		if ($this->use_avatar)
		{
			fetch_avatar_from_userinfo($this->item, true);
		}

		$this->process_date_status();
		$this->process_display();
		$this->process_text();
		$this->prepare_end();

		// actual display...
		foreach ($this->template_data_vars as $varname)
		{
			${$varname} = $this->$varname;
		}

		global $show, $vbphrase;
		global $spacer_open, $spacer_close;
		global $perpage, $pagenumber;

		global $bgclass, $altbgclass;
		exec_switch_bg();

		if ($this->hook_display_complete)
		{
			($hook = vBulletinHook::fetch_hook($this->hook_display_complete)) ? eval($hook) : false;
		}

		$templater = vB_Template::create($this->template);

		if ($this->template_item_var)
		{
			$templater->register($this->template_item_var, $this->item);
		}
		$templater->register('pagenumber', $pagenumber);
		$templater->register('perpage', $perpage);
		$templater->register('template_hook', $template_hook);

		return $templater->render();
	}

	/**
	* Any startup work that needs to be done.
	*
	* @access protected
	*/
	function prepare_start()
	{
		$this->item = array_merge($this->item, convert_bits_to_array($this->item['options'], $this->registry->bf_misc_useroptions));
		$this->item = array_merge($this->item, convert_bits_to_array($this->item['adminoptions'], $this->registry->bf_misc_adminoptions));
	}

	/**
	* Process note as if a registered user posted
	*
	* @access protected
	*/
	function process_registered_user()
	{
		global $show, $vbphrase;

		fetch_musername($this->item);

		$this->item['onlinestatus'] = 0;
		// now decide if we can see the user or not
		if ($this->item['lastactivity'] > (TIMENOW - $this->registry->options['cookietimeout']) AND $this->item['lastvisit'] != $this->item['lastactivity'])
		{
			if ($this->item['invisible'])
			{
				if (($this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canseehidden']) OR $this->item['userid'] == $this->registry->userinfo['userid'])
				{
					// user is online and invisible BUT bbuser can see them
					$this->item['onlinestatus'] = 2;
				}
			}
			else
			{
				// user is online and visible
				$this->item['onlinestatus'] = 1;
			}
		}

		if (!isset($this->factory->perm_cache["{$this->item['userid']}"]))
		{
			$this->factory->perm_cache["{$this->item['userid']}"] = cache_permissions($this->item, false);
		}

		if (   // item doesn't use avatars
			!$this->use_avatar
			OR // no avatar defined for this user
			empty($this->item['avatarurl'])
			OR // visitor doesn't want to see avatars
			($this->registry->userinfo['userid'] > 0 AND !$this->registry->userinfo['showavatars'])
			OR // user has a custom avatar but no permission to display it
			(!$this->item['avatarid'] AND !($this->factory->perm_cache["{$this->item['userid']}"]['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canuseavatar']) AND !$this->item['adminavatar']) //
		)
		{
			$show['avatar'] = false;
		}
		else
		{
			$show['avatar'] = true;
		}

		$show['emaillink'] = (
			$this->item['showemail'] AND $this->registry->options['displayemails'] AND (
				!$this->registry->options['secureemail'] OR (
					$this->registry->options['secureemail'] AND $this->registry->options['enableemail']
				)
			) AND $this->registry->userinfo['permissions']['genericpermissions'] & $this->registry->bf_ugp_genericpermissions['canemailmember']
		);
		$show['homepage'] = ($this->item['homepage'] != '' AND $this->item['homepage'] != 'http://');
		$show['pmlink'] = ($this->registry->options['enablepms'] AND $this->registry->userinfo['permissions']['pmquota'] AND ($this->registry->userinfo['permissions']['adminpermissions'] & $this->registry->bf_ugp_adminpermissions['cancontrolpanel']
	 					OR ($this->item['receivepm'] AND $this->factory->perm_cache["{$this->userinfo['userid']}"]['pmquota'])
	 				)) ? true : false;
	}

	/**
	* Process note as if an unregistered user posted
	*
	* @access protected
	*/
	function process_unregistered_user()
	{
		$this->item['rank'] = '';
		$this->item['notesperday'] = 0;
		$this->item['displaygroupid'] = 1;
		$this->item['username'] = $this->item['postusername'];
		fetch_musername($this->item);
		$this->item['usertitle'] = $this->registry->usergroupcache['1']['usertitle'];
		$this->item['joindate'] = '';
		$this->item['notes'] = 'n/a';
		$this->item['avatar'] = '';
		$this->item['profile'] = '';
		$this->item['email'] = '';
		$this->item['useremail'] = '';
		$this->item['icqicon'] = '';
		$this->item['aimicon'] = '';
		$this->item['yahooicon'] = '';
		$this->item['msnicon'] = '';
		$this->item['skypeicon'] = '';
		$this->item['homepage'] = '';
		$this->item['findnotes'] = '';
		$this->item['signature'] = '';
		$this->item['reputationdisplay'] = '';
		$this->item['onlinestatus'] = '';
		$this->item['showemail'] = false;
	}

	/**
	* Prepare the text for display
	*
	* @access protected
	*/
	function process_text(){}

	/**
	* Any closing work to be done.
	*
	* @access protected
	*/
	function prepare_end(){}

	/**
	 * Create Human readable Dates and Times
	 *
	 * @access protected
	 */
	function process_date_status()
	{
		if (isset($this->item['dateline']))
		{
			$this->item['date'] = vbdate($this->registry->options['dateformat'], $this->item['dateline'], true);
			$this->item['time'] = vbdate($this->registry->options['timeformat'], $this->item['dateline']);
		}
	}

	/**
	 * Sets up different display variables for the Group Item
	 *
	 * @access protected
	 */
	function process_display(){}

	/**
	 * Sets whether to show moderation details.
	 *
	 * @access public
	 *
	 * @var boolean
	 */
	function show_moderation_tools($show = true)
	{
		$this->show_moderation_tools = $show;
	}

	/**
	 * Sets whether to force inline selection.
	 *
	 * @access public
	 *
	 * @param boolean $force
	 */
	function force_inline_selection($force = true)
	{
		$this->force_inline_selection = $force;
	}

	/**
	 * Sets an alternative template to use
	 *
	 * @param string $template
	 */
	function set_template($template)
	{
		$this->template = $template;
	}
}


/**
* Group Bit Factory.
* Fetches the appropriate class for rendering a group content item's template bit.
*
* @package 		vBulletin
* @copyright 	http://www.vbulletin.com/license.html
*
*/
class vB_Group_Bit_Factory extends vB_Bit_Factory
{
	/**
	 * Item types that the factory supports.
	 *
	 * @access protected
	 * @var array
	 */
	var $types = array('discussion', 'message');

	/**
	 * Class prefix for created collections
	 *
	 * @access protected
	 * @var string
	 */
	var $class_prefix = 'vB_Group_Bit_';

	/**
	 * Information about the social group
	 *
	 * @access protected
	 * @var array mixed
	 */
	var $group;

	// #######################################################################

	/**
	* Create a bit renderer for the specified item.
	*
	* @access public
	*
	* @param	array							item information
	* @return	vB_Group_Bit
	*/
	function create($item, $group)
	{
		$this->group = $group;

		return parent::create_instance($item);
	}

	/**
	 * Resolves the appropriate class name for the required Bit object
	 *
	 * @param	mixed array $item				Item info array
	 * @return	string
	 */
	function get_class_name($item)
	{
		$class_name = $this->class_prefix . ucfirst($item['type']);

		if ($item['issearch'])
		{
			return 	$class_name .= '_Search';
		}

		switch ($item['state'])
		{
			case 'deleted':
				$class_name .= '_Deleted';
				break;

			case 'moderation':
			case 'visible':
			default:
				if (!empty($item['ignored']))
				{
					$class_name .= '_Ignored';
				}
		}

		return $class_name;
	}

	/**
	 * Instantiates the required bit object.
	 *
	 * @access protected
	 * @see vB_Bit
	 *
	 * @param string $class_name				The resolved name of the class to instantate
	 * @param array mixed $item					Information about the item being rendered
	 * @return vB_Bit							The appropriate bit handler
	 */
	function instantiate($class_name, $item)
	{
		return new $class_name($this->registry, $this, $item, $this->group);
	}
}


/**
 * Album bit class.
 *
 * @package 	vBulletin
 * @copyright	http://www.vbulletin.com/license.html
 */
class vB_Bit_Album extends vB_Bit
{
	/**
	* The template that will be used for outputting
	*
	* @access protected
	* @var	string
	*/
	var $template = 'albumbit';

	/**
	 * Hook run at start of template bit
	 *
	 * @access protected
	 * @var string
	 */
	var $hook_display_start = 'albumbit_display_start';

	/**
	 * Hook run at end of template bit
	 *
	 * @access protected
	 * @var string
	 */
	var $hook_display_complete = 'albumbit_display_complete';

	/**
	 * The variable name used in templates for the item
	 *
	 * @access protected
	 * @var string
	 */
	var $template_item_var = 'album';

	// #######################################################################

	/**
	 * Created Human readable Dates and Times
	 *
	 * @access protected
	 */
	function process_date_status()
	{
		$this->item['picturedate'] = vbdate($this->registry->options['dateformat'], $this->item['lastpicturedate'], true);
		$this->item['picturetime'] = vbdate($this->registry->options['timeformat'], $this->item['lastpicturedate']);
	}

	/**
	* Prepare the text for display
	*
	* @access protected
	*/
	function process_text()
	{
		parent::process_text();

		$this->item['description_html'] = nl2br(fetch_word_wrapped_string(fetch_censored_text($this->item['description'])));
		$this->item['title_html'] = fetch_word_wrapped_string(fetch_censored_text($this->item['title']));
	}

	/**
	 * Sets up different display variables for the Group Message
	 *
	 * @access protected
	 */
	function process_display()
	{
		global $show, $vbphrase;

		// Simplify moderation for templating
		$this->item['picturecount'] = vb_number_format($this->item['visible']);

		// Get cover image info
		$this->item['coverthumburl'] = ($this->item['attachmentid'] ? 'attachment.php?' . $this->registry->session->vars['sessionurl'] . "albumid={$this->item['albumid']}&attachmentid={$this->item['attachmentid']}&thumb=1&d={$this->item['thumbnail_dateline']}" : '');
		$this->item['coverdimensions'] = ($this->item['thumbnail_width'] ? "width=\"{$this->item[thumbnail_width]}px\" height=\"{$this->item[thumbnail_height]}px\"" : '');
		if (defined('VB_API') AND VB_API === true)
		{
			if ($this->item['coverthumburl'])
			{
				$this->item['pictureurl'] = create_full_url($this->item['coverthumburl']);
			}
			else
			{
				$this->item['pictureurl'] = '';
			}
		}

		// Display album type
		if ('private' == $this->item['state'])
		{
			$show['personalalbum'] = true;
			$this->item['albumtype'] = $vbphrase['private_album_paren'];
		}
		else if ('profile' == $this->item['state'])
		{
			$show['personalalbum'] = true;
			$this->item['albumtype'] = $vbphrase['profile_album_paren'];
		}
		else
		{
			$show['personalalbum'] = false;
		}

		// Show moderation details
		if ($this->item['moderation'] AND (can_moderate(0, 'canmoderatepictures') OR $vbulletin->userinfo['userid'] == $this->item['userid']))
		{
			$show['moderated'] = true;
			$this->item['moderatedcount'] = vb_number_format($this->item['moderation']);
		}
		else
		{
			$show['moderated'] = false;
		}
	}
}


/**
 * Social Group Category bit class.
 *
 * @package 	vBulletin
 * @copyright	http://www.vbulletin.com/license.html
 */
class vB_Bit_GroupCategory extends vB_Bit
{
	/**
	* The template that will be used for outputting
	*
	* @access protected
	* @var	string
	*/
	var $template = 'socialgroups_categorylist_bit';

	/**
	 * Hook run at start of template bit
	 *
	 * @access protected
	 * @var string
	 */
	var $hook_display_start = 'categorylist_bit_display_start';

	/**
	 * Hook run at end of template bit
	 *
	 * @access protected
	 * @var string
	 */
	var $hook_display_complete = 'categorylist_bit_display_complete';

	/**
	 * The variable name used in templates for the item
	 *
	 * @access protected
	 * @var string
	 */
	var $template_item_var = 'category';

	/**
	 * Whether to include information about the item user
	 *
	 * @access protected
	 * @var boolean
	 */
	var $process_user = false;

	// #######################################################################

	/**
	* Prepare the text for display
	*
	* @access protected
	*/
	function process_text()
	{
		parent::process_text();

		$this->item['description'] = nl2br(fetch_word_wrapped_string(fetch_censored_text($this->item['description'])));
		$this->item['title'] = fetch_word_wrapped_string(fetch_censored_text($this->item['title']));
	}

	/**
	 * Sets up different display variables for the Group Message
	 *
	 * @access protected
	 */
	function process_display()
	{
		global $show, $vbphrase;

		// Simplify moderation for templating
		$this->item['groups'] = vb_number_format($this->item['groups']);
	}
}


/**
* Group bit class.
* Group bit classes render the template bit for the given group content item.
* Use the group bit factory to resolve and fetch the appropriate bit class for a
* group content item.
* @see vB_Group_Bit_Factory
*
* @package 		vBulletin
* @copyright 	http://www.vbulletin.com/license.html
*
* @abstract
*/
class vB_Group_Bit extends vB_Bit
{
	/**
	* Information about the group this message belongs to
	*
	* @access protected
	* @var	array
	*/
	var $group = array();

	// #######################################################################

	/**
	* Constructor, sets up the object.
	*
	* @access public
	*
	* @param	vB_Registry
	* @param	vB_Group_Bit_Factory
	* @param	array							Item info
	* @param	array							Group info
	*/
	function vB_Group_Bit(&$registry, &$factory, $item, $group)
	{
		parent::vB_Bit($registry, $factory, $item);
		$this->group = $group;
	}

	/**
	* Template method that does all the work to render the item, including processing the template
	*
	* @access public
	*
	* @return	string	Templated note output
	*/
	function construct()
	{
		if (isset($this->group))
		{
			$group = $this->group;
		}

		return parent::construct();
	}

	/**
	* Any startup work that needs to be done.
	*
	* @access protected
	*/
	function prepare_start()
	{
		parent::prepare_start();

		$this->item['checkbox_value'] = 0;
		$this->item['checkbox_value'] += ($this->item['state'] == 'moderation') ? POST_FLAG_INVISIBLE : 0;
		$this->item['checkbox_value'] += ($this->item['state'] == 'deleted') ? POST_FLAG_DELETED : 0;
	}
}


/**
 * Group message bit class.
 *
 * @package 		vBulletin
 * @copyright 	http://www.vbulletin.com/license.html
 */
class vB_Group_Bit_Message extends vB_Group_Bit
{
	/**
	* The template that will be used for outputting
	*
	* @access protected
	* @var	string
	*/
	var $template = 'socialgroups_message';

	/**
	 * Hook run at start of template bit
	 *
	 * @access protected
	 * @var string
	 */
	var $hook_display_start = 'group_messagebit_display_start';

	/**
	 * Hook run at end of template bit
	 *
	 * @access protected
	 * @var string
	 */
	var $hook_display_complete = 'group_messagebit_display_complete';

	/**
	 * Whether to fetch avatar info for the item's author
	 *
	 * @access protected
	 * @var boolean
	 */
	var $use_avatar = true;

	/**
	 * The variable name used in templates for the item
	 */
	var $template_item_var = 'message';

	/**
	 * Sets up different display variables for the Group Message
	 *
	 * @access protected
	 */
	function process_display()
	{
		global $show;

		$this->discussion = fetch_socialdiscussioninfo($this->item['discussionid']);
		$this->group = fetch_socialgroupinfo($this->discussion['groupid']);

		$this->item['is_discussion'] =  ($this->item['gmid'] == $this->discussion['firstpostid']);

		$show['moderation'] = ($this->item['state'] == 'moderation');

		if ($this->show_moderation_tools AND !$this->force_inline_selection)
		{
			if ($this->item['is_discussion'])
			{
				$this->item['inlinemod'] = (
					(
						$this->item['state'] != 'moderation'
						OR fetch_socialgroup_modperm('canmoderatediscussions', $this->group)
					)
					AND
					(
						$this->item['state'] != 'deleted'
						OR fetch_socialgroup_modperm('canundeletediscussions', $this->group)
					)
					AND
					(
						fetch_socialgroup_modperm('canmoderatediscussions')
						OR fetch_socialgroup_modperm('candeletediscussions', $this->group)
						OR fetch_socialgroup_modperm('canremovediscussions', $this->group)
					)
				);
			}
			else
			{
				$this->item['inlinemod'] = (
					(
						$this->item['state'] != 'deleted'
						OR fetch_socialgroup_modperm('canundeletegroupmessages', $this->group)
					)
					AND
					(
						$this->item['state'] != 'moderated'
						OR fetch_socialgroup_modperm('canmoderategroupmessages', $this->group)
					)
					AND
					(
						fetch_socialgroup_modperm('canmoderategroupmessages', $this->group)
						OR fetch_socialgroup_modperm('canundeletegroupmessages', $this->group)
						OR fetch_socialgroup_modperm('canremovegroupmessages', $this->group)
					)
				);
			}
		}
		else
		{
			$this->item['inlinemod'] = $this->force_inline_selection;
		}

		if ($this->show_moderation_tools)
		{
			if ($this->item['is_discussion'])
			{
				$this->item['edit'] = (can_edit_group_discussion($this->discussion) OR can_edit_group_message($this->item, $this->group));
			}
			else
			{
				$this->item['edit'] = can_edit_group_message($this->item, $this->group);
			}
		}
		else
		{
			$show['edit'] = $this->item['edit'] = false;
		}

		// legacy
		$show['inlinemod'] = $this->item['inlinemod'];
		$show['edit'] = $this->item['edit'];
	}

	// #######################################################################

	/**
	* Prepare the text for display
	*
	* @access protected
	*/
	function process_text()
	{
		$this->item['message'] = $this->bbcode->parse(
			$this->item['pagetext'],
			'socialmessage',
			$this->item['allowsmilie']
		);
		$this->parsed_cache = $this->bbcode->cached;

		if (!empty($this->item['del_reason']))
		{
			$this->item['del_reason'] = fetch_censored_text($this->item['del_reason']);
		}

		$this->item['groupid'] = $this->group['groupid'];
		$this->item['groupname'] = $this->group['name'];
		$this->item['discussiontitle'] = $this->discussion['title'];
	}

	/**
	* Any closing work to be done.
	*
	* @access protected
	*/
	function prepare_end()
	{
		global $show;

		global $onload, $itemid;

		if (can_moderate(0, 'canviewips'))
		{
			$this->item['itemipaddress'] = ($this->item['itemipaddress'] ? htmlspecialchars_uni(long2ip($this->item['itemipaddress'])) : '');
		}
		else
		{
			$this->item['itemipaddress'] = '';
		}

		$show['reportlink'] = (
			$this->registry->userinfo['userid']
			AND ($this->registry->options['rpforumid'] OR
				($this->registry->options['enableemail'] AND $this->registry->options['rpemail']))
		);
	}
}


/**
* Search result message bit class.
*
* @package 		vBulletin
* @copyright 	http://www.vbulletin.com/license.html
*
*/
class vB_Group_Bit_Message_Search extends vB_Group_Bit_Message
{
	/**
	* The template that will be used for outputting
	*
	* @access protected
	* @var	string
	*/
	var $template = 'search_results_socialgroup_message';

	/**
	 * Whether to fetch avatar info for the item's author
	 *
	 * @access protected
	 * @var boolean
	 */
	var $use_avatar = false;
}


/**
* Deleted message bit class.
*
* @package 		vBulletin
* @copyright 	http://www.vbulletin.com/license.html
*
*/
class vB_Group_Bit_Message_Deleted extends vB_Group_Bit_Message
{
	/**
	* The template that will be used for outputting
	*
	* @access protected
	* @var	string
	*/
	var $template = 'socialgroups_message_deleted';

	/**
	 * Whether to fetch avatar info for the item's author
	 *
	 * @access protected
	 * @var boolean
	 */
	var $use_avatar = false;
}


/**
* Ignored message bit class.
*
* @package 		vBulletin
* @copyright 	http://www.vbulletin.com/license.html
*
*/
class vB_Group_Bit_Message_Ignored extends vB_Group_Bit_Message
{
	/**
	* The template that will be used for outputting
	*
	* @access protected
	* @var	string
	*/
	var $template = 'socialgroups_message_ignored';

	/**
	 * Whether to fetch avatar info for the item's author
	 *
	 * @access protected
	 * @var boolean
	 */
	var $use_avatar = false;
}


/**
 * Group discussion bit class.
 *
 * @package 	vBulletin
 * @copyright	http://www.vbulletin.com/license.html
 */
class vB_Group_Bit_Discussion extends vB_Group_Bit
{
	/**
	* The template that will be used for outputting
	*
	* @access protected
	* @var	string
	*/
	var $template = 'socialgroups_discussion';

	/**
	 * Hook run at start of template bit
	 *
	 * @access protected
	 * @var string
	 */
	var $hook_display_start = 'group_discussionbit_display_start';

	/**
	 * Hook run at end of template bit
	 *
	 * @access protected
	 * @var string
	 */
	var $hook_display_complete = 'group_discussionbit_display_complete';

	/**
	 * Whether to check read status of the discussion
	 *
	 * @access protected
	 * @var boolean
	 */
	var $check_read = true;

	/**
	 * Whether to show subscription info
	 *
	 * @access protected
	 * @var boolean
	 */
	var $show_subscription = false;

	/**
	 * The variable name used in templates for the item
	 *
	 * @access protected
	 * @var string
	 */
	var $template_item_var = 'discussion';

	var $template_data_vars = array('group');

	/**
	 * Whether to fetch avatar info for the item's author
	 *
	 * @access protected
	 * @var boolean
	 */
	var $use_avatar = false;

	// #######################################################################

	/**
	* Template method that does all the work to render the item, including processing the template
	*
	* @access public
	*
	* @return	string	Templated note output
	*/
	function construct()
	{
		if (isset($this->discussion))
		{
			$discussion = $this->discussion;
		}

		return parent::construct();
	}

	/**
	 * Created Human readable Dates and Times
	 *
	 * @access protected
	 */
	function process_date_status()
	{
		parent::process_date_status();
		$this->item['lastpostdate'] = vbdate($this->registry->options['dateformat'], $this->item['lastpost'], true);
		$this->item['lastposttime'] = vbdate($this->registry->options['timeformat'], $this->item['lastpost']);
	}

	/**
	* Prepare the text for display
	*
	* @access protected
	*/
	function process_text()
	{
		parent::process_text();

		$this->item['title'] = fetch_censored_text($this->item['title']);
		$this->item['trimmessage'] = htmlspecialchars_uni(fetch_trimmed_title(fetch_censored_text(strip_bbcode($this->item['pagetext'],false,true)), 100));

		$this->item['groupname'] = $this->group['name'];
	}

	/**
	 * Sets up different display variables for the Group Message
	 *
	 * @access protected
	 */
	function process_display()
	{
		global $show, $vbphrase;

		$this->item['canview'] = ($this->item['state'] == 'visible'
							OR (
								($this->item['state'] == 'deleted')
								AND fetch_socialgroup_modperm('canundeletediscussions', $this->group)
								)
							OR (
								$this->item['state'] == 'moderation'
								AND fetch_socialgroup_modperm('canmoderatediscussions', $this->group)
								)
							);

		// Simplify moderation for templating
		if (fetch_socialgroup_modperm('canmoderategroupmessages', $this->group))
		{
			$this->item['moderated_replies'] = ($this->item['moderation'] > 1 OR ($this->item['state'] != 'moderation' AND $this->item['moderation'] == 1));
		}
		else
		{
			$this->item['moderated_replies'] = 0;
		}
		$this->item['moderated'] = ($this->item['state'] == 'moderation');

		// Show inline selection tools
		if ($this->show_moderation_tools AND !$this->force_inline_selection)
		{
			$this->item['inlinemod'] = (
				(
					$this->item['state'] != 'deleted'
					AND fetch_socialgroup_modperm('canmoderatediscussions', $this->group)
				)
				OR fetch_socialgroup_modperm('canundeletediscussions', $this->group)
				OR fetch_socialgroup_modperm('canremovediscussions', $this->group)
			);

			$show['inlinemod'] = ($show['inlinemod'] OR $this->item['inlinemod']);
		}
		else
		{
			$show['inlinemod'] = $this->item['inlinemod'] = $this->force_inline_selection;
		}

		// Show edit links
		$this->item['edit'] = ($this->show_moderation_tools AND can_edit_group_discussion($this->item, $this->group));
		$show['edit'] = $this->item['edit'];

		if ($this->check_read)
		{
			if (!$this->item['is_read'])
			{
				if (!$this->item['readtime'])
				{
					$this->item['readtime'] = 0;

					// no database marking, check cookie
					if (!$this->registry->options['threadmarking'] OR !$this->registry->userinfo['userid'])
					{
						$this->item['readtime'] = max(
							fetch_bbarray_cookie('discussion_marking', $this->item['discussionid']),
							$this->registry->userinfo['lastvisit']
						);
					}
				}

				// posts older than markinglimit days won't be highlighted as new
				$oldtime = (TIMENOW - ($this->registry->options['markinglimit'] * 24 * 60 * 60));
				$this->item['readtime'] = max($this->group['readtime'], $this->item['readtime'], $oldtime);
				$this->item['is_read'] = ($this->item['readtime'] > $this->item['lastpost']);
				$this->item['goto_readtime'] = array('goto' => $this->item['readtime']);
			}
		}
		else
		{
			$this->item['is_read'] = true;
		}

		$this->item['readstate'] = $this->item['is_read'] ? 'old' : 'new';
		$this->item['replies'] = max(0, ($this->item['visible']-1));

		if ($this->show_subscription)
		{
			$this->item['showsubsinfo'] = $this->show_subscription;
			$this->item['notification'] = ($this->item['emailupdate'] ? $vbphrase['instant'] : $vbphrase['none']);
		}
		else
		{
			$this->item['showsubsinfo'] = false;
			$this->item['notification'] = "";
		}
	}

	/**
	 * Sets whether to check if item is read
	 *
	 * @access public
	 *
	 * @param boolean $check
	 */
	function check_read($check = true)
	{
		$this->check_read = $check;
	}

	/**
	 * Sets whether to show subscription info.
	 *
	 * @access public
	 *
	 * @param boolean $show
	 */
	function show_subscription($show = true)
	{
		$this->show_subscription = $show;
	}
}


/**
* Deleted discussion bit class.
*
* @package 		vBulletin
* @copyright 	http://www.vbulletin.com/license.html
*
*/
class vB_Group_Bit_Discussion_Search extends vB_Group_Bit_Discussion
{
	/**
	* The template that will be used for outputting
	*
	* @access protected
	* @var	string
	*/
	var $template = 'search_results_socialgroup_discussion';
}


/**
* Deleted discussion bit class.
*
* @package 		vBulletin
* @copyright 	http://www.vbulletin.com/license.html
*
*/
class vB_Group_Bit_Discussion_Deleted extends vB_Group_Bit_Discussion
{
	/**
	* The template that will be used for outputting
	*
	* @access protected
	* @var	string
	*/
	var $template = 'socialgroups_discussion_deleted';
}


/**
* Ignored discussion bit class.
*
* @package 		vBulletin
* @copyright 	http://www.vbulletin.com/license.html
*
*/
class vB_Group_Bit_Discussion_Ignored extends vB_Group_Bit_Discussion
{
	/**
	* The template that will be used for outputting
	*
	* @access protected
	* @var	string
	*/
	var $template = 'socialgroups_discussion_ignored';
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 40924 $
|| ####################################################################
\*======================================================================*/
?>
