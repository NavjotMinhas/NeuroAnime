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
* Human Verification class for Image Verification
*
* @package 		vBulletin
* @version		$Revision: 32878 $
* @date 		$Date: 2009-10-28 11:38:49 -0700 (Wed, 28 Oct 2009) $
*
*/
class vB_HumanVerify_Image extends vB_HumanVerify_Abstract
{
	/**
	* Constructor
	*
	* @return	void
	*/
	function vB_HumanVerify_Image(&$registry)
	{
		parent::vB_HumanVerify_Abstract($registry);
	}

	/**
	* Verify is supplied token/reponse is valid
	*
	*	@param	array	Values given by user 'input' and 'hash'
	*
	* @return	bool
	*/
	function verify_token($input)
	{
		$input['input'] = trim(str_replace(' ', '', $input['input']));

		if ($this->delete_token($input['hash'], $input['input']))
		{
			return true;
		}
		else
		{
			$this->error = 'humanverify_image_wronganswer';
			return false;
		}
	}

	/**
	 * Returns the HTML to be displayed to the user for Human Verification
	 *
	 * @param	string	Passed to template
	 *
	 * @return 	string	HTML to output
	 *
	 */
	function output_token($var_prefix = 'humanverify')
	{
		global $vbphrase, $show;
		$vbulletin =& $this->registry;

		$humanverify = $this->generate_token();

		$templater = vB_Template::create('humanverify_image');
			$templater->register('humanverify', $humanverify);
			$templater->register('var_prefix', $var_prefix);
		$output = $templater->render();

		return $output;
	}

	/**
	* Call this class' answer function via a middleman since it has an argument
	*
	* @return	string
	*/
	function fetch_answer()
	{
		return $this->fetch_answer_string();
	}

	/**
	* Generate a random string for image verification
	*
	* @param	int		Length of result
	*
	* @return	string
	*/
	function fetch_answer_string($length = 6)
	{
		$somechars = '234689ABCEFGHJMNPQRSTWY';
		$morechars = '234689ABCEFGHJKMNPQRSTWXYZabcdefghjkmnpstwxyz';

		for ($x = 1; $x <= $length; $x++)
		{
			$chars = ($x <= 2 OR $x == $length) ? $morechars : $somechars;
			$number = vbrand(1, strlen($chars));
			$word .= substr($chars, $number - 1, 1);
	 	}

	 	return $word;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>
