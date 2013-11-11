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

require_once(DIR . '/includes/class_bbcode.php');

/**
* Stack based BB code parser.
*
* @package 		vBulletin
* @version		$Revision: 36198 $
* @date 		$Date: 2010-04-06 11:03:04 -0700 (Tue, 06 Apr 2010) $
*
*/
class vB_SignatureParser extends vB_BbCodeParser
{
	/**
	* Internal reference to the permissions array i.e. $vbulletin->userinfo['permissions']
	*
	* @var	array
	*/
	var $permissions = array();

	/**
	* User this signature belongs to
	*
	* @var	integer
	*/
	var $userid = 0;

	/**
	* Groupings for tags
	*
	* @var	array
	*/
	var $tag_groups = array();

	/**
	* Errors found in the signature
	*
	* @var	array
	*/
	var $errors = array();

	/**
	* Constructor. Sets up the tag permissions list.
	*
	* @param	vB_Registry	Reference to registry object
	* @param	array		The tag_list array for the parent class parser
	* @param	integer		The permssions number for the user from their usergroup  i.e. $vbulletin->userinfo['permissions']
	* @param	integer		The user this signature belongs to. Required
	* @param	boolean		Whether to append custom tags (they will not be parsed anyway)
	*/
	function vB_SignatureParser(&$registry, $tag_list, $usergroup_signature_permission, $userid, $append_custom_tags = true)
	{
		parent::vB_BbCodeParser($registry, $tag_list, false);

		$this->userid = intval($userid);
		if (!$this->userid)
		{
			trigger_error("User ID is 0. A signature cannot be parsed unless it belongs to a user.", E_USER_ERROR);
		}

		$this->permissions =& $usergroup_signature_permission;

		$this->tag_groups = array(
			'b'      => 'basic',
			'i'      => 'basic',
			'u'      => 'basic',

			'color'  => 'color',
			'size'   => 'size',
			'font'   => 'font',

			'left'   => 'align',
			'center' => 'align',
			'right'  => 'align',
			'indent' => 'align',

			'list'   => 'list',

			'url'    => 'link',
			'email'  => 'link',
			'thread' => 'link',
			'post'   => 'link',

			'code'   => 'code',
			'php'    => 'php',
			'html'   => 'html',
			'quote'  => 'quote',
		);

		// General, allowed or not
		foreach ($this->tag_groups AS $tag => $tag_group)
		{
			if (isset($this->tag_list['no_option']["$tag"]))
			{
				$this->tag_list['no_option']["$tag"]['callback'] = 'check_bbcode_general';
				unset($this->tag_list['no_option']["$tag"]['html']);
			}

			if (isset($this->tag_list['option']["$tag"]))
			{
				$this->tag_list['option']["$tag"]['callback'] = 'check_bbcode_general';
				unset($this->tag_list['option']["$tag"]['html']);
			}
		}

		// Specific functions
		$this->tag_list['option']['size']['callback'] = 'check_bbcode_size';
		$this->tag_list['no_option']['img']['callback'] = 'check_bbcode_img';

		// needs to parse sig pics like any other bb code
		$this->tag_list['no_option']['sigpic'] = array(
			'strip_empty' => false,
			'callback' => 'check_bbcode_sigpic'
		);

		if ($append_custom_tags)
		{
			$this->append_custom_tags();
		}
	}

	/**
	* Collect parser options and misc data to determine how to parse a signature
	* and determine if errors have occurred.
	*
	* @param	string	Unparsed text
	*
	* @return	string	Parsed text
	*/
	function parse($text)
	{
		$sig_perms =& $this->permissions['signaturepermissions'];
		$global_sig_perms =& $this->registry->bf_ugp_signaturepermissions;

		$dohtml = ($sig_perms & $global_sig_perms['allowhtml']);
		$dosmilies = ($sig_perms & $global_sig_perms['allowsmilies']);
		$dobbcode = ($sig_perms & $global_sig_perms['canbbcode']);
		$dobbimagecode = ($sig_perms & $global_sig_perms['allowimg']);

		return $this->do_parse($text, $dohtml, $dosmilies, $dobbcode, $dobbimagecode, false, false);
	}

	/**
	* BB code callback allowed check
	*
	*/
	function check_bbcode_general($text)
	{
		$tag = $this->current_tag['name'];

		if ($this->tag_groups["$tag"] AND !($this->registry->bf_ugp_signaturepermissions["canbbcode{$this->tag_groups[$tag]}"] & $this->permissions['signaturepermissions']))
		{
			$this->errors["$tag"] = 'tag_not_allowed';
		}

		return $text;
	}

	/**
	* BB code callback allowed check with size checking
	*
	*/
	function check_bbcode_size($text, $size)
	{
		$size_mod = array();
		foreach ($this->stack AS $stack)
		{
			if ($stack['type'] == 'tag' AND $stack['name'] == 'size')
			{
				$size_mod[] = trim($stack['option']);
			}
		}

		// need to process as a queue, not a stack of open tags
		$base_size = 3;
		foreach (array_reverse($size_mod) AS $tag_size)
		{
			if ($tag_size[0] == '-' OR $tag_size[0] == '+')
			{
				$base_size += $tag_size;
			}
			else
			{
				$base_size = $tag_size;
			}
		}

		if ($this->registry->bf_ugp_signaturepermissions['canbbcodesize'] & $this->permissions['signaturepermissions'])
		{
			if ($this->permissions['sigmaxsizebbcode'] > 0 AND $base_size > $this->permissions['sigmaxsizebbcode'])
			{
				$this->errors['size'] = 'sig_bbcode_size_tag_too_big';
			}

		}
		else
		{
			$this->errors['size'] = 'tag_not_allowed';
		}

		return $text;
	}

	/**
	* BB code callback allowed check for images. Images fall back to links
	* if the image code is disabled, so allow if either is true.
	*
	*/
	function check_bbcode_img($image_path)
	{
		if (!($this->registry->bf_ugp_signaturepermissions['allowimg'] & $this->permissions['signaturepermissions'])
			AND !($this->registry->bf_ugp_signaturepermissions['canbbcodelink'] & $this->permissions['signaturepermissions'])
		)
		{
			$this->errors['img'] = 'tag_not_allowed';
		}

		return $image_path;
	}

	/**
	* BB code sigpic, returns the <img link.
	*
	*/
	function check_bbcode_sigpic($alt_text)
	{
		if (!$this->registry->db->query_first("SELECT userid FROM " . TABLE_PREFIX . "sigpic WHERE userid = " . $this->userid))
		{
			// guests can't have sigs (let alone sig pics) so why are we even here?
			if (!in_array('no_sig_pic_to_use', $this->errors))
			{
				$this->errors[] = 'no_sig_pic_to_use';
			}
			return 'sigpic';
		}

		static $sigpic_used = false;

		if ($sigpic_used == true)
		{
			// can only use the sigpic once in a signature
			if (!in_array('sig_pic_already_used', $this->errors))
			{
				$this->errors[] = 'sig_pic_already_used';
			}
			return 'sigpic';
		}

		$sigpic_used = true;
		return 'sigpic';
	}

} // End Class


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 36198 $
|| ####################################################################
\*======================================================================*/

?>