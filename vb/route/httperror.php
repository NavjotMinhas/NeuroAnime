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
 * Alternate Error Route for simple Http Errors.
 * Useful for ajax and other non xhtml responses.
 * @see vB_Route_Error
 *
 * @author vBulletin Development Team
 * @version $Revision: 29533 $
 * @since $Date: 2009-02-12 16:00:09 +0000 (Thu, 12 Feb 2009) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_Route_HttpError extends vB_Route_Error
{
	/*Properties====================================================================*/

	/**
	 * A default controller class.
	 *
	 * @var string
	 */
	protected $_default_controller_class = 'vB_Controller_HttpError';



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
		return vB_Route::create('vB_Route_HttpError')->getCurrentURL($segments);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 30528 $
|| ####################################################################
\*======================================================================*/