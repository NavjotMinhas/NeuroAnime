<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.1.5 Patch Level 1 - Licence Number VBF1F15E74
|| # ---------------------------------------------------------------- # ||
|| # Copyright 2000-2011 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

/*Legacy Bootstrap==================================================================*/

// Turn on error reporting
error_reporting(E_ALL & ~E_NOTICE);

// Legacy system constants
define('CSRF_PROTECTION', true);
define('VB_AREA', 'Forum');

// Don't use the usual WOLPATH resolution
define('SKIP_WOLPATH', 1);

// Legacy info
// TODO: Load the cms phrasegroup elsewhere
$phrasegroups = array('vbcms');

// Bootstrap to the legacy system
require('./includes/class_bootstrap.php');
$bootstrap = new vB_Bootstrap();
$bootstrap->datastore_entries = array('routes');
$bootstrap->bootstrap();


/*MVC Bootstrap=====================================================================*/

// Notify includes they are ok to run
if (!defined('VB_ENTRY'))
{
	define('VB_ENTRY', 1);
}

// Get the entry time
define('VB_ENTRY_TIME', microtime(true));

// vB core path
define('VB_PATH', realpath(dirname(__FILE__)) . '/');

// The package path
define('VB_PKG_PATH', realpath(VB_PATH . '../packages') . '/');

// Bootstrap the framework
require_once(VB_PATH . 'vb.php');
vB::init();

if (defined('VB_API') AND VB_API === true)
{
	// Force vB::$vbulletin->options['route_requestvar'] to 'r' for API.
	vB::$vbulletin->options['route_requestvar'] = 'r';
}

// Get routed response
print_output(vB_Router::getResponse());


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28749 $
|| ####################################################################
\*======================================================================*/