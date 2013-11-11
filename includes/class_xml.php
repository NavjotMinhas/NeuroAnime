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

error_reporting(E_ALL & ~E_NOTICE);

// Attempt to load XML extension if we don't have the XML functions
// already loaded.
if (!function_exists('xml_set_element_handler'))
{
	$extension_dir = ini_get('extension_dir');
	if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN')
	{
		$extension_file = 'php_xml.dll';
	}
	else
	{
		$extension_file = 'xml.so';
	}
	if ($extension_dir AND file_exists($extension_dir . '/' . $extension_file))
	{
		ini_set('display_errors', true);
		dl($extension_file);
	}
}

if (!function_exists('ini_size_to_bytes') OR (($current_memory_limit = ini_size_to_bytes(@ini_get('memory_limit'))) < 128 * 1024 * 1024 AND $current_memory_limit > 0))
{
	@ini_set('memory_limit', 128 * 1024 * 1024);
}

/**
* vBulletin XML Parsing Object
*
* This class allows the parsing of an XML document to an array
*
* @package 		vBulletin
* @author		Scott MacVicar
* @version		$Revision: 41161 $
* @date 		$Date: 2010-12-20 12:32:25 -0800 (Mon, 20 Dec 2010) $
* @copyright 	http://www.vbulletin.com/license.html
*
*/
class vB_XML_Parser
{
	/**
	* Internal PHP XML parser
	*
	* @var	resource
	*/
	var $xml_parser;

	/**
	* Error number (0 for no error)
	*
	* @var	integer
	*/
	var $error_no = 0;

	/**
	* The actual XML data being processed
	*
	* @var	integer
	*/
	var $xmldata = '';

	/**
	* The final, outputtable data
	*
	* @var	array
	*/
	var $parseddata = array();

	/**
	* Intermediate stack value used while parsing.
	*
	* @var	array
	*/
	var $stack = array();

	/**
	* Current CData being parsed
	*
	* @var	string
	*/
	var $cdata = '';

	/**
	* Number of tags open currently
	*
	* @var	integer
	*/
	var $tag_count = 0;

	/**
	* Kludge to include the top level element since this parser was written to not return it and now all of the XML functions assume it to not be there
	*
	* @var	boolean
	*/
	var $include_first_tag = false;

	/**
	* Error code from XML object prior to releases of resources. This needs to be done to avoid a segfault in PHP 4. See Bug#24425
	*
	* @var integer
	*/
	var $error_code = 0;

	/**
	* Error line number from XML object prior to releases of resources. This needs to be done to avoid a segfault in PHP 4. See Bug#24425
	*
	* @var integer
	*/
	var $error_line = 0;

	/**
	 * Whether to behave in legacy mode for compatibility.
	 * @TODO: Update dependencies and remove legacy support
	 *
	 * @var bool
	 */
	var $legacy_mode = true;

	/**
	 * The encoding of the input xml.
	 * This can be overridden by the client code.
	 * @see vB_XML_Parser::set_encoding()
	 *
	 * @var string
	 */
	var $encoding;

	/**
	 * Specified target encoding.
	 * If this is not set then the target encoding will be resolved from language settings.
	 * @see vB_XML_Parser::set_target_encoding()
	 *
	 * @var string
	 */
	var $target_encoding;

	/**
	 * Specifies whether to NCR encode multibyte.
	 * By default this is disabled and out of range characters will be displayed incorrectly.
	 *
	 * @var bool
	 */
	var $ncr_encode;

	/**
	 * Whether to escape html in cdata.
	 *
	 * @var bool
	 */
	var $escape_html;

	/**
	* Constructor
	*
	* @param	mixed	XML data or boolean false
	* @param	string	Path to XML file to be parsed
	*/
	function vB_XML_Parser($xml, $path = '')
	{
		if ($xml !== false)
		{
			$this->xmldata = $xml;
		}
		else
		{
			if (empty($path))
			{
				$this->error_no = 1;
			}
			else if (!($this->xmldata = @file_get_contents($path)))
			{
				$this->error_no = 2;
			}
		}
	}

	/**
	* Parses XML document into an array
	*
	* @param	string	Encoding of the input XML
	* @param	bool	Empty the XML data string after parsing
	*
	* @return	mixed	array or false on error
	*/
	function &parse($encoding = 'ISO-8859-1', $emptydata = true)
	{
		// Set our own encoding to that passed
		$this->encoding = $encoding;

		// Ensure the target encoding is set
		if (!$this->legacy_mode)
		{
			$this->resolve_target_encoding();
		}

		if (empty($this->xmldata) OR $this->error_no > 0)
		{
			$this->error_code = XML_ERROR_NO_ELEMENTS + (PHP_VERSION > '5.2.8' ? 0 : 1);
			return false;
		}

		if (!($this->xml_parser = xml_parser_create($encoding)))
		{
			return false;
		}

		xml_parser_set_option($this->xml_parser, XML_OPTION_SKIP_WHITE, 0);
		xml_parser_set_option($this->xml_parser, XML_OPTION_CASE_FOLDING, 0);
		xml_set_character_data_handler($this->xml_parser, array(&$this, 'handle_cdata'));
		xml_set_element_handler($this->xml_parser, array(&$this, 'handle_element_start'), array(&$this, 'handle_element_end'));

		xml_parse($this->xml_parser, $this->xmldata, true);
		$err = xml_get_error_code($this->xml_parser);

		if ($emptydata)
		{
			$this->xmldata = '';
			$this->stack = array();
			$this->cdata = '';
		}

		if ($err)
		{
			$this->error_code = @xml_get_error_code($this->xml_parser);
			$this->error_line = @xml_get_current_line_number($this->xml_parser);
			xml_parser_free($this->xml_parser);
			return false;
		}

		xml_parser_free($this->xml_parser);

		return $this->parseddata;
	}

	/**
	* Handle encoding issues as well as parsing the XML into an array
	*
	* @return	boolean	Success
	*/
	function parse_xml()
	{
		if ($this->legacy_mode)
		{
			return $this->legacy_parse_xml();
		}

		if (preg_match('#(<?xml.*encoding=[\'"])(.*?)([\'"].*?>)#m', $this->xmldata, $match))
		{
			$encoding = strtoupper($match[2]);

			if ($encoding != 'UTF-8')
			{
				// XML will always be UTF-8 at parse time
				$this->xmldata = str_replace($match[0], "$match[1]UTF-8$match[3]", $this->xmldata);
			}

			if (!$this->encoding)
			{
				$this->encoding = $encoding;
			}
		}
		else
		{
			if (!$this->encoding)
			{
				$this->encoding = 'UTF-8';
			}

			if (strpos($this->xmldata, '<?xml') === false)
			{
				// no xml tag, force one
				$this->xmldata = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $this->xmldata;
			}
			else
			{
				// xml tag doesn't have an encoding, which is bad
				$this->xmldata = preg_replace(
					'#(<?xml.*)(\?>)#',
					'\\1 encoding="UTF-8" \\2',
					$this->xmldata
				);
			}
		}

		// Ensure the XML is UTF-8
		if ('UTF-8' !== $this->encoding)
		{
			$this->xmldata = to_utf8($this->xmldata, $this->encoding);
		}

		// Parse the XML as UTF-8
		if (!$this->parse('UTF-8'))
		{
			return false;
		}

		return true;
	}

	/**
	* Handle encoding issues as well as parsing the XML into an array
	*
	* @return	boolean	Success
	*/
	function legacy_parse_xml()
	{
		// in here we should do conversion from the input to the output.
		if (preg_match('#(<?xml.*encoding=[\'"])(.*?)([\'"].*?>)#m', $this->xmldata, $match))
		{
			$in_encoding = strtoupper($match[2]);
			if ($in_encoding == 'ISO-8859-1')
			{
				// browsers treat the encodings like this, so we need iconv to do so as well
				$in_encoding = 'WINDOWS-1252';
			}

			if (($in_encoding != 'UTF-8' OR strtoupper(vB_Template_Runtime::fetchStyleVar('charset')) != 'UTF-8'))
			{
				// this is necessary in PHP5 when try to output a non-support encoding
				$this->xmldata = str_replace($match[0], "$match[1]ISO-8859-1$match[3]", $this->xmldata);
			}
		}
		else
		{
			$in_encoding = 'UTF-8';

			if (strpos($this->xmldata, '<?xml') === false)
			{
				// this is necessary if there's no XML tag, as PHP5 doesn't know what character set it's in,
				// so special characters die
				$this->xmldata = '<?xml version="1.0" encoding="ISO-8859-1"?>' . "\n" . $this->xmldata;
			}
			else
			{
				// xml tag doesn't have an encoding, which is bad
				$this->xmldata = preg_replace(
					'#(<?xml.*)(\?>)#',
					'\\1 encoding="ISO-8859-1" \\2',
					$this->xmldata
				);
			}

			$in_encoding = 'ISO-8859-1';
		}

		$orig_string = $this->xmldata;

		// this is the current user if its the admincp or the guest session for cron
		// should we stick with this or query the DB for the default language?
		$target_encoding = (strtolower(vB_Template_Runtime::fetchStyleVar('charset')) == 'iso-8859-1' ? 'WINDOWS-1252' : vB_Template_Runtime::fetchStyleVar('charset'));
		$xml_encoding = (($in_encoding != 'UTF-8' OR strtoupper(vB_Template_Runtime::fetchStyleVar('charset')) != 'UTF-8') ? 'ISO-8859-1' : 'UTF-8');
		$iconv_passed = false;

		if (strtoupper($in_encoding) !== strtoupper($target_encoding))
		{
			// now we need to deal with those unknown character sets, meep!
			if (function_exists('iconv') AND $encoded_data = iconv($in_encoding, $target_encoding . '//TRANSLIT', $this->xmldata))
			{
				$iconv_passed = true;
				$this->xmldata =& $encoded_data;
			}

			if (!$iconv_passed AND function_exists('mb_convert_encoding') AND $encoded_data = @mb_convert_encoding($this->xmldata, $target_encoding, $in_encoding))
			{
				$this->xmldata =& $encoded_data;
			}
		}

		if ($this->parse($xml_encoding))
		{
			return true;
		}
		else if ($iconv_passed AND $this->xmldata = iconv($in_encoding, $target_encoding . '//IGNORE', $orig_string))
		{
			// this is probably happening because iconv is chopping off the string for some reason.
			// However, when //TRANSLIT fails, //IGNORE still sometimes works, so try that.
			if ($this->parse($xml_encoding))
			{
				return true;
			}
			else
			{
				return false;
			}
		}
		else
		{
			return false;
		}
	}


	/**
	* XML parser callback. Handles CDATA values.
	*
	* @param	resource	Parser that called this
	* @param	string		The CDATA
	*/
	function handle_cdata(&$parser, $data)
	{
		$this->cdata .= $data;
	}

	/**
	* XML parser callback. Handles tag opens.
	*
	* @param	resource	Parser that called this
	* @param	string		The name of the tag opened
	* @param	array		The tag's attributes
	*/
	function handle_element_start(&$parser, $name, $attribs)
	{
		$this->cdata = '';

		foreach ($attribs AS $key => $val)
		{
			if (preg_match('#&[a-z]+;#i', $val))
			{
				$attribs["$key"] = unhtmlspecialchars($val);
			}
		}

		array_unshift($this->stack, array('name' => $name, 'attribs' => $attribs, 'tag_count' => ++$this->tag_count));
	}

	/**
	* XML parser callback. Handles tag closes.
	*
	* @param	resource	Parser that called this
	* @param	string		The name of the tag closed
	*/
	function handle_element_end(&$parser, $name)
	{
		$tag = array_shift($this->stack);
		if ($tag['name'] != $name)
		{
			// there's no reason this should actually happen -- it'd mean invalid xml
			return;
		}

		$output = $tag['attribs'];

		if (trim($this->cdata) !== '' OR $tag['tag_count'] == $this->tag_count)
		{
			if (sizeof($output) == 0)
			{
				$output = $this->unescape_cdata($this->cdata);
			}
			else
			{
				$this->add_node($output, 'value', $this->unescape_cdata($this->cdata));
			}
		}

		if (isset($this->stack[0]))
		{
			$this->add_node($this->stack[0]['attribs'], $name, $output);
		}
		else
		{
			// popped off the first element
			// this should complete parsing
			if ($this->include_first_tag)
			{
				$this->parseddata = array($name => $output);
			}
			else
			{
				$this->parseddata = $output;
			}
		}


		$this->cdata = '';
	}

	/**
	* Returns parser error string
	*
	* @return	mixed error message
	*/
	function error_string()
	{
		if ($errorstring = @xml_error_string($this->error_code()))
		{
			return $errorstring;
		}
		else
		{
			return 'unknown';
		}
	}

	/**
	* Returns parser error line number
	*
	* @return	int error line number
	*/
	function error_line()
	{
		if ($this->error_line)
		{
				return $this->error_line;
		}
		else
		{
			return 0;
		}
	}

	/**
	* Returns parser error code
	*
	* @return	int error line code
	*/
	function error_code()
	{
		if ($this->error_code)
		{
			return $this->error_code;
		}
		else
		{
			return 0;
		}
	}

	/**
	* Adds node with appropriate logic, multiple values get added to array where unique are their own entry
	*
	* @param	array	Reference to array node has to be added to
	* @param	string	Name of node
	* @param	string	Value of node
	*
	*/
	function add_node(&$children, $name, $value)
	{
		if (!is_array($children) OR !in_array($name, array_keys($children)))
		{ // not an array or its not currently set
			$children[$name] = $value;
		}
		else if (is_array($children[$name]) AND isset($children[$name][0]))
		{ // its the same tag and is already an array
			$children[$name][] = $value;
		}
		else
		{  // its the same tag but its not been made an array yet
			$children[$name] = array($children[$name]);
			$children[$name][] = $value;
		}
	}

	/**
	* Adds node with appropriate logic, multiple values get added to array where unique are their own entry
	*
	* @param	string	XML to have any of our custom CDATAs to be made into CDATA
	*
	*/
	function unescape_cdata($xml)
	{
		static $find, $replace;

		if (!is_array($find))
		{
			$find = array('«![CDATA[', ']]»', "\r\n", "\n");
			$replace = array('<![CDATA[', ']]>', "\n", "\r\n");
		}

		if (!$this->legacy_mode AND ($this->encoding != $this->target_encoding))
		{
			$xml = $this->encode($xml);
		}

		return str_replace($find, $replace, $xml);
	}


	/**
	 * Overrides the character encoding for the input XML.
	 *
	 * @param	string	charset
	 */
	function set_encoding($encoding)
	{
		$this->encoding = $encoding;
	}


	/**
	 * Sets the target charset encoding for the parsed XML.
	 *
	 * @param	string	Target charset
	 * @param	bool	Whether to ncr encode non ASCII
	 * @param	bool	Whether to escape HTML
	 */
	function set_target_encoding($target_encoding, $ncr_encode = false, $escape_html = false)
	{
		$this->target_encoding = $target_encoding;
		$this->ncr_encode = $ncr_encode;
		$this->escape_html = $escape_html;
	}


	/**
	 * Resolves the target encoding of the output.
	 */
	function resolve_target_encoding()
	{
		if (!$this->target_encoding)
		{
			$this->target_encoding = vB_Template_Runtime::fetchStyleVar('charset');
		}

		$this->target_encoding = strtoupper($this->target_encoding);

		// Prefer WINDOWS-1252 over ISO-8859-1
		if ('ISO-8859-1' == $this->target_encoding)
		{
			$this->target_encoding = 'WINDOWS-1252';
		}
	}

	/**
	 * Encodes data to the target encoding.
	 *
	 * @param	string	UTF-8 string to reencode
	 * @return	string	The reencoded string
	 */
	function encode($data)
	{
		if ($this->encoding == $this->target_encoding)
		{
			return $data;
		}

		// Escape HTML
		if ($this->escape_html)
		{
			$data = @htmlspecialchars($data, ENT_COMPAT, $this->encoding);
		}

		// NCR encode
		if ($this->ncr_encode)
		{
			$data = ncrencode($data, true);
		}

		// Convert to the target charset
		return to_charset($data, $this->encoding, $this->target_encoding);
	}


	/**
	 * Disables legacy mode.
	 * With legacy mode disabled character encoding is handled correctly however
	 * legacy dependencies will break.
	 */
	function disable_legacy_mode($disable = true)
	{
		$this->legacy_mode = !$disable;
	}
}

// #############################################################################
// xml builder

class vB_XML_Builder
{
	var $registry = null;
	var $charset = 'windows-1252';
	var $content_type = 'text/xml';
	var $open_tags = array();
	var $tabs = "";

	function vB_XML_Builder(&$registry, $content_type = null, $charset = null)
	{
		if (is_object($registry))
		{
			$this->registry =& $registry;
		}
		else
		{
			trigger_error("vB_XML_Builder::Registry object is not an object", E_USER_ERROR);
		}

		if ($content_type)
		{
			$this->content_type = $content_type;
		}

		if ($charset == null)
		{
			$charset = $this->registry->userinfo['lang_charset'] ? $this->registry->userinfo['lang_charset'] : vB_Template_Runtime::fetchStyleVar('charset');
		}

		$this->charset = (strtolower($charset) == 'iso-8859-1') ? 'windows-1252' : $charset;
	}

	/**
	* Fetches the content type header with $this->content_type
	*/
	function fetch_content_type_header()
	{
		return 'Content-Type: ' . $this->content_type . ($this->charset == '' ? '' : '; charset=' . $this->charset);
	}

	/**
	* Fetches the content length header
	*/
	function fetch_content_length_header()
	{
		return 'Content-Length: ' . $this->fetch_xml_content_length();
	}

	/**
	* Sends the content type header with $this->content_type
	*/
	function send_content_type_header()
	{
		@header('Content-Type: ' . $this->content_type . ($this->charset == '' ? '' : '; charset=' . $this->charset));
	}

	/**
	* Sends the content length header
	*/
	function send_content_length_header()
	{
		@header('Content-Length: ' . $this->fetch_xml_content_length());
	}

	/**
	* Returns the <?xml tag complete with $this->charset character set defined
	*
	* @return	string	<?xml tag
	*/
	function fetch_xml_tag()
	{
		return '<?xml version="1.0" encoding="' . $this->charset . '"?>' . "\n";
	}

	/**
	*
	* @return	integer	Length of document
	*/
	function fetch_xml_content_length()
	{
		return strlen($this->doc) + strlen($this->fetch_xml_tag());
	}

	function add_group($tag, $attr = array())
	{
		$this->open_tags[] = $tag;
		$this->doc .= $this->tabs . $this->build_tag($tag, $attr) . "\n";
		$this->tabs .= "\t";
	}

	function close_group()
	{
		$tag = array_pop($this->open_tags);
		$this->tabs = substr($this->tabs, 0, -1);
		$this->doc .= $this->tabs . "</$tag>\n";
	}

	function add_tag($tag, $content = '', $attr = array(), $cdata = false, $htmlspecialchars = false)
	{
		$this->data[$tag] = $content;
		$this->doc .= $this->tabs . $this->build_tag($tag, $attr, ($content === ''));
		if ($content !== '')
		{
			if ($htmlspecialchars)
			{
				$this->doc .= htmlspecialchars_uni($content);
			}
			else if ($cdata OR preg_match('/[\<\>\&\'\"\[\]]/', $content))
			{
				$this->doc .= '<![CDATA[' . $this->escape_cdata($content) . ']]>';
			}
			else
			{
				$this->doc .= $content;
			}
			$this->doc .= "</$tag>\n";
		}
	}

	function build_tag($tag, $attr, $closing = false)
	{
		$tmp = "<$tag";
		if (!empty($attr))
		{
			foreach ($attr AS $attr_name => $attr_key)
			{
				if (strpos($attr_key, '"') !== false)
				{
					$attr_key = htmlspecialchars_uni($attr_key);
				}
				$tmp .= " $attr_name=\"$attr_key\"";
			}
		}
		$tmp .= ($closing ? " />\n" : '>');
		return $tmp;
	}

	function escape_cdata($xml)
	{
		// strip invalid characters in XML 1.0:  00-08, 11-12 and 14-31
		// I did not find any character sets which use these characters.
		$xml = preg_replace('#[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]#', '', $xml);

		return str_replace(array('<![CDATA[', ']]>'), array('«![CDATA[', ']]»'), $xml);
	}

	function output()
	{
		if (!empty($this->open_tags))
		{
			trigger_error("There are still open tags within the document", E_USER_ERROR);
			return false;
		}

		return $this->doc;
	}

	/**
	* Prints out the queued XML and then exits.
	*
	* @param	boolean	If not using shut down functions, whether to do a full shutdown (session updates, etc) or to just close the DB
	*/
	function print_xml($full_shutdown = false)
	{
		if (class_exists('vBulletinHook', false))
		{
			($hook = vBulletinHook::fetch_hook('xml_print_output')) ? eval($hook) : false;
		}

		if (defined('VB_API') AND VB_API === true)
		{
			print_output($this->data);
		}

		//run any registered shutdown functions
		$GLOBALS['vbulletin']->shutdown->shutdown();
		if (defined('NOSHUTDOWNFUNC'))
		{
			if ($full_shutdown)
			{
				exec_shut_down();
			}
			else
			{
				$this->registry->db->close();
			}
		}

		$this->send_content_type_header();

		if ($this->fetch_send_content_length_header())
		{
			// this line is causing problems with mod_gzip/deflate, but is needed for some IIS setups
			$this->send_content_length_header();
		}

		echo $this->fetch_xml();
		exit;
	}

	/**
	* Prints XML header, use this if you need to output data that can't be easily queued. It won't work properly if content-length is required
	*
	* @param	boolean	If not using shut down functions, whether to do a full shutdown (session updates, etc) or to just close the DB
	*/
	function print_xml_header()
	{
		// Can't use this is we need to send a content length header as we don't know how much bogus data is going to be sent
		if ($this->fetch_send_content_length_header())
		{
			if (!defined('SUPPRESS_KEEPALIVE_ECHO'))
			{
				define('SUPPRESS_KEEPALIVE_ECHO', true);
			}
			return false;
		}

		if (class_exists('vBulletinHook', false))
		{
			($hook = vBulletinHook::fetch_hook('xml_print_output')) ? eval($hook) : false;
		}
		
		$this->send_content_type_header();
		echo $this->fetch_xml_tag();
	}

	/**
	* Prints out the queued XML and then exits. Use in combination with print_xml_header();
	*
	* @param	boolean	If not using shut down functions, whether to do a full shutdown (session updates, etc) or to just close the DB
	*/
	function print_xml_end($full_shutdown = false)
	{
		// Can't use this is we need to send a content length header as we don't know how much bogus data is going to be sent
		if ($this->fetch_send_content_length_header())
		{
			return $this->print_xml();
		}

		//run any registered shutdown functions
		$GLOBALS['vbulletin']->shutdown->shutdown();
		if (defined('NOSHUTDOWNFUNC'))
		{
			if ($full_shutdown)
			{
				exec_shut_down();
			}
			else
			{
				$this->registry->db->close();
			}
		}		

		echo $this->output();
	}

	/**
	 * Determine if we send the content length header
	 *
	 * @return boolean
	 */
	function fetch_send_content_length_header()	
	{
		return (strpos($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS') !== false);
	}

	/**
	 * Fetches the queued XML
	 *
	 * @return string
	 */
	function fetch_xml()
	{
		return $this->fetch_xml_tag() . $this->output();
	}
}

// #############################################################################

class vB_AJAX_XML_Builder extends vB_XML_Builder
{
	function escape_cdata($xml)
	{
		$xml = preg_replace('#[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]#', '', $xml);

		return str_replace(array('<![CDATA[', ']]>'), array('<=!=[=C=D=A=T=A=[', ']=]=>'), $xml);
	}
}

// #############################################################################
// legacy stuff

class XMLparser extends vB_XML_Parser
{
}
class XMLexporter extends vB_XML_Builder
{
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 41161 $
|| ####################################################################
\*======================================================================*/
