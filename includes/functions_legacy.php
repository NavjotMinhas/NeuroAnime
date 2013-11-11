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
* Reads specific variables from the given array, cleans them and places them in the global scope
*
* @deprecated	Deprecated since 3.5. Use $vbulletin->input->clean_array_gpc() instead now.
*
* @param	array	Array from which to read the variables
* @param	array	Array of variable names and data types - array('userid' => INT, 'username' => STR) etc.
*/
function globalize(&$var_array, $var_names)
{
	foreach ($var_names AS $varname => $type)
	{
		if (is_numeric($varname)) // This handles the case where you send a variable in without giving its type, i..e. 'foo' => INT
		{
			$varname = $type;
			$type = '';
		}
		if (isset($var_array["$varname"]) OR $type == INT OR $type == FILE)
		{
			switch ($type)
			{
				// integer value - run intval() on data
				case INT:
					$var_array["$varname"] = intval($var_array["$varname"]);
					break;

				// html-safe string - trim and htmlspecialchars data
				case STR_NOHTML:
					$var_array["$varname"] = htmlspecialchars_uni(trim($var_array["$varname"]));
					break;

				// string - trim data
				case STR:
					$var_array["$varname"] = trim($var_array["$varname"]);
					break;

				// file - get data from $_FILES array
				case FILE:
					if (isset($_FILES["$varname"]))
					{
						$var_array["$varname"] = $_FILES["$varname"];
					}
					break;

				// Do nothing, i.e. arrays, etc.
				default:
			}
			$GLOBALS["$varname"] =& $var_array["$varname"];
		}
	}
}

/**
* Reenable the various legacy variables. This is not recommended for long term compatibility.
*/
function legacy_enable()
{
	/** TRANSLATION EXAMPLES TABLE
	*
	* Shows examples of vB 3.0.3 variables and their 3.5.0 equivalents
	*
	* $vboptions['x']                            --> $vbulletin->options['x']
	* $iforumcache                               --> $vbulletin->iforumcache
	* $forumcache                                --> $vbulletin->forumcache
	* $usergroupcache                            --> $vbulletin->usergroupcache
	* $datastore['wol_spiders']                  --> $vbulletin->wol_spiders
	* $smiliecache                               --> $vbulletin->smiliecache
	* $stylechoosercache                         --> $vbulletin->stylecache
	* $datastore['x']                            --> $vbulletin->x
	*
	* $_BITFIELD['usergroup']                    --> $vbulletin->bf_ugp
	* $_BITFIELD['usergroup']['x']               --> $vbulletin->bf_ugp_x
	* $_BITFIELD['usergroup']['x']['y']          --> $vbulletin->bf_ugp_x['y']
	* $_BITFIELD['calmoderatorpermissions']['x'] --> $vbulletin->bf_misc_calmoderatorpermissions['x']
	* $_BITFIELD['moderatorpermissions']['x']    --> $vbulletin->bf_misc_moderatorpermissions['x']
	* $_BITFIELD['languageoptions']['x']         --> $vbulletin->bf_misc_languageoptions['x']
	* $_USEROPTIONS['x']                         --> $vbulletin->bf_misc_useroptions['x']
	* $_FORUMOPTIONS['x']                        --> $vbulletin->bf_misc_forumoptions['x']
	* $_INTPERMS                                 --> $vbulletin->bf_misc_intperms
	* $_INTPERMS['x']                            --> $vbulletin->bf_misc_intperms['x']
	*/

	global $vbulletin;

	$GLOBALS['DB_site'] =& $db; // this may or may not work

	$GLOBALS['bbuserinfo'] =& $vbulletin->userinfo;
	$GLOBALS['session'] =& $vbulletin->session->vars;

	$GLOBALS['vboptions'] =& $vbulletin->options;
	$GLOBALS['iforumcache'] =& $vbulletin->iforumcache;
	$GLOBALS['forumcache'] =& $vbulletin->forumcache;
	$GLOBALS['usergroupcache'] =& $vbulletin->usergroupcache;
	$GLOBALS['smiliecache'] =& $vbulletin->smiliecache;
	$GLOBALS['stylechoosercache'] =& $vbulletin->stylecache;

	$dstore = array(
		'attachmentcache',
		'$avatarcache',
		'birthdaycache',
		'eventcache',
		'iconcache',
		'markupcache',
		'bbcodecache',
		'cron',
		'mailqueue',
		'banemail',
		'maxloggedin',
		'pluginlist',
		'products',
		'rankphp',
		'statement',
		'userstats',
		'wol_spiders'
	);
	foreach ($dstore AS $item)
	{
		if ($vbulletin->$item !== null)
		{
			$GLOBALS['datastore']["$dstore"] =& $vbulletin->$item;
		}
	}

	$GLOBALS['_BITFIELD']['usergroup'] =& $vbulletin->bf_ugp;
	$GLOBALS['_BITFIELD']['calmoderatorpermissions'] =& $vbulletin->bf_misc_calmoderatorpermissions;
	$GLOBALS['_BITFIELD']['moderatorpermissions'] =& $vbulletin->bf_misc_moderatorpermissions;
	$GLOBALS['_BITFIELD']['languageoptions'] =& $vbulletin->bf_misc_languageoptions;

	$GLOBALS['_USEROPTIONS'] =& $vbulletin->bf_misc_useroptions;
	$GLOBALS['_FORUMOPTIONS'] =& $vbulletin->bf_misc_forumoptions;
	$GLOBALS['_INTPERMS'] =& $vbulletin->bf_misc_intperms;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>