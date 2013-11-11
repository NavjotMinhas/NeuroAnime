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
* Extension to BBCode Parser for SN Oriented Messages
*
* @package 		vBulletin
* @version		$Revision: 43197 $
* @date 		$Date: 2011-05-02 08:29:53 -0700 (Mon, 02 May 2011) $
*
*/
class vB_SocialMessageParser extends vB_BbCodeParser
{
	/**
	* Tags that are allowed within this
	*
	* @var	array
	*/
	var $tag_allowed = array();

	/**
	* Options Group for The BBCodes
	*
	* @var	string
	*/
	var $optiongroup = 'allowedbbcodes';

	/**
	* Tag Groups, and respective BF values
	*
	* @var array
	*/
	var $tag_group_perms = array(
		'basic'	=> ALLOW_BBCODE_BASIC,
		'color' => ALLOW_BBCODE_COLOR,
		'size'  => ALLOW_BBCODE_SIZE,
		'font'  => ALLOW_BBCODE_FONT,
		'align' => ALLOW_BBCODE_ALIGN,
		'list'  => ALLOW_BBCODE_LIST,
		'link'  => ALLOW_BBCODE_URL,
		'code'  => ALLOW_BBCODE_CODE,
		'php'   => ALLOW_BBCODE_PHP,
		'html'  => ALLOW_BBCODE_HTML,
		'quote' => ALLOW_BBCODE_QUOTE,
		'img'   => ALLOW_BBCODE_IMG,
	);

	/**
	* Groupings for tags
	*
	* @var	array
	*/
	var $tag_groupings = array(
		'b'      => 'basic',
		'i'      => 'basic',
		'u'      => 'basic',
		
		'table'  => 'basic',
		'hr'     => 'basic',
		'sub'    => 'basic',
		'sup'    => 'basic',

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

		'img'    => 'img',

		// custom doesn't need to be explicitly handled here
	);

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
	* @param	boolean		Whether to append custom tags
	*/
	function vB_SocialMessageParser(&$registry, $tag_list, $append_custom_tags = true)
	{
		parent::vB_BbCodeParser($registry, $tag_list, false);

		// Load the information regarding allowed tags from the options
		$this->allow_bbcodes();

		// General, whether allowed or not
		foreach ($this->tag_groupings AS $tag => $tag_group)
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

		// lets treat the image tag like a full tag here
		$this->tag_list['no_option']['img']['callback'] = 'check_bbcode_general';

		if ($append_custom_tags)
		{
			$this->append_custom_tags();
		}
	}


	/**
	* Import Settings as to which BBCodes are allowed in this Message
	*
	*/
	function allow_bbcodes()
	{
		$optiongroup = $this->optiongroup;

		foreach ($this->tag_group_perms AS $tag_group => $value)
		{
			if ($this->registry->options["$optiongroup"] & $value)
			{
				$this->tag_allowed[] = $tag_group;
			}
		}
	}

	/**
	* BB code callback allowed check
	*
	* @param	string	Text between the tags
	*
	* @return	string
	*/
	function check_bbcode_general($text)
	{
		$tag = $this->current_tag['name'];

		if ($this->tag_groupings["$tag"] AND !in_array($this->tag_groupings["$tag"], $this->tag_allowed))
		{
			$this->errors["$tag"] = 'tag_not_allowed';
		}

		return $text;
	}

	/**
	* Are Custom BBCodes allowed? (callback)
	*
	* @param	string	Text between the tags
	*
	* @return	string
	*/
	function check_custom_bbcode($text)
	{
		if (!($this->registry->options[$this->optiongroup] & ALLOW_BBCODE_CUSTOM))
		{
			$tag = $this->current_tag['name'];
			$this->errors["$tag"] = 'tag_not_allowed';
		}

		return $text;
	}

	/**
	* Overrides standard version in order to place the error-generating callback on all custom tags
	*
	*/
	function append_custom_tags()
	{
		if (!$this->custom_fetched)
		{
			$this->custom_fetched = true;

			if ($this->registry->bbcodecache !== null) // get bbcodes from the datastore
			{
				foreach($this->registry->bbcodecache AS $customtag)
				{
					$this->tag_list[($customtag['twoparams'] ? 'option' : 'no_option')][strtolower($customtag['bbcodetag'])] = array('callback' => 'check_custom_bbcode');
				}
			}
			else // query bbcodes out of the database
			{
				$this->registry->bbcodecache = array();

				$bbcodes = $this->registry->db->query_read_slave("SELECT * FROM " . TABLE_PREFIX . "bbcode");
				while ($customtag = $this->registry->db->fetch_array($bbcodes))
				{
					$this->tag_list[($customtag['twoparams'] ? 'option' : 'no_option')]["$customtag[bbcodetag]"] = array('callback' => 'check_custom_bbcode');
					$this->registry->bbcodecache["$customtag[bbcodeid]"] = $customtag;
				}
			}
		}
	}

} // End Class


/**
* Extension to BBCode Parser for Visitor Messages
*
* @package 		vBulletin
* @version		$Revision: 43197 $
* @date 		$Date: 2011-05-02 08:29:53 -0700 (Mon, 02 May 2011) $
*
*/
class vB_VisitorMessageParser extends vB_SocialMessageParser
{
	/**
	* Options Group for The BBCodes
	*
	* @var	string
	*/
	var $optiongroup = 'vm_allowed_bbcode';
}

/**
* Extension to BBCode Parser for Group Messages
*
* @package 		vBulletin
* @version		$Revision: 43197 $
* @date 		$Date: 2011-05-02 08:29:53 -0700 (Mon, 02 May 2011) $
*
*/
class vB_GroupMessageParser extends vB_SocialMessageParser
{
	/**
	* Options Group for The BBCodes
	*
	* @var	string
	*/
	var $optiongroup = 'sg_allowed_bbcode';
}

/**
* Extension to BBCode Parser for Picture Comments
*
* @package 		vBulletin
* @version		$Revision: 43197 $
* @date 		$Date: 2011-05-02 08:29:53 -0700 (Mon, 02 May 2011) $
*
*/
class vB_PictureCommentParser extends vB_SocialMessageParser
{
	/**
	* Options Group for The BBCodes
	*
	* @var	string
	*/
	var $optiongroup = 'pc_allowed_bbcode';
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 43197 $
|| ####################################################################
\*======================================================================*/
?>