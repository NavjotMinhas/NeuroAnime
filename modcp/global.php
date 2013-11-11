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

// identify where we are
define('VB_AREA', 'ModCP');

//this should technically be the name of the script file being run, but
//it really just needs to be set to something to pass the include checks
//and figuring out how to set it correctly from $_SERVER information is
//proving to be more trouble than it is  worth
define('VB_ENTRY', 'ModCP');
define('IN_CONTROL_PANEL', true);
if (!defined('VB_API'))
{
	define('VB_API', false);
}

if (!isset($phrasegroups) OR !is_array($phrasegroups))
{
	$phrasegroups = array();
}
$phrasegroups[] = 'cpglobal';

if (!isset($specialtemplates) OR !is_array($specialtemplates))
{
	$specialtemplates = array();
}
$specialtemplates[] = 'pluginlistadmin';

// ###################### Start functions #######################
chdir('./../');
define('CWD', (($getcwd = getcwd()) ? $getcwd : '.'));

require_once(CWD . '/includes/init.php');
require_once(DIR . '/includes/adminfunctions.php');
require_once(DIR . '/includes/modfunctions.php');
require_once(DIR . '/includes/functions_calendar.php');

// ###################### Start headers #######################
exec_nocache_headers();

if ($vbulletin->userinfo['cssprefs'] != '')
{
	$vbulletin->options['cpstylefolder'] = $vbulletin->userinfo['cssprefs'];
}

// ###################### Get date / time info #######################
// override date/time settings if specified
fetch_options_overrides($vbulletin->userinfo);
fetch_time_data();

// ############################################ LANGUAGE STUFF ####################################
// initialize $vbphrase and set language constants
$vbphrase = init_language();
$_tmp = NULL;
fetch_stylevars($_tmp, $vbulletin->userinfo);

$permissions = cache_permissions($vbulletin->userinfo, true);
$vbulletin->userinfo['permissions'] =& $permissions;
$cpsession = array();

$vbulletin->input->clean_array_gpc('c', array(
	COOKIE_PREFIX . 'cpsession' => TYPE_STR,
));

if (!empty($vbulletin->GPC[COOKIE_PREFIX . 'cpsession']))
{
	$cpsession = $db->query_first("
		SELECT * FROM " . TABLE_PREFIX . "cpsession
		WHERE userid = " . $vbulletin->userinfo['userid'] . "
			AND hash = '" . $db->escape_string($vbulletin->GPC[COOKIE_PREFIX . 'cpsession']) . "'
			AND dateline > " . iif($vbulletin->options['timeoutcontrolpanel'], intval(TIMENOW - $vbulletin->options['cookietimeout']), intval(TIMENOW - 3600))
	);

	if (!empty($cpsession))
	{
		$db->shutdown_query("
			UPDATE LOW_PRIORITY " . TABLE_PREFIX . "cpsession
			SET dateline = " . TIMENOW . "
			WHERE userid = " . $vbulletin->userinfo['userid'] . "
				AND hash = '" . $db->escape_string($vbulletin->GPC[COOKIE_PREFIX . 'cpsession']) . "'
		");
	}
}

define('CP_SESSIONHASH', $cpsession['hash']);

if ((!can_moderate() AND !can_moderate_calendar()) OR ($vbulletin->options['timeoutcontrolpanel'] AND !$vbulletin->session->vars['loggedin']) OR empty($vbulletin->GPC[COOKIE_PREFIX . 'cpsession']) OR $vbulletin->GPC[COOKIE_PREFIX . 'cpsession'] != $cpsession['hash'] OR empty($cpsession))
{
	print_cp_login();
}

($hook = vBulletinHook::fetch_hook('mod_global')) ? eval($hook) : false;

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 40651 $
|| ####################################################################
\*======================================================================*/
?>