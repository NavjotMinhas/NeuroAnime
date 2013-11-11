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
 * Default Error Route
 * Routing for the default error controller.
 *
 * When a 404, AccessDenied or Critical exception is thrown, the router will reroute
 * based on the path returned by the current router for 404, 403 and 500 errors. The
 * default implementation returns a route path to this router; which in turn calls a
 * response from vB_Controller_Error to provide a default error response.
 *
 * Routes should override their $_error_route_class property or the getXPath()
 * methods to override the default implementation.
 *
 * @author vBulletin Development Team
 * @version $Revision: 29533 $
 * @since $Date: 2009-02-12 16:00:09 +0000 (Thu, 12 Feb 2009) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_Route_Error extends vB_Route
{
	/*Properties====================================================================*/

	/**
	 * The segment scheme
	 *
	 * @see vB_Route::$_segment_scheme
	 *
	 * @var array mixed
	 */
	protected $_segment_scheme = array(
		'error'			=>	array (
			'optional'  =>  true,
			'values'	=>	array (
								'403',
								'404',
								'409',
								'500'
							),
			'default'	=>	'404'
		)
	);

	/**
	 * A default controller class.
	 *
	 * @var string
	 */
	protected $_default_controller_class = 'vB_Controller_Error';

	/**
	 * Default path.
	 *
	 * @var string
	 */
	protected $_default_path = '404';



	/*URL===========================================================================*/

	/**
	 * Returns a representative URL of a route.
	 * Optional segments and parameters may be passed to set the route state.
	 *
	 * @param array mixed $segments				- Assoc array of segment => value
	 * @param array mixed $parameters			- Array of parameter values, in order
	 * @return string							- The URL representing the route
	 */
	public static function getURL(array $segments = null, array $parameters = null, $absolute_path = false)
	{
		return vB_Route::create('vB_Route_Error')->getCurrentURL($segments);
	}


	/**
	 * Gets the 403 path.
	 *
	 * @return string
	 */
	public function get403Path()
	{
		return $this->_class_segment . '/403';
	}


	/**
	 * Gets the 404 path.
	 *
	 * @return string
	 */
	public function get404Path()
	{
		return $this->_class_segment . '/404';
	}


	/**
	 * Gets the 409 path
	 *
	 * @return string
	 */
	public function get409Path()
	{
		return $this->_class_segment . '/409';
	}


	/**
	 * Gets the 500 path.
	 *
	 * @return string
	 */
	public function get500Path()
	{
		return $this->_class_segment . '/500';
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 30528 $
|| ####################################################################
\*======================================================================*/