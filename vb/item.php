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
 * Base model class for single items.
 *
 * Items define an array of $item_properties that should match class properties.
 * This is used with setInfo and setProperty to apply an array of property values to
 * the item model.  This is particularly useful when loading item properties from db
 * query results.
 *
 * This is done internally with loadInfo() but the properties are set via the public
 * method setInfo(), allowing the same to be done with client code.  This allows
 * application wide querying to be kept to a minimum when item properties are
 * already known by the client code.
 *
 * The simplest way of doing this is to ensure that the field aliases in a query
 * match the item_properties, as illustrated with the following example:
 *
 * @example
 *  vB_Item_Box has the class property 'label'.
 *  vB_Item_Box should have $item_properties = array('label') to enable automagic info loading.
 *
 *  // Creating and loading a vB_Item_Box with client code
 *  $boxinfo = $db->query_one('SELECT boxid AS itemid, stickylabel as label FROM box');
 * 	$box = new vB_Item_Box($boxinfo['itemid']);
 *  $box->setInfo($boxinfo, vB_Item_Box::INFO_BASIC);
 *
 *  Note: As `boxid` was aliased as `itemid`, it was safely ignored with setInfo().
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 40577 $
 * @since $Date: 2010-11-15 14:57:02 -0800 (Mon, 15 Nov 2010) $
 * @copyright vBulletin Solutions Inc.
 */
abstract class vB_Item extends vB_Model
{
	/*ModelProperties===============================================================*/

	/**
	 * Array of all valid model properties.
	 * This is used to check if a class property can be set as a property and allows
	 * automagic setting of properties from db info results.
	 *
	 * Note: The property 'itemid' is interpreted as the primary key of the item and
	 * is never set.  Use 'itemid' when setting info to safely ignore the primary
	 * key when setting db results as info.  Never use 'itemid' for anything else.
	 *
	 * For all other properties, ensure that the queried fieldname matches the item
	 * property name so that they can be loaded automatically.
	 *
	 * If the item has a corresponding DM, ensure that the fieldnames are the same
	 * as those accepted by vB_DM::setExistingFields().  If this is not possible in
	 * the queries themself, ensure that the name is transformed in vB_Item::loadDM
	 * before giving it to the DM.
	 *
	 * @see Load()
	 * @see setInfo()
	 *
	 * @var array string
	 */
	protected $item_properties = array();

	/**
	 * The class name of the most appropriate DM for managing the item's data.
	 * @see vB_Item::getDM()
	 *
	 * @var string
	 */
	protected $dm_class;

	/**
	 * Info flags required to load all of the properties needed to set the existing
	 * fields in the DM for this item.
	 *
	 * @var int
	 */
	protected $dm_load_flags = self::INFO_BASIC;



	/*Initialisation================================================================*/

	/**
	 * Convenience factory method for creating items.
	 *
	 * @param string $package					- String pakcage identifier
	 * @param string $class						- Class segment identifier
	 * @param mixed $itemid						- Itemid to load
	 * @param int $load_flags					- Info to load
	 * @return vB_Item
	 */
	public static function create($package, $class, $itemid = false, $load_flags = false)
	{
		$class = $package . '_Item_' . $class;
		return new $class($itemid, $load_flags);
	}



	/*LoadInfo======================================================================*/

	/**
	 * Applies info to the model object.
	 * For items, info keys should match vB_Item::model_properties.
	 *
	 * To apply non model_properties to an item, override this method or add new
	 * methods.
	 *
	 * @param array mixed $info					- Property => value
	 * @param int $info_flags					- The info being loaded.
	 */
	public function setInfo($info, $load_flags = false)
	{
		if (empty($info))
		{
			return;
		}

		if (!is_array($info))
		{
			throw (new vB_Exception_Model('Info passed to model item for loading is not an array'));
		}

		foreach ($info AS $property => $value)
		{
			$this->setProperty($property, $value);
		}

		// Mark info as loaded
		$this->loaded_info |= ($load_flags | self::INFO_BASIC);
	}


	/**
	 * Assigns properties from an info array.
	 * This should only be used internally as no transformation will take place.  The
	 * values should already have been transformed by setProperty().
	 *
	 * @param array mixed $info
	 */
	protected function assignProperties($info)
	{
		foreach ($info AS $property => $value)
		{
			if (in_array($property, $this->item_properties))
			{
				$this->$property = $value;
			}
		}
	}


	/**
	 * Sets a model property with the given value.
	 * This provides automagic loading of model / class properties and is
	 * particularly useful when loading item properties with a db query result.
	 * @see vB_Model::Load()
	 *
	 * Extend this if data needs to be transformed.
	 *
	 * @param string $property					- The property to set
	 * @param mixed $value						- The value to set it to
	 */
	protected function setProperty($property, $value)
	{
		// Allow itemid to be safely ignored.  This is conveniant for client code
		// with the itemid in a query result.
		if ('itemid' == $property)
		{
			return;
		}

		// Validate
		if (!$this->validateProperty($property, $value))
		{
			throw (new vB_Exception_Model('Value \'' . htmlspecialchars($value) . '\' given for item model property \'' . htmlspecialchars($property) . '\' is not valid'));
		}

		$this->$property = $value;
	}


	/**
	 * Validates a property where required.
	 * Extend to perform per property validation.
	 *
	 * @param string $property					- The name of the property to validate
	 * @param mixed $value						- The value to validate
	 * @return bool								- Value is valid
	 */
	public function validateProperty($property, $value)
	{
		if (!property_exists($this, $property) OR !in_array($property, $this->item_properties))
		{
			throw (new vB_Exception_Model('Model and class property mismatch in ' . get_class($this) .
				' for property: ' . htmlspecialchars($property)));
		}

		return true;
	}


	/**
	 * Gets assigned properties as an assoc array.
	 *
	 * return array mixed
	 */
	protected function getProperties()
	{
		$properties = array();

		foreach ($this->item_properties AS $property)
		{
			if (isset($this->$property))
			{
				$properties[$property] = $this->$property;
			}
		}

		return $properties;
	}



	/*Cache=========================================================================*/

	/** Gives us a key we can use to store the item information
	 ****/
	protected function getCacheKey()
	{
		return false;
	}


	/**
	 * Loads the model info from the cache.
	 * Note: The cache is written after setInfo() so direct assignment of the
	 * properties is needed.
	 *
	 * @return bool								- Success
	 */
	protected function loadCache()
	{
		// Check if we're cachable
		if (!$this->cachable)
		{
			return false;
		}

		// Check if we are loading only INFO_BASIC and whether to use the cache
//		if (($this->required_info == self::INFO_BASIC) AND !$this->cache_basic)
//		{
//			return false;
//		}

		// Create a context to identify the cache entry
		if (!$key = $this->getCacheKey())
		{
			return false;
		}


		// Fetch the cache info
		if ($info = vB_Cache::instance()->read($key, true))
		{
			if (array_key_exists('item_properties', $info))
			{
				if (!isset($info['itemid']) OR ($this->itemid != $info['itemid']))
				{
					return false;
				}

				// load the info retrieved from the cache
				$this->loadCacheInfo($info);

				return true;
			}
		}

		return false;
	}


	/**
	 * Writes the item info to the cache.
	 *
	 * @return int
	 */
	protected function writeCache()
	{
		// Check if we're cachable
		if (!$this->cachable)
		{
			return;
		}

		// Create a context to identify the cache entry
		// Create a context to identify the cache entry
		if (!$key = $this->getCacheKey())
		{
			return false;
		}

		// Add extra info that is not in item_properties
		$info = $this->saveCacheInfo();

		// Write the cache
		return vB_Cache::instance()->write($key, $info, 0, $this->getCacheEvents());
	}


	/**
	 * Gets a context to identify the cache entry for the model info.
	 * Child implementations should wrap this if they need to add any filters or
	 * parameters that affects loadInfo()
	 *
	 * @param int $info_flags					- The required or loaded info flags
	 * @return vB_Context
	 */
	protected function getCacheContext($info_flags)
	{
		// Create a context to identify the cache entry
		$context = new vB_Context(get_class($this));
		$context->info = $info_flags;
		$context->itemid = $this->itemid;

		return $context;
	}


	/**
	 * Loads non item properties from a cache hit.
	 * Child implementations should override this to load any info that is not
	 * included in vB_Item::$item_properties.
	 *
	 * @param mixed $info						- The info loaded from the cache
	 */
	protected function loadCacheInfo($info)
	{
		$this->assignProperties($info['item_properties']);
		$this->loaded_info = $info['loaded_info'];
	}


	/**
	 * Saves non item properties as cachable info.
	 * Child implementations should override this to add any info that is not saved
	 * in vB_Item::$item_properties.
	 *
	 * @return array mixed $info				- The modified info array to cache
	 */
	protected function saveCacheInfo()
	{
		// Create the cachable info
		$info = array();
		$info['item_properties'] = $this->getProperties();
		$info['itemid'] = $this->itemid;
		$info['loaded_info'] = $this->loaded_info;

		return $info;
	}


	/**
	 * Fetches the events to register with the cache entry.
	 *
	 * @return array string						- The cache event ids
	 */
	protected function getCacheEvents()
	{
		return array(get_class($this) . '.' . $this->itemid);
	}



	/*DataManager===================================================================*/

	/**
	 * Creates an instance of the most appropriate DM for this item.
	 * The item is automatically loaded as the DM's subject.
	 *
	 * @return vB_DM
	 */
	public function getDM()
	{
		if ($this->dm_class)
		{
			return new $this->dm_class($this);
		}

		throw (new vB_Exception_Model('getDM called for \'' . get_class($this) . '\' but no DM class specified'));
	}


	/**
	 * Loads a corresponding DM with the fields it needs to express the current
	 * values.
	 *
	 * @param vB_DM $dm							- The DM to give the existing values to.
	 */
	public function loadDM(vB_DM $dm)
	{
		$this->Load($this->dm_load_flags);
		// using $this->getProperties() because the intended use of setExistingFields() is to send the actual property values themselves to populate the DM with existing data.
		$dm->setExistingFields($this->getProperties(), $this);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 40577 $
|| ####################################################################
\*======================================================================*/