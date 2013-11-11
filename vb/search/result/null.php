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
 * @package vBulletin
 * @subpackage Search
 * @author Kevin Sours, vBulletin Development Team
 * @version $Revision: 28678 $
 * @since $Date: 2008-12-03 16:54:12 +0000 (Wed, 03 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */

require_once (DIR . '/vb/search/result.php');

/**
 * Enter description here...
 *
 * @package vBulletin
 * @subpackage Search
 */
class vB_Search_Result_Null extends vB_Search_Result
{
	public function __construct() {}

	/**
	 * Enter description here...
	 *
	 * @param unknown_type $user
	 * @return unknown
	 */
	public function can_search($user)
	{
		return false;
	}

	public function get_contenttype() 
	{
		return "";
	}

	/**
	 * Enter description here...
	 *
	 * @return unknown
	 */
	public function render($current_user, $criteria, $template_name = '')
	{
		return "";
	}

	//for real types, the type should be a singleton that we keep a reference 
	//to in every item
	public function get_type()
	{
	}

	public function get_group_item()
	{
		return new vB_Search_Result_Null();
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/

