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
require_once (DIR . '/vb/legacy/forum.php');

/**
 * Enter description here...
 *
 * @package vBulletin
 * @subpackage Search
 */
class vBForum_Search_Result_Forum extends vB_Search_Result
{

	public static function create($id)
	{
		$result = new vBForum_Search_Result_Forum();
		$result->forum = vB_Legacy_Forum::create_from_id($id);
		return $result;
	}

	protected function __construct() {}


	public function get_contenttype()
	{
		return vB_Search_Core::get_instance()->get_contenttypeid('vBForum', 'Forum');
	}

	public function can_search($user)
	{
		return $this->forum ? $this->forum->can_search($user) : false;
	}

	public function render($current_user, $criteria, $template_name = '')
	{
		global $vbulletin;

		if ('' == $template_name)
		{
			$template_name = 'search_results_forum';
		}
		$template = vB_Template::create($template_name);
		$template->register('forum', $this->forum->get_record());
		$template->register('dateformat', $vbulletin->options['dateformat']);
		$template->register('timeformat', $vbulletin->options['default_timeformat']);
		return $template->render();
	}


	/*** Returns the primary id. Allows us to cache a result item.
	 *
	 * @result	integer
	 ***/
	public function get_id()
	{
		if (isset($this->forum) AND ($forumid = $this->forum->get_field('forumid')) )
		{
			return $forumid;
		}
		return false;
	}

	private $forum;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/