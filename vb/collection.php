<?php if (!defined('VB_ENTRY')) die('Access denied.');
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.1.5 Patch Level 1 - Licence Number VBF1F15E74
|| # ---------------------------------------------------------------- # ||
|| # Copyright 2000-2011 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

/**
 * Collection
 * Fetches a collection of items with the given criteria.
 *
 * Usually, if an itemid is specified for a collection then it is an array of all of
 * the itemids that should be fetched.
 *
 * Note that the INFO_ constants should use those of the child items and only add
 * more if they are applicable.
 *
 * Note: As a standard, collections should not add conditions to the query if the item
 * ids are already known; unless some property has been set to force this.
 *
 * Note: If the collection supports pagination it should set $can_paginate to true,
 * and should include SQL_CALC_FOUND_ROWS in it's fields.  It should also limit the
 * results by $start and $quantity.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 35689 $
 * @since $Date: 2010-03-04 16:01:53 -0800 (Thu, 04 Mar 2010) $
 * @copyright vBulletin Solutions Inc.
 */
abstract class vB_Collection extends vB_Model implements ArrayAccess, Iterator
{
	/*Item==========================================================================*/

	/**
	 * The package identifier of the child items.
	 *
	 * @var string
	 */
	protected $item_package = 'vB';

	/**
	 * The class identifier of the child items.
	 *
	 * @var string
	 */
	protected $item_class = 'Item';

	/**
	 * Collections mostly don't require a itemid.
	 *
	 * @var bool
	 */
	protected $allow_no_itemid = true;

	/**
	 * Whether this collection type supports pagination.
	 *
	 * @var bool
	 */
	protected $can_paginate = false;

	/**
	 * Whether to calculate pagination.
	 *
	 * @var bool
	 */
	protected $paginate = false;

	/**
	 * The page to display if paginated.
	 *
	 * @var int
	 */
	protected $page = 1;

	/**
	 * The amount of items to fetch.
	 * If 0, all items will be fetched.
	 *
	 * @var int
	 */
	protected $quantity = 20;

	/**
	 * The resolved start result index in the collection
	 *
	 * @var int
	 */
	protected $start;

	/**
	 * The resolved end result index in the collection
	 *
	 * @var int
	 */
	protected $end;



	/*Sort&Order====================================================================*/

	/**
	 * If this is true, the collection will be fetched in descending order.
	 *
	 * @var bool
	 */
	protected $descending;

	/**
	 * Field to sort by.
	 * @todo allow multiple
	 *
	 * @var mixed
	 */
	protected $sortfield;



	/*Result========================================================================*/

	/**
	 * Cached result
	 *
	 * @var array
	 */
	protected $collection = array();

	/**
	 * Field name of the primary key.
	 * This is required so that the collection can refer to a specific item when
	 * setting info.
	 *
	 * @var string
	 */
	protected $primary_key = 'itemid';

	/**
	 * The resolved total size of the collection.
	 *
	 * @var integer
	 */
	protected $total;

	/**
	 * Reference to the first item of the resolved collection.
	 *
	 * @var array
	 */
	protected $firstitem;

	/**
	 * Reference to the last item of the resolved collection.
	 *
	 * @var array
	 */
	protected $lastitem;



	/*Hooks=========================================================================*/

	/**
	 * Hook id for changing the sort column and order.
	 *
	 * @var string
	 */
	protected $sort_hook;



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
		if (!$this->item_package OR !$this->item_class)
		{
			throw (new vB_Exception_Model('No item type defined for collection \'' . get_class($this) . '\''));
		}

		parent::__construct($itemid, $load_flags);
	}



	/*Criteria======================================================================*/

	/**
	 * Fetches the collection array.
	 *
	 * @return array vb_Item
	 */
	public function getCollection()
	{
		$this->Load();

		return $this->collection;
	}


	/**
	 * Sets an existing array of items as the collection
	 *
	 * @param $items							- Array of itemid => item
	 * @param $load_flags						- INFO already loaded for the items
	 */
	public function setCollection(array $items, $load_flags)
	{
		foreach ($items AS $item)
		{
			if (!$this->validCollectionItem($item))
			{
				throw (new vB_Exception_Model('Trying to add an item of the wrong type (\'' . get_class($item) . '\' to a collection (\'' . get_class($this) . '\')'));
			}
		}

		$this->itemid = array_keys($items);
		$this->collection = $items;
		$this->removeFilters();
		$this->loaded_info = $load_flags;
	}


	/**
	 * Checks if an item of a valid type to be in the collection.
	 *
	 * @param $item
	 * @return bool
	 */
	protected function validCollectionItem($item)
	{
		if (!($item instanceof vB_Item))
		{
			return false;
		}

		return true;
	}


	/**
	 * Sets the item ids for the collection.
	 * The item id's are arbitrary but must be understood by the item classes that
	 * the collection creates.
	 *
	 * Child classes can extend this for validation.
	 *
	 * @param mixed $itemids
	 */
	public function setItemId($itemid)
	{
		if (!$itemid)
		{
			return;
		}

		$itemid = (array)$itemid;

		foreach($itemid AS &$id)
		{
			$id = intval($id);
		}

		if ($this->itemid !== $itemid)
		{
			$this->itemid = $itemid;
			$this->removeFilters();
		}
	}


	/**
	 * Adds a single itemid to the collection itemid.
	 *
	 * @param $itemid
	 */
	public function addItemId($itemid)
	{
		if (!$itemid)
		{
			return;
		}

		$itemid = (array)$itemid;

		if ($this->itemid)
		{
			$itemid = array_merge($this->itemid, $itemid);
		}

		$this->setItemId($itemid);
	}


	/**
	 * Removes any filters.
	 */
	public function removeFilters(){}



	/*PaginationCriteria============================================================*/

	/**
	 * Set the page to fetch,
	 *
	 * @param int $page
	 */
	public function paginatePage($page)
	{
		if (!$this->can_paginate)
		{
			throw (new vB_Exception_Model('Setting page for collection \'' . get_class($this) . '\' but the collection does not support pagination'));
		}

		$page = intval($page);
		$this->paginate = true;

		if ($this->page != $page)
		{
			$this->page = $page;
			$this->reset();
		}
	}


	/**
	 * Sets the maximum amount of results to fetch.
	 *
	 * @param int $quantity
	 */
	public function paginateQuantity($quantity)
	{
		if (!$this->can_paginate)
		{
			throw (new vB_Exception_Model('Setting quantity for collection \'' . get_class($this) . '\' but the collection does not support pagination'));
		}

		$this->paginate = true;

		if ($this->quantity != $quantity)
		{
			$this->quantity = $quantity;
			$this->Reset();
		}
	}


	/**
	 * Enables or disables pagination.
	 *
	 * @param bool $paginate
	 */
	public function paginate($paginate = true)
	{
		if (!$this->can_paginate)
		{
			throw (new vB_Exception_Model('Setting pagination for collection \'' . get_class($this) . '\' but the collection does not support pagination'));
		}

		$this->paginate = $paginate;
	}


	/**
	 * Convenience method for setting all pagination criteria with a single call.
	 *
	 * @param int $page							- The page offset to use
	 * @param int $quantity						- The amount of items to show per page
	 */
	public function paginateCriteria($page, $quantity)
	{
		$this->paginatePage($page);
		$this->paginateQuantity($quantity);
	}



	/*LoadInfo======================================================================*/

	/**
	 * Populates the model info.
	 *
	 * @param int $info_flags					- Additional info to load
	 */
	protected function Load($info_flags = false)
	{
		// Resolve the start index to fetch
		if ($this->paginate AND !$this->start)
		{
			// actual start index
			$this->start = $this->paginate ? (max(($this->page - 1), 0) * $this->quantity) : 0;
		}

		return parent::Load($info_flags);
	}


	/**
	 * Builds or updates the collection from a db result.
	 * If child classes need to apply loaded info to items that are not part of the
	 * item model properties then they will have to extend or override this method.
	 *
	 * @param resource $result					- The result resource of the query
	 * @param int $load_query					- The query that the result is from
	 * @return bool								- Success
	 */
	protected function applyLoad($result, $load_query)
	{
		// Calculate the newly loaded info from required info and the loaded query
		$loaded = ($this->required_info & $this->query_info[$load_query]);
		
		if (self::QUERY_BASIC == $load_query)
		{
			// resolve total
			if ($this->paginate)
			{
				$sql = "SELECT FOUND_ROWS() AS qty";
				$record = $this->important ? vB::$db->query_first($sql) : $record = vB::$db->query_first_slave($sql);
				$this->total = $record['qty'];
			}
			else
			{
				list($this->total) = vB::$db->num_rows($result);
			}
		}

		// Build collection, get the first item and flag ignored messages
		while ($iteminfo = vB::$db->fetch_array($result))
		{
			// create the collection on first load
			if ($this->requireLoad(self::INFO_BASIC))
			{
				// create the item and set the info
				if ($item = $this->createItem($iteminfo, $this->required_info))
				{
					$this->lastitem = $this->collection[$iteminfo[$this->primary_key]] = $item;

					if (!$this->firstitem)
					{
						$this->firstitem = $this->lastitem;
					}
				}
			}
			else
			{
				// set the info on existing items
				$this->setInfo($iteminfo, $loaded);
			}
		}

		// Set itemid based on resolved collection
		if (self::QUERY_BASIC == $load_query)
		{
			$this->itemid = array_keys($this->collection);
			$this->removeFilters();

			// check page is valid
			if ($this->paginate)
			{
				if ($this->start >= $this->total)
				{
					$this->page = ceil($this->total / $this->quantity);
				}
			}
			else
			{
				$this->page = 1;
			}

			// calculate end
			$this->end = $this->paginate ? (min(($this->start) + $this->quantity, $this->total)) : $this->total;

			// nudge start to user friendly range (1+)
			$this->start++;
		}

		// Mark info as loaded
		$this->loaded_info |= $loaded;

		return (!($loaded & self::INFO_BASIC) OR sizeof($this->collection));
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
		if ((self::QUERY_BASIC == $load_query) AND $this->paginate)
		{
			list($this->total) = vB::$db->query_first("SELECT FOUND_ROWS()", DBARRAY_NUM);
		}

		return false;
	}


	/**
	 * Creates an item to add to the collection.
	 *
	 * @param array mixed $iteminfo				- The known properties of the new item
	 * @return vB_Item							- The created item
	 */
	protected function createItem($iteminfo, $load_flags = false)
	{
		if (!isset($iteminfo[$this->primary_key]))
		{
			throw (new vB_Exception_Model('No primary key property value in iteminfo for vB_Collection::createItem()'));
		}

		$item_class = $this->item_package . '_Item_' . $this->item_class;
		$item = new $item_class($iteminfo[$this->primary_key]);
		$item->setInfo($iteminfo, $load_flags);

		if ($item->isValid())
		{
			return $item;
		}

		return false;
	}


	/**
	 * Sets info on a single item.
	 * If items are not indexed with $this->primary_key then the child class will
	 * have to override this method to ensure the info is assigned to the correct
	 * item.
	 *
	 * Note that the collection must be loaded before iteminfo can be set.  The
	 * collection doesn't support adding items on the fly yet (until needed).
	 *
	 * @param array mixed $iteminfo				- Property => Value
	 */
	public function setInfo($iteminfo, $load_flags = false)
	{
		if (!isset($iteminfo[$this->primary_key]))
		{
			throw (new vB_Exception_Model('No primary key property value in iteminfo for vB_Collection::setInfo()'));
		}

		// If the item exists, set the info
		if (isset($this->collection[$iteminfo[$this->primary_key]]))
		{
			$itemid = reset(array_splice($iteminfo, $this->primary_key, 1));

			// ensure we don't set the primary key
			unset($iteminfo[$this->primary_key]);

			$this->collection[$itemid]->setInfo($iteminfo, $load_flags);
		}
		else
		{
			throw (new vB_Exception_Model('Setting collection item iteminfo for an item that is not in the collection: \'' . htmlspecialchars($iteminfo[$this->primary_key]) . '\''));
		}
	}


	/**
	 * Only fetches the total size of the collection.
	 *
	 * @return int
	 */
	public function getTotal()
	{
		$this->Load();

		return $this->total;
	}


	/**
	 * Fetches the first item of the collection.
	 *
	 * @return vB_Item							- The first item
	 */
	public function getFirstItem()
	{
		$this->Load();

		return ($this->firstitem ? $this->firstitem : false);
	}


	/**
	 * Fetches the last item of the collection.
	 *
	 * @return vB_Item							- The last item
	 */
	public function getLastItem()
	{
		$this->Load();

		return ($this->lastitem ? $this->lastitem : false);
	}


	/**
	 * Unsets the fetched collection.
	 * Child classes may want to extend this to reset any other data.
	 */
	public function reset($reset_item = false)
	{
		$this->loaded_info = 0;
		$this->collection = array();
		unset($this->total, $this->firstitem, $this->lastitem);

		if ($reset_item)
		{
			unset($this->itemid);
		}
	}



	/*SQL===========================================================================*/

	/**
	 * Sets the hook for modifying the sort fields and order.
	 *
	 * @param string $name						- The name of the hook to use
	 */
	public function setSortHook($name)
	{
		$this->sort_hook = $name;
	}



	/*Sort&Order====================================================================*/

	/**
	 * Sets the order to ASC or DESC.
	 *
	 * @param bool $descending
	 */
	public function orderDescending($descending = true)
	{
		if ($this->descending != $descending)
		{
			$this->descending = $descending;
			$this->Reset();
		}
	}


	/**
	 * Sets the sort field.
	 * Child classes should validate the field and prefix.* the appropriate table alias.
	 *
	 * @param string $field						- The client id of the field to sort by
	 */
	public function orderSortField($field)
	{
		$this->orderSortFieldHook($field);
	}


	/**
	 * Allows hooks to evaluate sort field.
	 *
	 * @param string $field						- The client id of the field to sort by
	 */
	protected function orderSortFieldHook($field)
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
				$this->Reset();
			}
		}
	}



	/*PaginationResults=============================================================*/

	/**
	 * Fetches the start index of the collection.
	 *
	 * @return int
	 */
	public function getStart()
	{
		$this->Load();

		return $this->start;
	}


	/**
	 * Fetches the end index of the collection.
	 *
	 * @return int
	 */
	public function getEnd()
	{
		$this->Load();

		return $this->end;
	}


	/**
	 * Fetches how many items were fetched.
	 *
	 * @return int
	 */
	public function getShown()
	{
		$this->Load();

		return sizeof($this->collection);
	}


	/**
	 * Fetches the counts of the fetched collection.
	 *
	 * @return array							- Assoc array of count information
	 */
	public function getCounts()
	{
		if (! $this->isValid())
		{
			return false;
		}

		return array(
				'start' => $this->getStart(),
				'end' => $this->getEnd(),
				'shown' => $this->getShown(),
				'total' => $this->getTotal(),
				'page' => $this->getPageNumber());
	}


	/**
	 * Gets the resolved results page
	 *
	 * @return int
	 */
	public function getPageNumber()
	{
		$this->Load();

		return $this->page;
	}


	/**
	 * Gets the set per page quantity
	 *
	 * @return int
	 */
	public function getQuantity()
	{
		return $this->quantity;
	}



	/*Iterator======================================================================*/

	/**
	 * Returns current item.
	 *
	 * @return vB_Item
	 */
	public function current()
	{
		// Ensure collection is loaded
		$this->Load();
		return current($this->collection);
	}


	/**
	 * Returns key of current element.
	 *
	 * @return string | int
	 */
	public function key()
	{
		return key($this->collection);
	}


	/**
	 * Advances pointer to next element and returns it's value.
	 *
	 * @return vB_Item
	 */
	public function next()
	{
		return next($this->collection);
	}


	/**
	 * Returns pointer to the beginning.
	 */
	public function rewind()
	{
		reset($this->collection);
	}


	/**
	 * Checks if there is a current element.
	 *
	 * @return bool
	 */
	public function valid()
	{
		return $this->Load() AND (bool)current($this->collection);
	}



	/*ArrayAccess===================================================================*/

	/**
	 * Checks that a key exists.
	 *
	 * @param mixed $offset
	 * @return bool
	 */
	public function offsetExists($offset)
	{
		if (!$this->loaded_info)
		{
			$this->Load();
		}

		return isset($this->collection[$offset]);
	}


	/**
	 * Gets the value for the given key if it exists.
	 * If it doesn't exist then the same warning is triggered that php gives as
	 * standard.
	 *
	 * @param mixed $offset
	 * @return mixed
	 */
	public function offsetGet($offset)
	{
		if (!$this->loaded_info)
		{
			$this->Load();
		}

		if (!isset($this->collection[$offset]))
		{
			trigger_error('Undefined index: ' . $offset, E_USER_WARNING);
		}
		else
		{
			return $this->collection[$offset];
		}
	}


	/**
	 * Sets the value for the given key.
	 *
	 * @param mixed $offset
	 * @param mixed $value
	 */
	public function offsetSet($offset, $value)
	{
		throw (new vB_Exception_Model('Cannot set vB_Collection items directly'));
	}


	/**
	 * Unsets the element for the given key
	 *
	 * @param mixed $offset
	 */
	public function offsetUnset($offset)
	{
		throw (new vB_Exception_Model('Cannot unset vB_Collection items directly'));
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 35689 $
|| ####################################################################
\*======================================================================*/
