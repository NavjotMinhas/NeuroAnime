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
* BB code parser for the WYSIWYG editor
*
* @package 		vBulletin
* @version		$Revision: 44293 $
* @date 		$Date: 2011-06-08 12:17:25 -0700 (Wed, 08 Jun 2011) $
*
*/
class vB_BbCodeParser_Wysiwyg extends vB_BbCodeParser
{
	/**
	* List of tags the WYSIWYG BB code parser should not parse.
	*
	* @var	array
	*/
	var $unparsed_tags = array(
		'thread',
		'post',
		'quote',
		'highlight',
		'noparse',
		'video',

		// leave these parsed, because <space><space> needs to be replaced to emulate pre tags
		//'php',
		//'code',
		//'html',
	);

	/**
	* Type of WYISWYG parser (IE or Mozilla at this point)
	*
	* @var	string
	*/
	var $type = '';

	/**
	* Constructor. Sets up the tag list.
	*
	* @param	vB_Registry	Reference to registry object
	* @param	array		List of tags to parse
	* @param	boolean		Whether to append custom tags (they will not be parsed anyway)
	*/
	function vB_BbCodeParser_Wysiwyg(&$registry, $tag_list = array(), $append_custom_tags = true)
	{
		parent::vB_BbCodeParser($registry, $tag_list, $append_custom_tags);

		// change all unparsable tags to use the unparsable callback
		foreach ($this->unparsed_tags AS $remove)
		{
			if (isset($this->tag_list['option']["$remove"]))
			{
				$this->tag_list['option']["$remove"]['callback'] = 'handle_wysiwyg_unparsable';
				unset($this->tag_list['option']["$remove"]['html'], $this->tag_list['option']["$remove"]['strip_space_after']);
			}
			if (isset($this->tag_list['no_option']["$remove"]))
			{
				$this->tag_list['no_option']["$remove"]['callback'] = 'handle_wysiwyg_unparsable';
				unset($this->tag_list['no_option']["$remove"]['html'], $this->tag_list['option']["$remove"]['strip_space_after']);
			}
		}

		// make the "pre" tags use the correct handler
		foreach (array('code', 'php', 'html') AS $pre_tag)
		{
			if (isset($this->tag_list['no_option']["$pre_tag"]))
			{
				$this->tag_list['no_option']["$pre_tag"]['callback'] = 'handle_preformatted_tag';
				unset($this->tag_list['no_option']["$pre_tag"]['html'], $this->tag_list['option']["$pre_tag"]['strip_space_after']);
			}
		}

		$this->type = is_browser('ie') ? 'ie' : 'moz_css';
	}

	/**
	* Object to provide the implementation of the table helper to use.
	* See setTableHelper and getTableHelper.
	*
	* @var	vBForum_BBCodeHelper_Table
	*/
	protected $table_helper = null;

	/**
	* External method to set/change the table helper implementation if necessary.
	* Generally won't be used.
	*
	* @param	vBForum_BBCodeHelper_Table	Alternative helper
	*/
	public function setTableHelper(vBForum_BBCodeHelper_Table $helper)
	{
		$this->table_helper = $helper;
	}

	/**
	* Fetches the table helper in use. It also acts as a lazy initializer.
	* If no table helper has been explicitly set, it will instantiate
	* the class's default.
	*
	* @return	vBForum_BBCodeHelper_Table	Table helper object
	*/
	public function getTableHelper()
	{
		if (!$this->table_helper)
		{
			require_once DIR . '/packages/vbforum/bbcodehelper/table/wysiwyg.php' ;
			$this->table_helper = new vBForum_BBCodeHelper_Table_Wysiwyg($this);
		}

		return $this->table_helper;
	}

	protected function parsePageTag($page_title)
	{
		return '<h3 class="wysiwyg_pagebreak">' . $page_title . '</h3>';
	}

	protected function parsePreviewBreakTag($text)
	{
		return '<hr class="previewbreak" />' . $text;
	}

	protected function parseTableTag($content, $params = '')
	{
		$helper = $this->getTableHelper();
		return $helper->parseTableTag($content, $params);
	}

	protected function rematchIELinebreaks($match)
	{
		$text = $match[3];
		$open = $match[1];
		$close = $match[2];

		if (strpos($text, "\n") !== false)
		{
			$text = str_replace("</p>\n<p>", "<br>\n", $text);
			return '[' . $open . ']' . $text . '[/' . $close . ']';
		}
		else
		{
			return $match[0];
		}
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
					'callback' => 'handle_wysiwyg_unparsable',
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
					'callback' => 'handle_wysiwyg_unparsable',
					'strip_empty'		=> intval($customtag['options']) & $this->registry->bf_misc['bbcodeoptions']['strip_empty'],
					'stop_parse' 		=> intval($customtag['options']) & $this->registry->bf_misc['bbcodeoptions']['stop_parse'],
					'disable_smilies'	=> intval($customtag['options']) & $this->registry->bf_misc['bbcodeoptions']['disable_smilies'],
					'disable_wordwrap'	=> intval($customtag['options']) & $this->registry->bf_misc['bbcodeoptions']['disable_wordwrap']
				);
			}
		}
	}

	/**
	* Handles an [img] tag.
	*
	* @param	string	The text to search for an image in.
	* @param	string	Whether to parse matching images into pictures or just links.
	*
	* @return	string	Text representation of the tag.
	*/
	function handle_bbcode_img($bbcode, $do_imgcode, $has_img_code = false)
	{
		global $vbphrase, $vbulletin;

		if (($has_img_code == 2 OR $has_img_code == 3) AND preg_match_all('#\[attach=config\](\d+)\[/attach\]#i', $bbcode, $matches))
		{
			$search = array();
			$replace = array();

			$ids = array();
			$attachments = $vbulletin->db->query_read_slave("
				SELECT a.settings, a.attachmentid
				FROM " . TABLE_PREFIX . "attachment AS a
				WHERE
					a.attachmentid IN (" . implode(',', $matches[1]) . ")
						AND
					a.settings <> ''				
				LIMIT 50
			");
			while ($attachment = $vbulletin->db->fetch_array($attachments))
			{
				$extension = strtolower(file_extension($attachment['filename']));
				$ids[$attachment['attachmentid']] = @unserialize($attachment['settings']);
			}

			foreach($matches[1] AS $attachmentid)
			{
				$fullsize = false;
				$class = array('previewthumb');
				$attribs = array(
					'src'          => $vbulletin->options['bburl'] . '/attachment.php?attachmentid=' . $attachmentid . '&amp;stc=1',
					'attachmentid' => $attachmentid,
					'alt'          => '',
					'id'           => 'vbattach_' . $attachmentid,
				);
				if ($setting = $ids[$attachmentid])
				{
					if ($setting['styles'])
					{
						$attribs['style'] = $setting['styles'];
					}
					if ($setting['alignment'])
					{
						switch ($setting['alignment'])
						{
								case 'left':
									$class[] = 'align_left';
									break;
								case 'center':
									$class[] = 'align_center';
									break;
								case 'right':
									$class[] = 'align_right';
									break;							
						}
					}
					if ($setting['size'])
					{
						switch ($setting['size'])
						{
							case 'thumbnail':
								$class[] = 'size_thumbnail';
								break;
							case 'medium':
								$class[] = 'size_medium';
								break;
							case 'large':
								$class[] = 'size_large';
								break;
							case 'fullsize':
								$class[] = 'size_fullsize';
								break;
						}						
					}
				}
				$attribs['class'] = implode(' ', $class);
				$imgtag = '';
				foreach ($attribs AS $tag => $value)
				{
					$imgtag .= "$tag=\"$value\" ";
				}
				
				$search[] = '#\[attach=config\](' . $attachmentid . ')\[/attach\]#i';
				$replace[] = '<img ' . $imgtag . '/>';
			}

			$bbcode = preg_replace($search, $replace, $bbcode);
		}

		if ($has_img_code == 1 OR $has_img_code == 3)
		{
			if ($do_imgcode AND ($this->registry->userinfo['userid'] == 0 OR $this->registry->userinfo['showimages']))
			{
				// do [img]xxx[/img]
				$bbcode = preg_replace('#\[img\]\s*(https?://([^*\r\n]+|[a-z0-9/\\._\- !]+))\[/img\]#iUe', "\$this->handle_bbcode_img_match('\\1')", $bbcode);
			}
		}

		return $bbcode;
	}

	/**
	* Handles a [code]/[html]/[php] tag. In WYSIYWYG parsing, keeps the tag but replaces
	* <space><space> with a non-breaking space followed by a space.
	*
	* @param	string	The code to display
	*
	* @return	string	Tag with spacing replaced
	*/
	function handle_preformatted_tag($code)
	{
		$current_tag =& $this->current_tag;
		$tag_name = (isset($current_tag['name_orig']) ? $current_tag['name_orig'] : $current_tag['name']);

		return "[$tag_name]" . $this->emulate_pre_tag($code) . "[/$tag_name]";
	}

	/**
	* This does it's best to emulate an HTML pre tag and keep whitespace visible
	* in a standard HTML environment. Useful with code/html/php tags.
	*
	* @param	string	Code to process
	*
	* @return	string	Processed code
	*/
	function emulate_pre_tag($code)
	{
		$code = str_replace('  ', ' &nbsp;', $code);
		$code = preg_replace('#(\r\n|\n|\r|<p>)( )(?!([\r\n]}|<p>))#i', '$1&nbsp;', $code);
		return $code;
	}

	/**
	* Perform word wrapping on the text. WYSIWYG parsers should not
	* perform wrapping, so this function does nothing.
	*
	* @param	string	Text to be used for wrapping
	*
	* @return	string	Input string (unmodified)
	*/
	function do_word_wrap($text)
	{
		return $text;
	}

	/**
	* Parses out specific white space before or after cetain tags, rematches
	* tags where necessary, and processes line breaks.
	*
	* @param	string	Text to process
	* @param	bool	Whether to translate newlines to HTML breaks (unused)
	*
	* @return	string	Processed text
	*/
	function parse_whitespace_newlines($text, $do_nl2br = true)
	{
		$whitespacefind = array(
			'#(\r\n|\n|\r)?( )*(\[\*(=[0-9]+)?\]|\[/list|\[list|\[indent)#si',
			'#(/list\]|/indent\])( )*(\r\n|\n|\r)?#si'
		);
		$whitespacereplace = array(
			'\3',
			'\1'
		);
		$text = preg_replace($whitespacefind, $whitespacereplace, $text);

		/*
		if ($this->is_wysiwyg('ie'))
		{
			// this fixes an issue caused by odd nesting of tags. This causes IE's
			// WYSIWYG editor to display the output as vB will display it
			$rematch_find = array(
				'#\[((color)=.*)\](.*)\[/\\2\]#siUe',
				'#\[((font)=.*)\](.*)\[/\\2\]#siUe',
				'#\[((size)=.*)\](.*)\[/\\2\]#siUe',
			);
			$text = preg_replace($rematch_find, "\$this->bbcode_rematch_tags_wysiwyg('\\3', '\\2', '\\1')", $text);

			$rematch_find = array(
				'#\[(b)\](((?>[^[]+?)|(?R)|\[)*)\[/\\1\]#siUe',
				'#\[(i)\](((?>[^[]+?)|(?R)|\[)*)\[/\\1\]#siUe',
				'#\[(u)\](((?>[^[]+?)|(?R)|\[)*)\[/\\1\]#siUe',
				'#\[(left)\](((?>[^[]+?)|(?R)|\[)*)\[/\\1\]#siUe',
				'#\[(center)\](((?>[^[]+?)|(?R)|\[)*)\[/\\1\]#siUe',
				'#\[(right)\](((?>[^[]+?)|(?R)|\[)*)\[/\\1\]#siUe',
			);
			$text = preg_replace($rematch_find, "\$this->bbcode_rematch_tags_wysiwyg('\\2', '\\1')", $text);

			$text = '<p>' . preg_replace('#(\r\n|\n|\r)#', "</p>\n<p>", ltrim($text)) . '</p>';

			if (strpos('[/list', strtolower($text))) // workaround bug #22749
			{
				$text = preg_replace('#(\[list(=(&quot;|"|\'|)(.*)\\3)?\])(((?>[^\[]*?|(?R))|(?>.))*)(\[/list(=\\3\\4\\3)?\])#siUe', "\$this->remove_wysiwyg_breaks('\\0')", $text);
			}
			$text = preg_replace('#<p>\s*</p>(?!\s*\[list|$)#i', '<p>&nbsp;</p>', $text);

			$text = str_replace('<p></p>', '', $text);

			// heading tags that span multiple lines shouldn't have p tags within
			// and they can't be split into multiple tags
			$text = preg_replace_callback(
				array(
					'#\[((h)=.*)\](.*)\[/\\2\]#siU',
					'#\[((page))\](.*)\[/\\2\]#siU',
				),
				array($this, 'rematchIELinebreaks'),
				$text
			);

			// close any open p tags that come up to heading tags
			$text = preg_replace(
				array(
					'#(\[h=[1-6]\].*\[/h\])#siU',
					'#(\[page\].*\[/page\])#siU',
				),
				'</p>\\1<p>',
				$text
			);

			$text = str_replace('<p></p>', '', $text);
		}
		else
		{
			$text = nl2br($text);
		}
*/

		$text = nl2br($text);
		// convert tabs to four &nbsp;
		$text = str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', $text);

		return $text;
	}

	/**
	* Parse an input string with BB code to a final output string of HTML
	*
	* @param	string	Input Text (BB code)
	* @param	bool	Whether to parse smilies
	* @param	bool	Whether to parse img code (for the video bbcodes)
	* @param	bool	Whether to allow HTML (for smilies)
	*
	* @return	string	Ouput Text (HTML)
	*/
	function parse_bbcode($input_text, $do_smilies, $do_imgcode, $do_html = false)
	{
		$text = $this->parse_array($this->fix_tags($this->build_parse_array($input_text)), $do_smilies, $do_imgcode, $do_html);

		if ($this->is_wysiwyg('ie'))
		{
			$text = preg_replace('#<p>((<[^>]+>)*)<(p|div) align="([a-z]+)">(.*)</\\3>((<[^>]+>)*)</p>#siU', '<p align="\\4">\\1\\5\\6</p>', $text);

			// by now, any empty p tags are things that used to be in the form of <p>[tag][/tag]</p>,
			// so we need to leave them to be equivalent to the nl2br version
			$text = preg_replace('#<p></p>#siU', '<p>&nbsp;</p>', $text);

			// rematch <p> tags around blockquotes (from the indent tag)
			do
			{
				$orig_text = $text;
				$text = preg_replace('#<blockquote>(?!<p>)(.*)</blockquote>(?!<p>)#siU', '</p><blockquote><p>\\1</p></blockquote><p>', $text);
			}
			while ($orig_text != $text);

			// it's possible the blockquote rematch caused some blank p tags, so remove them
			$text = preg_replace('#<p></p>#siU', '', $text);
		}

		// table
		if (substr($text, -8) == '</table>')
		{
			// must add a trailing line break to a table that ends the text
			if ($this->is_wysiwyg('ie'))
			{
				$text .= "<p></p>";
			}
			else
			{
				$text .= "<br />";
			}
		}

		// need to display smilies in code/php/html tags as literals
		$text = preg_replace('#\[(code|php|html)\](.*)\[/\\1\]#siUe', "\$this->strip_smilies(str_replace('\\\"', '\"', '\\0'), true)", $text);

		return $text;
	}

	/**
	* Call back to handle any tag that the WYSIWYG editor can't handle. This
	* parses the tag, but returns an unparsed version of it. The advantage of
	* this method is that any parsing directives (no parsing, no smilies, etc)
	* will still be applied to the text within.
	*
	* @param	string	Text inside the tag
	*
	* @return	string	The unparsed tag and the text within it
	*/
	function handle_wysiwyg_unparsable($text)
	{
		$tag_name = (isset($this->current_tag['name_orig']) ? $this->current_tag['name_orig'] : $this->current_tag['name']);
		return '[' . $tag_name .
			($this->current_tag['option'] !== false ?
				('=' . $this->current_tag['delimiter'] . $this->current_tag['option'] . $this->current_tag['delimiter']) :
				''
			) . ']' . $text . '[/' . $tag_name . ']';
	}

	/**
	* Handles a single bullet of a list
	*
	* @param	string	Text of bullet
	*
	* @return	string	HTML for bullet
	*/
	function handle_bbcode_list_element($text, $indentvalue = 0)
	{
		$bad_tag_list = '(br|p|li|ul|ol)';

		$exploded = preg_split("#(\r\n|\n|\r)#", $text);

		$output = '';
		foreach ($exploded AS $value)
		{
			if (!preg_match('#(</' . $bad_tag_list . '>|<' . $bad_tag_list . '\s*/>)$#iU', $value))
			{
				if (trim($value) == '')
				{
					$value = '&nbsp;';
				}
				$output .= $value . "<br />\n";
			}
			else
			{
				$output .= "$value\n";
			}
		}
		$output = preg_replace('#<br />+\s*$#i', '', $output);

		$indent = '';
		$indentvalue = intval($indentvalue);
		if ($indentvalue)
		{
			$dir = $this->registry->stylevars['textdirection']['string'] == 'rtl' ? 'right' : 'left';
			$indent = " style=\"margin-{$dir}: {$indentvalue}px\"";
		}

		return "<li{$indent}>$output</li>";
	}

	/**
	* Returns whether this parser is a WYSIWYG parser if no type is specified.
	* If a type is specified, it checks whether our type matches
	*
	* @param	string|null	Type of parser to match; null represents any type
	*
	* @return	bool		True if it is; false otherwise
	*/
	function is_wysiwyg($type = null)
	{
		if ($type == null)
		{
			return true;
		}
		else
		{
			return ($this->type == $type);
		}
	}

	/**
	* Automatically inserts a closing tag before a line break and reopens it after.
	* Also wraps the text in the tag. Workaround for IE WYSIWYG issue.
	*
	* @param	string	Text to search through
	* @param	string	Tag to close and reopen (can't include the option)
	* @param	string	Raw text that opens the tag (this needs to include the option if there is one)
	*
	* @return	string	Processed text
	*/
	function bbcode_rematch_tags_wysiwyg($innertext, $tagname, $tagopen_raw = '')
	{
		// This function replaces line breaks with [/tag]\n[tag].
		// It is intended to be used on text inside [tag] to fix an IE WYSIWYG issue.

		$tagopen_raw = str_replace('\"', '"', $tagopen_raw);
		if (!$tagopen_raw)
		{
			$tagopen_raw = $tagname;
		}

		$innertext = str_replace('\"', '"', $innertext);
		return "[$tagopen_raw]" . preg_replace('#(\r\n|\n|\r)#', "[/$tagname]\n[$tagopen_raw]", $innertext) . "[/$tagname]";
	}

	/**
	* Removes IE's WYSIWYG breaks from within a list. -- this function is no longer called..
	*
	* @param	string	Text to remove breaks from. Should start with [list] and end with [/list]
	*
	* @return	string	Text with breaks removed
	*/
	function remove_wysiwyg_breaks($fulltext)
	{
		$fulltext = str_replace('\"', '"', $fulltext);
		preg_match('#^(\[list(=(&quot;|"|\'|)(.*)\\3(?:|INDENT=(?:[0-9+])?)?\])(.*?)(\[/list(=\\3\\4\\3)?\])$#siU', $fulltext, $matches);
		$prepend = $matches[1];
		$innertext = $matches[5];

		$find = array("</p>\n<p>", '<br />', '<br>');
		$replace = array("\n", "\n", "\n");
		$innertext = str_replace($find, $replace, $innertext);

		if ($this->is_wysiwyg('ie'))
		{
			return '</p>' . $prepend . $innertext . '[/list]<p>';
		}
		else
		{
			return $prepend . $innertext . '[/list]';
		}
	}
}

/**
* BB code parser for the image checks. Only [img], [attach], [video] tags are actually
* parsed with this parser to prevent user-added <img> tags from counting.
*
* @package 		vBulletin
* @version		$Revision: 44293 $
* @date 		$Date: 2011-06-08 12:17:25 -0700 (Wed, 08 Jun 2011) $
*
*/
class vB_BbCodeParser_ImgCheck extends vB_BbCodeParser
{
	/**
	* Constructor. Sets up the tag list.
	*
	* @param	vB_Registry	Reference to registry object
	* @param	array		List of tags to parse
	* @param	boolean		Whether to append custom tags (they will not be parsed anyway)
	*/
	function vB_BbCodeParser_ImgCheck(&$registry, $tag_list = array(), $append_custom_tags = true)
	{
		parent::vB_BbCodeParser($registry, $tag_list, $append_custom_tags);

		$skiplist_option = array(
			'video'
		);

		// change all unparsable tags to use the unparsable callback
		// [img] and [attach] tags are not parsed via the normal parser
		foreach ($this->tag_list['option'] AS $tagname => $info)
		{
			if (in_array($tagname, $skiplist_option))
			{
				continue;
			}
			if (isset($this->tag_list['option']["$tagname"]))
			{
				$this->tag_list['option']["$tagname"]['callback'] = 'handle_unparsable';
				unset($this->tag_list['option']["$tagname"]['html']);
			}
		}

		foreach ($this->tag_list['no_option'] AS $tagname => $info)
		{
			if (isset($this->tag_list['no_option']["$tagname"]))
			{
				$this->tag_list['no_option']["$tagname"]['callback'] = 'handle_unparsable';
				unset($this->tag_list['no_option']["$tagname"]['html']);
			}
		}
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
					'callback' 		=> 'handle_unparsable',
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
					'callback' 		=> 'handle_unparsable',
					'strip_empty'		=> intval($customtag['options']) & $this->registry->bf_misc['bbcodeoptions']['strip_empty'],
					'stop_parse' 		=> intval($customtag['options']) & $this->registry->bf_misc['bbcodeoptions']['stop_parse'],
					'disable_smilies'	=> intval($customtag['options']) & $this->registry->bf_misc['bbcodeoptions']['disable_smilies'],
					'disable_wordwrap'	=> intval($customtag['options']) & $this->registry->bf_misc['bbcodeoptions']['disable_wordwrap']
				);
			}
		}
	}

	/**
	* Call back to replace any tag with itself. In the context of this class,
	* very few tags are actually parsed.
	*
	* @param	string	Text inside the tag
	*
	* @return	string	The unparsed tag and the text within it
	*/
	function handle_unparsable($text)
	{
		$current_tag =& $this->current_tag;

		return "[$current_tag[name]" .
			($current_tag['option'] !== false ?
				"=$current_tag[delimiter]$current_tag[option]$current_tag[delimiter]" :
				''
			) . "]$text [/$current_tag[name]]";
	}

	/**
	* Handles a [video] tag. Displays a movie.
	*
	* @param	string	The code to display
	*
	* @return	string	<img> - expectation that occurrences of <img> are checked
	*/
	function handle_bbcode_video($url, $option)
	{
		global $vbulletin, $vbphrase, $show;

		$params = array();
		$options = explode(';', $option);
		$provider = strtolower($options[0]);
		$code = $options[1];

		if (!$code OR !$provider)
		{
			return '[video=' . $option . ']' . $url . '[/video]';
		}

		return '<img />';
	}

	/**
	* Handles an [img] tag.
	*
	* @param	string	The text to search for an image in.
	* @param	string	Whether to parse matching images into pictures or just links.
	*
	* @return	string	HTML representation of the tag.
	*/
	function handle_bbcode_img($bbcode, $do_imgcode, $has_img_code = false)
	{
		$show_images = $this->registry->userinfo['showimages'];
		$this->registry->userinfo['showimages'] = true;

		$bbcode = parent::handle_bbcode_img($bbcode, $do_imgcode, $has_img_code);
		$this->registry->userinfo['showimages'] = $show_images;
		return $bbcode;
	}
}

class vB_BbCodeParser_PrintableThread extends vB_BbCodeParser
{

	var $printable = true;

	/**
	* Constructor. Sets up the tag list.
	*
	* @param	vB_Registry	Reference to registry object
	* @param	array		List of tags to parse
	* @param	boolean		Whether to append custom tags (they will not be parsed anyway)
	*/
	function vB_BbCodeParser_PrintableThread(&$registry, $tag_list = array(), $append_custom_tags = true)
	{
		parent::vB_BbCodeParser($registry, $tag_list, $append_custom_tags);
	}

	/**
	* Parse the string with the selected options
	*
	* @param	string	Unparsed text
	* @param	bool	Whether to allow HTML -- ignored, always true
	* @param	bool	Whether to parse smilies -- ignored, always false
	* @param	bool	Whether to parse BB code
	* @param	bool	Whether to parse the [img] BB code (independent of $do_bbcode)
	* @param	bool	Whether to run nl2br -- ignored, always false
	* @param	bool	Whether the post text is cachable -- ignored, always false
	*
	* @return	string	Parsed text
	*/
	function do_parse($text, $do_html = false, $do_smilies = true, $do_bbcode = true , $do_imgcode = true, $do_nl2br = true, $cachable = false)
	{
		return parent::do_parse($text, $do_html, $do_smilies, $do_bbcode, false, $do_nl2br, $cachable);
	}
}

/**
* BB code parser that generates plain text. This is basically useful for emails.
*
* @package 		vBulletin
* @version		$Revision: 44293 $
* @date 		$Date: 2011-06-08 12:17:25 -0700 (Wed, 08 Jun 2011) $
*
*/
class vB_BbCodeParser_PlainText extends vB_BbCodeParser
{
	/**
	* A list of tags that are representable in plain text. Parsed and merged
	* into $this->tag_list in the constructor. If a tag is not present, the tag
	* markup and option are removed; the value is printed straight out.
	* If the value of an element is false, the tag is considered parsable,
	* but nothing is changed in the tag list; otherwise, the tag list value is
	* overwrriten.
	*
	* @var	array
	*/
	var $plaintext_tags = array(
		'option' => array(
			'quote' => false, // overridden

			'list' => false, // overridden

			'email' => false, // overridden
			'url' => false, // overridden
		),

		'no_option' => array(
			'noparse' => false, // no need to override
			'quote' => false, // overridden

			'b' => array(
				'html' => '*%1$s*', // *text here*
				'strip_empty' => true
			),
			'i' => array(
				'html' => '%1$s',
				'strip_empty' => true
			),
			'u' => array(
				'html' => '_%1$s_', // _text here_
				'strip_empty' => true
			),
			'highlight' => array(
				'html' => '*%1$s*', // *text here* -- same as bold
				'strip_empty' => true
			),

			// these are block level tags, so lets do a basic emulation and put a line break after
			'left' => array(
				'html' => "%1\$s\n",
				'strip_empty' => true,
				'strip_space_after' => 1
			),
			'center' => array(
				'html' => "%1\$s\n",
				'strip_empty' => true,
				'strip_space_after' => 1
			),
			'right' => array(
				'html' => "%1\$s\n",
				'strip_empty' => true,
				'strip_space_after' => 1
			),
			'indent' => array(
				'html' => "%1\$s\n",
				'strip_empty' => true,
				'strip_space_after' => 1
			),

			'list' => false, // overridden

			'email' => false, // overridden
			'url' => false, // overridden

			'code' => false, // overridden
			'html' => false, // overridden
			'php' => false // overridden
		)
	);

	/**
	* The ID of the language the parser is using to output the text.
	*
	* @var	integer
	*/
	var $parsing_language = -1;

	/**
	* Constructor. Sets up the tag list.
	*
	* @param	vB_Registry	Reference to registry object
	* @param	array		List of tags to parse
	* @param	boolean		Whether to append custom tags (they will not be parsed anyway)
	*/
	function vB_BbCodeParser_PlainText(&$registry, $tag_list = array(), $append_custom_tags = true)
	{
		parent::vB_BbCodeParser($registry, $tag_list, $append_custom_tags);


		$forum_path_full = '';
		if ($registry->options['vbforum_url'])
		{
			$forum_path_full = rtrim($registry->options['vbforum_url'], '/') . '/';
		}

		if (strpos($forum_path_full, '://') === false)
		{
			$forum_path_full =  rtrim($registry->options['bburl'], '/') . '/' . $forum_path_full; 
		}	

		// add thread and post tags as parsed -- this can't be done above
		// because I need to use a variable in $registry
		$this->plaintext_tags['option']['thread']  = array(
			'html' => '%1$s (' . $forum_path_full . 'showthread.php?t=%2$s)',
			'option_regex' => '#^\d+$#',
			'strip_empty' => true
		);
		$this->plaintext_tags['no_option']['thread']  = array(
			'html' => $forum_path_full . 'showthread.php?t=%1$s',
			'data_regex' => '#^\d+$#',
			'strip_empty' => true
		);

		$this->plaintext_tags['option']['post']  = array(
			'html' => '%1$s (' . $forum_path_full . 'showthread.php?p=%2$s#post%2$s)',
			'option_regex' => '#^\d+$#',
			'strip_empty' => true
		);
		$this->plaintext_tags['no_option']['post']  = array(
			'html' => $forum_path_full . 'showthread.php?p=%1$s#post%1$s',
			'data_regex' => '#^\d+$#',
			'strip_empty' => true
		);

		// update all parsable tags to their new value and make unparsable tags disappear
		foreach ($this->tag_list['option'] AS $tagname => $info)
		{
			if (!isset($this->plaintext_tags['option']["$tagname"]))
			{
				$this->tag_list['option']["$tagname"]['html'] = '%1$s';
				unset($this->tag_list['option']["$tagname"]['callback']);
			}
			else if ($this->plaintext_tags['option']["$tagname"] !== false)
			{
				$this->tag_list['option']["$tagname"] = $this->plaintext_tags['option']["$tagname"];
			}
		}

		foreach ($this->tag_list['no_option'] AS $tagname => $info)
		{
			if (!isset($this->plaintext_tags['no_option']["$tagname"]))
			{
				$this->tag_list['no_option']["$tagname"]['html'] = '%1$s';
				unset($this->tag_list['no_option']["$tagname"]['callback']);
			}
			else if ($this->plaintext_tags['no_option']["$tagname"] !== false)
			{
				$this->tag_list['no_option']["$tagname"] = $this->plaintext_tags['no_option']["$tagname"];
			}
		}
	}

	/**
	* Sets the language of the parser and the phrases used.
	*
	* @param	integer	Language ID to parse with
	*/
	function set_parsing_language($language)
	{
		$this->parsing_language = intval($language);
	}

	/**
	* Fetches a phrase used by the parser in the currently selected parsing language.
	* The only phrases accepted are in the BB code phrase group and begin with
	* "bbcode_plaintext_".
	*
	* @param	string	Name of phrase to fetch.
	*
	* @return	string	Value of the phrase.
	*/
	function fetch_parser_phrase($phrasename)
	{
		global $vbphrase;
		static $parser_phrase_cache = null;

		if (!is_array($parser_phrase_cache))
		{
			$parser_phrase_cache = array();

			$getphrases = $this->registry->db->query_read_slave("
				SELECT varname, text, languageid
				FROM " . TABLE_PREFIX . "phrase AS phrase
				WHERE fieldname = 'bbcode'
					AND varname LIKE 'bbcode\\_plaintext\\_%'
			");
			while ($getphrase = $this->registry->db->fetch_array($getphrases))
			{
				$parser_phrase_cache["$getphrase[varname]"]["$getphrase[languageid]"] = $getphrase['text'];
			}
			$this->registry->db->free_result($getphrases);
		}

		$phrase =& $parser_phrase_cache["$phrasename"];

		$languageid = $this->parsing_language;

		if ($languageid == -1)
		{
			// didn't pass in a languageid as this is the default value so use the browsing user's languageid
			$languageid = LANGUAGEID;
		}
		else if ($languageid == 0)
		{
			// the user is using forum default
			$languageid = $this->registry->options['languageid'];
		}

		if (isset($phrase["$languageid"]))
		{
			$messagetext = $phrase["$languageid"];
		}
		else if (isset($phrase[0]))
		{
			$messagetext = $phrase[0];
		}
		else if (isset($phrase['-1']))
		{
			$messagetext = $phrase['-1'];
		}
		else
		{
			$messagetext = "Could not find phrase '$phrasename'.";
		}

		$messagetext = str_replace('%', '%%', $messagetext);
		$messagetext = preg_replace('#\{([0-9])+\}#sU', '%\\1$s', $messagetext);

		return $messagetext;
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
					'html' 			=> '%1$s',
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
					'html' => '%1$s',
					'strip_empty'		=> intval($customtag['options']) & $this->registry->bf_misc['bbcodeoptions']['strip_empty'],
					'stop_parse' 		=> intval($customtag['options']) & $this->registry->bf_misc['bbcodeoptions']['stop_parse'],
					'disable_smilies'	=> intval($customtag['options']) & $this->registry->bf_misc['bbcodeoptions']['disable_smilies'],
					'disable_wordwrap'	=> intval($customtag['options']) & $this->registry->bf_misc['bbcodeoptions']['disable_wordwrap']
				);
			}
		}
	}

	/**
	* Parses smilie -- plain text doesn't parse smilies, so just return
	*
	* @param	string	Text with smilie codes
	* @param	bool	Whether HTML is allowed (unused)
	*
	* @return	string	Text with smilie codes remaining
	*/
	function parse_smilies($text, $do_html = false)
	{
		return $text;
	}

	/**
	* Do not do wordwrapping in the plain text parser.
	*
	* @param	string	Text to wrap
	*
	* @return	string	Text that was originally passed in
	*/
	function do_word_wrap($text)
	{
		return $text;
	}

	/**
	* Parse the string with the selected options
	*
	* @param	string	Unparsed text
	* @param	bool	Whether to allow HTML -- ignored, always true
	* @param	bool	Whether to parse smilies -- ignored, always false
	* @param	bool	Whether to parse BB code
	* @param	bool	Whether to parse the [img] BB code (independent of $do_bbcode)
	* @param	bool	Whether to run nl2br -- ignored, always false
	* @param	bool	Whether the post text is cachable -- ignored, always false
	*
	* @return	string	Parsed text
	*/
	function do_parse($text, $do_html = false, $do_smilies = true, $do_bbcode = true , $do_imgcode = true, $do_nl2br = true, $cachable = false)
	{
		global $html_allowed;

		// explicitly disable the options that don't make sense for a plain text parser
		$do_html = true;
		$do_smilies = false;
		$do_nl2br = false;
		$cachable = false;

		$this->options = array(
			'do_html' => $do_html,
			'do_smilies' => $do_smilies,
			'do_bbcode' => $do_bbcode,
			'do_imgcode' => $do_imgcode,
			'do_nl2br' => $do_nl2br,
			'cachable' => $cachable
		);
		$this->cached = array('text' => '', 'has_images' => 0);

		// ********************* REMOVE HTML CODES ***************************
		$html_allowed = $do_html;

		$text = $this->parse_whitespace_newlines($text, $do_nl2br);

		// ********************* PARSE BBCODE TAGS ***************************
		if ($do_bbcode)
		{
			$text = $this->parse_bbcode($text, $do_smilies, $do_imgcode, $do_html);
		}

		// parse out nasty active scripting codes
		static $global_find = array('/javascript:/si', '/about:/si', '/vbscript:/si');
		static $global_replace = array('java_script:', 'about_:', 'vbscript_:');
		$text = preg_replace($global_find, $global_replace, $text);

		// run the censor
		$text = fetch_censored_text($text);
		$has_img_tag = ($do_bbcode ? $this->contains_bbcode_img_tags($text) : 0);

		// do [img] tags if the item contains images
		if(($do_bbcode OR $do_imgcode) AND $has_img_tag)
		{
			$text = $this->handle_bbcode_img($text, $do_imgcode, $has_img_tag);
		}

		// it is possible some HTML has crept in when escaping things to prevent parsing
		// let's fix that. Only touch printable ASCII characters though!
		$text = preg_replace(
			'/&#([0-9]{2,3});/e',
			'(($1 >= 32 AND $1 <= 127) ? chr($1) : "&#$1;")',
			$text
		);

		($hook = vBulletinHook::fetch_hook('bbcode_parse_complete')) ? eval($hook) : false;

		return $text;
	}

	/**
	* Handles an [email] tag.
	*
	* @param	string	If tag has option, the displayable email name. Else, the email address.
	* @param	string	If tag has option, the email address.
	*
	* @return	string	Text representation of the tag.
	*/
	function handle_bbcode_email($text, $link = '')
	{
		$rightlink = trim($link);
		if (empty($rightlink))
		{
			// no option -- use param
			$rightlink = trim($text);
		}

		if (!trim($link) OR $text == $rightlink)
		{
			return $text;
		}
		else if (is_valid_email($rightlink))
		{
			return "$text ($rightlink)";
		}
		else
		{
			return $text;
		}
	}

	/**
	* Handles a [url] tag. Creates a link to another web page.
	*
	* @param	string	If tag has option, the displayable name. Else, the URL.
	* @param	string	If tag has option, the URL.
	*
	* @return	string	Text representation of the tag.
	*/
	function handle_bbcode_url($text, $link)
	{
		$rightlink = trim($link);
		if (empty($rightlink))
		{
			// no option -- use param
			$rightlink = trim($text);
		}

		if (!preg_match('#^[a-z0-9]+(?<!about|javascript|vbscript):#si', $rightlink))
		{
			$rightlink = "http://$rightlink";
		}

		if (!trim($link) OR $text == $rightlink)
		{
			return $text;
		}
		else
		{
			return "$text ($rightlink)";
		}
	}

	/**
	* Handles a [quote] tag. Displays a string in an area indicating it was quoted from someone/somewhere else.
	*
	* @param	string	The body of the quote.
	* @param	string	If tag has option, the original user to post.
	*
	* @return	string	Text representation of the tag.
	*/
	function handle_bbcode_quote($message, $username = '')
	{
		global $vbulletin, $vbphrase;

		$message = $this->strip_front_back_whitespace($message, 1);

		if (preg_match('/^(.+)(?<!&#[0-9]{3}|&#[0-9]{4}|&#[0-9]{5});\s*(\d+)\s*$/U', $username, $match))
		{
			$username = $match[1];
			$postid = $match[2];
		}
		else
		{
			$postid = 0;
		}

		if ($username)
		{
			return "\n" . construct_phrase(
				$this->fetch_parser_phrase('bbcode_plaintext_quote_username_x_y'),
				$username,
				$message
			) . "\n";
		}
		else
		{
			return "\n" . construct_phrase(
				$this->fetch_parser_phrase('bbcode_plaintext_quote_x'),
				$message
			) . "\n";
		}
	}

	/**
	* Handles a [php] tag. Syntax highlighting is not possible in plain text.
	*
	* @param	string	The code to highlight.
	*
	* @return	string	Text representation of the tag.
	*/
	function handle_bbcode_php($code)
	{
		$code = $this->strip_front_back_whitespace($code, 1);
		return "\n" . construct_phrase(
			$this->fetch_parser_phrase('bbcode_plaintext_php_x'),
			$code
		) . "\n";
	}

	/**
	* Handles a [code] tag. Displays a preformatted string.
	*
	* @param	string	The code to display
	*
	* @return	string	HTML representation of the tag.
	*/
	function handle_bbcode_code($code)
	{
		$code = $this->strip_front_back_whitespace($code, 1);
		return "\n" . construct_phrase(
			$this->fetch_parser_phrase('bbcode_plaintext_code_x'),
			$code
		) . "\n";
	}

	/**
	* Handles an [html] tag. Syntax highlighting is not possible in plain text.
	*
	* @param	string	The code to highlight.
	*
	* @return	string	Text representation of the tag.
	*/
	function handle_bbcode_html($code)
	{
		$code = $this->strip_front_back_whitespace($code, 1);
		return "\n" . construct_phrase(
			$this->fetch_parser_phrase('bbcode_plaintext_html_x'),
			$code
		) . "\n";
	}

	/**
	* Handles a [list] tag. Makes a bulleted or ordered list.
	* Plain text only supports * lists and 1, 2, 3... lists
	*
	* @param	string	The body of the list.
	* @param	string	If tag has option, the type of list (ordered, etc).
	*
	* @return	string	Text representation of the tag.
	*/
	function handle_bbcode_list($text, $type = '')
	{
		$line_prefix = '';
		if (count($this->stack) > 1)
		{
			// look for other open list tags to figure out how much to
			// prefix this by
			foreach ($this->stack AS $key => $stack_tag)
			{
				if ($key == 0)
				{
					// the current tag is always stack[0]
					continue;
				}

				if ($stack_tag['type'] == 'tag' AND $stack_tag['name'] == 'list')
				{
					// 3 spaces per prefix for "x. ", 2 per for "* "
					if ($stack_tag['option'])
					{
						// going to be a "x. " type
						$line_prefix .= '   ';
					}
					else
					{
						$line_prefix .= '  ';
					}
				}
			}
		}

		$bullets = preg_split('#\s*\[\*(=[0-9]+)?]#s', trim($text), -1, PREG_SPLIT_NO_EMPTY);
		if (empty($bullets))
		{
			return "\n\n";
		}

		$output = '';
		$counter = 0;

		$total_bullets = count($bullets);
		$length_letters = ceil(log($total_bullets) / log(26));
		$length_decimal = ceil(log($total_bullets) / log(10));

		$options = explode('|', $type);
		$type = $options[0];

		foreach ($bullets AS $bullet)
		{
			$counter++;

			switch (trim($type))
			{
				case 'a':
					// 97 is the char code of "a"
					$letter_counter = $this->fetch_list_letter($counter, 97, $length_letters);
					$output .= "$line_prefix$letter_counter. $bullet\n";
					break;

				case 'A':
					// 65 is the char code of "A"
					$letter_counter = $this->fetch_list_letter($counter, 65, $length_letters);
					$output .= "$line_prefix$letter_counter. $bullet\n";
					break;

				case '':
					$output .= "$line_prefix* $bullet\n";
					break;

				// decimal is the default case if a type is specified
				case '1':
				default:
					$decimal_counter = str_pad($counter, $length_decimal, ' ', STR_PAD_LEFT);
					$output .= "$line_prefix$decimal_counter. $bullet\n";
					break;
			}
		}

		return "\n$output\n";
	}

	/**
	* Called when doing an A, B, C...AA, BB... list. Based on the counter, figures
	* out the appropriate letter to print. Wraps after 26 characters.
	*
	* @param	integer	Count of position in list (1 is first)
	* @param	integer	ASCII character code of starting character (65 for A, 97 for a)
	* @param	integer	Amount of characters to pad output to
	*
	* @return	string	Padded letter counter
	*/
	function fetch_list_letter($counter, $start_char_code, $pad_length = 0)
	{
		$letter_counter = '';
		while ($counter >= 0)
		{
			// this allows for "a" to be remainder 1
			// (eg, 1 = a, 26 = z, 27 = aa)
			$counter--;
			if ($counter < 26)
			{
				// this is actually the most significant digit
				$letter_counter = chr($start_char_code + $counter) . $letter_counter;
				break;
			}
			else
			{
				$letter_counter = chr($start_char_code + ($counter % 26)) . $letter_counter;
				$counter = floor($counter / 26);
			}
		}

		return str_pad($letter_counter, $pad_length, ' ', STR_PAD_LEFT);
	}

	/**
	* Handles an [img] tag.
	*
	* @param	string	The text to search for an image in.
	* @param	string	Whether to parse matching images into pictures or just links.
	*
	* @return	string	Text representation of the tag.
	*/
	function handle_bbcode_img($bbcode, $do_imgcode, $has_img_code = false)
	{
		global $vbphrase;

		if (($has_img_code == 2 OR $has_img_code == 3) AND preg_match_all('#\[attach(?:=(right|left))?\](\d+)\[/attach\]#i', $bbcode, $matches))
		{
			$search = array();
			$replace = array();
			foreach($matches[2] AS $key => $attachmentid)
			{
				$align = $matches[1]["$key"];
				$search[] = '#\[attach' . (!empty($align) ? '=' . $align : '') . '\](' . $attachmentid . ')\[/attach\]#i';
				$replace[] = construct_phrase($this->fetch_parser_phrase('bbcode_plaintext_attachment_x'), $attachmentid) .
					" ({$this->registry->options['bburl']}/attachment.php?attachmentid=$attachmentid)";
			}

			$bbcode = preg_replace($search, $replace, $bbcode);
		}

		// If you wanted to be able to edit [img] when editing a post instead of seeing the image, add the get_class() check from above
		if (($has_img_code == 1 OR $has_img_code == 3) AND $do_imgcode)
		{
			// do [img]xxx[/img]
			$bbcode = preg_replace('#\[img\]\s*(https?://([^<>*"]+|[a-z0-9/\\._\- !]+))\[/img\]#iUe', "\$this->handle_bbcode_img_match('\\1')", $bbcode);
		}

		return $bbcode;
	}

	/**
	* Handles a match of the [img] tag that will be displayed as an actual image.
	*
	* @param	string	The URL to the image.
	*
	* @return	string	HTML representation of the tag.
	*/
	function handle_bbcode_img_match($link)
	{
		// handle issue with results from regex
		$link = str_replace('\\"', '"', $link);

		return construct_phrase($this->fetch_parser_phrase('bbcode_plaintext_image_x'), $link) . ' ';
	}
}

/**
* BB code parser for the Video tag, this parser converts the [video]host[/video] tag into the [video=XYZ]host[/video] tag. Is only executed after a
* post is made.
*
* @package 		vBulletin
* @version		$Revision: 44293 $
* @date 		$Date: 2011-06-08 12:17:25 -0700 (Wed, 08 Jun 2011) $
*
*/
class vB_BbCodeParser_Video_PreParse extends vB_BbCodeParser
{

	/**
	* Constructor. Sets up the tag list.
	*
	* @param	vB_Registry	Reference to registry object
	* @param	array		List of tags to parse
	* @param	boolean		Whether to append custom tags (they will not be parsed anyway)
	*/
	function vB_BbCodeParser_Video_PreParse(&$registry, $tag_list = array(), $append_custom_tags = true)
	{
		parent::vB_BbCodeParser($registry, $tag_list, $append_custom_tags);
		$this->tag_list = array();

		// [NOPARSE]-- doesn't need a callback, just some flags
		$this->tag_list['no_option']['noparse'] = array(
			//'html'            => '[noparse]%1$s[/noparse]',
			'strip_empty'     => false,
			'stop_parse'      => true,
			'disable_smilies' => true,
			'callback'        => 'handle_noparse',
		);

		// [VIDEO]
		$this->tag_list['no_option']['video'] = array(
			'callback'    => 'handle_bbcode_video',
			'strip_empty' => true
		);
	}

	function handle_noparse($bbcode)
	{
		return '[noparse]' . str_replace(array('&#91;', '&#93;'), array('[', ']'), $bbcode) . '[/noparse]';
	}

	/**
	* Handles an [img] tag. Send it back as is
	*
	* @param	string	The text to search for an image in.
	*
	* @return	string	HTML representation of the tag.
	*/
	function handle_bbcode_img($bbcode)
	{
		return $bbcode;
	}


	/**
	* Collect parser options and misc data and fully parse the string into an HTML version- disable images
	*
	* @param	string	Unparsed text
	*
	* @return	string	Parsed text
	* @param	int|str	ID number of the forum whose parsing options should be used or a "special" string
	*/
	function parse($text, $forumid = 0)
	{
		return parent::parse($text, $forumid, false, false, '', false);
	}

	/**
	* Parse the string with the selected options
	*
	* @param	string	Unparsed text
	* @param	bool	Whether to allow HTML (true) or not (false)
	*
	* @return	string	Parsed text
	*/
	function do_parse($text)
	{
		return parent::do_parse($text, true, false, true, true, false, false, null, true);
	}

	/**
	* Handles a [video] tag. Displays a movie.
	*
	* @param	string	The code to display
	*
	* @return	string	HTML representation of the tag.
	*/
	function handle_bbcode_video($url, $option)
	{
		global $vbulletin, $vbphrase, $show;

		static $providers = array();
		static $scraped = 0;

		$search = $replace = array();
		($hook = vBulletinHook::fetch_hook('data_preparse_bbcode_video_start')) ? eval($hook) : false;

		if (!$providers)
		{
			$bbcodes = $vbulletin->db->query_read_slave("
				SELECT
					provider, url, regex_url, regex_scrape, tagoption
				FROM " . TABLE_PREFIX . "bbcode_video
				ORDER BY priority
			");
			while ($bbcode = $vbulletin->db->fetch_array($bbcodes))
			{
				$providers["$bbcode[tagoption]"] = $bbcode;
			}
		}

		if (!empty($providers))
		{
			$match = false;
			foreach ($providers AS $provider)
			{
				$addcaret = ($provider['regex_url'][0] != '^') ? '^' : '';
				if (preg_match('#' . $addcaret . $provider['regex_url'] . '#si', $url, $match))
				{
					break;
				}
			}
			if ($match)
			{
				if (!$provider['regex_scrape'] AND $match[1])
				{
					return '[video=' . $provider['tagoption'] . ';' . $match[1] . ']' . $url . '[/video]';
				}
				else if ($provider['regex_scrape'] AND $vbulletin->options['bbcode_video_scrape'] > 0 AND $scraped < $vbulletin->options['bbcode_video_scrape'])
				{
					require_once(DIR . '/includes/functions_file.php');
					$result = fetch_body_request($url);
					if (preg_match('#' . $provider['regex_scrape'] . '#si', $result, $scrapematch))
					{
						return '[video=' . $provider['tagoption'] . ';' . $scrapematch[1] . ']' . $url . '[/video]';
					}
					$scraped++;
				}
			}
		}

		return '[video]' . $url . '[/video]';
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 44293 $
|| ####################################################################
\*======================================================================*/
?>
