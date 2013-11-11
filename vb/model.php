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
 * Model
 * Base class for models, such as vb_Item and vB_Collection.
 * The model tracks the information that is required by the client code and what has
 * already been loaded so that only the required information is queried.
 *
 * Child classes should define class constants using bit flags, and then initialise
 * $INFO_ALL with the total value.
 *
 * Client code should inform the model of what information is required ahead of
 * fetching as much as possible to reduce the amount of queries made.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: $
 * @since $Date: $
 * @copyright vBulletin Solutions Inc.
 */
abstract class vB_Model
{
	/**
	 * The primary id used for fetching model data.
	 *
	 * @var mixed
	 */
	protected $itemid;

	/**
	 * Whether itemid is allowed to be false.
	 *
	 * @var bool
	 */
	protected $allow_no_itemid = false;

	/**
	 * Whether an item was successfully resolved from the given id.
	 *
	 * @var bool
	 */
	protected $is_valid = true;

	/**
	 * What info has been loaded.
	 * This is a bitfield accumulating the INFO constants.  This is optional and
	 * can be used by child classes when optional data can be queried together.
	 *
	 * @var int
	 */
	protected $loaded_info;

	/**
	 * What info is required.
	 * This is a bitfield accumulating the INFO constants.  This is optional and
	 * can be used by child classes when optional data can be queried together.
	 *
	 * @var int
	 */
	protected $required_info;

	/**
	 * Whether to always load basic info when anything else is loaded.
	 * Some models may want to disable this if there are no common fields
	 * to all required info cases.
	 * @see __contruct()
	 *
	 * @var bool
	 */
	protected $always_load_basic = true;

	/**
	 * Whether to autoload the basicinfo when the object is created.
	 *
	 * @var bool
	 */
	protected $autoload = false;

	/**
	 * Whether the item info is important.
	 * Determines how accurate the item properties should be.  Child classes should
	 * check this value when determining whether to read properties from a cache, or
	 * master / slave databases.
	 *
	 * This should be set to true when editing model properties or related data.
	 * @see vB_Model::setImportant()
	 *
	 * @var bool
	 */
	protected $important = false;

	/**
	 * Whether the model info can be cached.
	 *
	 * @var bool
	 */
	protected $cachable = false;

	/**
	 * Whether to cache model info if only INFO_BASIC is loaded.
	 * Usually, INFO_BASIC is a simply query, negating the need for caching.
	 *
	 * @var bool
	 */
	protected $cache_basic = false;



	/*InfoFlags=====================================================================*/

	/**
	 * Flags for required item info.
	 * These are used for $required_info and $loaded_info.
	 *
	 * Hooks can base their extended loading on any of the INFO constants for the
	 * model class they are extending where appropriate.
	 */
	const INFO_BASIC = 0x1;

	/**
	 * The total flags for all info.
	 * This should be overridden by children based on the total of their info flags.
	 * @TODO Make static when we have late static binding
	 *
	 * @var int
	 */
	protected $INFO_ALL = 1;

	/**
	 * List of dependencies.
	 * If a particular info requires another info to be loaded then you can map them
	 * here.  The array should be in the form array(dependent => dependent on)
	 *
	 * @var array int
	 */
	protected $INFO_DEPENDENCIES = array();

	/**
	 * Query types.
	 * Query types are used when requesting the query for a given set of info. A lot
	 * of info will share the same query, only affecting how it is parsed; however
	 * some models will require various different queries for fetching different
	 * kinds of info.
	 *
	 * Using query id's allows a single method to determine the required query while
	 * providing an entry point for hooks and letting them know which query is being
	 * constructed.
	 */
	const QUERY_BASIC = 1;

	/**
	 * Map of query => info.
	 * Specifies what info can be loaded by a query.  This is used to automatically
	 * get the required query for required info, and to mark queried info as loaded.
	 * @see vB_Model::loadInfo()
	 *
	 * @TODO: This should be static once late static binding is available.
	 * @TODO: Values should be an array, with an extra value that states INFO_FLAGS
	 * that will always be loaded by the query, regardless of it being required or
	 * not.  This prevents duplicate querying for data that has already been loaded
	 * but not required.
	 *
	 * @var array int => int
	 */
	protected $query_info = array(
		self::QUERY_BASIC => 1
	);



	/*Hooks=========================================================================*/

	/**
	 * Hook id for manipulating the fetch query.
	 *
	 * @var string
	 */
	protected $query_hook;



	/*Initialisation================================================================*/

	/**
	 * Constructs the Model.
	 * The id passed will usually be the primary key of the model data in the
	 * database but as this is model specific it can be interpreted in other ways.
	 *
	 * @param mixed $itemid					- The id of the item
	 * @param int $load_flags				- Any required info prenotification
	 */
	public function __construct($itemid = false, $load_flags = false)
	{
		// Check validity of the itemid
		if (!$this->allow_no_itemid AND !$itemid)
		{
			throw (new vB_Exception_Model('No required itemid specified when instantiating Model object \'' . get_class($this) . '\''));
		}

		// Assign the primary id for fetching
		$this->setItemId($itemid);

		// Prenotify any specified load flags
		if ($load_flags)
		{
			$this->requireInfo($load_flags);
		}

		// Ensure basic info is always loaded
		if ($this->always_load_basic)
		{
			$this->requireInfo(self::INFO_BASIC);
		}

		if ($this->autoload AND $this->always_load_basic)
		{
			$this->Load();
		}
	}


	/**
	 * Sets the itemid of the item to be loaded.
	 *
	 * @param mixed $itemid
	 */
	protected function setItemId($itemid)
	{
		$this->itemid = $itemid;
	}


	/**
	 * Sets whether the item data is important.
	 * Determines if the model data should be loaded using the most accurate
	 * querying method.  This should be set when editing the model properties, or
	 * when editing related data.
	 *
	 * Child classes can check this value to determine whether to use caching, or to
	 * query from master or slave databases.
	 *
	 * @param bool $important
	 */
	public function setImportant($important = true)
	{
		$this->important = $important;
	}



	/*LoadInfo======================================================================*/

	/**
	 * Populates the model info.
	 * Any of the prequired info will also be loaded.
	 *
	 * @param int $info_flags					- Additional info to load
	 */
	protected function Load($info_flags = false)
	{
		// Validate criteria
		if (!$this->validateCriteria())
		{
			return false;
		}

		// If the item is already invalid, don't query again
		if (!$this->is_valid)
		{
			return false;
		}

		// If everything is loaded then no need to query again
		if ($this->loaded_info == $this->INFO_ALL)
		{
			return true;
		}

		// Add any last minute required info
		if ($info_flags)
		{
			$this->requireInfo($info_flags);
		}

		// Check if everything required is loaded
		if (!$this->required_info OR (($this->loaded_info & $this->required_info) == $this->required_info))
		{
			return true;
		}

		// Do the actual loading
		return $this->is_valid = $this->loadInfo();
	}

	/**
	 * Loads required info.
	 * Determines the required queries for the pending required_info, executes the
	 * queries and applies the results to the object.
	 *
	 * If child classes use more than QUERY_BASIC then they should override this
	 * method to determine what queries are required, and how their results should
	 * be applied.
	 *
	 * @return array							- Returns the entire fetched collection
	 */
	protected function loadInfo()
	{
		$valid = false;

		// Try to load from the cache
		if ($this->loadCache())
		{
			return true;
		}

		// Check the required queries
		foreach ($this->query_info AS $query => $info)
		{
			// check if any of this queries' info is required
			if ($this->requireLoad($info))
			{
				// get query
				if (! $sql = $this->getLoadQuery($query))
				{
					//For some conditions we don't return any sql, so let's just return
					continue;
				}
				// exec query
				$result = ($this->important ? vB::$db->query_read($sql) : vB::$db->query_read_slave($sql));

				// check we have a result
				if (($info & self::INFO_BASIC) AND (self::QUERY_BASIC == $query) AND $this->requireLoad(self::INFO_BASIC) AND !vB::$db->num_rows($result))
				{
					return $this->noResult($query);
				}

				// apply the results
				$valid = $this->applyLoad($result, $query) OR $valid;
				// Free result
				vB::$db->free_result($result);
			}

		}

		return $valid;
	}


	/**
	 * Applies the result of the load query.
	 *
	 * This method should only ever be used directly after performing the queries so
	 * that $this->required_info accurately reflects the query result.
	 *
	 * @param resource $result					- The db result resource
	 * @param int $load_query					- The query that the result is from
	 */
	protected function applyLoad($result, $load_query)
	{
		// Calculate the newly loaded info from required info and the loaded query
		$loaded = ($this->loaded_info | ($this->query_info[$load_query] & $this->required_info ));
		// Get first result
		$iteminfo = vB::$db->fetch_array($result);

		// Set loaded info
		$this->setInfo($iteminfo, $loaded);

		return true;
	}


	/**
	 * Return value for no result from a query.
	 * This allows child items to set related properties or populate defaults.
	 *
	 * @param $load_query						- The query that returned no result
	 * @return bool								- Whether the model is valid
	 */
	protected function noResult($load_query)
	{
		return false;
	}


	/**
	 * Applies info to the model object.
	 * Should apply an info result to the object model properties.  Client code may
	 * also use setInfo when the info is already available.
	 *
	 * @param array mixed $info					- Property => value
	 * @param int $info_flags					- The info being loaded.
	 */
	abstract public function setInfo($info, $loaded_info = self::INFO_BASIC);


	/**
	 * Copies info from this object to another of the same type.
	 * This is usefull when using a generic collection class that used a parent type
	 * to fetch the items.
	 *
	 * @param vB_Model $target
	 */
	public function castInfo($target)
	{
		if (!($target instanceof $this))
		{
			throw (new vB_Exception_Model('Can not castInfo with mismatching types'));
		}

		$info = array();
		$properties = array_merge($this->item_properties, $target->item_properties);
		foreach ($properties AS $property)
		{
			$info[$property] = $this->$property;
		}

		$target->setInfo($info, $this->loaded_info);
	}


	/**
	 * Returns whether the item is valid or not.
	 *
	 *	@return bool
	 */
	public function isValid()
	{
		return $this->Load();
	}


	/**
	 * Validates criteria.
	 * Child implementations should override this to validate criteria that affects
	 * queries, such as the specified itemid.
	 *
	 * @return bool
	 */
	public function validateCriteria()
	{
		return true;
	}



	/*SQL===========================================================================*/

	/**
	 * Sets the hook for modifying the fetch query.
	 *
	 * @param string $name						- The name of the hook to use
	 */
	public function setQueryHook($name)
	{
		$this->query_hook = $name;
	}


	/**
	 * Fetches the SQL for loading.
	 * $required_query is used to identify which query to build for classes that
	 * have multiple queries for fetching info.
	 *
	 * Child classes should override this method if used.
	 *
	 * @param int $required_info				- The required query
	 *
	 * @return string
	 */
	protected function getLoadQuery($required_query)
	{
		throw (new vB_Exception_Model('Invalid query id \'' . htmlspecialchars($required_query) . '\' specified for ' . get_class($this) . ' item: ' . htmlspecialchars($this->itemid)));
	}




	/*Require=======================================================================*/

	/**
	 * Notifies the item of information that will be required.
	 * See the INFO flag constants for valid values.
	 *
	 * Model classes should be developed so that unloaded info can be requested at
	 * any time; however if the model is prenotified of required info then the model
	 * can fetch info together, reducing the amount of executed queries.
	 *
	 * @param int $info						- The required info. See INFO constants.
	 */
	public function requireInfo($flags)
	{
		if (!is_numeric($flags))
		{
			if (is_array($flags))
			{
				foreach ($flags AS $flag)
				{
					$this->requireInfo($flag);
				}
			}

			throw (new vB_Exception_Model('Info flags passed to vB_Model::requireInfo() is not an int or array int: \'' . $flags . '\''));
		}

		if (!($this->required_info & $flags))
		{
			$this->required_info = ($this->required_info | $flags);

			foreach ($this->INFO_DEPENDENCIES AS $dependant => $dependency)
			{
				if ($flags & $dependant)
				{
					$this->requireInfo($dependency);
				}
			}
		}
	}


	/**
	 * Whether info is required for loading.
	 * This is a helper method to reduce ugly bitwise syntax.
	 *
	 * @param int $flag							- The info flag to check
	 * @return bool								- Whether it needs to be loaded
	 */
	protected function requireLoad($flags)
	{
		// INFO_BASIC should always be loaded, even if it hasn't been required
		// @TODO: This makes $always_load_basic redundant.  Check which is required.
		if (($flags & self::INFO_BASIC) AND !($this->loaded_info & self::INFO_BASIC))
		{
				return true;
		}

		return ($this->required_info & $flags) AND !(($this->loaded_info & $flags) == $flags);
	}

	/*Cache=========================================================================*/

	/**
	 * Loads the model info from the cache.
	 *
	 * @return bool								- Success
	 */
	protected function loadCache()
	{
		//Check to see if the child record is cachable.
		if ($this->cachable AND is_callable(array($this, 'getContentCacheHash')))
		{
			$hash = $this->getContentCacheHash();
			//Try a read
			if ($item = vB_Cache::instance()->read($hash, true))
			{
				//We have a cache value. Now if we don't have all the necessary
				// fields, we can't cache.
				$this->itemid = $item->itemid;
				$this->loaded_info = $item->loaded_info;
				foreach ($this->content_properties as $key )
				{
					if (!isset($item->values[$key]))
					{
						return false;
					}
					$this->key = $item->values[$key];
				}
				foreach ($this->item_properties as $key => $value)
				{
					if (!isset($item->values[$key]))
					{
						return false;
					}
					$this->key = $item->values[$key];
				}
				return true;
			}

		}
		return false;
	}


	/**
	 * Writes the model info to the cache.
	 *
	 * @return int
	 */
	protected function writeCache()
	{
		if ($this->cachable AND is_callable(array($this, 'getContentCacheHash'))
			AND is_callable(array($this, 'getContentCacheEvent')))
		{
			$item = array();
			$item->values = array();
			$hash = $this->getContentCacheHash();
			if ($item = vB_Cache::instance()->read($hash, true))
			{
				$this->itemid = $item->itemid;
				$item->loaded_info |= $this->loaded_info;
				foreach (array_merge($item->item_properties, $this->content_properties) as $key )
				{
					$item->values[$key] = $item->key;
				}
				return true;
			}
			$hashevent = $this->getContentCacheEvent();
			vB_Cache::instance()->write($hash, $item, 1440, $hashevent);

		}
		return false;
	}

	/*Accessors=====================================================================*/

	/**
	 * Returns the resolved itemid for the item.
	 *
	 * @return int
	 */
	public function getId()
	{
		return $this->itemid;
	}


	/**
	 * Returns INFO_FLAGS that have already been loaded.
	 *
	 * @return int
	 */
	public function getLoadedInfoFlags()
	{
		return $this->loaded_info;
	}



	/*Reset=========================================================================*/

	/**
	 * Unloads all info.
	 * This is useful when the info has been changed and needs to be updated.
	 */
	public function reset()
	{
		$this->loaded_info = false;
		$this->required_info = false;
		$this->is_valid = true;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28694 $
|| ####################################################################
\*======================================================================*/