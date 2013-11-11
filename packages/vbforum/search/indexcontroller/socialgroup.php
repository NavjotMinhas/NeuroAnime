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
 * @author Kevin Sours
 * @version $Revision: 28678 $
 * @since $Date: 2008-12-03 16:54:12 +0000 (Wed, 03 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */

require_once (DIR."/vb/search/core.php");

/**
 * Index Controller for Social Group
 *
 * @package vBulletin
 * @subpackage Search
 */
class vBForum_Search_IndexController_SocialGroup extends vB_Search_IndexController
{
	public function __construct()
  {
  	$this->contenttypeid = vB_Search_Core::get_instance()->get_contenttypeid("vBForum", "SocialGroup");
  }

	public function get_max_id()
	{
		global $vbulletin;
		$row = $vbulletin->db->query_first_slave("
			SELECT max(groupid) AS max FROM " . TABLE_PREFIX . "socialgroup"
		);
		return $row['max'];
	}

	/**
	 * Index group message
	 *
	 * @param int $id
	 */
	public function index($id)
	{
		global $vbulletin;
		$row = $vbulletin->db->query_first($this->get_query("socialgroup.groupid = " . intval($id)));

		vB_Search_Core::get_instance()->get_core_indexer()->index($this->record_to_indexfields($row));
	}

	/**
	 * Index group message range
	 *
	 * @param int $start
	 * @param int $end
	 */
	public function index_id_range($start, $end)
	{
		global $vbulletin;
		$set = $vbulletin->db->query(
			$this->get_query("socialgroup.groupid >= " . intval($start) . 
				" AND socialgroup.groupid <= " . intval($end)));

		$indexer = vB_Search_Core::get_instance()->get_core_indexer();
		while ($row = $vbulletin->db->fetch_array($set))
		{
			$indexer->index($this->record_to_indexfields($row));
		}
		$vbulletin->db->free_result($set);
	}

	private function get_query($filter)
	{
		return "
			SELECT socialgroup.groupid, socialgroup.creatoruserid, socialgroup.socialgroupcategoryid, 
				socialgroup.name, socialgroup.description, socialgroup.dateline, socialgroup.lastdiscussion, 
				socialgroup.lastupdate, user.username
			FROM " . TABLE_PREFIX . "socialgroup AS socialgroup JOIN
				" . TABLE_PREFIX . "user AS user ON socialgroup.creatoruserid = user.userid
			WHERE $filter
		";	
	}

	/**
	 * Hard delete social group info from index
	 *
	 * @param id of social grouop to delete
	 * @return boolean
	 */
	public function delete_socialgroup($id)
	{
		return $this->delete($id);
	}

	/**
	 * Convert the basic table row to the index fieldset
	 *
	 * @param array $record
	 * @return return index fields
	 */
	private function record_to_indexfields($record)
	{
		$fields['contenttypeid'] = $this->get_contenttypeid();
		$fields['id'] = $record['groupid'];
		$fields['groupid'] = 0;
		$fields['dateline'] = $record['dateline'];
		$fields['keywordtext'] = $record['description'];
		$fields['title'] = $record['name'];
		$fields['userid'] = $record['creatoruserid'];
		$fields['username'] =$record['username'];

		return $fields;
	}

	protected $contenttypeid;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/
