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

if (!class_exists('vB_DataManager'))
{
	exit;
}

/**
* Class to do data save/delete operations for StyleVarDefinitions.
*
* @package	vBulletin
* @version	$Revision: 34206 $
* @date		$Date: 2009-12-08 18:12:21 -0800 (Tue, 08 Dec 2009) $
*/

class vB_DataManager_StyleVarDefn extends vB_DataManager
{
	/**
	* Array of recognized and required fields for attachment inserts
	*
	* @var	array
	*/
	var $validfields = array(
		'stylevarid'    => array(TYPE_STR,      REQ_YES),
		'styleid'       => array(TYPE_INT,      REQ_NO,   'if ($data < -1) { $data = 0; } return true;'),
		'parentid'      => array(TYPE_INT,      REQ_YES,  VF_METHOD),
		// 'parentlist'    => array(TYPE_STR,      REQ_AUTO, 'return preg_match(\'#^(\d+,)*-1$#\', $data);'),
		'parentlist'    => array(TYPE_STR,      REQ_NO),
		'stylevargroup' => array(TYPE_STR,		REQ_YES),
		'product'       => array(TYPE_STR,		REQ_YES,  VF_METHOD),
		'datatype'      => array(TYPE_STR,		REQ_YES,  VF_METHOD),
		'validation'    => array(TYPE_STR,		REQ_NO),
		'failsafe'      => array(TYPE_STR,		REQ_NO),
		'units'         => array(TYPE_STR,		REQ_NO,   VF_METHOD),
		'uneditable'    => array(TYPE_BOOL,		REQ_YES),
	);

	/**
	* Array of field names that are bitfields, together with the name of the variable in the registry with the definitions.
	* For example: var $bitfields = array('options' => 'bf_misc_useroptions', 'permissions' => 'bf_misc_moderatorpermissions')
	*
	* @var	array
	*/
	var $bitfields = array();

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'stylevardfn';

	/**
	* Condition template for update query
	*
	* @var	array
	*/
	var $condition_construct = array('stylevarid = "%1$s"', 'stylevarid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	public function __construct(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('stylevardfndata_start')) ? eval($hook) : false;
	}

	/**
	* Verifies that the parent style specified exists and is a valid parent for this style
	*
	* @param	integer	Parent style ID
	*
	* @return	boolean	Returns true if the parent id is valid, and the parent style specified exists
	*/
	public function verify_parentid(&$parentid)
	{
		if ($parentid == $this->fetch_field('styleid'))
		{
			$this->error('cant_parent_style_to_self');
			return false;
		}
		else if ($parentid <= 0)
		{
			$parentid = -1;
			return true;
		}
		else if (!isset($this->registry->stylecache["$parentid"]))
		{
			$this->error('invalid_style_specified');
			return false;
		}
		else if ($this->condition !== null)
		{
			return $this->is_substyle_of($this->fetch_field('styleid'), $parentid);
		}
		else
		{
			// no condition specified, so it's not an existing style...
			return true;
		}
	}

	/**
	* Verifies that a given style parent id is not one of its own children
	*
	* @param	integer	The ID of the current style
	* @param	integer	The ID of the style's proposed parentid
	*
	* @return	boolean	Returns true if the children of the given parent style does not include the specified style... or something
	*/
	public function is_substyle_of($styleid, $parentid)
	{
		// TODO: TEST THIS FUNCTION!!!  Coded w/o testing or reference
		while (is_array($this->registry->stylecache["$styleid"]))
		{
			$curstyle = $this->registry->stylecache["$styleid"];
			if ($curstyle['parentid'] == $parentid)
			{
				return true;
			}
			if ($curstyle['parentid'] == -1)
			{
				break;
			}
			$styleid = $curstyle['parentid'];
		}
		$this->error('cant_parent_style_to_child');
		return flase;
	}


	public function verify_product($product)
	{
		// check if longer than 25 chars, contains anything other than a-zA-Z1-0
		return (preg_match('#^[a-z0-9_]+$#s', $product) AND strlen($product) < 25);
	}

	public function verify_datatype($datatype)
	{
		$valid_datatypes = array('numeric', 'string', 'color', 'url', 'path', 'background', 'imagedir', 'fontlist', 'textdecoration', 'dimension', 'border', 'padding', 'margin', 'font', 'size');
		return in_array($datatype, $valid_datatypes);
	}

	public function verify_units($unit)
	{
		$valid_units = array('', '%', 'px', 'pt', 'em', 'ex', 'pc', 'in', 'cm', 'mm');
		return in_array($unit, $valid_units);
	}

	/**
	* Any checks to run immediately before saving. If returning false, the save will not take place.
	*
	* @param	boolean	Do the query?
	*
	* @return	boolean	True on success; false if an error occurred
	*/
	public function pre_save($doquery = true)
	{
		if ($this->presave_called !== null)
		{
			return $this->presave_called;
		}

		$return_value = true;
		($hook = vBulletinHook::fetch_hook('stylevardfndata_presave')) ? eval($hook) : false;

		$this->presave_called = $return_value;
		return $return_value;
	}

	/**
	* Additional data to update after a save call (such as denormalized values in other tables).
	* In batch updates, is executed for each record updated.
	*
	* @param	boolean	Do the query?
	*/
	public function post_save_each($doquery = true)
	{
		($hook = vBulletinHook::fetch_hook('stylevardfndata_postsave')) ? eval($hook) : false;
	}

	/**
	* Deletes a stylevardfn and its associated data from the database
	*/
	public function delete()
	{
		// fetch list of stylevars to delete
		$stylevardfnlist = '';

		$stylevardfns = $this->dbobject->query_read_slave("SELECT stylevarid FROM " . TABLE_PREFIX . "stylevardfn WHERE " . $this->condition);
		while($thisdfn = $this->dbobject->fetch_array($stylevardfns))
		{
			$stylevardfnlist .= ',' . $thisdfn['stylevarid'];
		}
		$this->dbobject->free_result($stylevardfns);

		$stylevardfnlist = substr($stylevardfnlist, 1);

		if ($stylevardfnlist == '')
		{
			// nothing to do
			$this->error('invalid_stylevardfn_specified');
		}
		else
		{
			$condition = "stylevarid IN ($stylevardfnlist)";

			// delete from data tables
			$this->db_delete(TABLE_PREFIX, 'stylevar', $condition);
			$this->db_delete(TABLE_PREFIX, 'stylevardfn', $condition);

			($hook = vBulletinHook::fetch_hook('stylevardfndata_delete')) ? eval($hook) : false;
		}
	}
}

/**
* Abstract class to do data save/delete operations for StyleVar.
*
* @package	vBulletin
* @version	$Revision: 34206 $
* @date		$Date: 2009-12-08 18:12:21 -0800 (Tue, 08 Dec 2009) $
*/
class vB_DataManager_StyleVar extends vB_DataManager
{
	/**
	* Array of field names that are bitfields, together with the name of the variable in the registry with the definitions.
	* For example: var $bitfields = array('options' => 'bf_misc_useroptions', 'permissions' => 'bf_misc_moderatorpermissions')
	*
	* @var	array
	*/
	var $bitfields = array();

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'stylevar';

	/**
	* The name of the primary ID column that is used to uniquely identify records retrieved.
	* This will be used to build the condition in all update queries!
	*
	* @var string
	*/
	var $primary_id = 'stylevarid';

	/**
	* Array of recognised and required fields for stylevar, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'stylevarid'		=> array(TYPE_STR,			REQ_YES,	VF_METHOD, 'verify_stylevar'),
		'styleid'			=> array(TYPE_INT,			REQ_YES,	'if ($data < -1) { $data = 0; } return true;'),
		'dateline'			=> array(TYPE_UNIXTIME,		REQ_AUTO),
		'username'			=> array(TYPE_STR,			REQ_NO),
		'value'				=> array(TYPE_ARRAY_STR,	REQ_NO,   	VF_METHOD, 'verify_serialized'),
	);

	/**
	* Local storage, used to house data that we will be serializing into value
	*
	* @var  array
	*/
	var $local_storage = array();
	var $childvals = array();

	/**
	* Local value telling us what datatype this is; saves the resources of gettype()
	*
	* @var  string
	*/
	var $datatype = '';

	/**
	* Condition template for update query
	*
	* @var	array
	*/
	var $condition_construct = array('stylevarid = "%1$s" AND styleid = %2$d', 'stylevarid', 'styleid');

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	public function __construct(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		($hook = vBulletinHook::fetch_hook('stylevardata_start')) ? eval($hook) : false;
	}

	/**
	* Sets the datatype of this Stylevar so we know how to build the template value, should be triggered by constructor of individual types
	*/
	function set_datatype($datatype)
	{
		$this->datatype = $datatype;
	}

	/**
	* Saves the data from the object into the specified database tables
	*
	* @param	boolean	Do the query?
	* @param	boolean	Whether to call post_save_once() at the end
	*
	* @return	boolean	True on success; false on failure
	*/
	function save($doquery = true, $call_save_once = true)
	{
		if ($this->has_errors())
		{
			return false;
		}

		if (!$this->pre_save($doquery))
		{
			return false;
		}

		if ($this->condition === null)
		{
			$return = $this->db_insert(TABLE_PREFIX, $this->table, $doquery);
		}
		else
		{
			$return = $this->db_update(TABLE_PREFIX, $this->table, $this->condition, $doquery, $delayed);
		}

		if ($return AND $this->post_save_each($doquery) AND $this->post_save_once($doquery))
		{
			return $return;
		}
		else
		{
			return false;
		}
	}

	/**
	* database build method that builds the data into our value field
	*
	* @return	boolean	True on success; false if an error occurred
	*/
	public function build()
	{
		// similar to check required, this verifies actual data for stylevar instead of the datamanager fields
		if (is_array($this->childfields)) {
			foreach ($this->childfields AS $fieldname => $validfield)
			{
				if ($validfield[VF_REQ] == REQ_YES AND !$this->local_storage["$fieldname"])
				{
					$this->error('required_field_x_missing_or_invalid', $fieldname);
					return false;
				}
			}
			$this->set('value', $this->childvals);
		}
		else
		{
			$this->set('value', array());
		}
		return true;
	}

	/**
	* Sets the supplied data to be part of the data to be build into value.
	*
	* @param	string	The name of the field to which the supplied data should be applied
	* @param	mixed	The data itself
	* @param	boolean	Clean data, or insert it RAW (used for non-arbitrary updates, like posts = posts + 1)
	* @param	boolean	Whether to verify the data with the appropriate function. Still cleans data if previous arg is true.
	* @param	string	Table name to force. Leave as null to use the default table
	*
	* @return	boolean	Returns false if the data is rejected for whatever reason
	*/
	public function set_child($fieldname, $value, $clean = true, $doverify = true, $table = null)
	{
		if ($clean)
		{
			$verify = $this->verify_child($fieldname, $value, $doverify);
			if ($verify === true)
			{
				$errsize = sizeof($this->errors);
				$this->do_set_child($fieldname, $value, $table);
				return true;
			}
			else
			{
				if ($this->childfields["$fieldname"][VF_REQ] AND $errsize == sizeof($this->errors))
				{
					$this->error('required_field_x_missing_or_invalid', $fieldname);
				}
				return $verify;
			}
		}
		else if (isset($this->childfields["$fieldname"]))
		{
			$this->local_storage["$fieldname"] = true;
			$this->do_set_child($fieldname, $value, $table);
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	* Verifies that the supplied child data is one of the fields used by this object
	*
	* Also ensures that the data is of the correct type,
	* and attempts to correct errors in the supplied data.
	*
	* @param	string	The name of the field to which the supplied data should be applied
	* @param	mixed	The data itself
	* @param	boolean	Whether to verify the data with the appropriate function. Data is still cleaned though.
	*
	* @return	boolean	Returns true if the data is one of the fields used by this object, and is the correct type (or has been successfully corrected to be so)
	*/
	public function verify_child($fieldname, &$value, $doverify = true)
	{
		if (isset($this->childfields["$fieldname"]))
		{
			$field =& $this->childfields["$fieldname"];

			// clean the value according to its type
			$value = $this->registry->input->clean($value, $field[VF_TYPE]);

			if ($doverify AND isset($field[VF_CODE]))
			{
				if ($field[VF_CODE] === VF_METHOD)
				{
					if (isset($field[VF_METHODNAME]))
					{
						return $this->{$field[VF_METHODNAME]}($value);
					}
					else
					{
						return $this->{'verify_' . $fieldname}($value);
					}
				}
				else
				{
					$lamdafunction = create_function('&$data, &$dm', $field[VF_CODE]);
					return $lamdafunction($value, $this);
				}
			}
			else
			{
				return true;
			}
		}
		else
		{
			trigger_error("Field <em>$fieldname</em> is not defined in <em>\$validfields</em> in class <strong>" . get_class($this) . "</strong>", E_USER_ERROR);
			return false;
		}
	}

	/**
	* Takes valid data and sets it as part of the child data to be saved
	*
	* @param	string	The name of the field to which the supplied data should be applied
	* @param	mixed		The data itself
	* @param	string	Table name to force. Leave as null to use the default table
	*/
	public function do_set_child($fieldname, &$value, $table = null)
	{
		$this->local_storage["$fieldname"] = true;
		$this->childvals["$fieldname"] =& $value;
	}

	/**
	* Validation functions
	*/
	public function verify_stylevar($stylevarid)
	{
		// check if longer than 25 chars, contains anything other than a-zA-Z1-0
		$return = preg_match('#^[_a-z][a-z0-9_]*$#i', $stylevarid) ? true : false;
		return ($return);
	}

	public function verify_url($url)
	{
		// TODO: validate the URL
		// return true if it is a valid URL
		return true;
	}

	public function verify_color($color)
	{
		// TODO: validate the color
		// return true if it is a valid color
		return true;
	}

	public function verify_image($image)
	{
		// TODO: validate the image is an image -- just a string though?
		// return true if it is an image
		return true;
	}

	public function verify_repeat($repeat)
	{
		// TODO: validate if the repeat is one of the valid repeats
		// return true if it is a valid repeat
		$valid_repeat = array(
			'',
			'repeat',
			'repeat-x',
			'repeat-y',
			'no-repeat'
		);
		return in_array($repeat, $valid_repeat);
	}

	public function verify_fontfamily($family)
	{
		return true;
	}

	public function verify_fontweight($weight)
	{
		return true;
	}

	public function verify_fontstyle($style)
	{
		return true;
	}

	public function verify_fontvariant($variant)
	{
		return true;
	}

	public function verify_size($variant)
	{
		return true;
	}

	public function verify_width($width)
	{
		return true;
	}

	public function verify_height($height)
	{
		return true;
	}

	public function verify_fontlist($fontlist)
	{
		// TODO: validate fontlist is a list of fonts, with "'" wrapped around font names with spaces, and each font separated with a ",".
		return true;
	}

	public function verify_units($unit)
	{
		$valid_units = array('', '%', 'px', 'pt', 'em', 'ex', 'pc', 'in', 'cm', 'mm');
		return in_array($unit, $valid_units);
	}

	public function verify_margin($margin)
	{
		return ($margin === 'auto' OR $margin === strval($margin + 0));
	}
}

class vB_DataManager_StyleVarNumeric extends vB_DataManager_StyleVar
{
	/**
	* Array of recognised and required child fields for stylevar, and their types
	*
	* @var	array
	*/
	var $childfields = array(
		'numeric'			=> array(TYPE_NUM,			REQ_NO),
	);

	/**
	 * Constructor - checks that the registry object has been passed correctly.
	 *
	 * @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	 * @param	integer		One of the ERRTYPE_x constants
	 */
	public function __construct(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::__construct($registry, $errtype);
		parent::set_datatype("Numeric");
	}
}

class vB_DataManager_StyleVarString extends vB_DataManager_StyleVar
{
	/**
	* Array of recognised and required child fields for stylevar, and their types
	*
	* @var	array
	*/
	var $childfields = array(
		'string'			=> array(TYPE_STR,			REQ_NO),
	);

	/**
	 * Constructor - checks that the registry object has been passed correctly.
	 *
	 * @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	 * @param	integer		One of the ERRTYPE_x constants
	 */
	public function __construct(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::__construct($registry, $errtype);
		parent::set_datatype("String");
	}
}

class vB_DataManager_StyleVarURL extends vB_DataManager_StyleVar
{
	var $childfields = array(
		'url'				=> array(TYPE_STR,			REQ_NO,		VF_METHOD),
	);

	/**
	 * Constructor - checks that the registry object has been passed correctly.
	 *
	 * @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	 * @param	integer		One of the ERRTYPE_x constants
	 */
	public function __construct(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::__construct($registry, $errtype);
		parent::set_datatype("URL");
	}
}

class vB_DataManager_StyleVarImageDir extends vB_DataManager_StyleVar
{
	var $childfields = array(
		'imagedir'			=> array(TYPE_STR,			REQ_NO),
	);

	/**
	 * Constructor - checks that the registry object has been passed correctly.
	 *
	 * @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	 * @param	integer		One of the ERRTYPE_x constants
	 */
	public function __construct(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::__construct($registry, $errtype);
		parent::set_datatype("ImageDir");
	}
}

class vB_DataManager_StyleVarPath extends vB_DataManager_StyleVar
{
	var $childfields = array(
		'path'				=> array(TYPE_STR,			REQ_NO),
	);

	/**
	 * Constructor - checks that the registry object has been passed correctly.
	 *
	 * @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	 * @param	integer		One of the ERRTYPE_x constants
	 */
	public function __construct(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::__construct($registry, $errtype);
		parent::set_datatype("Path");
	}
}

class vB_DataManager_StyleVarColor extends vB_DataManager_StyleVar
{
	var $childfields = array(
		'color'				=> array(TYPE_STR,			REQ_NO,		VF_METHOD),
	);

	/**
	 * Constructor - checks that the registry object has been passed correctly.
	 *
	 * @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	 * @param	integer		One of the ERRTYPE_x constants
	 */
	public function __construct(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::__construct($registry, $errtype);
		parent::set_datatype("Color");
	}
}

class vB_DataManager_StyleVarBackground extends vB_DataManager_StyleVar
{
	var $childfields = array(
		'image'				=> array(TYPE_STR,			REQ_NO,		VF_METHOD,	'verify_image'),
		'color'				=> array(TYPE_STR,			REQ_NO,		VF_METHOD),
		'repeat'			=> array(TYPE_STR,			REQ_NO,		VF_METHOD,	'verify_repeat'),
		'units'				=> array(TYPE_STR,			REQ_NO,		VF_METHOD,	'verify_units'),
		'x'						=> array(TYPE_STR,			REQ_NO),
		'y'						=> array(TYPE_STR,			REQ_NO),
	);

	/**
	 * Constructor - checks that the registry object has been passed correctly.
	 *
	 * @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	 * @param	integer		One of the ERRTYPE_x constants
	 */
	public function __construct(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::__construct($registry, $errtype);
		parent::set_datatype("Background");
	}
}

class vB_DataManager_StyleVarFont extends vB_DataManager_StyleVar
{
	var $childfields = array(
		'family'			=> array(TYPE_STR,			REQ_NO,		VF_METHOD,	'verify_fontfamily'),
		'units'				=> array(TYPE_STR,			REQ_NO,		VF_METHOD,	'verify_units'),
		'size'				=> array(TYPE_NUM,			REQ_NO,		VF_METHOD),
		'weight'			=> array(TYPE_STR,			REQ_NO,		VF_METHOD,	'verify_fontweight'),
		'style'				=> array(TYPE_STR,			REQ_NO,		VF_METHOD,	'verify_fontstyle'),
		'variant'			=> array(TYPE_STR,			REQ_NO,		VF_METHOD,	'verify_fontvariant'),
	);


	/**
	 * Constructor - checks that the registry object has been passed correctly.
	 *
	 * @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	 * @param	integer		One of the ERRTYPE_x constants
	 */
	public function __construct(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::__construct($registry, $errtype);
		parent::set_datatype("Font");
	}
}

class vB_DataManager_StyleVarTextDecoration extends vB_DataManager_StyleVar
{
	var $childfields = array(
		'none'				=> array(TYPE_BOOL,			REQ_NO),
		'underline'			=> array(TYPE_BOOL,			REQ_NO),
		'overline'			=> array(TYPE_BOOL,			REQ_NO),
		'line-through'		=> array(TYPE_BOOL,			REQ_NO),
		'blink'				=> array(TYPE_BOOL,			REQ_NO),
	);

	/**
	 * Constructor - checks that the registry object has been passed correctly.
	 *
	 * @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	 * @param	integer		One of the ERRTYPE_x constants
	 */
	public function __construct(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::__construct($registry, $errtype);
		parent::set_datatype("TextDecoration");
	}
}

class vB_DataManager_StyleVarSize extends vB_DataManager_StyleVar
{
	var $childfields = array(
		'size'				=> array(TYPE_STR,			REQ_NO),
		'units'				=> array(TYPE_STR,			REQ_NO,		VF_METHOD,	'verify_units'),
	);

	/**
	 * Constructor - checks that the registry object has been passed correctly.
	 *
	 * @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	 * @param	integer		One of the ERRTYPE_x constants
	 */
	public function __construct(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::__construct($registry, $errtype);
		parent::set_datatype("size");
	}
}

class vB_DataManager_StyleVarImage extends vB_DataManager_StyleVar
{
	var $childfields = array(
		'image'				=> array(TYPE_STR,			REQ_NO,		VF_METHOD,	'verify_image'),
	);

	/**
	 * Constructor - checks that the registry object has been passed correctly.
	 *
	 * @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	 * @param	integer		One of the ERRTYPE_x constants
	 */
	public function __construct(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::__construct($registry, $errtype);
		parent::set_datatype("Image");
	}
}

class vB_DataManager_StyleVarDimension extends vB_DataManager_StyleVar
{
	var $childfields = array(
		'width'				=> array(TYPE_NUM,			REQ_NO),
		'height'			=> array(TYPE_NUM,			REQ_NO),
		'units'				=> array(TYPE_STR,			REQ_NO,		VF_METHOD,	'verify_units'),
	);


	/**
	 * Constructor - checks that the registry object has been passed correctly.
	 *
	 * @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	 * @param	integer		One of the ERRTYPE_x constants
	 */
	public function __construct(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::__construct($registry, $errtype);
		parent::set_datatype("Dimension");
	}
}

class vB_DataManager_StyleVarBorder extends vB_DataManager_StyleVar
{
	var $childfields = array(
		'width'			=> array(TYPE_NUM,			REQ_NO),
		'style'			=> array(TYPE_STR,			REQ_NO),
		'color'			=> array(TYPE_STR,			REQ_NO,		VF_METHOD),
		'units'			=> array(TYPE_STR,			REQ_NO,		VF_METHOD,	'verify_units'),
	);


	/**
	 * Constructor - checks that the registry object has been passed correctly.
	 *
	 * @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	 * @param	integer		One of the ERRTYPE_x constants
	 */
	public function __construct(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::__construct($registry, $errtype);
		parent::set_datatype("Dimension");
	}
}

class vB_DataManager_StyleVarPadding extends vB_DataManager_StyleVar
{
	var $childfields = array(
		'top'				=> array(TYPE_NUM,			REQ_NO),
		'right'				=> array(TYPE_NUM,			REQ_NO),
		'bottom'			=> array(TYPE_NUM,			REQ_NO),
		'left'				=> array(TYPE_NUM,			REQ_NO),
		'same'				=> array(TYPE_BOOL,			REQ_NO),
		'units'				=> array(TYPE_STR,			REQ_NO,		VF_METHOD,	'verify_units'),
	);


	/**
	 * Constructor - checks that the registry object has been passed correctly.
	 *
	 * @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	 * @param	integer		One of the ERRTYPE_x constants
	 */
	public function __construct(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::__construct($registry, $errtype);
		parent::set_datatype("Padding");
	}
}

class vB_DataManager_StyleVarMargin extends vB_DataManager_StyleVar
{
	var $childfields = array(
		'top'				=> array(TYPE_STR,			REQ_NO,		VF_METHOD,	'verify_margin'),
		'right'				=> array(TYPE_STR,			REQ_NO,		VF_METHOD,	'verify_margin'),
		'bottom'			=> array(TYPE_STR,			REQ_NO,		VF_METHOD,	'verify_margin'),
		'left'				=> array(TYPE_STR,			REQ_NO,		VF_METHOD,	'verify_margin'),
		'same'				=> array(TYPE_BOOL,			REQ_NO),
		'units'				=> array(TYPE_STR,			REQ_NO,		VF_METHOD,	'verify_units'),
	);

	/**
	 * Constructor - checks that the registry object has been passed correctly.
	 *
	 * @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	 * @param	integer		One of the ERRTYPE_x constants
	 */
	public function __construct(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::__construct($registry, $errtype);
		parent::set_datatype("Margin");
	}
}

class vB_DataManager_StyleVarFontlist extends vB_DataManager_StyleVar
{
	var $childfields = array(
		'fontlist'			=> array(TYPE_STR,			REQ_NO,		VF_METHOD,	'verify_fontlist'),
	);

	/**
	 * Constructor - checks that the registry object has been passed correctly.
	 *
	 * @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	 * @param	integer		One of the ERRTYPE_x constants
	 */
	public function __construct(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::__construct($registry, $errtype);
		parent::set_datatype("Fontlist");
	}
}

class vB_DataManager_StyleVarCustom extends vB_DataManager_StyleVar
{
	// Honestly, I don't know if we even need this; it is not being used right now...
	var $childfields = array();

	/**
	 * Adds a child field to the custom stylevar
	 *
	 * @param	string		The key used for storage
	 * @param	array		The descriptor data; IE: array(TYPE_INT, REQ_NO, VF_METHOD)
	 */
	public function add_child($key, $descriptor)
	{
		$this->childfields[$key] = $descriptor;
	}

	/**
	 * Constructor - checks that the registry object has been passed correctly.
	 *
	 * @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	 * @param	integer		One of the ERRTYPE_x constants
	 */
	public function __construct(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::__construct($registry, $errtype);
		parent::set_datatype("Custom");
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 34206 $
|| ####################################################################
\*======================================================================*/
