<?php
error_reporting(E_ALL & ~E_NOTICE);

if (!defined('VB_ENTRY'))
{
die('Access denied.');
}
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

/**
 * vB_Search_Indexcontroller_QueueProcessor
 * This object is used to index an item based on a map of fieldname/value pairs
 * It is only called statically.
 * We can be called by a cron job, in which case our task is to read from the
 * database all the records that should be processed and
 * take care of them. For each we:
 *    figure out what object we need.
 *    extract the parameters
 *    statically call the method
 *    remove the record from the database
 * Or we can be called to index a single record.
 *
 * @package vBForum
 * @author Ed Brown, vBulletin Team
 * @copyright Copyright (c)vBulletin Solutions Inc. 2009
 * @version $Id$
 * @access public
 */

/**
 * vB_Search_Indexcontroller_QueueProcessor
 *
 * @package
 * @author ebrown
 * @copyright Copyright (c) 2009
 * @version $Id$
 * @access public
 */
class vB_Search_Indexcontroller_QueueProcessor
{
// ###################### Start __construct ######################
	//ensure this is only called static
	private function __construct ()
	{
	}

// ###################### Start index ######################
	/**
	 * vB_Search_Indexcontroller_QueueProcessor::index()
	 * This is the default method. We get called by the cron job, and we have
	 * no idea how many records are waiting, etc.
	 *
	 * @return : boolean success indicator
	 */
	public static function index()
	{
		//first, do a check to see if we are unique. If we are already running,
		// we just quit.
		global $vbulletin;
		$lock_name = TABLE_PREFIX . 'vb_queue_lock';

		if (!$row = $vbulletin->db->query_first("SELECT IS_FREE_LOCK('$lock_name')") )
		{
			error_log('in vB_Search_Indexcontroller_QueueProcessor::index,
				unable to query lock to do queue indexing');
			return false;
		}
		reset($row);
		if (! current($row))
		{
			return false;
		}

		if (!$row = $vbulletin->db->query_first("SELECT GET_LOCK('$lock_name', 2)") )
		{
			return false;
		}

		if (! current($row))
		{
			return false;
		}

		//if we got here, we were able to get the lock.
		$vb = vB_Search_Core::get_instance();
		$db = vB_Search_Core::get_db();
		$rst = $db->query_read("SELECT indexqueue.* FROM " . TABLE_PREFIX
			. "indexqueue AS indexqueue ORDER BY queueid");
		$ids = array();
		$currtime = gettimeofday(true);
		$timeout = ini_get('max_execution_time');

		if ($timeout < 15	or $timeout > 300)
		{
			$timeout = 60;
			@set_time_limit($timeout);
		}
		$endtime = $currtime + intval($timeout * .75);

		while ($row = $db->fetch_array($rst))
		{
			//make sure we have good data

			if ($row['contenttype'] == null || $row['package'] == null  )
			{
				continue;
			}

			//let's try to get the correct controller
			if (($indexcontroller = $vb->get_index_controller($row['package'], $row['contenttype'])) == null)
			{
				continue;
			}

			if (gettimeofday(true) > $endtime)
			{
				break;
			}

			//The data is serialized, so let's extract it.
			$row['data'] = unserialize($row['data']);

			if (!self::indexOne($indexcontroller,
				$row['contenttype'], $row['operation'], $row['data']))
			{
				error_log('Unable to index ' . ': ' . $row['operation']
					. ': ' . isset($row['data'][0]) ? $row['data'][0] : '');
			}
			$ids[] = $row['queueid'];
		}

		if (count($ids))
		{
			$db->query_write("DELETE from " . TABLE_PREFIX ."indexqueue WHERE queueid in(".
			 implode(', ', $ids) . ")");
		}
		$vbulletin->db->query_first("SELECT RELEASE_LOCK('$lock_name')");
		return true;
	}

	// ###################### Start indexNow ######################
	/**
	 * vB_Search_Indexcontroller_QueueProcessor::indexNow()
	 * This function is used if we want to index immediate. This will happen
	 * primarily for small boards with very low use. In that case the cron
	 * script may only be run a couple times a day or less, and the indexer
	 * would use all the available cron runs.
	 *
	 * @param string $package : the package containing the index controller
	 * @param string $contenttype : content type string we are indexing
	 * @param string $operation : the index operation
	 * @param mixed $data : an array with whatever data the indexer needs.
	 * @return : boolean success indicator
	 */
	public static function indexNow($package, $contenttype, $operation, $data)
	{
		if (isset($package) AND isset($contenttype) AND isset($operation))
		{
			$indexcontroller = vB_Search_Core::get_instance()->get_index_controller($package,
				$contenttype);

			if (is_a($indexcontroller,'vb_Search_Indexcontroller_Null') )
			{
				return false;
			}
			return vb_Search_Indexcontroller_QueueProcessor::indexOne($indexcontroller,
				$contenttype, $operation, $data);

		}
		else
		{
			return false;
		}
	}


	// ###################### Start indexOne ######################
	/**
	 * vB_Search_Indexcontroller_QueueProcessor::indexOne()
	 * This function does the actual work of indexing.
	 * We have a controller, we've verified the data, and we're ready to call the
	 * indexer.
	 *
	 * @param mixed $indexcontroller : the indexcontroller object
	 * @param string $contenttype : the content type we are indexing
	 * @param string $operation : the index operation we are going to perform
	 * @param mixed $data : an array with whatever data the indexer needs
	 * @return : boolean success indicator
	 */
	private function indexOne($indexcontroller, $contenttype, $operation, $data)
	{
		//We have a controller.
		//We may need id, contenttype, and oldid in the child, so make sure
		// they are available.
		//We normally will have the newid as $data parameter zero, and if we
		// need id2 it will be parameter one. Anything else will be parameter three,
		// which is likely an array.
		$log_entry = var_export($data, true);
		global $vbulletin;

		try
		{
			call_user_func_array(array($indexcontroller,$operation), $data);
			return true;
		}
		catch (Exception $e)
		{
			//Nothing we can do- we're probably doing this from a queued operation.
			return false;
		}
	}
}
