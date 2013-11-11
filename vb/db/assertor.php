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
abstract class vB_dB_Assertor
{
	/** This class is the new master database class
	* The main way of using this is
	* vB_dB_Assertor::getInstance()->assertQuery($queryid, $params);
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

	//the database instance
	protected static	$instance = false;

	/** The database connection **/
	protected static $db = false;

	/** The user info ***/
	protected static $userinfo = false;

	/**The database we are using **/
	protected static $site_db_type = 'MYSQL';

	/*Initialisation================================================================*/

	/** prevent instantiation **/
	protected function __construct()
	{
	}

	/** This sets the db and userinfo. It will normally be call in the boot process
	*
	* @param object 	the db object
	* @param array		userinfo array
	***/
	public static function init(&$db, &$userinfo)
	{
		self::$db = $db;
		self::$userinfo = $userinfo;
		$class = 'vB_dB_' . self::$site_db_type . '_Assertor';
		if (class_exists($class))
		{
			self::$instance = new $class();
		}
	}

	/** returns the singleton instance
	*
	**/
	public static function getInstance()
	{
		if (!isset(self::$instance))
		{
			return false;
		}

		return self::$instance;
	}

	/*** Core function- validates, composes, and executes a query. See above for more
	*
	* @param string
	* @param array
	* @param string
	*
	* @return mixed	boolean, integer, or results object
	*/
	public static function assertQuery($queryid, $params, $orderby = false)
	{
		//make sure we have been initialized
		if (!isset(self::$instance))
		{
			return false;
		}
		// get the query object
		$query_class = 'vB_dB_' . self::$site_db_type . '_Query';
		$query = new $query_class($queryid, self::$db, self::$userinfo);

		if (! $query)
		{
			return false;
		}

		//set the parameters
		$check = $query->setQuery($params, $orderby);

		if (! $check)
		{
			return $check;
		}
		return $query->execSQL();
	}
}

/*======================================================================*\
|| ####################################################################
|| # Downloaded=> 01:57, Mon Sep 12th 2011
|| # SVN=> $Revision=> 28823 $
|| ####################################################################
\*======================================================================*/