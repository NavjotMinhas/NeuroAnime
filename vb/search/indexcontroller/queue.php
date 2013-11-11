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

require_once (DIR . "/vb/search/core.php");
require_once (DIR . '/vb/search/indexcontroller/queueprocessor.php');

/**
 * @package vbForum
 * @subpackage Search
 * @author Ed Brown, vBulletin Development Team
 * @version $Revision: 29553 $
 * @since $Date: 2009-02-17 16:54:12 +0000 (Wed, 03 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_Search_Indexcontroller_Queue
{
	/*********************
	 * We work in either of two modes. The default is to queue inserts and process
	 * them later. This allows good performance for the user.
	 * In this mode we don't actually do any indexing. We just write a record to the
	 * queue table,which is indexqueue.
	 * Later the cron job will scan the table and actually index the items.
	 * If the admin flag $vbulletin->config['search']['immediatewrites'] is set to 1
	 * then we process them immediately
	 * Normally we will be called static, i.e. "vb_Search_Indexcontroller_Queue::index();
	 *****/


	//ensure this is only called static
	private function __construct ()
	{

	}

/**
* vb_Search_Indexcontroller_Queue::indexQueue()
*
* Index an item based on a map of fieldname/value pairs
*
* @param string $package : the package which we are indexing
* @param string $contenttype : text string with the type of content
* @param string $operation: the index action, which will vary depending on the action.
*    usually it will just be "index"
* @param data : If we have fourth parameter we take it as an associative array of field values
* @return : boolean success indicator
*/
	public static function indexQueue($package, $contenttype, $operation)
	{
		$data = array_slice(func_get_args(), 3);
		global $vbulletin;
		$db = vB_Search_Core::get_db();
		//For now we need to compose an sql query. Parameters are not available.
		//First make sure we've got good data. If we don't have the three parameters

		if (isset($package))
		{
			$dbfields['package'] =  "'" . $db->escape_string($package) . "'";
		}
		else
		{
			return(false);
		}

		if (isset($contenttype))
		{
			$dbfields['contenttype'] = "'" .$db->escape_string($contenttype) . "'";
		}
		else
		{
			return(false);
		}

		if (isset($operation))
		{
			$dbfields['operation'] =  "'" . $db->escape_string($operation) . "'";
		}

		if (!$vbulletin->options['searchqueueupdates'])
		{
			// we just call indexNow. It checks for valid data.
			return vB_Search_Indexcontroller_QueueProcessor::indexNow($package,
					$contenttype, $operation, $data);
		}

		$dbfields['data'] = "'" . $db->escape_string(serialize($data)) ."'";

		$sql = "INSERT INTO " . TABLE_PREFIX . "indexqueue (" . implode(', ', array_keys($dbfields)) . ")
			VALUES ( " . implode(', ', $dbfields) . " )";
		$db->query_write($sql);
		return true;
	}
}
