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

/**
* An (almost) atomic flood check implementation. The actual check is atomic
* via affected_rows(). The selected data (used in a rollback) is not guaranteed
* to be atomic.
*
* This class only functions on integer values!
*
* @package	vBulletin
* @version	$Revision: 32878 $
* @date		$Date: 2009-10-28 11:38:49 -0700 (Wed, 28 Oct 2009) $
*/
class vB_FloodCheck
{
	/**
	* The vBulletin registry object
	*
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* The name of the table being worked on.
	*
	* @var	string
	*/
	var $table_name = '';

	/**
	* The column that is the primary key for this table. Note, this column
	* must return an integer!
	*
	* @var	stirng
	*/
	var $primary_key = '';

	/**
	* The column that contains the actual data to be floodchecked.
	* Note, this column must return an integer!
	*
	* @var	string
	*/
	var $read_column = '';

	/**
	* The value of the primary key for the matched row.
	*
	* @var	integer
	*/
	var $key_value = 0;

	/**
	* The value of the read column for the matched row.
	*
	* @var	integer
	*/
	var $read_value = 0;

	/**
	* The value that we are trying to update the read column to.
	*
	* @var	integer
	*/
	var $commit_value = 0;

	/**
	* Whether data has been committed. Data can be committed
	* even if the floodcheck is hit!
	*
	* @var	boolean
	*/
	var $is_committed = false;

	/**
	* The amount of time that a user must wait before the flood check will pass.
	* Null if no data has been committed, 0 if the flood check is passed,
	* greater than 0 if the check is hit.
	*
	* @var	null|integer
	*/
	var $flood_wait = null;

	/**
	* Constructor. Initializes the object to a commitable state.
	*
	* @param	vB_Registry	Registry object
	* @param	string		The name of the table to work on
	* @param	string		The column to read data from
	* @param	string		The name of the primary key column. Defaults to <table>id
	*/
	function vB_FloodCheck(&$registry, $table, $read_column, $primary_key = '')
	{
		if (!is_object($registry))
		{
			trigger_error('Registry object is not an object', E_USER_ERROR);
		}

		$this->registry =& $registry;
		$this->table = preg_replace('#[^a-z0-9_]#i', '', $table);
		$this->read_column = preg_replace('#[^a-z0-9_]#i', '', $read_column);

		if (empty($primary_key))
		{
			$primary_key = $this->table . 'id';
		}
		$this->primary_key = preg_replace('#[^a-z0-9_]#i', '', $primary_key);
	}

	/**
	* Commits data using the primary key as the where clause.
	*
	* @param	integer	The value of the primary key. Will match "WHERE primarykey = value"
	* @param	integer	The value to update the read column too
	* @param	integer	The minimum value for the read column that will be hit by the flood check. Higher values are too new.
	*
	* @return	boolean	True if the commit succeeded. Note that true will be returned even if the flood check is hit!
	*/
	function commit_key($key_value, $commit_value, $floodmin_value)
	{
		return $this->commit_clause(
			$this->primary_key . " = " . intval($key_value),
			$commit_value,
			$floodmin_value
		);
	}

	/**
	* Commits data using an arbitrary where clause.
	*
	* @param	string	The value of the where claused used to fetch matching data
	* @param	integer	The value to update the read column too
	* @param	integer	The minimum value for the read column that will be hit by the flood check. Higher values are too new.
	* @param	string	Extra ordering information to use when fetching data; useful if possibly matching multiple records
	*
	* @return	boolean	True if the commit succeeded. Note that true will be returned even if the flood check is hit!
	*/
	function commit_clause($where_clause, $commit_value, $floodmin_value, $order_by = '')
	{
		if (!$this->fetch_initial_data($where_clause, $order_by))
		{
			// couldn't get any initial data -- bad where primary key value?
			return false;
		}

		$this->commit_value = intval($commit_value); // TIMENOW
		$floodmin_value = intval($floodmin_value); // TIMENOW - 30

		$db =& $this->registry->db;

		// update the column to the new value, provided it's older than the flood check limit
		$db->query_write("
			UPDATE " . TABLE_PREFIX . $this->table . " AS " . $this->table . "
			SET " . $this->read_column . " = " . $this->commit_value . "
			WHERE " . $this->primary_key . " = " . intval($this->key_value) . "
				AND " . $this->read_column . " < $floodmin_value
		");

		// if we updated something, we're not flooding; otherwise, we have to wait
		$this->flood_wait = ($db->affected_rows() > 0 ? 0 : ($this->read_value - $floodmin_value));

		/*
			If a negative value occurs then it really is flooding.
			The reason is that fetch_initial_data executed for each request before the first update,
			when this happens $this->read_value will be the same and affected_rows will return 0 for
			further requests.
		*/
		if ($this->flood_wait < 0)
		{
			$this->flood_wait = $commit_value - $floodmin_value;
		}

		$this->is_committed = true;
		return true;
	}

	/**
	* Fetches the initial data in the table. This is used to determine the
	* flood wait time and for rollbacks.
	*
	* @param	string	Where clause to use to search
	* @param	string	Extra ordering. Only the first row of the result will be used!
	*
	* @return	boolean	If sucessful, the values will be placed in $this->key_value and $this->read_value.
	*/
	function fetch_initial_data($where_clause, $order_by = '')
	{
		$data = $this->registry->db->query_first("
			SELECT " . $this->primary_key . " AS primarykey, " . $this->read_column . " AS readcolumn
			FROM " . TABLE_PREFIX . $this->table . " AS " . $this->table . "
			WHERE $where_clause
			" . ($order_by ? "ORDER BY $order_by" : '') . "
			LIMIT 1
		");
		if (!$data)
		{
			// no record matching
			return false;
		}

		$this->key_value = $data['primarykey'];
		$this->read_value = $data['readcolumn'];

		return true;
	}

	/**
	* Rolls the commit back. Use this if an error occurs after the flood check
	* has been passed.
	*
	* @return	boolean
	*/
	function rollback()
	{
		if (!$this->is_committed)
		{
			// haven't committed anything to rollback!
			return false;
		}

		if ($this->is_flooding())
		{
			// we're flooding, so nothing was actually updated
			return true;
		}

		// roll the value back to what it was when we originally read it, provided
		// nothing else has updated the column since then
		$this->registry->db->query_write("
			UPDATE " . TABLE_PREFIX . $this->table . " AS " . $this->table . "
			SET " . $this->read_column . " = " . intval($this->read_value) . "
			WHERE " . $this->primary_key . " = " . intval($this->key_value) . "
				AND " . $this->read_column . " = " . $this->commit_value . "
		");

		$this->is_committed = false;

		return true;
	}

	/**
	* Determines whether any data has been commitedd.
	*
	* @return	boolean
	*/
	function is_committed()
	{
		return $this->is_committed;
	}

	/**
	* Determines whether the flood check has been hit.
	*
	* @return	boolean
	*/
	function is_flooding()
	{
		return ($this->flood_wait > 0);
	}

	/**
	* If the flood check has been hit, the amount of time to wait before
	* the check will pass.
	*
	* @return	integer
	*/
	function flood_wait()
	{
		return intval($this->flood_wait);
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 32878 $
|| ####################################################################
\*======================================================================*/
?>