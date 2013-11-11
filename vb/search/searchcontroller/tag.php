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
 * @author Ed Brown, vBulletin Development Team
 * @version $Revision: 28678 $
 * @since $Date: 2009-02-20 16:54:12 +0000 (Wed, 03 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */

require_once (DIR . '/vb/search/results.php');
require_once (DIR . '/vb/search/searchcontroller.php');

/**
*	Tag Searcher .
*
* Defines the interface that the search implementation needs to provide.
* All search controllers should inherit from this class.
*
* @package vBulletin
* @subpackage Search
*/
class vb_Search_SearchController_Tag extends vB_Search_SearchController
{
	/**
   * @see vB_Search_Controller
	 */
	public function get_supported_filters($contenttype)
	{
		return $this->filters;
	}

	/**
   * @see vB_Search_Controller
	 */
	public function get_supported_sorts($contenttype)
	{
		return $this->sorts;
	}

	/**
	 * Fetch the search results
	 *
	 * This returns the raw results from the search implementation.  The search implementation must
	 * only return items that match the search filter.  It must return all such items to which the
	 * searching user
	 *
	 * @param user The user performing the search.  Intended to allow search implementations
	 * 	to perform a rough filter of search results based on permissions
	 * @param criteria This is a vB_Search_Results object. We just use the tag,
	 * @return array array of results of the form array(Content Type, Content ID).  This is not
	 * 	an associative array to reduce (hopefully) the size of the resultset for large return
	 *	values
	 */
	public function get_results($user, $criteria)
	{
		//for now we are ignoring the rights, therefore the user doesn't matter.
		// and we get exactly one tag on which to search. Later we can get fancier.
		global $vbulletin;


		$filters = $criteria->get_equals_filters();

		//contenttype is special
		$types = array();
		
		if (isset($filters['contenttype']))
		{
			$types = $filters['contenttype'];
			unset($filters['contenttype']);
		}
		$equals = $criteria->get_equals_filters();
		$results = array();
		if (array_key_exists('tag', $equals))  
		{
			$sql = "
				SELECT contenttypeid, tagcontent.contentid, tagcontent.contentid as threadid
				FROM ". TABLE_PREFIX . "tagcontent as tagcontent
				WHERE tagid = " . $equals['tag'] . " 
				ORDER BY dateline DESC 
				LIMIT 20000";
			
			$rst = $vbulletin->db->query_read($sql); 
			while ($row = $vbulletin->db->fetch_row($rst))
			{
				$results[] = $row;
			}
		}
		
		return $results;
	}
	private $sorts = array('dateline', 'contenttypeid', 'contentid');
	private $filters = array('tagid', 'contenttypeid', 'dateline');
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/
