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
 * Index Controller for group Messages
 * @package vBulletin
 * @subpackage Search
 * @author Ed Brown, vBulletin Development Team
 * @version $Id: visitormessage.php 29897 2009-03-16 18:40:14Z ebrown $
 * @since $Date: 2009-03-16 11:40:14 -0700 (Mon, 16 Mar 2009) $
 * @copyright vBulletin Solutions Inc.
 */
require_once (DIR . "/vb/legacy/forum.php");
require_once (DIR."/vb/search/core.php");

/**
 * vBForum_Search_IndexController_VisitorMessage
 *
 * @package
 * @author ebrown
 * @copyright Copyright (c) 2009
 * @version $Id: visitormessage.php 29897 2009-03-16 18:40:14Z ebrown $
 * @access public
 */
class vBForum_Search_IndexController_VisitorMessage extends vB_Search_IndexController
{
	/**
	 * vBForum_Search_IndexController_VisitorMessage::__construct()
	 *  standard constructor, takes no parameters. We do need to set
	 *  the content type
	 */
	public function __construct()
	{
		$this->contenttypeid = vB_Search_Core::get_instance()->get_contenttypeid("vBForum", "VisitorMessage");
	}

	/**
	 * vBForum_Search_IndexController_VisitorMessage::get_max_id()
	 *
	 * @return integer : maximum existing vmid from the database
	 */
	public function get_max_id()
	{
		global $vbulletin;
		$row = $vbulletin->db->query_first_slave("
			SELECT MAX(visitormessage.vmid) AS max FROM " . TABLE_PREFIX
			. "visitormessage as visitormessage"
		);
		return $row['max'];
	}

	/**
	 * vBForum_Search_IndexController_VisitorMessage::index()
	 *
	 * @param integer $id : the record id to be indexed
	 */
	public function index($id)
	{
		global $vbulletin;
		//we just pull a record from the database.

		if ($rst = $vbulletin->db->query_read("SELECT visitormessage.* FROM "
			. TABLE_PREFIX . "visitormessage AS visitormessage WHERE vmid = $id")
			AND $row = $vbulletin->db->fetch_array($rst))
		{
			vB_Search_Core::get_instance()->get_core_indexer()->index($this->recordToIndexfields($row));
		}
	}

	/**
	 * vBForum_Search_IndexController_VisitorMessage::index_id_range()
	 * This will index a range of id's
	 *
	 * @param integer $start
	 * @param integer $finish
	 */
	public function index_id_range($start, $finish)
	{
		for ($id = $start; $id <= $finish; $id++)
		{
			$this->index($id);
		}
	}

	/**
	 * vBForum_Search_IndexController_VisitorMessage::getUserName()
	 *
	 * @param integer $userid
	 * @return string username : name of the user with that id.
	 */
	private function getUserName($userid)
	{
		global $vbulletin;

		if ($rst = $vbulletin->db->query_read("SELECT user.username FROM "
			. TABLE_PREFIX . "user AS user WHERE userid = $userid")
			AND $row = $vbulletin->db->fetch_row($rst))
		{
			return $row[0];
		}
		//if we got here the userid is invalid
		return '';
	}

	/**
	 * vBForum_Search_IndexController_VisitorMessage::recordToIndexfields()
	 * Converts the visitormessage table row to the indexable fieldset
	 *
	 * @param associative array $visitormessage
	 * @return associative array $fields= the fields populated to match the
	 *   searchcored table in the database
	 */
	private function recordToIndexfields($visitormessage)
	{
		$fields['contenttypeid'] = $this->contenttypeid;
		$fields['id'] = $visitormessage['vmid'];
		$fields['groupid'] = 0;
		$fields['dateline'] = $visitormessage['dateline'];
		$fields['userid'] = $visitormessage['postuserid'];
//todo move this field to the join rather than a seperate lookup
		$fields['username'] = $this->getUserName($visitormessage['postuserid']);
		$fields['ipaddress'] = $visitormessage['ipaddress'];
		$fields['keywordtext'] = $visitormessage['pagetext'];
		return $fields;
	}

	protected $contenttypeid;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 29897 $
|| ####################################################################
\*======================================================================*/
