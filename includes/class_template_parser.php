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

require_once(DIR . '/includes/class_xml_dom.php');
require_once(DIR . '/includes/class_generator.php');

require_once(DIR . '/includes/template_tag_defs.php');
require_once(DIR . '/includes/template_curly_defs.php');

require_once(DIR . '/vb/exception.php');
require_once(DIR . '/vb/exception/parser.php');

/**
* A derivative of an HTML/XML tokenizing parser, used to parse special tags.
*
* Parses special tags embedded in an HTML document. This differs from a standard
* HTML parser in that the special tags can be embedded within valid HTML tags.
* Eg: <a <if condition="$something">href=""</if>>
* Only the named tags are treated as tags; all other data is treated as text.
*
* Uses state based parsing to move through the string, maintaining a pointer
* to the current position in the string.
*/
class vB_SpecialtyTagParser
{
	// namespace and names of tags to limit the search to
	private $namespace = '';
	private $tag_list = array();

	// The compiled tags to search for and the positions in the string they're at
	private $tag_search = array();
	private $locations = array();

	// data to search and strlen of data
	private $data = '';
	private $data_len = 0;

	// current position in the string
	private $position = 0;

	// output tokens
	private $tokens = array();

	//errors
	private $errors = array();

	/**
	* List of states. Key is state name, value is the name of the method to call.
	*
	* @var	array
	*/
	private $states = array(
		'start' => 'state_start_all',
		'literal' => 'state_literal',
		'tag' => 'state_tag',
		'end' => 'state_end',
	);

	private $state_tag = 'state_tag';

	/**
	* Constructor. Sets up the class and creates the tag search array as an optimization.
	*
	* @param	string	An optional namespace to search in. If no tag list is specified, all tags in the namespace will be found.
	* @param	array	An optional list of valid tags.
	*/
	public function __construct($namespace = 'vb', $tag_list = array())
	{
		$this->namespace = $namespace;
		$this->tag_list = $tag_list;

		if ($namespace OR $tag_list)
		{
			if ($tag_list)
			{
				foreach ($tag_list AS $tag)
				{
					$tag = strtolower(($namespace ? "$namespace:" : '') . $tag);
					$this->tag_search[] = $tag;
					$this->tag_search[] = "/$tag";
				}
			}
			else
			{
				$this->tag_search[] = strtolower($namespace) . ':';
				$this->tag_search[] = '/' . strtolower($namespace) . ':';
			}

			//$this->states['start'] = 'state_start_special';
		}
	}

	/**
	*	Returns any parse errors.
	*/
	public function get_errors()
	{
		return $this->errors;
	}

	/**
	* Returns then tokens associated with the parser
	*
	* @return	array
	*/
	public function get_tokens()
	{
		return $this->tokens;
	}

	/**
	* Returns true if the parser is at the end of the string to parse.
	*
	* @return	boolean
	*/
	private function is_end()
	{
		return ($this->position >= $this->data_len);
	}

	/**
	* Reads the current character from the string.
	*
	* @return	string
	*/
	private function read_current()
	{
		return $this->data[$this->position];
	}

	/**
	* Returns the next character from the string. Moves the pointer forward.
	*
	* @return	string
	*/
	private function read_next()
	{
		++$this->position;
		return $this->data[$this->position];
	}

	/**
	 * Returns the previous character from the string.  Leaves pointer intact.
	 *
	 * @return	string
	 */
	private function read_previous()
	{
		if ($this->position)
		{
			return $this->data[$this->position-1];
		}

		return null;
	}

	private function read_rest()
	{
		return substr($this->data, $this->position);
	}

	/**
	* Peeks at the next character in the string. Does not move the pointer.
	*
	* @return string
	*/
	private function peek()
	{
		return $this->data[$this->position + 1];
	}

	/**
	* Moves the pointer forward a character.
	*
	* @return void
	*/
	private function step_forward()
	{
		++$this->position;
	}

	/**
	* Moves the pointer back a character.
	*
	* @return void
	*/
	private function step_backwards()
	{
		--$this->position;
	}

	/**
	* Reads until a character from the list is found.
	*
	* @param	string	A list of characters to stop when found. Each byte is treated as a character.
	*
	* @return	string
	*/
	private function read_until_character($character_list)
	{
		$read_until = $this->data_len;

		$strlen = strlen($character_list);
		for ($i = 0; $i < $strlen; ++$i)
		{
			// step through each character in the list and find the first occurance
			// after the current position
			$char_pos = strpos($this->data, $character_list[$i], $this->position);

			// if that occurred earlier than the previous first occurance, only read until there
			if ($char_pos !== false AND $char_pos < $read_until)
			{
				$read_until = $char_pos;
			}
		}

		$text = strval(substr($this->data, $this->position, $read_until - $this->position));
		$this->position = $read_until;

		return $text;
	}

	/**
	* Reads until the exact string is found.
	*
	* @param	string	When this string is encountered, reading is stopped.
	*
	* @return	string
	*/
	private function read_until_string($string, &$not_found = false)
	{
		$string_pos = strpos($this->data, $string, $this->position);

		if ($string_pos === false)
		{
			$not_found = true;
			$string_pos = $this->data_len;
		}

		$text = substr($this->data, $this->position, $string_pos - $this->position);
		$this->position = $string_pos;
		return $text;
	}

	/**
	* Reads until the current character is *not* found in the list.
	*
	* @param	string	A list of characters to read while matched.
	*
	* @return	string
	*/
	private function read_while_character($character_list)
	{
		$length = strspn(substr($this->data, $this->position), $character_list);

		$text = substr($this->data, $this->position, $this->position + $length);
		$this->position += $length;
		return $text;
	}

	/**
	* Reads until the end of the string.
	*
	* @return	string
	*/
	private function read_until_end()
	{
		$text = substr($this->data, $this->position);
		$this->position = $this->data_len;
		return $text;
	}

	/**
	* Skips past any whitespace (spaces, carriage returns, new lines, tabs).
	*/
	private function skip_whitespace()
	{
		$this->read_while_character(" \r\n\t");
	}

	/**
	* Adds to the list of output tokens. Folds contiguous cdata tokens automatically.
	*
	* @param	string	Type of token. Usually 'cdata' or 'tag', though not explicitly limited.
	* @param	mixed	The data of the token. String for 'cdata', an array for 'tag'
	*/
	private function add_token($type, $data)
	{
		if ($type == 'cdata' AND is_array($this->tokens) AND $last = end($this->tokens) AND $last['type'] == 'cdata')
		{
			$key = key($this->tokens);
			$this->tokens["$key"]['data'] .= $data;
		}
		else
		{
			$this->tokens[] = array('type' => $type, 'data' => $data);
		}
	}

	/**
	* Parses the data into tokens.
	*
	* @param	string	The data to parse
	*
	* @return	array	The tokenized data
	*/
	public function parse($data)
	{
		$this->data = $data;
		$this->data_len = strlen($data);
		$this->position = 0;
		$this->tokens = array();

		$lower_data = strtolower($data);

		// optimization -- if we know what tags to search for, let's find them using strpos.
		// note that this may find false positives.
		$this->locations = array();
		if ($this->tag_search)
		{
			foreach ($this->tag_search AS $tag_search)
			{
				// entries in this array are "tag" and "/tag", so we just need the <
				$curpos = 0;
				do
				{
					$pos = strpos($lower_data, '<' . $tag_search, $curpos);
					if ($pos !== false)
					{
						$this->locations[] = $pos;
					}

					// +1 accounts for the <
					$curpos = $pos + strlen($tag_search) + 1;
				}
				while ($pos !== false);
			}
		}

		sort($this->locations, SORT_NUMERIC);
		reset($this->locations);

		// Work through the states. The functions themselves move the pointer around.
		// When false is returned, we're done.
		$state = $this->states['start'];
		do
		{
			$state = $this->{$state}();
		}
		while ($state);

		return $this->tokens;
	}

	/**
	* Special case of the start state. Used when we have done a tag search optimization.
	*
	* @return	string	Next state to move to
	*/
	private function state_start_special()
	{
		// find the first tag location
		if ($this->position == 0)
		{
			$location = current($this->locations);
		}
		else
		{
			$location = next($this->locations);
		}

		if ($location === false)
		{
			// no more locations, so the rest is text
			if (($text = $this->read_until_end()) !== '')
			{
				$this->add_token('cdata', $text);
			}
			return $this->states['end'];
		}
		else if ($location >= $this->position)
		{
			// next location is later in the string, so find the text up until that point
			$text = substr($this->data, $this->position, ($location - $this->position));
			if ($text !== '')
			{
				$this->add_token('cdata', $text);
			}

			// ...move to the tag and start to parse it
			$this->position = $location + 1;
			return $this->states['tag'];
		}
		else if ($location < $this->position)
		{
			// this location is before we were are, so just ignore it
			// and move to the next one
			return $this->states['start'];
		}

	}

	/**
	* Default start state function. Used if we haven't done tag search processing.
	* This assumes no knowledge of the string.
	*
	* @return	string	Next state to move to
	*/
	private function state_start_all()
	{
		// find what would be the start of a tag (a '<' or '{')
		// if we are in a literal tag, make sure we dont use '{' as a delimiter
		$read_until_delim = ($this->states['tag'] == $this->states['literal']?'<':'<{');
		$text = $this->read_until_character($read_until_delim);
		if ($text !== '')
		{
			// ... anything we found is just text
			$this->add_token('cdata', $text);
		}

		// it's possible that we hit the end of the string instead of a < or {, so check for that
		if ($this->is_end())
		{
			return $this->states['end'];
		}
		// else, the next char is < or {

		if ($this->read_current() === '<')
		{
			$this->step_forward();
		}

		while ($this->read_current() == '<')
		{
			// we're seeing <<, so the first should be cdata
			$this->add_token('cdata', '<');
			$this->step_forward();
		}

		return $this->states['tag'];
	}

	private function state_literal()
	{
		$tag_start = $this->position - 1;

		// There was a < but it was eaten already in state_start_all()
		$this->step_backwards();

		$search = '</' . (!empty($this->namespace) ? $this->namespace . ':' : '')  . 'literal>';
		$text = $this->read_until_string($search);


		$this->add_token('cdata', $text);
		$this->parse_close_tag('literal', (!empty($this->namespace) ? $this->namespace : ''), $tag_start);

		$this->states['tag'] = $this->state_tag;
		return $this->states['start'];
	}

	/**
	* End state. Does nothing
	*
	* @return	string	Empty string (stop processing)
	*/
	private function state_end()
	{
		return '';
	}

	/**
	* State to process a tag from the start.
	*
	* @return	string	State to move to
	*/
	private function state_tag()
	{
		$tag_start = $this->position - 1;
		$previous_character = $this->read_current();

		if ($previous_character === '{')
		{ // we can't walk forward in the start state since for curlys we need to know what we're dealing with
				$this->parse_curlys($tag_start);
		}
		else
		{
			$name = $this->read_until_character(">< \r\n\t\${");

			if ($name === '')
			{
				// the tag either began with whitespace, was <>, or the < was the last character in the string
				$this->add_token('cdata', '<');
				return $this->states['start'];
			}

			// match: is closing, namespace, tagname
			if (preg_match('#^(/?)(([a-z0-9_-]+):)?([^/:]+)$#siU', $name, $match))
			{
				$skip_parse = false;
				$namespace = strtolower($match[3]);
				$tag_name = strtolower($match[4]);
				if ($this->namespace)
				{
					// if namespace, only parse if the namespace is correct
					$do_parse = ($namespace == strtolower($this->namespace));
				}
				else
				{
					// no namespace, so include the namespace in the tag name
					$tag_name = ($namespace ? ($namespace . ':') : '') . $tag_name;
					$namespace = '';
					$do_parse = true;
				}

				if ($do_parse)
				{
					// always do this check if we need to parse: make sure the tag name is in the tag list
					$do_parse = (empty($this->tag_list) OR in_array($tag_name, $this->tag_list));
				}

				if (!$skip_parse AND !$do_parse)
				{
					// not a tag we're parsing, just text
					$this->add_token('cdata', '<' . $name);
					return $this->states['start'];
				}

				if ($match[1]) // if we start with a /
				{
					$this->parse_close_tag($tag_name, $namespace, $tag_start);
				}
				else
				{
					$this->parse_tag($tag_name, $namespace, $tag_start);
				}

				if ($tag_name == 'literal')
				{
					$last = end($this->tokens);
					if ($last['type'] == 'tag' AND $last['data']['tag_name'] == 'literal' AND $last['data']['type'] == 'open')
					{
						$this->state_tag = $this->states['tag'];
						$this->states['tag'] = $this->states['literal'];
					}
				}

				return $this->states['start'];
			}
			else
			{
				// didn't match, so this tag name isn't valid anyway
				$this->add_token('cdata', '<' . $name);
			}
		}

		return $this->states['start'];
	}

	/**
	* Parse the attributes of a tag name.
	*
	* @param	string	Name of the tag
	* @param	string	Namespace the tag is in (if there is one)
	* @param	integer	Position of the tag's start
	*/
	private function parse_tag($tag_name, $tag_namespace, $tag_start)
	{
		// ignore any whitespace after the tag start
		$this->skip_whitespace();

		$attributes = array();
		$in_tag = true;
		$self_close = false;

		do
		{
			// attribute name is anything but a delimiter, end of the tag, or whitespace
			$attr_name = $this->read_until_character("=/> \r\n\t");
			$attr_value = false;

			switch ($this->read_current())
			{
				// if we're at a >, we're at the end of a tag
				case '>':
					$this->step_forward();
					$in_tag = false;

					if (empty($attributes) AND substr($tag_name, -1) == '/')
					{
						// no attributes and the last character of the tag name is a /
						// -- the tag is in <tag/> form, which is self-closing
						$tag_name = substr($tag_name, 0, -1);
						$self_close = true;
					}
					break;

				// if we're at a /, then we're probably in a self-closing tag
				// (if the next character is a >)
				case '/':
					$this->skip_whitespace();
					if ($this->read_next() == '>')
					{
						$this->step_forward();
						$in_tag = false;
						$self_close = true;
					}
					break;

				// found whitespace, let's move through the rest and hope we hit =.
				// if we do, treat it as "x=y". Otherwise, there's not value to the attribute
				case ' ':
				case "\r":
				case "\n":
				case "\t":
					$this->skip_whitespace();
					if ($this->read_current() != '=')
					{
						// no attribute value
						break;
					}
					// else fall through

				// oh, found an equals -- this is the standard attribute form
				case '=':
					$delimiter = $this->read_next();
					if ($delimiter == '"' OR $delimiter == "'")
					{
						// delimited by " or ', read until that character comes again
						$this->step_forward();
						$attr_value = $this->read_until_character($delimiter);
						$this->step_forward();
					}
					else
					{
						// no delimiter -- read until the end of the tag or whitespace
						$attr_value = $this->read_until_character("> \r\n\t");
					}

					$this->skip_whitespace();
			}

			if (!empty($attr_name))
			{
				$attributes["$attr_name"] = $attr_value;
			}
		}
		while ($in_tag == true AND !$this->is_end());

		if ($in_tag == true)
		{
			// the tag was never closed, so consider it to be text only
			$this->position = $tag_start;
			$text = $this->read_until_end();
			$this->add_token('cdata', $text);
		}
		else
		{
			// finished the tag, so add it to the tokens
			$message = array(
				'tag_name' => $tag_name,
				'namespace' => $tag_namespace,
				'type' => $self_close ? 'open_close' : 'open',
				'attributes' => $attributes
			);
			$this->add_token('tag', $message);
		}
	}

	/**
	* Parses the close of a tag.
	*
	* @param	string	Tag name
	* @param	string	Tag namespace
	* @param	integer	Position of the tag start
	*/
	private function parse_close_tag($tag_name, $tag_namespace, $tag_start)
	{
		$this->read_until_character('>');

		if ($this->read_current() != '>')
		{
			// this means we're at the end of the string
			$this->position = $tag_start;
			$text = $this->read_until_end();
			$this->add_token('cdata', $text);
		}
		else
		{
			$this->step_forward();

			$message = array(
				'tag_name' => $tag_name,
				'namespace' => $tag_namespace,
				'type' => 'close'
			);
			$this->add_token('tag', $message);
		}
	}

	/**
	* Parse the attributes of a tag name.
	*
	* @param	integer	Position of the tag's start
	*/
	private function parse_curlys($tag_start)
	{
		$position = $this->position;

		$data = $this->read_until_end();
		$this->position = $position;

		$scanner = new vbTemplateLexer($data);
		$parser = new vB_Template_Parser($scanner);

		try
		{
			$result = $parser->run();

			if (!empty($result[0]))
			{
					if ($result[0]['namespace'] == $this->namespace)
					{
						$this->position += $scanner->getCurrentPosition();

						// if we aren't at the end then getCurrentPosition is the start of the next token, so step back.
						if (!$this->is_end() OR $this->read_previous() !== '}') {
							$this->position -= strlen($scanner->value);
						}

						$this->add_token('curly', $result[0]);
						return;
					}
			}
		}
		catch (vB_Exception_Parser $e)
		{
			$this->errors[] = $e->getMessage();
		}
		catch (Exception $e)
		{
			// ksours 2009-06-25 -- parser error on a curly.  If a parse error occurs the curly text will be passed
			// uniterpreted to the output.  However, a number of legitimate constructs -- for example css definitions --
			// will be interpreted as curlys and potentially fail to parse in ways that end up here.  Flagging those
			// as errors is bad.

			// potentially print an error here once a different exception is thrown for syntax errors
		}

		$this->step_forward();
		$this->add_token('cdata', '{');
	}

	/**
	* Fetchs a DOM-class compatible version of the tokens.
	* Note that this only works if the token list is valid XML!
	* It will error otherwise!
	*
	* @return	array	DOM-compatible output
	*/
	public function fetch_dom_compatible()
	{
		$node_list = array(
			0 => array('type' => 'start', 'children' => array())
		);
		$cur_nodeid = 0;

		foreach ($this->tokens AS $token)
		{
			$data = $token['data'];
			if ($token['type'] == 'tag')
			{
				switch ($data['type'])
				{
					case 'open':
						// opening a tag -- add it, and make the current parent become the tag
						$cur_nodeid = $this->_add_dom_node($node_list, 'tag', $cur_nodeid, strtolower($data['tag_name']), $data['attributes'], $data['namespace']);
						break;

					case 'close':
						// closing a tag -- move up a level
						$cur_nodeid = $node_list[$cur_nodeid]['parent'];
						break;

					case 'open_close':
						// same as an open and a close -- add the tag, but don't move the current node
						$this->_add_dom_node($node_list, 'tag', $cur_nodeid, strtolower($data['tag_name']), $data['attributes'], $data['namespace']);
						break;
				}
			}
			else if ($token['type'] == 'curly')
			{
				$this->_add_dom_node($node_list, 'curly', $cur_nodeid, strtolower($data['tag_name']), $data['attributes'], '');
			}
			else if ($token['type'] == 'cdata')
			{
				if (isset($node_list[$cur_nodeid]['children']))
				{
					$last_child = end($node_list[$cur_nodeid]['children']);
				}
				else
				{
					$last_child = false;
				}

				if ($last_child !== false AND $node_list["$last_child"]['type'] == 'text')
				{
					// the previous thing we ran into on this tag was text, so fold into that
					$node_list["$last_child"]['value'] .= $data;
				}
				else
				{
					$this->_add_dom_node($node_list, 'text', $cur_nodeid, $data);
				}
			}
		}

		return $node_list;
	}

	/**
	* Add an node to the DOM node list
	*
	* @param	array	DOM node list
	* @param	string	Type of node to add
	* @param	integer	Unique ID for the parent
	* @param	string	Value of the node (tag name, text, etc)
	* @param	array	Array of attributes (keyed on name)
	* @param	string	Namespace of the tag
	*
	* @return	integer	ID of the added node
	*/
	private function _add_dom_node(&$node_list, $type, $parent_node, $value, $attributes = array(), $namespace = '')
	{
		if (!isset($node_list["$parent_node"]))
		{
			return false; 
//			echo "adding child to non-existent node!";
//			exit;
		}

		$node_list[] = array(
			'type' => $type,
			'value' => $value,
			'parent' => $parent_node
		);

		end($node_list);
		$nodeid = key($node_list);

		if (!empty($namespace))
		{
			$node_list["$nodeid"]['namespace'] = $namespace;
		}
		if (!empty($attributes))
		{
			$node_list["$nodeid"]['attributes'] = $attributes;
		}

		if (!isset($node_list["$parent_node"]['children']))
		{
			$node_list["$parent_node"]['children'] = array();
		}
		$node_list["$parent_node"]['children'][] = $nodeid;

		return $nodeid;
	}
}

// ##########################################################################

/**
* Extendable class to parse templates to be evaled. Supports proprietary tags
* through a modular class system. Uses DOM-parsing.
*/
class vB_TemplateParser
{
	private $tag_parser = null;
	private $output = null;
	public $dom_doc = null;
	private $dom_nodes = null;
	public $text = '';
	public $outputVar = '$final_rendered';

	/**
	* Constructor.
	*
	* @param	string	Text to parse
	*/
	public function __construct($text)
	{
		//An egregious hack.  The parser doesn't work right in some cases where
		//the last character is a "}" (There is a problem with the curly tags
		//where the character after gets trimmed.  We can account for every case
		//but the one where the last character is also a }).  Since this problem
		//doesn't occur if the last character is a space and the resulting compiled
		//template will end in an "extra" space we can add it here and remove it
		//when we are done.
		$this->text = $text . " ";
		$this->output = new vB_PHP_Generator();
		$this->tag_parser = new vB_SpecialtyTagParser('vb');
		$this->tag_parser->parse($this->text);
	}

	/**
	* Validate the name of a variable to ensure its valid PHP
	*
	* @param	string	Name of the variable
	*
	* @return	boolean	True if valid, else false
	*/
	public static function validPHPVariable($name)
	{
		if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*/', $name))
		{
			return true;
		}
		return false;
	}

	/**
	* Validate the text for invalid tag nesting and usage (eg, an elseif after an else).
	*
	* @param	array	An array of errors. An extra return value!
	*
	* @return	boolean	True on success, false on failure
	*/
	public function validate(&$errors)
	{
		$parse_errors = $this->tag_parser->get_errors();
		if (count($parse_errors))
		{
			$errors = $parse_errors;
			return false;
		}

		$errors = array();
		$stack = array();

		foreach ($this->tag_parser->get_tokens() AS $token)
		{
			if ($token['type'] == 'tag')
			{
				$data = $token['data'];
				if ($data['type'] == 'open')
				{
					// push this take onto the top of the stack
					array_push($stack, $data['tag_name']);
				}
				else if ($data['type'] == 'close')
				{
					$last_item = end($stack);
					if ($last_item != $data['tag_name'])
					{
						// top of the stack isn't this tag, so we didn't close the proper tag
						$errors[] = 'invalid_tag_nesting';
					}
					else
					{
						// closed the proper tag on the stack
						array_pop($stack);
					}
				}
				// open_close doesn't need to be handled
			}
		}

		if (count($stack) > 0)
		{
			// forgot to close a tag
			$errors[] = 'unclosed_tag';
		}

		// no errors, so let's do DOM-level validation
		if (!$errors)
		{
			$this->dom_nodes = $this->fetch_dom_compatible();
			$this->dom_doc = new vB_DomDocument($this->dom_nodes);
			foreach (array_keys($this->dom_nodes) AS $key)
			{
				switch ($this->dom_nodes["$key"]['type'])
				{
					case 'tag':
					{
						$node = new vB_DomNode($this->dom_nodes["$key"], $key, $this->dom_doc);
						$node_class = 'vB_TemplateParser_Tag' . preg_replace('#[^a-z0-9_]#i', '', $node->value);
						if (class_exists($node_class, false))
						{
							// validate the tag's usage (eg, the attributes, how it relates to siblings, etc)
							$dom_errors = call_user_func_array(
								array($node_class, 'validate'),
								array($node, $this)
							);
							if ($dom_errors)
							{
								$errors = array_merge($errors, $dom_errors);
							}
						}
						else
						{
							echo "No such class: $node_class<br />";
						}
					}
					break;
					case 'curly':
						$node = new vB_CurlyNode($this->dom_nodes["$key"], $this->dom_doc);
						$this->validate_curly($node, $errors);
					/*
					{
						$node = new vB_CurlyNode($this->dom_nodes["$key"], $this->dom_doc);
						$node_class = 'vB_TemplateParser_Curly' . preg_replace('#[^a-z0-9_]#i', '', $node->value);
						if (class_exists($node_class, false))
						{
							// validate the curly usage (eg, the attributes, how it relates to siblings, etc)
							$dom_errors = call_user_func_array(
								array($node_class, 'validate'),
								array($node, $this)
							);
							if ($dom_errors)
							{
								$errors = array_merge($errors, $dom_errors);
							}
						}
						else
						{
							throw new Exception('Unable to find a class to validate: ' . $node_class);
						}
					}
					*/
					break;
				}
			}
		}

		return (count($errors) == 0);
	}

	/*
		Seperate this out so we can make it recursive.
	*/
	protected function validate_curly($node, &$errors)
	{
		$node_class = 'vB_TemplateParser_Curly' . preg_replace('#[^a-z0-9_]#i', '', $node->value);

		if (class_exists($node_class, false))
		{
			// validate the curly usage (eg, the attributes, how it relates to siblings, etc)
			$dom_errors = call_user_func_array(
				array($node_class, 'validate'),
				array($node, $this)
			);
			if ($dom_errors)
			{
				$errors = array_merge($errors, $dom_errors);
			}

			//handle validation of nested curlies.
			if (is_array($node->attributes))
			{
				foreach($node->attributes as $attribute)
				{
					if($attribute instanceof vB_CurlyNode)
					{
						$this->validate_curly($attribute, $errors);
					}
				}
			}
		}
		else
		{
			throw new Exception('Unable to find a class to validate: ' . $node_class);
		}
	}

	/**
	* Compile the template into a directly eval()'able format.
	*
	* @return	string	Evalable version of the template
	*/
	public function compile()
	{
		if (!$this->dom_doc)
		{
			$this->dom_nodes = $this->fetch_dom_compatible();
			$this->dom_doc = new vB_DomDocument($this->dom_nodes);
		}

		//the other half of the hack started in the constructor.  Strip the bogus
		//space that we added above.
		$output = $this->_parse_nodes($this->dom_doc->childNodes());

		if ($output[-1] == ' ')
		{
			$output = substr($output, 0,  -1);
		}
		return $this->outputVar . ' = \'' . $output . "';";
	}

	/**
	* Parse an array of nodes. Append the output to a variable in order of occurrance.
	*
	* @param	array	An array of DOMNodes
	*
	* @return	string	Outputtable string
	*/
	public function _parse_nodes($nodes)
	{
		$output = '';

		$keys = array_keys($nodes);
		foreach ($keys AS $key)
		{
			$node =& $nodes["$key"];
			$output .= $this->_default_node_handler($node);
		}

		return $output;
	}

	/**
	* Default way to handle a node. If it's text, use that class. If it's a tag,
	* look for a specific tag handler and use it if possible;
	* if not, ignore it and parse the children.
	*
	* @param	object	DOMNode object
	*
	* @return	string	Outputtable string
	*/
	public function _default_node_handler(&$node)
	{
		if ($node->type == 'text')
		{
			return vB_TemplateParser_Text::compile($node, $this);
		}
		else if ($node->type == 'tag')
		{
			$node_class = 'vB_TemplateParser_Tag' . preg_replace('#[^a-z0-9_]#i', '', $node->value);
			if (class_exists($node_class, false))
			{
				return "' . " . call_user_func_array(
					array($node_class, 'compile'),
					array($node, $this)
				) . " . '";
			}
			else
			{
				return $this->_parse_nodes($node->childNodes());
			}
		}
		else if ($node->type == 'curly')
		{
			$node_class = 'vB_TemplateParser_Curly' . preg_replace('#[^a-z0-9_]#i', '', $node->value);
			if (class_exists($node_class, false))
			{
				return "' . " . call_user_func_array(
					array($node_class, 'compile'),
					array($node, $this)
				) . " . '";
			}
			else
			{
				return $this->_parse_nodes($node->childNodes());
			}
		}
	}

	/**
	* Default way to escape a string. Suitable for evaling.
	*
	* @param	string	String to escape
	*
	* @return	string	Escaped string
	*/
	public function _escape_string($string)
	{
		return str_replace('\"', '"', addslashes($string));
	}

	/**
	* Fetch the DOM-compatible version of the tokenized version of this template.
	* Note that the tokens must be well-formed (proper nesting, all closed).
	* Make sure it passes validate() first!
	*
	* @return	array	Array of nodes in a DOM-compatible format
	*/
	public function fetch_dom_compatible()
	{
		// note: this function assumes XML-valid nesting! Make sure it passes validate() first.
		return $this->tag_parser->fetch_dom_compatible();
	}
}

// ##########################################################################

/**
* Text handler for the vB_TemplateParser. Should be called statically.
*/
class vB_TemplateParser_Text
{
	/**
	* Compiles a text node in the vB_TemplateParser format (evalable).
	*
	* @param	object	DOM Node of type text
	* @param	object	vB_TemplateParser object
	*
	* @return	string	Evalable text
	*/
	public static function compile(vB_DomNode $main_node, vB_TemplateParser $parser)
	{
		return $parser->_escape_string($main_node->value);
	}
}

require_once(DIR . '/includes/vb_template.php');

class vB_Template_Parser
{
	private $scanner;

	public function __construct(vbTemplateLexer $scanner)
	{
		$this->scanner = $scanner;
	}

	public function dumpTokens()
	{
		while ($this->scanner->yylex() !== false)
		{
			var_dump($this->scanner->token);
		}
	}

	public function run($parse_all = false)
	{
		$statements = array();
		// left hand side is required for when we recurse
		while ($this->scanner->token == vbTemplateParser::CURLY_START OR $this->scanner->yylex() !== false)
		{
			if ($statement = $this->statement())
			{
				$statements[] = $statement;
				if (!$parse_all) {
					break;
				}
			}
		}

		return $statements;
	}

	private function statement()
	{
		if ($this->scanner->token == vbTemplateParser::CURLY_START AND $this->scanner->yylex())
		{
			$result = $this->expression();
			if ($this->scanner->token == vbTemplateParser::CURLY_END)
			{
				$this->scanner->yylex();
				return $result;
			}
		}
		return false;
	}

	private function expression()
	{
		if ($this->scanner->token === vbTemplateParser::SIMPLE_VAR)
		{ // hack because we allow someone not to specify {var }
			$token = array(
				'tag_name' => 'var',
				'type' => 'curly',
			);
		}
		else
		{

			$token = array(
				'tag_name' => $this->scanner->currentValue,
				'type' => 'curly',
			);
			$this->scanner->yylex();

			if ($this->scanner->token === vbTemplateParser::TOKEN AND $this->scanner->currentValue == ':')
			{
				$this->scanner->yylex();
				$token['namespace'] = $token['tag_name'];
				$token['tag_name'] = $this->scanner->currentValue;
				$this->scanner->yylex();
			}
		}

		if ($this->scanner->token != vbTemplateParser::CURLY_END)
		{
			$token['attributes'] = $this->variables();
		}

		return $token;
	}

	private function variables()
	{
		$variables = array();
		while ($this->scanner->token != vbTemplateParser::CURLY_END)
		{
			$variables[] = $this->variable();
			// TODO: Fix this, but requires changing simple_var() to not read as far as it should
			if ($this->scanner->token == vbTemplateParser::TOKEN AND $this->scanner->currentValue == ',') {
				$this->scanner->yylex();
			}
		}

		return $variables;
	}

	private function variable()
	{
		if ($this->scanner->token == vbTemplateParser::CURLY_START)
		{
			return $this->statement();
		}
		else if ($this->scanner->token == vbTemplateParser::STRING_VALUE)
		{
			$value = $this->scanner->currentValue;

			// throw exception if we reach end of input before closing curly brace
			if ($this->scanner->yylex() === false AND $this->scanner->token != vbTemplateParser::CURLY_END)
			{
				throw new vB_Exception_Parser('unclosed_curlybrace');
			}
			return $value;
		}
		else
		{
			return $this->simple_var();
		}
	}

	private function simple_var()
	{
		$token = '';

		while (
			($this->scanner->token == vbTemplateParser::TOKEN AND $this->scanner->currentValue != ',')
				OR
			($this->scanner->token != vbTemplateParser::CURLY_END AND $this->scanner->token != vbTemplateParser::CURLY_START AND $this->scanner->token != vbTemplateParser::TOKEN)
		)
		{
			$token .= $this->scanner->currentValue;
			if ($this->scanner->yylex() === false)
			{
				throw new vB_Exception_Parser('unclosed_curlybrace');
			}
		}

		if (stripos($token, 'null') === 0)
		{
			$token = NULL;
		}

		return $token;
	}
}
