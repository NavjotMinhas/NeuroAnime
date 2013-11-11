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

error_reporting(E_ALL & ~E_NOTICE);

// #############################################################################
/**
* Checks whether or not an administrator can post announcements
*
* @param	integer	Forum ID
*
* @return	integer	The return value of this function is really rubbish... who wrote this?
*/
function fetch_announcement_permission_error($forumid)
{
	global $vbulletin, $phrase;

	if ($forumid == -1 AND !($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions['ismoderator']))
	{
		return 1;
	}
	else if ($forumid != -1 AND !can_moderate($forumid, 'canannounce'))
	{
		return 2;
	}

	return 0;
}

// #############################################################################
/**
* Returns the phrase name for an error message
*
* @param	integer	Magic error number
*
* @return	string
*/
function fetch_announcement_permission_error_phrase($errno)
{
	global $vbphrase;
	switch($errno)
	{
		case 1:
			return 'you_do_not_have_permission_global';
			break;
		case 2:
			return 'you_do_not_have_permission_forum';
			break;
		default:
			return construct_phrase($vbphrase['unknown_error'], $errno);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>