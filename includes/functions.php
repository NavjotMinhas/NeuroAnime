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

// defines for the datamanagers
define('ERRTYPE_ARRAY',    0);
define('ERRTYPE_STANDARD', 1);
define('ERRTYPE_CP',       2);
define('ERRTYPE_SILENT',   3);

define('VF_TYPE',       0);
define('VF_REQ',        1);
define('VF_CODE',       2);
define('VF_METHODNAME', 3);

define('VF_METHOD', '_-_mEtHoD_-_');

define('REQ_NO',   0);
define('REQ_YES',  1);
define('REQ_AUTO', 2);
define('REQ_INCR', 3);

/**
* @ignore
*/
define('COOKIE_SALT', 'FPvcQJXNE2b6bxLR4OiweeI2jYt1');
define('EDITOR_INDENT', 40);

// #############################################################################
/**
* Essentially a wrapper for the ternary operator.
*
* @deprecated	Deprecated as of 3.5. Use the ternary operator.
*
* @param	string	Expression to be evaluated
* @param	mixed	Return this if the expression evaluates to true
* @param	mixed	Return this if the expression evaluates to false
*
* @return	mixed	Either the second or third parameter of this function
*/
function iif($expression, $returntrue, $returnfalse = '')
{
	return ($expression ? $returntrue : $returnfalse);
}

// #############################################################################
/**
* Converts shorthand string version of a size to bytes, 8M = 8388608
*
* @param	string			The value from ini_get that needs converted to bytes
*
* @return	integer			Value expanded to bytes
*/
function ini_size_to_bytes($value)
{
	$value = trim($value);
	$retval = intval($value);

	switch(strtolower($value[strlen($value) - 1]))
	{
		case 'g':
			$retval *= 1024;
			/* break missing intentionally */
		case 'm':
			$retval *= 1024;
			/* break missing intentionally */
		case 'k':
			$retval *= 1024;
			break;
	}

	return $retval;
}

// #############################################################################
/**
* Class factory. This is used for instantiating the extended classes.
*
* @param	string			The type of the class to be called (user, forum etc.)
* @param	vB_Registry		An instance of the vB_Registry object.
* @param	integer			One of the ERRTYPE_x constants
* @param	string			Option to force loading a class from a specific file; no extension
*
* @return	vB_DataManager	An instance of the desired class
*/
function &datamanager_init($classtype, &$registry, $errtype = ERRTYPE_STANDARD, $forcefile = '')
{
	static $called;

	if (empty($called))
	{
		// include the abstract base class
		require_once(DIR . '/includes/class_dm.php');
		$called = true;
	}

	if (preg_match('#^\w+$#', $classtype))
	{
		$classtype = strtolower($classtype);
		if ($forcefile)
		{
			$classfile = preg_replace('#[^a-z0-9_]#i', '', $forcefile);
		}
		else
		{
			$classfile = str_replace('_multiple', '', $classtype);
		}
		require_once(DIR . '/includes/class_dm_' . $classfile . '.php');

		switch($classtype)
		{
			case 'userpic_avatar':
			case 'userpic_profilepic':
			case 'userpic_sigpic':
				$object = vB_DataManager_Userpic::fetch_library($registry, $errtype, $classtype);
				break;
			case('socialgroupicon'):
				$object = vB_DataManager_SocialGroupIcon::fetch_library($registry, $errtype, $classtype);
				break;
			default:
				$classname = 'vB_DataManager_' . $classtype;
				$object = new $classname($registry, $errtype);
		}

		return $object;
	}
}

// #############################################################################
/**
* Converts A-Z to a-z, doesn't change any other characters
*
* @param	string	String to convert to lowercase
*
* @return	string	Lowercase string
*/
function vbstrtolower($string)
{
	if (function_exists('mb_strtolower') AND $newstring = @mb_strtolower($string, vB_Template_Runtime::fetchStyleVar('charset')))
	{
		return $newstring;
	}
	else
	{
		return strtr($string,
			'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
			'abcdefghijklmnopqrstuvwxyz'
		);
	}
}

// #############################################################################
/**
* Splits a string into individual words for use in the search index
*
* @param	string	String to be seperated into individual words
*
* @return	array	Array of words based on the input string
*/
function split_string($string)
{
	switch (vB_Template_Runtime::fetchStyleVar('charset'))
	{
		case 'big5':
			preg_match_all('#((?:[A-Za-z]+)|(?:[\xa1-\xfe][\x40-\x7e]|[\xa1-\xfe]))#xs', $string, $matches);
			if (!$matches)
			{
				return array();
			}
			return $matches[0];
		break;
		default:
			return explode(' ', $string);
	}
}

// #############################################################################
/**
* Attempts to do a character-based strlen on data that might contain HTML entities.
* By default, it only converts numeric entities but can optional convert &quot;,
* &lt;, etc. Uses a multi-byte aware function to do the counting if available.
*
* @param	string	String to be measured
* @param	boolean	If true, run unhtmlspecialchars on string to count &quot; as one, etc.
*
* @return	integer	Length of string
*/
function vbstrlen($string, $unhtmlspecialchars = false)
{
	$string = preg_replace('#&\#([0-9]+);#', '_', $string);
	if ($unhtmlspecialchars)
	{
		// don't try to translate unicode entities ever, as we want them to count as 1 (above)
		$string = unhtmlspecialchars($string, false);
	}

	if (function_exists('mb_strlen') AND $length = @mb_strlen($string, vB_Template_Runtime::fetchStyleVar('charset')))
	{
		return $length;
	}
	else
	{
		return strlen($string);
	}
}

/**
* Chops off a string at a specific length, counting entities as once character
* and using multibyte-safe functions if available.
*
* @param	string	String to chop
* @param	integer	Number of characters to chop at
*
* @return	string	Chopped string
*/
function vbchop($string, $length)
{
	$length = intval($length);
	if ($length <= 0)
	{
		return $string;
	}

	// Pretruncate the string to something shorter, so we don't run into memory problems with
	// very very very long strings at the regular expression down below.
	//
	// UTF-32 allows 0x7FFFFFFF code space, meaning possibility of code point: &#2147483647;
	// If we assume entire string we want to keep is in this butchered form, we need to keep
	// 13 bytes per character we want to output. Strings actually encoded in UTF-32 takes 4
	// bytes per character, so 13 is large enough to cover that without problem, too.
	//
	// ((Unlike the regex below, no memory problems here with very very very long comments.))
	$pretruncate = 13 * $length;
	$string = substr($string, 0, $pretruncate);

	if (preg_match_all('/&(#[0-9]+|lt|gt|quot|amp);/', $string, $matches, PREG_OFFSET_CAPTURE))
	{
		// find all entities because we need to count them as 1 character
		foreach ($matches[0] AS $match)
		{
			$entity_length = strlen($match[0]);
			$offset = $match[1];

			// < since length starts at 1 but offset starts at 0
			if ($offset < $length)
			{
				// this entity happens in the chop area, so extend the length to include this
				// -1 since the entity should still count as 1 character
				$length += strlen($match[0])  - 1;
			}
			else
			{
				break;
			}
		}
	}

	if (function_exists('mb_substr'))
	{
		$charset = vB_Template_Runtime::fetchStyleVar('charset');
		$substr = $charset ? @mb_substr($string, 0, $length, $charset) : @mb_substr($string, 0, $length);
	}

	if ($substr != '' )
	{
		return $substr;
	}
	else
	{
		return substr($string, 0, $length);
	}
}

// #############################################################################
/**
 * Transliterates non ASCII chars to ASCII.
 * This is an approximation.
 *
 * Note: Performance and accuracy is gained if the pecl translit extension is available.
 * @see http://pecl.php.net/package/translit
 *
 * @param	string String to transliterate
 * @return	string
 */
function to_ascii($str)
{
	if (!$str)
    {
    	return;
    }

    if (function_exists('transliterate'))
    {
    	return transliterate($str, array('normalize_ligature'), 'ISO-8859-1', 'ISO-8859-1');
    }

	static $lookup = array(
		'&Agrave;' => 'A',
		'&Aacute;' => 'A',
		'&Acirc;' => 'A',
		'&Atilde;' => 'A',
		'&Auml;' => 'AE',
		'&Aring;' => 'A',
		'&AElig;' => 'AE',
		'&Ccedil;' => 'C',
		'&Egrave;' => 'E',
		'&Eacute;' => 'E',
		'&Ecirc;' => 'E',
		'&Euml;' => 'E',
		'&Igrave;' => 'I',
		'&Iacute;' => 'I',
		'&Icirc;' => 'I',
		'&Iuml;' => 'I',
		'&ETH;' => 'Dj',
		'&Ntilde;' => 'N',
		'&Ograve;' => 'O',
		'&Oacute;' => 'O',
		'&Ocirc;' => 'O',
		'&Otilde;' => 'O',
		'&Ouml;' => 'OE',
		'&Oslash;' => 'U',
		'&Ugrave;' => 'U',
		'&Uacute;' => 'U',
		'&Ucirc;' => 'U',
		'&Uuml;' => 'UE',
		'&Yacute;' => 'Y',
		'&THORN;' => 'Th',
		'&szlig;' => 'ss',
		'&agrave;' => 'a',
		'&aacute;' => 'a',
		'&acirc;' => 'a',
		'&atilde;' => 'a',
		'&auml;' => 'ae',
		'&aring;' => 'a',
		'&aelig;' => 'ae',
		'&ccedil;' => 'c',
		'&egrave;' => 'e',
		'&eacute;' => 'e',
		'&ecirc;' => 'e',
		'&euml;' => 'e',
		'&igrave;' => 'i',
		'&iacute;' => 'i',
		'&icirc;' => 'i',
		'&iuml;' => 'i',
		'&eth;' => 'dj',
		'&ntilde;' => 'n',
		'&ograve;' => 'o',
		'&oacute;' => 'o',
		'&ocirc;' => 'o',
		'&otilde;' => 'o',
		'&ouml;' => 'oe',
		'&oslash;' => 'o',
		'&ugrave;' => 'u',
		'&uacute;' => 'u',
		'&ucirc;' => 'u',
		'&uuml;' => 'ue',
		'&yacute;' => 'y',
		'&thorn;' => 'th',
		'&yuml;' => 'y'
	);

    $str = htmlentities($str);
    $str = str_replace(array_keys($lookup), array_values($lookup), $str);
    $str = html_entity_decode($str);
    $str = preg_replace('#[^a-z0-9]+#i', '-', $str);

    return $str;
}

// #############################################################################
/**
* Formats a number with user's own decimal and thousands chars
*
* @param	mixed	Number to be formatted: integer / 8MB / 16 GB / 6.0 KB / 3M / 5K / ETC
* @param	integer	Number of decimal places to display
* @param	boolean	Special case for byte-based numbers
*
* @return	mixed	The formatted number
*/
function vb_number_format($number, $decimals = 0, $bytesize = false, $decimalsep = null, $thousandsep = null)
{
	global $vbulletin, $vbphrase;

	if (defined('VB_API') AND VB_API === true)
	{
		// The number format of API should always be standard
		$decimalsep = '.';
		$thousandsep = '';
	}

	$type = '';

	if (empty($number))
	{
		return 0;
	}
	else if (preg_match('#^(\d+(?:\.\d+)?)(?>\s*)([mkg])b?$#i', trim($number), $matches))
	{
		switch(strtolower($matches[2]))
		{
			case 'g':
				$number = $matches[1] * 1073741824;
				break;
			case 'm':
				$number = $matches[1] * 1048576;
				break;
			case 'k':
				$number = $matches[1] * 1024;
				break;
			default:
				$number = $matches[1] * 1;
		}
	}

	if ($bytesize)
	{
		if ($number >= 1073741824)
		{
			$number = $number / 1073741824;
			$decimals = 2;
			$type = " $vbphrase[gigabytes]";
		}
		else if ($number >= 1048576)
		{
			$number = $number / 1048576;
			$decimals = 2;
			$type = " $vbphrase[megabytes]";
		}
		else if ($number >= 1024)
		{
			$number = $number / 1024;
			$decimals = 1;
			$type = " $vbphrase[kilobytes]";
		}
		else
		{
			$decimals = 0;
			$type = " $vbphrase[bytes]";
		}
	}

	if ($decimalsep === null)
	{
		$decimalsep = $vbulletin->userinfo['lang_decimalsep'];
	}
	if ($thousandsep === null)
	{
		$thousandsep = $vbulletin->userinfo['lang_thousandsep'];
	}

	return str_replace('_', '&nbsp;', number_format($number, $decimals, $decimalsep, $thousandsep)) . $type;
}

// #############################################################################
/**
* Generates a random password that is much stronger than what we currently use.
*
* @param	integer	Length of desired password
*/
function fetch_random_password($length = 8)
{
	$password_characters = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz';
	$total_password_characters = strlen($password_characters) - 1;

	$digit = vbrand(0, $length - 1);

	$newpassword = '';
	for ($i = 0; $i < $length; $i++)
	{
		if ($i == $digit)
		{
			$newpassword .= chr(vbrand(48, 57));
			continue;
		}

		$newpassword .= $password_characters{vbrand(0, $total_password_characters)};
	}
	return $newpassword;
}

// #############################################################################
/**
* vBulletin's hash fetcher, note this may change from a-f0-9 to a-z0-9 in future.
*
* @param	integer	Length of desired hash, limited to 40 characters at most
*/
function fetch_random_string($length = 32)
{
	$hash = sha1(TIMENOW . SESSION_HOST . microtime() . uniqid(mt_rand(), true) . @implode('', @fstat(@fopen( __FILE__, 'r'))));

	return substr($hash, 0, $length);
}

// #############################################################################
/**
* vBulletin's own random number generator
*
* @param	integer	Minimum desired value
* @param	integer	Maximum desired value
* @param	mixed	Seed for the number generator (if not specified, a new seed will be generated)
*/
function vbrand($min = 0, $max = 0, $seed = -1)
{
	mt_srand(crc32(microtime()));

	if ($max AND $max <= mt_getrandmax())
	{
		$number = mt_rand($min, $max);
	}
	else
	{
		$number = mt_rand();
	}
	// reseed so any calls outside this function don't get the second number
	mt_srand();

	return $number;
}

// #############################################################################
/**
* Returns an array of usergroupids from all the usergroups to which a user belongs
*
* @param	array	User info array - must contain usergroupid and membergroupid fields
* @param	boolean	Whether or not to fetch the user's primary group as part of the returned array
*
* @return	array	Usergroup IDs to which the user belongs
*/
function fetch_membergroupids_array($user, $getprimary = true)
{
	if (!empty($user['membergroupids']))
	{
		$membergroups = explode(',', str_replace(' ', '', $user['membergroupids']));
	}
	else
	{
		$membergroups = array();
	}

	if ($getprimary)
	{
		$membergroups[] = $user['usergroupid'];
	}

	return array_unique($membergroups);
}

// #############################################################################
/**
* Works out if a user is a member of the specified usergroup(s)
*
* This function can be overloaded to test multiple usergroups: is_member_of($user, 1, 3, 4, 6...)
*
* @param	array	User info array - must contain userid, usergroupid and membergroupids fields
* @param	integer	Usergroup ID to test
* @param	boolean	Pull result from cache
*
* @return	boolean
*/
function is_member_of($userinfo, $usergroupid, $cache = true)
{
	static $user_memberships;

	switch (func_num_args())
	{
		// 1 can't happen

		case 2: // note: func_num_args doesn't count args with default values unless they're overridden
			$groups = is_array($usergroupid) ? $usergroupid : array($usergroupid);
		break;

		case 3:
			if (is_array($usergroupid))
			{
				$groups = $usergroupid;
				$cache = (bool)$cache;
			}
			else if (is_bool($cache))
			{
				// passed in 1 group and a cache state
				$groups = array($usergroupid);
			}
			else
			{
				// passed in 2 groups
				$groups = array($usergroupid, $cache);
				$cache = true;
			}
		break;

		default:
			// passed in 4+ args, which means it has to be in the 1,2,3 method
			$groups = func_get_args();
			unset($groups[0]);

			$cache = true;
	}

	if (!is_array($user_memberships["$userinfo[userid]"]) OR !$cache)
	{
		// fetch membergroup ids for this user
		$user_memberships["$userinfo[userid]"] = fetch_membergroupids_array($userinfo);
	}

	foreach ($groups AS $usergroupid)
	{
		// is current group user's primary usergroup, or one of their membergroups?
		if ($userinfo['usergroupid'] == $usergroupid OR in_array($usergroupid, $user_memberships["$userinfo[userid]"]))
		{
			// yes - return true
			return true;
		}
	}

	// if we get here then the user doesn't belong to any of the groups.
	return false;
}

// #############################################################################
/**
* Works out if the specified user is 'in Coventry'
*
* @param	integer	User ID
* @param	boolean	Whether or not to confirm that the visiting user is himself in Coventry or not
*
* @return	boolean
*/
function in_coventry($userid, $includeself = false)
{
	global $vbulletin;
	static $Coventry;

	// if user is guest, or user is bbuser, user is NOT in Coventry.
	if ($userid == 0 OR ($userid == $vbulletin->userinfo['userid'] AND $includeself == false))
	{
		return false;
	}

	if (!is_array($Coventry))
	{
		if (trim($vbulletin->options['globalignore']) != '')
		{
			$Coventry = preg_split('#\s+#s', $vbulletin->options['globalignore'], -1, PREG_SPLIT_NO_EMPTY);
		}
		else
		{
			$Coventry = array();
		}
	}

	// if Coventry is empty, user is not in Coventry
	if (empty($Coventry))
	{
		return false;
	}

	// return whether or not user's id is in Coventry
	return in_array($userid, $Coventry);
}


// #############################################################################
/**
* Replaces any non-printing ASCII characters with the specified string.
* This also supports removing Unicode characters automatically when
* the entered value is >255 or starts with a 'u'.
*
* @param	string	Text to be processed
* @param	string	String with which to replace non-printing characters
*
* @return	string
*/
function strip_blank_ascii($text, $replace)
{
	global $vbulletin;
	static $blanks = null;

	if ($blanks === null AND trim($vbulletin->options['blankasciistrip']) != '')
	{
		$blanks = array();

		if (!($charset = vB_Template_Runtime::fetchStyleVar('charset')))
		{
			$charset = $vbulletin->userinfo['lang_charset'];
		}

		$charset_unicode = (strtolower($charset) == 'utf-8');

		$raw_blanks = preg_split('#\s+#', $vbulletin->options['blankasciistrip'], -1, PREG_SPLIT_NO_EMPTY);
		foreach ($raw_blanks AS $code_point)
		{
			if ($code_point[0] == 'u')
			{
				// this is a unicode character to remove
				$code_point = intval(substr($code_point, 1));
				$force_unicode = true;
			}
			else
			{
				$code_point = intval($code_point);
				$force_unicode = false;
			}

			if ($code_point > 255 OR $force_unicode OR $charset_unicode)
			{
				// outside ASCII range or forced Unicode, so the chr function wouldn't work anyway
				$blanks[] = '&#' . $code_point . ';';
				$blanks[] = convert_int_to_utf8($code_point);
			}
			else
			{
				$blanks[] = chr($code_point);
			}
		}
	}

	if ($blanks)
	{
		$text = str_replace($blanks, $replace, $text);
	}

	return $text;
}

// #############################################################################
/**
* Replaces any instances of words censored in $vbulletin->options['censorwords'] with $vbulletin->options['censorchar']
*
* @param	string	Text to be censored
*
* @return	string
*/
function fetch_censored_text($text)
{
	global $vbulletin;
	static $censorwords;

	if (!$text)
	{
		// return $text rather than nothing, since this could be '' or 0
		return $text;
	}

	if ($vbulletin->options['enablecensor'] AND !empty($vbulletin->options['censorwords']))
	{
		if (empty($censorwords))
		{
			$vbulletin->options['censorwords'] = preg_quote($vbulletin->options['censorwords'], '#');
			$censorwords = preg_split('#[ \r\n\t]+#', $vbulletin->options['censorwords'], -1, PREG_SPLIT_NO_EMPTY);
		}

		foreach ($censorwords AS $censorword)
		{
			if (substr($censorword, 0, 2) == '\\{')
			{
				if (substr($censorword, -2, 2) == '\\}')
				{
					// prevents errors from the replace if the { and } are mismatched
					$censorword = substr($censorword, 2, -2);
				}

				// ASCII character search 0-47, 58-64, 91-96, 123-127
				$nonword_chars = '\x00-\x2f\x3a-\x40\x5b-\x60\x7b-\x7f';

				// words are delimited by ASCII characters outside of A-Z, a-z and 0-9
				$text = preg_replace(
					'#(?<=[' . $nonword_chars . ']|^)' . $censorword . '(?=[' . $nonword_chars . ']|$)#si',
					str_repeat($vbulletin->options['censorchar'], vbstrlen($censorword)),
					$text
				);
			}
			else
			{
				$text = preg_replace("#$censorword#si", str_repeat($vbulletin->options['censorchar'], vbstrlen($censorword)), $text);
			}
		}
	}

	// strip any admin-specified blank ascii chars
	$text = strip_blank_ascii($text, $vbulletin->options['censorchar']);

	return $text;
}

// #############################################################################
/**
* Attempts to intelligently wrap excessively long strings onto multiple lines
*
* @param	string	Text to be wrapped
* @param	integer	If specified, max word wrap length
* @param	string	Text to insert at the wrap point
*
* @return	string
*/
function fetch_word_wrapped_string($text, $limit = false, $wraptext = ' ')
{
	global $vbulletin;

	if ($limit === false)
	{
		$limit = $vbulletin->options['wordwrap'];
	}

	$limit = intval($limit);

	if ($limit > 0 AND !empty($text))
	{
		return preg_replace('
			#((?>[^\s&/<>"\\-\[\]]|&[\#a-z0-9]{1,7};){' . $limit . '})(?=[^\s&/<>"\\-\[\]]|&[\#a-z0-9]{1,7};)#i',
			'$0' . $wraptext,
			$text
		);
	}
	else
	{
		return $text;
	}
}

// #############################################################################
/**
* Trims a string to the specified length while keeping whole words
*
* @param	string	String to be trimmed
* @param	integer	Number of characters to aim for in the trimmed string
* @param  boolean Append "..." to shortened text
*
* @return	string
*/
function fetch_trimmed_title($title, $chars = -1, $append = true)
{
	global $vbulletin;

	if ($chars == -1)
	{
		$chars = $vbulletin->options['lastthreadchars'];
	}

	if ($chars)
	{
		// limit to 10 lines (\n{240}1234567890 does weird things to the thread preview)
		$titlearr = preg_split('#(\r\n|\n|\r)#', $title);
		$title = '';
		$i = 0;
		foreach ($titlearr AS $key)
		{
			$title .= "$key \n";
			$i++;
			if ($i >= 10)
			{
				break;
			}
		}
		$title = trim($title);
		unset($titlearr);

		if (vbstrlen($title) > $chars)
		{
			$title = vbchop($title, $chars);
			if (($pos = strrpos($title, ' ')) !== false)
			{
				$title = substr($title, 0, $pos);
			}
			if ($append)
			{
				$title .= '...';
			}
		}

		//$title = fetch_soft_break_string($title);
	}

	return $title;
}

// #############################################################################
/**
* Breaks up strings (typically URLs) semi-invisibly to avoid word-wrapping issues
*
* @param	string	Text to be broken
*
* @return	string
*/
function fetch_soft_break_string($string)
{
	// replace forward slashes and question marks not followed by digits or slashes with soft hyphens (&shy;)
	return preg_replace('#(/|\?)([^/\d])#s', '\1&shy;\2', $string);
}

// #############################################################################
/**
* Checks to see if the IP address of the visiting user is banned from visiting
*
* This function will show an error and halt execution if the IP is banned.
*/
function verify_ip_ban()
{
	// make sure we can contact the admin
	if (THIS_SCRIPT == 'sendmessage' AND (empty($_REQUEST['do']) OR $_REQUEST['do'] == 'contactus' OR $_REQUEST['do'] == 'docontactus'))
	{
		return;
	}

	global $vbulletin;

	$user_ipaddress = IPADDRESS . '.';

	if ($vbulletin->options['enablebanning'] == 1 AND $vbulletin->options['banip'] = trim($vbulletin->options['banip']))
	{
		$addresses = preg_split('#\s+#', $vbulletin->options['banip'], -1, PREG_SPLIT_NO_EMPTY);
		foreach ($addresses AS $banned_ip)
		{
			if (strpos($banned_ip, '*') === false AND $banned_ip{strlen($banned_ip) - 1} != '.')
			{
				$banned_ip .= '.';
			}

			$banned_ip_regex = str_replace('\*', '(.*)', preg_quote($banned_ip, '#'));
			if (preg_match('#^' . $banned_ip_regex . '#U', $user_ipaddress))
			{
				eval(standard_error(fetch_error('banip', $vbulletin->options['contactuslink'])));
			}
		}
	}
}

// #############################################################################
/**
* Fetches the remaining characters in a filename after the final dot
*
* @param	string	The filename to test
*
* @return	string	The extension of the provided file
*/
function file_extension($filename)
{
	return substr(strrchr($filename, '.'), 1);
}

// #############################################################################
/**
* Tests a string to see if it's a valid email address
*
* @param	string	Email address
*
* @return	boolean
*/
function is_valid_email($email)
{
	// checks for a valid email format
	return preg_match('#^[a-z0-9.!\#$%&\'*+-/=?^_`{|}~]+@([0-9.]+|([^\s\'"<>@,;]+\.+[a-z]{2,6}))$#si', $email);
}

// #############################################################################
/**
* Reads the email message queue and delivers a number of pending emails to the message sender
*/
function exec_mail_queue()
{
	global $vbulletin;

	if ($vbulletin->mailqueue !== null AND $vbulletin->mailqueue > 0 AND $vbulletin->options['usemailqueue'])
	{
		// mailqueue template holds number of emails awaiting sending
		if (!class_exists('vB_Mail', false))
		{
			require_once(DIR . '/includes/class_mail.php');
		}

		$mail =& vB_QueueMail::fetch_instance();
		$mail->exec_queue();
	}
}

// #############################################################################
/**
* Begin adding email to the mail queue
*/
function vbmail_start()
{
	if (!class_exists('vB_Mail', false))
	{
		require_once(DIR . '/includes/class_mail.php');
	}
	$mail =& vB_QueueMail::fetch_instance();
	$mail->set_bulk(true);
}

// #############################################################################
/**
* Starts the process of sending an email - either immediately or by adding it to the mail queue.
*
* @param	string	Destination email address
* @param	string	Email message subject
* @param	string	Email message body
* @param	boolean	If true, do not use the mail queue and send immediately
* @param	string	Optional name/email to use in 'From' header
* @param	string	Additional headers
* @param	string	Username of person sending the email
*/
function vbmail($toemail, $subject, $message, $sendnow = false, $from = '', $uheaders = '', $username = '')
{
	if (empty($toemail))
	{
		return false;
	}

	global $vbulletin;

	if (!class_exists('vB_Mail', false))
	{
		require_once(DIR . '/includes/class_mail.php');
	}

	if (!($mail = vB_Mail::fetchLibrary($vbulletin, !$sendnow AND $vbulletin->options['usemailqueue'])))
	{
		return false;
	}

	if (!$mail->start($toemail, $subject, $message, $from, $uheaders, $username))
	{
		return false;
	}

	return $mail->send();
}

// #############################################################################
/**
* Stop adding mail to the mail queue and insert the mailqueue data for sending later
*/
function vbmail_end()
{
	if (!class_exists('vB_Mail', false))
	{
		require_once(DIR . '/includes/class_mail.php');
	}
	$mail =& vB_QueueMail::fetch_instance();
	$mail->set_bulk(false);
}

// #############################################################################
/**
* Returns a portion of an SQL query to select language fields from the database
*
* @param	boolean	If true, select 'language.fieldname' otherwise 'fieldname'
*
* @return	string
*/
function fetch_language_fields_sql($addtable = true)
{
	global $phrasegroups, $vbulletin;

	if (!is_array($phrasegroups))
	{
		$phrasegroups = array();
	}

	if (!defined('NOGLOBALPHRASE'))
	{
		array_unshift($phrasegroups, 'global');
	}

	if ($addtable)
	{
		$prefix = 'language.';
	}
	else
	{
		$prefix = '';
	}

	$sql = '';

	foreach ($phrasegroups AS $group)
	{
		$group = preg_replace('#[^a-z0-9_]#i', '', $group); // just to be safe...
		if ($group == 'reputationlevel' AND !$vbulletin->options['reputationenable'] AND VB_AREA == 'Forum')
		{	// Don't load reputation phrases if reputation is disabled
			continue;
		}
		$sql .= ",
			{$prefix}phrasegroup_$group AS phrasegroup_$group";
	}

	$sql .= ",
			{$prefix}phrasegroupinfo AS lang_phrasegroupinfo,
			{$prefix}options AS lang_options,
			{$prefix}languagecode AS lang_code,
			{$prefix}charset AS lang_charset,
			{$prefix}locale AS lang_locale,
			{$prefix}imagesoverride AS lang_imagesoverride,
			{$prefix}dateoverride AS lang_dateoverride,
			{$prefix}timeoverride AS lang_timeoverride,
			{$prefix}registereddateoverride AS lang_registereddateoverride,
			{$prefix}calformat1override AS lang_calformat1override,
			{$prefix}calformat2override AS lang_calformat2override,
			{$prefix}logdateoverride AS lang_logdateoverride,
			{$prefix}decimalsep AS lang_decimalsep,
			{$prefix}thousandsep AS lang_thousandsep";

	return $sql;
}

// #############################################################################
/**
* Returns an UPDATE or INSERT query string for use in big queries with loads of fields...
*
* @param	array	Array of fieldname = value pairs - array('userid' => 21, 'username' => 'John Doe')
* @param	string	Name of the table into which the data should be saved
* @param	string	SQL condition to add to the query string
* @param	array	Array of field names that should be ignored from the $queryvalues array
*
* @return	string
*/
function fetch_query_sql($queryvalues, $table, $condition = '', $exclusions = '')
{
	global $vbulletin;

	if (empty($exclusions))
	{
		$exclusions = array();
	}

	$numfields = sizeof($queryvalues);
	$i = 1;

	if (!empty($condition))
	{
		$querystring = "\n### UPDATE QUERY GENERATED BY fetch_query_sql() ###\n";
		foreach($queryvalues AS $fieldname => $value)
		{
			if (!preg_match('#^\w+$#', $fieldname))
			{
				continue;
			}
			$querystring .= "\t`$fieldname` = " . iif(is_numeric($value) OR in_array($fieldname, $exclusions), "'$value'", "'" . $vbulletin->db->escape_string($value) . "'") . iif($i++ == $numfields, "\n", ",\n");
		}
		return "UPDATE " . TABLE_PREFIX . "$table SET\n$querystring$condition";
	}
	else
	{
		#$fieldlist = $table . 'id, ';
		#$valuelist = 'NULL, ';
		$fieldlist = '';
		$valuelist = '';
		foreach($queryvalues AS $fieldname => $value)
		{
			if (!preg_match('#^\w+$#', $fieldname))
			{
				continue;
			}
			$endbit = iif($i++ == $numfields, '', ', ');
			$fieldlist .= "`" . $fieldname . "`" . $endbit;
			$valuelist .= iif(is_numeric($value) OR in_array($fieldname, $exclusions), "'$value'", "'" . $vbulletin->db->escape_string($value) . "'") . $endbit;
		}
		return "\n### INSERT QUERY GENERATED BY fetch_query_sql() ###\nINSERT INTO " . TABLE_PREFIX . "$table\n\t($fieldlist)\nVALUES\n\t($valuelist)";
	}
}

// #############################################################################
/**
* fetches the proper username markup and title
*
* @param	array	(ref) User info array
* @param	string	Name of the field representing displaygroupid in the User info array
* @param	string	Name of the field representing username in the User info array
*
* @return	string
*/
function fetch_musername(&$user, $displaygroupfield = 'displaygroupid', $usernamefield = 'username')
{
	global $vbulletin;

	if (!empty($user['musername']))
	{
		// function already been called
		return $user['musername'];
	}

	$username = $user["$usernamefield"];

	if (!empty($user['infractiongroupid']) AND $vbulletin->usergroupcache["$user[usergroupid]"]['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup'])
	{
		$displaygroupfield = 'infractiongroupid';
	}

	if (isset($user["$displaygroupfield"], $vbulletin->usergroupcache["$user[$displaygroupfield]"]) AND $user["$displaygroupfield"] > 0)
	{
		// use $displaygroupid
		$displaygroupid = $user["$displaygroupfield"];
	}
	else if (isset($vbulletin->usergroupcache["$user[usergroupid]"]) AND $user['usergroupid'] > 0)
	{
		// use primary usergroupid
		$displaygroupid = $user['usergroupid'];
	}
	else
	{
		// use guest usergroup
		$displaygroupid = 1;
	}

	$user['musername'] = $vbulletin->usergroupcache["$displaygroupid"]['opentag'] . $username . $vbulletin->usergroupcache["$displaygroupid"]['closetag'];
	$user['displaygrouptitle'] = $vbulletin->usergroupcache["$displaygroupid"]['title'];
	$user['displayusertitle'] = $vbulletin->usergroupcache["$displaygroupid"]['usertitle'];

	if ($displaygroupfield == 'infractiongroupid' AND $usertitle = $vbulletin->usergroupcache["$user[$displaygroupfield]"]['usertitle'])
	{
		$user['usertitle'] = $usertitle;
	}
	else if (isset($user['customtitle']) AND $user['customtitle'] == 2)
	{
		$user['usertitle'] = htmlspecialchars_uni($user['usertitle']);
	}

	static $hook_code = false;
	if ($hook_code === false)
	{
		$hook_code = vBulletinHook::fetch_hook('fetch_musername');
	}
	if ($hook_code)
	{
		eval($hook_code);
	}

	return $user['musername'];
}

// #############################################################################
/**
* Returns an array containing info for the specified forum, or false if forum is not found
*
* @param	integer	(ref) Forum ID
* @param	boolean	Whether or not to return the result from the forumcache if it exists
*
* @return	mixed
*/
function fetch_foruminfo(&$forumid, $usecache = true)
{
	global $vbulletin;

	$forumid = intval($forumid);
	if (!$usecache OR !isset($vbulletin->forumcache["$forumid"]))
	{
		if (isset($vbulletin->forumcache["$forumid"]['permissions']))
		{
			$perms = $vbulletin->forumcache["$forumid"]['permissions'];
		}

		$vbulletin->forumcache["$forumid"] = $vbulletin->db->query_first_slave("
			SELECT forum.*, NOT ISNULL(podcast.forumid) AS podcast
			FROM " . TABLE_PREFIX . "forum AS forum
			LEFT JOIN " . TABLE_PREFIX . "podcast AS podcast ON (forum.forumid = podcast.forumid AND podcast.enabled = 1)
			WHERE forum.forumid = $forumid
		");
	}

	if (!$vbulletin->forumcache["$forumid"])
	{
		return false;
	}

	if (isset($perms))
	{
		$vbulletin->forumcache["$forumid"]['permissions'] = $perms;
	}

	// decipher 'options' bitfield
	$vbulletin->forumcache["$forumid"]['options'] = intval($vbulletin->forumcache["$forumid"]['options']);
	foreach($vbulletin->bf_misc_forumoptions AS $optionname => $optionval)
	{
		$vbulletin->forumcache["$forumid"]["$optionname"] = (($vbulletin->forumcache["$forumid"]['options'] & $optionval) ? 1 : 0);
	}

	// depth == num of forums in parent list - 2 (itself, -1) == commas - 1
	$vbulletin->forumcache["$forumid"]['depth'] = substr_count($vbulletin->forumcache["$forumid"]['parentlist'], ',') - 1;

	($hook = vBulletinHook::fetch_hook('fetch_foruminfo')) ? eval($hook) : false;

	return $vbulletin->forumcache["$forumid"];
}

// #############################################################################
/**
* Returns an array containing info for the speficied thread, or false if thread is not found
*
* @param	integer	(ref) Thread ID
*
* @return	mixed
*/
function fetch_threadinfo(&$threadid, $usecache = true)
{
	global $vbulletin, $threadcache, $vbphrase;

	if ($vbulletin->userinfo['userid'] AND in_coventry($vbulletin->userinfo['userid'], true))
	{
		$tachyjoin = "
			LEFT JOIN " . TABLE_PREFIX . "tachythreadcounter AS tachythreadcounter ON
				(tachythreadcounter.threadid = thread.threadid AND tachythreadcounter.userid = " . $vbulletin->userinfo['userid'] . ")
			LEFT JOIN " . TABLE_PREFIX . "tachythreadpost AS tachythreadpost ON
				(tachythreadpost.threadid = thread.threadid AND tachythreadpost.userid = " . $vbulletin->userinfo['userid'] . ')
		';

		$tachyselect = "
			,IF(tachythreadpost.userid IS NULL, thread.lastpost, tachythreadpost.lastpost) AS lastpost,
			IF(tachythreadpost.userid IS NULL, thread.lastposter, tachythreadpost.lastposter) AS lastposter,
			IF(tachythreadpost.userid IS NULL, thread.lastposterid, tachythreadpost.lastposterid) AS lastposterid,
			IF(tachythreadpost.userid IS NULL, thread.lastpostid, tachythreadpost.lastpostid) AS lastpostid,
			IF(tachythreadcounter.userid IS NULL, thread.replycount, thread.replycount + tachythreadcounter.replycount) AS replycount
		";
	}
	else
	{
		$tachyjoin = '';
		$tachyselect = '';
	}

	$threadid = intval($threadid);
	if (!isset($threadcache["$threadid"]) OR !$usecache)
	{
		$hook_query_fields = $hook_query_joins = '';
		($hook = vBulletinHook::fetch_hook('fetch_threadinfo_query')) ? eval($hook) : false;

		$marking = ($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid']);
		$threadcache["$threadid"] = $vbulletin->db->query_first("
			SELECT IF(thread.visible = 2, 1, 0) AS isdeleted,
			" . (THIS_SCRIPT == 'postings' ? " deletionlog.userid AS del_userid,
			deletionlog.username AS del_username, deletionlog.reason AS del_reason, deletionlog.dateline AS del_dateline," : "") . "

			" . iif($vbulletin->userinfo['userid'] AND ($vbulletin->options['threadsubscribed'] AND THIS_SCRIPT == 'showthread') OR THIS_SCRIPT == 'editpost' OR THIS_SCRIPT == 'newreply' OR THIS_SCRIPT == 'postings', 'NOT ISNULL(subscribethread.subscribethreadid) AS issubscribed, emailupdate, folderid,')
			  . iif($vbulletin->options['threadvoted'] AND $vbulletin->userinfo['userid'], 'threadrate.vote,')
			  . iif($marking, 'threadread.readtime AS threadread, forumread.readtime AS forumread,') . "
			post.pagetext AS description,
			thread.*
			$tachyselect
			$hook_query_fields
			FROM " . TABLE_PREFIX . "thread AS thread
			" . (THIS_SCRIPT == 'postings' ? "LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (deletionlog.primaryid = thread.threadid AND deletionlog.type = 'thread')" : "") . "
			" . iif($vbulletin->userinfo['userid'] AND ($vbulletin->options['threadsubscribed'] AND THIS_SCRIPT == 'showthread') OR THIS_SCRIPT == 'editpost' OR THIS_SCRIPT == 'newreply' OR THIS_SCRIPT == 'postings', "LEFT JOIN " . TABLE_PREFIX . "subscribethread AS subscribethread ON (subscribethread.threadid = thread.threadid AND subscribethread.userid = " . $vbulletin->userinfo['userid'] . "  AND subscribethread.canview = 1)") . "
			" . iif($vbulletin->options['threadvoted'] AND $vbulletin->userinfo['userid'], "LEFT JOIN " . TABLE_PREFIX . "threadrate AS threadrate ON (threadrate.threadid = thread.threadid AND threadrate.userid = " . $vbulletin->userinfo['userid'] . ")") . "
			" . iif($marking, "
				LEFT JOIN " . TABLE_PREFIX . "threadread AS threadread ON (threadread.threadid = thread.threadid AND threadread.userid = " . $vbulletin->userinfo['userid'] . ")
				LEFT JOIN " . TABLE_PREFIX . "forumread AS forumread ON (forumread.forumid = thread.forumid AND forumread.userid = " . $vbulletin->userinfo['userid'] . ")
			") . "
			LEFT JOIN " . TABLE_PREFIX . "post AS post ON(post.postid = thread.firstpostid)
			$tachyjoin
			$hook_query_joins
			WHERE thread.threadid = $threadid
		");

		$thread =& $threadcache["$threadid"];
		if ($thread['prefixid'])
		{
			$thread['prefix_plain_html'] = htmlspecialchars_uni($vbphrase["prefix_$thread[prefixid]_title_plain"]);
			$thread['prefix_rich'] = $vbphrase["prefix_$thread[prefixid]_title_rich"];
		}
	}

	($hook = vBulletinHook::fetch_hook('fetch_threadinfo')) ? eval($hook) : false;

	return $threadcache["$threadid"];
}

// #############################################################################
/**
* Returns an array contining info for the specified post, or false if post is not found
*
* @param	integer	(ref) Post ID
*
* @return	mixed
*/
function fetch_postinfo(&$postid)
{
	global $vbulletin;
	global $postcache;

	$postid = intval($postid);
	if (!isset($postcache["$postid"]))
	{
		$hook_query_fields = $hook_query_joins = '';
		($hook = vBulletinHook::fetch_hook('fetch_postinfo_query')) ? eval($hook) : false;

		$postcache["$postid"] = $vbulletin->db->query_first("
			SELECT post.*,
			IF(post.visible = 2, 1, 0) AS isdeleted,
			" . (THIS_SCRIPT == 'postings' ? " deletionlog.userid AS del_userid,
			deletionlog.username AS del_username, deletionlog.reason AS del_reason, deletionlog.dateline AS del_dateline," : "") . "

			editlog.userid AS edit_userid, editlog.dateline AS edit_dateline, editlog.reason AS edit_reason, editlog.hashistory
			$hook_query_fields
			FROM " . TABLE_PREFIX . "post AS post
			" . (THIS_SCRIPT == 'postings' ? "LEFT JOIN " . TABLE_PREFIX . "deletionlog AS deletionlog ON (deletionlog.primaryid = post.postid AND deletionlog.type = 'post')" : "") . "
			LEFT JOIN " . TABLE_PREFIX . "editlog AS editlog ON (editlog.postid = post.postid)
			$hook_query_joins
			WHERE post.postid = $postid
		");
	}

	($hook = vBulletinHook::fetch_hook('fetch_postinfo')) ? eval($hook) : false;

	return $postcache["$postid"];
}

// #############################################################################
define('FETCH_USERINFO_AVATAR',     0x02);
define('FETCH_USERINFO_LOCATION',   0x04);
define('FETCH_USERINFO_PROFILEPIC', 0x08);
define('FETCH_USERINFO_ADMIN',      0x10);
define('FETCH_USERINFO_SIGPIC',     0x20);
define('FETCH_USERINFO_USERCSS',    0x40);
define('FETCH_USERINFO_ISFRIEND',   0x80);

/**
* Fetches an array containing info for the specified user, or false if user is not found
*
* Values for Option parameter:
* 1 - Nothing ...
* 2 - Get avatar
* 4 - Process user's online location
* 8 - Join the customprofilpic table to get the userid just to check if we have a picture
* 16 - Join the administrator table to get various admin options
* 32 - Join the sigpic table to get the userid just to check if we have a picture
* 64 - Get user's custom CSS
* 128 - Is the logged in User a friend of this person?
* Therefore: Option = 6 means 'Get avatar' and 'Process online location'
* See fetch_userinfo() in the do=getinfo section of member.php if you are still confused
*
* @param	integer	(ref) User ID
* @param	integer	Bitfield Option (see description)
*
* @return	array	The information for the requested user
*/
function fetch_userinfo(&$userid, $option = 0, $languageid = 0, $nocache = 0, $querymaster = false)
{
	global $vbulletin, $usercache, $vbphrase;

	if ((($userid == $vbulletin->userinfo['userid'] AND $option != 0) OR $nocache) AND isset($usercache["$userid"]))
	{
		// clear the cache if we are looking at ourself and need to add one of the JOINS to our information.
		unset($usercache["$userid"]);
	}

	$userid = intval($userid);

	// return the cached result if it exists
	if (isset($usercache["$userid"]))
	{
		return $usercache["$userid"];
	}

	$hook_query_fields = $hook_query_joins = '';
	($hook = vBulletinHook::fetch_hook('fetch_userinfo_query')) ? eval($hook) : false;

	// no cache available - query the user, run query on master or slave.
	if ($querymaster)
	{
		$queryname = 'query_first';
	}
	else
	{
		$queryname = 'query_first_slave';
	}

	$user = $vbulletin->db->$queryname("
		SELECT " .
			iif(($option & FETCH_USERINFO_ADMIN), ' administrator.*, ') . "
			userfield.*, usertextfield.*, user.*, UNIX_TIMESTAMP(passworddate) AS passworddate, user.languageid AS saved_languageid,
			IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid" .
			iif(($option & FETCH_USERINFO_AVATAR) AND $vbulletin->options['avatarenabled'], ', avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline, customavatar.width AS avwidth, customavatar.height AS avheight, customavatar.height_thumb AS avheight_thumb, customavatar.width_thumb AS avwidth_thumb, customavatar.filedata_thumb').
			iif(($option & FETCH_USERINFO_PROFILEPIC), ', customprofilepic.userid AS profilepic, customprofilepic.dateline AS profilepicdateline, customprofilepic.width AS ppwidth, customprofilepic.height AS ppheight') .
			iif(($option & FETCH_USERINFO_SIGPIC), ', sigpic.userid AS sigpic, sigpic.dateline AS sigpicdateline, sigpic.width AS sigpicwidth, sigpic.height AS sigpicheight') .
			(($option & FETCH_USERINFO_USERCSS) ? ', usercsscache.cachedcss, IF(usercsscache.cachedcss IS NULL, 0, 1) AS hascachedcss, usercsscache.buildpermissions AS cssbuildpermissions' : '') .
			(isset($vbphrase) ? '' : fetch_language_fields_sql()) .
			(($vbulletin->userinfo['userid'] AND ($option & FETCH_USERINFO_ISFRIEND)) ?
				", IF(userlist1.friend = 'yes', 1, 0) AS isfriend, IF (userlist1.friend = 'pending' OR userlist1.friend = 'denied', 1, 0) AS ispendingfriend" .
				", IF(userlist1.userid IS NOT NULL, 1, 0) AS u_iscontact_of_bbuser, IF (userlist2.friend = 'pending', 1, 0) AS requestedfriend" .
				", IF(userlist2.userid IS NOT NULL, 1, 0) AS bbuser_iscontact_of_user" : "") . "
			$hook_query_fields
		FROM " . TABLE_PREFIX . "user AS user
		LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON (user.userid = userfield.userid)
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON (usertextfield.userid = user.userid) " .
		iif(($option & FETCH_USERINFO_AVATAR) AND $vbulletin->options['avatarenabled'], "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON (avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON (customavatar.userid = user.userid) ") .
		iif(($option & FETCH_USERINFO_PROFILEPIC), "LEFT JOIN " . TABLE_PREFIX . "customprofilepic AS customprofilepic ON (user.userid = customprofilepic.userid) ") .
		iif(($option & FETCH_USERINFO_ADMIN), "LEFT JOIN " . TABLE_PREFIX . "administrator AS administrator ON (administrator.userid = user.userid) ") .
		iif(($option & FETCH_USERINFO_SIGPIC), "LEFT JOIN " . TABLE_PREFIX . "sigpic AS sigpic ON (user.userid = sigpic.userid) ") .
		(($option & FETCH_USERINFO_USERCSS) ? 'LEFT JOIN ' . TABLE_PREFIX . 'usercsscache AS usercsscache ON (user.userid = usercsscache.userid)' : '') .
		iif(!isset($vbphrase), "LEFT JOIN " . TABLE_PREFIX . "language AS language ON (language.languageid = " . (!empty($languageid) ? $languageid : "IF(user.languageid = 0, " . intval($vbulletin->options['languageid']) . ", user.languageid)") . ") ") .
		(($vbulletin->userinfo['userid'] AND ($option & FETCH_USERINFO_ISFRIEND)) ?
			"LEFT JOIN " . TABLE_PREFIX . "userlist AS userlist1 ON (userlist1.relationid = user.userid AND userlist1.type = 'buddy' AND userlist1.userid = " . $vbulletin->userinfo['userid'] . ")" .
			"LEFT JOIN " . TABLE_PREFIX . "userlist AS userlist2 ON (userlist2.userid = user.userid AND userlist2.type = 'buddy' AND userlist2.relationid = " . $vbulletin->userinfo['userid'] . ")" : "") . "
		$hook_query_joins
		WHERE user.userid = $userid
	");
	if (!$user)
	{
		return false;
	}

	if (!isset($vbphrase) AND $user['lang_options'] === null)
	{
		trigger_error('The requested language does not exist, reset via tools.php.', E_USER_ERROR);
	}

	$user['languageid'] = (!empty($languageid) ? $languageid : $user['languageid']);

	// decipher 'options' bitfield
	$user['options'] = intval($user['options']);

	foreach ($vbulletin->bf_misc_useroptions AS $optionname => $optionval)
	{
		$user["$optionname"] = ($user['options'] & $optionval ? 1 : 0);
		//DEVDEBUG("$optionname = $user[$optionname]");
	}

	foreach($vbulletin->bf_misc_adminoptions AS $optionname => $optionval)
	{
		$user["$optionname"] = ($user['adminoptions'] & $optionval ? 1 : 0);
	}

	// make a username variable that is safe to pass through URL links
	$user['urlusername'] = urlencode(unhtmlspecialchars($user['username']));

	fetch_musername($user);

	// get the user's real styleid (not the cookie value)
	$user['realstyleid'] = $user['styleid'];

	$user['securitytoken_raw'] = sha1($user['userid'] . sha1($user['salt']) . sha1(COOKIE_SALT));
	$user['securitytoken'] = TIMENOW . '-' . sha1(TIMENOW . $user['securitytoken_raw']);

	$user['logouthash'] =& $user['securitytoken'];

	if ($option & FETCH_USERINFO_LOCATION)
	{ // Process Location info for this user
		require_once(DIR . '/includes/functions_online.php');
		$user = fetch_user_location_array($user);
	}

	($hook = vBulletinHook::fetch_hook('fetch_userinfo')) ? eval($hook) : false;

	$usercache["$userid"] = $user;
	return $usercache["$userid"];
}

// #############################################################################
/**
* Converts the database value of a profilefield and prepares the displayable value as $profilefield['value']
*
* @param	array	Profilefield data (SELECT * FROM profilefield WHERE profilefieldid = $profilefieldid)
* @param	string	Database value of profilefield
*
* @return	array	Profilefield data including 'value' key
*/
function fetch_profilefield_display(&$profilefield, $profilefield_value)
{
	global $vbphrase;

	$profilefield['title'] = $vbphrase["field$profilefield[profilefieldid]_title"];

	if ($profilefield['type'] == 'checkbox' OR $profilefield['type'] == 'select_multiple')
	{
		$data = unserialize($profilefield['data']);
		foreach ($data AS $key => $val)
		{
			if ($profilefield_value & pow(2, $key))
			{
				$profilefield['value'] .= iif($profilefield['value'], ', ') . $val;
			}
		}
	}
	else if ($profilefield['type'] == 'textarea')
	{
		// Convert newlines to <br /> and replace 3+ <br /> with two <br />
		$profilefield['value'] = preg_replace('#(<br />){3,}#', '<br /><br />', nl2br(trim($profilefield_value)));
	}
	else
	{
		$profilefield['value'] = $profilefield_value;
	}

	($hook = vBulletinHook::fetch_hook('member_customfields')) ? eval($hook) : false;

	return $profilefield;
}

// #############################################################################
/**
* Returns the parentlist of the specified forum
*
* @param	integer	Forum ID
*
* @return	string	Comma separated list of parent forum IDs
*/
function fetch_forum_parent_list($forumid)
{
	global $vbulletin;
	static $forumarraycache;

	$forumid = intval($forumid);

	if (isset($vbulletin->forumcache["$forumid"]['parentlist']))
	{
		DEVDEBUG("CACHE parentlist from forum $forumid");
		return $vbulletin->forumcache["$forumid"]['parentlist'];
	}
	else
	{
		if (isset($forumarraycache["$forumid"]))
		{
			return $forumarraycache["$forumid"];
		}
		else
		{
			DEVDEBUG("QUERY parentlist from forum $forumid");
			$foruminfo = $vbulletin->db->query_first_slave("
				SELECT parentlist
				FROM " . TABLE_PREFIX . "forum
				WHERE forumid = $forumid
			");
			$forumarraycache["$forumid"] = $foruminfo['parentlist'];
			return $foruminfo['parentlist'];
		}
	}
}

// #############################################################################
/**
* Returns an SQL condition like (forumid = 1 OR forumid = 2 OR forumid = 3) for each of a forum's parents
*
* @param	integer	Forum ID
* @param	string	The name of the field to be used in the clause
* @param	string	The 'joiner' word - could be 'OR' or 'AND' etc.
* @param	string	The parentlist of the specified forum (comma separated string)
*
* @return	string
*/
function fetch_forum_clause_sql($forumid, $field = 'forumid', $joiner = 'OR', $parentlist = '')
{
	global $vbulletin;

	if (empty($parentlist))
	{
		$parentlist = fetch_forum_parent_list($forumid);
	}

	if (empty($parentlist))
	{
		// prevents an error, and is at least somewhat correct
		$parentlist = '-1,' . intval($forumid);
	}

	if (strtoupper($joiner) == 'OR')
	{
		return "$field IN ($parentlist)";
	}
	else
	{
		return "($field = '" . implode(explode(',', $parentlist), "' $joiner $field = '") . '\')';
	}

}

// #############################################################################
/**
* Multi-purpose function to verify that an item exists and fetch data at the same time
*
* This function works with threads, forums, posts, users and other tables that obey the {item}id, title convention.
* If the data is not found and execution is not halted, a false value will be returned
*
* @param	string	Name of the ID field to be fetched (forumid, threadid etc.)
* @param	integer	(ref) ID of the item to be fetched
* @param	boolean	If true, halt and show error when data is not found
* @param	boolean	If true, 'SELECT *' instead of selecting just the ID field
* @param	integer	Bitfield options to be passed to fetch_userinfo()
*
* @return	mixed
*/
function verify_id($idname, &$id, $alert = true, $selall = false, $options = 0)
{
	// verifies an id number and returns a correct one if it can be found
	// returns 0 if none found
	global $vbulletin, $vbphrase;

	if (empty($vbphrase["$idname"]))
	{
		$vbphrase["$idname"] = $idname;
	}
	$id = intval($id);
	if (empty($id))
	{
		if ($alert)
		{
			eval(standard_error(fetch_error('noid', $vbphrase["$idname"], $vbulletin->options['contactuslink'])));
		}
		else
		{
			return 0;
		}
	}

	$selid = ($selall ? '*' : $idname . 'id');

	switch ($idname)
	{
		case 'thread':
		case 'forum':
		case 'post':
			$function = 'fetch_' . $idname . 'info';
			$tempcache = $function($id);
			if (!$tempcache AND $alert)
			{
				eval(standard_error(fetch_error('invalidid', $vbphrase["$idname"], $vbulletin->options['contactuslink'])));
			}
			return ($selall ? $tempcache : $tempcache[$idname . 'id']);

		case 'user':
			$tempcache = fetch_userinfo($id, $options);
			if (!$tempcache AND $alert)
			{
				eval(standard_error(fetch_error('invalidid', $vbphrase["$idname"], $vbulletin->options['contactuslink'])));
			}
			return ($selall ? $tempcache : $tempcache[$idname . 'id']);

		case 'poll':
			if ($selall)
			{
				$check = $vbulletin->db->query_first("
					SELECT poll.*, thread.threadid
					FROM " . TABLE_PREFIX . "poll AS poll
					INNER JOIN " . TABLE_PREFIX . "thread AS thread ON (thread.pollid = poll.pollid AND thread.open <> 10)
					WHERE poll.pollid = $id
				");
			}
			else
			{
				$check = $vbulletin->db->query_first("
					SELECT poll.pollid
					FROM " . TABLE_PREFIX . "poll AS poll
					WHERE poll.pollid = $id
				");
			}
			if ($alert AND !$check)
			{
				eval(standard_error(fetch_error('invalidid', $vbphrase["$idname"], $vbulletin->options['contactuslink'])));
			}
			else
			{
				return ($selall ? $check : $check['pollid']);
			}
			break;

		default:
			if (!$check = $vbulletin->db->query_first("SELECT $selid FROM " . TABLE_PREFIX . "$idname WHERE $idname" . "id = $id"))
			{
				if ($alert)
				{
					eval(standard_error(fetch_error('invalidid', $vbphrase["$idname"], $vbulletin->options['contactuslink'])));
				}

				return ($selall ? array() : 0);
			}
			else
			{
				return ($selall ? $check : $check["$selid"]);
			}
	}
}

// #############################################################################
/**
* Strips away [quote] tags and their contents from the specified string
*
* @param	string	Text to be stripped of quote tags
*
* @return	string
*/
function strip_quotes($text)
{
	$lowertext = strtolower($text);

	// find all [quote tags
	$start_pos = array();
	$curpos = 0;
	do
	{
		$pos = strpos($lowertext, '[quote', $curpos);
		if ($pos !== false AND ($lowertext[$pos + 6] == '=' OR $lowertext[$pos + 6] == ']'))
		{
			$start_pos["$pos"] = 'start';
		}

		$curpos = $pos + 6;
	}
	while ($pos !== false);

	if (sizeof($start_pos) == 0)
	{
		return $text;
	}

	// find all [/quote] tags
	$end_pos = array();
	$curpos = 0;
	do
	{
		$pos = strpos($lowertext, '[/quote]', $curpos);
		if ($pos !== false)
		{
			$end_pos["$pos"] = 'end';
			$curpos = $pos + 8;
		}
	}
	while ($pos !== false);

	if (sizeof($end_pos) == 0)
	{
		return $text;
	}

	// merge them together and sort based on position in string
	$pos_list = $start_pos + $end_pos;
	ksort($pos_list);

	do
	{
		// build a stack that represents when a quote tag is opened
		// and add non-quote text to the new string
		$stack = array();
		$newtext = '';
		$substr_pos = 0;
		foreach ($pos_list AS $pos => $type)
		{
			$stacksize = sizeof($stack);
			if ($type == 'start')
			{
				// empty stack, so add from the last close tag or the beginning of the string
				if ($stacksize == 0)
				{
					$newtext .= substr($text, $substr_pos, $pos - $substr_pos);
				}
				array_push($stack, $pos);
			}
			else
			{
				// pop off the latest opened tag
				if ($stacksize)
				{
					array_pop($stack);
					$substr_pos = $pos + 8;
				}
			}
		}

		// add any trailing text
		$newtext .= substr($text, $substr_pos);

		// check to see if there's a stack remaining, remove those points
		// as key points, and repeat. Allows emulation of a non-greedy-type
		// recursion.
		if ($stack)
		{
			foreach ($stack AS $pos)
			{
				unset($pos_list["$pos"]);
			}
		}
	}
	while ($stack);

	return $newtext;
}

// #############################################################################
/**
* Strips away bbcode from a given string, leaving plain text
*
* @param	string	Text to be stripped of bbcode tags
* @param	boolean	If true, strip away quote tags AND their contents
* @param	boolean	If true, use the fast-and-dirty method rather than the shiny and nice method
* @param	boolean	If true, display the url of the link in parenthesis after the link text
* @param	boolean	If true, strip away img/video tags and their contents
* @param	boolean	If true, keep [quote] tags. Useful for API.
*
* @return	string
*/
function strip_bbcode($message, $stripquotes = false, $fast_and_dirty = false, $showlinks = true, $stripimg = false, $keepquotetags = false)
{
	$find = array();
	$replace = array();
	$block_elements = array(
		'code',
		'php',
		'html',
		'quote',
		'indent',
		'center',
		'left',
		'right',
		'video',
	);

	if ($stripquotes)
	{
		// [quote=username] and [quote]
		$message = strip_quotes($message);
	}

	if ($stripimg)
	{
		$find[] = '#\[(attach|img|video).*\].+\[\/\\1\]#siU';
		$replace[] = '';
	}

	// a really quick and rather nasty way of removing vbcode
	if ($fast_and_dirty)
	{

		// any old thing in square brackets
		$find[] = '#\[.*/?\]#siU';
		$replace[] = '';

		$message = preg_replace($find, $replace, $message);
	}
	// the preferable way to remove vbcode
	else
	{

		// simple links
		$find[] = '#\[(email|url)=("??)(.+)\\2\]\\3\[/\\1\]#siU';
		$replace[] = '\3';

		// named links
		$find[] = '#\[(email|url)=("??)(.+)\\2\](.+)\[/\\1\]#siU';
		$replace[] = ($showlinks ? '\4 (\3)' : '\4');

		// replace links (and quotes if specified) from message
		$message = preg_replace($find, $replace, $message);

		if ($keepquotetags)
		{
			$regex = '#\[(?!quote)(\w+?)(?>[^\]]*?)\](.*)(\[/\1\])#siU';
		}
		else
		{
			$regex = '#\[(\w+?)(?>[^\]]*?)\](.*)(\[/\1\])#siU';
		}

		// strip out all other instances of [x]...[/x]
		while(preg_match_all($regex, $message, $regs))
		{
			foreach($regs[0] AS $key => $val)
			{
				$message  = str_replace($val, (in_array(strtolower($regs[1]["$key"]), $block_elements) ? "\n" : '') . $regs[2]["$key"], $message);
			}
		}
		$message = str_replace('[*]', ' ', $message);
	}

	return trim($message);
}

// #############################################################################
/**
* Returns a gzip-compressed version of the specified string
*
* @param	string	Text to be gzipped
* @param	integer	Level of Gzip compression (1-10)
*
* @return	string
*/
function fetch_gzipped_text($text, $level = 1)
{
	global $vbulletin;

	$returntext = $text;

	if (function_exists('crc32') AND function_exists('gzcompress') AND !$vbulletin->nozip)
	{
		if (strpos(' ' . $_SERVER['HTTP_ACCEPT_ENCODING'], 'x-gzip') !== false)
		{
			$encoding = 'x-gzip';
		}
		if (strpos(' ' . $_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false)
		{
			$encoding = 'gzip';
		}

		if ($encoding)
		{
			$vbulletin->donegzip = true;
			header('Content-Encoding: ' . $encoding);

			if (false AND function_exists('gzencode'))
			{
				$returntext = gzencode($text, $level);
			}
			else
			{
				$size = strlen($text);
				$crc = crc32($text);

				$returntext = "\x1f\x8b\x08\x00\x00\x00\x00\x00\x00\xff";
				$returntext .= substr(gzcompress($text, $level), 2, -4);
				$returntext .= pack('V', $crc);
				$returntext .= pack('V', $size);
			}
		}
	}
	return $returntext;
}

// #############################################################################
/**
* Checks whether or not any headers have been sent to the browser yet
*
* @param	string	(ref) File name (> PHP 4.3.x)
* @param	integer	(ref) Line number (> PHP 4.3.x)
*
* @return	boolean	True if headers have been sent
*/
function vbheaders_sent(&$filename, &$linenum)
{
	return headers_sent($filename, $linenum);
}

// #############################################################################
/**
* Sets a cookie based on vBulletin environmental settings
*
* @param	string	Cookie name
* @param	mixed	Value to store in the cookie
* @param	boolean	If true, do not set an expiry date for the cookie
* @param	boolean	Allow secure cookies (SSL)
* @param	boolean	Set 'httponly' for cookies in supported browsers
*/
function vbsetcookie($name, $value = '', $permanent = true, $allowsecure = true, $httponly = false)
{
	if (defined('NOCOOKIES'))
	{
		return;
	}

	global $vbulletin;

	if ($permanent)
	{
		$expire = TIMENOW + 60 * 60 * 24 * 365;
	}
	else
	{
		$expire = 0;
	}

	// IE for Mac doesn't support httponly
	$httponly = (($httponly AND (is_browser('ie') AND is_browser('mac'))) ? false : $httponly);

	// check for SSL
	$secure = ((REQ_PROTOCOL === 'https' AND $allowsecure) ? true : false);

	$name = COOKIE_PREFIX . $name;

	$filename = 'N/A';
	$linenum = 0;

	if (!headers_sent($filename, $linenum))
	{ // consider showing an error message if they're not sent using above variables?

		if ($value === '' OR $value === false)
		{
			// this will attempt to unset the cookie at each directory up the path.
			// ie, path to file = /test/vb3/. These will be unset: /, /test, /test/, /test/vb3, /test/vb3/
			// This should hopefully prevent cookie conflicts when the cookie path is changed.

			if ($_SERVER['PATH_INFO'] OR $_ENV['PATH_INFO'])
			{
				$scriptpath = $_SERVER['PATH_INFO'] ? $_SERVER['PATH_INFO'] : $_ENV['PATH_INFO'];
			}
			else if ($_SERVER['REDIRECT_URL'] OR $_ENV['REDIRECT_URL'])
			{
				$scriptpath = $_SERVER['REDIRECT_URL'] ? $_SERVER['REDIRECT_URL'] : $_ENV['REDIRECT_URL'];
			}
			else
			{
				$scriptpath = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_ENV['PHP_SELF'];
			}

			$scriptpath = preg_replace(
				array(
					'#/[^/]+\.php$#i',
					'#/(' . preg_quote($vbulletin->config['Misc']['admincpdir'], '#') . '|' . preg_quote($vbulletin->config['Misc']['modcpdir'], '#') . ')(/|$)#i'
				),
				'',
				$scriptpath
			);

			$dirarray = explode('/', preg_replace('#/+$#', '', $scriptpath));

			$alldirs = '';
			$havepath = false;
			if (!defined('SKIP_AGGRESSIVE_LOGOUT'))
			{
				// sending this many headers has caused problems with a few
				// servers, especially with IIS. Defining SKIP_AGGRESSIVE_LOGOUT
				// reduces the number of cookie headers returned.
				foreach ($dirarray AS $thisdir)
				{
					$alldirs .= "$thisdir";

					if ($alldirs == $vbulletin->options['cookiepath'] OR "$alldirs/" == $vbulletin->options['cookiepath'])
					{
						$havepath = true;
					}

					if (!empty($thisdir))
					{
						// try unsetting without the / at the end
						exec_vbsetcookie($name, $value, $expire, $alldirs, $vbulletin->options['cookiedomain'], $secure, $httponly);
					}

					$alldirs .= "/";
					exec_vbsetcookie($name, $value, $expire, $alldirs, $vbulletin->options['cookiedomain'], $secure, $httponly);
				}
			}

			if ($havepath == false)
			{
				exec_vbsetcookie($name, $value, $expire, $vbulletin->options['cookiepath'], $vbulletin->options['cookiedomain'], $secure, $httponly);
			}
		}
		else
		{
			exec_vbsetcookie($name, $value, $expire, $vbulletin->options['cookiepath'], $vbulletin->options['cookiedomain'], $secure, $httponly);
		}
	}
	else if (empty($vbulletin->db->explain) AND !VB_API)
	{ //show some sort of error message
		global $templateassoc, $vbulletin;
		if (empty($templateassoc))
		{
			// this is being called before templates have been cached, so just get the default one
			$template = $vbulletin->db->query_first_slave("
				SELECT templateid
				FROM " . TABLE_PREFIX . "template
				WHERE title = 'STANDARD_ERROR' AND styleid = -1
			");
			$templateassoc = array('STANDARD_ERROR' => $template['templateid']);
		}
		eval(standard_error(fetch_error('cant_set_cookies', $filename, $linenum)));
	}
}

// #############################################################################
/**
* Calls PHP's setcookie() or sends raw headers if 'httponly' is required.
* Should really only be called through vbsetcookie()
*
* @param	string	Name
* @param	string	Value
* @param	int		Expire
* @param	string	Path
* @param	string	Domain
* @param	boolean	Secure
* @param	boolean	HTTP only - see http://msdn.microsoft.com/workshop/author/dhtml/httponly_cookies.asp
*
* @return	boolean	True on success
*/
function exec_vbsetcookie($name, $value, $expires, $path = '', $domain = '', $secure = false, $httponly = false)
{
	if ($httponly AND $value)
	{
		// cookie names and values may not contain any of the characters listed
		foreach (array(",", ";", " ", "\t", "\r", "\n", "\013", "\014") AS $bad_char)
		{
			if (strpos($name, $bad_char) !== false OR strpos($value, $bad_char) !== false)
			{
				return false;
			}
		}

		// name and value
		$cookie = "Set-Cookie: $name=" . urlencode($value);

		// expiry
		$cookie .= ($expires > 0 ? '; expires=' . gmdate('D, d-M-Y H:i:s', $expires) . ' GMT' : '');

		// path
		$cookie .= ($path ? "; path=$path" : '');

		// domain
		$cookie .= ($domain ? "; domain=$domain" : '');

		// secure
		$cookie .= ($secure ? '; secure' : '');

		// httponly
		$cookie .= ($httponly ? '; HttpOnly' : '');

		header($cookie, false);
		return true;
	}
	else
	{
		return setcookie($name, $value, $expires, $path, $domain, $secure);
	}
}

// #############################################################################
/**
* Returns the value for an array stored in a cookie
*
* @param	string	Name of the cookie
* @param	mixed	ID of the data within the cookie
*
* @return	mixed
*/
function fetch_bbarray_cookie($cookiename, $id)
{
	global $vbulletin;

	$cookie_name = COOKIE_PREFIX . $cookiename; // name of cookie variable
	$cache_name = 'bb_cache_' . $cookiename; // name of cache variable
	global $$cache_name; // internal array for cacheing purposes

	$cookie =& $vbulletin->input->clean_gpc('c', $cookie_name, TYPE_STR);
	$cache =  &$$cache_name;
	if ($cookie != '' AND !isset($cache))
	{
		$cache = @unserialize(convert_bbarray_cookie($cookie));
	}

	if (isset($cache))
	{
		return empty($cache["$id"]) ? null : $cache["$id"];
	}

}

// #############################################################################
/**
* Sets the value for data stored in an array-cookie
*
* @param	string	Name of the cookie
* @param	mixed	ID of the data within the cookie
* @param	mixed	Value for the data
* @param	boolean	If true, make this a permanent cookie
*/
function set_bbarray_cookie($cookiename, $id, $value, $permanent = false)
{
	// sets the value for a array and sets the cookie
	global $vbulletin;

	$cookie_name = COOKIE_PREFIX . $cookiename; // name of cookie variable
	$cache_name = 'bb_cache_' . $cookiename; // name of cache variable
	global $$cache_name; // internal array for cacheing purposes

	$cookie =& $vbulletin->input->clean_gpc('c', $cookie_name, TYPE_STR);
	$cache =& $$cache_name;
	if ($cookie != '' AND !isset($cache))
	{
		$cache = @unserialize(convert_bbarray_cookie($cookie));
	}

	$cache["$id"] = $value;

	vbsetcookie($cookiename, convert_bbarray_cookie(serialize($cache), 'set'), $permanent);

}

// #############################################################################
/**
* Replaces all those none safe characters so we dont waste space in array cookie values with URL entities
*
* @param	string	Cookie array
* @param	string	Direction ('get' or 'set')
*
* @return	array
*/
function convert_bbarray_cookie($cookie, $dir = 'get')
{
	if ($dir == 'set')
	{
		$cookie = str_replace(array('"', ':', ';'), array('.', '-', '_'), $cookie);
		// prefix cookie with 32 character hash
		$cookie = sign_client_string($cookie);
	}
	else
	{
		if (($cookie = verify_client_string($cookie)) !== false)
		{
			$cookie = str_replace(array('.', '-', '_'), array('"', ':', ';'), $cookie);
		}
		else
		{
			$cookie = '';
		}
	}
	return $cookie;
}

// #############################################################################
/**
* Signs a string we intend to pass to the client but don't want them to alter
*
* @param	string	String to be signed
*
* @return	string	MD5 hash followed immediately by the string
*/
function sign_client_string($string, $extra_entropy = '')
{
	if (preg_match('#[\x00-\x1F\x80-\xFF]#s', $string))
	{
		$string = base64_encode($string);
		$prefix = 'B64:';
	}
	else
	{
		$prefix = '';
	}

	return $prefix . sha1($string . sha1(COOKIE_SALT) . $extra_entropy) . $string;
}

// #############################################################################
/**
* Verifies a string return from a client that it has been unaltered
*
* @param	string	String from the client to be verified
*
* @return	string|boolean	String without the verification hash or false on failure
*/
function verify_client_string($string, $extra_entropy = '')
{
	if (substr($string, 0, 4) == 'B64:')
	{
		$firstpart = substr($string, 4, 40);
		$return = substr($string, 44);
		$decode = true;
	}
	else
	{
		$firstpart = substr($string, 0, 40);
		$return = substr($string, 40);
		$decode = false;
	}

	if (sha1($return . sha1(COOKIE_SALT) . $extra_entropy) === $firstpart)
	{
		return ($decode ? base64_decode($return) : $return);
	}

	return false;
}

// #############################################################################
/**
* Verifies a security token is valid
*
* @param	string	Security token from the REQUEST data
* @param	string	Security token used in the hash
*
* @return	boolean	True if the hash matches and is within the correct TTL
*/
function verify_security_token($request_token, $user_token)
{
	global $vbulletin;

	// This is for backwards compatability before tokens had TIMENOW prefixed
	if (strpos($request_token, '-') === false)
	{
		return ($request_token === $user_token);
	}

	list($time, $token) = explode('-', $request_token);

	if ($token !== sha1($time . $user_token))
	{
		return false;
	}

	// A token is only valid for 3 hours
	if ($time <= TIMENOW - 10800)
	{
		$vbulletin->GPC['securitytoken'] = 'timeout';
		return false;
	}

	return true;
}

// #############################################################################
/**
* Reads $bgclass and returns the alternate table class
*
* @param	integer	If > 0, allows us to have multiple classes on one page without them overwriting each other
*
* @return	string	CSS class name
*/
function exec_switch_bg($alternate = 0)
{
	global $bgclass, $altbgclass;
	static $tempclass;

	if ($tempclass != '')
	{
		$bgclass = $tempclass;
		$tempclass = '';
	}

	if ($alternate > 0)
	{
		$varname = 'bgclass' . $alternate;
		global $$varname;

		if ($$varname == 'alt1')
		{
			$$varname = 'alt2';
			$altbgclass = 'alt1';
		}
		else
		{
			$$varname = 'alt1';
			$altbgclass = 'alt2';
		}
		$tempclass = $bgclass;
		$bgclass = $$varname;
	}
	else
	{
		if ($bgclass == 'alt1')
		{
			$bgclass = 'alt2';
			$altbgclass = 'alt1';
		}
		else
		{
			$bgclass = 'alt1';
			$altbgclass = 'alt2';
		}
	}

	return $bgclass;
}

// #############################################################################
/**
* Ensures that the variables for a multi-page display are sane
*
* @return	integer	Maximum posts perpage that a user can see
*/
function sanitize_maxposts($perpage = 0)
{
	global $vbulletin;
	$max = intval(max(explode(',', $vbulletin->options['usermaxposts'])));

	if ($max AND $vbulletin->userinfo['maxposts'])
	{
		if (!$perpage)
		{
			return $vbulletin->userinfo['maxposts'] == -1 ? $vbulletin->options['maxposts'] : $vbulletin->userinfo['maxposts'];
		}
		else if ($perpage == -1)
		{
			return $max;
		}
		else
		{
			return ($perpage > $max ? $max : $perpage);
		}
	}
	else if (!empty($vbulletin->options['maxposts']))
	{
		return $vbulletin->options['maxposts'];
	}
	else
	{
		return 10;
	}
}

// #############################################################################
/**
* Ensures that the variables for a multi-page display are sane
*
* @param	integer	Total number of items to be displayed
* @param	integer	(ref) Current page number
* @param	integer	(ref) Desired number of results to show per-page
* @param	integer	Maximum allowable results to show per-page
* @param	integer	Default number of results to show per-page
*/
function sanitize_pageresults($numresults, &$page, &$perpage, $maxperpage = 20, $defaultperpage = 20)
{
	$perpage = fetch_perpage($perpage, $maxperpage, $defaultperpage);
	$numpages = ceil($numresults / $perpage);
	if ($numpages == 0)
	{
		$numpages = 1;
	}

	if ($page < 1)
	{
		$page = 1;
	}
	else if ($page > $numpages)
	{
		$page = $numpages;
	}
}

/**
* Returns the number of items to display on a page based on a desired value and
* constraints.
*
* If the desired value is not given use the default.  Under no circumstances allow
* a value greater than maxperpage.
*
* @param	integer	Desired number of results to show per-page
* @param	integer	Maximum allowable results to show per-page
* @param	integer	Default number of results to show per-page
* @return actual per page results
*/
function fetch_perpage($perpage, $maxperpage = 20, $defaultperpage = 20)
{
	$perpage = intval($perpage);
	if ($perpage < 1)
	{
		$perpage = $defaultperpage;
	}

	if ($perpage > $maxperpage)
	{
		$perpage = $maxperpage;
	}
	return $perpage;
}

// #############################################################################
/**
* Returns the HTML for multi-page navigation
*
* @param	integer	Page number being displayed
* @param	integer	Number of items to be displayed per page
* @param	integer	Total number of items found
* @param	string	Base address for links eg: showthread.php?t=99{&page=4}
* @param	string	Ending portion of address for links
*
* @return	string	Page navigation HTML
*/
function construct_page_nav($pagenumber, $perpage, $results, $address, $address2 = '', $anchor = '', $seolink = '', $objectinfo = array(), $pageinfo = array())
{
	global $vbulletin, $vbphrase, $show;

	$curpage = 0;
	$pagenavarr = array();
	$firstlink = '';
	$prevlink = '';
	$lastlink = '';
	$nextlink = '';

	if ($results <= $perpage)
	{
		$show['pagenav'] = false;
		return '';
	}

	$show['pagenav'] = true;

	$total = vb_number_format($results);
	$totalpages = ceil($results / $perpage);
	$show['prev'] = false;
	$show['next'] = false;
	$show['first'] = false;
	$show['last'] = false;

	//this function has already been hacked beyond recognition to handle seo urls
	//This change is intended to handle the case where friendly urls are set to
	//"standard urls".  In this case we need to pick up some ids from the url, and
	//pass them into the form (since the form is a get, it throws away anything
	//on the action query string).  Currently this requires passing a value for
	//address using the old style url in addition to the seo information.
	//
	//We don't want to directly use the $objectinfo because it's frequently a
	//large array containing stuff not needed for the url.  This gets the minimum
	//set of items.
	if (!$address AND $seolink)
	{
		//the friendly url basic urls do not work as form values -- they have a segment
		//of the query string that is not a name/value pair and the hidden variables force
		//it into that mold -- which breaks the parsing when the page is reloaded.
		//We fix this by forcing the url that we grab the hidden fields to be the basic
		//non friendly version which will always work.  This is approximately the original
		//behavior of the code, we just centralize it here rather than force the caller to
		//deal with it.
		require_once(DIR . '/includes/class_friendly_url.php');
		$friendlyurl = vB_Friendly_Url::fetchLibrary($vbulletin, $seolink, $objectinfo);
		$address = $friendlyurl->get_url(FRIENDLY_URL_OFF, $canonical);
	}

	$bits = parse_url($address);
	//jumpaddress isn't referenced anywhere.
	//$jumpaddress = $bits['path'];
	$querybits = explode('&amp;', $bits['query'] . $address2);
	$hiddenfields = '';
	if (!empty($querybits))
	{
		foreach ($querybits AS $bit)
		{
			if ($bit)
			{
				$bitinfo = explode('=', $bit);
				$hiddenfields .= "<input type=\"hidden\" name=\"$bitinfo[0]\" value=\"$bitinfo[1]\" />";
			}
		}
	}
	if ($seolink)
	{
		$show['pagelinks'] = false;
		// $jumpaddress = fetch_seo_url($seolink, $objectinfo, $pageinfo);
		if (!empty($pageinfo))
		{
			foreach ($pageinfo AS $name => $value)
			{
				$hiddenfields .= "<input type=\"hidden\" name=\"$name\" value=\"$value\" />";
			}
		}
		$use_qmark = 0;
		$use_amp = 0;
	}
	else
	{
		$firstaddress = $prevaddress = $nextaddress = $lastaddress = $address;
		$show['pagelinks'] = true;
		$use_qmark =  strpos($address, '?') ? 0 : 1;
		$use_amp = $bits['query'] AND !$use_qmark ? 1 : 0;
	}

	if ($pagenumber > 1)
	{
		$prevpage = $pagenumber - 1;
		$prevnumbers = fetch_start_end_total_array($prevpage, $perpage, $results);
		if ($seolink)
		{
			$pageinfo['page'] = $prevpage;
			$prevaddress = fetch_seo_url($seolink, $objectinfo, $pageinfo);
		}
		$show['prev'] = true;
	}
	if ($pagenumber < $totalpages)
	{
		$nextpage = $pagenumber + 1;
		if ($seolink)
		{
			$pageinfo['page'] = $nextpage;
			$nextaddress = fetch_seo_url($seolink, $objectinfo, $pageinfo);
		}
		$nextnumbers = fetch_start_end_total_array($nextpage, $perpage, $results);
		$show['next'] = true;
	}

	// create array of possible relative links that we might have (eg. +10, +20, +50, etc.)
	if (!isset($vbulletin->options['pagenavsarr']))
	{
		$vbulletin->options['pagenavsarr'] = preg_split('#\s+#s', $vbulletin->options['pagenavs'], -1, PREG_SPLIT_NO_EMPTY);
	}


	$pages = array(1, $pagenumber, $totalpages);

	if ($vbulletin->options['pagenavpages'] > 0)
    {
	
	    for ($i = 1; $i <= $vbulletin->options['pagenavpages']; $i++)
	    {
		    $pages[] = $pagenumber + $i;
		    $pages[] = $pagenumber - $i;
	    }

	    foreach ($vbulletin->options['pagenavsarr'] AS $relpage)
	    {
		    $pages[] = $pagenumber + $relpage;
		    $pages[] = $pagenumber - $relpage;
	    }
    }
    else 
    {
        for ($i = 2; $i < $totalpages; $i++)
        {
            $pages[] = $i;
        }
    } 
    
	$show_prior_elipsis = $show_after_elipsis = ($totalpages > $vbulletin->options['pagenavpages']) ? 1 : 0;

	$pages = array_unique($pages);
	sort($pages);

	foreach ($pages AS $foo => $curpage)
	{
		if ($curpage < 1 OR $curpage > $totalpages)
		{
			continue;
		}

		($hook = vBulletinHook::fetch_hook('pagenav_page')) ? eval($hook) : false;

		if ($pagenumber != 1)
		{
			$firstnumbers = fetch_start_end_total_array(1, $perpage, $results);
			if ($seolink)
			{
				unset($pageinfo['page']);
				$firstaddress = fetch_seo_url($seolink, $objectinfo, $pageinfo);
			}
			$show['first'] = true;
		}
		if ($pagenumber != $totalpages)
		{
			$lastnumbers = fetch_start_end_total_array($totalpages, $perpage, $results);
			if ($seolink)
			{
				$pageinfo['page'] = $totalpages;
				$lastaddress = fetch_seo_url($seolink, $objectinfo, $pageinfo);
			}
			$show['last'] = true;
		}

		if (abs($curpage - $pagenumber) >= $vbulletin->options['pagenavpages'] AND $vbulletin->options['pagenavpages'] != 0)
		{
			// generate relative links (eg. +10,etc).
			if (in_array(abs($curpage - $pagenumber), $vbulletin->options['pagenavsarr']) AND $curpage != 1 AND $curpage != $totalpages)
			{
				$pagenumbers = fetch_start_end_total_array($curpage, $perpage, $results);
				$relpage = $curpage - $pagenumber;
				if ($relpage > 0)
				{
					$relpage = '+' . $relpage;
				}
				if ($seolink)
				{
					$pageinfo['page'] = $curpage;
					$address = fetch_seo_url($seolink, $objectinfo, $pageinfo);
					$show['curpage'] = false;
				}
				else
				{
					$show['curpage'] = ($curpage != 1);
				}
				$templater = vB_Template::create('pagenav_pagelinkrel');
					$templater->register('address', $address);
					$templater->register('address2', $address2);
					$templater->register('anchor', $anchor);
					$templater->register('curpage', $curpage);
					$templater->register('pagenumbers', $pagenumbers);
					$templater->register('relpage', $relpage);
					$templater->register('total', $total);
					$templater->register('use_qmark', $use_qmark);
					$templater->register('use_amp', $use_amp);
				$pagenavarr[] = $templater->render();
			}
		}
		else
		{
			//if appropriate, hide the elipses
			if ($curpage == 1)
			{
				$show_prior_elipsis = 0;
			}
			else if ($curpage == $totalpages)
			{
				$show_after_elipsis = 0;
			}
			if ($curpage == $pagenumber)
			{
				$numbers = fetch_start_end_total_array($curpage, $perpage, $results);
				$templater = vB_Template::create('pagenav_curpage');
					$templater->register('curpage', $curpage);
					$templater->register('numbers', $numbers);
					$templater->register('total', $total);
					$templater->register('use_qmark', $use_qmark);
					$templater->register('use_amp', $use_amp);
				$pagenavarr[] = $templater->render();
			}
			else
			{
				if ($seolink)
				{
					$pageinfo['page'] = $curpage;
					$address = fetch_seo_url($seolink, $objectinfo, $pageinfo);
					$show['curpage'] = false;
				}
				else
				{
					$show['curpage'] = ($curpage != 1);
				}
				$pagenumbers = fetch_start_end_total_array($curpage, $perpage, $results);
				$templater = vB_Template::create('pagenav_pagelink');
					$templater->register('address', $address);
					$templater->register('address2', $address2);
					$templater->register('anchor', $anchor);
					$templater->register('curpage', $curpage);
					$templater->register('pagenumbers', $pagenumbers);
					$templater->register('total', $total);
					$templater->register('use_qmark', $use_qmark);
					$templater->register('use_amp', $use_amp);
				$pagenavarr[] = $templater->render();
			}
		}
	}

	if (LANGUAGE_DIRECTION == 'rtl' AND (is_browser('ie') AND is_browser('ie') < 8))
	{
		$pagenavarr = array_reverse($pagenavarr);
	}

	$pagenav = implode('', $pagenavarr);

	($hook = vBulletinHook::fetch_hook('pagenav_complete')) ? eval($hook) : false;

	$templater = vB_Template::create('pagenav');
		$templater->register('address2', $address2);
		$templater->register('address', $address);
		$templater->register('anchor', $anchor);
		$templater->register('firstaddress', $firstaddress);
		$templater->register('firstnumbers', $firstnumbers);
		$templater->register('jumpaddress', $address);
		$templater->register('lastaddress', $lastaddress);
		$templater->register('lastnumbers', $lastnumbers);
		$templater->register('nextaddress', $nextaddress);
		$templater->register('nextnumbers', $nextnumbers);
		$templater->register('nextpage', $nextpage);
		$templater->register('pagenav', $pagenav);
		$templater->register('pagenumber', $pagenumber);
		$templater->register('prevaddress', $prevaddress);
		$templater->register('prevnumbers', $prevnumbers);
		$templater->register('prevpage', $prevpage);
		$templater->register('total', $total);
		$templater->register('totalpages', $totalpages);
		$templater->register('show_prior_elipsis', $show_prior_elipsis);
		$templater->register('show_after_elipsis', $show_after_elipsis);
		$templater->register('hiddenfields', $hiddenfields);
		$templater->register('use_qmark', $use_qmark);
		$templater->register('use_amp', $use_amp);
	$pagenav = $templater->render();
	return $pagenav;
}

/**
* Returns the HTML for multi-page navigation without a fully know result set
*
* This handles multipage navigation when we don't have a count for the
* resultset.  The follow things must be true for this logic to work correctly
*
* 1) $confirmedcount is less than or equal to the total number of results
* 2) if $confirmedcount is not the total number of results, it must be at
* 	least equal to the "count" of the last result displayed in the window.
*
* These assumptions allow us to display the window links knowing that they will
* be valid without knowing the full extent of the result set.
*
* @see fetch_seo_url
* @param	integer	Page number being displayed
* @param	integer Number of pages to show before and after current page
* @param	integer	Number of items to be displayed per page
* @param 	integer	Number of items confirmed in the results
* @param	string	Base address for links eg: showthread.php?t=99{&page=4}
* @param	string	Ending portion of address for links
* @param 	string 	The base link for seo urls (if this is used address will not be)
* @param 	array 	Additonal object info for generating the seo urls
* @param	array 	Additonal page info for generating the seo urls
*
* @todo is it correct to include the pagenav hooks here?  Do we need other hooks
* 	to replace them?
* @return	string	Page navigation HTML
*/
function construct_window_page_nav (
	$pagenumber,
	$window,
	$perpage,
	$confirmedcount,
	$address,
	$address2 = '',
	$anchor = '',
	$seolink = '',
	$objectinfo = '',
	$pageinfo = ''
	)
{
	global $vbulletin, $vbphrase, $show;

	$curpage = 0;
	$pagenavarr = array();
	$firstlink = '';
	$prevlink = '';
	$lastlink = '';
	$nextlink = '';

	if ($confirmedcount <= $perpage)
	{
		$show['pagenav'] = false;
		return '';
	}

	$show['pagenav'] = true;

	$confirmedpages = ceil($confirmedcount / $perpage);

	//window style page navs don't permit "jump to end" logic
	$show['jumppage'] = false;
	$show['last'] = false;

	$show['prev'] = false;
	$show['next'] = false;
	$show['first'] = false;

	$bits = parse_url($address);
	$jumpaddress = $bits['path'];
	$querybits = explode('&amp;', $bits['query'] . $address2);
	$hiddenfields = '';
	if (!empty($querybits))
	{
		foreach ($querybits AS $bit)
		{
			if ($bit)
			{
				$bitinfo = explode('=', $bit);
				$hiddenfields .= "<input type=\"hidden\" name=\"$bitinfo[0]\" value=\"$bitinfo[1]\" />";
			}
		}
	}
	$hiddenfields .= "<input type=\"hidden\" name=\"s\" value=\"" . vB::$vbulletin->session->fetch_sessionhash() . "\" />
			<input type=\"hidden\" name=\"securitytoken\" value=\"" . vB::$vbulletin->userinfo['securitytoken'] .
		"\" />";

	if ($seolink)
	{
		$show['pagelinks'] = false;
		$use_qmark = 0;
		$use_amp = 0;
	}
	else
	{
		$firstaddress = $prevaddress = $nextaddress = $lastaddress = $address;
		$show['pagelinks'] = true;
		$use_qmark =  strpos($address, '?') ? 0 : 1;
		$use_amp = $bits['query'] AND !$use_qmark ? 1 : 0;		
	}

	if ($pagenumber > 1)
	{
		$prevpage = $pagenumber - 1;
		$prevnumbers = fetch_start_end_total_array($prevpage, $perpage, $confirmedcount);
		if ($seolink)
		{
			$pageinfo['page'] = $prevpage;
			$prevaddress = fetch_seo_url($seolink, $objectinfo, $pageinfo);
		}
		$show['prev'] = true;
	}

	if ($pagenumber < $confirmedpages)
	{
		$nextpage = $pagenumber + 1;
		if ($seolink)
		{
			$pageinfo['page'] = $nextpage;
			$nextaddress = fetch_seo_url($seolink, $objectinfo, $pageinfo);
		}
		$nextnumbers = fetch_start_end_total_array($nextpage, $perpage, $confirmedcount);
		$show['next'] = true;
	}

	if (($pagenumber - $window) > 1)
	{
		$firstnumbers = fetch_start_end_total_array(1, $perpage, $confirmedcount);
		if ($seolink)
		{
			unset($pageinfo['page']);
			$firstaddress = fetch_seo_url($seolink, $objectinfo, $pageinfo);
		}
		$show['first'] = true;
	}


	for ($curpage = ($pagenumber - $window); $curpage <= $pagenumber+$window AND $curpage <= $confirmedpages; $curpage++)
	{
		if ($curpage < 1)
		{
			continue;
		}

		else if ($curpage == $pagenumber)
		{
			$numbers = fetch_start_end_total_array($curpage, $perpage, $confirmedcount);

			$templater = vB_Template::create('pagenav_curpage_window');
			$templater->register('curpage', $curpage);
			$templater->register('numbers', $numbers);
			$templater->register('use_qmark', $use_qmark);
			$templater->register('use_amp', $use_amp);
			$templater->register('total', $total);
			$pagenavarr[] = $templater->render();
		}

		else
		{
			if ($seolink)
			{
				$pageinfo['page'] = $curpage;
				$address = fetch_seo_url($seolink, $objectinfo, $pageinfo);
				$show['curpage'] = false;
			}
			else
			{
				$show['curpage'] = ($curpage != 1);
			}
			$pagenumbers = fetch_start_end_total_array($curpage, $perpage, $confirmedcount);

			$templater = vB_Template::create('pagenav_pagelink_window');
			$templater->register('address', $address);
			$templater->register('address2', $address2);
			$templater->register('anchor', $anchor);
			$templater->register('curpage', $curpage);
			$templater->register('pagenumbers', $pagenumbers);
			$templater->register('total', $total);
			$templater->register('use_qmark', $use_qmark);
			$templater->register('use_amp', $use_amp);
			$pagenavarr[] = $templater->render();
		}
	}

	if (LANGUAGE_DIRECTION == 'rtl' AND (is_browser('ie') AND is_browser('ie') < 8))
	{
		$pagenavarr = array_reverse($pagenavarr);
	}

	$pagenav = implode('', $pagenavarr);

	$templater = vB_Template::create('pagenav_window');
	$templater->register('address2', $address2);
	$templater->register('anchor', $anchor);
	$templater->register('firstaddress', $firstaddress);
	$templater->register('firstnumbers', $firstnumbers);
	$templater->register('jumpaddress', $address);
	$templater->register('lastaddress', $lastaddress);
	$templater->register('lastnumbers', $lastnumbers);
	$templater->register('nextaddress', $nextaddress);
	$templater->register('nextnumbers', $nextnumbers);
	$templater->register('nextpage', $nextpage);
	$templater->register('pagenav', $pagenav);
	$templater->register('pagenumber', $pagenumber);
	$templater->register('prevaddress', $prevaddress);
	$templater->register('prevnumbers', $prevnumbers);
	$templater->register('prevpage', $prevpage);
	$templater->register('total', $total);
	$templater->register('totalpages', $confirmedpages);
	$templater->register('use_qmark', $use_qmark);
	$templater->register('use_amp', $use_amp);
	$templater->register('hiddenfields', $hiddenfields);

	$pagenav = $templater->render();
	return $pagenav;
}

// #############################################################################
/**
* Returns an array so you can print 'Showing results $arr[first] to $arr[last] of $totalresults'
*
* @param	integer	Current page number
* @param	integer	Results to show per-page
* @param	integer	Total results found
*
* @return	array	In the format of - array('first' => x, 'last' => y)
*/
function fetch_start_end_total_array($pagenumber, $perpage, $total)
{
	$first = $perpage * ($pagenumber - 1);
	$last = $first + $perpage;

	if ($last > $total)
	{
		$last = $total;
	}
	$first++;

	return array('first' => vb_number_format($first), 'last' => vb_number_format($last));
}

// #############################################################################
/**
* Returns the HTML for the navigation breadcrumb in the navbar
*
* This function will also set the GLOBAL $pagetitle to equal whatever is the last item in the navbits
*
* @param	array	Array of link => title pairs from which to build the link chain
* @param 	boolean Whether to include the forum breadcrumb
* @return	string
*/
function construct_navbits($nav_array)
{
	global $pagetitle, $vbulletin, $vbphrase, $show;

	// VB API doesn't require rendering navbar.
	if (defined('VB_API') AND VB_API === true)
	{
		return array();
	}

	$code = array(
		'breadcrumb' => '',
		'lastelement' => ''
	);

	$lastelement = sizeof($nav_array);
	$counter = 0;

	if (is_array($nav_array))
	{
		foreach($nav_array AS $nav_url => $nav_title)
		{
			$pagetitle = $nav_title;

			$elementtype = (++$counter == $lastelement) ? 'lastelement' : 'breadcrumb';
			$show['breadcrumb'] = ($elementtype == 'breadcrumb');

			if (empty($nav_title))
			{
				continue;
			}

			$skip_nav_entry = false;
			($hook = vBulletinHook::fetch_hook('navbits')) ? eval($hook) : false;
			if ($skip_nav_entry)
			{
				continue;
			}

			$templater = vB_Template::create('navbar_link');
				$templater->register('nav_title', $nav_title);
				$templater->register('nav_url', $nav_url);
			$code["$elementtype"] .= $templater->render();
		}
	}

	($hook = vBulletinHook::fetch_hook('navbits_complete')) ? eval($hook) : false;

	return $code;
}

/**
* Renders the navbar template with the specified navbits
*
* @param	array	Array of navbit information
*
* @return	string	Navbar HTML
*/
function render_navbar_template($navbits)
{
	global $vbulletin;

	// VB API doesn't require rendering navbar.
	if (defined('VB_API') AND VB_API === true)
	{
		return true;
	}

	$templater = vB_Template::create('navbar');

	// Resolve the root segment
	$templater->register('bbmenu', $vbulletin->options['bbmenu']);

	$templater->register('ad_location', $GLOBALS['ad_location']);
	$templater->register('foruminfo', $GLOBALS['foruminfo']);
	$templater->register('navbar_reloadurl', $GLOBALS['navbar_reloadurl']);
	$templater->register('navbits', $navbits);
	$templater->register('notices', $GLOBALS['notices']);
	$templater->register('notifications_menubits', $GLOBALS['notifications_menubits']);
	$templater->register('notifications_total', $GLOBALS['notifications_total']);
	$templater->register('pmbox', $GLOBALS['pmbox']);
	$templater->register('return_link', $GLOBALS['return_link']);
	$templater->register('template_hook', $GLOBALS['template_hook']);

	return $templater->render();
}

/**
* Renders the option template. Simply a helper method to shorten the length of code to do this.
*
* @param	string	Title of the option
* @param	string	Value of the option (sent to the server on submission)
* @param	string	If selected, should be string: selected="selected"
* @param	string	A class to apply to the option
*
* @return	string	Option HTML
*/
function render_option_template($optiontitle, $optionvalue, $optionselected = '', $optionclass = '')
{
	$templater = vB_Template::create('option');

	$templater->register('optionclass', $optionclass);
	$templater->register('optionselected', $optionselected);
	$templater->register('optiontitle', $optiontitle);
	$templater->register('optionvalue', $optionvalue);

	return $templater->render();
}

/**
* Extracts member information for the action drop-down from the postinfo array
*
* @param	array	post information
*
* @return	array	member information
*/
function fetch_lastposter_userinfo($lastpostinfo)
{
	global $show, $vbulletin;

	// use all the information thats already in lastpostinfo
	$memberinfo = $lastpostinfo;

	// calculate any pertinent missing info for member action drop-down
	$useroptions = $lastpostinfo['useroptions'];
	$memberinfo['receivepm'] = $useroptions & $vbulletin->bf_misc_regoptions['enablepm'];
	$memberinfo['userid'] = $lastpostinfo['lastposterid'];
	$memberinfo['username'] = $lastpostinfo['lastposter'];
	$memberinfo['showemail'] = (isset($memberinfo['showemail']) ? $memberinfo['showemail'] : false);
	$memberinfo['online'] = (isset($memberinfo['online']) ? $memberinfo['online'] : 'offline');

	return $memberinfo;
}

/**
* Returns the HTML for the member dwop-down pop-up menu
*
* @param	array	user information for the drop-down context
* @param	array	template hook, if we dont want to use the global one (like in postbit)
* @param	string	class name to apply to the div for context specific stylings
*
* @return	string	Member Drop-Down HTML
*/
function construct_memberaction_dropdown($memberinfo, $template_hook = array(), $page_class = null)
{
	global $show, $vbulletin;

	$memberperm = cache_permissions($memberinfo, false);

	// display the private messgage link?
	$show['pmlink']=
	(
		$vbulletin->options['enablepms']
			AND
		$vbulletin->userinfo['permissions']['pmquota']
			AND
		(
			$vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']
				OR
			($memberinfo['receivepm'] AND $memberperm['pmquota'])
		)
	);

	// display the user's homepage link?
	$show['homepage'] = ($memberinfo['homepage'] != '' AND $memberinfo['homepage'] != 'http://');

	// display the add as friend link?
	$show['addfriend']=
	(
		$vbulletin->options['socnet'] & $vbulletin->bf_misc_socnet['enable_friends']
		AND $vbulletin->userinfo['userid']
		AND $memberinfo['userid'] != $vbulletin->userinfo['userid']
		AND $vbulletin->userinfo['permissions']['genericpermissions2'] & $vbulletin->bf_ugp_genericpermissions2['canusefriends']
		AND $memberperm['genericpermissions2'] & $vbulletin->bf_ugp_genericpermissions2['canusefriends']
		AND !$memberinfo['isfriend']
	);

	// Check if blog is installed, and show link if so
	$show['viewblog'] = $vbulletin->products['vbblog'];

	// Check if CMS is installed, and show link if so
	$show['viewarticles'] = $vbulletin->products['vbcms'];

	// display the email link?
	$show['emaillink'] = (
		$memberinfo['showemail'] AND $vbulletin->options['displayemails'] AND
		(
			!$vbulletin->options['secureemail']
				OR
			($vbulletin->options['secureemail'] AND $vbulletin->options['enableemail'])
		)
		AND $vbulletin->userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canemailmember']
		AND $vbulletin->userinfo['userid']
	);

	if (!$memberinfo['onlinestatusphrase'])
	{
		require_once(DIR . '/includes/functions_bigthree.php');
		fetch_online_status($memberinfo);
	}

	// execute memberaction hook
	($hook = vBulletinHook::fetch_hook('memberaction_dropdown')) ? eval($hook) : false;

	$templater = vB_Template::create('memberaction_dropdown');
	$templater->register('memberinfo', $memberinfo);
	$templater->register('template_hook', $template_hook);
	if (!empty($page_class))
	{
		$templater->register('page_class', $page_class);
	}

	return $templater->render();
}

// #############################################################################
/**
* Construct Phrase
*
* this function is actually just a wrapper for sprintf but makes identification of phrase code easier
* and will not error if there are no additional arguments. The first parameter is the phrase text, and
* the (unlimited number of) following parameters are the variables to be parsed into that phrase.
*
* @param	string	Text of the phrase
* @param	mixed	First variable to be inserted
* ..		..		..
* @param	mixed	Nth variable to be inserted
*
* @return	string	The parsed phrase
*/
function construct_phrase()
{
	$args = func_get_args();
	$numargs = sizeof($args);

	// FAIL SAFE: check if only parameter is an array,
	// if so we should have called construct_phrase_from_array instead
	if ($numargs == 1 AND is_array($args[0]))
	{
		return construct_phrase_from_array($args[0]);
	}

	// if this function was called with the phrase as the first argument, and an array
	// of paramters as the second, combine into single array for construct_phrase_from_array
	else if ($numargs == 2 AND is_string($args[0]) AND is_array($args[1]))
	{
		array_unshift($args[1], $args[0]);
		return construct_phrase_from_array($args[1]);
	}

	// otherwise just package arguments up as an array
	// and call the array version of this func
	else
	{
		return construct_phrase_from_array($args);
	}
}

/**
* Construct Phrase from Array
*
* this function is actually just a wrapper for sprintf but makes identification of phrase code easier
* and will not error if there are no additional arguments. The first element of the array is the phrase text, and
* the (unlimited number of) following elements are the variables to be parsed into that phrase.
*
* @param	array	array containing phrase and arguments
*
* @return	string	The parsed phrase
*/
function construct_phrase_from_array($phrase_array)
{
	$numargs = sizeof($phrase_array);

	// if we have only one argument then its a phrase
	// with no variables, so just return it
	if ($numargs < 2)
	{
		return $phrase_array[0];
	}

	// call sprintf() on the first argument of this function
	$phrase = @call_user_func_array('sprintf', $phrase_array);
	if ($phrase !== false)
	{
		return $phrase;
	}
	else
	{
		// if that failed, add some extra arguments for debugging
		for ($i = $numargs; $i < 10; $i++)
		{
			$phrase_array["$i"] = "[ARG:$i UNDEFINED]";
		}
		if ($phrase = @call_user_func_array('sprintf', $phrase_array))
		{
			return $phrase;
		}
		// if it still doesn't work, just return the un-parsed text
		else
		{
			return $phrase_array[0];
		}
	}
}

// #############################################################################
/**
* Returns 2 lines of eval()-able code -- one sets $message, the other $subject.
*
* @param	string	Name of email phrase to fetch
* @param	integer	Language ID from which to pull the phrase (see fetch_phrase $languageid)
* @param	string	If not empty, select the subject phrase with the given name
* @param	string	Optional prefix  for $message/$subject variable names (eg: $varprefix = 'test' -> $testmessage, $testsubject)
*
* @return	string
*/
function fetch_email_phrases($email_phrase, $languageid = -1, $emailsub_phrase = '', $varprefix = '')
{
	if (empty($emailsub_phrase))
	{
		$emailsub_phrase = $email_phrase;
	}

	if (!function_exists('fetch_phrase'))
	{
		require_once(DIR . '/includes/functions_misc.php');
	}

	return
		'$' . $varprefix . 'message = "' . fetch_phrase($email_phrase, 'emailbody', 'email_', true, iif($languageid >= 0, true, ''), $languageid, false) . '";' .
		'$' . $varprefix . 'subject = "' . fetch_phrase($emailsub_phrase, 'emailsubject', 'emailsubject_', true, iif($languageid >= 0, true, ''), $languageid, false) . '";';
}

// #############################################################################
/**
* Converts an array of error values to error strings by calling the fetch_error
* function.
*
* @param	Array(mixed) Errors to compile.  Values can either be a string, which is taken
* 	be be the error varname or it can be an array in which case it is viewed as
*		a list of parameters to pass to fetch_error
*
* @return	Array(string)	The array of compiled error messages.  Keys are preserved.
*/
function fetch_error_array($errors)
{
	$compiled_errors = array();
	foreach ($errors as $key => $value)
	{
		if (is_string($value))
		{
			$compiled_errors[$key] = fetch_error($value);
		}
		else if (is_array($value))
		{
			$compiled_errors[$key] = call_user_func_array('fetch_error', $value);
		}
	}

	return $compiled_errors;
}

// #############################################################################
/**
* Fetches an error phrase from the database and inserts values for its embedded variables
*
* @param	string	Varname of error phrase
* @param	mixed	Value of 1st variable
* @param	mixed	Value of 2nd variable
* @param	mixed	Value of Nth variable
*
* @return	string	The parsed phrase text
*/
function fetch_error()
{
	global $vbulletin;

	$args = func_get_args();

	// Allow an array of phrase and variables to be passed in as arg0 (for some internal functions)
	if (is_array($args[0]))
	{
		$args = $args[0];
	}

	if (class_exists('vBulletinHook', false))
	{
		($hook = vBulletinHook::fetch_hook('error_fetch')) ? eval($hook) : false;
	}

	if (!function_exists('fetch_phrase') AND !VB_API)
	{
		require_once(DIR . '/includes/functions_misc.php');
	}

	if ($vbulletin->GPC['ajax'])
	{
		switch ($args[0])
		{
			case 'invalidid':
			case 'nopermission_loggedin':
			case 'forumpasswordmissing':
				$args[0] = $args[0] . '_ajax';
		}
	}

	// API only needs error phrase name and args.
	if (defined('VB_API') AND VB_API === true)
	{
		return $args;
	}

	$args[0] = fetch_phrase($args[0], 'error', '', false);
	if (sizeof($args) > 1)
	{
		return call_user_func_array('construct_phrase', $args);
	}
	else
	{
		return $args[0];
	}
}

// #############################################################################
/**
* Halts execution and shows an error message stating that the visitor does not have permission to view the page
*/
function print_no_permission()
{
	global $vbulletin, $vbphrase;

	require_once(DIR . '/includes/functions_misc.php');

	$vbulletin->userinfo['badlocation'] = 1; // Used by exec_shut_down();

	($hook = vBulletinHook::fetch_hook('error_nopermission')) ? eval($hook) : false;

	$usergroupid = $vbulletin->userinfo['usergroupid'];

	if (!($vbulletin->usergroupcache["$usergroupid"]['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']))
	{
		$reason = $vbulletin->db->query_first_slave("
			SELECT reason, liftdate
			FROM " . TABLE_PREFIX . "userban
			WHERE userid = " . $vbulletin->userinfo['userid']
		);

		// Check for a date or a perm ban
		if ($reason['liftdate'])
		{
			$date = vbdate($vbulletin->options['dateformat'] . ', ' . $vbulletin->options['timeformat'], $reason['liftdate']);
		}
		else
		{
			$date = $vbphrase['never'];
		}

		if (!$reason['reason'])
		{
			$reason['reason'] = fetch_phrase('no_reason_specified', 'error');
		}

		eval(standard_error(fetch_error('nopermission_banned', $reason['reason'], $date)));
	}
	else if ($vbulletin->userinfo['userid'] AND !empty($vbulletin->userinfo['infractiongroupids']))
	{
		$date = $vbphrase['never'];

		$infractiongroupids = explode(',', str_replace(' ', '', $vbulletin->userinfo['infractiongroupids']));
		$bannedgroups = array();
		foreach ($infractiongroupids AS $usergroupid)
		{
			if (!($vbulletin->usergroupcache["$usergroupid"]['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']))
			{
				$bannedgroups["$usergroupid"] = $usergroupid;
			}
		}

		if (!empty($bannedgroups))
		{
			$points = $vbulletin->userinfo['ipoints'];
			$infractions = $vbulletin->db->query_read("
				SELECT points, expires
				FROM " . TABLE_PREFIX . "infraction
				WHERE userid = " . $vbulletin->userinfo['userid'] . "
					AND action = 0
					AND expires <> 0
					AND points <> 0
				ORDER BY expires ASC
			");

			if ($vbulletin->db->num_rows($infractions))
			{
				$infractiongroups = array();
				$groups = $vbulletin->db->query_read("
					SELECT orusergroupid, pointlevel
					FROM " . TABLE_PREFIX . "infractiongroup
					WHERE usergroupid IN (-1, " . $vbulletin->userinfo['usergroupid'] . ")
						AND pointlevel <= " . $vbulletin->userinfo['ipoints'] . "
					ORDER BY pointlevel
				");
				while ($group = $vbulletin->db->fetch_array($groups))
				{
					$infractiongroups[] = $group;
				}

				$foundbanned = true;
				while ($foundbanned AND $infraction = $vbulletin->db->fetch_array($infractions))
				{
					// Decremement user points as they would be when this infraction expires
					$foundbanned = false;
					$points -= $infraction['points'];
					foreach($infractiongroups AS $key => $group)
					{
						if ($points < $group['pointlevel'])
						{
							continue;
						}
						else if (!($vbulletin->usergroupcache["$group[orusergroupid]"]['genericoptions'] & $vbulletin->bf_ugp_genericoptions['isnotbannedgroup']))
						{
							$foundbanned = true;
						}
					}
				}

				if (!$foundbanned)
				{	// This is when we will be "unbanned"
					$date = vbdate($vbulletin->options['dateformat'] . ', ' . $vbulletin->options['timeformat'], $infraction['expires']);
				}

				eval(standard_error(fetch_error('nopermission_banned_infractions', $date)));
			}
		}
	}

	if ($vbulletin->userinfo['userid'])
	{
		eval(standard_error(fetch_error('nopermission_loggedin',
			$vbulletin->userinfo['username'],
			vB_Template_Runtime::fetchStyleVar('right'),
			$vbulletin->session->vars['sessionurl'],
			$vbulletin->userinfo['securitytoken'],
			fetch_seo_url('forumhome', array())
		)));
	}
	else
	{
		define('VB_ERROR_PERMISSION', true);
		eval(standard_error(fetch_error('nopermission_loggedout')));
	}
}

// #############################################################################
/**
* Returns eval()-able code to initiate a standard error
*
* @deprecated	Deprecated since 3.5. Use standard_error(fetch_error(...)) instead.
*
* @param	string	Name of error phrase
* @param	boolean	If false, use the name of error phrase as the phrase text itself
* @param	boolean	If true, set the visitor's status on WOL to error page
*
* @return	string
*/
function print_standard_error($err_phrase, $doquery = true, $savebadlocation = true)
{
	die("<h1><em>print_standard_error(...)</em><br />is now redundant. Instead, use<br /><em>standard_error(fetch_error(...))</em></h1>");

	if ($doquery)
	{
		if (!function_exists('fetch_phrase'))
		{
			require_once(DIR . '/includes/functions_misc.php');
		}

		return 'standard_error("' . fetch_phrase($err_phrase, 'error', 'error_') . "\", '', " . intval($savebadlocation) . ");";
	}
	else
	{
		return 'standard_error("' . $err_phrase . "\", '', " . intval($savebadlocation) . ");";
	}
}

// #############################################################################
/**
* Halts execution and shows the specified error message
*
* @param	string	Error message
* @param	string	Optional HTML code to insert in the <head> of the error page
* @param	boolean	If true, set the visitor's status on WOL to error page
* @param	string	Optional template to force the display to use. Ignored if showing a lite error
*/
function standard_error($error = '', $headinsert = '', $savebadlocation = true, $override_template = '')
{
	global $header, $footer, $headinclude, $forumjump, $timezone, $gobutton;
	global $vbulletin, $vbphrase, $template_hook;
	global $pmbox, $show, $ad_location, $notifications_menubits, $notifications_total;

	$show['notices'] = false;

	construct_quick_nav(array(), -1, true, true);

	$title = $vbulletin->options['bbtitle'];
	$pagetitle =& $title;
	$errormessage = $error;

	if (!$vbulletin->userinfo['badlocation'] AND $savebadlocation)
	{
		$vbulletin->userinfo['badlocation'] = 3;
	}
	require_once(DIR . '/includes/functions_misc.php');
	if ($_POST['securitytoken'] OR $vbulletin->GPC['postvars'])
	{
	$postvars = construct_post_vars_html();
		if ($vbulletin->GPC['postvars'])
		{
			$_postvars = @unserialize(verify_client_string($vbulletin->GPC['postvars']));
			if ($_postvars['securitytoken'] == 'guest')
			{
				unset($_postvars);
			}
		}
		else if ($_POST['securitytoken'] == 'guest')
		{
			unset($postvars);
		}
	}
	else
	{
		$postvars = '';
	}

	if (defined('VB_ERROR_PERMISSION') AND VB_ERROR_PERMISSION == true)
	{
		$show['permission_error'] = true;
	}
	else
	{
		$show['permission_error'] = false;
	}

	$show['search_noindex'] = (bool)($vbulletin->userinfo['permissions']['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview']);

	$navbits = $navbar = '';
	if (defined('VB_ERROR_LITE') AND VB_ERROR_LITE == true)
	{
		$templatename = 'STANDARD_ERROR_LITE';
		define('NOPMPOPUP', 1); // No Footer here
	}
	else
	{
		// bug 33454: we used to not register the navbar when users did not have general forum permissions (banned)
		// but that was causing display issues with the vb4 style, so now we always render navbar
		$navbits = construct_navbits(array('' => $vbphrase['vbulletin_message']));
		$navbar = render_navbar_template($navbits);

		$templatename = ($override_template ? preg_replace('#[^a-z0-9_]#i', '', $override_template) : 'STANDARD_ERROR');
	}

	($hook = vBulletinHook::fetch_hook('error_generic')) ? eval($hook) : false;

	if ($vbulletin->GPC['ajax'])
	{
		require_once(DIR . '/includes/class_xml.php');
		$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');
		$xml->add_tag('error', $errormessage);
		$xml->print_xml();
		exit;
	}
	else
	{
		if ($vbulletin->noheader)
		{
			@header('Content-Type: text/html' . ($vbulletin->userinfo['lang_charset'] != '' ? '; charset=' . $vbulletin->userinfo['lang_charset'] : ''));
		}

		$templater = vB_Template::create($templatename);
			$templater->register_page_templates();
			$templater->register('errormessage', $errormessage);
			$templater->register('forumjump', $forumjump);
			$templater->register('headinsert', $headinsert);
			$templater->register('navbar', $navbar);
			$templater->register('pagetitle', $pagetitle);
			$templater->register('postvars', $postvars);
			$templater->register('scriptpath', SCRIPTPATH);
			$templater->register('url', $vbulletin->url);
		print_output($templater->render());
	}
}

// #############################################################################
/**
* Returns eval()-able code to initiate a standard redirect
*
* The global variable $url should contain the URL target for the redirect
*
* @param	string	Name of redirect phrase
* @param	boolean	If false, use the name of redirect phrase as the phrase text itself
* @param	boolean	Whether or not to force a redirect message to be shown
* @param	integer	Language ID to fetch the phrase from (-1 uses the page-wide default)
* @param	bool	Force bypass of domain whitelist check
*
* @return	string
*/
function print_standard_redirect($redir_phrase, $doquery = true, $forceredirect = false, $languageid = -1, $bypasswhitelist = false)
{
	if (!VB_API)
	{
		if ($doquery)
		{
			if (!function_exists('fetch_phrase'))
			{
				require_once(DIR . '/includes/functions_misc.php');
			}

			$phrase = fetch_phrase($redir_phrase, 'frontredirect', 'redirect_', true, false, $languageid, false);
			// addslashes run in fetch_phrase
		}
		else
		{
			$phrase = addslashes($redir_phrase);
		}
	}
	else
	{
		$phrase = $redir_phrase;
	}

	return 'standard_redirect("' . $phrase . '", ' . intval($forceredirect) . ', ' . ($bypasswhitelist ? 'true' : 'false') . ');';
}

// #############################################################################
/**
* Halts execution and redirects to the address specified
*
* If the 'useheaderredirect' option is on, the system will attempt to redirect invisibly using header('Location...
* However, 'useheaderredirect' is overridden by setting $forceredirect to a true value.
*
* @param	string	Redirect message
* @param	string	URL to which to redirect the browser
* @param	bool	Force bypass of domain whitelist check
*/
function standard_redirect($message = '', $forceredirect = false, $bypasswhitelist = false)
{
	global $header, $footer, $headinclude, $headinclude_bottom, $forumjump;
	global $timezone, $vbulletin, $vbphrase;

	static
		$str_find     = array('"',      '<',    '>'),
		$str_replace  = array('&quot;', '&lt;', '&gt;');

	if ($vbulletin->db->explain)
	{
		$totaltime = microtime(true) - TIMESTART;

		$vartext .= "<!-- Page generated in " . vb_number_format($totaltime, 5) . " seconds with " . $vbulletin->db->querycount . " queries -->";

		$querytime = $vbulletin->db->time_total;
		echo "\n<b>Page generated in $totaltime seconds with " . $vbulletin->db->querycount . " queries,\nspending $querytime doing MySQL queries and " . ($totaltime - $querytime) . " doing PHP things.\n\n<hr />Shutdown Queries:</b>" . (defined('NOSHUTDOWNFUNC') ? " <b>DISABLED</b>" : '') . "<hr />\n\n";
		exit;
	}

	if ($vbulletin->url AND !$bypasswhitelist AND !$vbulletin->options['redirect_whitelist_disable'])
	{
		$foundurl = false;
		if ($urlinfo = @parse_url($vbulletin->url))
		{
			if (!$urlinfo['scheme'])
			{	// url is made full in exec_header_redirect which stops a url from being redirected to, say "www.php.net" (no http://)
				$foundurl = true;
			}
			else
			{
				$whitelist = array();
				if ($vbulletin->options['redirect_whitelist'])
				{
					$whitelist = explode("\n", trim($vbulletin->options['redirect_whitelist']));
				}
				// Add $bburl to the whitelist
				$bburlinfo = @parse_url($vbulletin->options['bburl']);
				$bburl = "{$bburlinfo['scheme']}://{$bburlinfo['host']}";
				array_unshift($whitelist, $bburl);

				// if the "realurl" of this request does not equal $bburl, add it as well..
				$realurl = VB_URL_SCHEME . '://' . VB_URL_HOST;
				if (strtolower($bburl) != strtolower($realurl))
				{
					array_unshift($whitelist, $realurl);
				}

				$vburl = strtolower($vbulletin->url);
				foreach ($whitelist AS $url)
				{
					$url = trim($url);
					if ($vburl == strtolower($url) OR strpos($vburl, strtolower($url) . '/', 0) === 0)
					{
						$foundurl = true;
						break;
					}
				}
			}
		}
		
		if (!$foundurl)
		{
			eval(standard_error(fetch_error('invalid_redirect_url_x', $vbulletin->url)));
		}
	}

	if ($vbulletin->options['useheaderredirect'] AND !$forceredirect AND !headers_sent() AND !$vbulletin->GPC['postvars'] AND !VB_API)
	{
		exec_header_redirect(unhtmlspecialchars($vbulletin->url, true));
	}

	$title = $vbulletin->options['bbtitle'];

	$pagetitle = $title;
	$errormessage = $message;

	$url = unhtmlspecialchars($vbulletin->url, true);
	$url = str_replace(chr(0), '', $url);
	$url = create_full_url($url);
	$url = str_replace($str_find, $str_replace, $url);
	$js_url = addslashes_js($url, '"'); // " has been replaced by &quot;

	$url = preg_replace(
		array('/&#0*59;?/', '/&#x0*3B;?/i', '#;#'),
		'%3B',
		$url
	);
	$url = preg_replace('#&amp%3B#i', '&amp;', $url);

	define('NOPMPOPUP', 1); // No footer here

	require_once(DIR . '/includes/functions_misc.php');
	$postvars = construct_hidden_var_fields(verify_client_string($vbulletin->GPC['postvars']));
	$formfile =& $url;

	($hook = vBulletinHook::fetch_hook('redirect_generic')) ? eval($hook) : false;

	$templater = vB_Template::create('STANDARD_REDIRECT');
		$templater->register('errormessage', $errormessage);
		$templater->register('formfile', $formfile);
		$templater->register('headinclude', $headinclude);
		$templater->register('headinclude_bottom', $headinclude_bottom);
		$templater->register('js_url', $js_url);
		$templater->register('pagetitle', $pagetitle);
		$templater->register('postvars', $postvars);
		$templater->register('url', $url);
	print_output($templater->render());
	exit;
}

// #############################################################################
/**
* Halts execution and redirects to the specified URL invisibly
*
* @param	string	Destination URL
*/
function exec_header_redirect($url, $redirectcode = 302)
{
	global $vbulletin;

	// VB API modification
	if (defined('VB_API') AND VB_API === true)
	{
		eval(print_standard_redirect('header_redirect', true, true));
	}

	$url = create_full_url($url);

	if (class_exists('vBulletinHook', false))
	{
		// this can be called when we don't have the hook class
		($hook = vBulletinHook::fetch_hook('header_redirect')) ? eval($hook) : false;
	}

	$url = str_replace('&amp;', '&', $url); // prevent possible oddity

	if (strpos($url, "\r\n") !== false)
	{
		trigger_error("Header may not contain more than a single header, new line detected.", E_USER_ERROR);
	}

	header("Location: $url", 0, $redirectcode);

	if ($vbulletin->options['addheaders'] AND (SAPI_NAME == 'cgi' OR SAPI_NAME == 'cgi-fcgi'))
	{
		// see #24779
		switch($redirectcode)
		{
			case 301:
				header('Status: 301 Moved Permanently');
			case 302:
				header('Status: 302 Found');
				break;
		}
	}

	define('NOPMPOPUP', 1);
	if (defined('NOSHUTDOWNFUNC'))
	{
		exec_shut_down();
	}
	exit;
}

// #############################################################################
/**
* Translates a relative URL to a fully-qualified URL. URLs not beginning with
* a / are assumed to be within the main vB-directory
*
* @param	string	Relative URL
* @param  string  Always use the bburl setting as the base path regardless of admin options.
* 	(Unless already an absolute path).  Primarily used in the archives where its currently
* 	hardcoded.
*
* @param	string	Fully-qualified URL
*/
function create_full_url($url = '', $force_bburl = false)
{
	global $vbulletin;

	// enforces HTTP 1.1 compliance
	if (!preg_match('#^[a-z]+(?<!about|javascript|vbscript|data)://#i', $url))
	{
		if ('/' == $url{0})
		{
			$url = VB_URL_WEBROOT . $url;
		}
		else if (VB_URL_SCHEME)
		{
			$url = $vbulletin->input->fetch_relpath($url);

			if ($force_bburl)
			{
				$base = $vbulletin->options['bburl'] . "/";
			}
			//these areas depend on the redirection being done against the VB_URL_BASE_PATH path explicitly.
			else if (in_array(VB_AREA, array("Install", "Upgrade", "AdminCP", "ModCP", "Archive")))
			{
				$base = VB_URL_BASE_PATH;
			}
			else
			{
				$base = $vbulletin->input->fetch_basepath();
			}
			$url = $base . ltrim($url, '/\\');
		}
	}
	else
	{
		$url = $vbulletin->input->xss_clean_url($url);
	}

	// Collapse ../ and ./
	$url = normalize_path($url);

	return $url;
}


/**
 * Adds a query string to a path, fixing the query characters.
 *
 * @param 	string		The path to add the query to
 * @return	string		The resulting string
 */
function add_query($path, $query = false)
{
	if (false === $query)
	{
		$query = VB_URL_QUERY;
	}

	if (!$query OR !($query = trim($query, '?&')))
	{
		return $path;
	}

	return $path . '?' . $query;
}


/**
 * Collapses ../ and ./ in a path.
 *
 * @param	string		The path to normalize
 * @return	string		The nromalized path
 */
function normalize_path($path)
{
	// Collapse ../
	$path = preg_replace('#\w+\/\.\.\/#', '', $path);

	// Collapse ./
	$path = preg_replace('#\/\.\/#', '/', $path);

	return $path;
}


// #############################################################################
/**
* Fetches a number of templates from the database and puts them into the templatecache
*
* @param	array	List of template names to be fetched
* @param	string	Serialized array of template name => template id pairs
* @param	bool	Whether to skip adding the bbcode style refs
*/
function cache_templates($templates, $templateidlist, $skip_bbcode_style = false)
{
	global $vbulletin, $templateassoc;

	if (empty($templateassoc))
	{
		$templateassoc = unserialize($templateidlist);
	}

	if ($vbulletin->options['legacypostbit'] AND in_array('postbit', $templates))
	{
		$templateassoc['postbit'] = $templateassoc['postbit_legacy'];
	}

	foreach ($templates AS $template)
	{
		$templateids[] = intval($templateassoc["$template"]);
	}

	if (!empty($templateids))
	{
		// run query
		$temps = $vbulletin->db->query_read_slave("
			SELECT title, template
			FROM " . TABLE_PREFIX . "template
			WHERE templateid IN (" . implode(',', $templateids) . ")
		");

		// cache templates
		while ($temp = $vbulletin->db->fetch_array($temps))
		{
			if (empty($vbulletin->templatecache["$temp[title]"]))
			{
				$vbulletin->templatecache["$temp[title]"] = $temp['template'];
			}
		}
		$vbulletin->db->free_result($temps);
	}

	if (!$skip_bbcode_style)
	{
		$vbulletin->bbcode_style = array(
				'code'  => &$templateassoc['bbcode_code_styleid'],
				'html'  => &$templateassoc['bbcode_html_styleid'],
				'php'   => &$templateassoc['bbcode_php_styleid'],
				'quote' => &$templateassoc['bbcode_quote_styleid']
		);
	}
}

// #############################################################################
/**
* Returns a single template from the templatecache or the database
*
* @param	string	Name of template to be fetched
* @param	integer	Escape quotes in template? 1: escape template; -1: unescape template; 0: do nothing
* @param	boolean	Wrap template in HTML comments showing the template name?
*
*	TODO Delete this function
*
* @return	string
*/
function fetch_template($templatename, $escape = 0, $gethtmlcomments = true)
{
	// gets a template from the db or from the local cache
	global $vbulletin, $tempusagecache, $templateassoc;

	trigger_error('fetch_template() calls should be replaced by the vB_Template class. Template name: ' . htmlspecialchars($templatename), E_USER_WARNING);

	// use legacy postbit if necessary
	if ($vbulletin->options['legacypostbit'] AND $templatename == 'postbit')
	{
		$templatename = 'postbit_legacy';
	}

	global $bootstrap;
	if (!empty($bootstrap) AND !$bootstrap->called('template'))
	{
		$bootstrap->process_templates();
	}

	if (isset($vbulletin->templatecache["$templatename"]))
	{
		$template = $vbulletin->templatecache["$templatename"];
	}
	else
	{
		vB_Template::$template_queries[$templatename] = true;

		$fetch_tid = intval($templateassoc["$templatename"]);
		if (!$fetch_tid)
		{
			$gettemp = array('template' => '');
		}
		else
		{
			$gettemp = $vbulletin->db->query_first_slave("
				SELECT template
				FROM " . TABLE_PREFIX . "template
				WHERE templateid = $fetch_tid
			");
		}
		$template = $gettemp['template'];
		$vbulletin->templatecache["$templatename"] = $template;
	}

	switch($escape)
	{
		case 1:
			// escape template
			$template = addslashes($template);
			$template = str_replace("\\'", "'", $template);
			break;

		case -1:
			// unescape template
			$template = stripslashes($template);
			break;
	}

	if (!isset(vB_Template::$template_usage[$templatename]))
	{
		vB_Template::$template_usage[$templatename] = 1;
	}
	else
	{
		vB_Template::$template_usage[$templatename]++;
	}

	if ($vbulletin->options['addtemplatename'] AND $gethtmlcomments)
	{
		$templatename = preg_replace('#[^a-z0-9_]#i', '', $templatename);
		return "<!-- BEGIN TEMPLATE: $templatename -->\n$template\n<!-- END TEMPLATE: $templatename -->";
	}

	return $template;
}

// #############################################################################
/**
* Gets counter information and makes sure the forums are in the proper order
* for tree iteration. Changes will be made to the forum cache directly.
*
* @param	boolean	Whether or not to get the forum counter info
* @param	boolean	Whether to include invisible forums in the liast
* @param	integer	ID of the user that subscribed forums should be fetched for
*/
function cache_ordered_forums($getcounters = 0, $getinvisibles = 0, $userid = 0)
{
	global $vbulletin;

	// query forum table to get latest lastpost/lastthread info and counters
	if ($getcounters)
	{
		if ($vbulletin->userinfo['userid'] AND in_coventry($vbulletin->userinfo['userid'], true))
		{
			$tachyjoin = "
				LEFT JOIN " . TABLE_PREFIX . "tachyforumpost AS tachyforumpost ON
					(tachyforumpost.forumid = forum.forumid AND tachyforumpost.userid = " . $vbulletin->userinfo['userid'] . ")
				LEFT JOIN " . TABLE_PREFIX . "tachyforumcounter AS tachyforumcounter ON
					(tachyforumpost.forumid = forum.forumid AND tachyforumpost.userid = " . $vbulletin->userinfo['userid'] . ")
			";

			$counter_select = '
				forum.forumid,
				IF(tachyforumpost.userid IS NULL, forum.lastpost, tachyforumpost.lastpost) AS lastpost,
				IF(tachyforumpost.userid IS NULL, forum.lastposter, tachyforumpost.lastposter) AS lastposter,
				IF(tachyforumpost.userid IS NULL, forum.lastposterid, tachyforumpost.lastposterid) AS lastposterid,
				IF(tachyforumpost.userid IS NULL, forum.lastthread, tachyforumpost.lastthread) AS lastthread,
				IF(tachyforumpost.userid IS NULL, forum.lastthreadid, tachyforumpost.lastthreadid) AS lastthreadid,
				IF(tachyforumpost.userid IS NULL, forum.lasticonid, tachyforumpost.lasticonid) AS lasticonid,
				IF(tachyforumpost.userid IS NULL, forum.lastpostid, tachyforumpost.lastpostid) AS lastpostid,
				IF(tachyforumpost.userid IS NULL, forum.lastprefixid, tachyforumpost.lastprefixid) AS lastprefixid,
				IF(tachyforumcounter.userid IS NULL, forum.threadcount, forum.threadcount + tachyforumcounter.threadcount) AS threadcount,
				IF(tachyforumcounter.userid IS NULL, forum.replycount, forum.replycount + tachyforumcounter.replycount) AS replycount
			';
		}
		else
		{
			$tachyjoin = '';
			$counter_select = 'forum.forumid, forum.lastpost, forum.lastposter, forum.lastposterid, forum.lastthread, forum.lastthreadid, forum.lasticonid, forum.threadcount, forum.replycount, forum.lastpostid, forum.lastprefixid';
		}

		($hook = vBulletinHook::fetch_hook('cache_ordered_forums')) ? eval($hook) : false;

		// get subscribed forums too
		if ($userid)
		{
			$query = "
				SELECT subscribeforumid, $counter_select, user.usergroupid, user.homepage, user.options AS useroptions, IF(userlist.friend = 'yes', 1, 0) AS isfriend,
					user.lastactivity, user.lastvisit, IF(user.options & " . $vbulletin->bf_misc_useroptions['invisible'] . ", 1, 0) AS invisible
					". iif($vbulletin->options['threadmarking'], ', forumread.readtime AS forumread') . "
				FROM " . TABLE_PREFIX . "forum AS forum
				LEFT JOIN " . TABLE_PREFIX . "subscribeforum AS subscribeforum ON (subscribeforum.forumid = forum.forumid AND subscribeforum.userid = $userid)
				" . iif($vbulletin->options['threadmarking'], " LEFT JOIN " . TABLE_PREFIX . "forumread AS forumread ON (forumread.forumid = forum.forumid AND forumread.userid = $userid)") . "
				LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = forum.lastposterid)
				LEFT JOIN " . TABLE_PREFIX . "userlist AS userlist ON (userlist.relationid = user.userid AND userlist.type = 'buddy' AND userlist.userid = " . $vbulletin->userinfo['userid'] . ")
				$tachyjoin
			";
		}
		// just get counters
		else
		{
			$query = "
				SELECT $counter_select, user.usergroupid, user.homepage, user.options AS useroptions, IF(userlist.friend = 'yes', 1, 0) AS isfriend,
					user.lastactivity, user.lastvisit, IF(user.options & " . $vbulletin->bf_misc_useroptions['invisible'] . ", 1, 0) AS invisible
					". iif($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'], ', forumread.readtime AS forumread') . "
				FROM " . TABLE_PREFIX . "forum AS forum
				" . iif($vbulletin->options['threadmarking'] AND $vbulletin->userinfo['userid'], " LEFT JOIN " . TABLE_PREFIX . "forumread AS forumread ON (forumread.forumid = forum.forumid AND forumread.userid = " .  $vbulletin->userinfo['userid'] . ")") . "
				LEFT JOIN " . TABLE_PREFIX . "user AS user ON (user.userid = forum.lastposterid)
				LEFT JOIN " . TABLE_PREFIX . "userlist AS userlist ON (userlist.relationid = user.userid AND userlist.type = 'buddy' AND userlist.userid = " . $vbulletin->userinfo['userid'] . ")
				$tachyjoin
			";
		}
	}
		// get subscribed forums
	else if ($userid)
	{
		$query = "
			SELECT subscribeforumid, forumid
			FROM " . TABLE_PREFIX . "subscribeforum
			WHERE userid = $userid
		";
	}
	// don't bother to query forum table, just use the cache
	else
	{
		$query = null;
	}

	if ($query !== null)
	{
		$db =& $vbulletin->db;

		$getthings = $db->query_read_slave($query);
		if ($db->num_rows($getthings))
		{
			while ($getthing = $db->fetch_array($getthings))
			{
				if (empty($vbulletin->forumcache["$getthing[forumid]"]))
				{
					$vbulletin->forumcache["$getthing[forumid]"] = $getthing;
				}
				else
				{
					// this adds the existing cache to $getthing without overwriting
					// any of $getthing's keys
					$vbulletin->forumcache["$getthing[forumid]"] = $getthing + $vbulletin->forumcache["$getthing[forumid]"];
				}
			}
		}
	}

	$vbulletin->iforumcache = array();

	foreach ($vbulletin->forumcache AS $forumid => $forum)
	{
		if ((!$forum['displayorder'] OR !($forum['options'] & $vbulletin->bf_misc_forumoptions['active'])) AND !$getinvisibles)
		{
			continue;
		}
		$forum['parentid'] = intval($forum['parentid']);
		$vbulletin->iforumcache["$forum[parentid]"]["$forumid"] = $forumid;
	}
}

// #############################################################################
/**
* Returns the HTML for the forum jump menu
*
* @param	array		Information about current page (id, title, and link)
* @param	integer	ID of the parent forum for the group to be shown (-1 for all)
* @param	boolean	If true, evaluate the forumjump template too
* @param	boolean	Override $complete
*/
function construct_quick_nav($navpopup = array(), $parentid = -1, $addbox = true, $override = false)
{
	global $vbulletin, $daysprune, $vbphrase, $forumjump;
	static $complete = false;

	if ($override)
	{
		$complete = false;
	}

	if (!$navpopup['id'])
	{
		$navpopup['id'] = 'navpopup';
	}

	if ($complete OR !$vbulletin->options['useforumjump'] OR !($vbulletin->userinfo['permissions']['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview']))
	{
		return;
	}

	if (empty($vbulletin->iforumcache))
	{
		// get the vbulletin->iforumcache, as we use it all over the place, not just for forumjump
		cache_ordered_forums(0, 1);
	}

	if (empty($vbulletin->iforumcache["$parentid"]) OR !is_array($vbulletin->iforumcache["$parentid"]))
	{
		return;
	}

	foreach($vbulletin->iforumcache["$parentid"] AS $forumid)
	{
		$forumperms = $vbulletin->userinfo['forumpermissions']["$forumid"];
		if ((!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) AND ($vbulletin->forumcache["$forumid"]['showprivate'] == 1 OR (!$vbulletin->forumcache["$forumid"]['showprivate'] AND !$vbulletin->options['showprivateforums']))) OR !($vbulletin->forumcache["$forumid"]['options'] & $vbulletin->bf_misc_forumoptions['showonforumjump']) OR !$vbulletin->forumcache["$forumid"]['displayorder'] OR !($vbulletin->forumcache["$forumid"]['options'] & $vbulletin->bf_misc_forumoptions['active']))
		{
			continue;
		}
		else
		{
			// set $forum from the $vbulletin->forumcache
			$forum = $vbulletin->forumcache["$forumid"];

			$children = explode(',', trim($forum['childlist']));
			if (sizeof($children) <= 2)
			{
				$jumpforumbits[] = array('type' => 'link', 'forum' => $forum);
			}
			else
			{
				 if ($forumbits = construct_quick_nav($navpopup, $forumid, false))
				 {
				 	$forum['depth']++;
				  	$jumpforumbits[] = array('type' => 'subforum_begin', 'forum' => $forum);
				  	$jumpforumbits = array_merge($jumpforumbits, $forumbits);
				  	$jumpforumbits[] = array('type' => 'subforum_end');
				 }
				 else
				 {
				  	$jumpforumbits[] = array('type' => 'link', 'forum' => $forum);
				 }
			}

		} // if can view
	} // end foreach ($vbulletin->iforumcache[$parentid] AS $forumid)

	if ($addbox)
	{
		($hook = vBulletinHook::fetch_hook('forumjump')) ? eval($hook) : false;

		$templater = vB_Template::create('forumjump');
			if ($daysprune)
			{
				$templater->register('daysprune', intval($daysprune));
			}
			$templater->register('jumpforumbits', $jumpforumbits);
			$templater->register('navpopup', $navpopup);
		$forumjump = $templater->render();

		// prevent forumjump from being built more than once
		$complete = true;
	}
	else
	{
		return $jumpforumbits;
	}
}

// #############################################################################
/**
* Sets various time and date related variables according to visitor's preferences
*
* Sets $timediff, $datenow, $timenow, $copyrightyear
*/
function fetch_time_data()
{
	global $vbulletin, $timediff, $datenow, $timenow, $copyrightyear;

	$vbulletin->userinfo['tzoffset'] = $vbulletin->userinfo['timezoneoffset']; // preserve timzoneoffset for profile editing and proper event display

	if ($vbulletin->userinfo['dstonoff'])
	{
		// DST is on, add an hour
		$vbulletin->userinfo['tzoffset']++;

		if (substr($vbulletin->userinfo['tzoffset'], 0, 1) != '-')
		{
			// recorrect so that it has + sign, if necessary
			$vbulletin->userinfo['tzoffset'] = '+' . $vbulletin->userinfo['tzoffset'];
		}
	}

	// some stuff for the gmdate bug
	$vbulletin->options['hourdiff'] = (date('Z', TIMENOW) / 3600 - $vbulletin->userinfo['tzoffset']) * 3600;

	if ($vbulletin->userinfo['tzoffset'])
	{
		if ($vbulletin->userinfo['tzoffset'] > 0 AND strpos($vbulletin->userinfo['tzoffset'], '+') === false)
		{
			$vbulletin->userinfo['tzoffset'] = '+' . $vbulletin->userinfo['tzoffset'];
		}
		if (abs($vbulletin->userinfo['tzoffset']) == 1)
		{
			$timediff = ' ' . $vbulletin->userinfo['tzoffset'] . ' hour';
		}
		else
		{
			$timediff = ' ' . $vbulletin->userinfo['tzoffset'] . ' hours';
		}
	}
	else
	{
		$timediff = '';
	}

	$datenow       = vbdate($vbulletin->options['dateformat'], TIMENOW);
	$timenow       = vbdate($vbulletin->options['timeformat'], TIMENOW);
	$copyrightyear = vbdate('Y', TIMENOW, false, false);
}

// #############################################################################
/**
* Formats a UNIX timestamp into a human-readable string according to vBulletin prefs
*
* Note: Ifvbdate() is called with a date format other than than one in $vbulletin->options[],
* set $locale to false unless you dynamically set the date() and strftime() formats in the vbdate() call.
*
* @param	string	Date format string (same syntax as PHP's date() function)
* @param	integer	Unix time stamp
* @param	boolean	If true, attempt to show strings like "Yesterday, 12pm" instead of full date string
* @param	boolean	If true, and user has a language locale, use strftime() to generate language specific dates
* @param	boolean	If true, don't adjust time to user's adjusted time .. (think gmdate instead of date!)
* @param	boolean	If true, uses gmstrftime() and gmdate() instead of strftime() and date()
* @param array    If set, use specified info instead of $vbulletin->userinfo
*
* @return	string	Formatted date string
*/
function vbdate($format, $timestamp = TIMENOW, $doyestoday = false, $locale = true, $adjust = true, $gmdate = false, $userinfo = '')
{
	global $vbulletin, $vbphrase;
	$uselocale = false;

	if (defined('VB_API') AND VB_API === true)
	{
		$doyestoday = false;
	}

	if (is_array($userinfo) AND !empty($userinfo))
	{
		if ($userinfo['lang_locale'])
		{
			$uselocale = true;
			$currentlocale = setlocale(LC_TIME, 0);
			setlocale(LC_TIME, $userinfo['lang_locale']);
			if (substr($userinfo['lang_locale'], 0, 5) != 'tr_TR')
			{
				setlocale(LC_CTYPE, $userinfo['lang_locale']);
			}
		}
		if ($userinfo['dstonoff'])
		{
			// DST is on, add an hour
			$userinfo['timezoneoffset']++;
			if (substr($userinfo['timezoneoffset'], 0, 1) != '-')
			{
				// recorrect so that it has a + sign, if necessary
				$userinfo['timezoneoffset'] = '+' . $userinfo['timezoneoffset'];
			}
		}
		$hourdiff = (date('Z', TIMENOW) / 3600 - $userinfo['timezoneoffset']) * 3600;
	}
	else
	{
		$hourdiff = $vbulletin->options['hourdiff'];
		if ($vbulletin->userinfo['lang_locale'])
		{
			$uselocale = true;
		}
	}

	if ($uselocale AND $locale)
	{
		if ($gmdate)
		{
			$datefunc = 'gmstrftime';
		}
		else
		{
			$datefunc = 'strftime';
		}
	}
	else
	{
		if ($gmdate)
		{
			$datefunc = 'gmdate';
		}
		else
		{
			$datefunc = 'date';
		}
	}
	if (!$adjust)
	{
		$hourdiff = 0;
	}
	$timestamp_adjusted = max(0, $timestamp - $hourdiff);

	if ($format == $vbulletin->options['dateformat'] AND $doyestoday AND $vbulletin->options['yestoday'])
	{
		if ($vbulletin->options['yestoday'] == 1)
		{
			if (!defined('TODAYDATE'))
			{
				define ('TODAYDATE', vbdate('n-j-Y', TIMENOW, false, false));
				define ('YESTDATE', vbdate('n-j-Y', TIMENOW - 86400, false, false));
				define ('TOMDATE', vbdate('n-j-Y', TIMENOW + 86400, false, false));
			}

			$datetest = @date('n-j-Y', $timestamp - $hourdiff);

			if ($datetest == TODAYDATE)
			{
				$returndate = $vbphrase['today'];
			}
			else if ($datetest == YESTDATE)
			{
				$returndate = $vbphrase['yesterday'];
			}
			else
			{
				$returndate = $datefunc($format, $timestamp_adjusted);
			}
		}
		else
		{
			$timediff = TIMENOW - $timestamp;

			if ($timediff >= 0)
			{
				if ($timediff < 120)
				{
					$returndate = $vbphrase['1_minute_ago'];
				}
				else if ($timediff < 3600)
				{
					$returndate = construct_phrase($vbphrase['x_minutes_ago'], intval($timediff / 60));
				}
				else if ($timediff < 7200)
				{
					$returndate = $vbphrase['1_hour_ago'];
				}
				else if ($timediff < 86400)
				{
					$returndate = construct_phrase($vbphrase['x_hours_ago'], intval($timediff / 3600));
				}
				else if ($timediff < 172800)
				{
					$returndate = $vbphrase['1_day_ago'];
				}
				else if ($timediff < 604800)
				{
					$returndate = construct_phrase($vbphrase['x_days_ago'], intval($timediff / 86400));
				}
				else if ($timediff < 1209600)
				{
					$returndate = $vbphrase['1_week_ago'];
				}
				else if ($timediff < 3024000)
				{
					$returndate = construct_phrase($vbphrase['x_weeks_ago'], intval($timediff / 604900));
				}
				else
				{
					$returndate = $datefunc($format, $timestamp_adjusted);
				}
			}
			else
			{
				$returndate = $datefunc($format, $timestamp_adjusted);
			}
		}
	}
	else
	{
		$returndate = $datefunc($format, $timestamp_adjusted);
	}

	if (!empty($userinfo['lang_locale']))
	{
		setlocale(LC_TIME, $currentlocale);
		if (substr($currentlocale, 0, 5) != 'tr_TR')
		{
			setlocale(LC_CTYPE, $currentlocale);
		}
	}
	return $returndate;
}

// #############################################################################
/**
* Returns a string where HTML entities have been converted back to their original characters
*
* @param	string	String to be parsed
* @param	boolean	Convert unicode characters back from HTML entities?
*
* @return	string
*/
function unhtmlspecialchars($text, $doUniCode = false)
{
	if ($doUniCode)
	{
		$text = preg_replace('/&#([0-9]+);/esiU', "convert_int_to_utf8('\\1')", $text);
	}

	return str_replace(array('&lt;', '&gt;', '&quot;', '&amp;'), array('<', '>', '"', '&'), $text);
}

// #############################################################################
/**
 * Checks if PCRE supports unicode
 *
 * @return bool
 */
function is_pcre_unicode()
{
	static $enabled;

	if (NULL !== $enabled)
	{
		return $enabled;
	}

	return $enabled = @preg_match('#\pN#u', '1');
}

/**
* Converts an integer into a UTF-8 character string
*
* @param	integer	Integer to be converted
*
* @return	string
*/
function convert_int_to_utf8($intval)
{
	$intval = intval($intval);
	switch ($intval)
	{
		// 1 byte, 7 bits
		case 0:
			return chr(0);
		case ($intval & 0x7F):
			return chr($intval);

		// 2 bytes, 11 bits
		case ($intval & 0x7FF):
			return chr(0xC0 | (($intval >> 6) & 0x1F)) .
				chr(0x80 | ($intval & 0x3F));

		// 3 bytes, 16 bits
		case ($intval & 0xFFFF):
			return chr(0xE0 | (($intval >> 12) & 0x0F)) .
				chr(0x80 | (($intval >> 6) & 0x3F)) .
				chr (0x80 | ($intval & 0x3F));

		// 4 bytes, 21 bits
		case ($intval & 0x1FFFFF):
			return chr(0xF0 | ($intval >> 18)) .
				chr(0x80 | (($intval >> 12) & 0x3F)) .
				chr(0x80 | (($intval >> 6) & 0x3F)) .
				chr(0x80 | ($intval & 0x3F));
	}
}

// #############################################################################
/**
* Converts Unicode entities of the format %uHHHH where each H is a hexadecimal
* character to &#DDDD; or the appropriate UTF-8 character based on current charset.
*
* @param	Mixed		array or text
*
* @return	string	Decoded text
*/
function convert_urlencoded_unicode($text)
{
	if (is_array($text))
	{
		foreach ($text AS $key => $value)
		{
			$text["$key"] = convert_urlencoded_unicode($value);
		}
		return $text;
	}

	if (!($charset = vB_Template_Runtime::fetchStyleVar('charset')))
	{
		global $vbulletin;
		$charset = $vbulletin->userinfo['lang_charset'];
	}

	$return = preg_replace(
		'#%u([0-9A-F]{1,4})#ie',
		"convert_unicode_char_to_charset(hexdec('\\1'), \$charset)",
		$text
	);

	$lower_charset = strtolower($charset);

	if ($lower_charset != 'utf-8' AND function_exists('html_entity_decode'))
	{
		// this converts certain &#123; entities to their actual character
		// set values; don't do this if using UTF-8 as it's already done above.
		// note: we don't want to convert &gt;, etc as that undoes the effects of STR_NOHTML
		$return = preg_replace('#&([a-z]+);#i', '&amp;$1;', $return);

		if ($lower_charset == 'windows-1251')
		{
			// there's a bug in PHP5 html_entity_decode that decodes some entities that
			// it shouldn't. So double encode them to ensure they don't get decoded.
			$return = preg_replace('/&#(128|129|1[3-9][0-9]|2[0-4][0-9]|25[0-5]);/', '&amp;#$1;', $return);
		}

		$return = @html_entity_decode($return, ENT_NOQUOTES, $charset);
	}

	return $return;
}

/**
* Converts a single unicode character to the desired character set if possible.
* Attempts to use iconv if it's available.
* Callback function for the regular expression in convert_urlencoded_unicode.
*
* @param	integer	Unicode code point value
* @param	string	Character to convert to
*
* @return	string	Character in desired character set or as an HTML entity
*/
function convert_unicode_char_to_charset($unicode_int, $charset)
{
	$is_utf8 = (strtolower($charset) == 'utf-8');

	if ($is_utf8)
	{
		return convert_int_to_utf8($unicode_int);
	}

	if (function_exists('iconv'))
	{
		// convert this character -- if unrepresentable, it should fail
		$output = @iconv('UTF-8', $charset, convert_int_to_utf8($unicode_int));
		if ($output !== false AND $output !== '')
		{
			return $output;
		}
	}

	return "&#$unicode_int;";
}

/**
* Poor man's urlencode that only encodes specific characters and preserves unicode.
* Use urldecode() to decode.
*
* @param	string	String to encode
* @return	string	Encoded string
*/
function urlencode_uni($str)
{
	return preg_replace(
		'`([\s/\\\?:@=+$,<>\%"\'\.\r\n\t\x00-\x1f\x7f]|(?(?<!&)#|#(?![0-9]+;))|&(?!#[0-9]+;)|(?<!&#\d|&#\d{2}|&#\d{3}|&#\d{4}|&#\d{5});)`e',
		"urlencode('\\1')",
		$str
	);
}

/**
 * Converts a string to utf8
 *
 * @param	string	The variable to clean
 * @param	string	The source charset
 * @param	bool	Whether to strip invalid utf8 if we couldn't convert
 * @return	string	The reencoded string
 */
function to_utf8($in, $charset = false, $strip = true)
{
	if ('' === $in OR false === $in OR is_null($in))
	{
		return $in;
	}

	// Fallback to UTF-8
	if (!$charset)
	{
		$charset = 'UTF-8';
	}

	// Try iconv
	if (function_exists('iconv'))
	{
		$out = @iconv($charset, 'UTF-8//IGNORE', $in);
		return $out;
	}

	// Try mbstring
	if (function_exists('mb_convert_encoding'))
	{
		return @mb_convert_encoding($in, 'UTF-8', $charset);
	}

	if (!$strip)
	{
		return $in;
	}

	// Strip non valid UTF-8
	// TODO: Do we really want to do this?
	$utf8 = '#([\x09\x0A\x0D\x20-\x7E]' .			# ASCII
			'|[\xC2-\xDF][\x80-\xBF]' .				# non-overlong 2-byte
			'|\xE0[\xA0-\xBF][\x80-\xBF]' .			# excluding overlongs
			'|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}' .	# straight 3-byte
			'|\xED[\x80-\x9F][\x80-\xBF]' .			# excluding surrogates
			'|\xF0[\x90-\xBF][\x80-\xBF]{2}' .		# planes 1-3
			'|[\xF1-\xF3][\x80-\xBF]{3}' .			# planes 4-15
			'|\xF4[\x80-\x8F][\x80-\xBF]{2})#S';	# plane 16

	$out = '';
	$matches = array();
	while (preg_match($utf8, $in, $matches))
	{
		$out .= $matches[0];
		$in = substr($in, strlen($matches[0]));
	}

	return $out;
}

/**
 * Converts a string from one character encoding to another.
 * If the target encoding is not specified then it will be resolved from the current
 * language settings.
 *
 * @param	string	The string to convert
 * @param	string	The source encoding
 * @return	string	The target encoding
 */
function to_charset($in, $in_encoding, $target_encoding = false)
{
	if (!$target_encoding)
	{
		if (!($target_encoding = vB_Template_Runtime::fetchStyleVar('charset')))
		{
			global $vbulletin;
			if (!($target_encoding = $vbulletin->userinfo['lang_charset']))
			{
				return $in;
			}
		}
	}

	// Try iconv
	if (function_exists('iconv'))
	{
		// Try iconv
		$out = @iconv($in_encoding, $target_encoding, $in);
		return $out;
	}

	// Try mbstring
	if (function_exists('mb_convert_encoding'))
	{
		return @mb_convert_encoding($in, $target_encoding, $in_encoding);
	}

	return $in;
}

/**
 * Strips NCRs from a string.
 *
 * @param	string	The string to strip from
 * @return	string	The result
 */
function stripncrs($str)
{
	return preg_replace('/(&#[0-9]+;)/', '', $str);
}

/**
 * Converts a UTF-8 string into unicode NCR equivelants.
 *
 * @param	string	String to encode
 * @param	bool	Only ncrencode unicode bytes
 * @param	bool	If true and $skip_ascii is true, it will skip windows-1252 extended chars
 * @return	string	Encoded string
 */
function ncrencode($str, $skip_ascii = false, $skip_win = false)
{
	if (!$str)
	{
		return $str;
	}

	if (function_exists('mb_encode_numericentity'))
	{
		if ($skip_ascii)
		{
			if ($skip_win)
			{
				$start = 0xFE;
			}
			else
			{
				$start = 0x80;
			}
		}
		else
		{
			$start = 0x0;
		}
		return mb_encode_numericentity($str, array($start, 0xffff, 0, 0xffff), 'UTF-8');
	}

	if (is_pcre_unicode())
	{
		return preg_replace_callback(
			'#\X#u',
			create_function('$matches', 'return ncrencode_matches($matches, ' . (int)$skip_ascii . ', ' . (int)$skip_win . ');'),
			$str
		);
	}

	return $str;
}

/**
 * NCR encodes matches from a preg_replace.
 * Single byte characters are preserved.
 *
 * @param	string	The character to encode
 * @return	string	The encoded character
 */
function ncrencode_matches($matches, $skip_ascii = false, $skip_win = false)
{
	$ord = ord_uni($matches[0]);

	if ($skip_win)
	{
		$start = 254;
	}
	else
	{
		$start = 128;
	}

	if ($skip_ascii AND $ord < $start)
	{
		return $matches[0];
	}

	return '&#' . ord_uni($matches[0]) . ';';
}

/**
 * Gets the Unicode Ordinal for a UTF-8 character.
 *
 * @param	string	Character to convert
 * @return	int		Ordinal value or false if invalid
 */
function ord_uni($chr)
{
	// Valid lengths and first byte ranges
	static $check_len = array(
		1 => array(0, 127),
		2 => array(192, 223),
		3 => array(224, 239),
		4 => array(240, 247),
		5 => array(248, 251),
		6 => array(252, 253)
	);

	// Get length
	$blen = strlen($chr);

	// Get single byte ordinals
	$b = array();
	for ($i = 0; $i < $blen; $i++)
	{
		$b[$i] = ord($chr[$i]);
	}

	// Check expected length
	foreach ($check_len AS $len => $range)
	{
		if (($b[0] >= $range[0]) AND ($b[0] <= $range[1]))
		{
			$elen = $len;
		}
	}

	// If no range found, or chr is too short then it's invalid
	if (!isset($elen) OR ($blen < $elen))
	{
		return false;
	}

	// Normalise based on octet-sequence length
	switch ($elen)
	{
		case (1):
			return $b[0];
		case (2):
			return ($b[0] - 192) * 64 + ($b[1] - 128);
		case (3):
			return ($b[0] - 224) * 4096 + ($b[1] - 128) * 64 + ($b[2] - 128);
		case (4):
			return ($b[0] - 240) * 262144 + ($b[1] - 128) * 4096 + ($b[2] - 128) * 64 + ($b[3] - 128);
		case (5):
			return ($b[0] - 248) * 16777216 + ($b[1] - 128) * 262144 + ($b[2] - 128) * 4096 + ($b[3] - 128) * 64 + ($b[4] - 128);
		case (6):
			return ($b[0] - 252) * 1073741824 + ($b[1] - 128) * 16777216 + ($b[2] - 128) * 262144 + ($b[3] - 128) * 4096 + ($b[4] - 128) * 64 + ($b[5] - 128);
	}
}

// #############################################################################
/**
* Stuffs a message into the $DEVDEBUG array
*
* @param	string	Message to store
*/
function devdebug($text = '')
{
	global $vbulletin;

	if ($vbulletin->debug)
	{
		$GLOBALS['DEVDEBUG'][] = $text;
	}
}

// #############################################################################
/**
* Sends the appropriate HTTP headers for the page that is being displayed
*
* @param	boolean	If true, send HTTP 200
* @param	boolean	If true, send no-cache headers
*/
function exec_headers($headers = true, $nocache = true)
{
	global $vbulletin;

	$contenttype = $vbulletin->contenttype ? $vbulletin->contenttype : 'text/html';

	$sendcontent = true;
	if ($vbulletin->options['addheaders'] AND !$vbulletin->noheader AND $headers)
	{
		// default headers
		if (SAPI_NAME == 'cgi' OR SAPI_NAME == 'cgi-fcgi')
		{
			header('Status: 200 OK');
		}
		else
		{
			header($_SERVER['SERVER_PROTOCOL'] . ' 200 OK');
		}
		@header('Content-Type: ' . $contenttype . iif($vbulletin->userinfo['lang_charset'] != '', '; charset=' . $vbulletin->userinfo['lang_charset']));
		$sendcontent = false;
	}

	if ($vbulletin->options['nocacheheaders'] AND !$vbulletin->noheader AND $nocache)
	{
		// no caching
		exec_nocache_headers($sendcontent);
	}
	else if (!$vbulletin->noheader)
	{
		@header("Cache-Control: private");
		@header("Pragma: private");
		if ($sendcontent)
		{
			$charset = $vbulletin->userinfo['lang_charset'] ? $vbulletin->userinfo['lang_charset'] : vB_Template_Runtime::fetchStyleVar('charset');
			@header('Content-Type: ' . $contenttype . '; charset=' . $charset);
		}
	}

	if ($vbulletin->options['forceie8'])
	{
		//Disable any IE emulate option
		@header('X-UA-Compatible: IE=Edge');
	}
}

// #############################################################################
/**
* Sends no-cache HTTP headers
*
* @param	boolean	If true, send content-type header
*/
function exec_nocache_headers($sendcontent = true)
{
	global $vbulletin;
	static $sentheaders;

	if (!$sentheaders)
	{
		@header("Expires: 0"); // Date in the past
		#@header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
		#@header("Cache-Control: no-store, no-cache, must-revalidate"); // HTTP/1.1
		@header("Cache-Control: private, post-check=0, pre-check=0, max-age=0", false);
		@header("Pragma: no-cache"); // HTTP/1.0
		if ($sendcontent)
		{
			@header('Content-Type: text/html' . iif($vbulletin->userinfo['lang_charset'] != '', '; charset=' . $vbulletin->userinfo['lang_charset']));
		}
	}

	$sentheaders = true;
}

// #############################################################################
/**
* Returns whether or not the visiting user can view the specified password-protected forum
*
* @param	integer	Forum ID
* @param	string	Provided password
* @param	boolean	If true, show error when access is denied
*
* @return	boolean
*/
function verify_forum_password($forumid, $password, $showerror = true)
{
	global $vbulletin;

	if (!$password OR ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']) OR ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['ismoderator']) OR can_moderate($forumid))
	{
		return true;
	}

	$foruminfo = fetch_foruminfo($forumid);
	$parents = explode(',', $foruminfo['parentlist']);
	if (!VB_API)
	{
		foreach ($parents AS $fid)
			{
				// get the pwd from any parent forums -- allows pwd cookies to cascade down
			if ($temp = fetch_bbarray_cookie('forumpwd', $fid) AND $temp === md5($vbulletin->userinfo['userid'] . $password))
			{
				return true;
			}
		}
	}
	else
	{
		$forumpwdmd5 = $vbulletin->input->clean_gpc('r', 'forumpwdmd5', TYPE_STR);
		if ($forumpwdmd5 === md5($vbulletin->userinfo['userid'] . $password))
		{
			return true;
		}
	}

	// didn't match the password in any cookie
	if ($showerror)
	{
		require_once(DIR . '/includes/functions_misc.php');

		$security_token_html = '<input type="hidden" name="securitytoken" value="' . $vbulletin->userinfo['securitytoken'] . '" />';

		// forum password is bad - show error

		//use the basic link here.  I'm not sure how the advanced link will play with the postvars in the form.
		require_once(DIR . '/includes/class_friendly_url.php');
		$forumlink = vB_Friendly_Url::fetchLibrary($vbulletin, 'forum|nosession',
			$foruminfo, array('do' => 'doenterpwd'));
		$forumlink = $forumlink->get_url(FRIENDLY_URL_OFF);
		// TODO convert the 'forumpasswordmissoing' phrase to vB4
		eval(standard_error(fetch_error('forumpasswordmissing',
			$vbulletin->session->vars['sessionhash'],
			$vbulletin->scriptpath,
			$forumid,
			construct_post_vars_html() . $security_token_html,
			10,
			1,
			$forumlink
		)));
	}
	else
	{
		// forum password is bad - return false
		return false;
	}
}

// #############################################################################
/**
* Returns whether or not the user requires a human verification test to complete the specified action
*
* @param string $action						The name of the action to check
* @return boolean							Whether a hv check is required
*/
function fetch_require_hvcheck($action)
{
	global $vbulletin;

	if (!$vbulletin->options['hv_type']
		OR !($vbulletin->options['hvcheck'] & $vbulletin->bf_misc_hvcheck[$action]))
	{
		return false;
	}

	switch ($action)
	{
		case 'register':
		{
			$guestuser = array(
				'userid'      => 0,
				'usergroupid' => 0,
			);
			cache_permissions($guestuser);

			return ($guestuser['permissions']['genericoptions'] & $vbulletin->bf_ugp_genericoptions['requirehvcheck']);
		}

		case 'lostpw':
		{
			if ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])
			{
				return false;
			}
			break;
		}
	}

	return ($vbulletin->userinfo['permissions']['genericoptions'] & $vbulletin->bf_ugp_genericoptions['requirehvcheck']);
}

// #############################################################################
/**
* Converts a bitfield into an array of 1 / 0 values based on the array describing the resulting fields
*
* @param	integer	(ref) Bitfield
* @param	array	Array containing field definitions - array('canx' => 1, 'cany' => 2, 'canz' => 4) etc
*
* @return	array
*/
function convert_bits_to_array(&$bitfield, $_FIELDNAMES)
{
	$bitfield = intval($bitfield);
	$arry = array();
	foreach ($_FIELDNAMES AS $field => $bitvalue)
	{
		if ($bitfield & $bitvalue)
		{
			$arry["$field"] = 1;
		}
		else

		{
			$arry["$field"] = 0;
		}
	}
	return $arry;
}

// #############################################################################
/**
* Returns the full set of permissions for the specified user (called by global or init)
*
* @param	array	(ref) User info array
* @param	boolean	If true, returns combined usergroup permissions, individual forum permissions, individual calendar permissions and attachment permissions
* @param boolean        Reset the accesscache array for permissions following access mask update. Only allows one reset.
*
* @return	array	Permissions component of user info array
*/
function cache_permissions(&$user, $getforumpermissions = true, $resetaccess = false)
{
	global $vbulletin, $forumpermissioncache;

	// these are the arrays created by this function

	//this is only set if we load the calendar perms, which have been moved to another function
	//global $calendarcache;

	static $accesscache = array(), $reset;

	if ($resetaccess AND !$reset)
	{	// Reset the accesscache array for permissions following access mask update. Only allows one reset.
		$accesscache = array();
		$reset = true;
	}

	$intperms = array();

	// set the usergroupid of the user's primary usergroup
	$USERGROUPID = $user['usergroupid'];

	if ($USERGROUPID == 0)
	{ // set a default usergroupid if none is set
		$USERGROUPID = 1;
	}

	// initialise $membergroups - make an array of the usergroups to which this user belongs
	$membergroupids = fetch_membergroupids_array($user);

	// build usergroup permissions
	if (sizeof($membergroupids) == 1 OR !($vbulletin->usergroupcache["$USERGROUPID"]['genericoptions'] & $vbulletin->bf_ugp_genericoptions['allowmembergroups']))
	{
		// if primary usergroup doesn't allow member groups then get rid of them!
		$membergroupids = array($USERGROUPID);

		// just return the permissions for the user's primary group (user is only a member of a single group)
		$user['permissions'] = $vbulletin->usergroupcache["$USERGROUPID"];
	}
	else
	{
		// initialise fields to 0
		foreach ($vbulletin->bf_ugp AS $dbfield => $permfields)
		{
			$user['permissions']["$dbfield"] = 0;
		}

		// return the merged array of all user's membergroup permissions (user has additional member groups)
		foreach ($membergroupids AS $usergroupid)
		{
			foreach ($vbulletin->bf_ugp AS $dbfield => $permfields)
			{
				$user['permissions']["$dbfield"] |= $vbulletin->usergroupcache["$usergroupid"]["$dbfield"];
			}
			foreach ($vbulletin->bf_misc_intperms AS $dbfield => $precedence)
			{
				// put in some logic to handle $precedence
				if (!isset($intperms["$dbfield"]))
				{
					$intperms["$dbfield"] = $vbulletin->usergroupcache["$usergroupid"]["$dbfield"];
				}
				else if (!$precedence)
				{
					if ($vbulletin->usergroupcache["$usergroupid"]["$dbfield"] > $intperms["$dbfield"])
					{
						$intperms["$dbfield"] = $vbulletin->usergroupcache["$usergroupid"]["$dbfield"];
					}
				}
				else if ($vbulletin->usergroupcache["$usergroupid"]["$dbfield"] == 0 OR (isset($intperms["$dbfield"]) AND $intperms["$dbfield"] == 0)) // Set value to 0 as it overrides all
				{
					$intperms["$dbfield"] = 0;
				}
				else if ($vbulletin->usergroupcache["$usergroupid"]["$dbfield"] > $intperms["$dbfield"])
				{
					$intperms["$dbfield"] = $vbulletin->usergroupcache["$usergroupid"]["$dbfield"];
				}
			}
		}
		$user['permissions'] = array_merge($vbulletin->usergroupcache["$USERGROUPID"], $user['permissions'], $intperms);
	}

	if (!empty($user['infractiongroupids']))
	{
		$infractiongroupids = explode(',', str_replace(' ', '', $user['infractiongroupids']));
	}
	else
	{
		$infractiongroupids = array();
	}

	foreach ($infractiongroupids AS $usergroupid)
	{
		foreach ($vbulletin->bf_ugp AS $dbfield => $permfields)
		{
			$user['permissions']["$dbfield"] &= $vbulletin->usergroupcache["$usergroupid"]["$dbfield"];
		}
		foreach ($vbulletin->bf_misc_intperms AS $dbfield => $precedence)
		{
			if (!$precedence)
			{
				if ($vbulletin->usergroupcache["$usergroupid"]["$dbfield"] < $user['permissions']["$dbfield"])
				{
					$user['permissions']["$dbfield"] = $vbulletin->usergroupcache["$usergroupid"]["$dbfield"];
				}
			}
			else if ($vbulletin->usergroupcache["$usergroupid"]["$dbfield"] < $user['permissions']["$dbfield"] AND $vbulletin->usergroupcache["$usergroupid"]["$dbfield"] != 0)
			{
				$user['permissions']["$dbfield"] = $vbulletin->usergroupcache["$usergroupid"]["$dbfield"];
			}
		}
	}

	if (defined('SKIP_SESSIONCREATE') AND $user['userid'] == $vbulletin->userinfo['userid'] AND !($user['permissions']['forumpermissions'] & $vbulletin->bf_ugp_forumpermissions['canview']))
	{	// grant canview for usergroup if session skipping is defined.
		$user['permissions']['forumpermissions'] += $vbulletin->bf_ugp_forumpermissions['canview'];
	}

	($hook = vBulletinHook::fetch_hook('cache_permissions')) ? eval($hook) : false;

	// if we do not need to grab the forum/calendar permissions
	// then just return what we have so far
	if ($getforumpermissions == false)
	{
		return $user['permissions'];
	}

	if (!isset($user['forumpermissions']) OR !is_array($user['forumpermissions']))
	{
		$user['forumpermissions'] = array();
	}
	if(!empty($vbulletin->forumcache))
	{
		foreach (array_keys($vbulletin->forumcache) AS $forumid)
		{
			if (!isset($user['forumpermissions']["$forumid"]))
			{
				$user['forumpermissions']["$forumid"] = 0;
			}
			foreach ($membergroupids AS $usergroupid)
			{
				$user['forumpermissions']["$forumid"] |= $vbulletin->forumcache["$forumid"]['permissions']["$usergroupid"];
			}
			foreach ($infractiongroupids AS $usergroupid)
			{
				$user['forumpermissions']["$forumid"] &= $vbulletin->forumcache["$forumid"]['permissions']["$usergroupid"];
			}
		}
	}
	// do access mask stuff if required
	if ($vbulletin->options['enableaccess'] AND isset($user['hasaccessmask']) AND $user['hasaccessmask'] == 1)
	{
		if (empty($accesscache["$user[userid]"]))
		{
			// query access masks
			// the ordercontrol is required! (3.5 bug 1878)
			$accessmasks = $vbulletin->db->query_read_slave("
				SELECT access.*, forum.forumid,
					FIND_IN_SET(access.forumid, forum.parentlist) AS ordercontrol
				FROM " . TABLE_PREFIX . "forum AS forum
				INNER JOIN " . TABLE_PREFIX . "access AS access ON (access.userid = $user[userid] AND FIND_IN_SET(access.forumid, forum.parentlist))
				ORDER BY ordercontrol DESC
			");

			$accesscache["$user[userid]"] = array();
			while ($access = $vbulletin->db->fetch_array($accessmasks))
			{
				$accesscache["$user[userid]"]["$access[forumid]"] = $access['accessmask'];
			}
			unset($access);
			$vbulletin->db->free_result($accessmasks);
		}

		// if an access mask is set for a forum, set the permissions accordingly
		// If this is empty then the user really has no access masks but the switch is turned on?!?
		if (!empty($accesscache["$user[userid]"]))
		{
			foreach ($accesscache["$user[userid]"] AS $forumid => $accessmask)
			{
				if ($accessmask == 0) // disable access
				{
					$user['forumpermissions']["$forumid"] = 0;
				}
				else // use combined permissions
				{
					$user['forumpermissions']["$forumid"] = $user['permissions']['forumpermissions'];
				}
			}
		}
		else
		{
			// says the user has access masks, but doesn't actually
			// so turn them off
			$userdm =& datamanager_init('User', $vbulletin, ERRTYPE_SILENT);
			$userdm->set_existing($user);
			$userdm->set_bitfield('options', 'hasaccessmask', false);
			$userdm->save();
			unset($userdm);
		}

	} // end if access masks enabled and is logged in user

	$calfiles = array(
		'online'   => true,
		'calendar' => true,
		'index'    => $vbulletin->options['showevents'] ? true : false,
	);

	if (THIS_SCRIPT == 'index' AND $vbulletin->options['showevents'])
	{
		if (!is_array($vbulletin->eventcache)
			OR gmdate('n-j-Y' , TIMENOW + 86400 + 86400 * $vbulletin->options['showevents']) != $vbulletin->eventcache['date']
		)
		{
			// need perms with rebuild
			$calfiles['index'] = true;
		}
		else if (count($vbulletin->eventcache) == 1)
		{
			// no events, only the date - don't need to cache the perms
			$calfiles['index'] = false;
		}
	}

	// query calendar permissions
	if (!empty($calfiles[THIS_SCRIPT]))
	{
		// Only query calendar permissions when accessing the calendar or subscriptions or index.php
		cache_calendar_permissions($user);
	}

	if (!empty($vbulletin->attachmentcache) AND empty($vbulletin->attachmentcache['extensions']))
	{
		$fields = array(
			'size'   => true,
			'width'  => true,
			'height' => true,
		);
		$user['attachmentextensions'] = '';

		// Combine the attachment permissions for all member groups
		foreach($vbulletin->attachmentcache AS $extension => $attachment)
		{
			$need_default = false;
			foreach($membergroupids AS $usergroupid)
			{
				if (!empty($attachment['custom']["$usergroupid"]))
				{
					$perm = $attachment['custom']["$usergroupid"];
					$user['attachmentpermissions']["$extension"]['permissions'] |= $perm['permissions'];

					foreach ($fields AS $dbfield => $precedence)
					{
						// put in some logic to handle $precedence
						if (!isset($user['attachmentpermissions']["$extension"]["$dbfield"]))
						{
							$user['attachmentpermissions']["$extension"]["$dbfield"] = $perm["$dbfield"];
						}
						else if (!$precedence)
						{
							if ($perm["$dbfield"] > $user['attachmentpermissions']["$extension"]["$dbfield"])
							{
								$user['attachmentpermissions']["$extension"]["$dbfield"] = $perm["$dbfield"];
							}
						}
						else if ($perm["$dbfield"] == 0 OR (isset($user['attachmentpermissions']["$extension"]["$dbfield"]) AND $user['attachmentpermissions']["$extension"]["$dbfield"] == 0))
						{
							$user['attachmentpermissions']["$extension"]["$dbfield"] = 0;
						}
						else if ($perm["$dbfield"] > $user['attachmentpermissions']["$extension"]["$dbfield"])
						{
							$user['attachmentpermissions']["$extension"]["$dbfield"] = $perm["$dbfield"];
						}
					}
				}
				else
				{
						$need_default = true;
				}
			}

			if (empty($user['attachmentpermissions']["$extension"]))
			{
				$user['attachmentpermissions']["$extension"] = array(
					'permissions'  => 1,
					'size'         => $vbulletin->attachmentcache["$extension"]['size'],
					'height'       => $vbulletin->attachmentcache["$extension"]['height'],
					'width'        => $vbulletin->attachmentcache["$extension"]['width'],
					'contenttypes' => isset($vbulletin->attachmentcache["$extension"]['contenttypes']) ?
						$vbulletin->attachmentcache["$extension"]['contenttypes'] : null,
				);
			}
			else if ($need_default)
			{
				$user['attachmentpermissions']["$extension"]['permissions'] = 1;
				$perm = $vbulletin->attachmentcache["$extension"];
				foreach ($fields AS $dbfield => $precedence)
				{
					// put in some logic to handle $precedence
					if (!isset($user['attachmentpermissions']["$extension"]["$dbfield"]))
					{
						$user['attachmentpermissions']["$extension"]["$dbfield"] = $perm["$dbfield"];
					}
					else if (!$precedence)
					{
						if ($perm["$dbfield"] > $user['attachmentpermissions']["$extension"]["$dbfield"])
						{
							$user['attachmentpermissions']["$extension"]["$dbfield"] = $perm["$dbfield"];
						}
					}
					else if ($perm["$dbfield"] == 0 OR (isset($user['attachmentpermissions']["$extension"]["$dbfield"]) AND $user['attachmentpermissions']["$extension"]["$dbfield"] == 0))
					{
 						$user['attachmentpermissions']["$extension"]["$dbfield"] = 0;
					}
					else if ($perm["$dbfield"] > $user['attachmentpermissions']["$extension"]["$dbfield"])
					{
						$user['attachmentpermissions']["$extension"]["$dbfield"] = $perm["$dbfield"];
					}
				}
			}

			foreach($infractiongroupids AS $usergroupid)
			{
				if (!empty($attachment['custom']["$usergroupid"]))
				{
					$perm = $attachment['custom']["$usergroupid"];
					$user['attachmentpermissions']["$extension"]['permissions'] &= $perm['permissions'];

					foreach ($fields AS $dbfield => $precedence)
					{
						if (!$precedence)
						{
							if ($perm["$dbfield"] < $user['attachmentpermissions']["$extension"]["$dbfield"])
							{
								$user['attachmentpermissions']["$extension"]["$dbfield"] = $perm["$dbfield"];
							}
						}
						else if ($perm["$dbfield"] < $user['attachmentpermissions']["$extension"]["$dbfield"] AND $perm["$dbfield"] != 0)
						{
							$user['attachmentpermissions']["$extension"]["$dbfield"] = $perm["$dbfield"];
						}
					}
				}
			}
		}

		foreach ($user['attachmentpermissions'] AS $extension => $foo)
		{
			if ($user['attachmentpermissions']["$extension"]['permissions'])
			{
				$user['attachmentextensions'] .= (!empty($user['attachmentextensions']) ? ' ' : '') . $extension;
			}
		}
	}

	return $user['permissions'];
}

/**
* Sets the calendar permissions to the passed user info array
*
* @param	array	(ref) User info array
*
* @return	array	Calendar permissions component of user info array
*/
function cache_calendar_permissions(&$user)
{
	global $calendarcache;
	global $vbulletin;

	$cpermscache = array();
	$calendarcache = array();
	$displayorder = array();

	//we should move this stuff to a user object.
	if (!empty($user['infractiongroupids']))
	{
		$infractiongroupids = explode(',', str_replace(' ', '', $user['infractiongroupids']));
	}
	else
	{
		$infractiongroupids = array();
	}

	// initialise $membergroups - make an array of the usergroups to which this user belongs
	$membergroupids = fetch_membergroupids_array($user);

	// build usergroup permissions
	if (sizeof($membergroupids) == 1 OR
		!($vbulletin->usergroupcache["$user[usergroupid]"]['genericoptions'] &
		$vbulletin->bf_ugp_genericoptions['allowmembergroups'])
	)
	{
		// if primary usergroup doesn't allow member groups then get rid of them!
		$membergroupids = array($user['usergroupid']);
	}

	$calendarpermissions = $vbulletin->db->query_read_slave("
		SELECT calendarpermission.usergroupid, calendarpermission.calendarpermissions,
			calendar.calendarid,calendar.title, displayorder
		FROM " . TABLE_PREFIX . "calendar AS calendar
		LEFT JOIN " . TABLE_PREFIX . "calendarpermission AS calendarpermission ON
			(calendarpermission.calendarid = calendar.calendarid AND
				usergroupid IN (" . implode(', ', $membergroupids) . "))
		ORDER BY displayorder ASC
	");
	while ($cp = $vbulletin->db->fetch_array($calendarpermissions))
	{
		$cpermscache["$cp[calendarid]"]["$cp[usergroupid]"] = intval($cp['calendarpermissions']);
		$calendarcache["$cp[calendarid]"] = $cp['title'];
		$displayorder["$cp[calendarid]"] = $cp['displayorder'];
	}
	$vbulletin->db->free_result($calendarpermissions);

	// Combine the calendar permissions for all member groups
	foreach ($cpermscache AS $calendarid => $cpermissions)
	{
		$user['calendarpermissions']["$calendarid"] = 0;

		if (empty($displayorder["$calendarid"]))
		{
			// leave permissions at 0 for calendars that aren't being displayed
			continue;
		}

		foreach ($membergroupids AS $usergroupid)
		{
			if (isset($cpermissions["$usergroupid"]))
			{
				$user['calendarpermissions']["$calendarid"] |= $cpermissions["$usergroupid"];
			}
			else
			{
				$user['calendarpermissions']["$calendarid"] |= $vbulletin->usergroupcache["$usergroupid"]['calendarpermissions'];
			}
		}
		foreach ($infractiongroupids AS $usergroupid)
		{
			if (isset($cpermissions["$usergroupid"]))
			{
				$user['calendarpermissions']["$calendarid"] &= $cpermissions["$usergroupid"];
			}
			else
			{
				$user['calendarpermissions']["$calendarid"] &= $vbulletin->usergroupcache["$usergroupid"]['calendarpermissions'];
			}
		}
	}
	return $user['calendarpermissions'];
}

// #############################################################################
/**
* Returns permissions for given forum and user
*
* @param	integer	Forum ID
* @param	integer	User ID
* @param	array	User info array
*
* @return	mixed
*/
function fetch_permissions($forumid = 0, $userid = -1, $userinfo = false)
{
	// gets permissions, depending on given userid and forumid
	global $vbulletin, $usercache, $permscache;

	$userid = intval($userid);
	if ($userid == -1)
	{
		$userid = $vbulletin->userinfo['userid'];
		$usergroupid = $vbulletin->userinfo['usergroupid'];
	}

	// ########## #DEBUG# CODE ##############
	$DEBUG_MESSAGE = (isset($GLOBALS['_permsgetter_']) ? "($GLOBALS[_permsgetter_])" : '(unspecified)') .
		" fetch_permissions($forumid, $userid, $usergroupid); ";
	unset($GLOBALS['_permsgetter_']);
	// ########## END #DEBUG# CODE ##############

	if ($userid == $vbulletin->userinfo['userid'])
	{
		// we are getting permissions for $vbulletin->userinfo
		// so return permissions built in querypermissions
		if ($forumid)
		{
			DEVDEBUG($DEBUG_MESSAGE."-> cached fperms for forum $forumid");
			return $vbulletin->userinfo['forumpermissions']["$forumid"];
		}
		else
		{
			DEVDEBUG($DEBUG_MESSAGE.'-> cached combined permissions');
			return $vbulletin->userinfo['permissions'];
		}
	}
	else
	{
	// we are getting permissions for another user...
		if (!is_array($userinfo))
		{
			return 0;
		}
		if ($forumid)
		{
			DEVDEBUG($DEBUG_MESSAGE."-> trying to get forumpermissions for non \$bbuserinfo");
			cache_permissions($userinfo);
			return $userinfo['forumpermissions']["$forumid"];
		}
		else
		{
			DEVDEBUG($DEBUG_MESSAGE."-> trying to get combined permissions for non \$bbuserinfo");
			return cache_permissions($userinfo, false);
		}
	}

}

// #############################################################################
/**
* Returns moderator permissions bitfield for the given forum and user
*
* @param	integer	Forum ID
* @param	integer	User ID
* @param	boolean	Include Global Permissions for Super Moderators
*
* @return	integer
*/
function fetch_moderator_permissions($forumid, $userid = -1, $useglobalperms = false)
{
	// gets permissions, depending on given userid and forumid
	global $vbulletin, $imodcache;
	static $modpermscache;

	$forumid = intval($forumid);

	if ($userid == -1)
	{
		$userid = $vbulletin->userinfo['userid'];
	}

	if (isset($modpermscache["$forumid"]["$userid"]))
	{
		DEVDEBUG("  CACHE \$modpermscache cache result");
		return $modpermscache["$forumid"]["$userid"];
	}

	$globalperms = array(
		'permissions'  => 0,
		'permissions2' => 0,
	);
	$getperms = array();
	$hasglobalperms = false;

	if (isset($imodcache))
	{
		if (isset($imodcache["$forumid"]["$userid"]))
		{
			DEVDEBUG("  CACHE first result from imodcache");
			$getperms = $imodcache["$forumid"]["$userid"];
		}
		else
		{
			$parentlist = explode(',', fetch_forum_parent_list($forumid));
			foreach($parentlist AS $parentid)
			{
				// we dont want the super perms since we'll merge them when required further down
				if (isset($imodcache["$parentid"]["$userid"]) AND $parentid != -1)
				{
					DEVDEBUG("  CACHE looped result from imodcache");
					$getperms = $imodcache["$parentid"]["$userid"];
				}
			}
		}
		$globalperms['permissions'] = $imodcache['-1']["$userid"]['permissions'];
		$globalperms['permissions2'] = $imodcache['-1']["$userid"]['permissions2'];
		$hasglobalperms = isset($imodcache['-1']["$userid"]['permissions']);
	}
	else
	{
		$forumlist = fetch_forum_clause_sql($forumid, 'forumid');
		if (!empty($forumlist))
		{
			$forumlist = 'AND ' . $forumlist;
		}
		DEVDEBUG("  QUERY: get mod permissions for user $userid");
		$perms = $vbulletin->db->query_read_slave("
			(SELECT permissions, permissions2, FIND_IN_SET(forumid, '" . fetch_forum_parent_list($forumid) . "') AS pos, forumid
			FROM " . TABLE_PREFIX . "moderator
			WHERE userid = $userid $forumlist
			ORDER BY pos ASC
			LIMIT 1)
			UNION
			(SELECT permissions, permissions2, 0, forumid
			FROM " . TABLE_PREFIX . "moderator
			WHERE userid = $userid AND forumid = -1
			)
		");
		while ($perm = $vbulletin->db->fetch_array($perms))
		{
			if ($perm['forumid'] == -1)
			{
				$globalperms['permissions'] = $perm['permissions'];
				$globalperms['permission2'] = $perm['permissions2'];
				$hasglobalperms = true;
			}
			else
			{
				$getperms['permissions'] = $perm['permissions'];
				$getperms['permissions2'] = $perm['permissions2'];
			}
		}
	}

	if ($useglobalperms)
	{
		if (!$hasglobalperms)
		{
			// super mod without a record, give them all permissions
			$globalperms['permissions'] = array_sum($vbulletin->bf_misc_moderatorpermissions) - ($vbulletin->bf_misc_moderatorpermissions['newthreademail'] + $vbulletin->bf_misc_moderatorpermissions['newpostemail']);
			$globalperms['permissions2'] = array_sum($vbulletin->bf_misc_moderatorpermissions2);
		}
		$getperms['permissions'] = !empty($getperms['permissions']) ? intval($getperms['permissions']) : 0;
		$getperms['permissions'] |= intval($globalperms['permissions']);
		$getperms['permissions2'] = !empty($getperms['permissions2']) ? intval($getperms['permissions2']) : 0;
		$getperms['permissions2'] |= intval($globalperms['permissions2']);
	}

	$modpermscache["$forumid"]["$userid"]['permissions'] = intval($getperms['permissions']);
	$modpermscache["$forumid"]["$userid"]['permissions2'] = intval($getperms['permissions2']);

	return $modpermscache["$forumid"]["$userid"];

}

// #############################################################################
/**
* Returns whether or not the given user can perform a specific moderation action in the specified forum
*
* @param	integer	Forum ID
* @param	string	If you want to check a particular moderation permission, name it here
* @param	integer	User ID
* @param	string	Comma separated list of usergroups to which the user belongs
*
* @return	boolean
*/
function can_moderate($forumid = 0, $do = '', $userid = -1, $usergroupids = '')
{
	global $vbulletin, $imodcache;
	static $modcache;
	static $permissioncache;

	$userid = intval($userid);
	$forumid = intval($forumid);

	if ($do)
	{
			if (isset($vbulletin->bf_misc_moderatorpermissions["$do"]))
			{
					$permission =& $vbulletin->bf_misc_moderatorpermissions["$do"];
					$set = 'permissions';
			}
			else if (isset($vbulletin->bf_misc_moderatorpermissions2["$do"]))
			{
					$permission =& $vbulletin->bf_misc_moderatorpermissions2["$do"];
					$set = 'permissions2';
			}
	}

	if ($userid == -1)
	{
		$userid = $vbulletin->userinfo['userid'];
	}

	if ($userid == 0)
	{
		return false;
	}

	$issupermod = false;
	if ($userid == $vbulletin->userinfo['userid'])
	{
		if ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['ismoderator'])
		{
			DEVDEBUG('  USER IS A SUPER MODERATOR');
			$issupermod = true;
		}
	}
	else
	{
		if (!$usergroupids)
		{
			$tempuser = $vbulletin->db->query_first_slave("SELECT usergroupid, membergroupids FROM " . TABLE_PREFIX . "user WHERE userid = $userid");
			if (!$tempuser)
			{
				return false;
			}
			$usergroupids = $tempuser['usergroupid'] . iif(trim($tempuser['membergroupids']), ",$tempuser[membergroupids]");
		}
		$supermodcheck = $vbulletin->db->query_first_slave("
			SELECT usergroupid
			FROM " . TABLE_PREFIX . "usergroup
			WHERE usergroupid IN ($usergroupids)
				AND (adminpermissions & " . $vbulletin->bf_ugp_adminpermissions['ismoderator'] . ") != 0
			LIMIT 1
		");
		if ($supermodcheck)
		{
			DEVDEBUG('  USER IS A SUPER MODERATOR');
			$issupermod = true;
		}

	}

	if ($forumid == 0)
	{ // just check to see if the user is a moderator of any forum
		if (isset($imodcache))
		{ // loop through imodcache to find user
			DEVDEBUG("looping through imodcache to find userid $userid");
			foreach ($imodcache AS $forummodid => $forummods)
			{
				if (isset($forummods["$userid"]) AND ($forummodid != -1 OR $issupermod))
				{
					if (!$do)
					{
						return true;
					}
					else if ($forummods["$userid"]["$set"] & $permission)
					{
						return true;
					}
				}
			}

			if ($issupermod AND !isset($imodcache['-1']["$userid"]))
			{
				// super mod without a record -- has all perms
				return true;
			}

			return false;
		}
		else
		{ // imodcache is not set - do a query

			if (isset($modcache["$userid"]["$do"]))
			{
				return $modcache["$userid"]["$do"];
			}

			if ($issupermod AND ($permissioncache["$userid"]['hassuperrecord'] === false))
			{
				$modcache["$userid"]["$do"] = 1;
				return $modcache["$userid"]["$do"];
			}

			if ($do AND isset($permissioncache["$userid"]["$set"]))
			{
				if ($set == 'permissions2')
				{
					$modcache["$userid"]["$do"] = $permissioncache["$userid"]["$set"] & $vbulletin->bf_misc_moderatorpermissions2["$do"];
					return $modcache["$userid"]["$do"];
				}
				else
				{
					$modcache["$userid"]["$do"] = $permissioncache["$userid"]["$set"] & $vbulletin->bf_misc_moderatorpermissions["$do"];
					return $modcache["$userid"]["$do"];
				}
			}

			$modcache["$userid"]["$do"] = 0;

			DEVDEBUG('QUERY: is the user a moderator (any forum)?');
			$ismod_all = $vbulletin->db->query_read_slave("
				SELECT forumid, moderatorid, permissions, permissions2
				FROM " . TABLE_PREFIX . "moderator
				WHERE userid = $userid" . (!$issupermod ? ' AND forumid != -1' : '')
			);

			if (!isset($permissioncache["$userid"]))
			{
				$permissioncache["$userid"]['permissions'] = 0;
				$permissioncache["$userid"]['permissions2'] = 0;
			}

			while ($ismod = $vbulletin->db->fetch_array($ismod_all))
			{
				$permissioncache["$userid"]['permissions'] = $permissioncache["$userid"]['permissions'] | $ismod['permissions'];
				$permissioncache["$userid"]['permissions2'] = $permissioncache["$userid"]['permissions2'] | $ismod['permissions2'];

				if ($ismod['forumid'] == '-1')
				{
					$permissioncache["$userid"]['hassuperrecord'] = true;
				}
				if ($do)
				{
					if ($ismod["$set"] & $permission)
					{
						$modcache["$userid"]["$do"] = 1;
					}
				}
				else
				{
					$modcache["$userid"]["$do"] = 1;
				}
			}
			$vbulletin->db->free_result($ismod_all);

			if ($issupermod AND !isset($permissioncache["$userid"]['hassuperrecord']))
			{
				$permissioncache["$userid"]['hassuperrecord'] = false;
				$modcache["$userid"]["$do"] = 1;
			}

			return $modcache["$userid"]["$do"];
		}
	}
	else
	{ // check to see if user is a moderator of specific forum
		$getmodperms = fetch_moderator_permissions($forumid, $userid, $issupermod);

		if (($getmodperms['permissions'] OR $getmodperms['permissions2']) AND empty($do))
		{ // check if user is a mod - no specific permission required
			return true;
		}
		else
		{ // check if user is a mod and has permissions to '$do'
			if ($getmodperms["$set"] & $permission)
			{
				return true;
			}
			else
			{
				$return = false;
				if (!isset($permission))
				{
					($hook = vBulletinHook::fetch_hook('can_moderate_forum')) ? eval($hook) : false;
				}
				return $return;
			}  // if has perms for this action
		}// if is mod for forum and no action set
	} // if forumid=0
}

// #############################################################################
/**
* Returns whether or not vBulletin is running in demo mode
*
* if DEMO_MODE is defined and set to true in config.php this function will return false,
* the main purpose of which is to disable parsing of stuff that is undesirable for a
* board running with a publicly accessible admin control panel
*
* @return	boolean
*/
function is_demo_mode()
{
	return (defined('DEMO_MODE') AND DEMO_MODE == true) ? true : false;
}

// #############################################################################
/**
* Browser detection system - returns whether or not the visiting browser is the one specified
*
* @param	string	Browser name (opera, ie, mozilla, firebord, firefox... etc. - see $is array)
* @param	float	Minimum acceptable version for true result (optional)
*
* @return	boolean
*/
function is_browser($browser, $version = 0)
{
	static $is;
	if (!is_array($is))
	{
		$useragent = strtolower($_SERVER['HTTP_USER_AGENT']);
		$is = array(
			'opera'     => 0,
			'ie'        => 0,
			'mozilla'   => 0,
			'firebird'  => 0,
			'firefox'   => 0,
			'camino'    => 0,
			'konqueror' => 0,
			'safari'    => 0,
			'webkit'    => 0,
			'webtv'     => 0,
			'netscape'  => 0,
			'mac'       => 0,
			'ie64bit'   => 0,
		);

		// detect opera
			# Opera/7.11 (Windows NT 5.1; U) [en]
			# Mozilla/4.0 (compatible; MSIE 6.0; MSIE 5.5; Windows NT 5.0) Opera 7.02 Bork-edition [en]
			# Mozilla/4.0 (compatible; MSIE 6.0; MSIE 5.5; Windows NT 4.0) Opera 7.0 [en]
			# Mozilla/4.0 (compatible; MSIE 5.0; Windows 2000) Opera 6.0 [en]
			# Mozilla/4.0 (compatible; MSIE 5.0; Mac_PowerPC) Opera 5.0 [en]
		if (strpos($useragent, 'opera') !== false)
		{
			preg_match('#opera(/| )([0-9\.]+)#', $useragent, $regs);
			$is['opera'] = $regs[2];
		}

		// detect internet explorer
			# Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; Q312461)
			# Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.0.3705)
			# Mozilla/4.0 (compatible; MSIE 5.22; Mac_PowerPC)
			# Mozilla/4.0 (compatible; MSIE 5.0; Mac_PowerPC; e504460WanadooNL)
		if (strpos($useragent, 'msie ') !== false AND !$is['opera'])
		{
			preg_match('#msie ([0-9\.]+)#', $useragent, $regs);
			$is['ie'] = $regs[1];
			if (strpos($useragent, 'x64') !== false)
			{
				$is['ie64bit'] = 1;
			}
		}

		// detect macintosh
		if (strpos($useragent, 'mac') !== false)
		{
			$is['mac'] = 1;
		}

		// detect safari
			# Mozilla/5.0 (Macintosh; U; PPC Mac OS X; en-us) AppleWebKit/74 (KHTML, like Gecko) Safari/74
			# Mozilla/5.0 (Macintosh; U; PPC Mac OS X; en) AppleWebKit/51 (like Gecko) Safari/51
			# Mozilla/5.0 (Windows; U; Windows NT 6.0; en) AppleWebKit/522.11.3 (KHTML, like Gecko) Version/3.0 Safari/522.11.3
			# Mozilla/5.0 (iPhone; U; CPU like Mac OS X; en) AppleWebKit/420+ (KHTML, like Gecko) Version/3.0 Mobile/1C28 Safari/419.3
			# Mozilla/5.0 (iPod; U; CPU like Mac OS X; en) AppleWebKit/420.1 (KHTML, like Gecko) Version/3.0 Mobile/3A100a Safari/419.3
		if (strpos($useragent, 'applewebkit') !== false)
		{
			preg_match('#applewebkit/([0-9\.]+)#', $useragent, $regs);
			$is['webkit'] = $regs[1];

			if (strpos($useragent, 'safari') !== false)
			{
				preg_match('#safari/([0-9\.]+)#', $useragent, $regs);
				$is['safari'] = $regs[1];
			}
		}

		// detect konqueror
			# Mozilla/5.0 (compatible; Konqueror/3.1; Linux; X11; i686)
			# Mozilla/5.0 (compatible; Konqueror/3.1; Linux 2.4.19-32mdkenterprise; X11; i686; ar, en_US)
			# Mozilla/5.0 (compatible; Konqueror/2.1.1; X11)
		if (strpos($useragent, 'konqueror') !== false)
		{
			preg_match('#konqueror/([0-9\.-]+)#', $useragent, $regs);
			$is['konqueror'] = $regs[1];
		}

		// detect mozilla
			# Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.4b) Gecko/20030504 Mozilla
			# Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.2a) Gecko/20020910
			# Mozilla/5.0 (X11; U; Linux 2.4.3-20mdk i586; en-US; rv:0.9.1) Gecko/20010611
		if (strpos($useragent, 'gecko') !== false AND !$is['safari'] AND !$is['konqueror'])
		{
			// See bug #26926, this is for Gecko based products without a build
			$is['mozilla'] = 20090105;
			if (preg_match('#gecko/(\d+)#', $useragent, $regs))
			{
				$is['mozilla'] = $regs[1];
			}

			// detect firebird / firefox
				# Mozilla/5.0 (Windows; U; WinNT4.0; en-US; rv:1.3a) Gecko/20021207 Phoenix/0.5
				# Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.4b) Gecko/20030516 Mozilla Firebird/0.6
				# Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.4a) Gecko/20030423 Firebird Browser/0.6
				# Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.6) Gecko/20040206 Firefox/0.8
			if (strpos($useragent, 'firefox') !== false OR strpos($useragent, 'firebird') !== false OR strpos($useragent, 'phoenix') !== false)
			{
				preg_match('#(phoenix|firebird|firefox)( browser)?/([0-9\.]+)#', $useragent, $regs);
				$is['firebird'] = $regs[3];

				if ($regs[1] == 'firefox')
				{
					$is['firefox'] = $regs[3];
				}
			}

			// detect camino
				# Mozilla/5.0 (Macintosh; U; PPC Mac OS X; en-US; rv:1.0.1) Gecko/20021104 Chimera/0.6
			if (strpos($useragent, 'chimera') !== false OR strpos($useragent, 'camino') !== false)
			{
				preg_match('#(chimera|camino)/([0-9\.]+)#', $useragent, $regs);
				$is['camino'] = $regs[2];
			}
		}

		// detect web tv
		if (strpos($useragent, 'webtv') !== false)
		{
			preg_match('#webtv/([0-9\.]+)#', $useragent, $regs);
			$is['webtv'] = $regs[1];
		}

		// detect pre-gecko netscape
		if (preg_match('#mozilla/([1-4]{1})\.([0-9]{2}|[1-8]{1})#', $useragent, $regs))
		{
			$is['netscape'] = "$regs[1].$regs[2]";
		}
	}

	// sanitize the incoming browser name
	$browser = strtolower($browser);
	if (substr($browser, 0, 3) == 'is_')
	{
		$browser = substr($browser, 3);
	}

	// return the version number of the detected browser if it is the same as $browser
	if ($is["$browser"])
	{
		// $version was specified - only return version number if detected version is >= to specified $version
		if ($version)
		{
			if ($is["$browser"] >= $version)
			{
				return $is["$browser"];
			}
		}
		else
		{
			return $is["$browser"];
		}
	}

	// if we got this far, we are not the specified browser, or the version number is too low
	return 0;
}

// #############################################################################
/**
* Check webserver's make and model
*
* @param	string	Browser name (apache, iis, samber, nginx... etc. - see $is array)
* @param	float	Minimum acceptable version for true result (optional)
*
* @return	boolean
*/
function is_server($server_name, $version = 0)
{
	static $server;

	// Resolve server
	if (!is_array($server))
	{
		$server_name = preg_quote(strtolower($server_name), '#');
		$server = strtolower($_SERVER['SERVER_SOFTWARE']);
		$matches = array();

		if (preg_match("#(.*)(?:/| )([0-9\.]*)#i", $server, $matches))
		{
			$server = array('name' => $matches[1]);
			$server['version'] = (isset($matches[2]) AND $matches[2]) ? $matches[2] : true;
		}
	}

	if (strpos($server['name'], $server_name))
	{
		if (!$version OR (true === $server['version']) OR ($server['version'] >= $version))
		{
			return true;
		}
	}

	return false;
}

// #############################################################################
/**
* Sets up the Fakey stylevars
*
* @param	array	(ref) Style info array
* @param	array	User info array
*
* @return	array
*/
function fetch_stylevars(&$style, $userinfo)
{
	global $vbulletin;

	if (is_array($style))
	{
		// if we have a buttons directory override, use it
		if ($userinfo['lang_imagesoverride'])
		{
			vB_Template_Runtime::addStyleVar('imgdir_button', str_replace('<#>', $style['styleid'], $userinfo['lang_imagesoverride']), 'imagedir');
		}
	}

	// get text direction and left/right values
	if ($userinfo['lang_options'] & $vbulletin->bf_misc_languageoptions['direction'])
	{
		vB_Template_Runtime::addStyleVar('left', 'left');
		vB_Template_Runtime::addStyleVar('right', 'right');
		vB_Template_Runtime::addStyleVar('textdirection', 'ltr');
	}
	else
	{
		vB_Template_Runtime::addStyleVar('left', 'right');
		vB_Template_Runtime::addStyleVar('right', 'left');
		vB_Template_Runtime::addStyleVar('textdirection', 'rtl');
	}

	if ($userinfo['lang_options'] & $vbulletin->bf_misc_languageoptions['dirmark'])
	{
		vB_Template_Runtime::addStyleVar('dirmark', ($userinfo['lang_options'] & $vbulletin->bf_misc_languageoptions['direction']) ? '&lrm;' : '&rlm;');
	}

	// get the 'lang' attribute for <html> tags
	vB_Template_Runtime::addStyleVar('languagecode', $userinfo['lang_code']);

	// get the 'charset' attribute
	vB_Template_Runtime::addStyleVar('charset', $userinfo['lang_charset']);

	// create the path to YUI depending on the version
	if ($vbulletin->options['remoteyui'] == 1)
	{
		vB_Template_Runtime::addStyleVar('yuipath', 'http://yui.yahooapis.com/' . YUI_VERSION . '/build');
	}
	else if ($vbulletin->options['remoteyui'] == 2)
	{
		vB_Template_Runtime::addStyleVar('yuipath', REQ_PROTOCOL . '://ajax.googleapis.com/ajax/libs/yui/' . YUI_VERSION . '/build');
	}
	else
	{
		vB_Template_Runtime::addStyleVar('yuipath', 'clientscript/yui');
	}

	vB_Template_Runtime::addStyleVar('yuiversion', YUI_VERSION);
	vB_Template_Runtime::addStyleVar('basepath', $vbulletin->options['bburl'] . '/');
}

// #############################################################################
/**
* Function to override various settings in $vbulletin->options depending on user preferences
*
* @param	array	User info array
*/
function fetch_options_overrides($userinfo)
{
	global $vbulletin;

	$vbulletin->options['default_dateformat'] = $vbulletin->options['dateformat'];
	$vbulletin->options['default_timeformat'] = $vbulletin->options['timeformat'];

	if ($userinfo['lang_dateoverride'] != '')
	{
		$vbulletin->options['dateformat'] = $userinfo['lang_dateoverride'];
	}
	if ($userinfo['lang_timeoverride'] != '')
	{
		$vbulletin->options['timeformat'] = $userinfo['lang_timeoverride'];
	}
	if ($userinfo['lang_registereddateoverride'] != '')
	{
		$vbulletin->options['registereddateformat'] = $userinfo['lang_registereddateoverride'];
	}
	if ($userinfo['lang_calformat1override'] != '')
	{
		$vbulletin->options['calformat1'] = $userinfo['lang_calformat1override'];
	}
	if ($userinfo['lang_calformat2override'] != '')
	{
		$vbulletin->options['calformat2'] = $userinfo['lang_calformat2override'];
	}
	if ($userinfo['lang_logdateoverride'] != '')
	{
		$vbulletin->options['logdateformat'] = $userinfo['lang_logdateoverride'];
	}
	if ($userinfo['lang_locale'] != '')
	{
		$locale1 = setlocale(LC_TIME, $userinfo['lang_locale']);
		if (substr($userinfo['lang_locale'], 0, 5) != 'tr_TR')
		{
			$locale2 = setlocale(LC_CTYPE, $userinfo['lang_locale']);
		}
	}

	if (defined('VB_API') AND VB_API === true)
	{
		// vboptions overwrite for API
		$vbulletin->options['dateformat'] = 'm-d-Y';
		$vbulletin->options['timeformat'] = 'h:i A';
		$vbulletin->options['registereddateformat'] = 'm-d-Y';
	}

}

// #############################################################################
/**
* Returns the initial $vbphrase array
*
* @return	array
*/
function init_language()
{
	global $vbulletin, $phrasegroups, $vbphrasegroup;
	global $copyrightyear, $timediff, $timenow, $datenow;

	// define languageid
	define('LANGUAGEID', iif(empty($vbulletin->userinfo['languageid']), $vbulletin->options['languageid'], $vbulletin->userinfo['languageid']));

	// define language direction
	define('LANGUAGE_DIRECTION', iif(($vbulletin->userinfo['lang_options'] & $vbulletin->bf_misc_languageoptions['direction']), 'ltr', 'rtl'));

	// define html language code (lang="xyz")
	define('LANGUAGE_CODE', $vbulletin->userinfo['lang_code']);

	// initialize the $vbphrase array
	$vbphrase = array();

	// populate the $vbphrase array with phrase groups
	foreach ($phrasegroups AS $phrasegroup)
	{
		$tmp = unserialize($vbulletin->userinfo["phrasegroup_$phrasegroup"]);
		if (is_array($tmp))
		{
			$vbphrase = array_merge($vbphrase, $tmp);
		}
		unset($vbulletin->userinfo["phrasegroup_$phrasegroup"], $tmp);
	}

	$vbphrasegroup = array();
	$tmp = unserialize($vbulletin->userinfo["lang_phrasegroupinfo"]);
	if (is_array($tmp))
	{
		$vbphrasegroup = $tmp;
	}
	unset($vbulletin->userinfo['lang_phrasegroupinfo']);

	// prepare phrases for construct_phrase / sprintf use
	//$vbphrase = preg_replace('/\{([0-9]+)\}/siU', '%\\1$s', $vbphrase);

	if (!defined('NOGLOBALPHRASE'))
	{
		// pre-parse some global phrases
		$tzoffset = iif($vbulletin->userinfo['tzoffset'], ' ' . $vbulletin->userinfo['tzoffset']);
		$vbphrase['all_times_are_gmt_x_time_now_is_y'] = construct_phrase($vbphrase['all_times_are_gmt_x_time_now_is_y'], $tzoffset, $timenow, $datenow);
		$vbphrase['vbulletin_copyright_orig'] = $vbphrase['vbulletin_copyright'];
		$vbphrase['vbulletin_copyright'] = construct_phrase($vbphrase['vbulletin_copyright'], $vbulletin->options['templateversion'], $copyrightyear);
		$vbphrase['powered_by_vbulletin'] = construct_phrase($vbphrase['powered_by_vbulletin'], $vbulletin->options['templateversion'], $copyrightyear);
		$vbphrase['timezone'] = construct_phrase($vbphrase['timezone'], $timediff, $timenow, $datenow);
	}

	// all done
	return $vbphrase;
}

// #############################################################################
/**
* Constructs a language chooser HTML menu
*
* @param	string	Marker to prepend each language name with
* @param	boolean	Whether or not this will build the quick chooser menu
* @param	integer	Reference to the total number of languages available in the chooser
*
* @return	string
*/
function construct_language_options($depthmark = '', $quickchooser = false, &$languagecount = 0)
{
	global $vbulletin, $vbphrase;

	$thislanguageid = ($quickchooser ? $vbulletin->userinfo['languageid'] : $vbulletin->userinfo['reallanguageid']);
	if ($thislanguageid == 0 AND $quickchooser)
	{
		$thislanguageid = $vbulletin->options['languageid'];
	}

	$languagelist = '';
	// set the user's 'real language id'
	if (!isset($vbulletin->userinfo['reallanguageid']))
	{
		$vbulletin->userinfo['reallanguageid'] = $vbulletin->userinfo['languageid'];
	}

	if (!$quickchooser)
	{
		if ($thislanguageid == 0)
		{
			$optionselected = 'selected="selected"';
		}
		$optionvalue = 0;
		$optiontitle = $vbphrase['use_forum_default'];
		$languagelist .= render_option_template($optiontitle, $optionvalue, $optionselected, $optionclass);
	}

	if ($vbulletin->languagecache === null)
	{
		$vbulletin->languagecache = array();
	}

	foreach ($vbulletin->languagecache AS $language)
	{
		if ($language['userselect']) # OR $vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])
		{
			++$languagecount;
			if ($thislanguageid == $language['languageid'])
			{
				$optionselected = 'selected="selected"';
			}
			else
			{
				$optionselected = '';
			}
			$optionvalue = $language['languageid'];
			$optiontitle = $depthmark . ' ' . $language['title'];
			$optionclass = '';
			$languagelist .= render_option_template($optiontitle, $optionvalue, $optionselected, $optionclass);
		}
	}

	return $languagelist;
}

// #############################################################################
/**
* Constructs a style chooser HTML menu
*
* @param	integer	Style ID
* @param	string	String repeated before style name to indicate nesting
* @param	boolean	Whether or not to initialize this function (this function is recursive)
* @param	boolean	Whether or not this will build the quick chooser menu
* @param	integer Reference to the total number of styles available in the chooser
*
* @return	string
*/
function construct_style_options($styleid = -1, $depthmark = '', $init = true, $quickchooser = false, &$stylecount = 0)
{
	global $vbulletin, $vbphrase;

	$thisstyleid = ($quickchooser ? $vbulletin->userinfo['styleid'] : $vbulletin->userinfo['realstyleid']);
	if ($thisstyleid == 0)
	{
		$thisstyleid = ($quickchooser ? $vbulletin->userinfo['realstyleid'] : $vbulletin->userinfo['styleid']);
	}
	if ($thisstyleid == 0)
	{
		$thisstyleid = $vbulletin->options['styleid'];
	}

	// initialize various vars
	if ($init)
	{
		$stylesetlist = '';
		// set the user's 'real style id'
		if (!isset($vbulletin->userinfo['realstyleid']))
		{
			$vbulletin->userinfo['realstyleid'] = $vbulletin->userinfo['styleid'];
		}

		if (!$quickchooser)
		{
			if ($thisstyleid == 0)
			{
				$optionselected = 'selected="selected"';
			}
			$optionvalue = 0;
			$optiontitle = $vbphrase['use_forum_default'];
			$stylesetlist .= render_option_template($optiontitle, $optionvalue, $optionselected, $optionclass);
		}
	}

	// check to see that the current styleid exists
	// and workaround a very very odd bug (#2079)
	if (isset($vbulletin->stylecache["$styleid"]) AND is_array($vbulletin->stylecache["$styleid"]))
	{
		$cache =& $vbulletin->stylecache["$styleid"];
	}
	else if (isset($vbulletin->stylecache[$styleid]) AND is_array($vbulletin->stylecache[$styleid]))
	{
		$cache =& $vbulletin->stylecache[$styleid];
	}
	else
	{
		return;
	}

	// loop through the stylecache to get results
	foreach ($cache AS $x)
	{
		foreach ($x AS $style)
		{
			if ($style['userselect'] OR $vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel'])
			{
				$stylecount++;
				if ($thisstyleid == $style['styleid'])
				{
					$optionselected = 'selected="selected"';
				}
				else
				{
					$optionselected = '';
				}
				$optionvalue = $style['styleid'];
				$optiontitle = $depthmark . ' ' . htmlspecialchars_uni($style['title']);
				$optionclass = '';
				$stylesetlist .= render_option_template($optiontitle, $optionvalue, $optionselected, $optionclass);
				$stylesetlist .= construct_style_options($style['styleid'], $depthmark . '--', false, $quickchooser, $stylecount);
			}
			else
			{
				$stylesetlist .= construct_style_options($style['styleid'], $depthmark, false, $quickchooser, $stylecount);
			}
		}
	}

	return $stylesetlist;
}

// #############################################################################
/**
* Saves the specified data into the datastore
*
* @param	string	The name of the datastore item to save
* @param	mixed	The data to be saved
* @param        integer 1 or 0 as to whether this value is to be automatically unserialised on retrieval
*/
function build_datastore($title = '', $data = '', $unserialize = 0)
{
	global $vbulletin;

	if ($title != '')
	{
		/*insert query*/
		$vbulletin->db->query_write("
			REPLACE INTO " . TABLE_PREFIX . "datastore
				(title, data, unserialize)
			VALUES
				('" . $vbulletin->db->escape_string(trim($title)) . "', '" . $vbulletin->db->escape_string(trim($data)) . "', " . intval($unserialize) . ")
		");

		if (method_exists($vbulletin->datastore, 'build'))
		{
			$vbulletin->datastore->build($title, $data);
		}
	}
}

// #############################################################################
/**
* Checks whether or not user came from search engine
*/
function is_came_from_search_engine()
{
	global $vbulletin;

	static $is_came_from_search_engine;	// hey, you, user, you came from search engine?

	if (!isset($is_came_from_search_engine))
	{
		$user_referrer = $_SERVER['HTTP_REFERER'] . '.';		// we're trusting wherever the user claims they're from, if they lie, we can't do anything about it

		if ($vbulletin->options['searchenginereferrers'] = trim($vbulletin->options['searchenginereferrers']))
		{

			$searchengines = preg_split('#\s+#', $vbulletin->options['searchenginereferrers'], -1, PREG_SPLIT_NO_EMPTY);
			foreach ($searchengines AS $searchengine)
			{
				if (strpos($searchengine, '*') === false AND $searchengine{strlen($searchengine) - 1} != '.')
				{
					$searchengine .= '.';
				}

				$searchengine_regex = str_replace('\*', '(.*)', preg_quote($searchengine, '#'));
				if (preg_match('#' . $searchengine_regex . '#U', $user_referrer))
				{
					$is_came_from_search_engine = true;
					return $is_came_from_search_engine;
				}
			}
		}

		// if nothing to match against (or we didn't return earlier)
		$is_came_from_search_engine = false;
	}

	return $is_came_from_search_engine;
}

// #############################################################################
/**
* Updates the LoadAverage DataStore
*/

function update_loadavg()
{
	global $vbulletin;

	if (!isset($vbulletin->loadcache))
	{
		$vbulletin->loadcache = array();
	}

	if (function_exists('exec') AND $stats = @exec('uptime 2>&1') AND trim($stats) != '' AND preg_match('#: ([\d.,]+),?\s+([\d.,]+),?\s+([\d.,]+)$#', $stats, $regs))
	{
		$vbulletin->loadcache['loadavg'] = $regs[2];
	}
	else if (@file_exists('/proc/loadavg') AND $filestuff = @file_get_contents('/proc/loadavg'))
	{
		$loadavg = explode(' ', $filestuff);

		$vbulletin->loadcache['loadavg'] = $loadavg[1];
	}
	else
	{
 		$vbulletin->loadcache['loadavg'] = 0;
	}

	$vbulletin->loadcache['lastcheck'] = TIMENOW;
	build_datastore('loadcache', serialize($vbulletin->loadcache), 1);
}

// #############################################################################
/**
* Escapes quotes in strings destined for Javascript
*
* @param	string	String to be prepared for Javascript
* @param	string	Type of quote (single or double quote)
*
* @return	string
*/
function addslashes_js($text, $quotetype = "'")
{
	if ($quotetype == "'")
	{
		// single quotes
		$replaced = str_replace(array('\\', '\'', "\n", "\r"), array('\\\\', "\\'","\\n", "\\r"), $text);
	}
	else
	{
		// double quotes
		$replaced = str_replace(array('\\', '"', "\n", "\r"), array('\\\\', "\\\"","\\n", "\\r"), $text);
	}

	$replaced = preg_replace('#(-(?=-))#', "-$quotetype + $quotetype", $replaced);
	$replaced = preg_replace('#</script#i', "<\\/scr$quotetype + {$quotetype}ipt", $replaced);

	return $replaced;
}

// #############################################################################
/**
* Returns the provided string with occurences of replacement variables replaced with their appropriate replacement values
*
* @param	string	Text containing replacement variables
* @param 	array	Override global $style if specified
*
* @return	string
*/
function process_replacement_vars($newtext, $paramstyle = false)
{
	global $vbulletin;
	static $replacementvars;

	if (connection_status())
	{
		exit;
	}

	if (is_array($paramstyle))
	{
		$style =& $paramstyle;
	}
	else
	{
		$style =& $GLOBALS['style'];
	}

	($hook = vBulletinHook::fetch_hook('replacement_vars')) ? eval($hook) : false;

	// do vBulletin 3 replacement variables
	if (!empty($style['replacements']))
	{
		if (!isset($replacementvars["$style[styleid]"]))
		{
			$replacementvars["$style[styleid]"] = unserialize($style['replacements']);
		}

		if (is_array($replacementvars["$style[styleid]"]) AND !empty($replacementvars["$style[styleid]"]))
		{
			$newtext = preg_replace(array_keys($replacementvars["$style[styleid]"]), $replacementvars["$style[styleid]"], $newtext);
		}
	}

	return $newtext;
}

// #############################################################################
/**
* Finishes off the current page (using templates), prints it out to the browser and halts execution
*
* @param	string	The HTML of the page to be printed
* @param	boolean	Send the content length header?
*/
function print_output($vartext, $sendheader = true)
{
	global $querytime, $vbulletin, $show, $vbphrase;

	if (VB_API)
	{
		$template = vB_Template::create('response');
		$template->register('response', $vartext);
		$vartext = $template->render(true, true);
	}

	if (!VB_API AND $vbulletin->options['addtemplatename'])
	{
		if ($doctypepos = @strpos($vartext, vB_Template_Runtime::fetchStyleVar('htmldoctype')))
		{
			$comment = substr($vartext, 0, $doctypepos);
			$vartext = substr($vartext, $doctypepos + strlen(vB_Template_Runtime::fetchStyleVar('htmldoctype')));
			$vartext = vB_Template_Runtime::fetchStyleVar('htmldoctype') . "\n" . $comment . $vartext;
		}
	}

	if (!VB_API AND (!empty($vbulletin->db->explain) OR $vbulletin->debug))
	{
		$totaltime = microtime(true) - TIMESTART;

		$vartext .= "<!-- Page generated in " . vb_number_format($totaltime, 5) . " seconds with " . $vbulletin->db->querycount . " queries -->";
	}

	// set cookies for displayed notices
	if ($show['notices'] AND !defined('NOPMPOPUP') AND !empty($vbulletin->np_notices_displayed) AND is_array($vbulletin->np_notices_displayed))
	{
		$np_notices_cookie = $_COOKIE[COOKIE_PREFIX . 'np_notices_displayed'];
		vbsetcookie('np_notices_displayed',
			($np_notices_cookie ? "$np_notices_cookie," : '') . implode(',', $vbulletin->np_notices_displayed),
			false
		);
	}

	// debug code
	global $DEVDEBUG, $vbcollapse;
	if (!VB_API AND $vbulletin->debug)
	{
		devdebug('php_sapi_name(): ' . SAPI_NAME);

		$messages = '';
		if (is_array($DEVDEBUG))
		{
			foreach($DEVDEBUG AS $debugmessage)
			{
				$messages .= "\t<option>" . htmlspecialchars_uni($debugmessage) . "</option>\n";
			}
		}

		if (!empty(vB_Template::$template_usage))
		{
			$tempusagecache = vB_Template::$template_usage;
			$_TEMPLATEQUERIES = vB_Template::$template_queries;

			unset($tempusagecache['board_inactive_warning'], $_TEMPLATEQUERIES['board_inactive_warning']);

			ksort($tempusagecache);
			foreach ($tempusagecache AS $template_name => $times)
			{
				$tempusagecache["$template_name"] =
					"<span class=\"shade\" style=\"float:right\">($times)</span>" .
						((isset($_TEMPLATEQUERIES["$template_name"]) AND $_TEMPLATEQUERIES["$template_name"]) ?
							"<span style=\"color:red; font-weight:bold\">$template_name</span>" : $template_name);
			}
		}
		else
		{
			$tempusagecache = array();
		}

		$hook_usage = '';
		$hook_total = 0;
		foreach (vBulletinHook::fetch_hookusage() AS $hook_name => $has_code)
		{
			$hook_usage .= '<li class="smallfont' . (!$has_code ? ' shade' : '') . '">' . $hook_name . '</li>';
			$hook_total++;
		}
		if (!$hook_usage)
		{
			$hook_usage = '<li class="smallfont">&nbsp;</li>';
		}

		$phrase_groups = '';
		sort($GLOBALS['phrasegroups']);
		foreach ($GLOBALS['phrasegroups'] AS $phrase_group)
		{
			$phrase_groups .= '<li class="smallfont">' . $phrase_group . '</li>';
		}
		if (!$phrase_groups)
		{
			$phrase_groups = '<li class="smallfont">&nbsp;</li>';
		}

		$vbcollapse['collapseimg_debuginfo'] = (!empty($vbcollapse['collapseimg_debuginfo']) ? $vbcollapse['collapseimg_debuginfo'] : '');
		$vbcollapse['collapseobj_debuginfo'] = (!empty($vbcollapse['collapseobj_debuginfo']) ? $vbcollapse['collapseobj_debuginfo'] : '');

		$debughtml = "
			<div class=\"block\" id=\"debuginfo\" style=\"width:800px; margin:4px auto;\">
				<h2 class=\"blockhead collapse\">
					<a style=\"float:" . vB_Template_Runtime::fetchStyleVar('right') . ";\" href=\"" . htmlspecialchars_uni($vbulletin->input->fetch_relpath()) . "#\" title=\"Close Debug Information\" onclick=\"document.getElementById('debuginfo').parentNode.removeChild(document.getElementById('debuginfo')); return false;\">X</a>
						vBulletin {$vbulletin->options['templateversion']} Debug Information
				</h2>
				<div style=\"border:" . vB_Template_Runtime::fetchStyleVar('blockhead_border') . "; border-top:0;\">
					<div class=\"blockbody\">
						<div class=\"blockrow\">
						<ul style=\"list-style:none; margin:0px; padding:0px\">
							<li class=\"smallfont\" style=\"display:inline; margin-right:8px\"><span class=\"shade\">Page Generation</span> " . vb_number_format($totaltime, 5) . " seconds</li>
							" . (function_exists('memory_get_usage') ? "<li class=\"smallfont\" style=\"display:inline; margin-right:8px\"><span class=\"shade\">Memory Usage</span> " . number_format(memory_get_usage() / 1024) . 'KB</li>' : '') . "
							<li class=\"smallfont\" style=\"display:inline; margin-right:8px\"><span class=\"shade\">Queries Executed</span> " . (empty($_TEMPLATEQUERIES) ? $vbulletin->db->querycount : "<span title=\"Uncached Templates!\" style=\"color:red; font-weight:bold\">{$vbulletin->db->querycount}</span>") . " <a href=\"" . (htmlspecialchars($vbulletin->scriptpath)) . (strpos($vbulletin->scriptpath, '?') === false ? '?' : '&amp;') . "explain=1\" target=\"_blank\" title=\"Explain Queries\">(?)</a></li>
						</ul>
						</div>
					</div>
					<div class=\"blocksubhead collapse\">
						<a style=\"top:5px;\" class=\"collapse\" id=\"collapse_debuginfo_body\" href=\"#top\"><img src=\"" . vB_Template_Runtime::fetchStyleVar('imgdir_button') . "/collapse_40b.png\" alt=\"\" title=\"Collapse Debug Information\" /></a>
						More Information
					</div>
					<div class=\"blockbody\" id=\"debuginfo_body\">
						<div class=\"blockrow\">
							<div style=\"width:48%; float:left;\">
								<div style=\"margin-bottom:6px; font-weight:bold;\">Template Usage (" . sizeof($tempusagecache) . "):</div>
						<ul style=\"list-style:none; margin:0px; padding:0px\"><li class=\"smallfont\">" . implode('</li><li class="smallfont">', $tempusagecache) . "&nbsp;</li></ul>
						<hr style=\"margin:10px 0px 10px 0px\" />

								<div style=\"margin-bottom:6px; font-weight:bold;\">Phrase Groups Available (" . sizeof($GLOBALS['phrasegroups']) . "):</div>
						<ul style=\"list-style:none; margin:0px; padding:0px\">$phrase_groups</ul>
							</div>
							<div style=\"width:48%; float:right;\">
								<div style=\"margin-bottom:6px; font-weight:bold;\">Included Files (" . sizeof($included_files = get_included_files()) . "):</div>
						<ul style=\"list-style:none; margin:0px; padding:0px\"><li class=\"smallfont\">" . implode('</li><li class="smallfont">', str_replace(str_replace('\\', '/', DIR) . '/', '', preg_replace('#^(.*/)#si', '<span class="shade">./\1</span>', str_replace('\\', '/', $included_files)))) . "&nbsp;</li></ul>
						<hr style=\"margin:10px 0px 10px 0px\" />

								<div style=\"margin-bottom:6px; font-weight:bold;\">Hooks Called ($hook_total):</div>
						<ul style=\"list-style:none; margin:0px; padding:0px\">$hook_usage</ul>
							</div>
							<br style=\"clear:both;\" />
						</div>
					</div>
					<div class=\"blockbody\">
						<div class=\"blockrow\">
							<label>Messages:<select style=\"display:block; width:100%\">$messages</select></label>
						</div>
					</div>
				</div>
			</div>
		";

		$vartext = str_replace('</body>', "<!--start debug html-->$debughtml<!--end debug html-->\n</body>", $vartext);
	}
	// end debug code

	$output = process_replacement_vars($vartext);

	if (!VB_API AND ($vbulletin->debug AND function_exists('memory_get_usage')))
	{
		$output = preg_replace('#(<!--querycount-->Executed <b>\d+</b> queries<!--/querycount-->)#siU', 'Memory Usage: <strong>' . number_format((memory_get_usage() / 1024)) . 'KB</strong>, \1', $output);
	}

	// parse PHP include ##################
	($hook = vBulletinHook::fetch_hook('global_complete')) ? eval($hook) : false;

	// make sure headers sent returns correctly
	if (ob_get_level() AND ob_get_length())
	{
		ob_end_flush();
	}

	if (defined('VB_API') AND VB_API === true AND !headers_sent() AND $sendheader)
	{
		// API Verification
		global $VB_API_REQUESTS;
		if (!in_array($VB_API_REQUESTS['api_m'], array('login_login', 'login_logout')))
		{
			$sign = md5($output . $vbulletin->apiclient['apiaccesstoken'] . $vbulletin->apiclient['apiclientid'] . $vbulletin->apiclient['secret'] . $vbulletin->options['apikey']);
			@header('Authorization: ' . $sign);
		}

		if (!$vbulletin->debug OR !$vbulletin->GPC['debug'])
		{
			//  JSON header
			@header('Content-Type: application/json');
		}

	}

	if (!headers_sent())
	{
		if ($vbulletin->options['gzipoutput'])
		{
			$output = fetch_gzipped_text($output, $vbulletin->options['gziplevel']);
		}

		if ($sendheader)
		{
			@header('Content-Length: ' . strlen($output));
		}
	}

	// Trigger shutdown event
	$vbulletin->shutdown->shutdown();

	if (defined('NOSHUTDOWNFUNC'))
	{
		exec_shut_down();
	}

	// show regular page
	if (empty($vbulletin->db->explain) OR (defined('VB_API') AND VB_API === true))
	{
		echo $output;
	}
	// show explain
	elseif (!VB_API)
	{
		$querytime = $vbulletin->db->time_total;
		echo "\n<b>Page generated in $totaltime seconds with " . $vbulletin->db->querycount . " queries,\nspending $querytime doing MySQL queries and " . ($totaltime - $querytime) . " doing PHP things.\n\n<hr />Shutdown Queries:</b>" . (defined('NOSHUTDOWNFUNC') ? " <b>DISABLED</b>" : '') . "<hr />\n\n";
	}

	// broken if zlib.output_compression is on with Apache 2
	if (SAPI_NAME != 'apache2handler' AND SAPI_NAME != 'apache2filter')
	{
		flush();
	}

	exit;
}

// #############################################################################
/**
* Converts raw link information into an appropriate URL
* Verifies that the requested linktype can be handled
*
* @param	string	Type of link, 'thread', etc
* @param	array		Specific information relevant to the page being linked to, $threadinfo, etc
* @param	array		Other information relevant to the page being linked to
* @param	string	Override the default $linkinfo[userid] with $linkinfo[$primaryid]
* @param	string	Override the default $linkinfo[title] with $linkinfo[$primarytitle]
* @param	bool	Get the raw, canonical url.  Set to true if the url is for a redirect.
*/
function fetch_seo_url($link, $linkinfo, $pageinfo = null, $primaryid = null, $primarytitle = null, $canonical = false)
{
	global $vbulletin;

	require_once(DIR . '/includes/class_friendly_url.php');
	$friendlyurl = vB_Friendly_Url::fetchLibrary($vbulletin, $link, $linkinfo, $pageinfo, $primaryid, $primarytitle);

	return $friendlyurl->get_url(false, $canonical);
}

/**
* Verifies that we are at the proper canonical seo url based on admin settings
*
* @param	string	Type of link, 'thread', etc
* @param	array		Specific information relevant to the page being linked to, $threadinfo, etc
* @param	array		Other information relevant to the page being linked to
* @param	string	Override the default $linkinfo[userid] with $linkinfo[$primaryid]
* @param	string	Override the default $linkinfo[title] with $linkinfo[$primarytitle]
*/
function verify_seo_url($link, $linkinfo, $pageinfo = null, $primaryid = null, $primarytitle = null)
{
	global $vbulletin;

	require_once(DIR . '/includes/class_friendly_url.php');

	// Check we have something to compare
	if (empty($linkinfo) OR !isset($vbulletin->input->friendly_uri))
	{
		return;
	}

	// Redirect if the current request is not canonical
	$canonical = vB_Friendly_Url::fetchLibrary($vbulletin, $link . '|nosession', $linkinfo, $pageinfo, $primaryid, $primarytitle);
	$canonical->redirect_canonical_url($vbulletin->input->friendly_uri);


	// Set the WOLPATH
	$url = $canonical->get_url(FRIENDLY_URL_OFF);
	$vbulletin->session->set('location', $url);
	define('WOLPATH', $vbulletin->input->strip_sessionhash($url));
}


function verify_subdirectory_url($prefix, $scriptid = null)
{
	global $vbulletin;
	if (!$prefix)
	{
		return;
	}

	// Never redirect a post
	// unless we're in debug mode and we wan't to error out on the bad urls
	// unless again its ajax... we're relying on the old urls for ajax because
	// a) it doesn't matter, users and search engines one see them and b) getting the
	// url logic into javascript is difficult.
	if (('GET' != $_SERVER['REQUEST_METHOD']) AND
		(!(defined('DEV_REDIRECT_404') AND DEV_REDIRECT_404) OR $vbulletin->GPC['ajax']) )
	{
		return;
	}

	if (defined('FRIENDLY_URL_LINK'))
	{
		$friendly = vB_Friendly_Url::fetchLibrary($vbulletin, FRIENDLY_URL_LINK . '|nosession');
		if (vB_Friendly_Url::getMethodUsed() == FRIENDLY_URL_REWRITE)
		{
			$scriptid = $friendly->getRewriteSegment();
		}
		else
		{
			$scriptid = $friendly->getScript();
		}
	}
	else
	{
		if (is_null($scriptid))
		{
			$scriptid = THIS_SCRIPT;
		}
	}

	$pos = strrpos(VB_URL_CLEAN, '/' . $scriptid);
	if ($pos === false)
	{
		//if we don't know what this url is, then lets not try to mess with it.
		//this came up with the situation where the main index.php is set to forum
		//but the forums have been moved to a subdirectory.  Doesn't make much sense
		//but we should handle it gracefully.
		return;
	}

	$base_part = substr(VB_URL_CLEAN, 0, $pos);
	$script_part = substr(VB_URL_CLEAN, $pos+1);

	if (!preg_match('#' . preg_quote($prefix) . '$#', $base_part))
	{
		//force the old urls to 404 for testing purposes
		if (defined('DEV_REDIRECT_404') AND DEV_REDIRECT_404)
		{
			//dev only, no need to phrase the error.
			header("HTTP/1.0 404 Not Found");
			standard_error("old style url found, shouldn't get here");
		}
		else
		{
			$url = $prefix . '/' . $script_part;
			// redirect to the correct url
			exec_header_redirect($url, 301);
		}
	}
}


function verify_forum_url($scriptid = null)
{
	global $vbulletin;
	return verify_subdirectory_url($vbulletin->options['vbforum_url'], $scriptid);
}


/**
 * Implodes an array using both values and keys
 *
 * @param string $glue1							- Glue between key and value
 * @param string $glue2							- Glue between value and key
 * @param mixed $array							- Arr to implode
 * @param boolean $skip_empty					- Whether to skip empty elements
 * @return string								- The imploded result
 */
function implode_both($glue1 = '', $glue2 = '', $array, $skip_empty = false)
{
	if (!is_array($array))
	{
		return '';
	}

	$newarray = array();
	foreach ($array as $key => $val)
	{
		if (!$skip_empty OR !empty($val))
		{
			$newarray[$key] = $key . $glue1 . $val;
		}
	}

	return implode($glue2, $newarray);
}

/**
 * Implodes an assoc array into a partial url query string
 *
 * @param mixed $array							- Array to parse
 * @param boolean $skip_empty					- Whether to skip empty elements
 * @return string								- The parsed result
 */
function urlimplode($array, $skip_empty = true, $skip_urlencode = false, $preserve_uni = false)
{
	if (!$skip_urlencode)
	{
		foreach ($array AS $key => $value)
		{
			$array[$key] = ($preserve_uni ? urlencode_uni($value) : urlencode($value));
		}
	}

	return implode_both('=', ($skip_urlencode ? '&' : '&amp;'), $array, $skip_empty);
}

// #############################################################################
/**
* Performs general clean-up after the system exits, such as running shutdown queries
*/
function exec_shut_down()
{
	global $vbulletin;
	global $foruminfo, $threadinfo, $calendarinfo;

	if (VB_AREA == 'Install' OR VB_AREA == 'Upgrade')
	{
		return;
	}

	$vbulletin->db->unlock_tables();

	if (!empty($vbulletin->userinfo['badlocation']))
	{
		$threadinfo = array('threadid' => 0);
		$foruminfo = array('forumid' => 0);
		$calendarinfo = array('calendarid' => 0);
	}

	if (!$vbulletin->options['bbactive'] AND !($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['cancontrolpanel']))
	{ // Forum is disabled and this is not someone with admin access
		$vbulletin->userinfo['badlocation'] = 2;
	}

	if (class_exists('vBulletinHook', false))
	{
		($hook = vBulletinHook::fetch_hook('global_shutdown')) ? eval($hook) : false;
	}

	if (is_object($vbulletin->session))
	{
		if (!defined('LOCATION_BYPASS'))
		{
			$vbulletin->session->set('inforum', (!empty($foruminfo['forumid']) ? $foruminfo['forumid'] : 0));
			$vbulletin->session->set('inthread', (!empty($threadinfo['threadid']) ? $threadinfo['threadid'] : 0));
			$vbulletin->session->set('incalendar', (!empty($calendarinfo['calendarid']) ? $calendarinfo['calendarid'] : 0));
		}
		$vbulletin->session->set('badlocation', (!empty($vbulletin->userinfo['badlocation']) ? $vbulletin->userinfo['badlocation'] : ''));
		if ($vbulletin->session->vars['loggedin'] == 1 AND !$vbulletin->session->created)
		{
			# If loggedin = 1, this is out first page view after a login so change value to 2 to signify we are past the first page view
			# We do a DST update check if loggedin = 1
			$vbulletin->session->set('loggedin', 2);
			if (!empty($vbulletin->profilefield['required']))
			{
				foreach ($vbulletin->profilefield['required'] AS $fieldname => $value)
				{
					if (!isset($vbulletin->userinfo["$fieldname"]) OR $vbulletin->userinfo["$fieldname"] === '')
					{
						$vbulletin->session->set('profileupdate', 1);
						break;
					}
				}
			}
		}
		$vbulletin->session->save();
	}

	if (is_array($vbulletin->db->shutdownqueries))
	{
		$vbulletin->db->hide_errors();
		foreach($vbulletin->db->shutdownqueries AS $name => $query)
		{
			if (!empty($query) AND ($name !== 'pmpopup' OR !defined('NOPMPOPUP')))
			{
				$vbulletin->db->query_write($query);
			}
		}
		$vbulletin->db->show_errors();
	}

	exec_mail_queue();

	// Make sure the database connection is closed since it can get hung up for a long time on php4 do to the mysterious echo() lagging issue
	// If NOSHUTDOWNFUNC is defined then this function should always be the last one called, before echoing of data
	if (defined('NOSHUTDOWNFUNC'))
	{
		$vbulletin->db->close();
	}
	$vbulletin->db->shutdownqueries = array();
	// bye bye!
}

/**
 * Spreads an array of values across the given number of stepped levels based on
 * their standard deviation from the mean value.
 *
 * The function accepts an array of $id => $value and returns $id => $level.
 *
 * @param array $values							- Array of id => values
 * @param integer $levels						- Number of levels to assign
 */
function fetch_standard_deviated_levels($values, $levels=5)
{
	if (!$count = sizeof($values))
	{
		return array();
	}

	$total = $summation = 0;
	$results = array();

	// calculate the total
	foreach ($values AS $value)
	{
		$total += $value;
	}

	// calculate the mean
	$mean = $total / $count;

	// calculate the summation
	foreach ($values AS $id => $value)
	{
		$summation += pow(($value - $mean), 2);
	}

	$sd = sqrt($summation / $count);

	if ($sd)
	{
		$sdvalues = array();
		$lowestsds = 0;
		$highestsds = 0;

		// find the max and min standard deviations
		foreach ($values AS $id => $value)
		{
			$value = (($value - $mean) / $sd);
			$values[$id] = $value;

			$lowestsds = min($value, $lowestsds);
			$highestsds = max($value, $highestsds);
		}

		foreach ($values AS $id => $value)
		{
			// normalize the std devs to 0 - 1, then map back to 1 - #levls
			$values[$id] = round((($value - $lowestsds) / ($highestsds - $lowestsds)) * ($levels - 1)) + 1;
		}
	}
	else
	{
		foreach ($values AS $id => $value)
		{
			$values[$id] = round($levels / 2);
		}
	}

	return $values;
}

/**
 * Checks if Facebook is enabled, and applicable to the current request
 *
 * @return bool, true if Facebook code should be run for the current request
 */
function is_facebookenabled()
{
	global $vbulletin;
	static $fb_funcs = false;

	// on top of facebook being enabled, make sure we are not skipping session for this request
	if ($vbulletin->options['enablefacebookconnect'] AND !defined('SKIP_SESSIONCREATE'))
	{
		// make sure to include facebook objects if they aren't already
		if (!class_exists('vB_Facebook'))
		{
			require_once(DIR . '/includes/class_facebook.php');
			require_once(DIR . '/includes/functions_facebook.php');
		}
		return true;
	}
	else
	{
		if (!$fb_funcs)
		{
			$fb_funcs = true;
			// Only try and load this once.
			require_once(DIR . '/includes/functions_facebook.php');
		}
		return false;
	}
}

/**
 * Ensures the framework is bootstrapped.
 * TODO: We're getting to a point where we may as well always bootstrap it during init.
 */
function bootstrap_framework()
{
	if (!class_exists('vB_Bootstrap_Framework'))
	{
		require_once(DIR . '/includes/class_bootstrap_framework.php');
		vB_Bootstrap_Framework::init();
	}
}

/** Checks to see if the current user has at least read access to the CMS root node.
*
* @return	boolean
**/

function can_see_cms()
{
	global $vbulletin;

	if (!$vbulletin->products['vbcms'])
	{
		return false;
	}

	if (class_exists('vBCMS_Permissions', false))
	{
		return vBCMS_Permissions::canView(1);
	}

	$ids = array();
	$rawids = explode(',', $vbulletin->userinfo['usergroupid'] . ',' . $vbulletin->userinfo['membergroupids']);
	foreach ($rawids AS $id)
	{
		if (($id = intval($id)) > 0)
		{
			$ids[] = $id;
		}
	}

	if (!empty($ids))
	{
		$perms = $vbulletin->db->query_first("
			SELECT MAX(permissions & 1) AS perm
			FROM " . TABLE_PREFIX . "cms_permissions
			WHERE nodeid = 1 AND usergroupid IN (" . implode(',', $ids) . ")
		");

		return (intval($perms['perm']) > 0);
	}

	return false;
}


/**
 * Clear out autosave content .. we need this all over the place
 */
function clear_autosave_text($contenttypeid, $contentid, $parentcontentid, $userid)
{
	global $vbulletin;
	
	$vbulletin->db->query_write("
		DELETE FROM " . TABLE_PREFIX . "autosave
		WHERE
			contenttypeid = '" . $vbulletin->db->escape_string($contenttypeid) . "'
				AND
			contentid = " . intval($contentid) . "
				AND
			parentcontentid = " . intval($parentcontentid) . "
				AND
			userid = " . intval($userid) . "
	");
}

/**
 * Write mobile device usage .. 
 */
function post_vb_api_details($contenttype, $contentid)
{
	global $vbulletin;

	if (defined('VB_API') AND VB_API === true)
	{
		bootstrap_framework();	// ensure framework is loaded
		if ($contenttypeid = vB_Types::instance()->getContentTypeID($contenttype))
		{
			$vbulletin->db->query_write("
				INSERT INTO " . TABLE_PREFIX . "apipost
					(
						contenttypeid, contentid, userid, clientname, clientversion, platformname, platformversion
					)
				VALUES
					(
						{$contenttypeid},
						{$contentid},
						{$vbulletin->userinfo['userid']},
						'" . $vbulletin->db->escape_string($vbulletin->apiclient['clientname']) . "',
						'" . $vbulletin->db->escape_string($vbulletin->apiclient['clientversion']) . "',
						'" . $vbulletin->db->escape_string($vbulletin->apiclient['platformname']) . "',
						'" . $vbulletin->db->escape_string($vbulletin->apiclient['platformversion']) . "'
					)
			");
		}
	}
}

/**
 * Wrapper for the PHP function var_export to work around PHP bug #52534. See: http://bugs.php.net/bug.php?id=52534
 *
 * @param	mixed	The variable to be exported
 * @param	bool	Whether or not to return the output
 * @param	int	Recursion level (for internal function recursive calls only)
 *
 * @return	mixed	Retuns the exported value, or null
 */
function vb_var_export($expression, $return = false, $level = 0)
{
	// test if workaround is needed
	static $needs_workaround = null;
	if ($needs_workaround === null)
	{
		$test = var_export(array(-1 => 1), true);
		$needs_workaround = (strpos($test, '-1') === false);
	}

	// skip the workaround when appropriate
	if (!$needs_workaround)
	{
		return var_export($expression, $return);
	}

	// begin workaround
	static $tab = '  ';
	$output = '';

	if (is_array($expression))
	{
		// use this function for arrays
		$tabs = str_repeat($tab, $level);

		if ($level > 0)
		{
			$output .= "\n$tabs";
		}

		$output .= "array (\n";
		foreach ($expression AS $k => $v)
		{
			// use var_export for keys, as they cannot be arrays
			$output .= "$tabs$tab" . var_export($k, true) . ' => ' . vb_var_export($v, true, $level + 1) . ",\n";
		}

		$output .= "$tabs)";
	}
	else
	{
		// use var_export for everything but arrays
		$output .= var_export($expression, true);
	}

	if ($level == 0)
	{
		// done

		// strip trailing spaces or tabs on each line to mimic var_export
		$output = preg_replace("/[ \t]+(\r\n|\n|\r)/", '$1', $output);

		if ($return)
		{
			return $output;
		}
		else
		{
			echo $output;
			return null;
		}
	}
	else
	{
		// return from recursive call
		return $output;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 46027 $
|| ####################################################################
\*======================================================================*/
