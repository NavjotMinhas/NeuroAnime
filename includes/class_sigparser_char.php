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

require_once(DIR . '/includes/class_sigparser.php');

/**
* Parser that counts the amount of text in a string without markup.
* Text is also normalized to size 2 (eg, 1 size 7 character counts as
* 3 size 2 characters). This is primarily used with signatures for line counting.
*
* @package 		vBulletin
* @version		$Revision: 32878 $
* @date 		$Date: 2009-10-28 11:38:49 -0700 (Wed, 28 Oct 2009) $
*
*/
class vB_SignatureParser_CharCount extends vB_SignatureParser
{
	var $block_tags = array(
		'left',
		'center',
		'right',
		'quote',
		'php',
		'code',
		'html'
	);

	var $size_ratios = array(
		1 => 0.85,
		2 => 1,
		3 => 1.25,
		4 => 1.4,
		5 => 1.85,
		6 => 2.5,
		7 => 3.725
	);

	/**
	* Constructor. Sets up the tag permissions list.
	*
	* @param	vB_Registry	Reference to registry object
	* @param	array		The tag_list array for the parent class parser
	* @param	integer		The permssions number for the user from their usergroup  i.e. $vbulletin->userinfo['permissions']
	* @param	integer		The user this signature belongs to. Required
	* @param	boolean		Whether to append custom tags (they will not be parsed anyway)
	*/
	function vB_SignatureParser_CharCount(&$registry, $tag_list, $usergroup_signature_permission, $userid, $append_custom_tags = true)
	{
		parent::vB_SignatureParser($registry, $tag_list, $usergroup_signature_permission, $userid, $append_custom_tags);

		foreach ($this->tag_list['option'] AS $bbcode_name => $data)
		{
			$tag_callback = (in_array($bbcode_name, $this->block_tags) ? 'handle_block_tag' : 'handle_standard_tag');
			$this->tag_list['option']["$bbcode_name"]['callback'] = $tag_callback;
			unset($this->tag_list['option']["$bbcode_name"]['html']);
		}

		foreach ($this->tag_list['no_option'] AS $bbcode_name => $data)
		{
			$tag_callback = (in_array($bbcode_name, $this->block_tags) ? 'handle_block_tag' : 'handle_standard_tag');
			$this->tag_list['no_option']["$bbcode_name"]['callback'] = $tag_callback;
			unset($this->tag_list['no_option']["$bbcode_name"]['html']);
		}

		if (isset($this->tag_list['option']['size']))
		{
			$this->tag_list['option']['size']['callback'] = 'handle_bbcode_size';
		}

		if (isset($this->tag_list['option']['list']))
		{
			$this->tag_list['option']['list']['callback'] = 'handle_bbcode_list';
		}

		if (isset($this->tag_list['no_option']['list']))
		{
			$this->tag_list['no_option']['list']['callback'] = 'handle_bbcode_list';
		}

		$this->tag_list['no_option']['_wrapper'] = array(
			'strip_empty' => false,
			'callback' => 'handle_standard_tag'
		);
	}

	/**
	* Loads any user specified custom BB code tags into the $tag_list. These tags
	* will not be parsed. They are loaded simply for directive parsing.
	*/
	function append_custom_tags()
	{
		if ($this->custom_fetched == true)
		{
			return;
		}

		$this->custom_fetched = true;
		// this code would make nice use of an interator
		if ($this->registry->bbcodecache !== null) // get bbcodes from the datastore
		{
			foreach($this->registry->bbcodecache AS $customtag)
			{
				$has_option = $customtag['twoparams'] ? 'option' : 'no_option';
				$customtag['bbcodetag'] = strtolower($customtag['bbcodetag']);

				// str_replace is stop gap until custom tags are updated to the new format
				$this->tag_list["$has_option"]["$customtag[bbcodetag]"] = array(
					'callback' => 'handle_standard_tag',
					'strip_empty' 		=> $customtag['strip_empty'],
					'stop_parse'		=> $customtag['stop_parse'],
					'disable_smilies'	=> $customtag['disable_smilies'],
					'disable_wordwrap'	=> $customtag['disable_wordwrap'],
				);
			}
		}
		else // query bbcodes out of the database
		{
			$bbcodes = $this->registry->db->query_read_slave("
				SELECT bbcodetag, bbcodereplacement, twoparams
				FROM " . TABLE_PREFIX . "bbcode
			");
			while ($customtag = $this->registry->db->fetch_array($bbcodes))
			{
				$has_option = $customtag['twoparams'] ? 'option' : 'no_option';

				// str_replace is stop gap until custom tags are updated to the new format
				$this->tag_list["$has_option"]["$customtag[bbcodetag]"] = array(
					'callback' => 'handle_standard_tag',
					'strip_empty'		=> intval($customtag['options']) & $this->registry->bf_misc['bbcodeoptions']['strip_empty'],
					'stop_parse' 		=> intval($customtag['options']) & $this->registry->bf_misc['bbcodeoptions']['stop_parse'],
					'disable_smilies'	=> intval($customtag['options']) & $this->registry->bf_misc['bbcodeoptions']['disable_smilies'],
					'disable_wordwrap'	=> intval($customtag['options']) & $this->registry->bf_misc['bbcodeoptions']['disable_wordwrap']
				);
			}
		}
	}

	/**
	* Parses smilie codes into a size equivalent to 3 characters
	*
	* @param	string	Text with smilie codes
	* @param	bool	Whether HTML is allowed
	*
	* @return	string	Text with HTML images in place of smilies
	*/
	function parse_smilies($text, $do_html = false)
	{
		$cache =& $this->cache_smilies($do_html);

		$parent_size = $this->calculate_parent_size(false);

		if (empty($this->size_ratios["$parent_size"]))
		{
			$computed_ratio = 1;
		}
		else
		{
			// multiply the length by the size of this text compared to default. Divide
			// by the parent size ratio to account for nested size tags, by increasing
			// the size of the output if there parent size is < 2, or decreasing if > 2
			$computed_ratio = $this->size_ratios["$parent_size"];
		}

		$output_len = floor(3 / $computed_ratio);
		if ($output_len < 1)
		{
			$output_len = 1;
		}

		$output = str_repeat('x', $output_len);

		foreach ($cache AS $find => $replace)
		{
			$text = preg_replace('/(?<!&amp|&quot|&lt|&gt|&copy|&#[0-9]{1}|&#[0-9]{2}|&#[0-9]{3}|&#[0-9]{4}|&#[0-9]{5})' . preg_quote($find, '/') . '/s', $output, $text);
		}

		return $text;
	}

	/**
	* Parse an input string with BB code to a final output string of HTML
	*
	* @param	string	Input Text (BB code)
	* @param	bool	Whether to parse smilies
	* @param	bool	Whether to parse img (for the video bbcodes)
	* @param	bool	Whether to allow HTML (for smilies)
	*
	* @return	string	Ouput Text (HTML)
	*/
	function parse_bbcode($input_text, $do_smilies, $do_imgcode, $do_html = false)
	{
		$input_text = '[_wrapper]' . $input_text . '[/_wrapper]';
		return $this->parse_array($this->fix_tags($this->build_parse_array($input_text)), $do_smilies, $do_imgcode, $do_html);
	}

	/**
	* Parses out specific white space before or after cetain tags and does nl2br.
	* This version of the parser doesn't use nl2br!
	*
	* @param	string	Text to process
	* @param	bool	Whether to translate newlines to <br /> tags -- ignored!
	*
	* @return	string	Processed text
	*/
	function parse_whitespace_newlines($text, $do_nl2br = true)
	{
		// don't do new lines!
		return parent::parse_whitespace_newlines($text, false);
	}

	function handle_standard_tag($value, $option = '')
	{
		return $value;
	}

	function handle_block_tag($value, $option = '')
	{

		// look for printable content before this tag - if we find some, we need to add a break
		$starting_break = '';
		$stack_size = count($this->stack);
		for ($i = 1; $i < $stack_size; $i++)
		{
			$stack_item = $this->stack["$i"];
			if ($stack_item['type'] != 'tag')
			{
				continue;
			}

			if (!in_array($stack_item['name'], $this->block_tags) AND $stack_item['data'] === '')
			{
				// an inline tag with no content, ignore it
				continue;
			}

			// block tag or inline tag with content...
			if ($stack_item['data'] == rtrim($stack_item['data']))
			{
				// ..that doesn't end in whitespace
				$starting_break = "\n";
			}
			break;
		}

		if ($this->node_num == $this->node_max - 1)
		{
			//this is the last printable thing, the only thing left is _wrapper, so don't add a trailing line
			$ending_break = '';
		}
		else
		{
			$ending_break = "\n";
		}

		// browsers tend to ignore a single trailing break on block tags (D modifier to make $ only match the end)
		$value = preg_replace('#(\r\n|\r|\n)[ \t]*$#sD', '', $value);

		return $starting_break . $value . $ending_break;
	}

	function calculate_parent_size($ignore_first_stack_entry = true)
	{
		// default size is 2
		$parent_size = 2;

		if (count($this->stack) > ($ignore_first_stack_entry ? 1 : 0))
		{
			$modifier = 0;

			// do we have another size tag we need to account for?
			foreach ($this->stack AS $key => $stack_tag)
			{
				if ($ignore_first_stack_entry AND $key == 0)
				{
					// the current tag is always stack[0]
					continue;
				}

				if ($stack_tag['type'] == 'tag' AND $stack_tag['name'] == 'size')
				{
					if ($stack_tag['option'] >= 0)
					{
						$parent_size = $stack_tag['option'] + $modifier;
						$modifier = 0;
						break;
					}
					else
					{
						// relative size, need to continue on
						$modifier += $stack_tag['option'];
					}
				}
			}

			if ($modifier)
			{
				$parent_size += $modifier;
			}
		}

		if ($parent_size <= 0)
		{
			$parent_size = 1;
		}
		else if ($parent_size > 7)
		{
			$parent_size = 7;
		}

		return $parent_size;
	}

	function handle_bbcode_size($value, $size)
	{
		$value = preg_replace('#(\r\n|\n|\r)#', "\n", $value);
		$length = strlen($value);

		$parent_size = $this->calculate_parent_size();

		if ($size < 0)
		{
			$size -= $parent_size;
		}

		if ($size == 0)
		{
			$size = 1;
		}
		else if ($size > 7)
		{
			$size = 7;
		}

		if (empty($this->size_ratios["$size"]) OR empty($this->size_ratios["$parent_size"]))
		{
			$computed_ratio = 1;
		}
		else
		{
			// multiply the length by the size of this text compared to default. Divide
			// by the parent size ratio to account for nested size tags, by increasing
			// the size of the output if there parent size is < 2, or decreasing if > 2
			$computed_ratio = $this->size_ratios["$size"] / $this->size_ratios["$parent_size"];
		}

		return preg_replace('#([^\n]+)#e', "\$this->handle_bbcode_size_callback('\\1', \$computed_ratio)", $value);
	}

	function handle_bbcode_size_callback($text, $computed_ratio)
	{
		#$txt = str_repeat('*', ceil(strlen($text) * $computed_ratio));
		#$txt = preg_replace('#\*{10}#siU', '*********X', $txt);
		#return $txt;

		#echo "<div>ratio:$computed_ratio x length:" . strlen($text) . " :: text:$text</div>";
		return str_repeat('*', ceil(strlen($text) * $computed_ratio));
	}

	function is_tag_open($tag_name, $ignore_first_stack_entry = true)
	{
		foreach ($this->stack AS $key => $stack_tag)
		{
			if ($ignore_first_stack_entry AND $key == 0)
			{
				// the current tag is always stack[0]
				continue;
			}

			if ($stack_tag['type'] == 'tag' AND $stack_tag['name'] == $tag_name)
			{
				return true;
			}
		}

		return false;
	}

	/**
	* Handles a [list] tag. Makes a bulleted or ordered list.
	*
	* @param	string	The body of the list.
	* @param	string	If tag has option, the type of list (ordered, etc).
	*
	* @return	string	HTML representation of the tag.
	*/
	function handle_bbcode_list($text, $type = '')
	{
		$bullets = preg_split('#\s*\[\*\]#s', ltrim($text), -1, PREG_SPLIT_NO_EMPTY);
		if (empty($bullets))
		{
			return "\n\n";
		}

		$nested_list = $this->is_tag_open('list');

		if ($nested_list)
		{
			$starting_break = "\n";
		}
		else
		{
			$starting_break = "\n";
			if (isset($this->stack['1']))
			{
				if (preg_match('#([\r\n]+)$#', $this->stack['1']['data'], $match))
				{
					$count = preg_match_all('#(\r\n|\n|\r)#', $match[1], $null);
				}
				else
				{
					$count = 0;
				}

				if ($count >= 2)
				{
					$starting_break = '';
				}
				else if ($count == 1)
				{
					$starting_break = "\n";
				}
				else
				{
					$starting_break = "\n\n";
				}
			}
		}

		// nested lists don't have the extra starting line break
		$output = $starting_break;

		foreach ($bullets AS $bullet)
		{
			$output .= rtrim($bullet) . "\n";
		}

		// nested lists don't have the extra trailing line break
		return $output . ($nested_list ? '' : "\n");
	}

	/**
	* Handles an img/attach/sigpic tag.
	*
	* @param	string	The text to search for an image in.
	* @param	string	Whether to parse matching images into pictures or just links.
	*
	* @return	string	HTML representation of the tag.
	*/
	function handle_bbcode_img($bbcode, $do_imgcode, $has_img_code = false)
	{
		global $vbphrase;

		if (($has_img_code & BBCODE_HAS_ATTACH) AND preg_match_all('#\[attach(?:=(right|left))?\](\d+)\[/attach\]#i', $bbcode, $matches))
		{
			$search = array();
			$replace = array();

			foreach($matches[2] AS $key => $attachmentid)
			{
				$align = $matches[1]["$key"];
				$search[] = '#\[attach' . (!empty($align) ? '=' . $align : '') . '\]('. $attachmentid . ')\[/attach\]#i';
				$replace[] = '';
			}

			$bbcode = preg_replace($search, $replace, $bbcode);
		}

		// If you wanted to be able to edit [img] when editing a post instead of seeing the image, add the get_class() check from above
		if ($has_img_code & BBCODE_HAS_IMG)
		{
			if ($do_imgcode)
			{
				// do [img]xxx[/img]
				$bbcode = preg_replace('#\[img\]\s*(https?://([^*\r\n]+|[a-z0-9/\\._\- !]+))\[/img\]#iU', '', $bbcode);
			}
			else
			{
				$bbcode = preg_replace('#\[img\]\s*(https?://([^*\r\n]+|[a-z0-9/\\._\- !]+))\[/img\]#iUe', "\$this->handle_standard_tag(str_replace('\\\"', '\"', '\\1'), '')", $bbcode);
			}
		}

		if ($has_img_code & BBCODE_HAS_SIGPIC)
		{
			$bbcode = preg_replace('#\[sigpic\](.*)\[/sigpic\]#siUe', '', $bbcode);
		}


		return $bbcode;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>