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

error_reporting(E_ALL & ~E_NOTICE);

// ###################### Constants #######################
define('USERCHANGELOG_COND_TYPE_USERID', 1);
define('USERCHANGELOG_COND_TYPE_ADMINID', 2);
define('USERCHANGELOG_COND_TYPE_FIELDNAME', 3);
define('USERCHANGELOG_COND_TYPE_USERNAME', 4);
define('USERCHANGELOG_COND_TYPE_TIME', 5);

/**
* Select and search functions for the userlog changes
*
* @package	vBulletin
* @version	$Revision: 32878 $
* @date		$Date: 2009-10-28 11:38:49 -0700 (Wed, 28 Oct 2009) $
*/
class vB_UserChangeLog
{
	/**
	* The vBulletin database object
	*
	* @var	vB_Database
	*/
	var $dbobject = null;
	
	/**
	* Execute or just build the query?
	*
	* @var	boolean
	*/
	var $execute = false;
	
	/**
	* Full query or just count?
	*
	* @var	boolean
	*/
	var $just_count = false;
	
	/**
	* Constructor
	*
	* @param	vBulletin database Instance
	*/
	function vB_UserChangeLog(&$registry)
	{
		// the db object need for the execute and for the escape string
		if (is_object($registry))
		{
			$this->registry =& $registry;
		}
		else
		{
			trigger_error('Database object is not an object', E_USER_ERROR);
		}
	}
	
	/**
	* Set the execute flag
	*
	* @param	boolean
	*/
	function set_execute($execute = false)
	{
		if ($execute)
		{
			$this->execute = true;
		}
		else
		{
			$this->execute = false;
		}
	}
	
	/**
	* Set the just_count flag
	*
	* @param	boolean
	*/
	function set_just_count($just_count = false)
	{
		if ($just_count)
		{
			$this->just_count = true;
		}
		else
		{
			$this->just_count = false;
		}
	}
	
	// ###################### Userchangelog Select "by something" Proxy Functions #######################
	/**
	* Select the userlog by user
	*
	* @param	integer	userid of the user
	* @param	integer minimum time (UNIX_TIMESTAMP)
	* @param	integer maximum time (UNIX_TIMESTAMP)
	*
	* @return	mixed	sql query (no execute) / select resultset (execute + no just_count) / selected count (execute + just_count)
	*/
	function sql_select_by_userid($userid, $time_start = 0, $time_end = 0, $page = 0, $limit = 100)
	{
		return $this->sql_select_core($userid, USERCHANGELOG_COND_TYPE_USERID, $time_start, $time_end, $page, $limit);
	}

	/**
	* Select the userlog by admin
	*
	* @param	integer	userid of the Admin who made the change
	* @param	integer minimum time (UNIX_TIMESTAMP)
	* @param	integer maximum time (UNIX_TIMESTAMP)
	*
	* @return	mixed	sql query (no execute) / select resultset (execute + no just_count) / selected count (execute + just_count)
	*/
	function sql_select_by_adminid($adminid, $time_start = 0, $time_end = 0, $page = 0, $limit = 100)
	{
		return $this->sql_select_core($adminid, USERCHANGELOG_COND_TYPE_ADMINID, $time_start, $time_end, $page, $limit);
	}

	/**
	* Select the userlog by field
	*
	* @param	string	The field name (reference: class_dm_user.php, user_changelog_fields variable)
	* @param	integer minimum time (UNIX_TIMESTAMP)
	* @param	integer maximum time (UNIX_TIMESTAMP)
	*
	* @return	mixed	sql query (no execute) / select resultset (execute + no just_count) / selected count (execute + just_count)
	*/
	function sql_select_by_fieldname($fieldname, $time_start = 0, $time_end = 0, $page = 0, $limit = 100)
	{
		return $this->sql_select_core($fieldname, USERCHANGELOG_COND_TYPE_FIELDNAME, $time_start, $time_end, $page, $limit);
	}

	/**
	* Select the userlog by username
	*
	* @param	string	The username
	* @param	integer minimum time (UNIX_TIMESTAMP)
	* @param	integer maximum time (UNIX_TIMESTAMP)
	*
	* @return	mixed	sql query (no execute) / select resultset (execute + no just_count) / selected count (execute + just_count)
	*/
	function sql_select_by_username($fieldname, $time_start = 0, $time_end = 0, $page = 0, $limit = 100)
	{
		return $this->sql_select_core($fieldname, USERCHANGELOG_COND_TYPE_USERNAME, $time_start, $time_end, $page, $limit);
	}

	/**
	* Select the userlog by time
	*
	* @param	integer minimum time (UNIX_TIMESTAMP)
	* @param	integer maximum time (UNIX_TIMESTAMP)
	*
	* @return	mixed	sql query (no execute) / select resultset (execute + no just_count) / selected count (execute + just_count)
	*/
	function sql_select_by_time($time_start, $time_end, $page = 0, $limit = 100)
	{
		return $this->sql_select_core(0, USERCHANGELOG_COND_TYPE_TIME, $time_start, $time_end, $page, $limit);
	}

	// ###################### Userchangelog Select Core Functions #######################
	/**
	* Select query builder / executer
	*
	* @param	mixed	condition value
	* @param	integer	condition type (reference: in this file define('USERCHANGELOG_COND_TYPE_*'))
	* @param	integer minimum time (UNIX_TIMESTAMP)
	* @param	integer maximum time (UNIX_TIMESTAMP)
	* @param	integer which page we want to select
	* @param	integer how many row on the page
	*
	* @return	mixed	sql query (no execute) / select resultset (execute + no just_count) / selected count (execute + just_count)
	*/
	function sql_select_core($cond_value, $cond_type, $time_start, $time_end, $page, $limit)
	{
		$page = intval($page);
		$limit = intval($limit);
		$where = '';
		$where2 = '';
		$query = '';
		$just_count = false;
		
		// dbbject need for the escape string
		if (!is_object($this->registry) OR !is_object($this->registry->db))
		{
			trigger_error('Database object is not an object', E_USER_ERROR);
		}
		
		// let's create the where condition depend on the condition type
		switch ($cond_type)
		{
			// condition by userid
			case USERCHANGELOG_COND_TYPE_USERID:
			{
				$where = "userchangelog.userid = " . intval($cond_value);
				break;
			}
			// condition by adminid
			case USERCHANGELOG_COND_TYPE_ADMINID:
			{
				$where = "userchangelog.adminid = " . intval($cond_value);
				break;
			}
			// condition by fieldname
			case USERCHANGELOG_COND_TYPE_FIELDNAME:
			{
				$where = "userchangelog.fieldname = '" . $this->registry->db->escape_string(strval($cond_value)) . "'";
				break;
			}
			// condition by username
			case USERCHANGELOG_COND_TYPE_USERNAME:
			{
				$where = "userchangelog.fieldname = 'username'";
				$where2 = "(userchangelog.oldvalue = '" . $this->registry->db->escape_string(strval($cond_value)) . "' OR userchangelog.newvalue = '" . $this->registry->db->escape_string(strval($cond_value)) . "')";
				break;
			}
			// condition by time (do nothing just avoid the default case)
			case USERCHANGELOG_COND_TYPE_TIME:
			{
				break;
			}
			// unknown condition type, return an empty string
			default:
			{
				return '';
			}
		}
		
		// when we have timeframe for the select then we add that to the condition
		if ($time_start)
		{
			$where .= ($where ? " AND " : "") . " change_time >= " . intval($time_start);
		}
		if ($time_end)
		{
			$where .= ($where ? " AND " : "") . " change_time <= " . intval($time_end);
		}
		
		// let's build the query if we got $where condition
		if ($where)
		{
			// extend the where with the additional part (part behind the time condition)
			$where .= ($where2 ? " AND " . $where2 : "");
			
			// build the query
			$query = "
				SELECT ".($this->just_count ? "COUNT(userchangelog.changeid) AS change_count" : "user.*, userchangelog.*, adminuser.username AS admin_username ") . " 
				FROM " . TABLE_PREFIX . "userchangelog AS userchangelog
				LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = userchangelog.userid)
				LEFT JOIN " . TABLE_PREFIX . "user AS adminuser ON(adminuser.userid = userchangelog.adminid)
				WHERE $where 
				" . ($this->just_count ? "" : "ORDER BY userchangelog.change_time DESC, userchangelog.change_uniq ASC, userchangelog.fieldname DESC") . "
				" . ($this->just_count ? "" : "LIMIT " . ($limit*$page) . ", $limit") . "
			";
		}

		// no execute: return with the query string
		if (!$this->execute)
		{
			return $query;
		}
		
		// execute: return with the select result
		if ($this->just_count)
		{
			$result = $this->registry->db->query_first($query);
			return $result['change_count'];
		}
		else
		{
			return $this->registry->db->query_read($query);
		}
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>