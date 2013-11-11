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
 * Default error controller.
 * Error controller with default implementations for providing error responses for
 * 403 (Access Denied), 404 (File not Found) and 500 (Internal Server Error)
 * responses.
 *
 * @author vBulletin Development Team
 * @version $Revision: 29533 $
 * @since $Date: 2009-02-12 16:00:09 +0000 (Thu, 12 Feb 2009) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_Controller_Error extends vB_Controller
{
	/*Properties====================================================================*/

	/**
	 * The package that the controller belongs to.
	 *
	 * @var string
	 */
	protected $package = 'vB';

	/**
	 * The class string id that identifies the controller.
	 *
	 * @var string
	 */
	protected $class = 'Error';



	/*Initialization================================================================*/

	/**
	 * Constructor.
	 *
	 * @param array mixed $parameters			- User requested parameters.
	 * @param string $action					- Optional action for the controller's getResponse()
	 */
	public function __construct(array $parameters = null, $action = false)
	{
		parent::__construct($parameters, $action);

		vBCms_NavBar::prepareNavBar();
	}

	/**
	 * Main entry point for the controller.
	 *
	 * @return string							- The final page output
	 */
	public function getResponse()
	{
		// Register the templater to be used for XHTML
		vB_View::registerTemplater(vB_View::OT_XHTML, new vB_Templater_vB());

		$error = vB_Router::getSegment('error');

		// Resolve rerouted error
		$error = in_array($error, array('403', '404', '409', '500')) ? $error : '404';

		$current_page = $_SERVER['SCRIPT_NAME'] . ($_SERVER['SCRIPT_NAME'] == '' ? '' :
			'?' . $_SERVER['QUERY_STRING']);
		if ('403' == $error)
		{
			define('WOLPATH',  '403|cpglobal|403_error|' . new vB_Phrase('wol', 'viewing_no_permission_message'));
			vB::$vbulletin->session->set('location', $current_page);
			print_no_permission();
		}
		else if ('409' == $error)
		{
			$message = ($message = vB_Router::getRerouteMessage()) ? $message : new vB_Phrase('error', 'error_409_description', vB_Router::getInitialURL(), vB_Router::getBaseURL(), vB::$vbulletin->options['contactuslink']);
			define('WOLPATH',  '409|wol|' . new vB_Phrase('cpglobal', 'error') . "|$message");
			vB::$vbulletin->session->set('location', $current_page);
			standard_error($message);
		}
		else if ('500' == $error)
		{
			$message = new vB_Phrase('error', 'error_500_description', vB_Router::getInitialURL(), vB_Router::getBaseURL(), vB::$vbulletin->options['contactuslink']);
			define('WOLPATH',  '500|wol|' . new vB_Phrase('cpglobal', 'error') . "|$message");
			vB::$vbulletin->session->set('location', $current_page);
			standard_error($message);
		}
		else
		{
			$message = new vB_Phrase('error', 'error_404_description', vB_Router::getBaseURL(), vB::$vbulletin->options['contactuslink']);
			define('WOLPATH',  '404|wol|' . new vB_Phrase('cpglobal', 'error') . "|$message");
			vB::$vbulletin->session->set('location', $current_page);
		}

		// Create the page view
		$page_view = new vB_View_Page('page');
		$title = new vB_Phrase('error', 'error_404');
		$page_view->setPageTitle($title);

		// Create the body view
		$error_view = new vB_View('error_message');

		$subtitle = ($title != ($subtitle = vB_Router::getRerouteMessage())) ? $subtitle : false;
		$error_view->title = $title;
		$error_view->subtitle = $subtitle;
		$error_view->message = new vB_Phrase('error', 'error_404_description', vB_Router::getBaseURL(), vB::$vbulletin->options['contactuslink']);

		$page_view->setBodyView($error_view);

		// Add general page info
		$page_view->setPageTitle($title);
		return $page_view->render();
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 29533 $
|| ####################################################################
\*======================================================================*/