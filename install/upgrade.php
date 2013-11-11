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

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);
ignore_user_abort(true);
chdir('./../');

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('NO_IMPORT_DOTS', true);
define('NOZIP', 1);
if (!defined('VB_AREA')) { define('VB_AREA', 'Upgrade'); }
define('TIMENOW', time());
if (!defined('VB_ENTRY')) { define('VB_ENTRY', 'upgrade.php'); }

require_once('./install/includes/language.php');

if (!function_exists('version_compare') OR version_compare(PHP_VERSION, '5.0.0', '<'))
{
	echo PHP4_ERROR;
	exit;
}

// ########################## REQUIRE BACK-END ############################
require_once('./install/includes/class_upgrade.php');
require_once('./install/init.php');
require_once(DIR . '/includes/functions.php');
require_once(DIR . '/includes/functions_misc.php');

if (function_exists('set_time_limit') AND !SAFEMODE)
{
	@set_time_limit(0);
}

$verify =& vB_Upgrade::fetch_library($vbulletin, $phrases, '', !defined('VBINSTALL'));

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 39181 $
|| ####################################################################
\*======================================================================*/
