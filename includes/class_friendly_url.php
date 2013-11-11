<?php if (!class_exists('vB_Database')) exit;
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

/**#@+
* Friendly URL types
* @TODO: move to constants in vB_Friendly_Url
*/
define('FRIENDLY_URL_OFF', 0);
define('FRIENDLY_URL_BASIC',	1);
define('FRIENDLY_URL_ADVANCED', 2);
define('FRIENDLY_URL_REWRITE',  3);

define('SEO_NOSESSION', 1);
define('SEO_JS', 2);
define('SEO_FULLURL', 4);
define('SEO_FULLURL_FORCEBBURL', 8);
/**#@-*/

/**
 * Base class for friendly URLs.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 32878 $
 * @since $Date: 2009-10-28 18:38:49 +0000 (Wed, 28 Oct 2009) $
 * @copyright vBulletin Solutions Inc.
 */
abstract class vB_Friendly_Url
{
	/**
	 * Regex to clean fragments.
	 *
	 * @var string
	 */
	const CLEAN_URL_REGEX = '*([\s$+,/:=\?@"\'<>%{}|\\^~[\]`\r\n\t\x00-\x1f\x7f]|(?(?<!&)#|#(?![0-9]+;))|&(?!#[0-9]+;)|(?<!&#\d|&#\d{2}|&#\d{3}|&#\d{4}|&#\d{5});)*s';

	/**
	 * Unicode URL options
	 *
	 * @var int
	 */
	const UNI_IGNORE = 0;
	const UNI_CONVERT = 1;
	const UNI_STRIP = 2;

	/**
	 * Canonical URL options
	 *
	 * @var int
	 */
	const CANON_OFF = 0;
	const CANON_STANDARD = 1;
	const CANON_STRICT = 2;

	/**
	 * The current resource id.
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * The current title of the resource.
	 *
	 * @var string
	 */
	protected $title;

	/**
	 * The URL parsed from the request.
	 *
	 * @var string
	 */
	protected static $request_url;

	/**
	 * The URI parsed from the request.
	 *
	 * @var string
	 */
	protected static $request_uri;

	/**
	 * Whether we have parsed the request uri.
	 *
	 * @var bool
	 */
	protected static $parsed_request;

	/**
	 * The resolved uri for the friendly url.
	 *
	 * @var string
	 */
	protected $uri;

	/**
	 * The request variable for the resource id.
	 *
	 * @var string
	 */
	protected $idvar;

	/**
	 * Page info.
	 * Additional properties to either build into the link path or always add to the
	 * query string.
	 *
	 * @var array
	 */
	protected $pageinfo = array();

	/**
	 * Link info index of the resource id.
	 *
	 * @var string
	 */
	protected $idkey = 'id';

	/**
	 * Link info index of the title.
	 *
	 * @var string
	 */
	protected $titlekey = 'title';

	/**
	 * Array of pageinfo vars to ignore when building the uri.
	 *
	 * @var array string
	 */
	protected $ignorelist = array();

	/**
	 * The name of the script that the URL links to.
	 *
	 * @var string
	 */
	protected $script;

	protected $script_base_option_name;

	/**
	 * The rewrite segment to identify this friendly url type.
	 *
	 * @var string
	 */
	protected $rewrite_segment;

	/**
	 * Reference to the $vbulletin registry.
	 *
	 * @var vB_Registry
	 */
	protected $registry;

	/**
	 * URL options bitfield.
	 * Whether to include the session hash or whether the link is for js.
	 * @see SEO_NOSESSION, SEO_JS, __construct(), get_qs_arguments()
	 *
	 * @var int
	 */
	protected $urloptions = 0;

	/**
	 * Whether to use the friendly uri in POST requests.
	 *
	 * @var bool
	 */
	protected $parse_post;

	/**
	 * Whether to always set the route, even if friendly urls are off.
	 *
	 * @var bool
	 */
	protected $always_route;


	/**
	 * Constructor.
	 * Note: The factory method must be used to create a vB_Friendly_Url.
	 * @see vB_Friendly_Url::fetchLibrary()
	 *
	 * @param vB_Registry $registry				- Reference to the vBulletin registry
	 * @param array $linkinfo					- Info about the link, the id, title etc
	 * @param array $pageinfo					- Additional info about the required request; pagenumber and query string
	 * @param string $idkey						- Override the key in $linkinfo for the resource id
	 * @param string $titlekey					- Override the key in $linkinfo for the resource title
	 * @param int $urloptions					- Bitfield of environment options SEO_NOSESSION, SEO_JS, etc
	 */
	protected function __construct (
		&$registry, 
		$linkinfo = null, 
		$pageinfo = null, 
		$idkey = false, 
		$titlekey = false, 
		$urloptions = 0
	)
	{
		$this->idkey = $idkey ? $idkey : $this->idkey;
		$this->titlekey = $titlekey ? $titlekey : $this->titlekey;
		$this->registry =& $registry;
		$this->urloptions = $urloptions;


		// Ensure linkinfo and pageinfo are arrays
		$linkinfo = $linkinfo ? (array)$linkinfo : null;
		$pageinfo = $pageinfo ? (array)$pageinfo : null;

		// Resolve the rewrite segment that identifies the script
		if (!$this->rewrite_segment)
		{
			if (defined('VB_ROUTER_SEGMENT'))
			{
				$this->rewrite_segment = VB_ROUTER_SEGMENT;
			}
			else
			{
				$pathinfo = pathinfo(SCRIPT);
				$this->rewrite_segment = $pathinfo['filename'];
			}
		}

		// If $linkinfo isn't set then parse it from the request
		$linkinfo ? $this->set_linkinfo($linkinfo) : $this->consume_request_uri();

		// Set the pageinfo
		if ($pageinfo)
		{
			$this->set_pageinfo($pageinfo);
		}
	}


	/**
	 * Factory method.
	 *
	 * @param vB_Registry $registry				- Reference to the vBulletin registry
	 * @param string $link						- The type of link to create and additional link options
	 * @param array $linkinfo					- Info about the link, the id, title etc
	 * @param array $pageinfo					- Additional info about the required request; pagenumber and query string
	 * @param string $idkey						- Override the key in $linkinfo for the resource id
	 * @param string $titlekey					- Override the key in $linkinfo for the resource title
	 * @return vB_Friendly_Url					- The apprpriate friendly url class
	 */
	public static function fetchLibrary(&$registry, $link, $linkinfo = null, $pageinfo = null, $idkey = null, $titlekey = null)
	{
		global $show;

		$linkoptions = explode('|', $link);
		$linktype = $linkoptions[0];

		$urloptions = 0;
		if (in_array('nosession', $linkoptions))
		{
			$urloptions += SEO_NOSESSION;
		}
		if (in_array('js', $linkoptions))
		{
			$urloptions += SEO_JS;
		}
		if (in_array('bburl', $linkoptions))
		{
			$urloptions += SEO_FULLURL + SEO_FULLURL_FORCEBBURL;
		}
		if (in_array('fullurl', $linkoptions))
		{
			$urloptions += SEO_FULLURL;
		}


		//this can be called prior to the Hooks system being set up so we need to shield it.
		if (class_exists('vBulletinHook'))
		{
			// Allow hooks to override the class
			($hook = vBulletinHook::fetch_hook('friendlyurl_resolve_class')) ? eval($hook) : false;
		}

		if (!isset($class))
		{
			$class = 'vB_Friendly_Url_' . ucfirst($linktype);
		}

		if (class_exists($class, false))
		{
			$instance = new $class($registry, $linkinfo, $pageinfo, $idkey, $titlekey, $urloptions);
		}
		else
		{
			$instance = new vB_Friendly_Url_Error($linktype);
		}

		return $instance;
	}


	/**
	 * Checks if the friendly url is relevant for this request.
	 *
	 * @return bool
	 */
	protected function is_eligible()
	{
		return $this->parse_post OR ($_SERVER['REQUEST_METHOD'] == 'GET');
	}


	/**
	 * Sets the linkinfo properties.
	 *
	 * @param array mixed $linkinfo
	 */
	protected function set_linkinfo(array $linkinfo)
	{
		//if the id isn't set, don't force it to 0.
		$this->id = $linkinfo[$this->idkey];
		if ($this->id OR $this->id === 0)
		{
			$this->id = intval($this->id);
		}

		$this->title = $linkinfo[$this->titlekey];

		// Unset the uri as it has changed
		unset($this->uri);
	}


	/**
	 * Sets the pageinfo properties.
	 *
	 * @param array mixed $pageinfo
	 */
	protected function set_pageinfo(array $pageinfo)
	{
		$this->pageinfo = $pageinfo;

		unset($this->uri);
	}


	/**
	 * Cleans output to be parsed into the uri.
	 * Setting $canonical is useful for creating redirect url's that cannot be
	 * encoded for redirects.
	 *
	 * @param string $fragment
	 * @param bool $canonical							- Whether to encode for output
	 * @return string
	 */
	public static function clean_fragment($fragment, $canonical = false)
	{
		global $vbulletin;

		// Convert to UTF-8
		if (self::UNI_CONVERT == $vbulletin->options['friendlyurl_unicode'])
		{
			// convert to UTF-8
			$fragment = to_utf8($fragment, $vbulletin->userinfo['lang_charset']);

			// convert NCRs
			$fragment = unhtmlspecialchars($fragment, true);
		}
		else if (self::UNI_STRIP == $vbulletin->options['friendlyurl_unicode'])
		{
			// strip NCRs
			$fragment = stripncrs($fragment);
		}

		// Remove url entities
		$fragment = self::clean_entities($fragment);

		// Prepare the URL for output
		if (!$canonical AND (self::UNI_CONVERT == $vbulletin->options['friendlyurl_unicode']) AND ('UTF-8' != $vbulletin->userinfo['lang_charset']))
		{
			if (is_browser('ie'))
			{
				if ($vbulletin->options['friendlyurl_ncrencode'])
				{
					$fragment = ncrencode($fragment, true);
				}
			}
			else
			{
				$fragment = urlencode($fragment);
			}
		}
		else if ($canonical AND (self::UNI_IGNORE == $vbulletin->options['friendlyurl_unicode']))
		{
			// ensure NCRs are converted
			$fragment = unhtmlspecialchars($fragment, true);
		}

		return $fragment;
	}


	/**
	 * Replaces url entities with -
	 *
	 * @param string $fragment
	 * @return string
	 */
	public static function clean_entities($fragment)
	{
		$fragment = preg_replace(self::CLEAN_URL_REGEX, '-', strip_tags($fragment));
		$fragment = trim(preg_replace('#-+#', '-', $fragment), '-');

		return $fragment;
	}

	// New function, VBIV-11727
	protected function addArgument(&$arguments, $var, $value)
	{
		if (is_array($value))
		{
			foreach ($value AS $arraykey => $arrayval)
			{
				$this->addArgument($arguments, $var . "[$arraykey]", $arrayval);
			}
		}
		else
		{
			$arguments[$var] = $value;
		}
	}
	
	/**
	 * Renders the pageinfo query string.
	 * Vars that are included in the main uri should be defined in $ignorelist so
	 * they can be skipped.
	 *
	 * @return string
	 */
	protected function get_query()
	{
		$arguments = array();

		// Add session argument if settings require it
		if (!($this->urloptions & SEO_NOSESSION) AND isset($this->registry->session) AND $this->registry->session->visible)
		{
			$arguments['s'] = $this->registry->session->vars['dbsessionhash'];
		}

		// Add any arguments that are not already displayed as part of friendly url
		if (!empty($this->pageinfo) AND is_array($this->pageinfo))
		{
			foreach ($this->pageinfo AS $var => $value)
			{
				if (!$this->skip_query_var($var, false))
				{
					$this->addArgument($arguments, $var, $value); // VBIV-11727
				}
			}
		}

		// Check ampersand to use
		$amp = ($this->urloptions & SEO_JS) ? '&' : '&amp;';
		$arguments = implode_both('=', $amp, $arguments);

		return $arguments;
	}


	/**
	 * Checks whether to ignore a pageinfo element when building the uri.
	 *
	 * @param string $key						- The key of the var to check
	 * @param bool $skip_pageinfo				- Whether to skip current pageinfo
	 * @return bool								- Whether to skip the argument
	 */
	protected function skip_query_var($key, $skip_pageinfo = true)
	{
		if (in_array($key, $this->ignorelist) OR ($skip_pageinfo AND isset($this->pageinfo[$key])))
		{
			return true;
		}

		return false;
	}


	/**
	 * Returns only the uri.
	 * Setting $canonical gets the uri without encoding it for output.
	 * @see vB_Friendly_Url::redirect_canonical_url()
	 *
	 * @param bool $canonical							- If true, don't encode for output
	 * @return string
	 */
	public function get_uri($canonical = false)
	{
		if (isset($this->uri) AND !$canonical)
		{
			return $this->uri;
		}

		if (!$this->always_route AND (FRIENDLY_URL_OFF == $this->registry->options['friendlyurl']))
		{
			return false;
		}

		return $this->uri = $this->clean_fragment($this->id . '-' . $this->title, $canonical);
	}


	/**
	 * Sets the uri from the current request.
	 */
	protected function consume_request_uri()
	{
		// Ensure the request was parsed
		$this->parse_request_uri();

		$this->uri = self::$request_uri;
		$this->id = $_GET[$this->idvar];
	}


	/**
	 * Adds pageinfo from the current request's query string.
	 */
	protected function consume_request_pageinfo($clean = false)
	{
		// Ensure the request was parsed
		$this->parse_request_uri();

		// Get the query string
		if (!VB_URL_QUERY)
		{
			return;
		}

		parse_str(VB_URL_QUERY, $query);

		// If using FRIENDLY_URL_BASIC ignore the first query var that was requested as it is the uri
		if (FRIENDLY_URL_BASIC == FRIENDLY_URL)
		{
			array_shift($query);
		}

		// Build the pageinfo from the query string
		foreach ($query AS $key => $value)
		{
			if (!$this->skip_query_var($key))
			{
				if ($clean)
				{
					$key = $this->registry->input->xss_clean($key);
					$value = $this->registry->input->xss_clean($value);
				}

				$this->pageinfo[$key] = $value;
			}
		}
	}


	/**
	 * Parses the uri from the current request.
	 * This method also sets FRIENDLY_URL as the detected method and applies the
	 * results to $_REQUEST and $_GET.
	 */
	public function parse_request_uri()
	{
		// Check if we have already defined the request method
		if (self::$parsed_request)
		{
			return;
		}

		// Mark the request as parsed
		self::$parsed_request = true;

		// Check if script uses friendly urls
		if (!$this->is_eligible())
		{
			define('FRIENDLY_URL', FRIENDLY_URL_OFF);

			return;
		}

		// Initialise resolution vars
		$method = $uri = false;

		// Get the requested path info
		$rewrite = false;

		// Check if script is in the uri
		if (stripos(VB_URL_CLEAN, $this->script) !== false)
		{
			// if the primaryvar was requested then we're not using friendly urls
			if (isset($_GET[$this->idvar]))
			{
				$method = FRIENDLY_URL_OFF;

				// Get raw uri for 'always_route' friendlies
				if ($this->always_route)
				{
					$pat_off = preg_quote($this->script, '#') . '\?' . preg_quote($this->idvar, '#') . '=([^=]*)(?:\?|&|$)';
					$matches = array();

					if (preg_match("#$pat_off#sui", VB_URL_CLEAN, $matches))
					{
						$uri = $matches[1];
					}
				}
				else
				{
					// the friendly will build the uri from the parsed query
					$uri = false;
				}
			}
			else
			{
				// Check for BASIC and ADVANCED
				$pat_basic = preg_quote($this->script, '#') . '\?([^=]*)(?:\?|&|$)';
				$pat_advanced = preg_quote($this->script, '#') . '/([^\?]*)';
				$matches = array();

				if (preg_match("#$pat_basic#si", VB_URL_CLEAN, $matches))
				{
					$method = FRIENDLY_URL_BASIC;
					$uri = $matches[1];
				}
				else if (preg_match("#$pat_advanced#i", VB_URL_CLEAN, $matches))
				{
					$method = FRIENDLY_URL_ADVANCED;
					$uri = $matches[1];
				}
			}
		}
		else
		{
			// Check for REWRITE
			$pat_rewrite = '(?:' . preg_quote($this->rewrite_segment, '#') . '/)*' . preg_quote($this->rewrite_segment, '#') . '/([^\?]*|$)';
			if (preg_match("#$pat_rewrite#sui", VB_URL_CLEAN, $matches))
			{
				$method = FRIENDLY_URL_REWRITE;
				$uri = $matches[1];
			}
		}

		// Check if we didn't detect a method
		if (!$method)
		{
			$method = FRIENDLY_URL_OFF;
		}

		// Set the decoded uri
		self::$request_uri = urldecode($uri);

		// Define the resolved friendly url method
		define('FRIENDLY_URL', $method);

		// If friendly url is off then there's nothing more to do
		if (FRIENDLY_URL == FRIENDLY_URL_OFF)
		{
			return;
		}

		// Remove the request uri from the query string
		if (FRIENDLY_URL == FRIENDLY_URL_BASIC)
		{
			unset($_REQUEST[self::$request_uri]);
			array_shift($_GET);
		}

		// Fix a known issue with apache
		if (is_server('apache') AND isset($_SERVER['REDIRECT_URL']))
		{
			self::fix_query_string($url);
		}

		// Set the request based on the decoded fragment
		if ($this->always_route)
		{
			$_REQUEST[$this->idvar] = $_GET[$this->idvar] = self::$request_uri;
		}
		else
		{
			$this->set_request(self::$request_uri);
		}

		$this->registry->input->convert_shortvars($_GET, true);
	}


	/**
	 * Render the friendly url.
	 *
	 * @example
	 * 	FRIENDLY_URL_OFF
	 *	showthread.php?t=1234&p=2
	 *
	 *	FRIENDLY_URL_BASIC
	 *	showthread.php?1234-Thread-Title/page2&pp=2
	 *
	 *	FRIENDLY_URL_ADVANCED
	 *	showthread.php/1234-Thread-Title/page2?pp=2
	 *
	 *	FRIENDLY_URL_REWRITE
	 *	/threads/1234-Thread-Title/page2?pp=2
	 *	RewriteRule ^/vb4/threads/([0-9]+)(?:/?$|(?:-[^/]+))(?:/?$|(?:/page([0-9]+)?)) /vb4/showthread.php?t=$1&page=$2 [QSA]
	 *
	 * @param int $method_override				- Force a Friendly URL method
	 * @param bool $canonical					- Whether to skip encoding for output
	 * @return string
	 */
	public function get_url($method_override = false, $canonical = false)
	{
		// Get the fragments
		$uri = $this->get_uri($canonical);
		
		// Check ampersand to use
		$amp = ($this->urloptions & SEO_JS) ? '&' : '&amp;';

		// Get the pageinfo arguments
		$query = $this->get_query();

		// Resolve method
		$method = (false !== $method_override) ? $method_override : $this->registry->options['friendlyurl'];

		if ($method == FRIENDLY_URL_REWRITE)
		{
			$base = $this->rewrite_segment;
		}
		else
		{
			$base = $this->script;
		}	
		
		//this is a nasty workaround, but we have to do it.  Instead of dealing with the base option
		//and forcing the full url after we construct the url, do it before.  The reason is that 
		//create_full_url can deal poorly with UTF characters encoded as &#xxxx; in the url (because it contains
		//'#'.  This may happen in friendly urls when the title contains UTF characters in this format.  
		if ($this->script_base_option_name AND $this->registry->options[$this->script_base_option_name])
		{
			$base = $this->registry->options[$this->script_base_option_name] . '/' . $base;
		}

		if ($this->urloptions & SEO_FULLURL)
		{
			$base = create_full_url($base, (bool) ($this->urloptions & SEO_FULLURL_FORCEBBURL));
		}

		// Get the appropriate url
		switch ($method)
		{
			case FRIENDLY_URL_BASIC:
				$sep = '?';
				$query_sep = $amp;

				//if we don't have a uri part, then switch around the seperators so the url still works.
				if (!$uri)
				{
					$sep = '';
					$query_sep = '?';
				}
				$url = $base . $sep . $uri . ($query ? $query_sep . $query : '');
				break;

			case FRIENDLY_URL_ADVANCED:
				$url = $base . ($uri ? '/' : '') . $uri . ($query ? '?' . $query : '');
				break;

			case FRIENDLY_URL_REWRITE:
				$url = $base . ($uri ? '/' : '') . $uri . ($query ? '?' . $query : '');
				break;

			case FRIENDLY_URL_OFF:
			default:
				$fullquery = array();
				if ($this->id)
				{
					$fullquery[] = $this->idvar . '=' . $this->id;
				}

				if ($this->page > 1)
				{
					$fullquery[] =  $this->pagevar . '=' . $this->page;
				}
				
				if ($query)
				{
					$fullquery[] = $query;
				}

				$fullquery = implode($amp, $fullquery);
				$url = $base . ($fullquery ? '?' : '') . $fullquery;
				break;
		}

		return $url;
	}

	/**
	 * Redirects to our url if the given uri is not canonical.
	 *
	 * @param string $request_uri				- The current uri to check
	 */
	public function redirect_canonical_url($request_uri)
	{
		// Never redirect a post
		if ('GET' != $_SERVER['REQUEST_METHOD'])
		{
			return;
		}

		// Allow hooks to handle non canonical urls
		($hook = vBulletinHook::fetch_hook('friendlyurl_redirect_canonical')) ? eval($hook) : false;

		// Check if canonical enforcement is enabled
		if (self::CANON_OFF == $this->registry->options['friendlyurl_canonical'] OR VB_API)
		{
			return;
		}

		// Only redirect guests and search engines
		if ($this->registry->userinfo['userid'] AND !$this->registry->options['friendlyurl_canonical_registered'])
		{
			return;
		}

		// Get the canonical uri
		if (!isset($canonical_uri))
		{
			// Get the canonical uri
			$canonical_uri = $this->get_uri(true);
		}

		// Whether the request was canonical
		$canonical = true;

		// Check Friendly URL method
		if (FRIENDLY_URL != $this->registry->options['friendlyurl'])
		{
			$canonical = false;
		}

		// Check URI
		if ($canonical AND (self::CANON_STRICT == $this->registry->options['friendlyurl_canonical']))
		{
			if ($request_uri != $canonical_uri)
			{
				$canonical = false;

				// request may have been in the current charset, try UTF-8.
				if ($canonical_uri == to_utf8($request_uri, $this->registry->userinfo['lang_charset']))
				{
					$canonical = true;
				}

				// request may have been in UTF-8, try current charset
				if ($request_uri == to_utf8($canonical_uri, $this->registry->userinfo['lang_charset']))
				{
					$canonical = true;
				}
			}
		}

		parse_str($_SERVER['QUERY_STRING'], $args);

		if ($this->registry->options['friendlyurl'] != FRIENDLY_URL_OFF AND (isset($args[$this->pagevar]) OR isset($args[$this->idkey])))
		{
			$canonical = false;
		}

		if ($this->registry->options['friendlyurl'] == FRIENDLY_URL_OFF AND !$canonical_uri)
		{
			if (isset($this->pagevar))
			{
				$this->ignorelist[] = $this->pagevar;

				if ($this->pagevar == 'page')
				{
					$this->ignorelist[] = 'pagenumber';
				}
			}

			if (($this->page > 1 AND $args[$this->pagevar] != $this->page) OR ($this->page == 1 AND isset($args[$this->pagevar])))
			{
				$canonical = false;
			}

			$canonical = ($canonical AND !array_intersect(array_values($this->registry->input->shortvars), array_keys($args)));
		}
		
		// Redirect if incorrect
		if (!$canonical)
		{
			// add the request query string to the pageinfo
			$this->consume_request_pageinfo();

			// redirect url must be raw
			$url = $this->get_url(false, true);
			$code = 301;

			// workaround for goto
			if (defined('THREADNEXT'))
			{
				$url = str_replace('&goto=nextnewest', '', str_replace('&goto=nextoldest', '',
						str_replace('?goto=nextnewest', '', str_replace('?goto=nextoldest', '', $url))));

				$code = 302;
			}

			// redirect to the correct url
			exec_header_redirect($url, $code);
		}
	}


	/**
	 * Set the request based on the given uri.
	 * @see vB_Friendly_Url::decode_friendly_url()
	 *
	 * The fragment given here is already decoded and should be used as is.
	 *
	 * @param string $uri
	 */
	protected function set_request($uri)
	{
		// Set the request based on the given uri
		$pat = '#^(\d+)#si';
		$matches = array();

		if (preg_match($pat, $uri, $matches))
		{
			$_REQUEST[$this->idvar] = $_GET[$this->idvar] = $matches[1];
		}
	}


	/**
	 * Removes bad $_GET variables that may be set by apache when using mod_rewrite.
	 * @see https://issues.apache.org/bugzilla/show_bug.cgi?id=34602
	 *
	 * When using mod_rewrite, the fragment is urldecoded before the QS is appended
	 * to the rewritten url.  If the fragment contains & then $_GET will be
	 * corrupted.
	 *
	 * This method checks the correct uri and resolves the correct values for $_GET.
	 *
	 * @param string $fragment					- The decoded fragment
	 */
	public function fix_query_string($uri)
	{
		static $fixed = false;

		if ($fixed)
		{
			return;
		}

		$fixed = true;

		// Probably also need to return if this is not apache
		if (FRIENDLY_URL_REWRITE != FRIENDLY_URL)
		{
			return;
		}

		$uri = parse_url($uri);
		$_SERVER['QUERY_STRING'] = $uri['query'];

		$_REQUEST = array_diff($_REQUEST, array_diff($_GET, $_POST, $_COOKIE));
		$_GET = array();

		if ($_SERVER['QUERY_STRING'])
		{
			// Get the query string
			parse_str($_SERVER['QUERY_STRING'], $query);

			$_GET = array_merge($_GET, $query);
			$_REQUEST = array_merge($_REQUEST, $_GET);
		}

		$this->registry->input->convert_shortvars($_REQUEST);
		$this->registry->input->convert_shortvars($_GET);
	}

	public function getScript()
	{
		return $this->script;
	}

	public function getRewriteSegment()
	{
		return $this->rewrite_segment;
	}

	/**
	 * Fetches a friendly name for a FRIENDLY_URL method.
	 * Note: This is only for debugging so the names are unphrased.
	 *
	 * @param int $method						- The method to fetch a name for
	 * @return string							- The friendly name
	 */
	public static function getMethodName($method)
	{
		static $methods = array(
			FRIENDLY_URL_OFF => 'Off',
			FRIENDLY_URL_BASIC => 'Basic',
			FRIENDLY_URL_ADVANCED => 'Advanced',
			FRIENDLY_URL_REWRITE => 'Rewrite'
		);

		return (isset($methods[$method])) ? $methods[$method] : 'Unknown (' . $method . ')';
	}

	/*
	 *	Return the friendly url type used to access the current page.
	 *	If we didn't use the friendly url, then we'll return FRIENDLY_URL_OFF 
	 *	This is available from a public define, but I'd like to move away from
	 *	the use of magic defines and keep things a behind interfaces.
	 *
	 *	The results of this function are not valid until after bootstrap (specifically
	 *	the port of bootstrap where we deal with parsing SEO Friendly links).
	 */
	public static function getMethodUsed()
	{
		if (!defined('FRIENDLY_URL'))
		{
			return FRIENDLY_URL_OFF; 
		}

		else 
		{
			return FRIENDLY_URL;
		}
	}
}


/**
 * Base class for paged friendly urls.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 32878 $
 * @since $Date: 2009-10-28 18:38:49 +0000 (Wed, 28 Oct 2009) $
 * @copyright vBulletin Solutions Inc.
 */
abstract class vB_Friendly_Url_Paged extends vB_Friendly_Url
{
	/**
	 * The request var to use for the page number.
	 *
	 * @var string
	 */
	protected $pagevar = 'page';

	/**
	 * The current page.
	 *
	 * @var int
	 */
	protected $page;


	/**
	 * Checks whether to ignore a pageinfo element when building the uri.
	 *
	 * @param string $argument					- The argument to check
	 * @param bool $skip_pageinfo				- Whether to skip current pageinfo
	 * @return bool								- Whether to skip the argument
	 */
	protected function skip_query_var($argument, $skip_pageinfo = true)
	{
		if (in_array($argument, array('page', 'pagenumber')))
		{
			if ($this->registry->options['friendlyurl'] != FRIENDLY_URL_OFF)
			{
				return true;
			}
		}

		return parent::skip_query_var($argument, $skip_pageinfo);
	}


	/**
	 * Sets the pageinfo properties.
	 *
	 * @param array mixed $pageinfo
	 */
	protected function set_pageinfo(array $pageinfo)
	{
		if (isset($pageinfo['pagenumber']))
		{
			$this->page = $pageinfo['pagenumber'];
			unset($pageinfo['pagenumber']);
		}
		else if (isset($pageinfo['page']))
		{
			$this->page = $pageinfo['page'];
			unset($pageinfo['page']);
		}

		$this->page = max(0, intval($this->page));

		$this->pageinfo = $pageinfo;

		// Unset the uri as it's changed
		unset($this->uri);
	}


	/**
	 * Gets all of the fragments for the uri.
	 *
	 * @param bool $canonical							- If true, don't encode for output
	 * @return string
	 */
	public function get_uri($canonical = false)
	{
		if (isset($this->uri) AND !$canonical)
		{
			return $this->uri;
		}

		if (!$this->always_route AND (FRIENDLY_URL_OFF == $this->registry->options['friendlyurl']))
		{
			return false;
		}

		$this->uri = $this->clean_fragment($this->id . '-' . $this->title, $canonical) . (($this->page > 1)? '/page' . $this->page : '');

		return $this->uri;
	}


	/**
	 * Set the request based on the given uri.
	 * @see vB_Friendly_Url::decode_friendly_url()
	 *
	 * @param string $uri
	 */
	protected function set_request($uri)
	{
		$pat = '#^(\d+).*?(?:/page(\d+)|$)#si';
		$matches = array();

		if (preg_match($pat, $uri, $matches))
		{
			$_REQUEST[$this->idvar] = $_GET[$this->idvar] = $matches[1];

			if ($matches[2])
			{
				$_REQUEST[$this->pagevar] = $_GET[$this->pagevar] = intval($matches[2]);
			}
		}
	}
}


/**
 * Friendly URL class to use for errors.
 * This is used when an appropriate class could not be resolved.
 * @see vB_Friendly_Url::fetchLibrary()
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 32878 $
 * @since $Date: 2009-10-28 18:38:49 +0000 (Wed, 28 Oct 2009) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_Friendly_Url_Error
{
	/**
	* Constructor
	*
	* @return	void
	*/
	public function __construct($link)
	{
		$this->link = $link;
	}

	/**
	* Generic error
	*
	* @return	string
	*/
	public function output()
	{
		global $vbulletin, $vbphrase;

		if ($vbulletin->debug)
		{
			return construct_phrase($vbphrase['invalid_link_type'], htmlspecialchars_uni($this->link));
		}

		return '';
	}
}


/**
 * Friendly URL for showthread.php
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 32878 $
 * @since $Date: 2009-10-28 18:38:49 +0000 (Wed, 28 Oct 2009) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_Friendly_Url_Thread extends vB_Friendly_Url_Paged
{
	/**
	 * The request variable for the resource id.
	 *
	 * @var string
	 */
	protected $idvar = 't';

	/**
	 * Link info index of the resource id.
	 *
	 * @var string
	 */
	protected $idkey = 'threadid';

	/**
	 * Array of pageinfo vars to ignore when building the uri.
	 *
	 * @var array string
	 */
	protected $ignorelist = array('t', 'threadid');

	/**
	 * The name of the script that the URL links to.
	 *
	 * @var string
	 */
	protected $script = 'showthread.php';
	protected $script_base_option_name = 'vbforum_url';

	/**
	 * The segment of the uri that identifies this type.
	 *
	 * @var string
	 */
	protected $rewrite_segment = 'threads';
}

/**
 * Friendly URL for showpost.php
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 32878 $
 * @since $Date: 2009-10-28 18:38:49 +0000 (Wed, 28 Oct 2009) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_Friendly_Url_Post extends vB_Friendly_Url
{
	/**
	 * The request variable for the resource id.
	 *
	 * @var string
	 */
	protected $idvar = 'p';

	/**
	 * Link info index of the resource id.
	 *
	 * @var string
	 */
	protected $idkey = 'postid';

	/**
	 * Link info index of the title.
	 *
	 * @var string
	 */
	protected $titlekey = 'title';

	/**
	 * Array of pageinfo vars to ignore when building the uri.
	 *
	 * @var array string
	 */
	protected $ignorelist = array('p', 'postid', 'title');

	/**
	 * The name of the script that the URL links to.
	 *
	 * @var string
	 */
	protected $script = 'showpost.php';
	protected $script_base_option_name = 'vbforum_url';

	/**
	 * The segment of the uri that identifies this type.
	 *
	 * @var string
	 */
	protected $rewrite_segment = 'post';
}


/**
 * Friendly URL for member.php
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 32878 $
 * @since $Date: 2009-10-28 18:38:49 +0000 (Wed, 28 Oct 2009) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_Friendly_Url_Member extends vB_Friendly_Url
{
	/**
	 * The request variable for the resource id.
	 *
	 * @var string
	 */
	protected $idvar = 'u';

	/**
	 * Link info index of the resource id.
	 *
	 * @var string
	 */
	protected $idkey = 'userid';

	/**
	 * Link info index of the title.
	 *
	 * @var string
	 */
	protected $titlekey = 'username';

	/**
	 * Array of pageinfo vars to ignore when building the uri.
	 *
	 * @var array string
	 */
	protected $ignorelist = array('u', 'userid', 'username');

	/**
	 * The name of the script that the URL links to.
	 *
	 * @var string
	 */
	protected $script = 'member.php';
	protected $script_base_option_name = 'vbforum_url';

	/**
	 * The segment of the uri that identifies this type.
	 *
	 * @var string
	 */
	protected $rewrite_segment = 'members';
}


/**
 * Friendly URL for forumdisplay.php
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 32878 $
 * @since $Date: 2009-10-28 18:38:49 +0000 (Wed, 28 Oct 2009) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_Friendly_Url_Forum extends vB_Friendly_Url_Paged
{
	/**
	 * The request variable for the resource id.
	 *
	 * @var string
	 */
	protected $idvar = 'f';

	/**
	 * Link info index of the resource id.
	 *
	 * @var string
	 */
	protected $idkey = 'forumid';

	/**
	 * Array of pageinfo vars to ignore when building the uri.
	 *
	 * @var array string
	 */
	protected $ignorelist = array('f', 'forumid');

	/**
	 * The name of the script that the URL links to.
	 *
	 * @var string
	 */
	protected $script = 'forumdisplay.php';
	protected $script_base_option_name = 'vbforum_url';

	/**
	 * The segment of the uri that identifies this type.
	 *
	 * @var string
	 */
	protected $rewrite_segment = 'forums';
}


/**
 *	Base class for Url classes that are intended to generate urls only
 *
 *	This is a bit of a hack, but the avantages outweigh the disadvantages
 *  The idea is to centralize some url generation that has gotten more complex
 *  with the ability to move some portions of the app into subdirectories.
 *
 *  We need some way of capturing the generation logic in a central location and 
 *  rather than create another one, it seemed like a good fit to make it part of 
 *  the friendly urls generation. 1) To avoid having yet another similar system, 
 *  2) This will automatically allow access to the feature in templates, 3) If
 *  we decide we need to make fully SEO friendly urls out of these links we are 
 *  partway there already. 4) Prevents somebody from accidentally leaving out 
 *  portions of the logic on a link -- for example forgetting the forum_url path 
 *  logic on a forum link or the forumhome script option on the forumhome link.
 *
 *  It may be confusing having these be special -- they do not generate anything
 *  but basic urls and the pages (and the rewrite scripts) are not set up to handle them
 *  if they did generate those urls (though in the cases where there aren't any page 
 *  parameters only the rewrite url is appreciably different).  If somebody attempts to 
 *  allow generation of the other url types without upgrading the entry pages to 
 *  friendly url status stuff will break.
 *
 */
class vB_Friendly_Url_GenerationOnly extends vB_Friendly_Url
{
	public function get_url($method_override = false, $canonical = false)
	{
		return parent::get_url(FRIENDLY_URL_OFF, $canonical);
	}

	public function redirect_canonical_url($request_uri)
	{
		//Internal error, should never happen.  Probably doesn't need to be phrased.
		throw new Exception("Invalid request.  Url class for url generation only.");
	}

	//The SEO url classes do two things
	//1) Generate urls for various pages in line with the SEO url options.
	//2) Read in the url and parse out the parameters for a particular page
	//
	//There isn't anything that tells the class which action it needs to do, this is 
	//determined by whether or not the page info array is provided to the class -- 
	//the assumption is that all urls will need page info and if its not passed it 
	//should be read from the url.
	//
	//Its also implicit that only one type of url -- the one for the page we are loading --
	//should ever parse the request object.  So we store the parsed information once on 
	//the base class and share that among all SEO url instances.
	//
	//We violate the first assumion when we add generation only urls that do not require
	//page info to render.  It looks at the empty page info and decides it needs to parse the
	//URI values.  However if it does this prior to the place where we handle this in the proper
	//location it will set incorrect information to those parameters and the real call will
	//use those instead of doing the proper parsing.  Since any url without page parameters 
	//are going to be generation only -- there isn't anything to parse, we can work around this
	//by disabling the parse in those classes.
	//
	//Long term we should figure out a better way to seperate out the URI load vs the url generation
	//use cases.
	public function consume_request_uri()
	{
	}
}

/**
 * Friendly URL for forumhome.php
 *
 * @package vBulletin * @author vBulletin Development Team
 * @version $Revision: 32878 $
 * @since $Date: 2009-10-28 18:38:49 +0000 (Wed, 28 Oct 2009) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_Friendly_Url_Forumhome extends vB_Friendly_Url_GenerationOnly
{
	protected $idkey = '';
	protected $titlekey = '';
	protected $ignorelist = array();
	protected $script = 'forum.php';
	protected $script_base_option_name = 'vbforum_url';
	protected $rewrite_segment = '';

	protected function __construct(
		&$registry, 
		array $linkinfo = null, 
		array $pageinfo = null, 
		$idkey = false, 
		$titlekey = false, 
		$urloptions = 0
	)
	{
		$this->script = $registry->options['forumhome'] . ".php";
		parent::__construct($registry, $linkinfo, $pageinfo, $idkey, $titlekey, $urloptions);
	}
}

/**
 * Friendly URL for "edit post" links 
 */
class vB_Friendly_Url_Editpost extends vB_Friendly_Url_GenerationOnly
{
	protected $idvar = 'p';
	protected $idkey = 'postid';
	protected $titlekey = '';
	protected $script = 'editpost.php';
	protected $script_base_option_name = 'vbforum_url';
}

/**
 * Friendly URL for "new reply" links 
 */
class vB_Friendly_Url_Newreply extends vB_Friendly_Url_GenerationOnly
{
	protected $idkey = '';
	protected $titlekey = '';
	protected $script = 'newreply.php';
	protected $script_base_option_name = 'vbforum_url';
}

class vB_Friendly_Url_Poll extends vB_Friendly_Url_GenerationOnly
{
	protected $idvar = 'pollid';
	protected $idkey = 'pollid';
	protected $titlekey = '';
	protected $script = 'poll.php';
	protected $script_base_option_name = 'vbforum_url';
}

/*
class vB_Friendly_Url_Posting extends vB_Friendly_Url_GenerationOnly
{
	protected $idkey = '';
	protected $titlekey = '';
	protected $script = 'postings.php';
	protected $script_base_option_name = 'vbforum_url';
}
*/

class vB_Friendly_Url_Threadprint extends vB_Friendly_Url_GenerationOnly
{
	protected $idvar = 't';
	protected $idkey = 'threadid';
	protected $titlekey = '';
	protected $script = 'printthread.php';
	protected $script_base_option_name = 'vbforum_url';
}

class vB_Friendly_Url_Subscription extends vB_Friendly_Url_GenerationOnly
{
	protected $idkey = '';
	protected $titlekey = '';
	protected $script = 'subscription.php';
	protected $script_base_option_name = 'vbforum_url';
}

/**
 * Friendly URL for group links 
 */
class vB_Friendly_Url_Group extends vB_Friendly_Url_GenerationOnly
{
	protected $idvar = 'groupid';
	protected $idkey = 'groupid';
	protected $titlekey = 'name';
	protected $ignorelist = array('groupid');
	protected $script = 'group.php';
	protected $script_base_option_name = 'vbforum_url';
}

/**
 * Friendly URL for group category links 
 */
class vB_Friendly_Url_Groupcategory extends vB_Friendly_Url_GenerationOnly
{
	protected $idvar = 'cat';
	protected $idkey = 'socialgroupcategoryid';
	protected $titlekey = 'categoryname';
	protected $ignorelist = array('cat');
	protected $script = 'group.php';
	protected $script_base_option_name = 'vbforum_url';
}

/**
 * Friendly URL for group home links 
 */
class vB_Friendly_Url_Grouphome extends vB_Friendly_Url_GenerationOnly
{
	protected $idkey = '';
	protected $titlekey = '';
	protected $script = 'group.php';
	protected $script_base_option_name = 'vbforum_url';
}

/**
 * Friendly URL for group discussion links 
 */
class vB_Friendly_Url_Groupdiscussion extends vB_Friendly_Url_GenerationOnly
{
	protected $idvar = 'discussionid';
	protected $idkey = 'discussionid';
	protected $titlekey = '';
	protected $script = 'group.php';
	protected $script_base_option_name = 'vbforum_url';

	protected function __construct(
		&$registry, 
		array $linkinfo = null, 
		array $pageinfo = null, 
		$idkey = false, 
		$titlekey = false, 
		$urloptions = 0
	)
	{
		if (!$pageinfo)
		{
			$pageinfo = array();
		}

		if (!isset($pageinfo['do']))
		{
			$pageinfo['do'] = 'discuss';
		}

		parent::__construct($registry, $linkinfo, $pageinfo, $idkey, $titlekey, $urloptions);
	}
}

class vB_Friendly_Url_Groupsub extends vB_Friendly_Url_GenerationOnly
{
	protected $idkey = '';
	protected $titlekey = '';
	protected $script = 'groupsubscription.php';
	protected $script_base_option_name = 'vbforum_url';
}

/**
 * Friendly URL for group discussion links 
 */
class vB_Friendly_Url_Groupmessage extends vB_Friendly_Url_GenerationOnly
{
	protected $idvar = 'gmid';
	protected $idkey = 'gmid';
	protected $titlekey = '';
	protected $script = 'group.php';
	protected $script_base_option_name = 'vbforum_url';

	protected function __construct(
		&$registry, 
		array $linkinfo = null, 
		array $pageinfo = null, 
		$idkey = false, 
		$titlekey = false, 
		$urloptions = 0
	)
	{
		if (!$pageinfo)
		{
			$pageinfo = array();
		}

		if (!isset($pageinfo['do']))
		{
			$pageinfo['do'] = 'discuss';
		}

		parent::__construct($registry, $linkinfo, $pageinfo, $idkey, $titlekey, $urloptions);
	}
}


/**
 * Blog Url
 **/


/**
 * Friendly URL for blog.php
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 32878 $
 * @since $Date: 2009-10-28 18:38:49 +0000 (Wed, 28 Oct 2009) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_Friendly_Url_Blog extends vB_Friendly_Url_Paged
{
	/**
	 * The request variable for the resource id.
	 *
	 * @var string
	 */
	protected $idvar = 'u';

	/**
	 * Link info index of the resource id.
	 *
	 * @var string
	 */
	protected $idkey = 'userid';

	/**
	 * Link info index of the title.
	 *
	 * @var string
	 */
	protected $titlekey = 'blog_title';

	/**
	 * Array of pageinfo vars to ignore when building the uri.
	 *
	 * @var array string
	 */
	protected $ignorelist = array('u', 'userid', 'b', 'blogid');

	/**
	 * The name of the script that the URL links to.
	 *
	 * @var string
	 */
	protected $script = 'blog.php';
	protected $script_base_option_name = 'vbblog_url';

	/**
	 * The segment of the uri that identifies this type.
	 *
	 * @var string
	 */
	protected $rewrite_segment = 'blogs';
}


/**
 * Friendly URL for entry.php
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 32878 $
 * @since $Date: 2009-10-28 18:38:49 +0000 (Wed, 28 Oct 2009) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_Friendly_Url_Entry extends vB_Friendly_Url_Paged
{
	/**
	 * The request variable for the resource id.
	 *
	 * @var string
	 */
	protected $idvar = 'b';

	/**
	 * Link info index of the resource id.
	 *
	 * @var string
	 */
	protected $idkey = 'blogid';

	/**
	 * Array of pageinfo vars to ignore when building the uri.
	 *
	 * @var array string
	 */
	protected $ignorelist = array('b', 'blogid');

	/**
	 * The name of the script that the URL links to.
	 *
	 * @var string
	 */
	protected $script = 'entry.php';
	protected $script_base_option_name = 'vbblog_url';

	/**
	 * The segment of the uri that identifies this type.
	 *
	 * @var string
	 */
	protected $rewrite_segment = 'entries';
}

/**
 * Friendly URL for "blog home" links (blog.php links that don't reference a specific user's blog
 */
class vB_Friendly_Url_Bloghome extends vB_Friendly_Url_GenerationOnly
{
	/*
	 * Note that if we ever want to make this something other than generation only
	 * (or to have the generation only class attempt to use any url mode other then
	 * FRIENDLY_URL_OFF) we have a potential problem.  Any urls built by this class
	 * will be read/parsed by vB_Friendly_Url_Blog since that is the class specified
	 * by blog.php, so there is some asymetry there.  It will probably work anyway, based
	 * on the similarity of the two classes, but that's a good way for things to break
	 * mysteriously when future changes are made.
	 *
	 * I'm doing it anyway because
	 * 1) The asymetry is implicitly in the system as is, its just less clear because
	 * 		the urls not handled by vB_Friendly_Url_Blog aren't currently centralized.
	 * 2) Trying to make the current full SEO classes handle urls that don't have 
	 *    the expected id/title values feel like a big can labeled worms.  If we 
	 *    want to make these classes do more than generated the old style urls
	 *    there is going to be quite a bit of work involved and we can do most of 
	 *		behind the interface we have if needs be (for example if make vB_Friendly_Url_Blog
	 *		handle everything and make this class a clone of vB_Friendly_Url_Blog then we'll
	 *		have cleaned that up without changing any code outside of this file).
	 * 3) Blog.php conceptually handles too much (which is possibly the root of the 
	 *    problem leading to the existance of this class).  We may want to split that
	 *    out into new files (or url based actions) which would also resolve the tension.
	 *
	 * Also note that we don't have vB_Friendly_Url_Paged in the loop for this class.  That
	 * implements some special handling of paging variables that we don't need and trying
	 * to include it would seriously complicate our inheritance.
	 */
	protected $titlekey = '';
	protected $idkey = '';
	protected $script = 'blog.php';
	protected $script_base_option_name = 'vbblog_url';
}

/**
 * Friendly URL for "blog subscription" links 
 */
class vB_Friendly_Url_Blogsub extends vB_Friendly_Url_GenerationOnly
{
	protected $titlekey = '';
	protected $idkey = '';
	protected $script = 'blog_subscription.php';
	protected $script_base_option_name = 'vbblog_url';
}

/**
 * Friendly URL for "blog subscription" links 
 */
class vB_Friendly_Url_Blogusercp extends vB_Friendly_Url_GenerationOnly
{
	protected $titlekey = '';
	protected $idkey = '';
	protected $script = 'blog_usercp.php';
	protected $script_base_option_name = 'vbblog_url';
}

/**
 * Friendly URL for "blog callback" links 
 */
class vB_Friendly_Url_Blogcallback extends vB_Friendly_Url_GenerationOnly
{
	protected $titlekey = '';
	protected $idkey = '';
	protected $script = 'blog_callback.php';
	protected $script_base_option_name = 'vbblog_url';
}

/**
 * Friendly URL for "blog post" links 
 */
class vB_Friendly_Url_Blogpost extends vB_Friendly_Url_GenerationOnly
{
	protected $titlekey = '';
	protected $idkey = '';
	protected $script = 'blog_post.php';
	protected $script_base_option_name = 'vbblog_url';
}


/**
 * Friendly URL for forumhome.php
 *
 * @package vBulletin * @author vBulletin Development Team
 * @version $Revision: 32878 $
 * @since $Date: 2009-10-28 18:38:49 +0000 (Wed, 28 Oct 2009) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_Friendly_Url_Blogcustompage extends vB_Friendly_Url_GenerationOnly
{
	protected $idvar = 'cp';
	protected $idkey = 'customblockid';
	protected $ignorelist = array('cp', 'customblockid');
	protected $script = 'blog.php';
	protected $script_base_option_name = 'vbblog_url';
	protected $rewrite_segment = 'blogs';
}

/**
 *	CMS Friendly urls
 */


/**
 * Friendly URL for vBCms
 *
 * @TODO: Resolve the properties automatically.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 32878 $
 * @since $Date: 2009-10-28 18:38:49 +0000 (Wed, 28 Oct 2009) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_Friendly_Url_vBCms extends vB_Friendly_Url
{
	/**
	 * Array of pageinfo vars to ignore when building the uri.
	 *
	 * @var array string
	 */
	protected $ignorelist = array();

	/**
	 * The name of the script that the URL links to.
	 *
	 * @var string
	 */
	protected $script;

	/**
	 * The segment of the uri that identifies this type.
	 *
	 * @var string
	 */
	protected $rewrite_segment;


	/**
	 * Whether to use the friendly uri in POST requests.
	 *
	 * @var bool
	 */
	protected $parse_post = true;

	/**
	 * Whether to always set the route, even if friendly urls are off.
	 *
	 * @var bool
	 */
	protected $always_route = true;


	/**
	 * Constructor.
	 *
	 * @param vB_Registry $registry				- Reference to the vBulletin registry
	 * @param array $linkinfo					- Info about the link, the id, title etc
	 * @param array $pageinfo					- Additional info about the required request; pagenumber and query string
	 * @param string $idkey						- Override the key in $linkinfo for the resource id
	 * @param string $titlekey					- Override the key in $linkinfo for the resource title
	 * @param int $urloptions					- Bitfield of environment options SEO_NOSESSION, SEO_JS, etc
	 */
	protected function __construct(&$registry, array $linkinfo = null, array $pageinfo = null, $idkey = false, $titlekey = false, $urloptions = 0)
	{
		$this->idvar = $this->ignorelist[] = $registry->options['route_requestvar'];
		$this->script = basename(SCRIPT);

		parent::__construct($registry, $linkinfo, $pageinfo, $idkey, $titlekey, $urloptions);
	}
}


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 27657 $
|| ####################################################################
\*======================================================================*/
