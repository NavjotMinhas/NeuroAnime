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
 * Base Data Manager class.
 * The datamanager is responsible for saving the state of a model object.  As the
 * required data for saving a unique object is often different from the read data
 * of a model item; datamanager's handle their fields independantly.  However, they
 * can load existing data from a model item with setExisting().
 *
 * The default save implementation will iterate the tables defined in
 * vB_DM::table_fields, and then the corresponding fields in vB_DM::$fields before
 * performing and update if an existing item was specified, or an insert if no item
 * was specified.
 *
 * The default setProperty implementation will also use the field definitions in
 * vB_DM::$fields to determine the expected type, requirement and validation
 * method for the property.
 *
 * For data that does not fit the default implementation, vB_DM::postSave() and
 * vB_DM::postDelete() can be overridden.
 *
 * Note:  Client code can call save() with parameters to use a REPLACE and/or IGNORE
 * when saving a new item.  If any non primary table fields are defined as auto
 * incrementing then using IGNORE will throw an exception.  Using REPLACE will also
 * throw an exception unless a primary id is specified with vB_DM::setAutoIncrementId().
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 28694 $
 * @since $Date: 2008-12-04 16:12:22 +0000 (Thu, 04 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */
abstract class vB_DM
{
	/*Constants=====================================================================*/

	/**
	 * Validfield definition elements.
	 * @see vB_DM::$fields
	 */
	const VF_TYPE = 0;			// The expected type
	const VF_REQ = 1;			// The requirement
	const VF_METHOD = 2;		// The validation method
	const VF_VERIFY = 3;		// Validation parameters

	/**
	 * Values for VF_REQ.
	 * @see vB_DM::$fields
	 */
	const REQ_NO = 0;			// Not required
	const REQ_YES = 1;			// Is required
	const REQ_INC = 2;			// Taken from the insertid or primaryid
	const REQ_AUTO = 3;			// The DM will populate the value itself

	/**
	 * Validation methods for validfield element VF_METHOD.
	 * @see vB_DM::$fields
	 */
	const VM_TYPE = 0;			// Only type checking will be done
	const VM_CALLBACK = 1;		// The callback specified by VF_VERIFY will be used
	const VM_LAMBDA = 2;		// The value of VF_VERIFY will be used as a lambda



	/*Properties====================================================================*/

	/**
	* Field definitions.
	* The field definitions are in the form:
	*	array(fieldname => array(VF_TYPE, VF_REQ, VF_METHOD, VF_VERIFY)).
	*
	* The field elements should be defined as follows:
	* VF_TYPE 	- This specifies the expected data type of the field, and
	* 			  draws on the constants defined in vB_Input.
	* VF_REQ 	- This specified whether or not the field is REQUIRED for a
	* 			  valid INSERT query.  Options include REQ_NO, REQ_YES, REQ_INC and
	* 			  REQ_AUTO, which is a special option, indicating that the value of
	* 			  the field is automatically created, and therefore does not need to
	* 			  be set by client code.
	* VF_METHOD	- This specifies the verification method to use on values set for
	* 			  the field.  The methods can be:
	* 				VM_TYPE 	- Only a type check is done based on the field's
	* 							  VF_TYPE.
	* 				VM_CALLBACK - An additional callback is used, as specified in
	* 							  the field's VF_VERIFY.
	* 				VM_LAMBDA	- A lambda function is used, as defined by the
	* 							  field's VF_VERIFY.
	* VF_VERIFY	- This specifies either the callback method for verification, or the
	* 			  code to use in a lambda function.
	*
	*
	* @var array string => array(int, int, mixed)
	*/
	protected $fields = array();

	/**
	 * Map of table => field for fields that can automatically be updated with their
	 * set value.
	 * This should be defined in the child classes where the default save()
	 * implementation is used.
	 *
	 * If there are any fields that cannot be set with the default save()
	 * implementation, then they should not be included here and instead be updated
	 * with an overridden vB_DM::postSave(), vB_DM::preSave() or vB_DM::save().
	 *
	 * @var array (tablename => array(fieldnames))
	 */
	protected $table_fields;

	/**
	 * Table name of the primary table.
	 * This is used to get the new item id on an insert save and should be defined
	 * in child classes where the default save() implementation is used.
	 *
	 * @var string
	 */
	protected $primary_table;

	/**
	 * vB_Item Class.
	 * Class of the vB_Item that this DM is responsible for updating and/or
	 * creating.  This is used to instantiate the item when lazy loading based on an
	 * item id.  This should be defined in child classes where the default
	 * implementation is used.
	 *
	 * @var string
	 */
	protected $item_class;

	/**
	* Array of fields that have been specified.
	* The array is an assoc array with the fieldname and the set value.  Fields that
	* exist in $set_fields will not necessarily be different from the existing
	* value.
	* @see vB_DM::isUpdated()
	*
	* @var array string
	*/
	protected $set_fields = array();

	/**
	 * Array of field values loaded from an existing item.
	 *
	 * @var array mixed
	 */
	protected $existing_fields = array();

	/**
	 * Array of field values that should be inserted raw.
	 * This is useful for increments and relational changes such as 'field + 1'.
	 * The value is still stored in vB_DM::$set_fields.  Raw values should only be
	 * set internally by the DM, being invoked by defined methods.
	 *
	 * @var array bool
	 */
	protected $raw_fields = array();

	/**
	 * A primary id for REQ_INC fields.
	 * @see vB_DM::save()
	 *
	 * @var mixed
	 */
	protected $primary_id;

	/**
	 * The id of the existing item to update.
	 * This can be specified with vB_DM::setExisting instead of an item.  The item
	 * will then be lazy loaded when required.  The id is arbitrary and should be
	 * interpreted by the vB_DM::$item_class to create a new item.
	 *
	 * @var mixed
	 */
	protected $item_id;

	/**
	* The item being updated.
	* This is optional.  If it is not present with vB_DM::save() is called, then a
	* new item is assumed.
	*
	* @var vB_Item
	*/
	protected $item;

	/**
	 * Whether validation is in strict mode.
	 * If strict mode is on then validation errors will always throw an exception.
	 * This is useful when user feedback is not required and can be used to halt
	 * further DM processing.
	 * @see vB_DM::strictValidation()
	 *
	 * @var bool
	 */
	protected $strict;

	/**
	 * Array of validation error phrases.
	 * When a property is set by client code and fails validation, the error message
	 * is stored here.  Note that these error message may be displayed to the user
	 * and therefore must be phrased.
	 * @TODO User input validation should not be the responsibility of the DM?
	 *
	 * @var array vB_Phrase
	 */
	protected $errors;

	/**
	 * Whether to allow updates without a condition.
	 * To allow tables to be updated without getConditionSQL() returning a
	 * condition, the affected table must be included in this array.  Tables should
	 * be added with absolute care as an unconditional update will update the entire
	 * table.
	 *
	 * Note: The varname is intentionally verbose.
	 *
	 * @var array string
	 */
	protected $allow_update_without_condition_tables = false;

	/**
	 * Whether to allow deletes without a condition.
	 * To allow table records to be deleted without getConditionSQL() returning a
	 * condition, the affected table must be included in this array.  Tables should
	 * be added with absolute care as an unconditional delete will delete the
	 * contents of the entire table.
	 *
	 * Note: The varname is intentionally verbose.
	 *
	 * @var array string
	 */
	protected $allow_delete_without_condition_tables = false;

	/**
	 * Whether inserts can be deferred by default.
	 *
	 * @var bool
	 */
	protected $can_defer_insert = false;

	/**
	 * Whether the insert id is required for further queries during an insert.
	 * This can be defined manually, or left to be resolved with
	 * vB_DM::requireAutoIncrementId().
	 *
	 * @var bool
	 */
	protected $require_auto_increment_id;

	/**
	* Array to store information
	*
	* @var	array
	*/
	public $info = array();



	/*Initialisation================================================================*/

	/**
	* Constructor.
	*
	* @param vB_Item $existing_item				An existing item that will be updated.
	*/
	public function __construct(vB_Item $existing_item = null)
	{
		if ($existing_item)
		{
			$this->setExisting($existing_item);
		}

		if (!isset($this->item_class))
		{
			throw (new vB_Exception_DM('No item class defined for DM ' . get_class($this)));
		}

		if (!isset($this->primary_table))
		{
			throw (new vB_Exception_DM('No primary table defined for DM ' . get_class($this)));
		}
	}



	/*Existing======================================================================*/

	/**
	* Sets an existing item to be updated.
	* An id can also be specified which will be used to lazy load the item when
	* required.  Existing items are generally used by an overridden postSave() or
	* save() when existing information about the item is needed to perform queries;
	* or by getConditionSQL() to limit the query to the item.
	*
	* @param vB_Item | mixed $item				- The existing item or itemid
	* @param bool $reset						- Whether to reset all changes
	*/
	public function setExisting($item, $reset = true)
	{
		if ($item instanceof vB_Item)
		{
			if (!($item instanceof $this->item_class))
			{
				throw (new vB_Exception_DM('Item class given to DM for modification is not of the DM\'s defined type \'' . htmlspecialchars($this->item_class . '\'')));
			}

			$this->item = $item;
			$this->item_id = $item->getId();
		}
		else
		{
			$this->item_id = $item;
			unset($this->item);
		}

		unset($this->existing_fields);

		if ($reset)
		{
			$this->reset();
		}
	}


	/**
	 * Loads the existing fields from the current item.
	 *
	 * @return bool								- Success
	 */
	protected function loadExisting()
	{
		// Check if an existing item has been specified
		if (!isset($this->item) AND !isset($this->item_id))
		{
			return false;
		}

		// Ensure the item is instatiated
		$this->assertItem();

		// Tell the item to populate the DM
		$this->item->loadDM($this);

		if (sizeof($this->existing_fields))
		{
			return true;
		}

		throw (new vB_Exception_DM('Existing fields loaded from current item but no fields were set'));
	}


	/**
	 * Ensures the existing item is instantiated and valid.
	 */
	protected function assertItem()
	{
		if (isset($this->item))
		{
			return true;
		}

		if (!isset($this->item_id))
		{
			throw (new vB_Exception_DM('No item id specified for creating an existing item in ' . get_class($this)));
		}

		$this->item = new $this->item_class($this->item_id);

		if (!$this->item->isValid())
		{
			throw (new vB_Exception_DM('The existing item assigned to the DM is not valid (class: ' . $this->item_class . ' id: ' . print_r($this->item_id, 1) . ')'));
		}
	}


	/**
	 * Sets the existing item values.
	 * Only the current item is allowed to set the existing values.  If the DM does
	 * not require an item to load existing fields, then this should be overridden.
	 *
	 * @param array mixed $fields
	 * @param vB_Item $item
	 */
	public function setExistingFields($fields, vB_Item $item)
	{
		if ($item !== $this->item)
		{
			throw (new vB_Exception_DM('Only the existing item can set existing fields on the DM'));
		}

		foreach($fields AS $field => $value)
		{
			$this->setExistingField($field, $value, true);
		}
	}


	/**
	 * Sets an existing field.
	 * The field is verified before being set.
	 *
	 * @param string $fieldname					- The name of the field to set
	 * @param value $value						- The value to set
	 * @param bool $skip_undefined				- Skip values that are not defined
	 */
	protected function setExistingField($fieldname, $value, $skip_undefined = false)
	{
		if ($skip_undefined AND !isset($this->fields[$fieldname]))
		{
			return;
		}

		// Validate the field
	    $value = $this->validate($fieldname, $value, $error);
        if ($error)	
		{
			throw (new vB_Exception_DM('Existing field \'' . htmlspecialchars($fieldname) . '\' is not valid: ' . $error));
		}

		// Set the field
		$this->existing_fields[$fieldname] = $value;
	}



	/*Fields========================================================================*/

	/**
	* Fetches the current value for a field.
	* If a new value has not been specified and an existing item has, then the
	* current value of the existing item will be returned.
	*
	* @param string $fieldname					- The name of the field to fetch the value for
	*
	* @return	mixed	The requested data
	*/
	public function getField($fieldname, $ignore_errors = false)
	{
		if (!isset($this->fields[$fieldname]))
		{
			throw (new vB_Exception_DM('A field value for \'' . htmlspecialchars($fieldname) . '\' was requested from the DM \'' . get_class($this) . '\' but the field is not defined'));
		}

		// Check if the value has been set
		if (isset($this->set_fields[$fieldname]))
		{
			return $this->set_fields[$fieldname];
		}

		// Check if we have an existing item
		if (isset($this->existing_fields[$fieldname]))
		{
			return $this->existing_fields[$fieldname];
		}
		return false;
	}



	/*Set===========================================================================*/

	/**
	 * Sets a field.
	 * The field is always validated before being set.  If any transformation is
	 * required, then this method should be overridden.
	 *
	 * @param string $fieldname					- The name of the field to set
	 * @param mixed $value						- The value to set
	 */
	public function set($fieldname, $value)
	{
		if (false === $value)
		{
			$value = '';
		}

		$error = false;
		if (false === ($value = $this->validate($fieldname, $value, $error)))
		{
			if ($this->strict)
			{
				throw (new vB_Exception_DM('Value given to set DM \'' . get_class($this) . '\' field \'' . hmtlspecialchars($fieldname) . '\' is not valid: ' . $error));
			}

			$this->error($error, $fieldname);
		}

		if (self::REQ_AUTO == $this->fields[$fieldname][self::VF_REQ])
		{
			throw (new vB_Exception_DM('Cannot set the value of automatic field \'' . htmlspecialchars($fieldname) . '\' in DM \'' . get_class($this) . '\''));
		}

		$this->setField($fieldname, $value);
	}


	/**
	 * Sets a field value.
	 * Allows child classes to perform any transformations on the field value before saving.
	 *
	 * @param string $fieldname					- The name of the field to set
	 * @param mixed $value						- The value to set
	 */
	protected function setField($fieldname, $value)
	{
		$this->set_fields[$fieldname] = $value;
	}


	/**
	* Unsets a value that has already been set.
	*
	* @param string $fieldname					- The field to unset
	*/
	public function unsetField($fieldname)
	{
		if (!isset($this->fields[$fieldname]))
		{
			throw (new vB_Exception_DM('Call to unset undefined field \'' . htmlspecialchars($fieldname) . '\' in DM \'' . get_class($this) . '\''));
		}

		unset ($this->set_fields[$fieldname]);
	}


	/**
	 * Resets all set changes.
	 */
	protected function reset()
	{
		$this->set_fields = $this->existing_fields = $this->errors = array();
	}



	/*Validate======================================================================*/

	/**
	 * Enables or disables strict validation mode.
	 * When strict mode is on, failed validation should always throw an exception.
	 * This is useful in cases where errors will never be delivered to the user by
	 * the client code and therefore continuation of the DM process is redundant.
	 *
	 * @param bool $strict						- True to turn on strict validation
	 */
	public function strictValidation($strict = true)
	{
		$this->strict = $strict;
	}


	/**
	 * Validates specific fields.
	 * This should always be performed when setting or loading a field to ensure
	 * that it is valid.  The validation method of the specified field is determined
	 * from the definition in vB_DM::$fields and the appropriate validation
	 * method is performed.
	 *
	 * @param string $fieldname					- The name of the field to check
	 * @param mixed $value						- The value to validate
	 * @param vB_Phrase $error					- Out error message
	 * @return mixed							- Filtered value or boolean false
	 */
	public function validate($fieldname, $value, &$error)
	{
		if (!isset($this->fields[$fieldname]))
		{
			throw (new vB_Exception_DM('Field \'' . htmlspecialchars($fieldname) . '\' checked for validation in DM \'' . get_class($this) . '\' is undefined'));
		}

		$field = $this->fields[$fieldname];

		// Clean the value according to it's type
		$value = vB_Input::clean($value, $this->fields[$fieldname][self::VF_TYPE]);

		// If no validation method has been specified then we're done
		if (!isset($field[self::VF_METHOD]) OR (self::VM_TYPE == $field[self::VF_METHOD]))
		{
			return $value;
		}

		if (self::VM_LAMBDA == $field[self::VF_METHOD])
		{
			$lambda = create_function('$value', '&$error', $field[self::VF_VERIFY]);
			$value = $lambda($value, $error);
		}
		else if (self::VM_CALLBACK == $field[self::VF_METHOD])
		{
			// ensure a callback is specified
			if (!is_array($field[self::VF_VERIFY]) OR !sizeof($field[self::VF_VERIFY] >= 2))
			{
				throw (new vB_Exception_DM('Invalid callback function specified for field \'' . htmlspecialchars($fieldname) . '\' in DM \'' . get_class($this) . '\''));
			}

			// check for extra parameters
			if (sizeof($field[self::VF_VERIFY] > 2))
			{
				// extract callback
				$callback = array_slice($field[self::VF_VERIFY], 0, 2);

				// add value and error reference as the first paramaters
				$params = array($value);
				$params[] =& $error;

				// extract defined parameters
				$params = array_merge($params, array_slice($field[self::VF_VERIFY], 2));

				// check if callback is this
				if ('$this' == $callback[0])
				{
					$value = call_user_func_array(array($this, $callback[1]), $params);
				}
				else
				{
					// call user func
					$value = call_user_func_array($callback, $params);
				}
			}
			else
			{
				// no extra parameters in field definition
				$value = call_user_func($field[self::VF_VERIFY], $value, $error);
			}
		}
		else
		{
			throw (new vB_Exception_DM('Unknown verify method given for dm field \'' . htmlspecialchars($fieldname) . '\' in dm \'' . get_class($this) . '\''));
		}

		if (false !== $value)
		{
			return $value;
		}

		if (!$error)
		{
			$error = new vB_Phrase('error', 'invalid_dm_value_x_y', $fieldname, htmlspecialchars($value));
		}

		return $value;
	}



	/*Verify========================================================================*/

	/**
	 * Ensures that fields are defined for a specified table.
	 * The tables are defined in vB_DM::$table_fields.
	 *
	 * @param string $table
	 * @throws vB_Exception_DM
	 */
	protected function assertTable($table)
	{
		if (!isset($this->table_fields[$table]))
		{
			throw (new vB_Exception_DM('Fetching save SQL for undefined table \'' . htmlspecialchars($table) . '\' in DM \'' . get_class($this) . '\''));
		}

		if (empty($this->table_fields))
		{
			throw (new vB_Exception_DM('Fetching save SQL for undefined table \'' . htmlspecialchars($table) . '\' in DM \'' . get_class($this) . '\''));
		}
	}


	/**
	* Checks that all required fields are set.
	*
	* @return bool								- Whether all required fields are set
	*/
	protected function assertRequired()
	{
		foreach ($this->fields AS $fieldname => $field)
		{
			if ($field[self::VF_REQ] == self::REQ_YES AND !isset($this->set_fields[$fieldname]))
			{
				if ($this->strict)
				{
					throw (new vB_Exception_DM('Required field \'' . htmlspecialchars($fieldname) . '\' has not been set'));
				}

				$this->error(new vB_Phrase('error', 'required_field_x_missing_or_invalid', $fieldname));

				return false;
			}
		}

		return true;
	}


	/**
	 * Checks if an existing field has been modified.
	 * This is not used in the default implementation but can be used by child
	 * classes where needed.
	 *
	 * @param string $fieldname					- The name of the field to check
	 */
	protected function isUpdated($fieldname)
	{
		// Ensure the field is valid
		if (!isset($this->fields[$fieldname]))
		{
			throw (new vB_Exception_DM('isUpdated() was called for the field \'' . htmlspecialchars($fieldname) . '\' from the DM \'' . get_class($this) . '\' but the field is not defined'));
		}

		// Check if we have an update
		if (!isset($this->set_fields[$fieldname]))
		{
			return false;
		}

		// If no existing item is specified then fields are always updated.
		if (!isset($this->item) AND !isset($this->item_id))
		{
			return true;
		}

		// If the field is marked as raw then it is always considered new
		if (isset($this->raw_fields[$fieldname]))
		{
			return true;
		}

		// Ensure the existing fields are loaded from the item
		$this->loadExisting();

		// Check if the update differs from the existing value
		return ($this->set_fields[$fieldname] == $this->existing_fields[$fieldname]);
	}


	/**
	 * Basic options to perform on all pagetext fields.
	 *
	 * @param string $pagetext					- The pagetext to verify
	 * @param mixed $error						- The var to assign an error to
	 * @param bool $noshouting					- Whether to run the case stripper
	 *
	 * @return string							- Finalized pagetext
	 */
	protected function verifyPageText($pagetext, &$error, $noshouting = true)
	{
		// if we're skipping verification (ie for blog or post promotions)
		// return the unmodified pagetext
		if ($this->info['skip_verify_pagetext'])
		{
			return $pagetext;
		}

		require_once(DIR . '/includes/functions_newpost.php');

		$pagetext = preg_replace('/&#(0*32|x0*20);/', ' ', $pagetext);
		$pagetext = trim($pagetext);

		// remove empty bbcodes
		//$pagetext = $this->strip_empty_bbcode($pagetext);

		// add # to color tags using hex if it's not there
		$pagetext = preg_replace('#\[color=(&quot;|"|\'|)([a-f0-9]{6})\\1]#i', '[color=\1#\2\1]', $pagetext);

		// strip alignment codes that are closed and then immediately reopened
		$pagetext = preg_replace('#\[/(left|center|right)]((\r\n|\r|\n)*)\[\\1]#si', '\\2', $pagetext);

		// remove [/list=x remnants
		if (stristr($pagetext, '[/list=') != false)
		{
			$pagetext = preg_replace('#\[/list=[a-z0-9]+\]#siU', '[/list]', $pagetext);
		}

		// remove extra whitespace between [list] and first element
		// -- unnecessary now, bbcode parser handles leading spaces after a list tag
		//$pagetext = preg_replace('#(\[list(=(&quot;|"|\'|)([^\]]*)\\3)?\])\s+#i', "\\1\n", $pagetext);

		// censor main message text
		$pagetext = fetch_censored_text($pagetext);

		// parse URLs in message text
		if ($this->info['parseurl'])
		{
			$pagetext = convert_url_to_bbcode($pagetext);
		}

		// remove sessionhash from urls:
		require_once(DIR . '/includes/functions_login.php');
		$pagetext = fetch_removed_sessionhash($pagetext);

		if ($noshouting)
		{
			$pagetext = fetch_no_shouting_text($pagetext);
		}

		require_once(DIR . '/includes/functions_video.php');
		$pagetext = parse_video_bbcode($pagetext);

		return $pagetext;
	}



	/*Save==========================================================================*/

	/**
	 * Prepare fields before saving.
	 */
	protected function prepareFields(){}


	/**
	 * Saves the set fields.
	 * Either an insert or update will be performed depending on whether an existing
	 * item was loaded.
	 *
	 * @param bool $deferred					- Defer the query until shutdown
	 * @param bool $get_affected_rows			- Return the affected rows if updating
	 * @param bool $replace						- Use REPLACE if inserting
	 * @param bool $ignore						- Use IGNORE if inserting
	 * @return int								- The insert id or affected rows
	 */
	public function save($deferred = false, $get_affected_rows = false, $replace = false, $ignore = false)
	{
		// Ensure we don't have errors
		if ($this->hasErrors())
		{
			return false;
		}

		// Allow child classes to transform values and resolve auto fields before insert
		$this->prepareFields();

		// Perform any pre save
		if (!$this->preSave($deferred, $replace, $ignore))
		{
			return false;
		}

		// Do an update
		if ($this->isUpdating())
		{
			// return the affected rows from the primary table if found
			$primary_result = 0;

			// update each table
			foreach (array_keys($this->table_fields) AS $table)
			{
				$result = $this->execUpdate($table, $deferred, $get_affected_rows);

				if ($this->primary_table == $table)
				{
					$primary_result = $result;
				}
			}

			$result = ($primary_result ? $primary_result : $result);
		}
		else
		{
			if ($deferred)
			{
				// check if we can defer
				$deferred = $this->canDeferInsert();
			}

			if (!$deferred)
			{
				// if we have any non primary table REQ_INC, ensure we insert on the primary table first
				if ($this->requireAutoIncrementId())
				{
					if ($replace)
					{
						throw (new vB_Exception_DM('Cannot use REPLACE if there are pending REQ_INC fields in DM \'' . get_class($this) . '\''));
					}

					if ($ignore)
					{
						throw (new vB_Exception_DM('cannot use IGNORE as there are pending REQ_INC fields in DM \'' . get_class($this) . '\''));
					}
				}

				// check required fields
				if (!$this->assertRequired())
				{
					return false;
				}

				// perform insert on primary table
				$result = $this->execInsert($this->primary_table);

				// set auto increment from primary table
				if ($this->requireAutoIncrementId())
				{
					$this->setAutoIncrementId($result);
				}

				// set auto inc for other tables
				foreach (array_keys($this->table_fields) AS $table)
				{
					if ($table == $this->primary_table)
					{
						continue;
					}

					$this->execInsert($table, $replace, $ignore);
				}
			}
		}

		// Perform any post save
		$result = $this->postSave($result, $deferred, $replace, $ignore);

		// Unset set fields as they are now saved
		unset($this->set_fields);

		return $result;
	}


	/**
	 * Check if save should UPDATE or INSERT
	 *
	 * @return bool								- Whether we are updating
	 */
	public function isUpdating()
	{
		return ($this->item_id OR $this->item);
	}


	/**
	 * Run any checks or tranformations before saving.
	 * Return false to cancel the save.
	 *
	 * @param bool $deferred					- Save will be deferred until shutdown
	 * @param bool $replace						- Save will use REPLACE
	 * @param bool $ignore						- Save will use IGNORE if inserting
	 *
	 * @return bool								- Whether to save
	 */
	protected function preSave($deferred = false, $replace = false, $ignore = false)
	{
		return true;
	}


	/**
	* Performs additional queries or tasks after saving.
	* Additional queries such as denormalized data in other tables can be performed
	* here.
	*
	* Return false to indicate that the entire save process was not a success.
	*
	* @param mixed								- The save result
	* @param bool $deferred						- Save was deferred
	* @param bool $replace						- Save used REPLACE
	* @param bool $ignore						- Save used IGNORE if inserting
	* @return bool								- Whether the save can be considered a success
	*/
	protected function postSave($result, $deferred, $replace, $ignore)
	{
		return $result;
	}


	/**
	* Resolves the condition SQL to be used in update queries.
	* This method is abstract and must be defined as there should always be a
	* condition for an existing item.
	* In child classes where the default save implementation is not used, this can
	* return false.
	*
	* @param string $table						- The table to get the condition for
	* @return string							- The resolved sql
	*/
	abstract protected function getConditionSQL($table);



	/*Update========================================================================*/

	/**
	* Builds the UPDATE query for automated fields.
	* The set fields for the specified table are checked for changes.  These are
	* then built into an UPDATE query.  If no changed fields are found then the
	* function returns false.
	*
	* @param string $table						- The table to update
	* @return string | false					- The compiled query or false
	*/
	protected function getUpdateSQL($table)
	{
		// Ensure there is a condition
		if (!($condition = $this->getConditionSQL($table)) AND !$this->allowUpdateWithoutCondition($table))
		{
			throw (new vB_Exception_DM('getUpdateSQL() was called in DM \'' . get_class($this) . '\' with no condition'));
		}

		// Ensure the table is defined
		$this->assertTable($table);

		// Build the SET fields
		$sql = '';
		foreach ($this->table_fields[$table] AS $fieldname)
		{
			if (array_key_exists($fieldname, $this->set_fields))
			{
				$sql .= "\r\n\t$fieldname = " . (array_key_exists($fieldname, $this->raw_fields) ?
													($this->raw_fields[$fieldname] === null ? 'NULL' : $this->raw_fields[$fieldname]) :
													vB::$db->sql_prepare($this->set_fields[$fieldname])) . ",";
			}
		}

		// If no fields found, return false
		if (!$sql)
		{
			return false;
		}

		// Add the UPDATE table
		$sql = "UPDATE " . TABLE_PREFIX . "$table SET" . $sql;

		// Remove trailing comma
		$sql = substr($sql, 0, -1);

		// Add condition
		if ($condition)
		{
			$sql .= "\r\nWHERE $condition";
		}

		return $sql;
	}


	/**
	* Performs an UPDATE on the current item.
	* Note: For obvious reasons, if the update is deferred, there can be no error
	* feedback for 0 affected_rows.
	*
	* @param string	$table						- The table to update
	* @param bool $deferred						- Whether to defer the update until shutdown
	* @param bool $get_affected_rows			- Whether to return the number of affected rows
	*
	* @return bool | int						- Success or affected rows
	*/
	protected function execUpdate($table, $deferred = false, $get_affected_rows = false)
	{
			if ($this->hasErrors())
		{
			return false;
		}

		if (!($sql = $this->getUpdateSQL($table)))
		{
			return 0;
		}

		// Defer query until shutdown
		if ($deferred)
		{
			if (is_string($deferred))
			{
				vB::$db->shutdown_query($sql, $deferred);
			}
			else
			{
				vB::$db->shutdown_query($sql);
			}
		}
		else
		{
			// exec the query

			vB::$db->query_write($sql);

			if ($get_affected_rows)
			{
				return vB::$db->affected_rows();
			}
		}

		return true;
	}


	/**
	 * Checks if an automated update is allowed without a condition.
	 * Using the default implementation, an automated update is only allowed on
	 * tables that are defined in self::$allow_update_without_condition_tables.
	 *
	 * @param string $table						- The table to check
	 * @return bool
	 */
	protected function allowUpdateWithoutCondition($table)
	{
		return in_array($table, $this->allow_update_without_condition_tables);
	}



	/*Insert========================================================================*/

	/**
	 * Builds an INSERT/REPLACE query for automated fields.
	 *
	 * @param string $table						- The table to insert into.
	 * @param bool $replace						- Whether to use a REPLACE instead of an INSERT
	 * @param bool $ignore						- Whether to IGNORE
	 * @return string							- The compiled SQL
	 */
	protected function getInsertSQL($table, $replace = false, $ignore = false)
	{
		// Ensure the table is defined
		$this->assertTable($table);

		// Get the fields to set
		$fields = array();
		foreach($this->table_fields[$table] AS $fieldname)
		{
			if (isset($this->set_fields[$fieldname]))
			{
				$fields[$fieldname] = $this->set_fields[$fieldname];
			}
		}

		// Ensure there are fields to insert
		if (!sizeof($fields))
		{
			throw (new vB_Exception_DM('No fields to INSERT for DM \'' . get_class($this . '\'')));
		}

		// Add fieldnames
		$sql = ($replace ? "REPLACE" : ($ignore ? "INSERT IGNORE" : "INSERT")) . " INTO " . TABLE_PREFIX . "$table\r\n\t(" . implode(', ', array_keys($fields)) . ")\r\nVALUES\r\n\t(";

		// Add values
		foreach ($fields AS $value)
		{
			$sql .= (isset($this->raw_fields[$fieldname]) ? ($value === null ? 'NULL' : $value) : vB::$db->sql_prepare($value)) . ", ";
		}

		// Remove trailing comma and close VALUES
		$sql = substr($sql, 0, -2);
		$sql .= ')';

		return $sql;
	}


	/**
	 * Performs an INSERT with the set fields.
	 *
	 * @param string $table						- The table to insert into
	 * @param bool $replace						- Whether to REPLACE instead of INSERT
	 * @param bool $ignore						- Whether to IGNORE
	 * @return int								- The insert id, or affected rows if using IGNORE
	 */
	protected function execInsert($table, $replace = false, $ignore = false)
	{
		// Ensure we don't have any errors
		if ($this->hasErrors())
		{
			return false;
		}

		// Get INSERT sql
		if (!($sql = $this->getInsertSQL($table, $replace)))
		{
			throw (new vB_Exception_DM('No SQL returned for insert query in DM \'' . get_class($this) . '\''));
		}

		// Exec query
		vB::$db->query_write($sql);

		// Return the insert id
		return ($ignore ? vB::$db->affected_rows() : vB::$db->insert_id());
	}


	/**
	 * Checks if we can defer saving on inserts.
	 * If any fields are REQ_INC that are not in the primary table then we cannot
	 * defer the insert queries as we will need the insert id to perform them.
	 *
	 * Classes that extend the insert implementation with non deferrable statements
	 * can set vB_DM::$can_defer to false.
	 *
	 * @return bool
	 */
	protected function canDeferInsert()
	{
		if (!$this->can_defer_insert)
		{
			return false;
		}

		return !$this->requireAutoIncrementId();
	}


	/**
	 * Checks if we require the insert id for auto incrementing for an insert.
	 *
	 * @return bool
	 */
	protected function requireAutoIncrementId()
	{
		// Check if a primary id has been set
		if ($this->primary_id)
		{
			return $this->require_auto_increment_id = false;
		}

		// Check local cached result
		if (isset($this->require_auto_increment_id))
		{
			return $this->require_auto_increment_id;
		}

		// Look for fields that are still waiting for an auto inc key
		foreach ($this->table_fields AS $table => $fields)
		{
			// REQ_INC on the primary table can be deferred
			if ($table == $this->primary_table)
			{
				continue;
			}

			foreach ($fields AS $fieldname)
			{
				if ((self::REQ_INC == $this->fields[$fieldname[self::VF_REQ]]) AND !isset($this->set_fields[$fieldname]))
				{
					return $this->require_auto_increment_id = true;
				}
			}
		}

		return $this->require_auto_increment_id = false;
	}


	/**
	 * Sets the insert id on all REQ_INC fields.
	 *
	 * @param mixed $id
	 */
	public function setAutoIncrementId($id)
	{
		if ($this->isUpdating())
		{
			return false;
		}

		$this->primary_id = $id;

		// Set any auto inc fields with the id
		foreach ($this->fields AS $fieldname => &$field)
		{
			if ((self::REQ_INC == $field[self::VF_REQ]) AND !isset($this->set_fields[$fieldname]))
			{
				$this->set($fieldname, $id);
			}
		}
	}



	/*Delete========================================================================*/

	/**
	* Deletes the existing item.
	*
	* @return int								- The number of affected rows.
	*/
	public function delete()
	{
		if ($this->hasErrors())
		{
			return false;
		}

		if (!$this->preDelete())
		{
			return false;
		}

		$return = $this->execDelete($this->primary_table);

		foreach (array_keys($this->table_fields) AS $table)
		{
			if ($table != $this->primary_table)
			{
				$this->execDelete($table);
			}
		}

		return $this->postDelete($return);
	}


	/**
	 * Performs a DELETE using the current item.
	 *
	 * @param string $table						- The table to delete from
	 * @return int								- Number of affected rows
	 */
	protected function execDelete($table)
	{
		$condition = $this->getConditionSQL($table);

		// Check condition
		if (!$condition AND !$this->allowDeleteWithoutCondition($table))
		{
			throw (new vB_Exception_DM('execDelete() was called in DM \'' . get_class($this) . '\' with no condition'));
		}

		// Build SQL
		$sql = "DELETE FROM " . TABLE_PREFIX . "$table WHERE $condition";

		// Exec delete
		vB::$db->query_write($sql);

		// Return affected rows
		return vB::$db->affected_rows();
	}


	/**
	 * Checks if an automated delete is allowed without a condition.
	 * Using the default implementation, an automated delete is only allowed on
	 * tables that are defined in self::$allow_delete_without_Condition_tables.
	 *
	 * @param string $table						- The table to check
	 * @return bool
	 */
	protected function allowDeleteWithoutCondition($table)
	{
		return in_array($table, $this->allow_delete_without_condition_tables);
	}


	/**
	* Additional tasks to perform before a delete.
	* Also determines if the delete should be executed.  Return false to cancel the
	* delete.
	*
	* @return									- Whether to perform the delete
	*/
	protected function preDelete()
	{
		return true;
	}


	/**
	* Additional tasks to perform after a delete.
	*
	* Return false to indicate that the entire delete process was not a success.
	*
	* @param mixed								- The result of execDelete()
	*/
	protected function postDelete($result)
	{
		return $result;
	}


	/** checks to see if a field has been set ***/
	public function getSet($field)
	{
		return isset($this->set_fields[$field]);
	}

	/*Errors========================================================================*/

	/**
	 * Sets an error phrase.
	 *
	 * @param vB_Phrase | string $error			- The error message
	 * @param string $fieldname					- The field that the error occured on
	 */
	protected function error($error, $fieldname = false)
	{
		if ($fieldname)
		{
			$this->errors[$fieldname] = $error;
		}
		else
		{
			$this->errors[] = $error;
		}
	}


	/**
	* Checks if the DM has errors.
	*/
	public function hasErrors()
	{
		return !empty($this->errors);
	}


	/**
	 * Fetches any DM errors.
	 * An array of field names can be passed to whitelist the errors that are
	 * required.  This is useful for fetching only user friendly errors.  The order
	 * of the given fields are preserved, which is useful for building ordered
	 * summaries.
	 *
	 * @param array $fields						- Which errors to allow
	 * @return array vB_Phrase
	 */
	public function getErrors(array $fields = null)
	{
		if ($fields)
		{
			// can't use array_intersect_key as we lose the order of $fields
			$errors = array();

			foreach ($fields AS $field)
			{
				if (isset($this->errors[$field]))
				{
					$errors[$field] = $this->errors[$field];
				}
			}

			return $errors;
		}

		return $this->errors;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 28749 $
|| ####################################################################
\*======================================================================*/