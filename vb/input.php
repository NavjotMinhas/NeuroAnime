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
 * Input
 * Utility class for handling user input, including cleaning.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: $
 * @since $Date: $
 * @copyright vBulletin Solutions Inc.
 */
abstract class vB_Input
{
	/*Constants=====================================================================*/

	/**
	 * Input types.
	 * These are currently derived from class_core.php
	 */
	const TYPE_NOCLEAN 			= TYPE_NOCLEAN;				// no change

	const TYPE_BOOL 			= TYPE_BOOL; 				// force boolean
	const TYPE_INT 				= TYPE_INT; 				// force integer
	const TYPE_UINT 			= TYPE_UINT; 				// force unsigned integer
	const TYPE_NUM 				= TYPE_NUM; 				// force number
	const TYPE_UNUM 			= TYPE_UNUM; 				// force unsigned number
	const TYPE_UNIXTIME 		= TYPE_UNIXTIME; 			// force unix datestamp (unsigned integer)
	const TYPE_STR 				= TYPE_STR; 				// force trimmed string
	const TYPE_NOTRIM 			= TYPE_NOTRIM; 				// force string - no trim
	const TYPE_NOHTML 			= TYPE_NOHTML; 				// force trimmed string with HTML made safe
	const TYPE_ARRAY 			= TYPE_ARRAY; 				// force array
	const TYPE_FILE 			= TYPE_FILE; 				// force file
	const TYPE_BINARY 			= TYPE_BINARY; 				// force binary string
	const TYPE_NOHTMLCOND 		= TYPE_NOHTMLCOND; 			// force trimmed string with HTML made safe if determined to be unsafe

	const TYPE_ARRAY_BOOL 		= TYPE_ARRAY_BOOL;
	const TYPE_ARRAY_INT 		= TYPE_ARRAY_INT;
	const TYPE_ARRAY_UINT 		= TYPE_ARRAY_UINT;
	const TYPE_ARRAY_NUM 		= TYPE_ARRAY_NUM;
	const TYPE_ARRAY_UNUM 		= TYPE_ARRAY_UNUM;
	const TYPE_ARRAY_UNIXTIME 	= TYPE_ARRAY_UNIXTIME;
	const TYPE_ARRAY_STR 		= TYPE_ARRAY_STR;
	const TYPE_ARRAY_NOTRIM 	= TYPE_ARRAY_NOTRIM;
	const TYPE_ARRAY_NOHTML 	= TYPE_ARRAY_NOHTML;
	const TYPE_ARRAY_ARRAY 		= TYPE_ARRAY_ARRAY;
	const TYPE_ARRAY_FILE 		= TYPE_ARRAY_FILE;  		// An array of "Files" behaves differently than other <input> arrays. TYPE_FILE handles both types.
	const TYPE_ARRAY_BINARY 	= TYPE_ARRAY_BINARY;
	const TYPE_ARRAY_NOHTMLCOND = TYPE_ARRAY_NOHTMLCOND;

	const TYPE_ARRAY_KEYS_INT 	= TYPE_ARRAY_KEYS_INT;
	const TYPE_ARRAY_KEYS_STR 	= TYPE_ARRAY_KEYS_STR;

	const TYPE_CONVERT_SINGLE 	= TYPE_CONVERT_SINGLE; 		// value to subtract from array types to convert to single types
	const TYPE_CONVERT_KEYS 	= TYPE_CONVERT_KEYS; 		// value to subtract from array => keys types to convert to single types



	/*Clean=========================================================================*/

	/**
	 * Cleans a value according to the specified type.
	 * The type should match one of the vB_Input::TYPE_ constants.
	 *
	 * Note: This is currently a wrapper for the vB_Input_Cleaner vB::$vbulletin->input.
	 *
	 * @param mixed $value						- The value to clean
	 * @param int $type							- The type to clean as
	 * @return mixed							- The cleaned value
	 */
	public static function clean($value, $type)
	{
		return vB::$vbulletin->input->clean($value, $type);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28694 $
|| ####################################################################
\*======================================================================*/