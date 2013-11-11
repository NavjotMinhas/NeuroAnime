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
 * Templater
 * Abstract base class / interface for templaters.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: 28674 $
 * @since $Date: 2008-12-03 12:56:57 +0000 (Wed, 03 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */
abstract class vB_Templater
{
	/*Properties====================================================================*/

	/**
	 * Pre notified results.
	 * Allows client code to prenotify the templater of results that will be
	 * rendered.  This allows the templater to defer fetching resources until render
	 * time so that it may optimise the fetching of multiple resources together.
	 *
	 * The values are arbitrary and reflect the purpose of a given view.  It is up
	 * to the concrete templater to interpret them as needed.  For example, a
	 * templater may use the result id's as template names.
	 *
	 * @var array string
	 */
	protected $notified_results = array();

	/**
	 * The content type of the content.
	 * This should generally be defined by the templater internally.
	 * @see vB_Templater::getContentType()
	 *
	 * @var string
	 */
	protected $content_type;

	/**
	 * The charset of the content.
	 * This should be specified by the client code that instantiates the templater.
	 *
	 * @var string
	 */
	protected $charset;



	/*Initialization================================================================*/

	/**
	 * Constructor.
	 * Allows a charset to be set.
	 *
	 * @param string $charset
	 */
	public function __construct($charset = false)
	{
		if ($charset)
		{
			$this->charset = $charset;
		}
	}



	/*Rendering=====================================================================*/

	/**
	 * Renders a view with the templater.
	 * Before rendering, the templater may check the prenotified results and
	 * optimise fetching before rendering.
	 *
	 * This should be overridden by child classes.
	 *
	 * @TODO: Should we handle mime type headers?
	 *
	 * @param vB_View $view						- The view to render
	 * @return string							- The rendering result
	 */
	public function renderView(vB_View $view)
	{
		if (sizeof($this->notified_results))
		{
			$this->prefetchResources();
		}

		return $this->render($view);
	}


	/**
	 * Performs the actual rendering of the view.
	 *
	 * @param vB_View $view						- The view to render
	 * @return string							- The rendering result
	 */
	abstract protected function render(vB_View $view);



	/*Headers=======================================================================*/

	/**
	 * Allows the charset to be specified.
	 *
	 * @param string $charset
	 */
	public function setCharset($charset)
	{
		$this->charset = $charset;
	}


	/**
	 * Fetches the appropriate content type.
	 *
	 * @return string
	 */
	public function getContentType()
	{
		if (!isset($this->content_type))
		{
			throw (new vB_Exception_Templater('No content type defined for templater ' . get_class($this)));
		}

		return $this->content_type;
	}


	/**
	 * Fetches the charset being used by the templater.
	 *
	 * @return string
	 */
	public function getCharset()
	{
		$charset = $this->charset;

		if ($charset == null)
		{
			if (!($charset = vB_Template_Runtime::fetchStyleVar('charset')))
			{
				$charset = vB::$vbulletin->userinfo['lang_charset'];
			}
		}

		return $charset;
	}



	/*Results=======================================================================*/

	/**
	 * Notifies the templater of a result that will be rendered later.
	 *
	 * @param string $resultid
	 */
	public function notifyResult($resultid)
	{
		$this->notified_results[$resultid] = true;
	}


	/**
	 * Notifies the templater of multiple results that will be rendered later.
	 *
	 * @param array string $resultids
	 */
	public function notifyResults(array $resultids)
	{
		if (sizeof($resultids))
		{
			foreach ($resultids AS $resultid)
			{
				$this->notifyResult($resultid);
			}
		}
	}


	/**
	 * Performs caching based on the prenotified results.
	 *
	 * This should be overridden by the child classes, and the notifed_results
	 * should be emptied.
	 */
	public function prefetchResources()
	{
		$this->notified_results = array();
	}



	/*Styles========================================================================*/

	/**
	 * Sets the style to use for successive templates.
	 * Note: Some templaters may ignore styles and do nothing.  The styleid should be a
	 * unique identifier to abstract differently styled results.
	 *
	 * @param int $styleid						- The id of the style to use
	 */
	public function setStyle($styleid){}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28674 $
|| ####################################################################
\*======================================================================*/