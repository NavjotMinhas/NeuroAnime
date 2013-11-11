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

/**
* Abstracted class that handles User CSS
*
* @package	vBulletin
* @version	$Revision: 32878 $
* @date		$Date: 2009-10-28 11:38:49 -0700 (Wed, 28 Oct 2009) $
*/
class vB_UserCSS
{
 	/**
 	* A list of valid properties along with verification info, permission checks, and
 	* mapping to actual CSS property names.
 	*
 	* @var	array
 	*/
 	var $properties = array(
 		'font_family' => array(
 			'callback'    => 'verify_font_family',
 			'cssproperty' => 'font-family',
 			'permission'  => 'caneditfontfamily'
 		),
 		'font_size' => array(
 			'callback'    => 'verify_size',
 			'cssproperty' => 'font-size',
 			'permission'  => 'caneditfontsize'
 		),

 		'color' => array(
 			'callback'    => 'verify_color',
 			'cssproperty' => 'color',
 			'permission'  => 'caneditcolors'
 		),
 		'linkcolor' => array(
 			'callback'    => 'verify_color',
 			'cssproperty' => 'color',
 			'permission'  => 'caneditcolors'
 		),
 		'shadecolor' => array(
 			'callback'    => 'verify_color',
 			'cssproperty' => 'color',
 			'permission'  => 'caneditcolors'
 		),

 		'background_color' => array(
 			'callback'    => 'verify_color',
 			'cssproperty' => 'background-color',
 			'permission'  => 'caneditcolors'
 		),
 		'background_image' => array(
 			'callback'    => 'verify_image',
 			'cssproperty' => 'background-image',
 			'permission'  => 'caneditbgimage'
 		),

		'background_repeat' => array(
			'callback'    => 'verify_repeat',
			'cssproperty' => 'background-repeat',
			'permission'  => 'caneditbgimage'
		),
 		'border_style' => array(
 			'callback'    => 'verify_border_style',
 			'cssproperty' => 'border-style',
 			'permission'  => 'caneditborders'
 		),
 		'border_width' => array(
 			'callback'    => 'verify_size',
 			'cssproperty' => 'border-width',
 			'permission'  => 'caneditborders'
 		),
 		'border_color' => array(
 			'callback'    => 'verify_color',
 			'cssproperty' => 'border-color',
 			'permission'  => 'caneditborders'
 		),
 		'padding' => array(
 			'callback'    => 'verify_box_outline_size',
 			'cssproperty' => 'padding',
 			'permission'  => 'caneditborders'
 		)
 	);

	/**
	* An array of information about the various CSS selectors/properties. See build_css_array().
	*
	* @var array
	*/
	var $cssedit = array();

	/**
	* An Array of the “store” of current data
	*
	* @var	array
	*/
	var $store = array();

	/**
	*  The data currently in the database
	*
	* @var	array
	*/
	var $existing = array();

	/**
	* An Array of tuples to be deleted from the database
	*
	* @var	array
	*/
	var $delete = array();

	/**
	* An integer referencing the User ID of the User for Whom the CSS changes are being made.
	*
	* @var	int
	*/
	var $userid = 0;

	/**
	* Array of permissions for the current user. Should be the format returned by cache_permissions()
	*
	* @var	array
	*/
	var $permissions = array();

	/**
	* Any errors that were encountered during the upload or verification process
	*
	* @var	array
	*/
	var $error = array();

	/**
	* Selector/property combinations that did not have valid values
	*
	* @var	array
	*/
	var $invalid = array();

	/**
	* Main registry object
	*
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* The vBulletin database object
	*
	* @var	vB_Database
	*/
	var $dbobject = null;


	/**
	* Constructor. Sets up the data for the specified user.
	*
	* @param	vB_Registry
	* @param	integer		User to process
	* @param	boolean		Whether to fetch their existing CSS data
	*/
	function vB_UserCSS(&$registry, $userid, $fetch_data = true)
	{
		$this->registry =& $registry;

		if (is_object($registry->db))
		{
			$this->dbobject =& $registry->db;
		}

		$this->set_userid($userid, $fetch_data);

		$this->cssedit = $this->build_css_array();

		($hook = vBulletinHook::fetch_hook('usercss_create')) ? eval($hook) : false;
	}

	/**
	* Sets the user we're working with. Automatically sets permissions as well.
	*
	* @param	integer	User to process
	* @param	boolean	Whether to fetch existing CSS data
	*
	* @return	boolean	True on success
	*/
	function set_userid($userid, $fetch = true)
	{
		$userid = intval($userid);

		if ($userid == $this->registry->userinfo['userid'])
		{
			$this->userid = $userid;
			$this->permissions = $this->registry->userinfo['permissions'];
		}
		else if ($user = $this->dbobject->query_first("SELECT * FROM " . TABLE_PREFIX . "user WHERE userid = $userid"))
		{
			$this->userid = $userid;
			$this->permissions = cache_permissions($user, false);
		}
		else
		{
			global $vbphrase;
			$this->error[] = fetch_error('invalidid', $vbphrase['user'], $this->registry->options['contactuslink']);
			return false;
		}

		if ($fetch)
		{
			$this->existing = $this->fetch_existing();
		}

		return true;
	}

	/**
	* Explicitly set permissions. Useful if you want to override the permissions
	* for the user being processed.
	*
	* @param	array
	*/
	function set_permissions($permissions)
	{
		$this->permissions = $permissions;
	}

	/**
	* Fetches the existing data for the selected user
	*
	* @return	array	Array of [selector][property] = value
	*/
	function fetch_existing()
	{
		$usercss_result = $this->dbobject->query_read("
			SELECT * FROM " . TABLE_PREFIX . "usercss
			WHERE userid = " . $this->userid . "
			ORDER BY selector
		");

		$existing = array();
		while ($usercss = $this->dbobject->fetch_array($usercss_result))
		{
			$existing["$usercss[selector]"]["$usercss[property]"] = $usercss['value'];
		}

		$this->dbobject->free_result($usercss_result);

		return $existing;
	}

	/**
	* Parses a particular property and sets it for storage/deletion if successful.
	*
	* @param	string	Selector
	* @param	string	Property
	* @param	string	Value
	*
	* @return	boolean
	*/
	function parse($selector, $property, $value = '')
	{
		$propertyinfo = $this->properties["$property"];

		if (empty($propertyinfo) OR $value == '')
		{
			$this->delete["$selector"][] = $property;
			return true;
		}
		else
		{
			$valid = false;

			if (empty($propertyinfo['callback']))
			{
				$valid = true;
			}
			else
			{
				$callback = $propertyinfo['callback'];
				if ($this->$callback($value))
				{
					$valid = true;
				}
			}

			if ($valid)
			{
				if ($this->existing["$selector"]["$property"] != $value)
				{
					$this->store["$selector"]["$property"] = $value;
				}
			}
			else
			{
				$this->invalid["$selector"]["$property"] = ' usercsserror ';
			}

			($hook = vBulletinHook::fetch_hook('usercss_parse')) ? eval($hook) : false;

			return $valid;
		}
	}

	/**
	* Saves the updated properties to the database.
	*/
	function save()
	{
		// First, we want to remove any properties they don't have access to.
		// This is in case they lost some permissions;
		// leaving them around leads to unexpected behavior.
		$prop_del = array();
		foreach ($this->properties AS $property => $propertyinfo)
		{
			if (!($this->permissions['usercsspermissions'] & $this->registry->bf_ugp_usercsspermissions["$propertyinfo[permission]"]))
			{
				$prop_del[] = $this->dbobject->escape_string($property);
			}
		}

		if ($prop_del)
		{
			$this->dbobject->query_write("
				DELETE FROM " . TABLE_PREFIX . "usercss
				WHERE userid = " . $this->userid . "
					AND property IN ('" . implode("','", $prop_del) . "')
			");
		}

		// now go for any entries that we emptied -- these are being removed
		if (!empty($this->delete))
		{
			foreach ($this->delete as $selector => $properties)
			{
				foreach ($properties as $property)
				{
					if (!empty($this->existing["$selector"]["$property"]))
					{
						$this->dbobject->query_write("
							DELETE FROM " . TABLE_PREFIX . "usercss
							WHERE userid = " . $this->userid . "
								AND selector = '" . $this->dbobject->escape_string($selector) . "'
								AND property = '" . $this->dbobject->escape_string($property) . "'
						");
					}

					unset($this->existing["$selector"]["$property"]);
				}

			}
		}

		// and for new/changed ones...
		if (!empty($this->store))
		{
			$value = array();

			foreach ($this->store as $selector => $properties)
			{
				foreach ($properties as $property => $value)
				{
					$values[] = "
						(" . $this->userid . ",
						'" . $this->dbobject->escape_string($selector) . "',
						'" . $this->dbobject->escape_string($property) . "',
						'" . $this->dbobject->escape_string($value) . "')
					";

					$this->dbobject->query_write("
						DELETE FROM " . TABLE_PREFIX . "usercss
						WHERE userid = " . $this->userid . "
							AND selector = '" . $this->dbobject->escape_string($selector) . "'
							AND property = '" . $this->dbobject->escape_string($property) . "'
					");
				}
			}

			if ($values)
			{
				$this->dbobject->query_write("
					INSERT INTO " . TABLE_PREFIX . "usercss
						(userid, selector, property, value)
					VALUES
						" . implode(", ", $values)
				);
			}

		}

		$this->update_css_cache();
	}

	/**
	* Fetches the array of CSS data that would be used if save were called.
	* Good for previews.
	*
	* @return	array	Array of CSS data [selector][property] = value
	*/
	function fetch_effective()
	{
		$usercss_cache = $this->fetch_existing();

		if ($this->delete)
		{
			foreach ($this->delete AS $selector => $properties)
			{
				foreach ($properties AS $property)
				{
					unset($usercss_cache["$selector"]["$property"]);
				}
			}
		}

		if ($this->store)
		{
			foreach ($this->store AS $selector => $properties)
			{
				foreach ($properties AS $property => $value)
				{
					$usercss_cache["$selector"]["$property"] = $value;
				}
			}
		}

		return $usercss_cache;
	}


	/**
	* Updates this user's CSS cache.
	*
	* @return	string	Compiled CSS
	*/
	function update_css_cache()
	{
		$buildcss = $this->build_css();

		$this->dbobject->query_write("
			REPLACE INTO " . TABLE_PREFIX . "usercsscache
				(userid, cachedcss, buildpermissions)
			VALUES
				(" . $this->userid . ", '" . $this->dbobject->escape_string($buildcss) . "', " . intval($this->permissions['usercsspermissions']) . ")
		");

		return $buildcss;
	}

	/**
	* Builds an individual CSS property into an array
	*
	* @param	array	(By Ref) Array to write into
	* @param	string	Selector group ID. Will be resolved to actual selectors.
	* @param	string	Property name (to be resolved to a CSS property)
	* @param	string	Value to set that property to
	*/
	function build_css_property(&$css, $selectorgroup, $property, $value)
	{
		$selectorgroupinfo = $this->cssedit["$selectorgroup"];

		if (!in_array($property, $selectorgroupinfo['properties']) OR $value == ''  OR !$this->check_css_permission($selectorgroup, $property, $this->permissions))
		{
			return;
		}

		if (isset($selectorgroupinfo['selectors']))
		{
			$selectors = array();
			foreach ($selectorgroupinfo['selectors'] AS $realselector)
			{
				$selectors[] = ($realselector != '#usercss' ? '#usercss ' : '') . $realselector;
			}
		}
		else
		{
			$selectors = array(
				($selectorgroup != '#usercss' ? '#usercss ' : '') . $selectorgroup
			);
		}

		if (!$selectors)
		{
			return;
		}

		$selector_list = implode(', ', $selectors);

		$propertyinfo = $this->properties["$property"];

		if ($property == 'background_image')
		{
			preg_match("/^([0-9]+),([0-9]+)$/", $value, $picture);
			$value = "url(attachment.php?/*sessionurl*/attachmentid=$picture[2])";
		}

		$css["$selector_list"]["$propertyinfo[cssproperty]"] = $value;

		// if we are dealing with the inputs group and we are NOT handling borders, apply the same styles to <option> and <optgroup>
		if ($selectorgroup == 'inputs' AND strpos($propertyinfo['cssproperty'], 'border') === false)
		{
			$css['#usercss option, #usercss optgroup']["$propertyinfo[cssproperty]"] = $value;
		}

		if ($property == 'background_color' AND !isset($css["$selector_list"]['background-image']))
		{
			// a color is set, but no image, so we need to blank out the image
			$css["$selector_list"]['background-image'] = 'none';
		}

		if ($propertyinfo['cssproperty'] == 'color' AND $selectorgroupinfo['overridelink'])
		{
			$this->build_css_property($css, $selectorgroupinfo['overridelink'], 'linkcolor', $value);
		}

		($hook = vBulletinHook::fetch_hook('usercss_build_property')) ? eval($hook) : false;
	}

	/**
	* Builds the compiled CSS.
	*
	* @param	array	Optional array of data to build the CSS from; defaults to the existing values
	*
	* @return	string	Compiled CSS
	*/
	function build_css($usercss_cache = null)
	{
		if (!is_array($usercss_cache))
		{
			$usercss_cache = $this->fetch_existing();
		}

		$css = array();

		foreach (array_keys($this->cssedit) AS $selector)
		{
			if (!isset($usercss_cache["$selector"]))
			{
				continue;
			}

			foreach ($usercss_cache["$selector"] AS $property => $value)
			{
				$this->build_css_property($css, $selector, $property, $value);
			}
		}

		$csstext = '';
		foreach ($css AS $selector_list => $properties)
		{
			$csstext .= "$selector_list {\n";
			foreach ($properties AS $property => $propvalue)
			{
				$csstext .= "\t$property: $propvalue;\n";
			}
			$csstext .= "}\n\n";
		}

		($hook = vBulletinHook::fetch_hook('usercss_build_css')) ? eval($hook) : false;

		return trim($csstext);
	}

	/**
	* Checks permissions for the specified selector/property.
	*
	* @param	string	Selector
	* @param	string	Property
	* @param	array	Array of permissions to check against
	*
	* @return	boolean
	*/
	function check_css_permission($selector, $property, $permissions)
	{
		$permfield = $this->properties["$property"]['permission'];

		if ($permfield == 'caneditbgimage')
		{
			return ($this->registry->options['socnet'] & $this->registry->bf_misc_socnet['enable_albums'] AND $this->permissions['usercsspermissions'] & $this->registry->bf_ugp_usercsspermissions["$permfield"]) ? true : false;
		}

		return ($this->permissions['usercsspermissions'] & $this->registry->bf_ugp_usercsspermissions["$permfield"]) ? true : false;
	}

	/**
	* Build an array of information about how to display the user CSS editor page
	*
	* @return	array
	*/
	function build_display_array()
	{
		$display = array(
			'main' => array(
				'phrasename' => 'usercss_main',
				'properties' => array(
					'background_color',
	 				'background_image',
	 				'background_repeat',
					'border_style',
					'border_width',
					'border_color',
					'color' => 'text',
					'padding',
					'linkcolor' => 'main_a',
					'font_family' => 'text',
					'shadecolor' => 'shadetext'
				)
			),
			'tableborder' => array(
				'phrasename' => 'usercss_tableborder',
				'properties' => null // inherit from build_css_array's value
			),
			'tabletitle' => array(
				'phrasename' => 'usercss_tabletitle',
				'properties' => null // inherit from build_css_array's value
			),
			'tableheader' => array(
				'phrasename' => 'usercss_tableheader',
				'properties' => null // inherit from build_css_array's value
			),
			'tablefooter' => array(
				'phrasename' => 'usercss_tablefooter',
				'properties' => null // inherit from build_css_array's value
			),
			'alternating1' => array(
				'phrasename' => 'usercss_alternating1',
				'properties' => array(
					'background_color',
	 				'background_image',
	 				'background_repeat',
					'color',
					'linkcolor' => 'alternating1_a'
				)
			),
			'alternating2' => array(
				'phrasename' => 'usercss_alternating2',
				'properties' => array(
					'background_color',
	 				'background_image',
	 				'background_repeat',
					'color',
					'linkcolor' => 'alternating2_a'
				)
			),
			'inputs' => array(
				'phrasename' => 'usercss_inputs',
				'properties' => null // inherit from build_css_array's value
			),
		);

		($hook = vBulletinHook::fetch_hook('usercss_build_display_array')) ? eval($hook) : false;

		return $display;
	}

	/**
	* Returns an array of information about selectors and properties
	*
	* @return	array
	*/
	function build_css_array()
	{
		$css = array(
			'main' => array(
				'selectors'	=> array(
					''
				),
				'properties' => array(
					'background_color',
	 				'background_image',
	 				'background_repeat',
					'border_style',
					'border_width',
					'border_color',
					'padding'
				)
			),
			'main_a' => array(
				'selectors' => array(
					'a'
				),
				'properties' => array(
					'linkcolor'
				)
			),

			'text' => array(
				'selectors'	=> array(
					'.block',
					'.blockhead',
					'h2 .blockhead',
					'.blocksubhead',
					'.blockbody',
					'.blockrow',
					'.blockfoot',
					'.alt1',
					'.alt2',
					'.popupmenu',
					'legend',
					'td',
					'th',
					'p',
					'li'
				),
				'properties' => array(
					'font_family',
					'color'
				)
			),

			'shadetext' => array(
				'selectors'	=> array(
					'.shade',
					'.time',
					'legend'
				),
				'properties' => array(
					'shadecolor'
				),
				'overridelink' => 'shadetext_a'
			),
			'shadetext_a' => array(
				'selectors'	=> array(
					'.shade a',
					'.time a'
				),
				'properties' => array(
					'linkcolor'
				),
				'noinputset' => true
			),

			'tableborder' => array(
				'selectors'	=> array(
					'.block',
					'.popupmenu',
				),
				'properties' => array(
					'border_style',
					'border_width',
					'border_color',
					'padding',
					'background_color',
	 				'background_image',
	 				'background_repeat',
				)
			),

			'tabletitle' => array(
				'selectors'	=> array(
					'.blockhead'
				),
				'properties' => array(
					'background_color',
	 				'background_image',
	 				'background_repeat',
					'color'
				),
				'overridelink' => 'tabletitle_a'
			),
			'tabletitle_a' => array(
				'selectors'	=> array(
					'.blocksubhead a'
				),
				'properties' => array(
					'linkcolor'
				),
				'noinputset' => true
			),

			'tableheader' => array(
				'selectors'	=> array(
					'.blocksubhead',
				),
				'properties' => array(
					'background_color',
	 				'background_image',
	 				'background_repeat',
					'color'
				),
				'overridelink' => 'tableheader_a'
			),
			'tableheader_a' => array(
				'selectors'	=> array(
					'.blocksubhead a',
				),
				'properties' => array(
					'linkcolor'
				),
				'noinputset' => true
			),

			'tablefooter' => array(
				'selectors'	=> array(
					'.blockfoot',
				),
				'properties' => array(
					'background_color',
	 				'background_image',
	 				'background_repeat',
					'color'
				),
				'overridelink' => 'tablefooter_a'
			),
			'tablefooter_a' => array(
				'selectors'	=> array(
					'.blockfoot a',
				),
				'properties' => array(
					'linkcolor'
				),
				'noinputset' => true
			),

			'alternating1' => array(
				'selectors'	=> array(
					'.blockrow'
				),
				'properties' => array(
					'background_color',
	 				'background_image',
	 				'background_repeat',
					'color',
				)
			),
			'alternating1_a' => array(
				'selectors'	=> array(
					'a'
				),
				'properties' => array(
					'linkcolor'
				)
			),

			'alternating2' => array(
				'selectors'	=> array(
					'.alt2'
				),
				'properties' => array(
					'background_color',
	 				'background_image',
	 				'background_repeat',
					'color',
				)
			),
			'alternating2_a' => array(
				'selectors'	=> array(
					'.alt2 a'
				),
				'properties' => array(
					'linkcolor'
				)
			),

			'inputs' => array(
				'selectors'	=> array(
					'.formcontrols input',
					'.formcontrols select',
					'.formcontrols textarea',
					'formcontrols .button'
					// also styles option and optgroup, but we use a hack to prevent it from applying border styles - see $this->build_css_property()
				),
				'properties' => array(
					'font_family',
					'font_size',
					'color',
					'background_color',
	 				'background_image',
	 				'background_repeat',
					'border_style',
					'border_width',
					'border_color'
				)
			),
		);

		($hook = vBulletinHook::fetch_hook('usercss_build_css_array')) ? eval($hook) : false;

		return $css;
	}

	/**
	* Verifies a padding/margin/border-width (shortcut) property
	*
	* @param	string	Proposed CSS property. May be modified
	*
	* @return	boolean	True if value
	*/
	function verify_box_outline_size($value)
	{
		return preg_match('/^((^|\s+)\d+(\.\d+)?(%|in|cm|mm|em|ex|pt|pc|px)){1,4}$/siU', $value);
	}

	/**
	* Verifies the a border-style property.
	*
	* @param	string	Value to verfiy. May be modified.
	*
	* @return	boolean	True if value.
	*/
	function verify_border_style($value)
	{
		return preg_match('/^(none|hidden|dotted|dashed|solid|double|groove|ridge|inset|outset)$/', $value);
	}

	/**
	* Verifies the a font-family property.
	*
	* @param	string	Value to verfiy. May be modified.
	*
	* @return	boolean	True if value.
	*/
	function verify_font_family(&$value)
	{
		return !empty($value);
	}

	/**
	* Verifies the a size property.
	*
	* @param	string	Value to verfiy. May be modified.
	*
	* @return	boolean	True if value.
	*/
	function verify_size($value)
	{
		return preg_match('/^(((([0-9])+\.)?[0-9]+)(%|in|cm|mm|em|ex|pt|pc|px)|xx-small|x-small|smaller|small|medium|large|larger|x-large|xx-large)$/', $value);
	}

	/**
	* Verifies the a color property.
	*
	* @param	string	Value to verfiy. May be modified.
	*
	* @return	boolean	True if value.
	*/
	function verify_color(&$value)
	{
		if (preg_match('/^(#([a-f0-9]{3}){1,2}|\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3})$/siU', $value))
		{
			return true;
		}
		else if (preg_match('/^[a-f0-9]{3}([a-f0-9]{3})?$/siU', $value))
		{
			$value = "#$value";
			return true;
		}
		else
		{
			return preg_match('/^[a-z0-9]+$/siU', $value);
		}
	}

	/**
	* Verifies the an image property. Must come from this user's album and the album must be public/profile.
	*
	* @param	string	Value to verfiy. May be modified.
	*
	* @return	boolean	True if value.
	*/
	function verify_image(&$value)
	{

		if (!($this->registry->options['socnet'] & $this->registry->bf_misc_socnet['enable_albums']))
		{
			$value = '';
			return true;
		}

		$foundalbum = preg_match('#albumid=([0-9]+)#', $value, $albumid);
		$foundpicture = preg_match('#attachmentid=([0-9]+)#', $value, $attachmentid);

		require_once(DIR . '/includes/class_bootstrap_framework.php');
		require_once(DIR . '/vb/types.php');
		vB_Bootstrap_Framework::init();
		$types = vB_Types::instance();
		$contenttypeid = intval($types->getContentTypeID('vBForum_Album'));

		if ($foundalbum AND $foundpicture AND $picture = $this->dbobject->query_first("
			SELECT album.userid
			FROM " . TABLE_PREFIX . "attachment AS a
			INNER JOIN " . TABLE_PREFIX . "album AS album ON (a.contentid = album.albumid)
			WHERE
				a.attachmentid = " . intval($attachmentid[1]) . "
	 				AND
	 			a.contenttypeid = $contenttypeid
	 				AND
	 			album.state IN ('profile', 'public')
	 				AND
	 			album.userid = " . $this->userid . "
	 				AND
	 			album.albumid = " . intval($albumid[1]) . "
	 	"))
	 	{
	 		$value = $albumid[1] . "," . $attachmentid[1];
	 		return true;
	 	}
	 	else
	 	{
	 		return false;
	 	}
	}

	/**
	* Verifies the a background-repeat property.
	*
	* @param	string	Value to verfiy. May be modified.
	*
	* @return	boolean	True if value.
	*/
	function verify_repeat(&$value)
	{
		return preg_match("/^(repeat|no-repeat|repeat-x|repeat-y)$/", $value);
	}

	/**
	* Builds the array for various admin-controlled select options (font sizes, etc).
	* Determines the CSS value and internal phrase key if there is one.
	*
	* @param	string	Raw string. Line break and pipe delimited.
	*
	* @return	array	Array prepared for select building
	*/
	function build_select_option($input_string)
	{
		$lines = preg_split("/(\n|\r\n|\r)/", $input_string, -1, PREG_SPLIT_NO_EMPTY);

		$output = array();
		foreach ($lines AS $line)
		{
			$parts = explode('|', $line);
			$key = trim($parts[0]);
			$value = isset($parts[1]) ? trim($parts[1]) : $key;
			$output["$key"] = $value;
		}

		return $output;
	}

	/**
	* Builds an array for select boxes in the admin CP. This includes the necessary phrasing.
	*
	* @param	string	Raw string. Line break and pipe delimited.
	* @param	string	Prefix for the phrases
	*
	* @return	array	Array prepared for select building
	*/
	function build_admin_select_option($input_string, $phrase_prefix)
	{
		global $vbphrase;

		$options = $this->build_select_option($input_string);

		$output = array('' => '');
		foreach ($options AS $key => $value)
		{
			$output["$value"] = !empty($vbphrase["$phrase_prefix$key"]) ? $vbphrase["$phrase_prefix$key"] : $key;
		}

		return $output;
	}
}


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>
