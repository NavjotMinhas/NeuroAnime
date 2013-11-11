<?php if (!defined('VB_ENTRY')) die('Access denied.');
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.1.5 Patch Level 1 - Licence Number VBF1F15E74
|| # ---------------------------------------------------------------- # ||
|| # Copyright �2000-2011 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

/**
 * Default Page View
 * View for rendering a page in the legacy structure.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: $
 * @since $Date: $
 * @copyright vBulletin Solutions Inc.
 */
class vB_View_Page extends vB_View
{
	/*Properties====================================================================*/

	/**
	 * Evaluated legacy navbits from breadcrumbinfo.
	 *
	 * @var array string						- Assoc array of URL => Title
	 */
	protected $navbits;

	/**
	 * Array of breadcrumbinfo to use for navbits.
	 *
	 * @var array string
	 */
	protected $breadcrumbinfo = array();

	/**
	 * Result id's of inner views for prenotification.
	 *
	 * @var array mixed
	 */
	protected $_cache_results = array(
		'navbar_link',
		'nav_title',
		'nav_url',
		'navbar'
	);

	/**
	 * A title for the page.
	 *
	 * @var string
	 */
	protected $pagetitle;

	/**
	 * Extra views to render in the header.
	 *
	 * @var array vB_View
	 */
	protected $head_views = array();

	/**
	 * Extra views to render before the body.
	 *
	 * @var array vB_View
	 */
	protected $body_views = array();

	/**
	 * View to render as the page body.
	 *
	 * @var vB_View
	 */
	protected $body_view;



	/*Render========================================================================*/

	/**
	 * Prepare the widget block locations and other info.
	 */
	protected function prepareProperties()
	{
		// Prepare breadcrumb
		$this->prepareBreadcrumb();

		// Prepare header, navbar and footer
		$this->prepareLegacyPage();
	}


	/**
	 * Prepares the breadcrumb for the template.
	 */
	protected function prepareBreadCrumb()
	{
		// Set the navbits from the breadcrumbinfo
		$navbits = array();

		// Add the CMS to the breadcrumb
		// TODO: CMS should use its own View_Page class, and this line should go to that View.
		$home_url = vB::$vbulletin->options['site_tab_url'];
		if(!empty(vB::$vbulletin->session->vars['sessionhash']))
		{
				$home_url .= (stripos('?',  vB::$vbulletin->options['site_tab_url']) === false ? '?' : '&')
						."s=" . vB::$vbulletin->session->vars['sessionhash'];
		}
		$navbits[$home_url] = new vB_Phrase('vbcms', 'vbcms_title');

		// Hide Home nav link
		global $show;
		$show['hidehomenavbit'] = false;//true;

		foreach ($this->breadcrumbinfo AS $breadcrumb)
		{
			$navbits[$breadcrumb['link']] = $breadcrumb['title'];
		}
		$navbits[''] = $this->pagetitle;
		$this->navbits = construct_navbits($navbits);
	}


	/**
	 * Prepares the legacy output.
	 * Registers the globals required for the legacy output such as the header,
	 * footer and navbar.
	 */
	protected function prepareLegacyPage()
	{
		// Make the legacy globals available to the template
		global $headinclude, $header, $navbar, $footer, $style;

		$this->headinclude = $headinclude;
		$this->header = $header;
		$this->footer = $footer;
		$this->style = $style;

		$this->_properties['pagetitle'] = (vB::$vbulletin->options['cmstitle'] ? vB::$vbulletin->options['cmstitle'] : vB::$vbulletin->options['bbtitle']) . ' - ' . $this->pagetitle;
		$this->_properties['head_views'] = $this->head_views;
		$this->_properties['body_views'] = $this->body_views;
		$this->_properties['body'] = $this->body_view;

		$this->base_url = vB_Router::getBaseURL();

		// Add the navbar as a view
		$this->navbar = new vB_View_NavBar('navbar');
		$this->navbar->navbits = $this->navbits;
		unset($this->navbits);

		$this->headinclude_bottom =  new vB_View('headinclude_bottom');

	}



	/*Accessors=====================================================================*/

	/**
	 * Sets the breadcrumbinfo.
	 * The breadcrumbinfo is translated to navbits in the legacy page structure.
	 *
	 * @return string
	 */
	public function setBreadcrumbInfo(array $info)
	{
		$this->breadcrumbinfo = $info;
	}


	/**
	 * Sets the page title.
	 *
	 * @param string $pagetitle
	 */
	public function setPageTitle($pagetitle)
	{
		$this->pagetitle = $pagetitle;
	}


	/**
	 * Adds a head view.
	 * Head views are rendered in the output header.
	 *
	 * @param vB_View $view
	 */
	public function addHeadView($view)
	{
		$this->head_views[] = $view;
	}


	/**
	 * Adds a body view.
	 * Body views are rendered in the output body, before vB_View_Page::$body.
	 *
	 * @param vB_View $view
	 */
	public function addBodyView($view)
	{
		$this->body_views[] = $view;
	}


	/**
	 * Sets the body view.
	 * The body view is rendered in the output body.
	 *
	 * @param vB_View $view
	 */
	public function setBodyView($view)
	{
		$this->body_view = $view;
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28709 $
|| ####################################################################
\*======================================================================*/