<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin Blog 4.1.5 Patch Level 1 - Licence Number VBF1F15E74
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2011 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

function cleanAPIName($name)
{
	return preg_replace('/[^a-z0-9_.]/', '', $name);
}

// #############################################################################
/**
 * Load API method definitions
 *
 * @param string $scriptname vBulletin core script name (e.g. forum = /forum.php)
 * @param string $do Action name (e.g. edit)
 * @param int $version API version
 * @param bool $updatedo When the function is called within another method file, whether to update $do so that API will load another action.
 * @return string Final vBulletin core script name to load.
 */
function loadAPI($scriptname, $do = '', $version = 0, $updatedo = false)
{
	static $internalscriptname;
	global $VB_API_WHITELIST, $VB_API_WHITELIST_COMMON, $VB_API_ROUTE_SEGMENT_WHITELIST;
	global $VB_API_REQUESTS;

	if (!$version)
	{
		$version = $VB_API_REQUESTS['api_version'];
	}

	$scriptname = cleanAPIName($scriptname);

	// Setup new API
	$internalscriptname = $scriptname;
	if ($updatedo)
	{
		$_REQUEST['do'] = $_GET['do'] = $_POST['do'] = $do;
	}

	$do = cleanAPIName($do);
	$version = intval($version);
	$access = false;
	// Check if the api method has been defined in versions
	for ($i = 1; $i <= $version; $i++)
	{
		$api_filename = CWD_API . '/' . $i . '/' . $scriptname . (($do AND !VB_API_CMS)?'_' . $do:'') . '.php';
		if (file_exists($api_filename))
		{
			$access = true;
			require_once $api_filename;
		}
	}

	// Still don't have the api file
	if (!$access)
	{
		if (!headers_sent())
		{
			header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
		}
		die();
	}

	return $internalscriptname;
}

// #############################################################################
/**
 * Load common whitelist definitions
 *
 * @param int $version API version
 * @return array common whitelist
 */
function loadCommonWhiteList($version = 0)
{
	global $VB_API_WHITELIST_COMMON, $VB_API_REQUESTS;
	if (!$version)
	{
		$version = $VB_API_REQUESTS['api_version'];
	}

	for ($i = 1; $i <= $version; $i++)
	{
		$filename = CWD_API . '/commonwhitelist_' . $i . '.php';
		if (file_exists($filename))
		{
			require $filename;
		}
	}
	return $VB_API_WHITELIST_COMMON;
}


// #############################################################################
/**
 * Print API error in JSON.
 *
 * @param string $errorid Unique (to existed phrases names) error ID of the error
 * @param string $errormessage Human friendly error message.
 * @return void
 */
function print_apierror($errorid, $errormessage = '')
{
	echo json_encode(array('response' => array(
			'errormessage' => array(
				$errorid, $errormessage
			)
		)
	));

	exit;
}


// #############################################################################
/**
 *  Build message_plain
 *
 * @global <type> $VB_API_REQUESTS
 * @param <type> $message String to be built
 * @return <type> plain-lized message
 */
function build_message_plain($message)
{
	global $VB_API_REQUESTS;

	$newmessage = strip_bbcode($message, false, false, true, false, true);

	if ($VB_API_REQUESTS['api_version'] > 1)
	{
		$regex = '#\[(quote)(?>[^\]]*?)\](.*)(\[/\1\])#siU';
		$newmessage = preg_replace($regex, "<< $2 >>", $newmessage);
	}

	return $newmessage;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 34655 $
|| ####################################################################
\*======================================================================*/
