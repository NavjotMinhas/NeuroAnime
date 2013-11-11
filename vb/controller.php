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
 * Base Page Controller
 *
 * 1. The page controller checks the evaluated route parameters to determine the
 *    appropriate action.
 * 2. The action modifies and gets all of the required data from the model classes.
 * 3. The controller then pushes the data to the appropriate view for the action and
 *    result.
 * 4. The controller finally renders the view output and returns it to vB for
 *    output.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 29157 $
 * @since $Date: 2009-01-16 18:35:08 +0000 (Fri, 16 Jan 2009) $
 * @copyright vBulletin Solutions Inc.
 */
abstract class vB_Controller
{
	/*Properties====================================================================*/

	/**
	 * The package that the controller belongs to.
	 *
	 * @var string
	 */
	protected $package;

	/**
	 * The class string id that identifies the controller.
	 *
	 * @var string
	 */
	protected $class;


	/**
	 * Parameters passed in with the route.
	 * This can be defined as an assoc array with string keys and default values.
	 * @see vB_Controller::assignParameters()
	 *
	 * @var array mixed
	 */
	protected $parameters = array();


	/**
	 * The requested action from the controller.
	 * Note: This is the resolved class action and not the user requested action.
	 * Any user requested actions should be resolved to a controller action as
	 * defined by vB_Controller::$actions.
	 *
	 * @var string
	 */
	protected $action;

	/**
	 * The action definitions for the controller.
	 * The action definition includes all of the possible actions for the
	 * controller.  If an action is requested with getResponse() and the action is
	 * valid, then a method with the name action$action will be called.
	 *
	 * @var array string => bool
	 */
	protected $actions = array();


	/**
	 * Wol info for the controller's actions.
	 * The info contains both the phrase to display for the user's action, and any
	 * route segment overrides.  This is useful to give a more appropriate link in
	 * some circumstances; for instance to link to view content if the user is
	 * editing it.
	 *
	 * The array should be in the form:
	 *
	 * 	array(
	 * 		action => array(array(phrasegroup, phrasekey), array(route_segment => value))
	 * 	)
	 *
	 * @var array mixed
	 */
	protected $wol_info = array();



	/*Initialisation================================================================*/

	/**
	 * Constructor.
	 * The constructor grabs the requested node segment and parameters.
	 *
	 * @param array mixed $parameters			- User requested parameters.
	 * @param string $action					- Optional action for the controller's getResponse()
	 */
	public function __construct(array $parameters = null, $action = false)
	{
		if (!isset($this->package) OR !isset($this->class))
		{
			throw (new vB_Exception_Critical('No package or class defined for controller ' . get_class($this)));
		}

		$this->parameters = $parameters;

		$this->action = $action;
	}


	/**
	 * Convenience factory method.
	 *
	 * @param string $package					- The package of the controller
	 * @param string $class						- The class segment of the controller
	 * @param array mixed $parameters			- Optional parameters
	 * @param string action						- Optional action
	 * @return vB_Controller
	 */
	public function create($package, $class, array $parameters = null, $action = false)
	{
		$class = $package . '_Controller_' . $class;
		return new $class($parameters, $action);
	}


	/**
	 * Assigns parameters.
	 *
	 * @param array mixed $parameters
	 */
	protected function setParameters(array $parameters = null)
	{
		$this->parameters = $parameters;
	}


	/**
	 * Main entry point for the controller.
	 * Performs all necessary controller related tasks to evaluate, render and
	 * return the page output.
	 *
	 * The controller's getResponse is responsible for catching and handling any non
	 * critical exceptions; and handling page caching where applicable.
	 *
	 * By default an action is resolved and called, however this system can be
	 * completely overridden by child implementations.
	 *
	 * @return string							- The final page output
	 */
	public function getResponse()
	{
		$method = 'action' . $this->action;

		if (!$this->action)
		{
			throw (new vB_Exception_Critical('No action requested'));
		}

		if (!in_array($this->action, $this->actions))
		{
			throw (new vB_Exception_404('Action not found: \'' . htmlspecialchars($this->action) . '\''));
		}

		if (!method_exists($this, $method))
		{
			throw (new vB_Exception_Critical('Action defined for controller but no matching method found: \'' . $method . '\''));
		}

		$this->authorizeAction();

		// Set Wolpath
		$this->resolveWolPath();

		return call_user_func_array(array($this, $method), $this->parameters);
	}


	/**
	 * Authorise the current user for the current action.
	 */
	protected function authorizeAction(){}


	/**
	 * Checks if a post request was intended for this controller.
	 *
	 * @return bool
	 */
	public function verifyPostId()
	{
		vB::$vbulletin->input->clean_array_gpc('p', array(
			'item_type' => vB_Input::TYPE_NOCLEAN,
			'item_class' => vB_Input::TYPE_STR
		));

		return (('controller' == vB::$vbulletin->GPC['item_type'])
				AND (vB::$vbulletin->GPC['item_class'] == vB_Types::instance()->getTypeKey($this->package, $this->class)));
	}


	/**
	 * Adds item id info to a view for submitting via post.
	 *
	 * @param vB_View $view
	 */
	protected function addPostId(vB_View $view)
	{
		$view->item_type = 'controller';
		$view->item_class = vB_Types::instance()->getTypeKey($this->package, $this->class);
	}


	/**
	 * Resolves the current Wolpath and assigns it to the session for saving.
	 */
	protected function resolveWolPath()
	{
		$route_path = vB_Router::getCurrentRoute();

		$wol_info_phrase = (isset($this->wol_info[$this->action][0]))
							? $this->wol_info[$this->action][0]
							: $this->wol_info['Default'][0];

		$wol_info_segments = (isset($this->wol_info[$this->action][1]))
							? $this->wol_info[$this->action][1]
							: $this->wol_info['Default'][1];

		if ($wol_info_segments)
		{
			$route = vB_Router::createRoute($route_path);
			$route->setSegments($wol_info_segments);

			$route_path = (string)$route;
		}

		if ($wol_info_phrase)
		{
			$action_phrase = implode('|', $wol_info_phrase);
		}
		else
		{
			$action_phrase = 'global|viewing';
		}

		$wol = array(
			urlencode($route_path),
			$action_phrase,
			urlencode($this->getTitle())
		);

		$wolpath = implode('|', $wol);

		vB::$vbulletin->session->set('location', 'route:' . $wolpath);
		define('WOLPATH', $wolpath);
	}


	/**
	 * Fetches the title of the page for Wol.
	 *
	 * @return string
	 */
	protected function getTitle()
	{
		if ($this->node)
		{
			return $this->node->getTitle();
		}
		else
		{
			return new vB_Phrase('vbcms', 'error_node_not_set');
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 29157 $
|| ####################################################################
\*======================================================================*/