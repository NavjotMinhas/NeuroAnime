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
 * Validation utility class.
 * Contains miscellaneous methods for validating a value.  Methods should take
 * $value as it's first parameter and a reference to $error as it's second.
 *
 * Methods return the value if validation succeeds, or boolean false if validation
 * fails.  If validation fails, $error should be populated with a string or
 * vB_Phrase, or an array of either.
 *
 * $value can be transformed into something valid and returned.  This allows filter
 * methods to be used for validation, or validation methods that also perform
 * filtering.
 *
 * @TODO: This class is primarily made up of methods copied from legacy code that
 * have not been updated to work with the framework.  These can be found under the
 * 'Unfinished' section.  If brought up to date, methods should be moved out of this
 * section.  Any remaining methods will be removed before the class goes into
 * production.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 28694 $
 * @since $Date: 2008-12-04 16:12:22 +0000 (Thu, 04 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */
abstract class vB_Validate
{
	/**
	 * Verifies that a styleid is valid.
	 *
	 * @param mixed $value						- The value to validate
	 * @param mixed $error						- The var to assign an error to
	 * @return mixed | bool						- The filtered value or boolean false
	 */
	public static function StyleID($value, &$error)
	{
		if (!vB_Style::validStyle($value))
		{
			$error = new vB_Phrase('error', 'validation_style_x', htmlspecialchars($value));
			return false;
		}

		return $value;
	}


	/**
	 * Verifies that the specified user exists
	 *
	 * @param mixed $value						- The value to validate
	 * @param mixed $error						- The var to assign an error to
	 * @return mixed | bool						- The filtered value or boolean false
	 */
	public static function userID($value, &$error)
	{
		return $value;
	}


	/*Unfinished====================================================================*/
	/*TODO: Use these methods as reference as needed, will be removed before release*/


	/**
	 * Verifies the length of a string is within a certain range
	 *
	 * @param mixed $value						- The value to validate
	 * @param mixed $error						- The var to assign an error to
	 * @return mixed | bool						- The filtered value or boolean false
	 */
	public static function stringLength($value, &$error, $minlength = 0, $maxlength = 0)
	{
		$value = trim(preg_replace('/&#(0*32|x0*20);/', ' ', $value));
		$max_error = $min_error = false;

		if (!$maxlength AND !$maxlength)
		{
			return $value;
		}

		if ($maxlength AND ($length = vbstrlen($value) > $maxlength))
		{
			$max_error = true;
		}

		if ($minlength AND ($length = vbstrlen($value)) < $minlength)
		{
			$min_error = true;

		}

		if ($max_error OR $min_error)
		{
			if ($max_error AND $min_error)
			{
				$error = new vB_Phrase('error', 'validation_length_between_x_y_z', $length, intval($maxlength), intval($minlength));
			}
			else if ($max_error)
			{
				$error = new vB_Phrase('error', 'validation_toolong_x_y', $length, intval($maxlength));
			}
			else if ($min_error)
			{
				$error = new vB_Phrase('error', 'validation_tooshort_x_y', $length, intval($minlength));
			}

			return false;
		}

		return $value;
	}


	/**
	 * Verifies that the provided username is valid, and attempts to correct it if it is not valid
	 *
	 * @param mixed $value						- The value to validate
	 * @param mixed $error						- The var to assign an error to
	 * @return mixed | bool						- The filtered value or boolean false
	 */
	public static function username($value, &$error)
	{
		// this is duplicated from the user manager

		// fix extra whitespace and invisible ascii stuff
		$username = trim(preg_replace('#[ \r\n\t]+#si', ' ', strip_blank_ascii($value, ' ')));
		$username_raw = $value;
		$value =& $username;

		$username = preg_replace(
			'/&#([0-9]+);/ie',
			"convert_unicode_char_to_charset('\\1', vB_Template_Runtime::fetchStyleVar('charset'))",
			$username
		);

		$username = preg_replace(
			'/&#0*([0-9]{1,2}|1[01][0-9]|12[0-7]);/ie',
			"convert_int_to_utf8('\\1')",
			$username
		);

		$username = str_replace(chr(0), '', $username);
		$username = trim($username);

		$length = vbstrlen($username);
		if ($length < $this->registry->options['minuserlength'])
		{
			// name too short
			$this->error('usernametooshort', $this->registry->options['minuserlength']);
			return false;
		}
		else if ($length > $this->registry->options['maxuserlength'])
		{
			// name too long
			$this->error('usernametoolong', $this->registry->options['maxuserlength']);
			return false;
		}
		else if (preg_match('/(?<!&#[0-9]{3}|&#[0-9]{4}|&#[0-9]{5});/', $username))
		{
			// name contains semicolons
			$this->error('username_contains_semi_colons');
			return false;
		}
		else if ($username != fetch_censored_text($username))
		{
			// name contains censored words
			$this->error('censorfield', $this->registry->options['contactuslink']);
			return false;
		}
		else if ($this->dbobject->query_first("
			SELECT userid, username FROM " . TABLE_PREFIX . "user
			WHERE userid != " . intval($this->existing['userid']) . "
			AND
			(
				username = '" . $this->dbobject->escape_string(htmlspecialchars_uni($username)) . "'
				OR
				username = '" . $this->dbobject->escape_string(htmlspecialchars_uni($username_raw)) . "'
			)
		"))
		{
			// name is already in use
			$this->error('usernametaken', htmlspecialchars_uni($username), $this->registry->session->vars['sessionurl']);
			return false;
		}
		else if (!empty($this->registry->options['illegalusernames']))
		{
			// check for illegal username
			$usernames = preg_split('/[ \r\n\t]+/', $this->registry->options['illegalusernames'], -1, PREG_SPLIT_NO_EMPTY);
			foreach ($usernames AS $val)
			{
				if (strpos(strtolower($username), strtolower($val)) !== false)
				{
					// wierd error to show, but hey...
					$this->error('usernametaken', htmlspecialchars_uni($username), $this->registry->session->vars['sessionurl']);
					return false;
				}
			}
		}

		// if we got here, everything is okay
		$username = htmlspecialchars_uni($username);

		return $value;
	}


	/**
	* Verifies that an integer is greater than zero
	*
	* @param mixed $value						- The value to validate
	* @param mixed $error						- The var to assign an error to
	* @return mixed | bool						- The filtered value or boolean false
	*/
	public static function nonZero($value, &$error)
	{
		if ($value > 0)
		{
			return $value;
		}

		// TODO: Error phrase
		return false;
	}


	/**
	* Verifies that an integer is greater than zero or the special value -1
	* this rule matches a fair number of id columns
	*
	* @param	integer	Value to check
	*
	* @return	boolean
	*/
	public static function nonZeroOrNegOne($value, &$error)
	{
		return ( ($value > 0) OR $value == -1);
	}

	/**
	* Verifies that a string is not empty
	*
	* @param	string	Text to check
	*
	* @return	boolean
	*/
	public static function verify_nonempty(&$string)
	{
		$string = strval($string);

		return ($string !== '');
	}

	/**
	* Verifies that a variable is a comma-separated list of integers
	*
	* @param	mixed	List (can be string or array)
	*
	* @return	boolean
	*/
	public static function verify_commalist(&$list)
	{
		return $this->verify_list($list, ',', true);
	}

	/**
	* Verifies that a variable is a space-separated list of integers
	*
	* @param	mixed	List (can be string or array)
	*
	* @return	boolean
	*/
	public static function verify_spacelist(&$list)
	{
		return $this->verify_list($list, ' ', true);
	}

	/**
	* Creates a valid string of comma-separated integers
	*
	* @param	mixed	Either specify a string of integers separated by parameter 2, or an array of integers
	* @param	string	The 'glue' for the string. Usually a comma or a space.
	* @param	boolean	Whether or not to exclude zero from the list
	*
	* @return	boolean
	*/
	public static function verify_list(&$list, $glue = ',', $dropzero = false)
	{
		if ($list !== '')
		{
			// turn strings into arrays
			if (!is_array($list))
			{
				if (preg_match_all('#(-?\d+)#s', $list, $matches))
				{
					$list = $matches[1];
				}
				else
				{
					$list = '';
					return true;
				}
			}

			// clean array values and remove duplicates, then sort into order
			$list = array_unique($this->registry->input->clean($list, TYPE_ARRAY_INT));
			sort($list);

			// remove zero values
			if ($dropzero)
			{
				$key = array_search(0, $list);
				if ($key !== false)
				{
					unset($list["$key"]);
				}
			}

			// implode back into a string
			$list = implode($glue, $list);
		}

		return true;
	}

	/**
	* Verifies that input is a serialized array (or force an array to serialize)
	*
	* @param	mixed	Either specify a serialized array, or an array to serialize, or an empty string
	*
	* @return	boolean
	*/
	public static function verify_serialized(&$data)
	{
		if ($data === '')
		{
			$data = serialize(array());
			return true;
		}
		else
		{
			if (!is_array($data))
			{
				$data = unserialize($data);
				if ($data === false)
				{
					return false;
				}
			}

			$data = serialize($data);
		}

		return true;
	}

	/**
	* Verifies an IP address - currently only works with IPv4
	*
	* @param	string	IP address
	*
	* @return 	boolean
	*/
	public static function verify_ipaddress(&$ipaddress)
	{
		if ($ipaddress == '')
		{
			return true;
		}
		else if (preg_match('#^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$#', $ipaddress, $octets))
		{
			for ($i = 1; $i <= 4; $i++)
			{
				if ($octets["$i"] > 255)
				{
					return false;
				}
			}
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	* Verifies that a string is an MD5 string
	*
	* @param	string	The MD5 string
	*
	* @return	boolean
	*/
	public static function verify_md5(&$md5)
	{
		return (preg_match('#^[a-f0-9]{32}$#', $md5) ? true : false);
	}

	/**
	* Verifies that an email address is valid
	*
	* @param	string	Email address
	*
	* @return	boolean
	*/
	public static function verify_email(&$email)
	{
		return is_valid_email($email);
	}

	/**
	* Verifies that a hyperlink is valid
	*
	* @param	string	Hyperlink URL
	*
	* @return	boolean
	*/
	public static function verify_link(&$link)
	{
		if (preg_match('#^www\.#si', $link))
		{
			$link = 'http://' . $link;
			return true;
		}
		else if (!preg_match('#^[a-z0-9]+://#si', $link))
		{
			// link doesn't match the http://-style format in the beginning -- possible attempted exploit
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	* Verifies a date array as a valid unix timestamp
	*
	* @param	array	Date array containing day/month/year and optionally: hour/minute/second
	*
	* @return	boolean
	*/
	public static function verify_date_array(&$date)
	{
		$date['year']   = intval($date['year']);
		$date['month']  = intval($date['month']);
		$date['day']    = intval($date['day']);
		$date['hour']   = intval($date['hour']);
		$date['minute'] = intval($date['minute']);
		$date['second'] = intval($date['second']);

		if ($date['year'] < 1970)
		{
			return false;
		}
		else if (checkdate($date['month'], $date['day'], $date['year']))
		{
			$date = vbmktime($date['hour'],  $date['minute'], $date['second'], $date['month'], $date['day'], $date['year']);

			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	* Basic options to perform on all pagetext type fields
	*
	* @param	string	Page text
	*
	* @param	bool	Whether the text is valid
	* @param	bool	Whether to run the case stripper
	*/
	public static function verify_pagetext(&$pagetext, $noshouting = true)
	{
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

		return true;
	}

	/**
	* Strips empty BB code from the entire message except inside PHP/HTML/Noparse tags.
	*
	* @param	string	Text to strip tags from
	*
	* @return	string	Text with tags stripped
	*/
	public static function strip_empty_bbcode($text)
	{
		return preg_replace_callback(
			'#(^|\[/(php|html|noparse)\])(.+)(?=\[(php|html|noparse)\]|$)#sU',
			array(&$this, 'strip_empty_bbcode_callback'),
			$text
		);
	}

	/**
	* Callback function for strip_empty_bbcode.
	*
	* @param	array	Array of matches. 1 is the close of the previous tag (if there is one). 3 is the text to strip from.
	*
	* @return	string	Compiled text with empty tags stripped where appropriate
	*/
	public static function strip_empty_bbcode_callback($matches)
	{
		$stripped = preg_replace('#(\[([^=\]]+)(=[^\]]+)?]\s*\[/\\2])#siU', '', $matches[3]);
		return $matches[1] . $stripped;
	}

	/**
	* Verifies the number of images in the post text. Call it from pre_save() after pagetext/allowsmilie has been set
	*
	* @return	bool	Whether the post passes the image count check
	*/
	public static function verify_image_count($pagetext = 'pagetext', $allowsmilie = 'allowsmilie', $parsetype = 'nonforum', $table = null)
	{
		global $vbulletin;

		$_allowsmilie =& $this->fetch_field($allowsmilie, $table);
		$_pagetext =& $this->fetch_field($pagetext, $table);

		if ($_allowsmilie !== null AND $_pagetext !== null)
		{
			// check max images
			require_once(DIR . '/includes/functions_misc.php');
			require_once(DIR . '/includes/class_bbcode_alt.php');
			$bbcode_parser = new vB_BbCodeParser_ImgCheck($this->registry, fetch_tag_list());
			$bbcode_parser->set_parse_userinfo($vbulletin->userinfo);

			if ($this->registry->options['maximages'] AND !$this->info['is_automated'])
			{
				$imagecount = fetch_character_count($bbcode_parser->parse($_pagetext, $parsetype, $_allowsmilie, true), '<img');
				if ($imagecount > $this->registry->options['maximages'])
				{
					$this->error('toomanyimages', $imagecount, $this->registry->options['maximages']);
					return false;
				}
			}
		}

		return true;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 28749 $
|| ####################################################################
\*======================================================================*/