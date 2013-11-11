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
 * Route Superclass.
 * The route object is responsible for parsing a route path into values, allowing
 * modification of those values, supplying the URL to the modified route and
 * delegating the appropriate controller for the route in it's current state.
 *
 * Which route class is used by default can be defined via the router.  Applications
 * need to create their own subclass of the route to enable application specific
 * behaviour.
 *
 * For this reason, route objects should only be created with the router factory
 * method router::newRoute().
 *
 * Note: Defined members are preceeded with an underscore to avoid collision with
 * magic properties in $_segments accessed via __get() and __set().
 *
 * @author vBulletin Development Team
 * @version $Revision: 29650 $
 * @since $Date: 2009-02-25 15:39:20 +0000 (Wed, 25 Feb 2009) $
 * @copyright vBulletin Solutions Inc.
 */
abstract class vB_Route
{
	/*Properties====================================================================*/

	/**
	 * The value of the user request segment of this route class.
	 * This is fetched from vB_Router using the defined lookup table `route`.
	 * @see vB_Router::getRouteSegmentFromClass()
	 *
	 * @var string
	 */
	protected $_class_segment;

	/**
	 * Whether the given route is valid.
	 *
	 * @var bool
	 */
	protected $_is_valid = true;

	/**
	 * Array of segment names => values.
	 * Populated when the route is consumed or new segments are set.
	 *
	 * @var array string
	 */
	protected $_segments = array();

	/**
	 * A scheme for segments.
	 * Populating this assoc array allows you to automate parsing of the segments and
	 * can simplify extending the class for applications.
	 *
	 * The schema must be exhaustive and include all possible segments.
	 *
	 * Segments here can be interpreted in three ways:
	 *  Optional with Values
	 * 		If one of defined values is not matched, the segment is not consumed or
	 * 		rendered.
	 *  Expected no Values
	 * 		The next available segment will always be consumed as this.
	 *  Expected with Values
	 * 		Only the defined values are allowed.  Consuming a route that fails the
	 * 		match will throw a 404 unless it is the last segment and has a default
	 * 		value to fall back on. Similarly, setting a segment value with client
	 * 		code that is not in the values list will throw a vB_Exception_Router.
	 *
	 * The scheme is used for both consuming and rendering a route.
	 *
	 * A default value can be specified.  When rendering a route, if the segment is
	 * optional and the value matches the default value, or is unspecified then the
	 * segment will not be rendered.
	 *
	 * Similarly, if the segment is expected and matches the default, and is the
	 * last segment, then it will not be rendered.
	 *
	 * When consuming a route, if a segment is not specified then the given defaults
	 * will be assumed.
	 *
	 * Example scheme:
	 * 	'output'	=>	'optional'	=>	true
	 * 					'values'	=>	'html'
	 * 									'rss'
	 * 									'atom'
	 * 									'ajax'
	 * 									'text'
	 * 					'default'	=>	'html'
	 * 	'language'	=>	'optional'	=>	false
	 * 					'default'	=>	'en-US'
	 * 	'controller'=>	'optional'	=>	false
	 * 					'values'	=>	'index'
	 * 									'news'
	 * 									'about'
	 * 					'default'	=>	'index'
	 *
	 * @var array mixed
	 */
	protected $_segment_scheme;

	/**
	 * Whether the scheme has been validated or not
	 *
	 * @var bool
	 */
	protected $_validated_scheme = false;

	/**
	 * A default controller class.
	 * Useful for routes that only use one controller class.
	 * @see vB_Route::getResponse()
	 *
	 * @var string
	 */
	protected $_default_controller_class;

	/**
	 * The class to use for error rerouting.
	 *
	 * @var string
	 */
	protected $_error_route_class = 'vB_Route_Error';

	/**
	 * The original or compiled route path.
	 * When a route is created an initial route should be specified, or the default
	 * path is used.
	 *
	 * If a segment value or parameters are changed and a URL is requested, the
	 * route path is recompiled.
	 *
	 * @var string
	 */
	protected $_route_path;

	/**
	 * Whether the route has been modified.
	 * When a segment value or parameter has been modified, this is set to true to
	 * allow the route to know that it should recompile the route path if a URL is
	 * requested.
	 *
	 * @var bool
	 */
	protected $_modified = true;

	/**
	 * Whether the original route path was parsed into values.
	 * When a segment value is set or requested the route first parses the original
	 * route path given upon creation and assigns the values to the defined segments.
	 *
	 * @var bool
	 */
	protected $_parsed = false;

	/**
	 * Controller Parameters.
	 * Controller parameters make up part of the route to pass values to the
	 * requested controller.  If the controller is changed, child classes should
	 * ensure that the parameters are emptied.
	 *
	 * Controllers can request their parameters with router::getParameters().
	 *
	 * NOTE: Controllers are responsible for validating all parameter and segment
	 * values.
	 *
	 * @var array mixed
	 */
	protected $_parameters = array();

	/**
	 * Whether to reset all parameters whenever any segment changes.
	 * If a route needs to reset parameters on selected segments only then it should
	 * override vB_Route::assignSegment().
	 *
	 * @var bool
	 */
	protected $_reset_params_on_change = false;


	/**
	 * The default route path.
	 * If a route path is not specified when the route is created, the default path
	 * is used.
	 *
	 * @var string
	 */
	protected $_default_path = false;

	/**
	 * The message of a reroute.
	 * If a reroute occurs, a message can be stored here for display.
	 *
	 * @var string
	 */
	protected static $_reroute_message;


	/** Forces an absolute path
	 *
	 ***/
	protected $_force_absolute_path = false;


	/*Initialisation================================================================*/

	/**
	 * The constructor.
	 * A base URL is required to prepend to compiled URL.  If a route path is not
	 * specified then the default route path is assumed.
	 * The route path can also be specified as integer 404 or integer 500 to use the
	 * error paths respectively.
	 *
	 * @param string | int $route_path			- The route path to compile
	 */
	public function __construct($route_path = false, $absolute_path = false)
	{
		// Ensure the default path is set for this route
		if (false === $this->_default_path)
		{
			throw (new vB_Exception_Router('No default path has been defined for the route class'));
		}

		// Get the class segment for this route type
		if (!($this->_class_segment = trim(vB_Router::getRouteSegmentFromClass(get_class($this)))))
		{
			throw (new vB_Exception_Router('Could not get the request segment for the route class \'' . get_class($this) . '\''));
		}

		// Allow dynamic segment schemes
		$this->buildSegmentScheme();


		// Set the route path
		$this->_route_path = $this->resolveRoutePath($route_path);
		$this->_force_absolute_path = $absolute_path;
	}


	/**
	 * Simple factory method for creating a route.
	 *
	 * @param string $class						- The class of the route to create
	 * @param string $route_path				- The route path to compile
	 * @return vB_Route							- The instantiated route object
	 */
	public static function create($class, $route_path = false, $query_string = false, $absolute_path = false)
	{
		return new $class($route_path, $absolute_path);
	}



	/*URL===========================================================================*/

	/**
	 * String conversion.
	 * Returns a string representation of the current route as used in URLs.
	 *
	 * NOTE: The base_url is not prepended.  @see getURL() to create valid URLs.
	 *
	 * @return string							- The URL representing the route
	 */
	public function __toString()
	{
		try
		{
			// Ensure the route path is fresh
			$this->assertRoutePath();

			return $this->_route_path;
		}
		catch (exception $e)
		{
			return vB_Router::get500Path();
		}
	}


	/**
	 * Returns a representative URL of the current route state.
	 * Optional segments and parameters may be passed to set the route state.
	 *
	 * @param array mixed $segments				- Assoc array of segment => value
	 * @param array mixed $parameters			- Array of parameter values, in order
	 * @param string $query_string				- Query string to append to the url
	 * @param int $override_method				- Allows the Friendly URL method to be overridden
	 * @param bool $canonical					- If true, don't encode for output
	 * @return string							- The URL representing the route
	 */
	public function getCurrentURL(array $segments = null, array $parameters = null, $query_string = '', $override_method = false, $canonical = false)
	{
		// Ensure the route is valid
		if (!$this->isValid())
		{
			throw (new vB_Exception_Router('A route URL was requested but the route is invalid'));
		}
		// Set any values specified
		if ($segments)
		{
			$this->setSegments($segments);
		}

		// Set new parameters if specified
		if ($parameters)
		{
			$this->setParameters($parameters);
		}

		if (!$canonical)
		{
			// Ensure the route path is fresh
			$this->assertRoutePath();
			$route_path = $this->_route_path;
		}
		else
		{
			$route_path = $this->buildRoutePath(true);
		}

		// Chop the route segment
		if (vB::$vbulletin->options['friendlyurl'] != vB_Router::METHOD_REWRITE)
		{
			$route_path = $route_path ? substr($route_path, strpos($route_path, '/')) : '';
		}

		// Return the appropriate route path
		if ((vB::$vbulletin->options['friendlyurl'] != vB_Router::METHOD_REWRITE) AND ($route_path == $this->_class_segment))
		{
			$url = trim(vB_Router::getBaseRoutingURL($this->_class_segment, false, $override_method), '/?');
		}
		else
		{
			// Return the URL
			$slash = ($route_path == $this->_class_segment) ? '/' : '';
			$url = trim(vB_Router::getBaseRoutingURL($this->_class_segment, true, $override_method) . ltrim($route_path, '/') . $slash, '?');
		}

		//are we forcing an absolute path?
		if ($this->_force_absolute_path)
		{
			//the url should start with the bburl. If it doesn't, then prepend it.
			if ((strtolower(substr($url, 0, 5)) != 'http:') AND (strtolower(substr($url, 0, 6)) != 'https:') )
			{
				$url = vB::$vbulletin->options['bburl'] . '/' . $url;
			}
		}

		if (($sessionurl = vB::$vbulletin->session->vars['sessionurl']) OR $query_string)
		{
			$q = (false == strpos($url, '?')) ? '?' : '&amp;';
			$url .= $q . ($sessionurl ? $sessionurl . ($query_string ? $query_string : '') : $query_string) ;
		}

		//we get some trailing &'s. Let's get rid of them
		if (substr($url, - 1) == '&')
		{
			$url = substr($url, 0, strlen($url) - 1);
		}

		if (substr($url, -5) == '&amp;')
		{
			$url = substr($url, 0, strlen($url) - 5);
		}

		return $url;
	}


	/**
	 * Fetches the route path.
	 * If child classes have segments with dynamic values, they may need to infalte
	 * those values to make them canonically correct.
	 *
	 * @param bool $chop_class_segment			- Whether to remove the class segment
	 * @param bool $inflate						- Whether to fetch canonical path
	 * @param bool $canonical					- If true, don't encode for output
	 * @return string
	 */
	public function getRoutePath($chop_class_segment = true, $inflate = false, $canonical = false)
	{
		if ($inflate)
		{
			$this->inflateSegments();
		}

		if (!$canonical)
		{
			// Ensure the route path is fresh
			$this->assertRoutePath();
			$route_path = $this->_route_path;
		}
		else
		{
			$route_path = $this->buildRoutePath(true);
		}

		if ($chop_class_segment)
		{
			$route_path = substr($this->_route_path, strpos($this->_route_path, '/'));
		}

		return trim($route_path, '/');
	}


	/**
	 * Returns a representative URL of a route.
	 * Optional segments and parameters may be passed to set the route state.
	 *
	 * @TODO: Uncomment and remove child implementations when LSB is available
	 *
	 * @param array mixed $segments				- Assoc array of segment => value
	 * @param array mixed $parameters			- Array of parameter values, in order
	 * @return string							- The URL representing the route
	 */
	abstract public static function getURL(array $segments = null, array $parameters = null, $absolute_path = false );
	/*
	{
		$class = __CLASS__;
		$route = new $class();
		return $route->getCurrentURL($segments, $parameters);
	}
	*/


	/** Forces the route to return an absolute path.
	 *
	 * 	@param	bool
	 ***/
	public function setAbsolutePath($absolute = true)
	{
		$this->_force_absolute_path = $absolute;
	}

	/**
	 * Resolves the route path to use.
	 *
	 * @param string $route_path
	 */
	protected function resolveRoutePath($route_path = false)
	{

		if (!$route_path)
		{
			return $this->getDefaultPath();
		}
		$route_path = trim($route_path, ' /&');

		while (strtolower(substr($route_path, -5)) == '&amp;')
		{
			$route_path = substr($route_path, 0, (strlen($route_path) - 5));
		}
		return $route_path;
	}


	/**
	 * Gets the default path.
	 *
	 * @return string
	 */
	public function getDefaultPath()
	{
		return $this->_class_segment . '/' . $this->_default_path;
	}


	/**
	 * Gets the 403 path.
	 *
	 * @return string
	 */
	public function get403Path()
	{
		return vB_Route::create($this->_error_route_class)->get403Path();
	}


	/**
	 * Gets the 404 path.
	 *
	 * @return string
	 */
	public function get404Path()
	{
		return vB_Route::create($this->_error_route_class)->get404Path();
	}


	/**
	 * Gets the 409 path.
	 *
	 * @return string
	 */
	public function get409Path()
	{
		return vB_Route::create($this->_error_route_class)->get409Path();
	}


	/**
	 * Gets the 500 path.
	 *
	 * @return string
	 */
	public function get500Path()
	{
		return vB_Route::create($this->_error_route_class)->get500Path();
	}


	/**
	 * Builds a string path representing the current segment and parameter values.
	 * How this path is built will vary wildly between implementations depending on
	 * the segment scheme.
	 */
	protected function assertRoutePath()
	{
		// Ensure the original route path was parsed
		$this->parseRoutePath();

		// Only compile the route path if it is stale
		if ($this->_modified OR !$this->_route_path)
		{
			$this->_route_path = $this->buildRoutePath();
			$this->_modified = false;
		}
	}


	/**
	 * Builds a route path from the current route state.
	 *
	 * @bool $canonical							- Don't encode for output
	 * @return string
	 */
	protected function buildRoutePath($canonical = false)
	{
		// Ensure the original route path was parsed
		$this->parseRoutePath();

		// start with the class segment
		$route_path = $this->_class_segment;
		$deferred_path = '';
		foreach ($this->_segment_scheme AS $name => $segment)
		{
			if (!$segment['optional'] AND ($this->_segments[$name] == $segment['default']))
			{
				$deferred_path .= ($deferred_path ? '/' : '') . $this->cleanSegment($this->_segments[$name], $canonical);
			}
			else if ($this->_segments[$name] != $segment['default'])
			{
				$route_path = ($route_path ? $route_path . '/' : '') . ($deferred_path ? $deferred_path . '/' : '') . $this->cleanSegment($this->_segments[$name], $canonical);
				$deferred_path = '';
			}
		}

		if (sizeof($this->_parameters))
		{
			// ensure any deferred path is added before parameters
			if ($deferred_path)
			{
				$route_path = ($route_path ? $route_path . '/' : '') . $deferred_path;
			}

			$parameters = array();
			foreach ($this->_parameters AS $parameter)
			{
				$parameters[] = $this->cleanSegment($parameter, $canonical);
			}

			$route_path .= '/' . implode('/', $parameters);
		}


		return $route_path;
	}


	/**
	 * Parses a string path into segment and parameter values.
	 * How the path is parsed will vary between implementations.
	 *
	 * This is only done once when a new route is created and values are requested.
	 */
	protected function parseRoutePath()
	{
		// We only parse the original route path once
		if ($this->_parsed)
		{
			return;
		}

		$this->_parsed = true;

		// Ensure the segment scheme is valid
		$this->validateSegmentScheme();
		// If no route path was given, use the scheme's default values
		if (!$this->_route_path)
		{
			// the default segment values are used
			foreach ($this->_segment_scheme AS $name => $segment)
			{
				if (!$this->validateSegment($name, $this->_segment_scheme[$name]['default']))
				{
					throw (new vB_Exception_Router('The default value \'' . $this->_segment_scheme[$name]['default'] . '\ is not valid for the segment \'' . $name . '\''));
				}

				// validate the segment value and transform if applicable
				$this->setSegment($name, $this->_segment_scheme[$name]['default']);
			}

			return;
		}

		// Break down the given route path
		$segment_values = explode('/', $this->_route_path);

		// Remove and verify the class segment
		if ($this->_class_segment == current($segment_values))
		{
			array_shift($segment_values);
		}

		// Get defined segments
		foreach ($this->_segment_scheme AS $name => $segment)
		{
			if (sizeof($segment_values))
			{
				$valid = $this->validateSegment($name, $segment_values[0]);
			}

			if (!sizeof($segment_values) OR ($this->_segment_scheme[$name]['optional'] AND !$valid))
			{
				$this->setSegment($name, $this->_segment_scheme[$name]['default'], true);
			}
			else if ($this->_segment_scheme[$name]['optional'] OR ($valid))
			{
				$this->setSegment($name, $segment_values[0], true);
				array_shift($segment_values);
			}
			else
			{
				// the given route is not valid
				$this->_is_valid = false;

				$this->_segments[$name] = false;
				array_shift($segment_values);
			}
		}

		// Any remaining segments are parameters
		$this->_parameters = $segment_values;
	}


	/**
	 * Cleans a segment.
	 *
	 * @param string $value						- The value to clean
	 * @param bool $canonical					- Don't encode for output
	 * @return string
	 */
	protected function cleanSegment($value, $canonical = false)
	{
		return vB_Friendly_Url::clean_fragment($value, $canonical);
	}


	/**
	 * Checks if the parsed route is valid.
	 *
	 * @return bool
	 */
	public function isValid()
	{
		// Ensure the route has been parsed
		$this->parseRoutePath();
		return $this->_is_valid;
	}


	/**
	 * Builds dynamic segment schemes.
	 */
	protected function buildSegmentScheme(){}


	/**
	 * Validates the segment scheme.
	 * This is done before any parsing to ensure a valid scheme was defined.
	 *
	 * @throws vB_Exception_Router
	 */
	protected function validateSegmentScheme()
	{
		if ($this->_validated_scheme)
		{
			return;
		}

		$this->_validated_scheme = true;

		if (empty($this->_segment_scheme) OR !is_array($this->_segment_scheme))
		{
			throw (new vB_Exception_Router('No valid segment schema defined in router'));
		}

		// Ensure default calues are et in the scheme
		foreach ($this->_segment_scheme AS $name => $segment)
		{
			if (!isset($this->_segment_scheme[$name]['default']))
			{
				throw (new vB_Exception_Router('The route segment \'' . $name . '\' has no default value'));
			}

			if (!isset($this->_segment_scheme[$name]['optional']))
			{

				$this->_segment_scheme[$name]['optional'] = false;
			}

			if ($this->_segment_scheme[$name]['optional'] AND (!is_array($this->_segment_scheme[$name]['values']) OR !sizeof($this->_segment_scheme[$name]['values'])))
			{
				throw (new vB_Exception_Router('The optional segment \'' . $name . '\' has no values defined'));
			}
		}
	}


	/**
	 * Validates a segment.
	 * The segment scheme is checked for constraints and boolean false is returned
	 * if the segment is not valid.
	 *
	 * Child classes can extend this if they use other validation methods.
	 *
	 * @throws vB_Exception_Router
	 *
	 * @param string $name						- The key name of the segment
	 * @param mixed $value						- The value to validate
	 * @return bool								- Success
	 */
	protected function validateSegment($name, $value)
	{
		// Ensure segment is defined
		if (!isset($this->_segment_scheme[$name]))
		{
			throw (new vB_Exception_Router('An invalid route segment was given for validation: \'' . $name . '\''));
		}

		if (defined('VB_API_CMS') AND VB_API_CMS === true)
		{
			global $VB_API_ROUTE_SEGMENT_WHITELIST;
			if ($VB_API_ROUTE_SEGMENT_WHITELIST)
			{
				if (is_array($VB_API_ROUTE_SEGMENT_WHITELIST[$name]) AND !in_array($value, $VB_API_ROUTE_SEGMENT_WHITELIST[$name]))
				{
					return false;
				}
			}
			else
			{
				return false;
			}
		}

		// If the segment scheme has values, only those values are allowed
		if (isset($this->_segment_scheme[$name]['values']))
		{
			if (!in_array($value, $this->_segment_scheme[$name]['values']))
			{
				return false;
			}
		}

		// Is valid
		return true;
	}


	/**
	 * Sets a segment value.
	 * Also checks if the route segments have been modified, and transforms segment
	 * values where needed.  Values given here should be considered valid and should
	 * only be transformed to boolean false if it can be successfully transformed
	 * back into a valid route path segment string.
	 *
	 * @param string $segment					- The name of the segment to update
	 * @param mixed $value						- The value to set the segment to
	 * @param bool $nomodify					- Don't flag the route object as modified
	 */
	protected function setSegment($segment, $value, $nomodify = false)
	{
		if (!isset($this->_segment_scheme[$segment]))
		{
			throw (new vB_Exception_Warning('A route segment value was set but the segment is undefined: \'' . $segment . '\''));
		}

		// Ensure the original route path was parsed first
		$this->parseRoutePath();

		// Update the segment
		if ($this->assignSegment($segment, $value))
		{
			if (!$nomodify)
			{
				$this->_modified = true;
				$this->_parameters = array();
			}
		}
	}


	/**
	 * Assigns a value to a segment.
	 * Useful for overiding.
	 *
	 * @param string $segment					- The name of the segment to update
	 * @param mixed $value						- The value to set the segment to
	 * @return bool								- Whether the segment was modified
	 */
	protected function assignSegment($segment, $value)
	{
		if (!isset($this->_segments[$segment]) OR $this->_segments[$segment] != $value)
		{
			$this->_segments[$segment] = $value;

			if ($this->_reset_params_on_change)
			{
				$this->_parameters = array();
			}

			return true;
		}

		return false;
	}


	/**
	 * Allows multiple segments to be set.
	 * The array given must be in the form segment => value.
	 *
	 * @param array mixed $segment_values		- The segment name => values to set
	 * @param bool $nomodify					- Don't flag the route object as modified
	 */
	public function setSegments($segment_values, $nomodify = false)
	{
		if (!is_array($segment_values))
		{
			throw (new vB_Exception_Router('The values passed to route::setSegments are not an array'));
		}

		if (empty($segment_values))
		{
			return;
		}

		foreach ($segment_values AS $segment => $value)
		{
			$this->setSegment($segment, $value, $nomodify);
		}
	}


	/**
	 * Inflate dynamic segments to canonical values.
	 */
	public function inflateSegments(){}



	/*Controller====================================================================*/

	/**
	 * Returns the response for the route.
	 * The default implementation delegates the controller class specified by
	 * vB_Route::$_default_controller_class.  Child implementations may vary.
	 *
	 * @return string							- The response
	 */
	public function getResponse()
	{
		if (!$this->isValid())
		{
			throw (new vB_Exception_404());
		}

		if ($this->_default_controller_class)
		{
			$controller = new $this->_default_controller_class($this->_parameters);
		}
		else
		{
			throw (new vB_Exception_Route('No default controller or getResponse() defined for route \'' . get_class($this) . '\''));
		}

		return $controller->getResponse();
	}

	public function assertSubdirectoryUrl()
	{
	}

	/**
	 * Verify Friendly URL
	 * Ensures the requested URL was in the correct format according to the
	 * friendlyurl option.  If not, throw a 301 to the correct route.
	 */
	public function assertFriendlyUrl()
	{
		// API don't need to redirect
		if (defined('VB_API') AND VB_API === true)
		{
			return;
		}

		// Only redirect on GET
		if ('GET' != $_SERVER['REQUEST_METHOD'])
		{
			return;
		}

		// If this route isn't valid then we'll be 404'ing anyway
		if (!$this->isValid())
		{
			return;
		}

		// If we don't have an entry path then there's nothing to do
		if (!($request_path = vB_Router::getEntryPath()))
		{
			return;
		}

		// Allow hooks to handle non canonical urls
		($hook = vBulletinHook::fetch_hook('friendlyurl_redirect_canonical_route')) ? eval($hook) : false;

		// Check if we should be enforcing the canonical url
		if (vB_Friendly_Url::CANON_OFF == vB::$vbulletin->options['friendlyurl_canonical'])
		{
			return;
		}

		// Only redirect guests and search engines
		if (vB::$vbulletin->userinfo['userid'] AND !vB::$vbulletin->options['friendlyurl_canonical_registered'])
		{
			return;
		}

		// Get the canonical path
		if (!isset($canonical_path))
		{
			$canonical_path = $this->getRoutePath(false, (vB_Friendly_Url::CANON_STRICT == vB::$vbulletin->options['friendlyurl_canonical']), true);
		}
		// Whether the request was canonical
		$canonical = true;

		// If no route path is specified then only rewrite can differ
		if ($request_path == VB_ROUTER_SEGMENT)
		{
			//This looks like a bug.  The second "==" should be an "AND".  This is based on the fact that how its written doesn't
			//make a lot of sense and the behavior with the change is more consistant.  However its a bug with senority at this
			//point and fixing it will change how urls behave.  For the time being leaving it alone is better than the risk of
			//changing it.
			if ((FRIENDLY_URL == FRIENDLY_URL_REWRITE) == (vB::$vbulletin->options['friendlyurl'] == FRIENDLY_URL_REWRITE))
			{
				return;
			}
		}

		// Check the Friendly URL method
		if (FRIENDLY_URL !== intval(vB::$vbulletin->options['friendlyurl']))
		{
			$canonical = false;
		}

		// Check URI
		if ($canonical AND (vB_Friendly_URL::CANON_STRICT == vB::$vbulletin->options['friendlyurl_canonical']))
		{
			if ($request_path != $canonical_path)
			{
				// request may have been in the current charset, try utf-8
				$request_path = to_utf8($request_path, vB::$vbulletin->userinfo['lang_charset']);

				if ($request_path != $canonical_path)
				{
					$canonical = false;
				}
			}
		}

		// Redirect if incorrect
		if (!$canonical)
		{
			// Get the raw redirect url
			$url = $this->getCurrentURL(null, null, '', false, true);

			// add any query vars
			$vars = $_GET;
			unset($vars[vB::$vbulletin->options['route_requestvar']]);


			if (!empty($vars))
			{
				$url .= (strpos($url, '?') ? '&' : '?') . urlimplode($vars, false, true);
			}

			//do a quick check to ensure that we aren't trying to redirect to the url
			//we came in on.  This is needed primarily because of a special case where the
			//friendly url logic doesn't correctly detect the rewrite URL version of the
			//incoming link and will attempt to redirect because they don't match.
			$url = create_full_url($url);
			if ($url != VB_URL_CLEAN)
			{
				// redirect to the canonical url
				exec_header_redirect($url, 301);
			}
		}
	}



	/*Accessors=====================================================================*/

	/**
	 * Sets the parameters for the controller.
	 *
	 * NOTE: If a new controller is required then it should be set before any new
	 * parameters for that controller as the parameters should reset when changing
	 * the controller.
	 *
	 * NOTE: Parameters are indexed numerically.
	 * @see setParameter()
	 *
	 * @param array mixed $params				- The parameters for the current controller
	 */
	public function setParameters($params)
	{
		$params = (array)$params;

		if ($this->_parameters !== $params)
		{
			$this->_parameters = array_values($params);
			$this->_modified = true;
		}
	}


	/**
	 * Sets a single parameter value based on it's index.
	 *
	 * NOTE: Parameters are indexed numerically and cannot be named as the route and
	 * router are unaware of the controller that will be resolved.
	 *
	 * For this reason, all client code should be considered 'controller' aware, ie
	 * they should add parameter values based on the requirement of the concrete
	 * controller classes, and in the correct order.
	 *
	 * @param int $index						- The index of the parameter
	 * @param mixed $value						- The parameters value
	 */
	public function setParameter($index, $value)
	{
		if (!isset($this->_parameters[$index]) OR $this->_parameters[$index] != $value)
		{
			$this->_parameters[$index] = $value;
			$this->_modified = true;
		}
	}


	/**
	 * Gets all of the currently set parameters.
	 *
	 * NOTE: It is the responsibility of the delegated controller to request and
	 * validate the parameters.
	 *
	 * @return array							- The currently set parameters.
	 */
	public function getParameters()
	{
		// Ensure the route is valid
		if (!$this->isValid())
		{
			throw (new vB_Exception_Router('A route parameter value was requested but the route is invalid'));
		}

		return $this->_parameters;
	}


	/**
	 * Gets a single parameter value based on it's index.
	 *
	 * NOTE: Parameters are numerically indexed.
	 * @see setParameter()
	 *
	 * @param int $index						- The parameter to get
	 * @return mixed							- The value of the parameter
	 */
	public function getParameter($index)
	{
		// Ensure the route is valid
		if (!$this->isValid())
		{
			throw (new vB_Exception_Router('A route parameter value was requested but the route is invalid'));
		}

		if (!isset($this->_parameters[$index]))
		{
			return NULL;
		}

		return $this->_parameters[$index];
	}


	/**
	 * Gets the value of a defined segment.
	 * If the original route path has not yet been parsed then it will be parsed the
	 * first time a segment value is requested.
	 *
	 * The possible segment names and default values should be defined in
	 * $_segment_scheme.
	 * @see $_segments, $_segment_scheme
	 *
	 * @param string $segment					- The name of the segment to get
	 * @return mixed							- The current value of the segment
	 */
	public function __get($segment)
	{
		// Check the segment is defined
		if (!isset($this->_segment_scheme[$segment]))
		{
			throw (new vB_Exception_Router('A route segment value was requested but the segment is undefined: \'' . $segment . '\''));
		}

		// Ensure the route is valid
		if (!$this->isValid())
		{
			throw (new vB_Exception_Router('A route segment value for \'' . $segment . '\' was requested but the route is invalid'));
		}

		return $this->_segments[$segment];
	}


	/**
	 * Sets the value of a segment.
	 * If the value is not valid then an exception is thrown.
	 *
	 * @param string $segment					- The name of the segment
	 * @param mixed $value						- The value to set it to
	 */
	public function __set($segment, $value)
	{
		if (!$this->validateSegment($segment, $value))
		{
			throw (new vB_Exception_Router('The route segment \'' . $segment . '\' was set with an invalid value: \'' . $value . '\''));
		}

		$this->setSegment($segment, $value);
	}



	/*Rerouting=====================================================================*/

	/**
	 * Sets a reroute message.
	 *
	 * @param string $message
	 */
	public function setRerouteMessage($message)
	{
		self::$_reroute_message = $message;
	}


	/**
	 * Returns the reroute message if one is set.
	 *
	 * @return string
	 */
	public function getRerouteMessage()
	{
		return self::$_reroute_message;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 29650 $
|| ####################################################################
\*======================================================================*/