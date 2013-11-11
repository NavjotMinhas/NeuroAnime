<?php
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

// ####################### SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// #################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'mobile');
define('CSRF_PROTECTION', true);

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array('register');

// get special data templates from the datastore
$specialtemplates = array();

// pre-cache templates used by all actions
$globaltemplates = array();

// pre-cache templates used by specific actions
$actiontemplates = array(
	'login' => array(
		'mobile_login'
	),
);

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');

if ($vbulletin->userinfo['styleid'] != $vbulletin->options['mobilestyleid_advanced']
	AND $vbulletin->userinfo['styleid'] != $vbulletin->options['mobilestyleid_basic'])
{
   exec_header_redirect($vbulletin->options['bburl']);
}

// #######################################################################
// ######################## START MAIN SCRIPT ############################
// #######################################################################

// ######################### start login page ############################
if ($_REQUEST['do'] == 'login')
{
	if ($vbulletin->userinfo['userid'])
	{
		// Already logged in
		exec_header_redirect($vbulletin->options['bburl']);
	}

	$show['forgetpassword'] = true;

	$templater = vB_Template::create('mobile_login');
		$templater->register_page_templates();
		$templater->register('url', $vbulletin->url);
	print_output($templater->render());
}

// ######################### start grid menu ############################
if ($_REQUEST['do'] == 'gridmenu')
{
	if (!$notifications_total) $notifications_total = '0';
	$templater = vB_Template::create('mobile_gridmenu');
		$templater->register_page_templates();
		$templater->register('notifications_total', $notifications_total);
		$templater->register('pageinfo_friends', array('tab' => 'friends'));
	print_output($templater->render());
}


// ######################### start notifications ############################
if ($_REQUEST['do'] == 'notifications')
{
	if ($notifications_total)
	{
		$show['notifications'] = true;
	}
	else
	{
		$show['notifications'] = false;
	}

	$templater = vB_Template::create('mobile_notifications');
		$templater->register_page_templates();
		$templater->register('notifications_menubits', $notifications_menubits);
		$templater->register('notifications_total', $notifications_total);
	print_output($templater->render());

}

// ######################### start agreement ############################
if ($_REQUEST['do'] == 'agreement')
{
	$templater = vB_Template::create('mobile_agreement');
	print_output($templater->render());

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 35803 $
|| ####################################################################
\*======================================================================*/