<?php if (!defined('VB_ENTRY')) die('Access denied.');
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.1.5 Patch Level 1 - Licence Number VBF1F15E74
|| # ---------------------------------------------------------------- # ||
|| # Copyright 2000-2011 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

/**
 * The vB core class.
 * The vB core class.
 * Everything required at the core level should be accessible through this.
 *
 * The core class performs initialisation for error handling, exception handling,
 * application instatiation and optionally debug handling.
 *
 * @TODO: Much of what goes on in global.php and init.php will be handled, or at
 * least called here during the initialisation process.  This will be moved over as
 * global.php is refactored.
 *
 * @package vBulletin
 * @version $Revision: 28823 $
 * @since $Date: 2008-12-16 17:43:04 +0000 (Tue, 16 Dec 2008) $
 * @copyright vBulletin Solutions Inc.
 */
class vB_dB_Query
{
	/** This class is called by the new vB_dB_Assertor database class
	 * It does the actual execution. See the vB_dB_Assertor class for more information

	 * $queryid can be either the id of a query from the dbqueries table, or the
	 * name of a table.
	 *
	 * if it is the name of a table , $params MUST include 'type' of either update, insert, select, or delete.
	 *
	 * $params includes a list of parameters. Here's how it gets interpreted.
	 *
	 * If the queryid was the name of a table and type was "update", one of the params
	 * must be the primary key of the table. All the other parameters will be matched against
	 * the table field names, and appropriate fields will be updated. The return value will
	 * be false if an error is generated and true otherwise
	 *
	 * If the queryid was the name of a table and type was "delete", one of the params
	 * must be the primary key of the table. All the other parameters will be ignored
	 * The return value will be false if an error is generated and true otherwise
	 *
	 * If the queryid was the name of a table and type was "insert", all the parameters will be
	 * matched against the table field names, and appropriate fields will be set in the insert.
	 * The return value is the primary key of the inserted record.
	 *
	 * If the queryid was the name of a table and type was "select", all the parameters will be
	 * matched against the table field names, and appropriate fields will be part of the
	 * "where" clause of the select. The return value will be a vB_dB_Result object
	 * The return value is the primary key of the inserted record.
	 *
	 * If the queryid is the key of a record in the dbqueries table then each params
	 * value will be matched to the query. If there are missing parameters we will return false.
	 * If the query generates an error we return false, and otherwise we return either true,
	 * or an inserted id, or a recordset.
	 *
	 **/

	/*Properties====================================================================*/


	/** The database connection **/
	protected $db = false;

	/** The user info ***/
	protected $userinfo = false;

	/** query_type - s (select), u (update), i (insert), d (delete), and t (table... we don't know yet
	**/
	protected $query_type = false;

	/** are we ready to execute? **/
	protected $data_loaded = false;

	/** are we ready to execute? **/
	protected $datafields = false;

	/** What is the primary key of the table, if applicable? */
	protected $primarykey = false;

	/** What is the text of the stored query from the dictionary, if applicable? */
	protected $query_string = false;

	/** The parameters are are going to use to populate the query data */
	protected $params = false;

	/** The array from a describe statement for database structure, if applicable? */
	protected $structure = false;

	/** The replacement variables from a stored query**/
	protected $replacements = false;

	/** The original query id **/
	protected $queryid = false;

	/** The most recent error **/
	protected $error = false;

	/** All errors for this query **/
	protected $errors = array();

	/** sortorder, for select queries only (obviously) **/
	protected $sortorder = false;


	/** This is the definition for tables we will process through.  It saves a
	* database query to put them here.
	* **/
	protected $table_data = array();

	protected $query_data = array();

	/*Initialisation================================================================*/

	/** validates that we know what to do with this queryid
	*
	*	@param 	string	id of the query
	*  @param 	mixed		the shared db object
	* 	@param	array		the user information
	*
	***/
	public function __construct($queryid, &$db, $userinfo)
	{
		$this->db = $db;
		$this->userinfo = $userinfo;
		$this->queryid = $queryid;
		$class = 'vB_Db_' . $this->db_type . '_QueryDefs';
		$query_defs = new $class();

		$this->query_data = $query_defs->getQueryData();
		$this->table_data = $query_defs->getTableData();

		if ($this->query_data[$queryid])
		{
			$this->query_type = $this->query_data[$queryid]['querytype'];
			$this->query_string = $this->query_data[$queryid]['query_string'];
			$matches = false;
			preg_match_all('#\{.+?\}#', $this->query_data[$queryid]['query_string'], $matches);
			$this->replacements = $matches;
			return;
		}

		if ($this->table_data[$queryid])
		{
			$this->query_type = 't';
			$this->structure = $this->table_data[$queryid]['structure'];
			$this->primarykey  = $this->table_data[$queryid]['key'];
			return;
		}

		//Now let's see if this is a stored query.
		$this_query = $this->db->query_first("SELECT * from " . TABLE_PREFIX . "dbquery
			WHERE dbqueryid = '" . $this->db->escape_string($queryid) . "'");
		$this->queryid = $queryid;

		if ($this_query)
		{
			$this->query_type = $this_query['querytype'];
			$this->query_string = $this_query['query_string'];
			$matches = false;
			preg_match_all('#\{.+?\}#', $this_query['query_string'], $matches);
			$this->replacements = $matches;
			return;
		}


		//We need to see if this is a table, and if so get the structure
		//If this isn't a table name we'll generate an error.

		$reporterror = $db->reporterror;
		$db->hide_errors();
		$structure = $this->db->query_read("describe " . TABLE_PREFIX . "$queryid;");
		if ($reporterror)
		{
			$db->show_errors();
		}

		if (!$structure)
		{
			return false;
		}
		$this->query_type = 't';
		$this->structure = array();
		while($record = $this->db->fetch_array($structure))
		{
			$this->structure[] = $record['Field'];

			if ($record['key'] == 'PRI')
			{
				$this->primarykey = $record['field'];
			}
		}
	}

	/** This loads and validates the data- ensures we have all we need
	*
	*	@param	array		the data for the query
	***/
	public function setQuery($params, $sortorder)
	{
		//Let's first check that we have a valid type, and if necessary we
		// have a valid key.

		if (!$this->query_type OR (!$this->query_string AND !$this->structure))
		{
			return false;
		}
		reset($params);

		if (is_array(current($params)))
		{
			$checkvals = current($params);
		}
		else
		{
			$checkvals = $params;
		}

		//We're not going to do the detailed match. At this step we should
		//only do the obvious. So if we are a stored query and either params or the
		//replacements are empty but not the other, then we can't execute.
		switch($this->query_type)
		{
			case 'i': //We are a stored query insert.
			case 'u': //We are a stored query update.
			case 's':  //We are a stored query select.
			case 'd': //We are a stored query delete.
				if (count($checkvals) AND ($this->query_string) AND !count($this->replacements))
				{
						return false;
				}

				if (count($this->replacements) AND ($this->query_string)  AND !count($checkvals))
				{
						return false;
				}
				//We at least are potentially good.


				break;
			case 't': //We are a table. We don't know yet what we are executing
				if (!$checkvals['type'])
				{
					return false;
				}
				switch($checkvals['type'])
				{
					case 'i': //We are a table insert.
					case 'u': //We are a table update.
					case 'd': //We are a table delete.
						if (count($checkvals) < 2)
						{
							return false;
						}
						$this->query_type = $checkvals['type'];
						break;
					case 's':  //We are a table select. We don't need anything
							$this->query_type = 's';
						break;
					default:
						return false;
				} // switch
				break;
			default:
				return false;
			;
		} // switch

		if (is_array(current($params)) OR ($this->query_type == 's'))
		{
			$this->params = $params;
		}
		else
		{
			$this->params = array($params);
		}

		if ($sortorder)
		{
			$this->sortorder = $sortorder;
		}
		$this->data_loaded = true;
		return true;
	}

	/** This function is the public interface to actually execute the SQL.
	*
	*	@return 	mixed
	**/
	public function execSQL()
	{
		$result_type = 'vB_dB_' . $this->db_type . '_result';

		//If we don't have the data loaded, we can't execute.
		if (!$this->query_type OR !$this->data_loaded)
		{
			return false;
		}

		switch($this->query_type)
		{
			case 'u': //We are a stored query update.
				$result = $this->doUpdates($result_type);
				break;
			case 'i': //We are a stored query insert.
				$result = $this->doInserts($result_type);
				break;
			case 's':  //We are a stored query select.
				return $this->doSelect($result_type);
				break;
			case 'd': //We are a stored query delete.
				$result = $this->doDeletes($result_type);
				;
				break;
			case 't': //We are a table. We should never have gotten here.
			default:
				return false;
				;
		} // switch
		return $result;
	}

	/** This matches a series of values against a query string
	*
	*	@param 	string		The query string we want to populate
	*	@param	mixed			The array of values
	*
	*  It returns either a string with all the values inserted ready to execute,
	*  or false;
	*/
	private function matchValues($querystring, $values)
	{
		//The replacements are like {1}
		foreach ($values as $key => $value)
		{
			//See if we skip escaping this value
			if (array_key_exists('quotes_ok', $this->query_data[$this->queryid]) AND
				in_array($key, $this->query_data[$this->queryid]['quotes_ok']))
			{
				$querystring = str_replace('{'.  $key . '}', $value, $querystring);
			}
			else
			{
				$querystring = str_replace('{'.  $key . '}', $this->db->escape_string($value), $querystring);

			}
		}
		$querystring = str_replace('{TABLE_PREFIX}', TABLE_PREFIX, $querystring);

		//See if there are variables we didn't replace.
		$matches = false;
		if (preg_match_all('#\{.{1,32}?\}#', $querystring, $matches) > 0)
		{
			return false;
		}
		else
		{
				$querystring .= "\n/**" . $this->queryid . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";
				return $querystring;
		}
	}


	/** This function generates the query text against a table.
	 *
	 *	@param	char
	 *	@param	array
	 *
	 *	@return 	mixed
	 **/
	private function buildQuery($type, $values)
	{
		$new_values = array();
		//first it will be useful to have an array of matches.
		foreach ($this->structure as $field )
		{
			if (isset($values[$field]))
			{
				if (array_key_exists('quotes_ok', $this->table_data[$this->queryid]) AND
					in_array($field, $this->table_data[$this->queryid]['quotes_ok']))
				{
					$new_values[$field] =  "'" . $values[$field] . "'";
				}
				else
				{
					$new_values[$field] =  "'" . $this->db->escape_string($values[$field]) . "'";
				}
			}
		}
		switch($this->query_type){
			case 'u':
				//we need a primary key value
				if (!$values[$this->primarykey])
				{
						return false;
				}
				unset($new_values[$this->primarykey]);
				foreach ($new_values as $key => $value)
				{
					$new_values[$key] = $key . "=" . $value;
				}
				return "UPDATE " . TABLE_PREFIX . $this->queryid . " SET " . implode($new_values, ',') . " WHERE " .
					$this->primarykey . "='" . $this->db->escape_string($values[$this->primarykey]) . "' " .
					 "\n/**" . $this->queryid . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";
				break;
			case 'i':
				return "INSERT INTO " . TABLE_PREFIX . $this->queryid . " (" . implode(array_keys($new_values), ',') . ")
				VALUES(" . implode($new_values, ',') . ")" .
					"\n/**" . $this->queryid . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";
				break;
			case 'd':
				//we need a primary key value

				if ($values[$this->primarykey] AND !empty($values[$this->primarykey]))
				{
					return "DELETE FROM " . TABLE_PREFIX . $this->queryid . " WHERE " .
						$this->primarykey . "='" . $this->db->escape_string($values[$this->primarykey]) . "' " .
						"\n/**" . $this->queryid . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";
				}
				foreach ($new_values as $key => $value)
				{
					$new_values[$key] = $key . "=" . $value;
				}
				return "DELETE FROM " . TABLE_PREFIX . $this->queryid . " WHERE " . implode($new_values, ' AND ')  .
					 "\n/**" . $this->queryid . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/";
				break;
			case 's':
				foreach ($new_values as $key => $value)
				{
					$new_values[$key] = $key . "=" . $value;
				}
				return "SELECT * FROM " . TABLE_PREFIX . $this->queryid . " WHERE " . implode($new_values, ' AND ')
					. ($this->sortorder ? " ORDER BY " . $this->sortorder : '') .
					"\n/**" . $this->queryid . (defined('THIS_SCRIPT') ? '- ' . THIS_SCRIPT : '') . "**/" ;

				break;
			default:
				return false;
			;
		} // switch
	}


	/** This function does the updates and returns array of the number of updates
	 *
	 *	@param	char
	 *
	 *	@return 	mixed
	 **/
	protected function doUpdates($result_type)
	{
		$results = array();

		if ($this->query_string)
		{
			foreach ($this->params as $params)
			{
				if ($querystring = $this->matchValues($this->query_string, $params))
				{
					$this->db->query_write($querystring);
					$this->error = $this->db->error();
					$results[] = $this->db->affected_rows();
				}
				else
				{
					$results[] = false;
				}
			}
		}
		else if ($this->structure)
		{
			foreach ($this->params as $params)
			{
				if ($querystring = $this->buildQuery($this->type, $params))
				{
					$this->db->query_write($querystring);
					$this->error = $this->db->error();
					$results[] = $this->db->affected_rows();
				}
				else
				{
					$results[] = false;
				}
			}

		}
		return $results;
	}


	/** This function does the inserts and returns (if applicable) the new keys
	 *
	 *	@param	char
	 *
	 *	@return 	mixed
	 **/
	protected function doInserts($result_type)
	{
		$results = array();
		if ($this->query_string)
		{
			foreach ($this->params as $params)
			{
				if ($querystring = $this->matchValues($this->query_string, $params))
				{
					$this->db->query_write($querystring);
					$this->error = $this->db->error();
					$results[] = $this->db->insert_id();
				}
				else
				{
					$results[] = false;
				}
			}
		}
		else if ($this->structure)
		{
			foreach ($this->params as $params)
			{
				if ($querystring = $this->buildQuery($this->type, $params))
				{
					$this->db->query_write($querystring);
					$this->error = $this->db->error();
					$results[] = $this->db->insert_id();
				}
				else
				{
					$results[] = false;
				}
			}
		}
		return $results;
	}

	/** This function does the selects and returns a result object
	 *
	 *	@param	char
	 *
	 *	@return 	object
	 **/
	protected function doSelect($result_type)
	{
		$results = array();

		if ($this->query_string)
		{
			$querystring = $this->matchValues($this->query_string, $this->params);
		}
		else if ($this->structure)
		{
			$querystring = $this->buildQuery($this->type, $this->params);
		}

		if (!$querystring)
		{
			return false;
		}
		$resultclass = 'vB_dB_' . $this->db_type . '_result';
		$result = new $resultclass($this->db, $querystring);
		return $result;
	}

	/** This function does the deletes and returns a flag to indicate whether the delete worked
	 *
	 *	@param	char
	 *
	 *	@return 	boolean
	 **/
	protected function doDeletes($result_type)
	{
		$results = array();
		if ($this->query_string)
		{
			foreach ($this->params as $params)
			{
				if ($querystring = $this->matchValues($this->query_string, $params))
				{
					$this->db->query_write($querystring);
					$this->error = $this->db->error();
					$results[] = empty($this->error);
				}
				else
				{
					$results[] = false;
				}
			}
		}
		else if ($this->structure)
		{
			foreach ($this->params as $params)
			{
				if ($querystring = $this->buildQuery($this->type, $params))
				{
					$this->db->query_write($querystring);
					$this->error = $this->db->error();
					$results[] = empty($this->error);
				}
				else
				{
					$results[] = false;
				}
			}

		}
		return $results;
	}

}

/*======================================================================*\
|| ####################################################################
|| # Downloaded=> 01:57, Mon Sep 12th 2011
|| # SVN=> $Revision=> 28823 $
|| ####################################################################
\*======================================================================*/