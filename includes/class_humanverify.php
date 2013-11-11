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

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

/**
* Abstracted human verification class
*
* @package 		vBulletin
* @version		$Revision: 32878 $
* @date 		$Date: 2009-10-28 11:38:49 -0700 (Wed, 28 Oct 2009) $
*
*/
class vB_HumanVerify
{
	/**
	* Constructor
	* Does nothing :p
	*
	* @return	void
	*/
	function vB_HumanVerify() {}

	/**
	* Singleton emulation: Select library
	*
	* @return	object
	*/
	function &fetch_library(&$registry, $library = '')
	{
		global $show;
		static $instance;

		if (!$instance)
		{
			if ($library)
			{		// Override the defined vboption
					$chosenlib = $library;
			}
			else
			{
					$chosenlib = ($registry->options['hv_type'] ? $registry->options['hv_type'] : 'Disabled');
			}

			$selectclass = 'vB_HumanVerify_' . $chosenlib;
			$chosenlib = strtolower($chosenlib);
			require_once(DIR . '/includes/class_humanverify_' . $chosenlib . '.php');
			$instance = new $selectclass($registry);
		}

		return $instance;
	}
}


/**
* Abstracted human verification class
*
* @package 		vBulletin
* @version		$Revision: 32878 $
* @date 		$Date: 2009-10-28 11:38:49 -0700 (Wed, 28 Oct 2009) $
*
* @abstract
*/
class vB_HumanVerify_Abstract
{
	/**
	* Main data registry
	*
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* Error string
	*
	* @var	string
	*/
	var $error = '';

	/**
	* Last generated hash
	*
	* @var	string
	*/
	var $hash = '';

	/**
	* Constructor
	* Don't allow direct construction of this abstract class
	* Sets registry
	*
	* @return	void
	*/
	function vB_HumanVerify_Abstract(&$registry)
	{
		if (!is_subclass_of($this, 'vB_HumanVerify_Abstract'))
		{
			trigger_error('Direct Instantiation of vB_HumanVerify_Abstract prohibited.', E_USER_ERROR);
			return NULL;
		}

		$this->registry =& $registry;
	}

	/**
	 * Deleted a Human Verification Token
	 *
	 * @param	string	The hash to delete
	 * @param	string	The Corresponding Option
	 * @param	integer	Whether the token has been viewd
	 *
	 * @return	boolean	Was anything deleted?
	 *
	*/
	function delete_token($hash, $answer = NULL, $viewed = NULL)
	{
		$options = array(
			"hash = '" . $this->registry->db->escape_string($hash) . "'"
		);

		if ($answer !== NULL)
		{
			$options[] = "answer = '" . $this->registry->db->escape_string($answer) . "'";
		}
		if ($viewed !== NULL)
		{
			$options[] = "viewed = " . intval($viewed);
		}

		if ($this->hash == $hash)
		{
			$this->hash = '';
		}

		$this->registry->db->query_write("
			DELETE FROM " . TABLE_PREFIX . "humanverify WHERE " . implode(" AND ", $options) . "
		");

		return $this->registry->db->affected_rows() ? true : false;
	}

	/**
	 * Returns the HTML to be displayed to the user for Human Verification
	 *
	 * @param	string	Passed to template
	 *
	 * @return 	string	HTML to output
	 *
	 */
	function output_token($var_prefix = 'humanverify') {}

	/**
	 * Generates a Random Token and stores it in the database
	 *
	 * @param	boolean	Delete the previous hash generated
	 *
	 * @return	array	an array consisting of the hash, and the answer
	 *
	*/
	function generate_token($deletehash = true)
	{
		$verify = array(
			'hash'   => md5(uniqid(vbrand(), true)),
			'answer' => $this->fetch_answer(),
		);

		if ($deletehash AND $this->hash)
		{
			$this->delete_token($this->hash);
		}
		$this->hash = $verify['hash'];

		$this->registry->db->query_write("
			INSERT INTO " . TABLE_PREFIX . "humanverify
				(hash, answer, dateline)
			VALUES
				('" . $this->registry->db->escape_string($verify['hash']) . "', '" . $this->registry->db->escape_string($verify['answer']) . "', " . TIMENOW . ")"
		);

		return $verify;
	}

	/**
	 * Verifies whether the HV entry was correct
	 *
	 * @param	array	An array consisting of the hash, and the inputted answer
	 *
	 * @return	boolean
	 *
	*/
	function verify_token($input)
	{
		return true;
	}

	/**
	 * Returns any errors that occurred within the class
	 *
	 * @return	mixed
	 *
	*/
	function fetch_error()
	{
		return $this->error;
	}

	/**
	 * Generates an expected answer
	 *
	 * @return	mixed
	 *
	*/
	function fetch_answer() {}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>
