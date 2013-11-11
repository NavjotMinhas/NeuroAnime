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
 * CMS Navbar View
 * View for rendering the legacy navbar.
 * Wraps up some global assignments and uses the legacy template.
 *
 * @package vBulletin
 * @author vBulletin Development Team
 * @version $Revision: $
 * @since $Date: $
 * @copyright vBulletin Solutions Inc.
 */
class vB_View_NavBar extends vB_View
{
	/*Render========================================================================*/

	/**
	 * Prepare the widget block locations and other info.
	 */
	protected function prepareProperties()
	{
		// Legacy globals needed by navbar template
		$this->prepareLegacyGlobals();
	}


	/**
	 * Prepares the legacy output.
	 * Registers the globals required for the legacy output such as the header,
	 * footer and navbar.
	 */
	protected function prepareLegacyGlobals()
	{
		global $stylevar, $vbphrase, $vboptions, $session, $navbar_reloadurl, $show,
		$bbuserinfo, $pmbox, $notifications_total, $template_hook, $return_link, $notices,
		$foruminfo, $notifications_menubits, $ad_location;

		$globals = array(
			'stylevar' => $stylevar,
			'vbphrase' => $vbphrase,
			'vboptions' => $vboptions,
			'session' => $session,
			'navbar_reloadurl' => $navbar_reloadurl,
			'show' => $show,
			'bbuserinfo' => $bbuserinfo,
			'pmbox' => $pmbox,
			'notifications_total' => $notifications_total,
			'template_hook' => $template_hook,
			'return_link' => $return_link,
			'notices' => $notices,
			'foruminfo' => $foruminfo,
			'notifications_menubits' => $notifications_menubits,
			'ad_location' => $ad_location
		);

		$this->_properties = array_merge($this->_properties, $globals);

		$this->_properties['bbmenu'] = vB::$vbulletin->options['bbmenu'];
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28709 $
|| ####################################################################
\*======================================================================*/