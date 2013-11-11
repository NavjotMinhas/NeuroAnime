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
 * Router.
 * The router is an abstract class that should provide the functionality for all
 * implementations.
 *
 * The router can be used to create new route objects, track the initial and current
 * routes and delegate a controller for the given route.
 *
 * To change the functionality of routing, applications should implement a child of
 * the route class rather than the router, and register the route in the `route`
 * table, including the user request segment value (the first segment of a given route).
 *
 * @author vBulletin Development Team
 * @version $Revision: 41256 $
 * @since $Date: 2010-12-29 14:56:43 -0800 (Wed, 29 Dec 2010) $
 * @copyright vBulletin Solutions Inc.
 */
abstract class vB_Router
{
	/*Constants=====================================================================*/

	/**
	 * Different methods of obtaining the requested route.
	 * The route method is saved in options['friendlyurl']
	 */
	const METHOD_GET = 0;
	const METHOD_REQUEST = 1;
	const METHOD_PATHINFO = 2;
	const METHOD_REWRITE = 3;



	/*Properties====================================================================*/

	/**
	 * The initial requested path.
	 * Kept for debug and tracking.
	 * @see vB_Router::getResponse()
	 *
	 * @var string
	 */
	protected static $initial_path;

	/**
	 * The initial route path.
	 * This is resolved from the request and does not change after internal
	 * redirects.
	 * @see vB_Router::getEntryPath()
	 *
	 * @var string
	 */
	protected static $entry_path;

	/**
	 * The first delegated route.
	 * Kept for debug and tracking purposes.
	 *
	 * @var vB_Route
	 */
	protected static $initial_route;

	/**
	 * The currently dispatched router.
	 *
	 * @var vB_Route
	 */
	protected static $current_route;

	/**
	 * Available routes.
	 * Routes are registered in `route` upon product registration.  They may also
	 * be user configurable to define the class segment value of the user requested
	 * route path.
	 * The assoc array is in the form
	 * 	route class segment => user request
	 *
	 * @var array string
	 */
	protected static $routes = array();

	/**
	 * Available actions.
	 * Actions are registered in `action` upon product registration.  They may also
	 * be user configurable to define the action segment value of the user requested
	 * route path.
	 * Actions are used to map a segment to a method of the page controller.
	 * However, they are optional and do not have to be implemented in the route
	 * class` segment scheme.
	 * The assoc array is in the form
	 * 	controller class => array ( action method => user request)
	 *
	 * @var array
	 */
	protected static $actions = array();

	/**
	 * Available route actions.
	 * Array of the user actions per route and their corresponding controller and
	 * target method.
	 * The assoc array is in the form
	 * 	route class => array ( user request => array (
	 * 							'controller' => controller class,
	 * 							'method' => class method ) )
	 *
	 * @var array
	 */
	protected static $route_actions = array();

	/**
	 * The base URL.
	 * This is prepended to generated routing URLs
	 *
	 * @var string
	 */
	protected static $base_url;

	/**
	 * Relative URL modifier.
	 * If the current entry script is in a subdir it should apply a modifier using
	 * vB_Router::setRelativePath();
	 *
	 * @var string
	 */
	protected static $relative_url = '';

	/**
	 * A trace stack of dispatched route paths.
	 * Used to prevent non consecutive reroute recursion and for debug and tracking.
	 * @see vB_Router::getResponse()
	 *
	 * @var array string
	 */
	protected static $path_trace = array();

	/**
	 * Upper limit for non consecutive routing recursion.
	 * How many times the router will tolerate a route being re-requested non
	 * consecutively.  Used to prevent infinite rerouting loops.
	 *
	 * @var int
	 */
	protected static $infinite_reroute_threshold = 5;

	/**
	 * Whether the entry url has been validated.
	 *
	 * @var bool
	 */
	protected static $validated_friendly_url;



	/*Dispatch======================================================================*/

	/**
	 * Gets the response from a routed controller.
	 * A page controller is resolved from the given or user requested route path.
	 * The first segment of a route path is always the 'class segment' and
	 * determines which vB_Route is being requested.  Class segments are mapped to
	 * actual class names in the table `routes`.  The full path is given to the
	 * resolved vB_Route and a response is requested.
	 *
	 * Rerouting recursions are detected and an error thrown when a recursion is
	 * found.  If the requested route is identical to the previous route the error
	 * is thrown immediately.  If recursive requesting is not consecutive, the
	 * router checks the path trace for previous occurences of the specified route
	 * and throws an error if it has occured more than $infinite_reroute_threshold
	 * times.
	 *
	 * Note: It is up to the client code to catch and handle any exceptions.  The
	 * only exception that should intentionally be thrown to here is a reroute
	 * exception which will contain a new route to respond to.
	 * @see class vB_Route
	 *
	 * @param string | vB_Route	$route			- The route to delegate a controller from
	 * @return vB_Controller					- The resolved controller
	 */
	public static function getResponse($route = false)
	{
		// Save the initial path for debug and errors
		if (!isset(self::$initial_path))
		{
			self::$initial_path = (string)$route;
		}

		// If no route specified, get the user requested route path
		if (false === $route)
		{
			$route = self::getEntryPath();
		}

		// Get the appropriate route for the given path
		if (!($route instanceof vB_Route))
		{
			$route = self::newRoute($route);
		}

		if (!self::$validated_friendly_url)
		{
			//check that we have the correct location
			$route->assertSubdirectoryUrl();

			// Check the request matches the friendly url option
			$route->assertFriendlyUrl();

			self::$validated_friendly_url = true;
		}

		// Check if new route is the same as the current route
		if (self::$current_route AND (string)$route == (string)self::$current_route)
		{
			// prevent recursion
			throw (new vB_Exception_Critical('Rerouting recursion with: ' . (string)$route));
		}

		// Detect non consecutive rerouting recursion
		if (sizeof(self::$path_trace) > 1 AND sizeof(array_keys(self::$path_trace, (string)$route)) > self::$infinite_reroute_threshold)
		{
			// prevent recursion
			throw (new vB_Exception_Critical('Non Consecutive Rerouting recursion detected with: ' . (string)$route));
		}

		// Save initial route for logging and errors
		if (!self::$initial_route)
		{
			self::$initial_route = $route;
		}

		// Save current route for recursion prevention, logging and errors
		self::$current_route = $route;

		// Add route string to route trace to prevent non consecutive recursion
		self::$path_trace[] = (string)$route;

		// Try to delegate a controller and get it's response, catching any reroute exceptions
		try
		{
			// get the response from the routed page controller
			return $route->getResponse();
		}
		catch (vB_Exception_Reroute $e)
		{
			// get the rerouted route
			$route = self::newRoute($e->getRoutePath());

			// save the reroute message
			$route->setRerouteMessage($e->getMessage());

			// get the response for the new route
			return self::getResponse($route);
		}
		catch (vB_Exception $e)
		{
			if (vB::$vbulletin->debug)
			{
				throw ($e);
			}

			// get the error response from the current route
			return self::getResponse(self::$current_route->get500Path());
		}
	}


	/**
	 * Gets the requested path from the user request.
	 * How the route path is given by the user depends on the routing method chosen in options.
	 *
	 * @return string							- The full user requested route
	 */
	public static function getEntryPath()
	{
		if (self::$entry_path)
		{
			return self::$entry_path;
		}

		// Get the entry path from the request
		self::$entry_path = $_REQUEST[vB::$vbulletin->options['route_requestvar']];
		
		//we get some trailing &'s. Let's get rid of them
		if (substr(self::$entry_path, - 1) == '&')
		{
			self::$entry_path = substr(self::$entry_path, 0, strlen(self::$entry_path) - 1);
		}

		if (substr(self::$entry_path, -5) == '&amp;')
		{
			self::$entry_path = substr(self::$entry_path, 0, strlen(self::$entry_path) - 5);
		}

		// Prepend the router segment
		self::$entry_path = trim(VB_ROUTER_SEGMENT . '/' . self::$entry_path, '/');

		return self::$entry_path;
	}


	/**
	 * Creates a new route object based on the given route path.
	 * Route objects can be used to manipulate a route by segment and parameters
	 * and then compile a valid URL to that route.
	 *
	 * The route object can also delegate an appropriate page controller for the
	 * given path.
	 *
	 * @param string $route_path				- The route path for the new route
	 * @return vB_Route
	 */
	protected static function newRoute($route_path = false, $get_default = true)
	{
		if (!$route_path)
		{
			return $get_default ? self::getDefaultRoute() : false;
		}

		// Get the appropriate route class for the requested path
		if (!($route_class = self::getRouteClassFromPath($route_path)))
		{
			return $get_default ? self::getDefaultRoute(404) : false;
		}

		// Check if the resolved class is valid
		if (!class_exists($route_class))
		{
			return $get_default ? self::getDefaultRoute(500) : false;
		}

		return new $route_class($route_path);
	}


	/**
	 * Creates a route object from a route path.
	 *
	 * @param srting $route_path
	 * @return vB_Route
	 */
	public static function createRoute($route_path)
	{
		return self::newRoute($route_path, false);
	}


	/**
	 * Set the default route class as the route for this request.
	 *
	 * @param int $error						- Optionally use an http error code to get the corresponding path
	 */
	protected static function getDefaultRoute($error = false)
	{
		$route_class = self::getRouteClassFromSegment($default_route = vB::$vbulletin->options['default_route_segment']);

		if (!class_exists($route_class))
		{
			throw (new vB_Exception_Critical('The default route \'' . htmlspecialchars($default_route) . '\' is not valid'));
		}

		return new $route_class($error);
	}


	/**
	 * Returns the resolved base URL.
	 * This is prepended to internal URLs generated by routing.
	 * @see vB_Route::getURL()
	 *
	 * return string
	 */
	public static function getBaseRoutingURL($class_segment, $for_path = true, $method = false)
	{
		if (!self::$base_url)
		{
			if (vB::$vbulletin->options['vbcms_url'])
			{
				self::$base_url = trim(normalize_path(vB::$vbulletin->options['vbcms_url']), '/\\');
			}
			else
			{
				// don't use relative url if we're forcing bburl as the basepath
				$relative_url = (self::$relative_url AND !vB::$vbulletin->options['bburl_basepath']) ? self::$relative_url : false;

				self::$base_url = trim(vB::$vbulletin->input->fetch_basepath($relative_url), '/\\');
			}
		}

		$base_url = self::$base_url;

		$method = (false === $method) ? vB::$vbulletin->options['friendlyurl'] : $method;

		switch ($method)
		{
			case (self::METHOD_REQUEST):
				$base_url .= '/' . $class_segment . '.php' . ($for_path ? '?' : '');
				break;
			case (self::METHOD_GET):
				$base_url .= '/' . $class_segment . '.php' . ($for_path ? ('?' . vB::$vbulletin->options['route_requestvar'] . '=') : '');
				break;
			case (self::METHOD_PATHINFO):
				$base_url .= '/' . $class_segment . '.php';
			case (self::METHOD_REWRITE):
			default:
				$base_url .= '/';
				break;
		}

		return $base_url;
	}

	public static function getBaseRoutingRoot()
	{
		//we need to be able to get at the Routing URL without any decoration for some situations.
		//rather than try to break up the above function do anything that might introduce problems,
		//we can set the parameters to produce the results that we want.  If the above function 
		//changes this may need to change as well.
		return self::getBaseRoutingURL('', false, vB_Router::METHOD_REWRITE);
	}

	/**
	 * Gets the base URL of vBulletin
	 *
	 * @return string
	 */
	public static function getBaseURL()
	{
		//base directories *must* have trailing slash
		$base = vB::$vbulletin->options['bburl'];
		if ($base[-1] != '/')
		{
			$base .= '/';
		}

		return $base;
	}


	/**
	 * Sets a relative path to use for resolving the basepath.
	 * This is needed if the entry script is in a subdirectory, such as /admincp.
	 *
	 * @param $relpath
	 */
	public static function setRelativePath($relpath)
	{
		self::$relative_url = $relpath;
	}


	/*Routing=======================================================================*/

	/**
	 * Ensures the application routes and actions are loaded.
	 *
	 * @todo vBulletin is not a registered product so if any future apps use it then
	 * we should either register vBulletin or make the app query less strict.
	 * @todo Check if we can use a collection here for routes if it isn't too early
	 * in the application stack.  It shouldn't be as routing is now optional.
	 */
	protected static function assertRoutes()
	{
		// Check if we've already loaded the route info
		if (sizeof(self::$routes))
		{
			return true;
		}

		// Check datastore
		if (!(sizeof($routecache = vB::$vbulletin->routes)))
		{
			if (vB::$vbulletin->datastore->fetch(array('routes')))
			{
				$routecache = vB::$vbulletin->routes;
			}
		}

		if (sizeof($routecache))
		{
			self::$routes = $routecache['routes'];
			self::$actions = $routecache['actions'];
			self::$route_actions = $routecache['route_actions'];

			return true;
		}

		// Add framework level routes
		self::$routes['vB_Route_Error'] = 'error';
		self::$routes['vB_Route_HttpError'] = 'httperror';

		// Get routes and action from db and cache in datastore
		$result = vB::$db->query_read("
			SELECT	route.userrequest AS route_segment, route.class AS route_class, route.packageid AS route_package,
					action.packageid AS action_package, action.controller, action.useraction AS action_segment,
					action.classaction AS controller_action
			FROM " . TABLE_PREFIX . "route AS route
			LEFT JOIN " . TABLE_PREFIX . "action AS action
				ON action.routeid = route.routeid
		");

		while ($route = vB::$db->fetch_array($result))
		{
			// cache route
			if (vB_Types::instance()->packageEnabled($route['route_package']))
			{
				$route_class = vB_Types::instance()->getPackageClass($route['route_package']) . '_Route_' . $route['route_class'];
				self::$routes[$route_class] = $route['route_segment'];
			}
			else
			{
				continue;
			}

			// cache action
			if ($route['action_segment'])
			{
				if (vB_Types::instance()->packageEnabled($route['action_package']))
				{
					$controller = vB_Types::instance()->getPackageClass($route['action_package']) . '_Controller_' . $route['controller'];

					// add to actions
					if (!isset(self::$actions[$controller]))
					{
						self::$actions[$controller] = array();
					}
					self::$actions[$controller][$route['controller_action']] = $route['action_segment'];

					// add to per route actions
					if (!isset(self::$route_actions[$route_class]))
					{
						self::$route_actions[$route_class] = array();
					}
					self::$route_actions[$route_class][$route['action_segment']] = array('controller' => $controller,
																						 'action' => $route['controller_action']);
				}
			}
		}

		// Cache results
		$routecache = array('routes' => self::$routes, 'actions' => self::$actions, 'route_actions' => self::$route_actions);
		build_datastore('routes', serialize($routecache), 1);
	}


	/**
	 * Fetches the actions for a given route class.
	 *
	 * @param string $route_class
	 */
	public static function getActions($route_class)
	{
		self::assertRoutes();

		return (isset(self::$route_classes[$route_class]) ? self::$route_classes[$route_class]['actions'] : false);
	}


	/**
	 * Fetches the user request segment for the specified route class.
	 *
	 * @param string $route_class				- The full class name of the route
	 * @return string							- The corresponding user request segment
	 */
	public static function getRouteSegmentFromClass($route_class)
	{
		// Ensure the route list is loaded
		self::assertRoutes();

		return (isset(self::$routes[$route_class]) ? self::$routes[$route_class] : false);
	}


	/**
	 * Resolves the appropriate route class for a complete route path.
	 *
	 * @param string $route_path				- A route path to resolve the class from
	 * @return string							- The resolved route class
	 */
	public static function getRouteClassFromPath($route_path)
	{
		// Resolve the route from the requested path
		if ($route_path)
		{
			$segments = explode('/', $route_path, 2);
			$route_segment = $segments[0];

			return self::getRouteClassFromSegment($route_segment);
		}

		return false;
	}


	/**
	 * Fetches the appropriate route class for the route path class segment.
	 * The class segment of a route path is the first segment of the requested
	 * route.
	 *
	 * @param string $route_segment
	 * @return string
	 */
	protected static function getRouteClassFromSegment($route_segment)
	{
		// Ensure the route list is loaded
		self::assertRoutes();

		return ($key = array_search($route_segment, self::$routes)) ? $key : false;
	}


	/**
	 * Returns the initially requested path for routing.
	 *
	 * @return string
	 */
	public static function getInitialPath()
	{
		return self::$initial_path;
	}


	/**
	 * Returns the resolved path of the first dispatched route.
	 *
	 * @return string							- The route path of the initial route
	 */
	public static function getInitialRoute()
	{
		return (string)self::$initial_route;
	}


	/**
	 * Returns the path for the currently dispatched route.
	 *
	 * @return string							- The route path of the current route
	 */
	public static function getCurrentRoute()
	{
		return (string)self::$current_route;
	}



	/*Actions=======================================================================*/

	/**
	 * Returns an instance of a controller for the given route and user action.
	 *
	 * @param string $route_class				- The class name of the route
	 * @param string $user_action				- The action as requested by the user
	 * @param array mixed $parameters			- User requested parameters
	 * @return vB_Controller					- The resolved controller
	 */
	public static function getActionController($route_class, $user_action, $parameters = false)
	{
		self::assertRoutes();

		if (!isset(self::$route_actions[$route_class][$user_action]))
		{
			throw (new vB_Exception_Router('An action controller could not be found for the route \'' . $route_class .
											'\' and the action \'' . htmlspecialchars($user_action) . '\''));
		}

		$class = self::$route_actions[$route_class][$user_action]['controller'];
		$action = self::$route_actions[$route_class][$user_action]['action'];

		return new $class($parameters, $action);
	}


	/**
	 * Fetches the appropriate user action for a given controller and method.
	 * @see vBCms_Route::$actions
	 *
	 * @param string $controller_class			- The full class name of the controller
	 * @param string $action					- The required action, as defined by the controller
	 * @return string							- The route segment mapped to the requested action
	 */
	public static function getUserAction($controller_class, $action)
	{
		self::assertRoutes();

		if (!isset(self::$actions[$controller_class][$action]))
		{
			throw (new vB_Exception_Router('An action segment was requested for an undefined action \'' .
											htmlspecialchars($controller_class . '::' . $action)));
		}

		return self::$actions[$controller_class][$action];
	}


	/**
	 * Fetches all of the action segments for a given route class.
	 *
	 * @param string $route_class				- The full class name of the route
	 * @return array string						- An array of all valid action segments
	 */
	public static function getRouteActions($route_class)
	{
		self::assertRoutes();

		if (!isset(self::$route_actions[$route_class]))
		{
			throw (new vB_Exception_Router('Action segments were requested for an undefined route class \'' .
											htmlspecialchars($route_class) . '\''));
		}

		return array_keys(self::$route_actions[$route_class]);
	}



	/*URL===========================================================================*/

	/**
	 * Fetches the URL of the currently dispatched route.
	 *
	 * @return string
	 */
	public function getCurrentURL($query_string = false)
	{
		if (!self::$current_route)
		{
			throw (new vB_Exception_Router('Requesting current URL with no current route'));
		}

		return self::$current_route->getCurrentURL(null, null, $query_string);
	}


	/**
	 * Fetches the URL of the initial requested route.
	 *
	 * @return string
	 */
	public function getInitialURL()
	{
		if (!self::$initial_route)
		{
			throw (new vB_Exception_Router('Requesting initial URL with no initial route'));
		}

		return self::$initial_route->getCurrentURL();
	}



	/*Current Route=================================================================*/

	/**
	 * Gets a segment value from the current route.
	 * This should only be used by page controllers, or objects that are guaranteed
	 * to only be instantiated by fetching a router response.
	 *
	 * NOTE: The values returned are raw and it is the responsibility of the
	 * controller to validate them.
	 *
	 * @param string $segment					- The name of the segment to get
	 * @return mixed							- The value of the specified segment
	 */
	public static function getSegment($segment)
	{
		if (!isset(self::$current_route))
		{
			throw (new vB_Exception_Router('Segment value requested for current route but no current route has been resolved.'));
		}

		return self::$current_route->$segment;
	}


	/**
	 * Gets the parameters from the current route.
	 * This should only be used by page controllers, or objects that are guaranteed
	 * to only be instantiated by fetching a router response.
	 *
	 * NOTE: The values returned are raw and it is the responsibility of the
	 * controller to validate them.
	 *
	 * @return array mixed						- An array of the parameter values
	 */
	public static function getParameters()
	{
		if (!isset(self::$current_route))
		{
			throw (new vB_Exception_Router('Parameters requested for current route but no current route has been resolved.'));
		}

		return self::$current_route->getParameters();
	}


	/**
	 * Fetches the URL of the current route.
	 */
	public static function getURL(array $segments = null, array $parameters = null, $query_string = false)
	{
		if (!isset(self::$current_route))
		{
			throw (new vB_Exception_Router('URL requested for current route but no current route has been resolved.'));
		}

		return self::$current_route->getCurrentURL(null, null, $query_string);
	}


	/**
	 * Fetches the defined 403 path for the current route.
	 *
	 * @return string
	 */
	public static function get403Path($current_route = false)
	{
		// TODO: get 403 from default route
		if (!isset(self::$current_route))
		{
			if ($current_route)
			{
				return $current_route->get403Path();
			}

			throw (new vB_Exception_Router('403 path requested for current route but no current route has been resolved.'));
		}

		return self::$current_route->get403Path();
	}


	/**
	 * Fetches the defined 404 path for the current route.
	 *
	 * @return string
	 */
	public static function get404Path()
	{
		// TODO: Get 404 from default route
		if (!isset(self::$current_route))
		{
			throw (new vB_Exception_Router('404 path requested for current route but no current route has been resolved.'));
		}

		return self::$current_route->get404Path();
	}


	/**
	 * Fetches the defined 409 path for the current route.
	 *
	 * @return string
	 */
	public static function get409Path()
	{
		// TODO: Get 409 from default route
		if (!isset(self::$current_route))
		{
			throw (new vB_Exception_Router('409 path requested for current route but no current route has been resolved.'));
		}

		return self::$current_route->get409Path();
	}


	/**
	 * Fetches the defined 500 path for the current route.
	 *
	 * @return string
	 */
	public static function get500Path()
	{
		// TODO: get 500 from default route
		if (!isset(self::$current_route))
		{
			throw (new vB_Exception_Router('500 path requested for current route but no current route has been resolved.'));
		}

		return self::$current_route->get500Path();
	}


	/**
	 * Fetches any set reroute message from the current route.
	 *
	 * @return string
	 */
	public static function getRerouteMessage()
	{
		if (isset(self::$current_route))
		{
			return self::$current_route->getRerouteMessage();
		}

		return '';
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 41256 $
|| ####################################################################
\*======================================================================*/
