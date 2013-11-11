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
* Class to build array from permissions within XML file
*
* @package	vBulletin
* @version	$Revision: 40651 $
* @date		$Date: 2010-11-16 16:23:46 -0800 (Tue, 16 Nov 2010) $
*/
class vB_Bitfield_Builder
{
	/**
	* Array to hold all the compiled data after the bitfield merging
	*
	* @var    array
	*/
	var $data = array();

	/**
	* Array to hold a datastore compatible object
	*
	* @var   array
	*/
	var $datastore = array();

	/**
	* Array to hold any error messages during merging of bitfields
	*
	* @var    array
	*/
	var $errors = array();

	/**
	* Expected number of groups in the datastore entry
	*
	* @var    array
	*/
	var $datastore_total = array();

	/**
	* Singleton Init
	*
	* Loads an instance of the object
	*
	* @return	object
	*/
	function &init()
	{
		static $instance;
		if (!$instance)
		{
			require_once(DIR . '/includes/class_xml.php');
			$instance = new vB_Bitfield_Builder();
		}
		return $instance;
	}

	/**
	* Returns the errors that hapepned during merging
	*
	* @return	array
	*/
	function fetch_errors()
	{
		$obj =& vB_Bitfield_Builder::init();
		return $obj->errors;
	}

	/**
	* Search for bitfield xml files, merge together and search for collisions
	*
	* @param	boolean	layout	Moves intperm entries into ['misc']['intperm']
	* @param	boolean	Process disabled products?
	*
	* @return	boolean
	*/
	function build($layout = true, $include_disabled = false)
	{
		$obj =& vB_Bitfield_Builder::init();
		$obj->data = array();
		$obj->datastore = array();
		$obj->datastore_total = array();

		$temp = array();

		if ($handle = @opendir(DIR . '/includes/xml/'))
		{
			while (($file = readdir($handle)) !== false)
			{
				if (!preg_match('#^bitfield_(.*).xml$#i', $file, $matches))
				{
					continue;
				}

				$data = $obj->fetch(DIR . '/includes/xml/' . $file, $layout, $include_disabled);
				if ($data !== false)
				{ // no error parsing at least
					$temp["$matches[1]"] = $data;
				}
			}
			closedir($handle);
		}

		// opendir failed or bitfield_vbulletin.xml is missing or it has a parse error
		if (empty($temp['vbulletin']))
		{
			if (is_readable(DIR . '/includes/xml/bitfield_vbulletin.xml'))
			{
				if ($data = $obj->fetch(DIR . '/includes/xml/bitfield_vbulletin.xml', $layout, $include_disabled))
				{
					$temp['vbulletin'] = $data;
				}
				else
				{
					trigger_error('Could not parse ' . DIR . '/includes/xml/bitfield_vbulletin.xml', E_USER_ERROR);
				}
			}
			else
			{
				trigger_error('Could not open ' . DIR . '/includes/xml/bitfield_vbulletin.xml', E_USER_ERROR);
			}
		}

		// products
		foreach($temp AS $product => $bitfields)
		{ // main group (usergroup, misc, etc)
			foreach ($bitfields AS $title => $permgroup)
			{ // subgroups such as forumpermissions
				foreach ($permgroup AS $subtitle => $permissions)
				{
					if (is_array($permissions))
					{
						foreach ($permissions AS $permtitle => $permvalue)
						{
							if (((is_array($permvalue) AND isset($permvalue['intperms'])) OR $permtitle == 'intperms') AND $layout)
							{
								if ($permtitle == 'intperms')
								{
									$obj->data['misc']["intperms"]["$subtitle"] = $permvalue;
								}
								else
								{
									$obj->data['misc']["intperms"]["$permtitle"] = $permvalue['intperms'];
								}
								continue;
							}
							else if (!$layout AND $title == 'layout')
							{
								$obj->data['layout']["$subtitle"]["$permtitle"] = $permvalue;
								continue;
							}
							else if (is_array($permvalue) AND !$layout)
							{
								if (empty($obj->datastore_total["$title"]["$subtitle"]) AND !isset($permvalue['intperm']))
								{
									$obj->datastore_total["$title"]["$subtitle"] = true;
								}
								$obj->data["$title"]["$subtitle"]["$permtitle"] = $permvalue;
								continue;
							}
							$obj->data["$title"]["$subtitle"]["$permtitle"] = $permvalue;
						}
						// check that all entries in subtitle have unique bitfield
						if ($title != 'layout' AND $layout AND is_array($obj->data["$title"]["$subtitle"]) AND sizeof($obj->data["$title"]["$subtitle"]) != sizeof(array_unique($obj->data["$title"]["$subtitle"])))
						{
							$uarray = array_unique($obj->data["$title"]["$subtitle"]);
							$collision = array_diff(array_keys($obj->data["$title"]["$subtitle"]), array_keys($uarray));
							foreach ($collision AS $key)
							{
								if (!$layout AND is_array($obj->data["$title"]["$subtitle"]["$key"]) AND isset($obj->data["$title"]["$subtitle"]["$key"]['intperms']))
								{
									continue;
								}
								$bitfield_collision_value = $obj->data["$title"]["$subtitle"]["$key"];
								$obj->errors[] = "Bitfield Collision: $key = " . array_search($bitfield_collision_value, $uarray);
							}
							if (!empty($obj->errors))
							{
								$obj->data = array();
								return false;
							}
						}
					}
					else
					{
						if (is_array($obj->data["$title"]))
						{
							foreach ($obj->data["$title"] AS $checktitle => $value)
							{
								if (is_array($value))
								{
									continue;
								}
								if ($value == $permissions)
								{
									$obj->errors[] = "Bitfield Collision: $checktitle = $subtitle";
									$obj->data = array();
									return false;
								}
							}
						}
						$obj->data["$title"]["$subtitle"] = $permissions;
					}
				}
			}
		}
		return true;
	}

	/**
	* Builds XML file into format for datastore
	*
	* @return	boolean	True on success, false on failure
	*/
	function build_datastore()
	{
		$obj =& vB_Bitfield_Builder::init();

		if (!empty($obj->datastore))
		{
			return true;
		}
		else if (vB_Bitfield_Builder::build(false) === false)
		{
			return false;
		}

		foreach($obj->data AS $maingroup => $subgroup)
		{
			foreach($subgroup AS $grouptitle => $perms)
			{
				foreach($perms AS $permtitle => $permvalue)
				{
					switch($maingroup)
					{
						case 'ugp':
							if (isset($permvalue['intperm']))
							{
								$obj->datastore['misc']['intperms']["$permtitle"] = $permvalue['value'];
							}
							else
							{
								$obj->datastore['ugp']["$grouptitle"]["$permtitle"] = $permvalue['value'];
							}
							break;
						case 'misc':
							$obj->datastore['misc']["$grouptitle"]["$permtitle"] = $permvalue['value'];
							break;
					}
				}
			}
		}

		return true;
	}

	/**
	* Saves Data into database
	*
	* @return	boolean
	*/
	function save($dbobject)
	{
		global $vbulletin;

		$obj =& vB_Bitfield_Builder::init();

		if (vB_Bitfield_Builder::build_datastore() === false)
		{
			return false;
		}

		// Update registry
		foreach (array_keys($obj->datastore) AS $group)
		{
			$vbulletin->{'bf_' . $group} =& $obj->datastore["$group"];
			foreach (array_keys($obj->datastore["$group"]) AS $subgroup)
			{
				$vbulletin->{'bf_' . $group . '_' . $subgroup} =& $obj->datastore["$group"]["$subgroup"];
			}
		}

		// save
		build_datastore('bitfields', serialize($obj->datastore), 1);

		return true;
	}

	/**
	* Returns array of the XML data parsed into array format
	*
	* @param	string	file	Filename
	* @param	boolean	Process disabled products?
	*
	* @return	array
	*/
	function fetch($file, $layout, $include_disabled = false)
	{
		global $vbulletin;

		$obj =& vB_Bitfield_Builder::init();
		$xmlobj = new vB_XML_Parser(false, $file);

		if (!($xml = $xmlobj->parse()))
		{	// xml parser failed
			return false;
		}

		if (!$include_disabled AND $xml['product'] AND $xml['product'] != 'vbulletin' AND empty($vbulletin->products["$xml[product]"]))
		{	// This product is disabled
			return false;
		}

		$tempdata = array();
		if (!$layout)
		{
			$xmlignore = $xml['ignoregroups'];
		}

		$xml = $xml['bitfielddefs'];
		if (!isset($xml['group'][0]))
		{
			$xml['group'] = array($xml['group']);
		}

		foreach ($xml['group'] AS $bitgroup)
		{
			if (!isset($tempdata["$bitgroup[name]"]))
			{ // this file as a group with the same name so don't intialise it
				$tempdata["$bitgroup[name]"] = array();
			}

			// deal with actual bitfields
			if (!isset($bitgroup['group']))
			{
				$tempdata["$bitgroup[name]"] = $obj->bitfield_array_convert($bitgroup['bitfield'], $layout);
			}
			else
			{
				$subdata = array();
				if (!isset($bitgroup['group'][0]))
				{
					$bitgroup['group'] = array($bitgroup['group']);
				}
				foreach ($bitgroup['group'] AS $subgroup)
				{
					$subdata["$subgroup[name]"] = $obj->bitfield_array_convert($subgroup['bitfield'], $layout);
				}
				$tempdata["$bitgroup[name]"] = $subdata;
			}
		}

		if (!$layout AND !empty($xmlignore['group']))
		{
			if (!isset($xmlignore['group'][0]))
			{
				$xmlignore['group'] = array($xmlignore['group']);
			}
			foreach ($xmlignore['group'] AS $title => $moo)
			{
				if (!empty($moo['ignoregroups']))
				{
					$moo['layoutperm']['ignoregroups'] = $moo['ignoregroups'];
				}
				$tempdata['layout']["$moo[name]"] = $moo['layoutperm'];
			}
		}
		$xmlobj = null;
		return $tempdata;
	}

	/**
	* Changes XML parsed data array into bitfield data array
	*
	* @param	array	bitfieldArray	The XML parsed data array
	*
	* @return	array
	*/
	function bitfield_array_convert($array, $layout)
	{
		$tempdata = array();
		if (!isset($array[0]))
		{
			$array = array($array);
		}
		foreach ($array AS $bit)
		{
			if (!$layout)
			{
				if (!empty($bit['phrase']))
				{
					$tempdata["$bit[name]"]['phrase'] = $bit['phrase'];
				}
				if (!empty($bit['group']))
				{
					$tempdata["$bit[name]"]['group'] = $bit['group'];
				}
				if (!empty($bit['readonly']))
				{
					$tempdata["$bit[name]"]['readonly'] = $bit['readonly'];
				}
				if (!empty($bit['options']))
				{
					$tempdata["$bit[name]"]['options'] = $bit['options'];
				}
				if ($bit['intperm'])
				{
					$tempdata["$bit[name]"]['intperm'] = $bit['intperm'];
				}
				if ($bit['install'])
				{
					$tempdata["$bit[name]"]['install'] = explode(',', $bit['install']);
				}
				if ($bit['default'])
				{
					$tempdata["$bit[name]"]['default'] = true;
				}
			}
			if (!$layout)
			{
				$tempdata["$bit[name]"]['value'] = intval($bit['value']);
			}
			else if ($bit['intperm'])
			{
				$tempdata["$bit[name]"]['intperms'] = intval($bit['value']);
			}
			else
			{
				$tempdata["$bit[name]"] = $bit['value'];
			}
		}
		return $tempdata;
	}

	/**
	* Fetches an array from the specified permission group (within the ugp tag).
	* Note that if you specify an invalid name or the group can't be built
	* errors will be printed. Intperms and non-sub-grouped fields will be ignored.
	*
	* @param	string	Name of group to fetch.
	*
	* @return	array	[subgroup][permtitle] => array(phrase => str, value => int)
	*/
	function fetch_permission_group($permgroup)
	{
		$output = array();
		$obj =& vB_Bitfield_Builder::init();

		if (vB_Bitfield_Builder::build(false) === false)
		{
			echo "<strong>error</strong>\n";
			print_r(vB_Bitfield_Builder::fetch_errors());
			return $output;
		}
		else if (empty($obj->data['ugp']["$permgroup"]))
		{
			echo "<strong>error</strong>\n";
			echo 'No Data';
			return $output;
		}

		foreach($obj->data['ugp']["$permgroup"] AS $permtitle => $permvalue)
		{
			if ($permvalue['intperm'] OR empty($permvalue['group']))
			{
				continue;
			}
			else
			{
				$output["$permvalue[group]"]["$permtitle"] = array(
					'phrase' => $permvalue['phrase'],
					'value' => $permvalue['value'],
				);
			}
		}

		return $output;
	}

	/**
	* Returns a multi-dimensional array of all defined bitfields, including <nocache> and disabled products
	*
	* $vbulletin->bf_ugp_forumpermissions['canview'] would be returned as $array['ugp']['forumpermissions']['canview']
	*
	* @return	array
	*/
	function return_data()
	{
		if (vB_Bitfield_Builder::build(true, true) === false)
		{
			return false;
		}
		$obj =& vB_Bitfield_Builder::init();
		return $obj->data;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 40651 $
|| ####################################################################
\*======================================================================*/
?>