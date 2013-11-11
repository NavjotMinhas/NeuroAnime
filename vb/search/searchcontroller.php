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


/**
*	Base class for the search controller.  
*
* Defines the interface that the search implementation needs to provide.
* All search controllers should inherit from this class.
*
* @package vBulletin
* @subpackage Search
*/
abstract class vB_Search_SearchController
{
	/**
	 */
//	abstract public function get_supported_filters($contenttype);

	/**
	 */
//	abstract public function get_supported_sorts($contenttype);

	/**
	 * Fetch the search results
	 *
	 * This returns the raw results from the search implementation.  The search implementation must
	 * only return items that match the search filter.  It must return all such items to which the 
	 * searching user 
	 *
	 * @param User The user performing the search.  Intended to allow search implementations
	 * 	to perform a rough filter of search results based on permissions
	 * @return array array of results of the form array(Content Type, Content ID).  This is not
	 * 	an associative array to reduce (hopefully) the size of the resultset for large return
	 *	values
	 */
	abstract public function get_results($user, $criteria);
	

	/**
	*	Get similar threads to a given thread title
	*
	* A hack to support similar thread functionality -- this used the search system 
	* previous and, in particular, the fulltext indexes on the thread table that 
	* we are trying to get rid of.  This allows us to move to the new search 
	* tables in the db search implementation and for other search implementations
	* to make use of whatever index they have to produce the results.
	*
	* Ideally this would work with the normal search interface or at least 
	* generalize to all content types, but the problem was noticed at the
	* last moment and some thought needs to be put into a more general implementation
	* (and there is no immediate requirement for one).
	*
	* Specialty search controllers can ignore this, it won't be used.
	*	A default implementation is provided that accesses the override hook.
	* Any custom implementation by a search package should respect the hook override.
	*
	*	@param string $threadtitle -- The title to match
	* @param int $threadid -- If provided this thread will be excluded from
	*   similar matches
	*/
	public function get_similar_threads($threadtitle, $threadid = 0)
	{
		$similarthreads = null;
		($hook = vBulletinHook::fetch_hook('search_similarthreads_fulltext')) ? eval($hook) : false;

		if ($similarthreads !== null)
		{
			return $similarthreads;
		}
		else 
		{
			return array();
		}
	}

	public function has_errors()
	{
		return (bool) count($this->errors);
	}

	public function get_errors()
	{
		return $this->errors;
	}
	
	protected function add_error($error)
	{
		$this->error[] = func_get_args(); 
	}

	private $errors = array();
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/

