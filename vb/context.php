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
 * Context
 * Container for information about a context that can be serialized and used as an
 * id to determine whether other data or methods are applicable.
 *
 * This is useful for both caching and state management.  A context can be created
 * to retrieve an appropriate cache object or managed state value.  The context can
 * be hashed providing a context value for testing.
 *
 * Values can be set in any order as they will be sorted for integrity before hashing
 * the context id.
 *
 * @example:
 * 	$context = new vB_Context();
 *  $context->widgetid = 55;
 *  $context->widgetlabel = 'mywidget';
 *  $context->nodeid = 2;
 *  $context->quantity = 10;
 *
 *  // Get cached result based on the above context
 *  if (!($result = $cache->read($context)))
 *  {
 * 		// ... code to create the new output
 * 		// save result to cache
 * 		$cache->write($context, $data);
 *  }
 *  return $result;
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 29424 $
 * @since $Date: 2009-02-02 14:07:13 +0000 (Mon, 02 Feb 2009) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_Context
{
	/*Properties====================================================================*/

	/**
	 * A custom key to add to the context.
	 * The evaluated md5 string representation of the context can be prefixed with a
	 * custom string to allow it to be more easily identified visually.  If a key is
	 * provided, the resulting string id of the context will be truncated to
	 * accomodate the prefix.
	 *
	 * @var string
	 */
	private $_key;

	/**
	 * The individualising characteristics of the context.
	 *
	 * @var array mixed
	 */
	private $_values = array();

	/**
	 * The resolved id of the context.
	 * This can be used to get or test data relating to the appropriate context.
	 *
	 * @var string
	 */
	private $_id;



	/*Initialization================================================================*/

	/**
	 * Constructor.
	 * Allows values to be given as an array on instantiation.
	 *
	 * @param array mixed $values
	 */
	public function __construct($key = false, $values = false)
	{
		$this->_key = $key;

		if (is_array($values))
		{
			$this->_values = $values;
		}
	}



	/*Definition====================================================================*/

	/**
	 * Setter for defining the context characteristics.
	 *
	 * @param string $property					- The property id
	 * @param mixed $value						- The value to set
	 */
	public function __set($property, $value)
	{
		$this->_values[$property] = $value;

		$this->_id = false;
	}



	/*Id============================================================================*/

	/**
	 * Gets an individual id for the context.
	 *
	 * @return string
	 */
	public function getId()
	{
		// Only calculate once
		if (!$this->_id)
		{
			// sort values so that the order is always the same regardless of the order that they were set
			ksort($this->_values);

			// serialize values for md5
			$result = serialize($this->_values);

			// return md5 of serialization
			$this->_id = md5($result);

			if ($this->_key)
			{
				$this->_id = substr($this->_key . '.' . $this->_id, 0, 64);
			}
		}

		return $this->_id;
	}


	/**
	 * __toString
	 * Returns the evaluated context id.
	 *
	 * @return string
	 */
	public function __toString()
	{
		return $this->getId();
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28694 $
|| ####################################################################
\*======================================================================*/