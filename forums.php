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

/**
 * If you want to move this file to the root of your website, change this
 * line to your vBulletin directory and uncomment it (delete the //).
 *
 * For example, if vBulletin is installed in '/forum' the line should
 * state:
 *
 *	define('VB_RELATIVE_PATH', 'forum');
 *
 * Note: You may need to change the cookie path of your vBulletin
 * installation to enable your users to log in at the root of your website.
 * If you move this file to the root of your website then you should ensure
 * the cookie path is set to '/'.
 *
 * See 'Admin Control Panel
 *	->Cookies and HTTP Header Options
 *	  ->Path to Save Cookies
 */

//define('VB_RELATIVE_PATH', 'forums');


// Do not edit.
if (defined('VB_RELATIVE_PATH'))
{
	chdir('./' . VB_RELATIVE_PATH);
}


/**
 * You can choose the default script here.  Uncomment the appropriate line
 * to set the default script.  Note: Only uncomment one of these, you must
 * add // to comment out the script(s) that you DO NOT want to use as your
 * default script.
 *
 * You can choose the default script even if you do not plan to move this
 * file to the root of your website.
 */

/**
 * Use the CMS as the default script:
 */

//require('content.php');


/**
 * Use the forum as the default script:
 */

require('forum.php');


/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 41267 $
|| ####################################################################
\*======================================================================*/