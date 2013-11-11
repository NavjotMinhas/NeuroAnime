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
 * Core search indexer
 *
 * This represents the core index functionality for an indexer.
 * Note that this in completely independant of the contenttype as search
 * implementations need to be able to index contenttypes that my not have
 * existed when the implementation was written.
 *
 * This is the minimum that that a search implementation has to implement on
 * the index side.  However, most implementations will want to implement their
 * own indexers for efficiency reasons.
 *
 * @package vBulletin
 * @subpackage Search
 */
abstract class vB_Search_ItemIndexer
{
	/**
	 * Index an item based on a map of fieldname/value pairs
	 *
	 * The exact fields vary by content type, but must include the core search fields.
	 * These include the content type and item id.
	 *
	 * @param array $fields fields to index.
	 */
	public function index($fields){}

	/**
	 * Delete an item from the index.
	 *
	 * @param string the content type
	 * @param int the item id
	 */
	public function delete($contenttype, $id) {}
	
	/**
	*	Blow out the entire index.
	* 
	*/
	public function empty_index() {}

	/*
	 * A count used for the ranged indexed on a index_id_range() call 
	 */
	protected $range_indexed = 0;
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # SVN: $Revision: 28678 $
|| ####################################################################
\*======================================================================*/
