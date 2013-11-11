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

define('FILE_VERSION', '4.1.5'); // this should match installsteps.php
define('SIMPLE_VERSION', '415'); // see vB_Datastore::check_options()
define('YUI_VERSION', '2.9.0'); // define the YUI version we bundle

/**#@+
* The maximum sizes for the "small" profile avatars
*/
define('FIXED_SIZE_AVATAR_WIDTH',  60);
define('FIXED_SIZE_AVATAR_HEIGHT', 80);
/**#@-*/

/**#@+
* These make up the bit field to disable specific types of BB codes.
*/
define('ALLOW_BBCODE_BASIC',  1);
define('ALLOW_BBCODE_COLOR',  2);
define('ALLOW_BBCODE_SIZE',   4);
define('ALLOW_BBCODE_FONT',   8);
define('ALLOW_BBCODE_ALIGN',  16);
define('ALLOW_BBCODE_LIST',   32);
define('ALLOW_BBCODE_URL',    64);
define('ALLOW_BBCODE_CODE',   128);
define('ALLOW_BBCODE_PHP',    256);
define('ALLOW_BBCODE_HTML',   512);
define('ALLOW_BBCODE_IMG',    1024);
define('ALLOW_BBCODE_QUOTE',  2048);
define('ALLOW_BBCODE_CUSTOM', 4096);
/**#@-*/

/**#@+
* These make up the bit field to control what "special" BB codes are found in the text.
*/
define('BBCODE_HAS_IMG',    1);
define('BBCODE_HAS_ATTACH', 2);
define('BBCODE_HAS_SIGPIC', 4);
define('BBCODE_HAS_RELPATH',8);
/**#@-*/

/**#@+
* Bitfield values for the inline moderation javascript selector which should be self-explanitory
*/
define('POST_FLAG_INVISIBLE', 1);
define('POST_FLAG_DELETED',   2);
define('POST_FLAG_ATTACH',    4);
define('POST_FLAG_GUEST',     8);
/**#@-*/

// #############################################################################
// MySQL Database Class

/**#@+
* The type of result set to return from the database for a specific row.
*/
define('DBARRAY_BOTH',  0);
define('DBARRAY_ASSOC', 1);
define('DBARRAY_NUM',   2);
/**#@-*/

/**
* Class to interface with a database
*
* This class also handles data replication between a master and slave(s) servers
*
* @package	vBulletin
* @version	$Revision: 46878 $
* @date		$Date: 2011-08-01 16:35:31 -0700 (Mon, 01 Aug 2011) $
*/
class vB_Database
{
	/**
	* Array of function names, mapping a simple name to the RDBMS specific function name
	*
	* @var	array
	*/
	var $functions = array(
		'connect'            => 'mysql_connect',
		'pconnect'           => 'mysql_pconnect',
		'select_db'          => 'mysql_select_db',
		'query'              => 'mysql_query',
		'query_unbuffered'   => 'mysql_unbuffered_query',
		'fetch_row'          => 'mysql_fetch_row',
		'fetch_array'        => 'mysql_fetch_array',
		'fetch_field'        => 'mysql_fetch_field',
		'free_result'        => 'mysql_free_result',
		'data_seek'          => 'mysql_data_seek',
		'error'              => 'mysql_error',
		'errno'              => 'mysql_errno',
		'affected_rows'      => 'mysql_affected_rows',
		'num_rows'           => 'mysql_num_rows',
		'num_fields'         => 'mysql_num_fields',
		'field_name'         => 'mysql_field_name',
		'insert_id'          => 'mysql_insert_id',
		'escape_string'      => 'mysql_escape_string',
		'real_escape_string' => 'mysql_real_escape_string',
		'close'              => 'mysql_close',
		'client_encoding'    => 'mysql_client_encoding',
		'ping'               => 'mysql_ping',
	);

	/**
	* The vBulletin registry object
	*
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* Array of constants for use in fetch_array
	*
	* @var	array
	*/
	var $fetchtypes = array(
		DBARRAY_NUM   => MYSQL_NUM,
		DBARRAY_ASSOC => MYSQL_ASSOC,
		DBARRAY_BOTH  => MYSQL_BOTH
	);

	/**
	* Full name of the system
	*
	* @var	string
	*/
	var $appname = 'vBulletin';

	/**
	* Short name of the system
	*
	* @var	string
	*/
	var $appshortname = 'vBulletin';

	/**
	* Database name
	*
	* @var	string
	*/
	var $database = null;

	/**
	* Link variable. The connection to the master/write server.
	*
	* @var	string
	*/
	var $connection_master = null;

	/**
	* Link variable. The connection to the slave/read server(s).
	*
	* @var	string
	*/
	var $connection_slave = null;

	/**
	* Link variable. The connection last used.
	*
	* @var	string
	*/
	var $connection_recent = null;

	/**
	* Whether or not we will be using different connections for read and write queries
	*
	* @var	boolean
	*/
	var $multiserver = false;

	/**
	* Array of queries to be executed when the script shuts down
	*
	* @var	array
	*/
	var $shutdownqueries = array();

	/**
	* The contents of the most recent SQL query string.
	*
	* @var	string
	*/
	var $sql = '';

	/**
	* Whether or not to show and halt on database errors
	*
	* @var	boolean
	*/
	var $reporterror = true;

	/**
	* The text of the most recent database error message
	*
	* @var	string
	*/
	var $error = '';

	/**
	* The error number of the most recent database error message
	*
	* @var	integer
	*/
	var $errno = '';

	/**
	* SQL Query String
	*
	* @var	integer	The maximum size of query string permitted by the master server
	*/
	var $maxpacket = 0;

	/**
	* Track lock status of tables. True if a table lock has been issued
	*
	* @var	bool
	*/
	var $locked = false;

	/**
	* Number of queries executed
	*
	* @var	integer	The number of SQL queries run by the system
	*/
	var $querycount = 0;

	/**
	* Constructor. If x_real_escape_string() is available, switches to use that
	* function over x_escape_string().
	*
	* @param	vB_Registry	Registry object
	*/
	function vB_Database(&$registry)
	{
		if (is_object($registry))
		{
			$this->registry =& $registry;
		}
		else
		{
			trigger_error("vB_Database::Registry object is not an object", E_USER_ERROR);
		}
	}

	/**
	* Connects to the specified database server(s)
	*
	* @param	string	Name of the database that we will be using for select_db()
	* @param	string	Name of the master (write) server - should be either 'localhost' or an IP address
	* @param	integer	Port for the master server
	* @param	string	Username to connect to the master server
	* @param	string	Password associated with the username for the master server
	* @param	boolean	Whether or not to use persistent connections to the master server
	* @param	string	(Optional) Name of the slave (read) server - should be either left blank or set to 'localhost' or an IP address, but NOT the same as the servername for the master server
	* @param	integer	(Optional) Port of the slave server
	* @param	string	(Optional) Username to connect to the slave server
	* @param	string	(Optional) Password associated with the username for the slave server
	* @param	boolean	(Optional) Whether or not to use persistent connections to the slave server
	* @param	string	(Optional) Parse given MySQL config file to set options
	* @param	string	(Optional) Connection Charset MySQLi / PHP 5.1.0+ or 5.0.5+ / MySQL 4.1.13+ or MySQL 5.1.10+ Only
	*
	* @return	none
	*/
	function connect($database, $w_servername, $w_port, $w_username, $w_password, $w_usepconnect = false, $r_servername = '', $r_port = 3306, $r_username = '', $r_password = '', $r_usepconnect = false, $configfile = '', $charset = '')
	{
		$this->database = $database;

		$w_port = $w_port ? $w_port : 3306;
		$r_port = $r_port ? $r_port : 3306;

		$this->connection_master = $this->db_connect($w_servername, $w_port, $w_username, $w_password, $w_usepconnect, $configfile, $charset);
		$this->multiserver = false;
		$this->connection_slave =& $this->connection_master;

		if ($this->connection_master)
		{
			$this->select_db($this->database);
		}
	}

	/**
	* Initialize database connection(s)
	*
	* Connects to the specified master database server, and also to the slave server if it is specified
	*
	* @param	string	Name of the database server - should be either 'localhost' or an IP address
	* @param	integer	Port of the database server (usually 3306)
	* @param	string	Username to connect to the database server
	* @param	string	Password associated with the username for the database server
	* @param	boolean	Whether or not to use persistent connections to the database server
	* @param	string  Not applicable; config file for MySQLi only
	* @param	string  Force connection character set (to prevent collation errors)
	*
	* @return	boolean
	*/
	function db_connect($servername, $port, $username, $password, $usepconnect, $configfile = '', $charset = '')
	{
		if (function_exists('catch_db_error'))
		{
			set_error_handler('catch_db_error');
		}

		// catch_db_error will handle exiting, no infinite loop here
		do
		{
			$link = $this->functions[$usepconnect ? 'pconnect' : 'connect']("$servername:$port", $username, $password);
		}
		while ($link == false AND $this->reporterror);

		restore_error_handler();

		if (!empty($charset))
		{
			if (function_exists('mysql_set_charset'))
			{
				mysql_set_charset($charset);
			}
			else
			{
				$this->sql = "SET NAMES $charset";
				$this->execute_query(true, $link);
			}
		}

		return $link;
	}

	/**
	* Selects a database to use
	*
	* @param	string	The name of the database located on the database server(s)
	*
	* @return	boolean
	*/
	function select_db($database = '')
	{
		if ($database != '')
		{
			$this->database = $database;
		}

		if ($check_write = @$this->select_db_wrapper($this->database, $this->connection_master))
		{
			$this->connection_recent =& $this->connection_master;
			return true;
		}
		else
		{
			$this->connection_recent =& $this->connection_master;
			if (!file_exists(DIR . '/install/install.php'))
			{
				$this->halt('Cannot use database ' . $this->database);
			}
			return false;
		}
	}

	/**
	* Simple wrapper for select_db(), to allow argument order changes
	*
	* @param	string	Database name
	* @param	integer	Link identifier
	*
	* @return	boolean
	*/
	function select_db_wrapper($database = '', $link = null)
	{
		return $this->functions['select_db']($database, $link);
	}

	/**
	* Forces the sql_mode varaible to a specific mode. Certain modes may be
	* incompatible with vBulletin. Applies to MySQL 4.1+.
	*
	* @param	string	The mode to set the sql_mode variable to
	*/
	function force_sql_mode($mode)
	{
		$reset_errors = $this->reporterror;
		if ($reset_errors)
		{
			$this->hide_errors();
		}

		$this->query_write("SET @@sql_mode = '" . $this->escape_string($mode) . "'");

		if ($reset_errors)
		{
			$this->show_errors();
		}
	}

	/**
	* Executes an SQL query through the specified connection
	*
	* @param	boolean	Whether or not to run this query buffered (true) or unbuffered (false). Default is unbuffered.
	* @param	string	The connection ID to the database server
	*
	* @return	string
	*/
	function &execute_query($buffered = true, &$link)
	{
		$this->connection_recent =& $link;
		$this->querycount++;

		if ($queryresult = $this->functions[$buffered ? 'query' : 'query_unbuffered']($this->sql, $link))
		{
			// unset $sql to lower memory .. this isn't an error, so it's not needed
			$this->sql = '';

			return $queryresult;
		}
		else
		{
			$this->halt();

			// unset $sql to lower memory .. error will have already been thrown
			$this->sql = '';
		}
	}

	/**
	* Executes a data-writing SQL query through the 'master' database connection
	*
	* @param	string	The text of the SQL query to be executed
	* @param	boolean	Whether or not to run this query buffered (true) or unbuffered (false). Default is buffered.
	*
	* @return	string
	*/
	function query_write($sql, $buffered = true)
	{
		$this->sql =& $sql;
		return $this->execute_query($buffered, $this->connection_master);
	}

	/**
	* Executes a data-reading SQL query through the 'master' database connection
	* we don't know if the 'read' database is up to date so be on the safe side
	*
	* @param	string	The text of the SQL query to be executed
	* @param	boolean	Whether or not to run this query buffered (true) or unbuffered (false). Default is buffered.
	*
	* @return	string
	*/
	function query_read($sql, $buffered = true)
	{
		$this->sql =& $sql;
		return $this->execute_query($buffered, $this->connection_master);
	}

	/**
	* Executes a data-reading SQL query through the 'slave' database connection
	*
	* @param	string	The text of the SQL query to be executed
	* @param	boolean	Whether or not to run this query buffered (true) or unbuffered (false). Default is buffered.
	*
	* @return	string
	*/
	function query_read_slave($sql, $buffered = true)
	{
		$this->sql =& $sql;
		return $this->execute_query($buffered, $this->connection_master);
	}

	/**
	* Executes an SQL query, using either the write connection
	*
	* @deprecated	Deprecated as of 3.6. Use query_(read/write)
	*
	* @param	string	The text of the SQL query to be executed
	* @param	boolean	Whether or not to run this query buffered (true) or unbuffered (false). Default is unbuffered.
	*
	* @return	string
	*/
	function query($sql, $buffered = true)
	{
		$this->sql =& $sql;
		return $this->execute_query($buffered, $this->connection_master);
	}

	/**
	* Executes a data-reading SQL query, then returns an array of the data from the first row from the result set
	*
	* @param	string	The text of the SQL query to be executed
	* @param	string	One of (NUM, ASSOC, BOTH)
	*
	* @return	array
	*/
	function &query_first($sql, $type = DBARRAY_ASSOC)
	{
		$this->sql =& $sql;
		$queryresult = $this->execute_query(true, $this->connection_master);
		$returnarray = $this->fetch_array($queryresult, $type);
		$this->free_result($queryresult);
		return $returnarray;
	}

	/**
	* Executes a FOUND_ROWS query to get the results of SQL_CALC_FOUND_ROWS
	*
	* @return	integer
	*/
	function found_rows()
	{
		$this->sql = "SELECT FOUND_ROWS()";
		$queryresult = $this->execute_query(true, $this->connection_recent);
		$returnarray = $this->fetch_array($queryresult, DBARRAY_NUM);
		$this->free_result($queryresult);

		return intval($returnarray[0]);
	}

	/**
	* Executes a data-reading SQL query against the slave server, then returns an array of the data from the first row from the result set
	*
	* @param	string	The text of the SQL query to be executed
	* @param	string	One of (NUM, ASSOC, BOTH)
	*
	* @return	array
	*/
	function &query_first_slave($sql, $type = DBARRAY_ASSOC)
	{
		$returnarray = $this->query_first($sql, $type);
		return $returnarray;
	}

	/**
	* Executes an INSERT INTO query, using extended inserts if possible
	*
	* @param	string	Name of the table into which data should be inserted
	* @param	string	Comma-separated list of the fields to affect
	* @param	array	Array of SQL values
	* @param	boolean	Whether or not to run this query buffered (true) or unbuffered (false). Default is unbuffered.
	*
	* @return	mixed
	*/
	function &query_insert($table, $fields, &$values, $buffered = true)
	{
		return $this->insert_multiple("INSERT INTO $table $fields VALUES", $values, $buffered);
	}

	/**
	* Executes a REPLACE INTO query, using extended inserts if possible
	*
	* @param	string	Name of the table into which data should be inserted
	* @param	string	Comma-separated list of the fields to affect
	* @param	array	Array of SQL values
	* @param	boolean	Whether or not to run this query buffered (true) or unbuffered (false). Default is unbuffered.
	*
	* @return	mixed
	*/
	function &query_replace($table, $fields, &$values, $buffered = true)
	{
		return $this->insert_multiple("REPLACE INTO $table $fields VALUES", $values, $buffered);
	}

	/**
	* Executes an INSERT or REPLACE query with multiple values, splitting large queries into manageable chunks based on $this->maxpacket
	*
	* @param	string	The text of the first part of the SQL query to be executed - example "INSERT INTO table (field1, field2) VALUES"
	* @param	mixed	The values to be inserted. Example: (0 => "('value1', 'value2')", 1 => "('value3', 'value4')")
	* @param	boolean	Whether or not to run this query buffered (true) or unbuffered (false). Default is unbuffered.
	*
	* @return	mixed
	*/
	function insert_multiple($sql, &$values, $buffered)
	{
		if ($this->maxpacket == 0)
		{
			// must do a READ query on the WRITE link here!
			$vars = $this->query_write("SHOW VARIABLES LIKE 'max_allowed_packet'");
			$var = $this->fetch_row($vars);
			$this->maxpacket = $var[1];
			$this->free_result($vars);
		}

		$i = 0;
		$num_values = sizeof($values);
		$this->sql = $sql;

		while ($i < $num_values)
		{
			$sql_length = strlen($this->sql);
			$value_length = strlen("\r\n" . $values["$i"] . ",");

			if (($sql_length + $value_length) < $this->maxpacket)
			{
				$this->sql .= "\r\n" . $values["$i"] . ",";
				unset($values["$i"]);
				$i++;
			}
			else
			{
				$this->sql = (substr($this->sql, -1) == ',') ? substr($this->sql, 0, -1) : $this->sql;
				$this->execute_query($buffered, $this->connection_master);
				$this->sql = $sql;
			}
		}
		if ($this->sql != $sql)
		{
			$this->sql = (substr($this->sql, -1) == ',') ? substr($this->sql, 0, -1) : $this->sql;
			$this->execute_query($buffered, $this->connection_master);
		}

		if (sizeof($values) == 1)
		{
			return $this->insert_id();
		}
		else
		{
			return true;
		}
	}

	/**
	* Registers an SQL query to be executed at shutdown time. If shutdown functions are disabled, the query is run immediately.
	*
	* @param	string	The text of the SQL query to be executed
	* @param	mixed	(Optional) Allows particular shutdown queries to be labelled
	*
	* @return	boolean
	*/
	function shutdown_query($sql, $arraykey = -1)
	{
		if ($arraykey === -1)
		{
			$this->shutdownqueries[] = $sql;
			return true;
		}
		else
		{
			$this->shutdownqueries["$arraykey"] = $sql;
			return true;
		}
	}

	/**
	* Returns the number of rows contained within a query result set
	*
	* @param	string	The query result ID we are dealing with
	*
	* @return	integer
	*/
	function num_rows($queryresult)
	{
		return @$this->functions['num_rows']($queryresult);
	}

	/**
	* Returns the number of fields contained within a query result set
	*
	* @param	string	The query result ID we are dealing with
	*
	* @return	integer
	*/
	function num_fields($queryresult)
	{
		return @$this->functions['num_fields']($queryresult);
	}

	/**
	* Returns the name of a field from within a query result set
	*
	* @param	string	The query result ID we are dealing with
	* @param	integer	The index position of the field
	*
	* @return	string
	*/
	function field_name($queryresult, $index)
	{
		return @$this->functions['field_name']($queryresult, $index);
	}

	/**
	* Returns the ID of the item just inserted into an auto-increment field
	*
	* @return	integer
	*/
	function insert_id()
	{
		return @$this->functions['insert_id']($this->connection_master);
	}

	/**
	* Returns the name of the character set
	*
	* @return	string
	*/
	function client_encoding()
	{
		return @$this->functions['client_encoding']($this->connection_master);
	}

	/**
	* Closes the connection to the database server
	*
	* @return	integer
	*/
	function close()
	{
		return @$this->functions['close']($this->connection_master);
	}

	/**
	* Escapes a string to make it safe to be inserted into an SQL query
	*
	* @param	string	The string to be escaped
	*
	* @return	string
	*/
	function escape_string($string)
	{
		if ($this->functions['escape_string'] == $this->functions['real_escape_string'])
		{
			return $this->functions['escape_string']($string, $this->connection_master);
		}
		else
		{
			return $this->functions['escape_string']($string);
		}
	}

	/**
	* Escapes a string using the appropriate escape character for the RDBMS for use in LIKE conditions
	*
	* @param	string	The string to be escaped
	*
	* @return	string
	*/
	function escape_string_like($string)
	{
		return str_replace(array('%', '_') , array('\%' , '\_') , $this->escape_string($string));
	}

	/**
	* Takes a piece of data and prepares it to be put into an SQL query by adding quotes etc.
	*
	* @param	mixed	The data to be used
	*
	* @return	mixed	The prepared data
	*/
	function sql_prepare($value)
	{
		if (is_string($value))
		{
			return "'" . $this->escape_string($value) . "'";
		}
		else if (is_numeric($value) AND $value + 0 == $value)
		{
			return $value;
		}
		else if (is_bool($value))
		{
			return $value ? 1 : 0;
		}
		else if (is_null($value))
		{
			return "''";
		}
		else if (is_array($value))
		{
			foreach ($value as $key => $item)
			{
				$value[$key] = $this->sql_prepare($item);
			}
			return $value;
		}
		else
		{
			return "'" . $this->escape_string($value) . "'";
		}
	}

	/**
	* Fetches a row from a query result and returns the values from that row as an array
	*
	* The value of $type defines whether the array will have numeric or associative keys, or both
	*
	* @param	string	The query result ID we are dealing with
	* @param	integer	One of DBARRAY_ASSOC / DBARRAY_NUM / DBARRAY_BOTH
	*
	* @return	array
	*/
	function fetch_array($queryresult, $type = DBARRAY_ASSOC)
	{
		static $hook_code = false;

		if ($hook_code === false AND class_exists('vBulletinHook', false))
		{
			$hook_code['pre_fetch'] = vBulletinHook::fetch_hook('database_pre_fetch_array');
			$hook_code['post_fetch'] = vBulletinHook::fetch_hook('database_post_fetch_array');
		}

		if ($hook_code['pre_fetch'])
		{
			$result = false;

			eval($hook_code['pre_fetch']);

			if ($result)
			{
				return $result;
			}
		}

		$result = @$this->functions['fetch_array']($queryresult, $this->fetchtypes["$type"]);

		if ($hook_code['post_fetch'])
		{
			eval($hook_code['post_fetch']);
		}

		return $result;
	}

	/**
	* Fetches a row from a query result and returns the values from that row as an array with numeric keys
	*
	* @param	string	The query result ID we are dealing with
	*
	* @return	array
	*/
	function fetch_row($queryresult)
	{
		static $hook_code = false;

		if ($hook_code === false AND class_exists('vBulletinHook', false))
		{
			$hook_code['pre_fetch'] = vBulletinHook::fetch_hook('database_pre_fetch_row');
			$hook_code['post_fetch'] = vBulletinHook::fetch_hook('database_post_fetch_row');
		}

		if ($hook_code['pre_fetch'])
		{
			$result = false;

			eval($hook_code['pre_fetch']);

			if ($result)
			{
				return $result;
			}
		}

		$result = @$this->functions['fetch_row']($queryresult);

		if ($hook_code['post_fetch'])
		{
			eval($hook_code['post_fetch']);
		}

		return $result;
	}

	/**
	* Fetches a row information from a query result and returns the values from that row as an array
	*
	* @param	string	The query result ID we are dealing with
	*
	* @return	array
	*/
	function fetch_field($queryresult)
	{
		static $hook_code = false;

		if ($hook_code === false AND class_exists('vBulletinHook', false))
		{
			$hook_code['pre_fetch'] = vBulletinHook::fetch_hook('database_pre_fetch_field');
			$hook_code['post_fetch'] = vBulletinHook::fetch_hook('database_post_fetch_field');
		}

		if ($hook_code['pre_fetch'])
		{
			$result = false;

			eval($hook_code['pre_fetch']);

			if ($result)
			{
				return $result;
			}
		}

		$result = @$this->functions['fetch_field']($queryresult);

		if ($hook_code['post_fetch'])
		{
			eval($hook_code['post_fetch']);
		}

		return $result;
	}

	/**
	* Moves the internal result pointer within a query result set
	*
	* @param	string	The query result ID we are dealing with
	* @param	integer	The position to which to move the pointer (first position is 0)
	*
	* @return	boolean
	*/
	function data_seek($queryresult, $index)
	{
		return @$this->functions['data_seek']($queryresult, $index);
	}

	/**
	* Frees all memory associated with the specified query result
	*
	* @param	string	The query result ID we are dealing with
	*
	* @return	boolean
	*/
	function free_result($queryresult)
	{
		$this->sql = '';
		return @$this->functions['free_result']($queryresult);
	}

	/**
	* Retuns the number of rows affected by the most recent insert/replace/update query
	*
	* @return	integer
	*/
	function affected_rows()
	{
		$this->rows = $this->functions['affected_rows']($this->connection_recent);
		return $this->rows;
	}

	/**
	* Ping connection and reconnect
	* Don't use this in a manner that could cause a loop condition
	*
	*/
	function ping()
	{
		if (!@$this->functions['ping']($this->connection_master))
		{
			$this->close();
			// make database connection
			$this->connect(
				$this->registry->config['Database']['dbname'],
				$this->registry->config['MasterServer']['servername'],
				$this->registry->config['MasterServer']['port'],
				$this->registry->config['MasterServer']['username'],
				$this->registry->config['MasterServer']['password'],
				$this->registry->config['MasterServer']['usepconnect'],
				$this->registry->config['SlaveServer']['servername'],
				$this->registry->config['SlaveServer']['port'],
				$this->registry->config['SlaveServer']['username'],
				$this->registry->config['SlaveServer']['password'],
				$this->registry->config['SlaveServer']['usepconnect'],
				$this->registry->config['Mysqli']['ini_file'],
				(isset($this->registry->config['Mysqli']['charset']) ? $this->registry->config['Mysqli']['charset'] : '')
			);
		}
	}

	/**
	* Lock tables
	*
	* @param	mixed	List of tables to lock
	* @param	string	Type of lock to perform
	*
	*/
	function lock_tables($tablelist)
	{
		if (!empty($tablelist) AND is_array($tablelist))
		{
			// Don't lock tables if we know we might get stuck with them locked (pconnect = true)
			// mysqli doesn't support pconnect! YAY!
			if (strtolower($this->registry->config['Database']['dbtype']) != 'mysqli' AND $this->registry->config['MasterServer']['usepconnect'])
			{
				return;
			}

			$sql = '';
			foreach($tablelist AS $name => $type)
			{
				$sql .= (!empty($sql) ? ', ' : '') . TABLE_PREFIX . $name . " " . $type;
			}

			$this->query_write("LOCK TABLES $sql");
			$this->locked = true;

		}
	}

	/**
	* Unlock tables
	*
	*/
	function unlock_tables()
	{
		# must be called from exec_shutdown as tables can get stuck locked if pconnects are enabled
		# note: the above case never actually happens as we skip the lock if pconnects are enabled (to be safe) =)
		if ($this->locked)
		{
			$this->query_write("UNLOCK TABLES");
		}
	}

	/**
	* Returns the text of the error message from previous database operation
	*
	* @return	string
	*/
	function error()
	{
		if ($this->connection_recent === null)
		{
			$this->error = '';
		}
		else
		{
			$this->error = $this->functions['error']($this->connection_recent);
		}
		return $this->error;
	}

	/**
	* Returns the numerical value of the error message from previous database operation
	*
	* @return	integer
	*/
	function errno()
	{
		if ($this->connection_recent === null)
		{
			$this->errno = 0;
		}
		else
		{
			$this->errno = $this->functions['errno']($this->connection_recent);
		}
		return $this->errno;
	}

	/**
	* Switches database error display ON
	*/
	function show_errors()
	{
		$this->reporterror = true;
	}

	/**
	* Switches database error display OFF
	*/
	function hide_errors()
	{
		$this->reporterror = false;
	}

	/**
	* Halts execution of the entire system and displays an error message
	*
	* @param	string	Text of the error message. Leave blank to use $this->sql as error text.
	*
	* @return	integer
	*/
	function halt($errortext = '')
	{
		global $vbulletin;

		if ($this->connection_recent)
		{
			$this->error = $this->error($this->connection_recent);
			$this->errno = $this->errno($this->connection_recent);
		}

		if ($this->reporterror)
		{
			if ($errortext == '')
			{
				$this->sql = "Invalid SQL:\r\n" . chop($this->sql) . ';';
				$errortext =& $this->sql;
			}

			$vboptions      =& $vbulletin->options;
			$technicalemail =& $vbulletin->config['Database']['technicalemail'];
			$bbuserinfo     =& $vbulletin->userinfo;
			$requestdate    = date('l, F jS Y @ h:i:s A', TIMENOW);
			$date           = date('l, F jS Y @ h:i:s A');
			$scriptpath     = str_replace('&amp;', '&', $vbulletin->scriptpath);
			$referer        = REFERRER;
			$ipaddress      = IPADDRESS;
			$classname      = get_class($this);

			if ($this->connection_recent)
			{
				$this->hide_errors();
				list($mysqlversion) = $this->query_first("SELECT VERSION() AS version", DBARRAY_NUM);
				$this->show_errors();
			}

			$display_db_error = (VB_AREA == 'Upgrade' OR VB_AREA == 'Install' OR $vbulletin->userinfo['usergroupid'] == 6 OR ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions));

			// Hide the MySQL Version if its going in the source
			if (!$display_db_error)
			{
				$mysqlversion = '';
			}

			eval('$message = "' . str_replace('"', '\"', file_get_contents(DIR . '/includes/database_error_message.html')) . '";');

			// add a backtrace to the message
			if ($vbulletin->debug)
			{
				$trace = debug_backtrace();
				$trace_output = "\n";

				foreach ($trace AS $index => $trace_item)
				{
					$param = (in_array($trace_item['function'], array('require', 'require_once', 'include', 'include_once')) ? $trace_item['args'][0] : '');

					// remove path
					$param = str_replace(DIR, '[path]', $param);
					$trace_item['file'] = str_replace(DIR, '[path]', $trace_item['file']);

					$trace_output .= "#$index $trace_item[class]$trace_item[type]$trace_item[function]($param) called in $trace_item[file] on line $trace_item[line]\n";
				}

				$message .= "\n\nStack Trace:\n$trace_output\n";
			}

			require_once(DIR . '/includes/functions_log_error.php');
			if (function_exists('log_vbulletin_error'))
			{
				log_vbulletin_error($message, 'database');
			}

			if ($technicalemail != '' AND !$vbulletin->options['disableerroremail'] AND verify_email_vbulletin_error($this->errno, 'database'))
			{
				// If vBulletinHook is defined then we know that options are loaded, so we can then use vbmail
				if (class_exists('vBulletinHook', false))
				{
					@vbmail($technicalemail, $this->appshortname . ' Database Error!', $message, true, $technicalemail);
				}
				else
				{
					@mail($technicalemail, $this->appshortname . ' Database Error!', preg_replace("#(\r\n|\r|\n)#s", (@ini_get('sendmail_path') === '') ? "\r\n" : "\n", $message), "From: $technicalemail");
				}
			}

			if (defined('STDIN'))
			{
				echo $message;
				exit;
			}

			// send ajax reponse after sending error email
			if ($vbulletin->GPC['ajax'])
			{
				require_once(DIR . '/includes/class_xml.php');
				$xml = new vB_AJAX_XML_Builder($vbulletin, 'text/xml');

				$error = '<p>Database Error</p>';
				if ($vbulletin->debug OR VB_AREA == 'Upgrade')
				{
				$error .= "\r\n\r\n$errortext";
				$error .= "\r\n\r\n{$this->error}";
				}

				eval('$ajaxmessage = "' . str_replace('"', '\"', file_get_contents(DIR . '/includes/database_error_message_ajax.html')) . '";');

				$xml->add_group('errors');
					$xml->add_tag('error', $error);
					$xml->add_tag('error_html', $ajaxmessage);
				$xml->close_group('errors');

				$xml->print_xml();
			}

			if (!headers_sent())
			{
				if (SAPI_NAME == 'cgi' OR SAPI_NAME == 'cgi-fcgi')
				{
					header('Status: 503 Service Unavailable');
				}
				else
				{
					header($_SERVER['SERVER_PROTOCOL'] . ' 503 Service Unavailable');
				}
			}

			if ($display_db_error)
			{
				// display error message on screen
				$message = '<form><textarea rows="15" cols="70" wrap="off" id="message">' . htmlspecialchars_uni($message) . '</textarea></form>';
			}
			else
			{
				// display hidden error message
				$message = "\r\n<!--\r\n" . htmlspecialchars_uni($message) . "\r\n-->\r\n";
			}

			if ($vbulletin->options['bburl'])
			{
				$imagepath = $vbulletin->options['bburl'];
			}
			else
			{
				// this might not work with too many slashes in the archive
				$imagepath = (VB_AREA == 'Forum' ? '.' : '..');
			}

			eval('$message = "' . str_replace('"', '\"', file_get_contents(DIR . '/includes/database_error_page.html')) . '";');

			// This is needed so IE doesn't show the pretty error messages
			$message .= str_repeat(' ', 512);
			die($message);
		}
		else if (!empty($errortext))
		{
			$this->error = $errortext;
		}
	}
}

// #############################################################################
// MySQLi Database Class

/**
* Class to interface with a MySQL 4.1 database
*
* This class also handles data replication between a master and slave(s) servers
*
* @package	vBulletin
* @version	$Revision: 46878 $
* @date		$Date: 2011-08-01 16:35:31 -0700 (Mon, 01 Aug 2011) $
*/
class vB_Database_MySQLi extends vB_Database
{
	/**
	* Array of function names, mapping a simple name to the RDBMS specific function name
	*
	* @var	array
	*/
	var $functions = array(
		'connect'            => 'mysqli_real_connect',
		'pconnect'           => 'mysqli_real_connect', // mysqli doesn't support persistent connections THANK YOU!
		'select_db'          => 'mysqli_select_db',
		'query'              => 'mysqli_query',
		'query_unbuffered'   => 'mysqli_unbuffered_query',
		'fetch_row'          => 'mysqli_fetch_row',
		'fetch_array'        => 'mysqli_fetch_array',
		'fetch_field'        => 'mysqli_fetch_field',
		'free_result'        => 'mysqli_free_result',
		'data_seek'          => 'mysqli_data_seek',
		'error'              => 'mysqli_error',
		'errno'              => 'mysqli_errno',
		'affected_rows'      => 'mysqli_affected_rows',
		'num_rows'           => 'mysqli_num_rows',
		'num_fields'         => 'mysqli_num_fields',
		'field_name'         => 'mysqli_field_tell',
		'insert_id'          => 'mysqli_insert_id',
		'escape_string'      => 'mysqli_real_escape_string',
		'real_escape_string' => 'mysqli_real_escape_string',
		'close'              => 'mysqli_close',
		'client_encoding'    => 'mysqli_client_encoding',
		'ping'               => 'mysqli_ping',
	);

	/**
	* Array of constants for use in fetch_array
	*
	* @var	array
	*/
	var $fetchtypes = array(
		DBARRAY_NUM   => MYSQLI_NUM,
		DBARRAY_ASSOC => MYSQLI_ASSOC,
		DBARRAY_BOTH  => MYSQLI_BOTH
	);

	/**
	* Initialize database connection(s)
	*
	* Connects to the specified master database server, and also to the slave server if it is specified
	*
	* @param	string  Name of the database server - should be either 'localhost' or an IP address
	* @param	integer	Port of the database server - usually 3306
	* @param	string  Username to connect to the database server
	* @param	string  Password associated with the username for the database server
	* @param	string  Persistent Connections - Not supported with MySQLi
	* @param	string  Configuration file from config.php.ini (my.ini / my.cnf)
	* @param	string  Mysqli Connection Charset PHP 5.1.0+ or 5.0.5+ / MySQL 4.1.13+ or MySQL 5.1.10+ Only
	*
	* @return	object  Mysqli Resource
	*/
	function db_connect($servername, $port, $username, $password, $usepconnect, $configfile = '', $charset = '')
	{
		if (function_exists('catch_db_error'))
		{
			set_error_handler('catch_db_error');
		}

		$link = mysqli_init();
		# Set Options Connection Options
		if (!empty($configfile))
		{
			mysqli_options($link, MYSQLI_READ_DEFAULT_FILE, $configfile);
		}

		// this will execute at most 5 times, see catch_db_error()
		do
		{
			$connect = $this->functions['connect']($link, $servername, $username, $password, '', $port);
		}
		while ($connect == false AND $this->reporterror);

		restore_error_handler();

		if (!empty($charset))
		{
			if (function_exists('mysqli_set_charset'))
			{
				mysqli_set_charset($link, $charset);
			}
			else
			{
				$this->sql = "SET NAMES $charset";
				$this->execute_query(true, $link);
			}
		}

		return (!$connect) ? false : $link;
	}

	/**
	* Executes an SQL query through the specified connection
	*
	* @param	boolean	Whether or not to run this query buffered (true) or unbuffered (false). Default is unbuffered.
	* @param	string	The connection ID to the database server
	*
	* @return	string
	*/
	function &execute_query($buffered = true, &$link)
	{
		$this->connection_recent =& $link;
		$this->querycount++;

		if ($queryresult = mysqli_query($link, $this->sql, ($buffered ? MYSQLI_STORE_RESULT : MYSQLI_USE_RESULT)))
		{
			// unset $sql to lower memory .. this isn't an error, so it's not needed
			$this->sql = '';

			return $queryresult;
		}
		else
		{
			$this->halt();

			// unset $sql to lower memory .. error will have already been thrown
			$this->sql = '';
		}
	}

	/**
	* Simple wrapper for select_db(), to allow argument order changes
	*
	* @param	string	Database name
	* @param	integer	Link identifier
	*
	* @return	boolean
	*/
	function select_db_wrapper($database = '', $link = null)
	{
		return $this->functions['select_db']($link, $database);
	}

	/**
	* Escapes a string to make it safe to be inserted into an SQL query
	*
	* @param	string	The string to be escaped
	*
	* @return	string
	*/
	function escape_string($string)
	{
		return $this->functions['real_escape_string']($this->connection_master, $string);
	}

	/**
	* Returns the name of a field from within a query result set
	*
	* @param	string	The query result ID we are dealing with
	* @param	integer	The index position of the field
	*
	* @return	string
	*/
	function field_name($queryresult, $index)
	{
		$field = @$this->functions['fetch_field']($queryresult);
		return $field->name;
	}

	/**
	* Switches database error display ON
	*/
	function show_errors()
	{
		$this->reporterror = true;
		mysqli_report(MYSQLI_REPORT_ERROR);
	}

	/**
	* Switches database error display OFF
	*/
	function hide_errors()
	{
		$this->reporterror = false;
		mysqli_report(MYSQLI_REPORT_OFF);
	}

}

// #############################################################################
// datastore class

/**
* Class for fetching and initializing the vBulletin datastore from the database
*
* @package	vBulletin
* @version	$Revision: 46878 $
* @date		$Date: 2011-08-01 16:35:31 -0700 (Mon, 01 Aug 2011) $
*/
class vB_Datastore
{
	/**
	* Default items that are always loaded by fetch();
	*
	* @var	array
	*/
	var $defaultitems = array(
		'options',
		'bitfields',
		'attachmentcache',
		'forumcache',
		'usergroupcache',
		'stylecache',
		'languagecache',
		'products',
		'pluginlist',
		'cron',
		'profilefield',
		'loadcache',
		'noticecache'
	);

	/**
	 * All of the entries that have already been fetched
	 *
	 * @var array string
	 */
	static $registered = array();

	/**
	* This variable should be set to be a reference to the registry object
	*
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* This variable should be set to be a reference to the database object
	*
	* @var	vB_Database
	*/
	var $dbobject = null;

	/**
	* Unique prefix for item's title, required for multiple forums on the same server using the same classes that read/write to memory
	*
	* @var	string
	*/
	var $prefix = '';

	/**
	 * Whether we have verified that options were loaded correctly.
	 *
	 * @var bool
	 */
	var $checked_options;

	/**
	* Constructor - establishes the database object to use for datastore queries
	*
	* @param	vB_Registry	The registry object
	* @param	vB_Database	The database object
	*/
	function vB_Datastore(&$registry, &$dbobject)
	{
		$this->registry =& $registry;
		$this->dbobject =& $dbobject;

		$this->prefix =& $this->registry->config['Datastore']['prefix'];

		if (defined('SKIP_DEFAULTDATASTORE'))
		{
			$this->defaultitems = array('options', 'bitfields', 'pluginlist');
		}

		if (!is_object($registry))
		{
			trigger_error('<strong>vB_Datastore</strong>: $this->registry is not an object', E_USER_ERROR);
		}
		if (!is_object($dbobject))
		{
			trigger_error('<strong>vB_Datastore</strong>: $this->dbobject is not an object!', E_USER_ERROR);
		}
	}

	/**
	* Sorts the data returned from the cache and places it into appropriate places
	*
	* @param	string	The name of the data item to be processed
	* @param	mixed	The data associated with the title
	* @param	integer	If the data needs to be unserialized, 0 = no, 1 = yes, 2 = auto detect
	*
	* @return	boolean
	*/
	function register($title, $data, $unserialize_detect = 2)
	{
		// specifies whether or not $data should be an array
		$try_unserialize = (($unserialize_detect == 2) AND ($data[0] == 'a' AND $data[1] == ':'));

		if ($try_unserialize OR $unserialize_detect == 1)
		{
			// unserialize returned an error so return false
			if (($data = unserialize($data)) === false)
			{
				return false;
			}
		}

		if ($title == 'bitfields')
		{
			$registry =& $this->registry;

			foreach (array_keys($data) AS $group)
			{
				$registry->{'bf_' . $group} =& $data["$group"];

				$group_prefix = 'bf_' . $group . '_';
				$group_info =& $data["$group"];

				foreach (array_keys($group_info) AS $subgroup)
				{
					$registry->{$group_prefix . $subgroup} =& $group_info["$subgroup"];
				}
			}
		}
		else if (!empty($title))
		{
			$this->registry->$title = $data;
		}

		// Ensure items are not refetched
		self::$registered[] = $title;

		return true;
	}

	/**
	 * Prepares a list of items for fetching.
	 * Items that are already fetched are skipped.
	 *
	 * @param array string $items				- Array of item titles that are required
	 * @return array string						- An array of items that need to be fetched
	 */
	function prepare_itemarray($items)
	{
		if ($items)
		{
			if (is_array($items))
			{
				$itemarray = $items;
			}
			else
			{
				$itemarray = explode(',', $items);

				foreach($itemarray AS &$title)
				{
					$title = trim($title);
				}
			}
			// Include default items
			$itemarray = array_merge($itemarray, $this->defaultitems);
		}
		else
		{
			$itemarray = $this->defaultitems;
		}

		// Remove anything that is already loaded
		$itemarray = array_diff($itemarray, vB_DataStore::$registered);

		return $itemarray;
	}

	/**
	 * Prepares an array of items into a list.
	 * The result is a comma delimited, db escaped, quoted list for use in SQL.
	 *
	 * @param array string $items				- An array of item titles
	 * @param bool $prepare_items				- Wether to check the items first
	 *
	 * @return string							- A sql safe comma delimited list
	 */
	function prepare_itemlist($items, $prepare_items = false)
	{
		if (is_string($items) OR $prepare_items)
		{
			$items = $this->prepare_itemarray($items);
		}

		if (!sizeof($items))
		{
			return false;
		}

		foreach ($items AS &$item)
		{
			$item = "'" . $this->dbobject->escape_string($item) . "'";
		}

		return implode(',', $items);
	}

	/**
	* Fetches the contents of the datastore from the database
	*
	* @param	array	Array of items to fetch from the datastore
	*
	* @return	boolean
	*/
	function fetch($items)
	{
		if ($items = $this->prepare_itemlist($items, true))
		{
			$result = $this->do_db_fetch($items);
			if (!$result)
			{
				return false;
			}
		}

		$this->check_options();
		return true;
	}

	/**
	* Performs the actual fetching of the datastore items for the database, child classes may use this
	*
	* @param	string	title of the datastore item
	*
	* @return	bool	Valid Query?
	*/
	function do_db_fetch($itemlist)
	{
		$db =& $this->dbobject;

		$db->hide_errors();
		$dataitems = $db->query_read("
			SELECT *
			FROM " . TABLE_PREFIX . "datastore
			WHERE title IN ($itemlist)
		");
		$db->show_errors();
		while ($dataitem = $db->fetch_array($dataitems))
		{
			$this->register($dataitem['title'], $dataitem['data'], (isset($dataitem['unserialize']) ? $dataitem['unserialize'] : 2));
		}
		$db->free_result($dataitems);

		return (!$db->errno());
	}

	/**
	* Checks that the options item has come out of the datastore correctly
	* and sets the 'versionnumber' variable
	*/
	function check_options()
	{
		if ($this->checked_options)
		{
			return;
		}

		if (!isset($this->registry->options['templateversion']))
		{
			// fatal error - options not loaded correctly
			require_once(DIR . '/includes/adminfunctions.php');
			require_once(DIR . '/includes/functions.php');
			$this->register('options', build_options(), 0);
		}

		// set the short version number
		$this->registry->options['simpleversion'] = SIMPLE_VERSION . (isset($this->registry->config['Misc']['jsver']) ? $this->registry->config['Misc']['jsver'] : '');

		// set the version number variable
		$this->registry->versionnumber =& $this->registry->options['templateversion'];

		$this->checked_options = true;
	}
}

// #############################################################################
// input handler class

/**#@+
* Ways of cleaning input. Should be mostly self-explanatory.
*/
define('TYPE_NOCLEAN',      0); // no change

define('TYPE_BOOL',     1); // force boolean
define('TYPE_INT',      2); // force integer
define('TYPE_UINT',     3); // force unsigned integer
define('TYPE_NUM',      4); // force number
define('TYPE_UNUM',     5); // force unsigned number
define('TYPE_UNIXTIME', 6); // force unix datestamp (unsigned integer)
define('TYPE_STR',      7); // force trimmed string
define('TYPE_NOTRIM',   8); // force string - no trim
define('TYPE_NOHTML',   9); // force trimmed string with HTML made safe
define('TYPE_ARRAY',   10); // force array
define('TYPE_FILE',    11); // force file
define('TYPE_BINARY',  12); // force binary string
define('TYPE_NOHTMLCOND', 13); // force trimmed string with HTML made safe if determined to be unsafe

define('TYPE_ARRAY_BOOL',     101);
define('TYPE_ARRAY_INT',      102);
define('TYPE_ARRAY_UINT',     103);
define('TYPE_ARRAY_NUM',      104);
define('TYPE_ARRAY_UNUM',     105);
define('TYPE_ARRAY_UNIXTIME', 106);
define('TYPE_ARRAY_STR',      107);
define('TYPE_ARRAY_NOTRIM',   108);
define('TYPE_ARRAY_NOHTML',   109);
define('TYPE_ARRAY_ARRAY',    110);
define('TYPE_ARRAY_FILE',     11);  // An array of "Files" behaves differently than other <input> arrays. TYPE_FILE handles both types.
define('TYPE_ARRAY_BINARY',   112);
define('TYPE_ARRAY_NOHTMLCOND',113);

define('TYPE_ARRAY_KEYS_INT', 202);
define('TYPE_ARRAY_KEYS_STR', 207);

define('TYPE_CONVERT_SINGLE', 100); // value to subtract from array types to convert to single types
define('TYPE_CONVERT_KEYS',   200); // value to subtract from array => keys types to convert to single types
/**#@-*/

// temporary
define('INT',        TYPE_INT);
define('STR',        TYPE_STR);
define('STR_NOHTML', TYPE_NOHTML);
define('FILE',       TYPE_FILE);

/**
* Class to handle and sanitize variables from GET, POST and COOKIE etc
*
* @package	vBulletin
* @version	$Revision: 46878 $
* @date		$Date: 2011-08-01 16:35:31 -0700 (Mon, 01 Aug 2011) $
*/
class vB_Input_Cleaner
{
	/**
	* Translation table for short name to long name
	*
	* @var    array
	*/
	var $shortvars = array(
		'f'     => 'forumid',
		't'     => 'threadid',
		'p'     => 'postid',
		'u'     => 'userid',
		'a'     => 'announcementid',
		'c'     => 'calendarid',
		'e'     => 'eventid',
		'q'     => 'query',
		'pp'    => 'perpage',
		'page'  => 'pagenumber',
		'sort'  => 'sortfield',
		'order' => 'sortorder',
	);

	/**
	* Translation table for short superglobal name to long superglobal name
	*
	* @var     array
	*/
	var $superglobal_lookup = array(
		'g' => '_GET',
		'p' => '_POST',
		'r' => '_REQUEST',
		'c' => '_COOKIE',
		's' => '_SERVER',
		'e' => '_ENV',
		'f' => '_FILES'
	);

	/**
	* System state. The complete URL of the current page, without sessionhash
	*
	* @var	string
	*/
	var $scriptpath = '';

	/**
	* Reload URL. Complete URL of the current page including sessionhash
	*
	* @var	string
	*/
	var $reloadurl = '';

	/**
	* System state. The complete URL of the page for Who's Online purposes
	*
	* @var	string
	*/
	var $wolpath = '';

	/**
	* System state. The complete URL of the referring page
	*
	* @var	string
	*/
	var $url = '';

	/**
	* System state. The IP address of the current visitor
	*
	* @var	string
	*/
	var $ipaddress = '';

	/**
	* System state. An attempt to find a second IP for the current visitor (proxy etc)
	*
	* @var	string
	*/
	var $alt_ip = '';

	/**
	* A reference to the main registry object
	*
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* Keep track of variables that have already been cleaned
	*
	* @var	array
	*/
	var $cleaned_vars = array();

	/**
	* Constructor
	*
	* First, reverses the effects of magic quotes on GPC
	* Second, translates short variable names to long (u --> userid)
	* Third, deals with $_COOKIE[userid] conflicts
	*
	* @param	vB_Registry	The instance of the vB_Registry object
	*/
	function vB_Input_Cleaner(&$registry)
	{
		$this->registry =& $registry;

		if (!is_array($GLOBALS))
		{
			die('<strong>Fatal Error:</strong> Invalid URL.');
		}

		// resolve the request URL
		$this->resolve_request_url($registry);

		// store a relative path that includes the sessionhash for reloadurl
		$registry->reloadurl = $this->xss_clean($this->add_query(VB_URL_PATH, VB_URL_QUERY_RAW));
		// store the current script
		$registry->script = SCRIPT;

		// store the scriptpath
		$registry->scriptpath = $this->xss_clean($this->add_query(VB_URL_PATH, VB_URL_QUERY));

		// overwrite GET[x] and REQUEST[x] with POST[x] if it exists (overrides server's GPC order preference)
		if ($_SERVER['REQUEST_METHOD'] == 'POST')
		{
			foreach (array_keys($_POST) AS $key)
			{
				if (isset($_GET["$key"]))
				{
					$_GET["$key"] = $_REQUEST["$key"] = $_POST["$key"];
				}
			}
		}

		// deal with session bypass situation
		if (!defined('SESSION_BYPASS'))
		{
			define('SESSION_BYPASS', !empty($_REQUEST['bypass']));
		}

		// reverse the effects of magic quotes if necessary
		if (function_exists('get_magic_quotes_gpc') AND get_magic_quotes_gpc())
		{
			$this->stripslashes_deep($_REQUEST); // needed for some reason (at least on php5 - not tested on php4)
			$this->stripslashes_deep($_GET);
			$this->stripslashes_deep($_POST);
			$this->stripslashes_deep($_COOKIE);

			if (is_array($_FILES))
			{
				foreach ($_FILES AS $key => $val)
				{
					$_FILES["$key"]['tmp_name'] = str_replace('\\', '\\\\', $val['tmp_name']);
				}
				$this->stripslashes_deep($_FILES);
			}
		}

		//28997 -- set_magic_quotes_runtime is throws deprecation warnings in 5.3.
		//We still need it (somebody could still turn it on, wj, so supress the warning (the only thing
		//we can do).  Add check for function so that 6.0 (or whichever) doesn't mysteriously
		//break because the "function doesn't exist" error gets supressed.
		if (function_exists('set_magic_quotes_runtime'))
		{
			@set_magic_quotes_runtime(0);
			@ini_set('magic_quotes_sybase', 0);
		}

		foreach (array('_GET', '_POST') AS $arrayname)
		{
			if (isset($GLOBALS["$arrayname"]['do']))
			{
				$GLOBALS["$arrayname"]['do'] = trim($GLOBALS["$arrayname"]['do']);
			}

			$this->convert_shortvars($GLOBALS["$arrayname"]);
		}

		// set the AJAX flag if we have got an AJAX submission
		// unless the request explictly doesn't want us to.  The problem with this is that it hits any XMLHttpRequest
		// even if we have a request for which the ajax handling is not appropriate (for example JQUERY mobile which
		// uses XMLHttpRequest for everything and expects html to come back).  Ideally we'd use a less blunt force
		// approach to handling AJAX behavior in the first place, but this allows specific requests to avoid it.
		if ($_SERVER['REQUEST_METHOD'] == 'POST' AND $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest' AND
			!(isset($_REQUEST['forcenoajax']) AND $_REQUEST['forcenoajax']))
		{
			$_POST['ajax'] = $_REQUEST['ajax'] = 1;
		}

		// reverse the effects of register_globals if necessary
		if (@ini_get('register_globals') OR !@ini_get('gpc_order'))
		{
			foreach ($this->superglobal_lookup AS $arrayname)
			{
				$registry->superglobal_size["$arrayname"] = sizeof($GLOBALS["$arrayname"]);

				foreach (array_keys($GLOBALS["$arrayname"]) AS $varname)
				{
					// make sure we dont unset any global arrays like _SERVER
					if (!in_array($varname, $this->superglobal_lookup))
					{
						unset($GLOBALS["$varname"]);
					}
				}
			}
		}
		else
		{
			foreach ($this->superglobal_lookup AS $arrayname)
			{
				$registry->superglobal_size["$arrayname"] = sizeof($GLOBALS["$arrayname"]);
			}
		}

		// deal with cookies that may conflict with _GET and _POST data, and create our own _REQUEST with no _COOKIE input
		foreach (array_keys($_COOKIE) AS $varname)
		{
			unset($_REQUEST["$varname"]);
			if (isset($_POST["$varname"]))
			{
				$_REQUEST["$varname"] =& $_POST["$varname"];
			}
			else if (isset($_GET["$varname"]))
			{
				$_REQUEST["$varname"] =& $_GET["$varname"];
			}
		}

		// fetch client IP address
		$registry->ipaddress = $this->fetch_ip();
		define('IPADDRESS', $registry->ipaddress);

		// attempt to fetch IP address from behind proxies - useful, but don't rely on it...
		$registry->alt_ip = $this->fetch_alt_ip();
		define('ALT_IP', $registry->alt_ip);

		// fetch url of current page for Who's Online
		if (!defined('SKIP_WOLPATH') OR !SKIP_WOLPATH)
		{
			$registry->wolpath = $this->fetch_wolpath();
			define('WOLPATH', $registry->wolpath);
		}

		// define session constants
		define('SESSION_HOST',   substr($registry->ipaddress, 0, 15));

		// define some useful contants related to environment
		define('USER_AGENT',     $_SERVER['HTTP_USER_AGENT']);
		define('REFERRER',       $_SERVER['HTTP_REFERER']);
		
		// All requests passed from API client should be in UTF-8 encoding and we need to convert it back to vB's current encoding.
		// We also need to do this this for the ajax requests.
		if ($_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest' OR (defined('VB_API') AND VB_API === true))
		{
			define('NEED_DECODE', true);
		}
		
	}

	/**
	 * Resolves information about the request URL.
	 */
	function resolve_request_url($registry)
	{
		// Get server port
		$port = intval($_SERVER['SERVER_PORT']);
		$port = in_array($port, array(80, 443)) ? '' : ':' . $port;

		// resolve the request scheme
		$scheme = ((':443' == $port) OR (isset($_SERVER['HTTPS']) AND $_SERVER['HTTPS'] AND ($_SERVER['HTTPS'] != 'off'))) ? 'https://' : 'http://';

		$host = $this->fetch_server_value('HTTP_HOST');
		$name = $this->fetch_server_value('SERVER_NAME');

		// prefer the one that's most qualified
		$host = (substr_count($host, '.') > substr_count($name, '.') ? $host : $name);

		// resolve the query
		$query = ($query = $this->fetch_server_value('QUERY_STRING')) ? '?' . $query : '';

		// resolve the path and query
		if (!($scriptpath = $this->fetch_server_value('REQUEST_URI')))
		{
			if (!($scriptpath = $this->fetch_server_value('UNENCODED_URL')))
			{
				$scriptpath = $this->fetch_server_value('HTTP_X_REWRITE_URL');
			}
		}

		if ($scriptpath)
		{
			// already have the query
			if ($scriptpath)
			{
				$query = '';
			}
		}
		else
		{
			// server hasn't provided a URI, try to resolve one
			if (!$scriptpath = $this->fetch_server_value('PATH_INFO'))
			{
				if (!$scriptpath = $this->fetch_server_value('REDIRECT_URL'))
				{
					if (!($scriptpath = $this->fetch_server_value('URL')))
					{
						if (!($scriptpath = $this->fetch_server_value('PHP_SELF')))
						{
							$scriptpath = $this->fetch_server_value('SCRIPT_NAME');
						}
					}
				}
			}
		}

		// build the URL
		$url = $scheme . $host . '/' . ltrim($scriptpath, '/\\') . $query;

		// store a literal version
		define('VB_URL', $url);

		// check relative path
		if (defined('VB_RELATIVE_PATH'))
		{
			define('VB_URL_RELATIVE_PATH', trim(VB_RELATIVE_PATH, '/') . '/');
		}
		else
		{
			define('VB_URL_RELATIVE_PATH', '');
		}

		// Set URL info
		$url_info = @parse_url(VB_URL);
		$url_info['path'] = '/' . ltrim($url_info['path'], '/\\');
		$url_info['query_raw'] = (isset($url_info['query']) ? $url_info['query'] : '');
		$url_info['query'] = $this->strip_sessionhash($url_info['query']);
		$url_info['query'] = trim($url_info['query'], '?&') ? $url_info['query'] : '';

		/*
			values seen in the wild:

			CGI+suexec:
			SCRIPT_NAME: /vb4/admincp/index.php
			ORIG_SCRIPT_NAME: /cgi-sys/php53-fcgi-starter.fcgi

			CGI #1:
			SCRIPT_NAME: /index.php
			ORIG_SCRIPT_NAME: /search/foo

			CGI #2:
			SCRIPT_NAME: /index.php/search/foo
			ORIG_SCRIPT_NAME: /index.php

		*/

		if (substr(PHP_SAPI, -3) == 'cgi' AND (isset($_SERVER['ORIG_SCRIPT_NAME']) AND !empty($_SERVER['ORIG_SCRIPT_NAME'])))
		{
			if (substr($_SERVER['SCRIPT_NAME'], 0, strlen($_SERVER['ORIG_SCRIPT_NAME'])) == $_SERVER['ORIG_SCRIPT_NAME'])
			{
				// cgi #2 above
				$url_info['script'] = $_SERVER['ORIG_SCRIPT_NAME'];
			}
			else
			{
				// cgi #1 and CGI+suexec above
				$url_info['script'] = $_SERVER['SCRIPT_NAME'];
			}
		}
		else
		{
			$url_info['script'] = (isset($_SERVER['ORIG_SCRIPT_NAME']) AND !empty($_SERVER['ORIG_SCRIPT_NAME'])) ? $_SERVER['ORIG_SCRIPT_NAME'] : $_SERVER['SCRIPT_NAME'];
		}
		$url_info['script'] = '/' . ltrim($url_info['script'], '/\\');


		// define constants
		define('VB_URL_SCHEME',      $url_info['scheme']);
		define('VB_URL_HOST',        $url_info['host']);
		define('VB_URL_PORT',        $port);
		define('VB_URL_SCRIPT_PATH', rtrim(dirname($url_info['script']), '/\\') . '/');
		define('VB_URL_SCRIPT',      basename($url_info['script']));
		define('VB_URL_PATH',        urldecode($url_info['path']));
		define('VB_URL_PATH_RAW',    $url_info['path']);
		define('VB_URL_QUERY',       $url_info['query'] ? $url_info['query'] : '');
		define('VB_URL_QUERY_RAW',   $url_info['query_raw']);
		define('VB_URL_CLEAN',       $this->xss_clean($this->strip_sessionhash(VB_URL)));
		define('VB_URL_WEBROOT',     $this->xss_clean(VB_URL_SCHEME . '://' . VB_URL_HOST . VB_URL_PORT));
		define('VB_URL_BASE_PATH',   $this->xss_clean(VB_URL_SCHEME . '://' . VB_URL_HOST . VB_URL_PORT . VB_URL_SCRIPT_PATH . VB_URL_RELATIVE_PATH));

		// legacy constants
		define('SCRIPT',       $_SERVER['SCRIPT_NAME']);
		define('SCRIPTPATH',   $this->xss_clean($this->add_query(VB_URL_PATH)));
		define('REQ_PROTOCOL', $url_info['scheme']);
		define('VB_HTTP_HOST', $url_info['host']);
	}

	/**
	 * Fetches a value from $_SERVER or $_ENV
	 *
	 * @param string $name
	 * @return string
	 */
	function fetch_server_value($name)
	{
		if (isset($_SERVER[$name]) AND $_SERVER[$name])
		{
			return $_SERVER[$name];
		}

		if (isset($_ENV[$name]) AND $_ENV[$name])
		{
			return $_ENV[$name];
		}

		return false;
	}


	/**
	 * Adds a query string to a path, fixing the query characters.
	 *
	 * @param 	string		The path to add the query to
	 * @param 	string		The query string to add to the path
	 *
	 * @return	string		The resulting string
	 */
	function add_query($path, $query = false)
	{
		if (false === $query)
		{
			$query = VB_URL_QUERY;
		}

		if (!$query OR !($query = trim($query, '?&')))
		{
			return $path;
		}

		return $path . '?' . $query;
	}

	/**
	 * Adds a fragment to a path
	 *
	 * @param 	string		The path to add the fragment to
	 * @param 	string		The fragment to add to the path
	 *
	 * @return	string		The resulting string
	 */
	function add_fragment($path, $fragment = false)
	{
		if (!$fragment)
		{
			return $path;
		}

		return $path . '#' . $fragment;
	}


	/**
	* Makes data in an array safe to use
	*
	* @param	array	The source array containing the data to be cleaned
	* @param	array	Array of variable names and types we want to extract from the source array
	*
	* @return	array
	*/
	function &clean_array(&$source, $variables)
	{
		$return = array();

		foreach ($variables AS $varname => $vartype)
		{
			$return["$varname"] =& $this->clean($source["$varname"], $vartype, isset($source["$varname"]));
		}

		return $return;
	}

	/**
	* Makes GPC variables safe to use
	*
	* @param	string	Either, g, p, c, r or f (corresponding to get, post, cookie, request and files)
	* @param	array	Array of variable names and types we want to extract from the source array
	*
	* @return	array
	*/
	function clean_array_gpc($source, $variables)
	{
		$sg =& $GLOBALS[$this->superglobal_lookup["$source"]];

		foreach ($variables AS $varname => $vartype)
		{
			// clean a variable only once unless its a different type
			if (!isset($this->cleaned_vars["$varname"]) OR $this->cleaned_vars["$varname"] != $vartype)
			{
				$this->registry->GPC_exists["$varname"] = isset($sg["$varname"]);
				$this->registry->GPC["$varname"] =& $this->clean(
					$sg["$varname"],
					$vartype,
					isset($sg["$varname"])
				);
				if ((defined('NEED_DECODE') AND NEED_DECODE === true))
				{
					switch ($vartype) {
						case TYPE_STR:
						case TYPE_NOTRIM:
						case TYPE_NOHTML:
						case TYPE_NOHTMLCOND:
							if (!($charset = vB_Template_Runtime::fetchStyleVar('charset')))
							{
								$charset = $this->registry->userinfo['lang_charset'];
							}

							$lower_charset = strtolower($charset);
							if ($lower_charset != 'utf-8')
							{
								if ($lower_charset == 'iso-8859-1')
								{
									$this->registry->GPC["$varname"] = to_charset(ncrencode($this->registry->GPC["$varname"], true, true), 'utf-8');
								}
								else
								{
									$this->registry->GPC["$varname"] = to_charset($this->registry->GPC["$varname"], 'utf-8');
								}
							}
					}
				}
				$this->cleaned_vars["$varname"] = $vartype;
			}
		}
	}

	/**
	* Makes a single GPC variable safe to use and returns it
	*
	* @param	array	The source array containing the data to be cleaned
	* @param	string	The name of the variable in which we are interested
	* @param	integer	The type of the variable in which we are interested
	*
	* @return	mixed
	*/
	function &clean_gpc($source, $varname, $vartype = TYPE_NOCLEAN)
	{
		// clean a variable only once unless its a different type
		if (!isset($this->cleaned_vars["$varname"]) OR $this->cleaned_vars["$varname"] != $vartype)
		{
			$sg =& $GLOBALS[$this->superglobal_lookup["$source"]];

			$this->registry->GPC_exists["$varname"] = isset($sg["$varname"]);
			$this->registry->GPC["$varname"] =& $this->clean(
				$sg["$varname"],
				$vartype,
				isset($sg["$varname"])
			);
			$this->cleaned_vars["$varname"] = $vartype;
		}

		return $this->registry->GPC["$varname"];
	}

	/**
	* Makes a single variable safe to use and returns it
	*
	* @param	mixed	The variable to be cleaned
	* @param	integer	The type of the variable in which we are interested
	* @param	boolean	Whether or not the variable to be cleaned actually is set
	*
	* @return	mixed	The cleaned value
	*/
	function &clean(&$var, $vartype = TYPE_NOCLEAN, $exists = true)
	{
		if ($exists)
		{
			if ($vartype < TYPE_CONVERT_SINGLE)
			{
				$this->do_clean($var, $vartype);
			}
			else if (is_array($var))
			{
				if ($vartype >= TYPE_CONVERT_KEYS)
				{
					$var = array_keys($var);
					$vartype -=  TYPE_CONVERT_KEYS;
				}
				else
				{
					$vartype -= TYPE_CONVERT_SINGLE;
				}

				foreach (array_keys($var) AS $key)
				{
					$this->do_clean($var["$key"], $vartype);
				}
			}
			else
			{
				$var = array();
			}
			return $var;
		}
		else
		{
			// We use $newvar here to prevent overwrite superglobals. See bug #28898.
			if ($vartype < TYPE_CONVERT_SINGLE)
			{
				switch ($vartype)
				{
					case TYPE_INT:
					case TYPE_UINT:
					case TYPE_NUM:
					case TYPE_UNUM:
					case TYPE_UNIXTIME:
					{
						$newvar = 0;
						break;
					}
					case TYPE_STR:
					case TYPE_NOHTML:
					case TYPE_NOTRIM:
					case TYPE_NOHTMLCOND:
					{
						$newvar = '';
						break;
					}
					case TYPE_BOOL:
					{
						$newvar = 0;
						break;
					}
					case TYPE_ARRAY:
					case TYPE_FILE:
					{
						$newvar = array();
						break;
					}
					case TYPE_NOCLEAN:
					{
						$newvar = null;
						break;
					}
					default:
					{
						$newvar = null;
					}
				}
			}
			else
			{
				$newvar = array();
			}

			return $newvar;
		}
	}

	/**
	* Does the actual work to make a variable safe
	*
	* @param	mixed	The data we want to make safe
	* @param	integer	The type of the data
	*
	* @return	mixed
	*/
	function &do_clean(&$data, $type)
	{
		static $booltypes = array('1', 'yes', 'y', 'true', 'on');

		switch ($type)
		{
			case TYPE_INT:    $data = intval($data);                                   break;
			case TYPE_UINT:   $data = ($data = intval($data)) < 0 ? 0 : $data;         break;
			case TYPE_NUM:    $data = strval($data) + 0;                               break;
			case TYPE_UNUM:   $data = strval($data) + 0;
							  $data = ($data < 0) ? 0 : $data;                         break;
			case TYPE_BINARY: $data = strval($data);                                   break;
			case TYPE_STR:    $data = trim(strval($data));                             break;
			case TYPE_NOTRIM: $data = strval($data);                                   break;
			case TYPE_NOHTML: $data = htmlspecialchars_uni(trim(strval($data)));       break;
			case TYPE_BOOL:   $data = in_array(strtolower($data), $booltypes) ? 1 : 0; break;
			case TYPE_ARRAY:  $data = (is_array($data)) ? $data : array();             break;
			case TYPE_NOHTMLCOND:
			{
				$data = trim(strval($data));
				if (strcspn($data, '<>"') < strlen($data) OR (strpos($data, '&') !== false AND !preg_match('/&(#[0-9]+|amp|lt|gt|quot);/si', $data)))
				{
					// data is not htmlspecialchars because it still has characters or entities it shouldn't
					$data = htmlspecialchars_uni($data);
				}
				break;
			}
			case TYPE_FILE:
			{
				// perhaps redundant :p
				if (is_array($data))
				{
					if (is_array($data['name']))
					{
						$files = count($data['name']);
						for ($index = 0; $index < $files; $index++)
						{
							$data['name']["$index"] = trim(strval($data['name']["$index"]));
							$data['type']["$index"] = trim(strval($data['type']["$index"]));
							$data['tmp_name']["$index"] = trim(strval($data['tmp_name']["$index"]));
							$data['error']["$index"] = intval($data['error']["$index"]);
							$data['size']["$index"] = intval($data['size']["$index"]);
						}
					}
					else
					{
						$data['name'] = trim(strval($data['name']));
						$data['type'] = trim(strval($data['type']));
						$data['tmp_name'] = trim(strval($data['tmp_name']));
						$data['error'] = intval($data['error']);
						$data['size'] = intval($data['size']);
					}
				}
				else
				{
					$data = array(
						'name'     => '',
						'type'     => '',
						'tmp_name' => '',
						'error'    => 0,
						'size'     => 4, // UPLOAD_ERR_NO_FILE
					);
				}
				break;
			}
			case TYPE_UNIXTIME:
			{
				if (is_array($data))
				{
					$data = $this->clean($data, TYPE_ARRAY_UINT);
					if ($data['month'] AND $data['day'] AND $data['year'])
					{
						require_once(DIR . '/includes/functions_misc.php');
						$data = vbmktime($data['hour'], $data['minute'], $data['second'], $data['month'], $data['day'], $data['year']);
					}
					else
					{
						$data = 0;
					}
				}
				else
				{
					$data = ($data = intval($data)) < 0 ? 0 : $data;
				}
				break;
			}
			// null actions should be deifned here so we can still catch typos below
			case TYPE_NOCLEAN:
			{
				break;
			}

			default:
			{
				if ($this->registry->debug)
				{
					trigger_error('vB_Input_Cleaner::do_clean() Invalid data type specified', E_USER_WARNING);
				}
			}
		}

		// strip out characters that really have no business being in non-binary data
		switch ($type)
		{
			case TYPE_STR:
			case TYPE_NOTRIM:
			case TYPE_NOHTML:
			case TYPE_NOHTMLCOND:
				$data = str_replace(chr(0), '', $data);
		}

		return $data;
	}

	/**
	* Removes HTML characters and potentially unsafe scripting words from a string
	*
	* @param	string	The variable we want to make safe
	*
	* @return	string
	*/
	function xss_clean($var)
	{
		static
			$preg_find    = array('#^javascript#i', '#^vbscript#i'),
			$preg_replace = array('java script',   'vb script');

		return preg_replace($preg_find, $preg_replace, htmlspecialchars(trim($var)));
	}

	/**
	 * Removes HTML characters and potentially unsafe scripting words from a URL
	 * Note: The query string is preserved.
	 *
	 * @param	string	The url to clean
	 * @return	string
	 */
	function xss_clean_url($url)
	{
		if ($query = parse_url($url, PHP_URL_QUERY))
		{
			$url = substr($url, 0, strpos($url, '?'));
			$url = $this->xss_clean($url);
			return $url . '?' . $query;
		}

		return $this->xss_clean($url);
	}


	/**
	 * Cleans a query string.
	 * Unicode is decoded, url entities are kept encoded, and slashes are preserved.
	 *
	 * @param string $path
	 * @return string
	 */
	function utf8_clean_path($path, $reencode = true)
	{
		$path = explode('/', $path);
		$path = array_map('urldecode', $path);

		if ($reencode)
		{
			$path = array_map('urlencode_uni', $path);
		}

		$path = implode('/', $path);

		return $path;
	}


	/**
	* Reverses the effects of magic_quotes on an entire array of variables
	*
	* @param	array	The array on which we want to work
	*/
	function stripslashes_deep(&$value, $depth = 0)
	{
		if (is_array($value))
		{
		    foreach ($value AS $key => $val)
		    {
		        if (is_string($val))
		        {
		            $value["$key"] = stripslashes($val);
		        }
		        else if (is_array($val) AND $depth < 10)
		        {
		            $this->stripslashes_deep($value["$key"], $depth + 1);
		        }
		    }
		}
	}

	/**
	* Turns $_POST['t'] into $_POST['threadid'] etc.
	*
	* @param	array	The name of the array
	*/
	function convert_shortvars(&$array, $setglobals = true)
	{
		// extract long variable names from short variable names
		foreach ($this->shortvars AS $shortname => $longname)
		{
			if (isset($array["$shortname"]) AND !isset($array["$longname"]))
			{
				$array["$longname"] =& $array["$shortname"];
				if ($setglobals)
				{
					$GLOBALS['_REQUEST']["$longname"] =& $array["$shortname"];
				}
			}
		}
	}

	/**
	* Strips out the s=gobbledygook& rubbish from URLs
	*
	* @param	string	The URL string from which to remove the session stuff
	*
	* @return	string
	*/
	function strip_sessionhash($string)
	{
		$string = preg_replace('/(s|sessionhash)=[a-z0-9]{32}?&?/', '', $string);
		return $string;
	}

	/**
	 * Fetches the 'basepath' variable that can be used as <base>.
	 *
	 * @return string
	 */
	function fetch_basepath($rel_modifier = false)
	{
		if ($this->registry->basepath != '')
		{
			return $this->registry->basepath;
		}

		if ($this->registry->options['bburl_basepath'])
		{
			$basepath = trim($this->registry->options['bburl'], '/\\') . '/';
		}
		else
		{
			$basepath = VB_URL_BASE_PATH;
		}

		return $basepath = $basepath . ($rel_modifier ? $this->xss_clean($rel_modifier) : '');
	}

	/**
	 * Fetches the path for the current request relative to the basepath.
	 * This is useful for local anchors (<a href="{vb:raw relpath}#post">).
	 *
	 * Substracts any overlap between basepath and path with the following results:
	 *
	 * 		base:		http://www.example.com/forums/
	 * 		path:		/forums/content.php
	 * 		result:		content.php
	 *
	 * 		base:		http://www.example.com/forums/admincp
	 * 		path:		/forums/content/1-Article
	 * 		result:		../content/1-Article
	 *
	 * @return string
	 */
	function fetch_relpath($path = false)
	{
		if (!$path AND ($this->registry->relpath != ''))
		{
			return $this->registry->relpath;
		}

		// if no path specified, use the request path
		if (!$path)
		{
			if ($_SERVER['REQUEST_METHOD'] == 'POST' AND $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest' AND $_POST['relpath'])
			{
				$relpath = $_POST['relpath'];
				$query = '';
			}
			else
			{
			$relpath = VB_URL_PATH;
			$query = VB_URL_QUERY;
		}
		}
		else
		{
			// if the path is already absolute there's nothing to do
			if (strpos($path, '://'))
			{
				return $path;
			}

			if (!$path)
			{
				return $path;
			}

			$relpath = parse_url($path, PHP_URL_PATH);
			$query = parse_url($path, PHP_URL_QUERY);
			$fragment = parse_url($path, PHP_URL_FRAGMENT);
		}

		$relpath = ltrim($relpath, '/');
		$basepath = @parse_url($this->fetch_basepath(), PHP_URL_PATH);
		$basepath = trim($basepath, '/');

		// get path segments for comparison
		$relpath = explode('/', $relpath);
		$basepath = explode('/', $basepath);

		// remove segments that basepath and relpath share
		foreach ($basepath AS $segment)
		{
			if ($segment == current($relpath))
			{
				array_shift($basepath);
				array_shift($relpath);
			}
			else
			{
				break;
			}
		}

		// rebuild the relpath
		$relpath = implode('/', $relpath);

		/*
		// if basepath is in another dir, back out of it
		if ($diff = sizeof($basepath))
		{
			$relpath = str_repeat('../', $diff) . $relpath;
		}
		*/

		// add the query string if the current path is being used
		if ($query)
		{
			$relpath = $this->add_query($relpath, $query);
		}

		// add the fragment back
		if ($fragment)
		{
			$relpath = $this->add_fragment($relpath, $fragment);
		}

		return $relpath;
	}


	/**
	* Fetches the 'wolpath' variable - ie: the same as 'scriptpath' but with a handler for the POST request method
	*
	* @return	string
	*/
	function fetch_wolpath()
	{
		$wolpath = SCRIPTPATH;

		if ($_SERVER['REQUEST_METHOD'] == 'POST')
		{
			// Tag the variables back on to the filename if we are coming from POST so that WOL can access them.
			$tackon = '';

			if (is_array($_POST))
			{
				foreach ($_POST AS $varname => $value)
				{
					switch ($varname)
					{
						case 'forumid':
						case 'threadid':
						case 'postid':
						case 'userid':
						case 'eventid':
						case 'calendarid':
						case 'do':
						case 'method': // postings.php
						case 'dowhat': // private.php
						{
							$tackon .= ($tackon == '' ? '' : '&amp;') . $varname . '=' . $value;
							break;
						}
					}
				}
			}
			if ($tackon != '')
			{
				$wolpath .= (strpos($wolpath, '?') !== false ? '&amp;' : '?') . "$tackon";
			}
		}

		return $wolpath;
	}

	/**
	* Fetches the 'url' variable - usually the URL of the previous page in the history
	*
	* @return	string
	*/
	function fetch_url()
	{
		$scriptpath = SCRIPTPATH;

		//note regarding the default url if not set or inappropriate.
		//started out as index.php then moved to options['forumhome'] . '.php' when that option was added.
		//now we've changed to to the forumhome url since there is now quite a bit of logic around that.
		//Its not clear, however, with the expansion of vb if that's the most appropriate generic landing
		//place (perhaps it *should* be index.php).
		//In any case there are several places in the code that check for the default page url and change it
		//to something more appropriate.  If the default url changes, so do those checks.
		//The solution is, most likely, to make some note when vbulletin->url is the default so it can be overridden
		//without worrying about what the exact text is.
		if (empty($_REQUEST['url']))
		{
			$url = (!empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
		}
		else
		{
			$temp_url = $_REQUEST['url'];
			if (!empty($_SERVER['HTTP_REFERER']) AND $temp_url == $_SERVER['HTTP_REFERER'])
			{
				//$url = 'index.php';
				$url = fetch_seo_url('forumhome|nosession', array());
			}
			else
			{
				$url = $temp_url;
			}
		}

		if ($url == $scriptpath OR empty($url))
		{
			//$url = 'index.php';
			$url = fetch_seo_url('forumhome|nosession', array());
		}

		//not a lot of point in doing this as a seperate step.
		// if $url is set to forum home page, check it against options
		//if ($url == 'index.php' AND $this->registry->options['forumhome'] != 'index')
		//{
		//	$url = $this->registry->options['forumhome'] . '.php';
		//}

		$url = $this->xss_clean($url);
		return $url;
	}

	/**
	* Fetches the IP address of the current visitor
	*
	* @return	string
	*/
	function fetch_ip()
	{
		return $_SERVER['REMOTE_ADDR'];
	}

	/**
	* Fetches an alternate IP address of the current visitor, attempting to detect proxies etc.
	*
	* @return	string
	*/
	function fetch_alt_ip()
	{
		$alt_ip = $_SERVER['REMOTE_ADDR'];

		if (isset($_SERVER['HTTP_CLIENT_IP']))
		{
			$alt_ip = $_SERVER['HTTP_CLIENT_IP'];
		}
		else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) AND preg_match_all('#\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}#s', $_SERVER['HTTP_X_FORWARDED_FOR'], $matches))
		{
			// try to avoid using an internal IP address, its probably a proxy
			$ranges = array(
				'10.0.0.0/8' => array(ip2long('10.0.0.0'), ip2long('10.255.255.255')),
				'127.0.0.0/8' => array(ip2long('127.0.0.0'), ip2long('127.255.255.255')),
				'169.254.0.0/16' => array(ip2long('169.254.0.0'), ip2long('169.254.255.255')),
				'172.16.0.0/12' => array(ip2long('172.16.0.0'), ip2long('172.31.255.255')),
				'192.168.0.0/16' => array(ip2long('192.168.0.0'), ip2long('192.168.255.255')),
			);
			foreach ($matches[0] AS $ip)
			{
				$ip_long = ip2long($ip);
				if ($ip_long === false)
				{
					continue;
				}

				$private_ip = false;
				foreach ($ranges AS $range)
				{
					if ($ip_long >= $range[0] AND $ip_long <= $range[1])
					{
						$private_ip = true;
						break;
					}
				}

				if (!$private_ip)
				{
					$alt_ip = $ip;
					break;
				}
			}
		}
		else if (isset($_SERVER['HTTP_FROM']))
		{
			$alt_ip = $_SERVER['HTTP_FROM'];
		}

		return $alt_ip;
	}
}

// #############################################################################
// data registry class

/**
* Class to store commonly-used variables
*
* @package	vBulletin
* @version	$Revision: 46878 $
* @date		$Date: 2011-08-01 16:35:31 -0700 (Mon, 01 Aug 2011) $
*/
class vB_Registry
{
	// general objects
	/**
	* Datastore object.
	*
	* @var	vB_Datastore
	*/
	var $datastore;

	/**
	* Input cleaner object.
	*
	* @var	vB_Input_Cleaner
	*/
	var $input;

	/**
	* Database object.
	*
	* @var	vB_Database
	*/
	var $db;

	// user/session related
	/**
	* Array of info about the current browsing user. In the case of a registered
	* user, this will be results of fetch_userinfo(). A guest will have slightly
	* different entries.
	*
	* @var	array
	*/
	var $userinfo;

	/**
	* Session object.
	*
	* @var vB_Session
	*/
	var $session;

	/**
	* Array of do actions that are exempt from checks
	*
	* @var array
	*/
	var $csrf_skip_list = array();

	// configuration
	/**
	* Array of data from config.php.
	*
	* @var	array
	*/
	var $config;

	// GPC input
	/**
	* Array of data that has been cleaned by the input cleaner.
	*
	* @var	array
	*/
	var $GPC = array();

	/**
	* Array of booleans. When cleaning a variable, you often lose the ability
	* to determine if it was specified in the user's input. Entries in this
	* array are true if the variable existed before cleaning.
	*
	* @var	array
	*/
	var $GPC_exists = array();

	/**
	* The size of the super global arrays.
	*
	* @var	array
	*/
	var $superglobal_size = array();

	// single variables
	/**
	* IP Address of the current browsing user.
	*
	* @var	string
	*/
	var $ipaddress;

	/**
	* Alternate IP for the browsing user. This attempts to use various HTTP headers
	* to find the real IP of a user that may be behind a proxy.
	*
	* @var	string
	*/
	var $alt_ip;

	/**
	* The URL of the currently browsed page.
	*
	* @var	string
	*/
	var $scriptpath;

	/**
	 * The request basepath.
	 * Use for <base>
	 *
	 * @var string
	 */
	var $basepath;

	/**
	* Similar to the URL of the current page, but expands some items and includes
	* data submitted via POST. Used for Who's Online purposes.
	*
	* @var	string
	*/
	var $wolpath;

	/**
	* The URL of the current page, without anything after the '?'.
	*
	* @var	string
	*/
	var $script;

	/**
	* Generally the URL of the referring page if there is one, though it is often
	* set in various places of the code. Used to determine the page to redirect
	* to, if necessary.
	*
	* @var	string
	*/
	var $url;

	// usergroup permission bitfields
	/**#@+
	* Bitfield arrays for usergroup permissions.
	*
	* @var	array
	*/
	var $bf_ugp;
	// $bf_ugp_x is a reference to $bf_ugp['x']
	var $bf_ugp_adminpermissions;
	var $bf_ugp_calendarpermissions;
	var $bf_ugp_forumpermissions;
	var $bf_ugp_genericoptions;
	var $bf_ugp_genericpermissions;
	var $bf_ugp_pmpermissions;
	var $bf_ugp_wolpermissions;
	var $bf_ugp_visitormessagepermissions;
	/**#@-*/

	// misc bitfield arrays
	/**#@+
	* Bitfield arrays for miscellaneous permissions and options.
	*
	* @var	array
	*/
	var $bf_misc;
	// $bf_misc_x is a reference to $bf_misc['x']
	var $bf_misc_calmoderatorpermissions;
	var $bf_misc_forumoptions;
	var $bf_misc_intperms;
	var $bf_misc_languageoptions;
	var $bf_misc_moderatorpermissions;
	var $bf_misc_useroptions;
	var $bf_misc_hvcheck;
	/**#@-*/

	/**#@+
	* Results for specific entries in the datastore.
	*
	* @var	mixed	Mixed, though mostly arrays.
	*/
	var $options = null;
	var $attachmentcache = null;
	var $avatarcache = null;
	var $birthdaycache = null;
	var $eventcache = null;
	var $forumcache = null;
	var $iconcache = null;
	var $markupcache = null;
	var $stylecache = null;
	var $languagecache = null;
	var $smiliecache = null;
	var $usergroupcache = null;
	var $bbcodecache = null;
	var $socialsitecache = null;
	var $cron = null;
	var $mailqueue = null;
	var $banemail = null;
	var $maxloggedin = null;
	var $pluginlist = null;
	var $products = null;
	var $ranks = null;
	var $statement = null;
	var $userstats = null;
	var $wol_spiders = null;
	var $loadcache = null;
	var $noticecache = null;
	var $prefixcache = null;
	/**#@-*/

	/**#@+
	* Miscellaneous variables
	*
	* @var	mixed
	*/
	var $bbcode_style = array('code' => -1, 'html' => -1, 'php' => -1, 'quote' => -1);
	var $templatecache = array();
	var $iforumcache = array();
	var $versionnumber;
	var $nozip;
	var $debug;
	var $noheader;
	public $stylevars;

	/**
	 * Shutdown handler
	 *
	 * @var vB_Shutdown
	 */
	var $shutdown;
	/**#@-*/

	/**
	* For storing global information specific to the CMS
	*
	* @var	array
	*/
	var $vbcms = array();


	/**
	* For storing information of the API Client
	*
	* @var	array
	*/
	var $apiclient = array();

	/**
	* Constructor - initializes the nozip system,
	* and calls and instance of the vB_Input_Cleaner class
	*/
	function vB_Registry()
	{
		// variable to allow bypassing of gzip compression
		$this->nozip = defined('NOZIP') ? true : (@ini_get('zlib.output_compression') ? true : false);
		// variable that controls HTTP header output
		$this->noheader = defined('NOHEADER') ? true : false;

		@ini_set('zend.ze1_compatibility_mode', 0);

		// initialize the input handler
		$this->input = new vB_Input_Cleaner($this);

		// initialize the shutdown handler
		$this->shutdown = vB_Shutdown::instance();

		$this->csrf_skip_list = (defined('CSRF_SKIP_LIST') ? explode(',', CSRF_SKIP_LIST) : array());
	}

	/**
	* Fetches database/system configuration
	*/
	function fetch_config()
	{
		// parse the config file
		$config = array();
		include(CWD . '/includes/config.php');

		if (sizeof($config) == 0)
		{
			if (file_exists(CWD. '/includes/config.php'))
			{
				// config.php exists, but does not define $config
				die('<br /><br /><strong>Configuration</strong>: includes/config.php exists, but is not in the 3.6+ format. Please convert your config file via the new config.php.new.');
			}
			else
			{
				die('<br /><br /><strong>Configuration</strong>: includes/config.php does not exist. Please fill out the data in config.php.new and rename it to config.php');
			}
		}

		$this->config =& $config;
		// if a configuration exists for this exact HTTP host, use it
		if (isset($this->config["$_SERVER[HTTP_HOST]"]))
		{
			$this->config['MasterServer'] = $this->config["$_SERVER[HTTP_HOST]"];
		}

		// define table and cookie prefix constants
		define('TABLE_PREFIX', trim($this->config['Database']['tableprefix']));
		define('COOKIE_PREFIX', (empty($this->config['Misc']['cookieprefix']) ? 'bb' : $this->config['Misc']['cookieprefix']) . '_');

		// set debug mode
		$this->debug = !empty($this->config['Misc']['debug']);
		define('DEBUG', $this->debug);
	}

	/**
	* Takes the contents of an array and recursively uses each title/data
	* pair to create a new defined constant.
	*/
	function array_define($array)
	{
		foreach ($array AS $title => $data)
		{
			if (is_array($data))
			{
				vB_Registry::array_define($data);
			}
			else
			{
				define(strtoupper($title), $data);
			}
		}
	}

	/**
	*	Check if a user has a specific permission
	*
	*	This is intended to replace direct acces to the userinfo['permissions'] array.
	*
	* For example:
	* $vbulletin->check_user_permission('genericpermissions', 'cancreatetag')
	*
	* which replaces
  * ($vbulletin->userinfo['permissions']['genericpermissions'] &
	*  $vbulletin->bf_ugp_genericpermissions['cancreatetag'])
	*
	*	@param string $group the permission group to check
	* @param string $permission the permission to check within the group
	* @return bool If the user has the requested permission
	*/
	public function check_user_permission($group, $permission)
	{
		return (bool) ($this->userinfo['permissions'][$group] &
			$this->{'bf_ugp_' . $group}[$permission]);
	}
}

// #############################################################################
// session management class

/**
* Class to handle sessions
*
* Creates, updates, and validates sessions; retrieves user info of browsing user
*
* @package	vBulletin
* @version	$Revision: 46878 $
* @date		$Date: 2011-08-01 16:35:31 -0700 (Mon, 01 Aug 2011) $
*/
class vB_Session
{
	/**
	* The individual session variables. Equivalent to $session from the past.
	*
	* @var	array
	*/
	var $vars = array();

	/**
	* A list of variables in the $vars member that are in the database. Includes their types.
	*
	* @var	array
	*/
	var $db_fields = array(
		'sessionhash'   => TYPE_STR,
		'userid'        => TYPE_INT,
		'host'          => TYPE_STR,
		'idhash'        => TYPE_STR,
		'lastactivity'  => TYPE_INT,
		'location'      => TYPE_STR,
		'styleid'       => TYPE_INT,
		'languageid'    => TYPE_INT,
		'loggedin'      => TYPE_INT,
		'inforum'       => TYPE_INT,
		'inthread'      => TYPE_INT,
		'incalendar'    => TYPE_INT,
		'badlocation'   => TYPE_INT,
		'useragent'     => TYPE_STR,
		'bypass'        => TYPE_INT,
		'profileupdate' => TYPE_INT,
		'apiclientid'   => TYPE_INT,
		'apiaccesstoken'=> TYPE_STR,
	);

	/**
	* An array of changes. Used to prevent superfluous updates from being made.
	*
	* @var	array
	*/
	var $changes = array();

	/**
	* Whether the session was created or existed previously
	*
	* @var	bool
	*/
	var $created = false;

	/**
	* Reference to a vB_Registry object that keeps various data we need.
	*
	* @var	vB_Registry
	*/
	var $registry = null;

	/**
	* Information about the user that this session belongs to.
	*
	* @var	array
	*/
	var $userinfo = null;

	/**
	* Is the sessionhash to be passed through URLs?
	*
	* @var	boolean
	*/
	var $visible = true;

	/**
	* Constructor. Attempts to grab a session that matches parameters, but will create one if it can't.
	*
	* @param	vB_Registry	Reference to a registry object
	* @param	string		Previously specified sessionhash
	* @param	integer		User ID (passed in through a cookie)
	* @param	string		Password, must arrive in cookie format: md5(md5(md5(password) . salt) . 'abcd1234')
	* @param	integer		Style ID for this session
	* @param	integer		Language ID for this session
	*/
	function vB_Session(&$registry, $sessionhash = '', $userid = 0, $password = '', $styleid = 0, $languageid = 0)
	{
		$userid = intval($userid);
		$styleid = intval($styleid);
		$languageid = intval($languageid);

		$this->registry =& $registry;
		$db =& $this->registry->db;
		$gotsession = false;

		$this->registry->input->clean_gpc('r', 'api');
		if (!defined('SESSION_IDHASH'))
		{
			if (!VB_API AND !$this->registry->GPC['api'])
			{
				define('SESSION_IDHASH', md5($_SERVER['HTTP_USER_AGENT'] . $this->fetch_substr_ip($registry->alt_ip))); // this should *never* change during a session
			}
			else
			{
				define('SESSION_IDHASH', md5($this->fetch_substr_ip($registry->alt_ip))); // API session idhash won't have User Agent compiled.
			}
		}

		if (!defined('SKIP_SESSIONCREATE'))
		{
			$test = false;

			if (defined('UNIT_TESTING') AND UNIT_TESTING === true)
			{
				$test = ($session = $db->query_first_slave("
					SELECT *
					FROM " . TABLE_PREFIX . "session
					WHERE sessionhash = '" . $db->escape_string($sessionhash) . "'
						AND lastactivity > " . (TIMENOW - $registry->options['cookietimeout']) . "
				"));
			}
			// apiaccesstoken specified, so see if it already exists
			elseif (defined('VB_API') AND VB_API === true AND $this->registry->apiclient['apiaccesstoken'])
			{
				$test = ($session = $db->query_first_slave("
					SELECT *
					FROM " . TABLE_PREFIX . "session
					WHERE apiaccesstoken = '" . $db->escape_string($this->registry->apiclient['apiaccesstoken']) . "'
						AND lastactivity > " . (TIMENOW - $registry->options['cookietimeout']) . "
						AND idhash = '" . $this->registry->db->escape_string(SESSION_IDHASH) . "'
				") AND $this->fetch_substr_ip($session['host']) == $this->fetch_substr_ip(SESSION_HOST));
			}
			// sessionhash specified, so see if it already exists
			elseif ($sessionhash)
			{
				$test = ($session = $db->query_first_slave("
					SELECT *
					FROM " . TABLE_PREFIX . "session
					WHERE sessionhash = '" . $db->escape_string($sessionhash) . "'
						AND lastactivity > " . (TIMENOW - $registry->options['cookietimeout']) . "
						AND idhash = '" . $this->registry->db->escape_string(SESSION_IDHASH) . "'
				") AND $this->fetch_substr_ip($session['host']) == $this->fetch_substr_ip(SESSION_HOST));

			}

			if ($test)
			{
				$gotsession = true;
				$this->vars =& $session;
				$this->created = false;

				// found a session - get the userinfo
				if ($session['userid'] != 0)
				{
					$useroptions = (defined('IN_CONTROL_PANEL') ? 16 : 0) + (defined('AVATAR_ON_NAVBAR') ? 2 : 0);
					$userinfo = fetch_userinfo($session['userid'], $useroptions, (!empty($languageid) ? $languageid : $session['languageid']));
					$this->userinfo =& $userinfo;
				}
			}
		}

		// API 'Remember Me'. UserID is stored in apiclient table.
		if (($gotsession == false OR empty($session['userid'])) AND defined('VB_API') AND VB_API === true AND $this->registry->apiclient['userid'] AND !defined('SKIP_SESSIONCREATE'))
		{
			$useroptions = (defined('IN_CONTROL_PANEL') ? FETCH_USERINFO_ADMIN : 0) + (defined('AVATAR_ON_NAVBAR') ? FETCH_USERINFO_AVATAR : 0);
			$userinfo = fetch_userinfo($this->registry->apiclient['userid'], $useroptions, $languageid);

			$gotsession = true;

			// combination is valid
			if (!empty($session['sessionhash']))
			{
				// old session still exists; kill it
				$db->shutdown_query("
					DELETE FROM " . TABLE_PREFIX . "session
					WHERE sessionhash = '" . $this->registry->db->escape_string($session['sessionhash']). "'
				");
			}

			$this->vars = $this->fetch_session($userinfo['userid']);
			$this->created = true;

			$this->userinfo =& $userinfo;
		}

		// or maybe we can use a cookie..
		if (($gotsession == false OR empty($session['userid'])) AND $userid AND $password AND !defined('SKIP_SESSIONCREATE') AND !VB_API)
		{
			$useroptions = (defined('IN_CONTROL_PANEL') ? FETCH_USERINFO_ADMIN : 0) + (defined('AVATAR_ON_NAVBAR') ? FETCH_USERINFO_AVATAR : 0);
			$userinfo = fetch_userinfo($userid, $useroptions, $languageid);

			if (md5($userinfo['password'] . COOKIE_SALT) == $password)
			{
				$gotsession = true;

				// combination is valid
				if (!empty($session['sessionhash']))
				{
					// old session still exists; kill it
					$db->shutdown_query("
						DELETE FROM " . TABLE_PREFIX . "session
						WHERE sessionhash = '" . $this->registry->db->escape_string($session['sessionhash']). "'
					");
				}

				$this->vars = $this->fetch_session($userinfo['userid']);
				$this->created = true;

				$this->userinfo =& $userinfo;
			}
		}

		// at this point, we're a guest, so lets try to *find* a session
		// you can prevent this check from being run by passing in a userid with no password
		if ($gotsession == false AND $userid == 0 AND !defined('SKIP_SESSIONCREATE'))
		{
			if ($session = $db->query_first_slave("
				SELECT *
				FROM " . TABLE_PREFIX . "session
				WHERE userid = 0
					AND host = '" . $this->registry->db->escape_string(SESSION_HOST) . "'
					AND idhash = '" . $this->registry->db->escape_string(SESSION_IDHASH) . "'
				LIMIT 1
			"))
			{
				$gotsession = true;

				$this->vars =& $session;
				$this->created = false;
			}
		}

		// well, nothing worked, time to create a new session
		if ($gotsession == false)
		{
			$gotsession = true;

			$this->vars = $this->fetch_session(0);
			$this->created = true;
		}

		$this->vars['dbsessionhash'] = $this->vars['sessionhash'];

		$this->set('styleid', $styleid);
		$this->set('languageid', $languageid);
		if ($this->created == false)
		{
			$this->set('useragent', USER_AGENT);
			$this->set('lastactivity', TIMENOW);
			if (!defined('LOCATION_BYPASS'))
			{
				$this->set('location', WOLPATH);
			}
			$this->set('bypass', SESSION_BYPASS);
		}
	}

	/**
	* Saves the session into the database by inserting it or updating an existing one.
	*/
	function save()
	{
		if (defined('SKIP_SESSIONCREATE'))
		{
			return;
		}

		$cleaned = $this->build_query_array();

		// since the sessionhash can be blanked out, lets make sure we pull from "dbsessionhash"
		$cleaned['sessionhash'] = "'" . $this->registry->db->escape_string($this->vars['dbsessionhash']) . "'";

		if ($this->created == true)
		{
			if($this->registry->options['enablespiders'])//VBIV-5766
			{
				require_once(DIR . '/includes/class_xml.php');
				$xmlobj = new vB_XML_Parser(false, DIR . '/includes/xml/spiders_vbulletin.xml');
				$spiderdata = $xmlobj->parse();
				$spiders = "";
				
				if (is_array($spiderdata['spider']))
				{
					foreach ($spiderdata['spider'] AS $spiderling)
					{
						$spiders .= ($spiders ? '|' : '') . preg_quote($spiderling['ident'], '#');
					}
				}
				
				unset($spiderdata, $xmlobj);
				
				//isbot to distinguish between bots and guests in session table VBIV-5766
				if (preg_match('#(' . $spiders . ')#si', $cleaned['useragent']))
				{
					$cleaned['isbot'] = true;
				}
			}// end VBIV-5766
			
			/*insert query*/
			$this->registry->db->query_write("
				INSERT IGNORE INTO " . TABLE_PREFIX . "session
					(" . implode(', ', array_keys($cleaned)) . ")
				VALUES
					(" . implode(', ', $cleaned) . ")
			");
		}
		else
		{
			// update query

			unset($this->changes['sessionhash']); // the sessionhash is not updateable
			$update = array();
			foreach ($cleaned AS $key => $value)
			{
				if (!empty($this->changes["$key"]))
				{
					$update[] = "$key = $value";
				}
			}

			if (sizeof($update) > 0)
			{
				// note that $cleaned['sessionhash'] has been escaped as necessary above!
				$this->registry->db->query_write("
					UPDATE " . TABLE_PREFIX . "session
					SET " . implode(', ', $update) . "
					WHERE sessionhash = $cleaned[sessionhash]
				");
			}
		}

		$this->changes = array();
	}

	/**
	* Builds an array that can be used to build a query to insert/update the session
	*
	* @return	array	Array of column name => prepared value
	*/
	function build_query_array()
	{
		$return = array();
		foreach ($this->db_fields AS $fieldname => $cleantype)
		{
			switch ($cleantype)
			{
				case TYPE_INT:
					$cleaned = intval($this->vars["$fieldname"]);
					break;
				case TYPE_STR:
				default:
					$cleaned = "'" . $this->registry->db->escape_string($this->vars["$fieldname"]) . "'";
			}
			$return["$fieldname"] = $cleaned;
		}

		return $return;
	}

	/**
	* Sets a session variable and updates the change list.
	*
	* @param	string	Name of session variable to update
	* @param	string	Value to update it with
	*/
	function set($key, $value)
	{
		if (!isset($this->vars["$key"]) OR $this->vars["$key"] != $value)
		{
			$this->vars["$key"] = $value;
			$this->changes["$key"] = true;
		}
	}

	/**
	* Sets the session visibility (whether session info shows up in a URL). Updates are put in the $vars member.
	*
	* @param	bool	Whether the session elements should be visible.
	*/
	function set_session_visibility($invisible)
	{
		$this->visible = !$invisible;

		if ($invisible)
		{
			$this->vars['sessionhash'] = '';
			$this->vars['sessionurl'] = '';
			$this->vars['sessionurl_q'] = '';
			$this->vars['sessionurl_js'] = '';
		}
		else
		{
			if (!VB_API)
			{
				$this->vars['sessionurl'] = 's=' . $this->vars['dbsessionhash'] . '&amp;';
				$this->vars['sessionurl_q'] = '?s=' . $this->vars['dbsessionhash'];
				$this->vars['sessionurl_js'] = 's=' . $this->vars['dbsessionhash'] . '&';
			}
			else
			{
				$this->vars['sessionurl'] = 's=' . $this->vars['dbsessionhash'] . '&amp;api=1&amp;';
				$this->vars['sessionurl_q'] = '?s=' . $this->vars['dbsessionhash'] . '&amp;api=1';
				$this->vars['sessionurl_js'] = 's=' . $this->vars['dbsessionhash'] . '&api=1&';
			}
		}
	}

	/**
	* Fetches a valid sessionhash value, not necessarily the one tied to this session.
	*
	* @return	string	32-character sessionhash
	*/
	function fetch_sessionhash()
	{
		return md5(uniqid(microtime(), true));
	}

	/**
	* Returns the IP address with the specified number of octets removed
	*
	* @param	string	IP address
	*
	* @return	string	truncated IP address
	*/
	function fetch_substr_ip($ip, $length = null)
	{
		if ($length === null OR $length > 3)
		{
			$length = $this->registry->options['ipcheck'];
		}
		return implode('.', array_slice(explode('.', $ip), 0, 4 - $length));
	}

	/**
	* Fetches a default session. Used when creating a new session.
	*
	* @param	integer	User ID the session should be for
	*
	* @return	array	Array of session variables
	*/
	function fetch_session($userid = 0)
	{
		$sessionhash = $this->fetch_sessionhash();
		if (!defined('SKIP_SESSIONCREATE'))
		{
			vbsetcookie('sessionhash', $sessionhash, false, false, true);
		}

		$session = array(
			'sessionhash'   => $sessionhash,
			'dbsessionhash' => $sessionhash,
			'userid'        => intval($userid),
			'host'          => SESSION_HOST,
			'idhash'        => SESSION_IDHASH,
			'lastactivity'  => TIMENOW,
			'location'      => defined('LOCATION_BYPASS') ? '' : WOLPATH,
			'styleid'       => 0,
			'languageid'    => 0,
			'loggedin'      => intval($userid) ? 1 : 0,
			'inforum'       => 0,
			'inthread'      => 0,
			'incalendar'    => 0,
			'badlocation'   => 0,
			'profileupdate' => 0,
			'useragent'     => USER_AGENT,
			'bypass'        => SESSION_BYPASS
		);

		if (defined('VB_API') AND VB_API === true)
		{
			if ($this->registry->apiclient['apiaccesstoken'])
			{
				// Access Token is valid here because it's validated in init.php
				$accesstoken = $this->registry->apiclient['apiaccesstoken'];
			}
			else
			{
				// Generate an accesstoken
				$accesstoken = fetch_random_string();

				$this->registry->apiclient['apiaccesstoken'] = $accesstoken;
			}

			$session['apiaccesstoken'] = $accesstoken;

			if ($this->registry->apiclient['apiclientid'])
			{
				$session['apiclientid'] = intval($this->registry->apiclient['apiclientid']);
				// Save accesstoken to apiclient table
				$this->registry->db->query_write("UPDATE " . TABLE_PREFIX . "apiclient SET
					apiaccesstoken = '" . $this->registry->db->escape_string($accesstoken) . "',
					lastactivity = " . TIMENOW . "
					WHERE apiclientid = $session[apiclientid]");
			}
		}

		($hook = vBulletinHook::fetch_hook('fetch_session_complete')) ? eval($hook) : false;

		return $session;

	}

	/**
	* Returns appropriate user info for the owner of this session.
	*
	* @return	array	Array of user information.
	*/
	function &fetch_userinfo()
	{
		if ($this->userinfo)
		{
			// we already calculated this
			return $this->userinfo;
		}
		else if ($this->vars['userid'] AND !defined('SKIP_USERINFO'))
		{
			// user is logged in
			$useroptions = (defined('IN_CONTROL_PANEL') ? FETCH_USERINFO_ADMIN : 0) + (defined('AVATAR_ON_NAVBAR') ? FETCH_USERINFO_AVATAR : 0);
			$this->userinfo = fetch_userinfo($this->vars['userid'], $useroptions, $this->vars['languageid']);
			return $this->userinfo;
		}
		else
		{
			// guest setup
			$this->userinfo = array(
				'userid'         => 0,
				'usergroupid'    => 1,
				'username'       => (!empty($_REQUEST['username']) ? htmlspecialchars_uni($_REQUEST['username']) : ''),
				'password'       => '',
				'email'          => '',
				'styleid'        => $this->vars['styleid'],
				'languageid'     => $this->vars['languageid'],
				'lastactivity'   => $this->vars['lastactivity'],
				'daysprune'      => 0,
				'timezoneoffset' => $this->registry->options['timeoffset'],
				'dstonoff'       => $this->registry->options['dstonoff'],
				'showsignatures' => 1,
				'showavatars'    => 1,
				'showimages'     => 1,
				'showusercss'    => 1,
				'dstauto'        => 0,
				'maxposts'       => -1,
				'startofweek'    => 1,
				'threadedmode'   => $this->registry->options['threadedmode'],
				'securitytoken'  => 'guest',
				'securitytoken_raw'  => 'guest'
			);

			$this->userinfo['options'] =
										$this->registry->bf_misc_useroptions['showsignatures'] | $this->registry->bf_misc_useroptions['showavatars'] |
										$this->registry->bf_misc_useroptions['showimages'] | $this->registry->bf_misc_useroptions['dstauto'] |
										$this->registry->bf_misc_useroptions['showusercss'];

			if (!defined('SKIP_USERINFO'))
			{
				// get default language
				$phraseinfo = $this->registry->db->query_first_slave("
					SELECT languageid" . fetch_language_fields_sql(0) . "
					FROM " . TABLE_PREFIX . "language
					WHERE languageid = " . (!empty($this->vars['languageid']) ? $this->vars['languageid'] : intval($this->registry->options['languageid'])) . "
				");
				if (empty($phraseinfo))
				{ // can't phrase this since we can't find the language
					trigger_error('The requested language does not exist, reset via tools.php.', E_USER_ERROR);
				}
				foreach($phraseinfo AS $_arrykey => $_arryval)
				{
					$this->userinfo["$_arrykey"] = $_arryval;
				}
				unset($phraseinfo);
			}

			return $this->userinfo;
		}
	}

	/**
	* Updates the last visit and last activity times for guests and registered users (differently).
	* Last visit is set to the last activity time (before it's updated) only when a certain
	* time has lapsed. Last activity is always set to the specified time.
	*
	* @param	integer	Time stamp for last visit time (guest only)
	* @param	integer	Time stamp for last activity time (guest only)
	*/
	function do_lastvisit_update($lastvisit = 0, $lastactivity = 0)
	{
		// update last visit/activity stuff
		if ($this->vars['userid'] == 0)
		{
			// guest -- emulate last visit/activity for registered users by cookies
			if ($lastvisit)
			{
				// we've been here before
				$this->userinfo['lastvisit'] = intval($lastvisit);
				$this->userinfo['lastactivity'] = ($lastvisit ? intval($lastvisit) : TIMENOW);

				// here's the emulation
				if (TIMENOW - $this->userinfo['lastactivity'] > $this->registry->options['cookietimeout'])
				{
					$this->userinfo['lastvisit'] = $this->userinfo['lastactivity'];

					vbsetcookie('lastvisit', $this->userinfo['lastactivity']);
				}
			}
			else
			{
				// first visit!
				$this->userinfo['lastactivity'] = TIMENOW;
				$this->userinfo['lastvisit'] = TIMENOW;

				vbsetcookie('lastvisit', TIMENOW);
			}
			vbsetcookie('lastactivity', $lastactivity);
		}
		else
		{
			// registered user
			if (!SESSION_BYPASS)
			{
				if (TIMENOW - $this->userinfo['lastactivity'] > $this->registry->options['cookietimeout'])
				{
					// see if session has 'expired' and if new post indicators need resetting
					$this->registry->db->shutdown_query("
						UPDATE " . TABLE_PREFIX . "user
						SET
							lastvisit = lastactivity,
							lastactivity = " . TIMENOW . "
						WHERE userid = " . $this->userinfo['userid'] . "
					", 'lastvisit');

					$this->userinfo['lastvisit'] = $this->userinfo['lastactivity'];
				}
				else
				{
					// if this line is removed (say to be replaced by a cron job, you will need to change all of the 'online'
					// status indicators as they use $userinfo['lastactivity'] to determine if a user is online which relies
					// on this to be updated in real time.
					$this->registry->db->shutdown_query("
						UPDATE " . TABLE_PREFIX . "user
						SET lastactivity = " . TIMENOW . "
						WHERE userid = " . $this->userinfo['userid'] . "
					", 'lastvisit');
				}
			}
		}
	}
}

/**
* Class to handle shutdown
*
* @package	vBulletin
* @version	$Revision: 46878 $
* @author	vBulletin Development Team
* @date		$Date: 2011-08-01 16:35:31 -0700 (Mon, 01 Aug 2011) $
*/
class vB_Shutdown
{
	/**
	 * A reference to the singleton instance
	 *
	 * @var vB_Cache_Observer
	 */
	protected static $instance;

	/**
	 * An array of shutdown callbacks to call on shutdown
	 */
	protected $callbacks;

	/**
	 * Constructor protected to enforce singleton use.
	 * @see instance()
	 */
	protected function __construct(){}

	/**
	 * Returns singleton instance of self.
	 *
	 * @return vB_Shutdown
	 */
	public function instance()
	{
		if (!isset(self::$instance))
		{
			$class = __CLASS__;
			self::$instance = new $class();
		}

		return self::$instance;
	}

	/**
	* Add callback to be executed at shutdown
	*
	* @param array $callback					- Call back to call on shutdown
	*/
	public function add($callback)
	{
		if (!is_array($this->callbacks))
		{
			$this->callbacks = array();
		}

		$this->callbacks[] = $callback;
	}

	// only called when an object is destroyed, so $this is appropriate
	public function shutdown()
	{
		if (sizeof($this->callbacks))
		{
			foreach ($this->callbacks AS $callback)
			{
				call_user_func($callback);
			}

			unset($this->callbacks);
		}
	}

	public function __wakeup()
	{
		unset($this->callbacks);
	}
}

/**
* This class implements variable-registration-based template evaluation,
* wrapped around the legacy template format. It will be extended in the
* future to support the new format/syntax without requiring changes to
* code written with it.
*
* Currently these vars are automatically registered: $vbphrase
*    $show, $bbuserinfo, $session, $vboptions
*
* @package	vBulletin
*/
class vB_Template
{
	/**
	 * Preregistered variables.
	 * Variables can be preregistered before a template is created and will be
	 * imported and reset when the template is created.
	 * The array should be in the form array(template_name => array(key => variable))
	 *
	 * @var array mixed
	 */
	protected static $pre_registered = array();

	/**
	* Name of the template to render
	*
	* @var	string
	*/
	protected $template = '';

	/**
	 * Array of registered variables.
	 * @see vB_Template::preRegister()
	*
	* @var	array
	*/
	protected $registered = array();

	/**
	 * Whether the globally accessible vars have been registered.
	 *
	 * @var bool
	 */
	protected $registered_globals;

	/**
	* Debug helper to count how many times a template was used on a page.
	*
	* @var	array
	*/
	public static $template_usage = array();

	/**
	* Debug helper to list the templates that were fetched out of the database (not cached properly).
	*
	* @var	array
	*/
	public static $template_queries = array();

	/**
	 * Hook code for register.
	 * @see vB_Template::register()
	 *
	 * @var string
	 */
	protected static $hook_code = false;



	/**
	 * Factory method to create the template object.
	 * Will choose the correct template type based on the request. Any preregistered
	 * variables are also registered and cleared from the preregister cache.
	*
	* @param	string	Name of the template to be evaluated
	* @return	vB_Template	Template object
	*/
	public static function create($template_name, $forcenoapi = false)
	{
		static $output_type;

		if (defined('VB_API') AND VB_API AND !$forcenoapi)
		{
			// TODO: Use an option to enable/disable the api output

			if (!isset($output_type))
			{
//				global $vbulletin;
//
//				$vbulletin->input->clean_gpc('r', 'api');
//				$output_type = in_array($vbulletin->GPC['api'], array('xml', 'json')) ? $vbulletin->GPC['api'] : 'json';
				// Currently we support json only
				$output_type = 'json';
			}

			if ($output_type == 'xml')
			{
				$template = new vB_Template_XML($template_name);
			}
			else
			{
				$template = new vB_Template_JSON($template_name);
			}

			if (!VB_API_CMS)
			{
				global $show;
				$copyofshow = $show;
				self::remove_common_show($copyofshow);
				$template->register('show', $copyofshow);
			}
		}
		else
		{
			$template = new vB_Template($template_name);
		}

		if (isset(self::$pre_registered[$template_name]))
		{
			$template->quickRegister(self::$pre_registered[$template_name]);
			// TODO: Reinstate once search uses a single template object
			// unset(self::$pre_registered[$template_name]);
		}

		return $template;
	}

	/**
	 * Unset common items in $show array for API
	 */
	protected static function remove_common_show(&$show)
	{
		// Unset common show variables
		unset(
			$show['old_explorer'], $show['rtl'], $show['admincplink'], $show['modcplink'],
			$show['registerbutton'], $show['searchbuttons'], $show['quicksearch'],
			$show['memberslist'], $show['guest'], $show['member'], $show['popups'],
			$show['nojs_link'], $show['pmwarning'], $show['pmstats'], $show['pmmainlink'],
			$show['pmtracklink'], $show['pmsendlink'], $show['siglink'], $show['avatarlink'],
			$show['detailedtime'], $show['profilepiclink'], $show['wollink'], $show['spacer'],
			$show['dst_correction'], $show['contactus'], $show['nopasswordempty'],
			$show['quick_links_groups'], $show['quick_links_albums'], $show['friends_and_contacts'],
			$show['communitylink'], $show['search_engine'], $show['editor_css']
		);
	}

	/**
	 * Protected constructor to enforce the factory pattern.
	 * Ensures the chrome templates have been processed.
	*/
	protected function __construct($template_name)
	{
		global $bootstrap;

		if (!empty($bootstrap) AND !$bootstrap->called('template'))
		{
			$bootstrap->process_templates();
		}

		$this->template = $template_name;
	}

	/**
	* Returns the name of the template that will be rendered.
	*
	* @return	string
	*/
	public function get_template_name()
	{
		return $this->template;
	}

	/**
	 * Preregisters variables before template instantiation.
	 *
	 * @param	string	The name of the template to register for
	 * @param	array	The variables to register
	 */
	public static function preRegister($template_name, array $variables = NULL)
	{
		if ($variables)
		{
			if (!isset(self::$pre_registered[$template_name]))
			{
				self::$pre_registered[$template_name] = array();
			}

			self::$pre_registered[$template_name] = array_merge(self::$pre_registered[$template_name], $variables);
		}
	}

	/**
	* Register a variable with the template.
	*
	* @param	string	Name of the variable to be registered
	* @param	mixed	Value to be registered. This may be a scalar or an array.
	 * @param	bool	Whether to overwrite existing vars
	 * @return	bool	Whether the var was registered
	*/
	public function register($name, $value, $overwrite = true)
	{
		if (!$overwrite AND $this->is_registered($name))
		{
			return false;
		}

		// Run register hook
		self::assert_register_hook();

		if (self::$hook_code)
		{
			eval(self::$hook_code);
		}

		$this->registered[$name] = $value;

		return true;
	}

	/**
	 * Registers an array of variables with the template.
	 *
	 * @param	mixed	Assoc array of name => value to be registered
	 */
	public function quickRegister($values, $overwrite = true)
	{
		if (!is_array($values))
		{
			return;
		}

		foreach ($values AS $name => $value)
		{
			$this->register($name, $value, $overwrite);
		}
	}

	/**
	 * Registers a named global variable with the template.
	 *
	 * @param	string	The global to register
	 * @param	bool	Whether to overwrite on a name collision
	 */
	public function register_global($name, $overwrite = true)
	{
		if (!$overwrite AND $this->is_registered($name))
		{
			return false;
		}

		return isset($GLOBALS[$name]) ? $this->register_ref($name, $GLOBALS[$name]) : false;
	}

	/**
	 * Registers a reference to a variable.
	 *
	 * @param	string	Name of the variable to be registered
	 * @param	mixed	Value to be registered. This may be a scalar or an array
	 * @param	bool	Whether to overwrite existing vars
	 * @return	bool	Whether the var was registered
	 */
	public function register_ref($name, &$value, $overwrite = true)
	{
		if (!$overwrite AND $this->is_registered($name))
		{
			return false;
		}

		// Run register hook
		self::assert_register_hook();

		if (self::$hook_code)
		{
			eval(self::$hook_code);
		}

		$this->registered[$name] =& $value;

		return true;
	}

	/**
	* Unregisters a previously registered variable.
	*
	* @param	string	Name of variable to be unregistered
	* @return	mixed	Null if the variable wasn't registered, otherwise the value of the variable
	*/
	public function unregister($name)
	{
		if (isset($this->registered[$name]))
		{
			$value = $this->registered[$name];
			unset($this->registered[$name]);
			return $value;
		}
		else
		{
			return null;
		}
	}

	/**
	 * Determines if a named variable is registered.
	*
	* @param	string	Name of variable to check
	* @return	bool
	*/
	public function is_registered($name)
	{
		return isset($this->registered[$name]);
	}

	/**
	* Return the value of a registered variable or all registered values
	 * If no variable name is specified then all variables are returned.
	*
	* @param	string	The name of the variable to get the value for.
	* @return	mixed	If a name is specified, the value of the variable or null if it doesn't exist.
	*/
	public function registered($name = '')
	{
		if ($name !== '')
		{
			return (isset($this->registered[$name]) ? $this->registered[$name] : null);
		}
		else
		{
			return $this->registered;
		}
	}

	/**
	 * Caches the register var hook code locally.
	 */
	protected static function assert_register_hook()
	{
		if (self::$hook_code === false AND class_exists('vBulletinHook', false))
		{
			self::$hook_code = vBulletinHook::fetch_hook('template_register_var');
		}
	}

	/**
	* Automatically register the page-level templates footer, header,
	* and headinclude based on their global values.
	*/
	public function register_page_templates()
	{
		// Only method forum requires these templates
		if (defined('VB_API') AND VB_API === true AND VB_ENTRY !== 'forum.php')
		{
			return true;
		}

		$this->register_global('footer');
		$this->register_global('header');
		$this->register_global('headinclude');
		$this->register_global('headinclude_bottom');
	}

	/**
	 * Register globally accessible vars.
	 *
	 * @param bool $final_render				- Whether we are rendering the final response
	*/
	protected function register_globals($final_render = false)
	{
		if ($this->registered_globals)
		{
			return;
		}
		$this->registered_globals = true;

		global $vbulletin, $style;

		$this->register_ref('bbuserinfo', $vbulletin->userinfo);
		$this->register_ref('vboptions', $vbulletin->options);
		$this->register_ref('session', $vbulletin->session->vars);
		$this->register('relpath', htmlspecialchars($vbulletin->input->fetch_relpath()));

		$this->register_global('vbphrase');
		$this->register_global('vbcollapse');
		$this->register_global('ad_location');
		$this->register_global('style');

		$this->register_global('show', false);
		$this->register_global('template_hook', false);

		$vbcsspath = vB_Template::fetch_css_path();
		$this->register('vbcsspath', $vbcsspath);

		if ($vbulletin->products['vbcms'])
		{
			$this->register('vb_suite_installed', true);
		}

		// If we're using bgclass, we might be using exec_switch_bg()
		// but we can only be sure if we match the global value.
		// A hack that will hopefully go away.
		if (isset($bgclass) AND $bgclass == $GLOBALS['bgclass'])
		{
			$this->register_ref('bgclass', $GLOBALS['bgclass']);
		}
	}


	/**
	 * Renders the template.
	 *
	 * @param	boolean	Whether to suppress the HTML comment surrounding option (for JS, etc)
	 * @return	string	Rendered version of the template
	 */
	public function render($suppress_html_comments = false, $final_render = false)
	{
		// Register globally accessible data
		$this->register_globals($final_render);

		// Render the output in the appropriate format
		return $this->render_output($suppress_html_comments);
	}


	/**
	 * Renders the output after preperation.
	 * @see vB_Template::render()
	 *
	 * @param boolean	Whether to suppress the HTML comment surrounding option (for JS, etc)
	 * @return string
	 */
	protected function render_output($suppress_html_comments = false)
	{
		//This global statement is here to expose $vbulletin to the templates.
		//It must remain in the same function as the template eval
		global $vbulletin;
		extract($this->registered, EXTR_SKIP | EXTR_REFS);

		$template_code = self::fetch_template($this->template);
		if (strpos($template_code, '$final_rendered') !== false)
		{
			eval($template_code);
		}
		else
		{
			eval('$final_rendered = "' . $template_code . '";');
		}

		if ($vbulletin->options['addtemplatename'] AND !$suppress_html_comments)
		{
			$template_name = preg_replace('#[^a-z0-9_]#i', '', $this->template);
			$final_rendered = "<!-- BEGIN TEMPLATE: $template_name -->\n$final_rendered\n<!-- END TEMPLATE: $template_name -->";
		}

		return $final_rendered;
	}


	/**
	* Returns the CSS path needed for the {vb:cssfile} template tag
	*
	* @return	string	CSS path
	*/
	public static function fetch_css_path()
	{
		global $vbulletin, $style, $foruminfo;

		if ($vbulletin->options['storecssasfile'])
		{
			$vbcsspath = 'clientscript/vbulletin_css/style' . str_pad($style['styleid'], 5, '0', STR_PAD_LEFT) . $vbulletin->stylevars['textdirection']['string'][0] . '/';
		}
		else
		{
			// Forum ID added when in forums with style overrides and the "Allow Users To Change Styles"
			// option is off, otherwise the requested styleid will be denied. Not added across the board
			// to ensure the highest cache hit rate possible. Not needed when CSS is stored as files.
			// See bug: VBIV-5647
			$forumid = intval($foruminfo['forumid']);
			$forum_styleid = intval($foruminfo['styleid']);
			if (!$vbulletin->options['allowchangestyles'] AND $forumid > 0 AND $forum_styleid > 0)
			{
				$add_forumid = '&amp;forumid=' . $forumid;
			}
			else
			{
				$add_forumid = '';
			}

			// textdirection var added to prevent cache if admin modified language text_direction. See bug #32640
			$vbcsspath = 'css.php?styleid=' . $style['styleid'] . $add_forumid . '&amp;langid=' . LANGUAGEID . '&amp;d=' . $style['dateline'] . '&amp;td=' . $vbulletin->stylevars['textdirection']['string'] . '&amp;sheet=';
		}

		return $vbcsspath;
	}

	/**
	* Returns a single template from the templatecache or the database and returns
	* the raw contents of it. Note that text will be escaped for eval'ing.
	*
	* @param	string	Name of template to be fetched
	*
	* @return	string
	*/
	public static function fetch_template_raw($template_name)
	{
		$template_code = self::fetch_template($template_name);

		if (strpos($template_code, '$final_rendered') !== false)
		{
			return preg_replace('#^\$final_rendered = \'(.*)\';$#s', '\\1', $template_code);
		}
		else
		{
			return $template_code;
		}
	}

	/**
	* Returns a single template from the templatecache or the database
	*
	* @param	string	Name of template to be fetched
	*
	* @return	string
	*/
	protected static function fetch_template($template_name)
	{
		global $vbulletin, $tempusagecache, $templateassoc;

		// use legacy postbit if necessary
		if ($vbulletin->options['legacypostbit'] AND $template_name == 'postbit')
		{
			$template_name = 'postbit_legacy';
		}

		if (isset($vbulletin->templatecache["$template_name"]))
		{
			$template = $vbulletin->templatecache["$template_name"];
		}
		else
		{
			self::$template_queries[$template_name] = true;
			$fetch_tid = intval($templateassoc["$template_name"]);
			if (!$fetch_tid)
			{
				$gettemp = array('template' => '');
			}
			else
			{
				$gettemp = $vbulletin->db->query_first_slave("
					SELECT template
					FROM " . TABLE_PREFIX . "template
					WHERE templateid = $fetch_tid
				");
			}
			$template = $gettemp['template'];
			$vbulletin->templatecache["$template_name"] = $template;
		}

		if (!isset(self::$template_usage[$template_name]))
		{
			self::$template_usage[$template_name] = 1;
		}
		else
		{
			self::$template_usage[$template_name]++;
		}

		return $template;
	}
}

abstract class vB_Template_Data extends vB_Template
{
	/**
	 * Registered templates and their local vars.
	 * The array should be in the form:
	 * 	array(template_name => array(registered, registered [,...]))
	 *
	 * @var array
	 */
	protected static $registered_templates = array();

	/**
	 * Prefix for the template token.
	 * If this is matched as the prefix of a registered variable then the value is
	 * picked up from $registered_templates.
	 */
	protected static $token_prefix = '_-_-template-_-_';

	/**
	 * Register a variable with the template.
	 * If the variable is prefixed with the template token then it is assumed as a
	 * child template and picked up from $registered_templates.
	 *
	 * @param	string	Name of the variable to be registered
	 * @param	mixed	Value to be registered. This may be a scalar or an array.
	 * @param	bool	Whether to overwrite existing vars
	 * @return	bool	Whether the var was registered
	 */
	public function register($name, $value, $overwrite = true)
	{
		if (!$overwrite AND $this->is_registered($name))
		{
			return false;
		}

		// Run register hook
		self::assert_register_hook();

		if (self::$hook_code)
		{
			eval(self::$hook_code);
		}

		if (defined('VB_API_CMS') AND VB_API_CMS === true)
		{
			$value = $this->escapeView($value);
		}

		// Convert any tokenised templates into the local vars
		$this->parse_token($value);

		$this->registered[$name] = $value;

		return true;
	}


	/**
	 * Identical to register, but registers a value as a reference.
	 *
	 * @param	string	Name of the variable to be registered
	 * @param	mixed	Value to be registered. This may be a scalar or an array.
	 * @param	bool	Whether to overwrite existing vars
	 * @return	bool	Whether the var was registered
	 */
	public function register_ref($name, &$value, $overwrite = true)
	{
		if (!$overwrite AND $this->is_registered($name))
		{
			return false;
		}

		// Run register hook
		self::assert_register_hook();

		if (self::$hook_code)
		{
			eval(self::$hook_code);
		}

		if (defined('VB_API_CMS') AND VB_API_CMS === true)
		{
			$value = $this->escapeView($value);
		}

		// Convert any tokenised templates into the local vars
		$this->parse_token($value);

		$this->registered[$name] = &$value;

		return true;
	}


	/**
	 * Checks if a registered value is a template token.
	 * If it is, the registered vars of the child template are picked up and
	 * assigned to this template.
	 *
	 * @param	string	Name of the variable to be registered
	 * @param	mixed	Value to be registered. This may be a scalar or an array.
	 * @return	bool	Whether the value was picked up as a token, or the resovled value
	 */
	public function parse_token(&$value)
	{
		if (is_array($value))
		{
			array_walk($value, array($this, 'parse_token'));
		}
		else
		{
			$matched = false;
			$matches = array();
			if (is_string($value) AND preg_match_all('#' . preg_quote(self::$token_prefix) . '(.+?):(\d+)#', $value, $matches, PREG_SET_ORDER))
			{
				$old_value = $value;
				$value = array();

				foreach ($matches AS $match)
				{
					$template_name = $match[1];
					$index = intval($match[2]);

					if (isset(self::$registered_templates[$template_name][$index]))
					{
						$value[] = self::$registered_templates[$template_name][$index];
						$matched = true;
					}
				}

				if (sizeof($value) <= 1)
				{
					$value = current($value);
				}
			}
		}

		return $matched;
	}

	protected function whitelist_filter()
	{
		global $VB_API_WHITELIST;

		// errormessage should be always added to the whitelist
		$VB_API_WHITELIST['response']['errormessage'] = '*';
		if (!$VB_API_WHITELIST['show'] AND !is_array($VB_API_WHITELIST['show']))
		{
			$VB_API_WHITELIST['show'] = '*';
		}

		$temp = array();
		$this->whitelist_filter_recur($VB_API_WHITELIST, $temp, $this->registered);
		$this->registered = $temp;

	}

	protected function whitelist_filter_recur($whitelist, &$arr, &$registered)
	{
		foreach ($whitelist as $k => $v)
		{
			if ($k !== '*')
			{
				if (is_numeric($k) AND isset($registered[$v]))
				{
					if (is_array($registered[$v]))
					{
						$this->removeShow($registered[$v]);
					}
					$arr[$v] = $registered[$v];
				}
				elseif (array_key_exists($k, (array)$registered))
				{
					if ($v === '*')
					{
						if (is_array($registered[$v]))
						{
							$this->removeShow($registered[$v]);
						}
						$arr[$k] = $registered[$k];
					}
					elseif (is_array($v))
					{
						$arr[$k] = array();
						$this->whitelist_filter_recur($whitelist[$k], $arr[$k], $registered[$k]);
						if (empty($arr[$k]))
						{
							unset($arr[$k]);
						}
					}
				}
			}
			elseif ($k === '*')
			{
				if (is_array($registered))
				{
					$registeredkeys = array_keys($registered);
					if (is_numeric($registeredkeys[0]))
					{
						foreach ($registered as $k2 => $v2)
						{
							if (is_array($whitelist[$k]) AND !in_array('show', array_keys($whitelist[$k])))
							{
								if (is_array($registered[$k2]))
								{
									$this->removeShow($registered[$k2]);
								}
							}
							$arr[$k2] = array();
							$this->whitelist_filter_recur($whitelist[$k], $arr[$k2], $registered[$k2]);
						}
					}
					else
					{
						if (is_array($whitelist[$k]) AND !in_array('show', array_keys($whitelist[$k])))
						{
							if (is_array($registered))
							{
								$this->removeShow($registered);
							}
						}
						$this->whitelist_filter_recur($whitelist[$k], $arr, $registered);
					}
				}
				else
				{
					$arr = $registered;
					unset($registered);
				}
			}
		}
	}

	protected function removeShow(&$arr)
	{
		if (is_array($arr))
		{
			unset($arr['show']);
			foreach($arr as &$v)
			{
				$this->removeShow($v);
			}
		}
	}

	protected function escapeView($value)
	{
		if (is_array($value))
		{
			foreach ($value AS &$el)
			{
				$el = $this->escapeView($el);
			}
		}

		if ($value instanceof vB_View)
		{
			$value = $value->render();
		}
		else if ($value instanceof vB_Phrase)
		{
			$value = (string)$value;
		}

		return $value;
	}


	/**
	 * Renders the template.
	 *
	 * @param	boolean	Whether to suppress the HTML comment surrounding option (for JS, etc)
	 * @return	string	Rendered version of the template
	 */
	public function render($suppress_html_comments = false, $final = false)
	{
		global $vbulletin, $show;

		$callback = vB_APICallback::instance();

		if ($final)
		{
			self::remove_common_show($show);

			// register whitelisted globals
			$this->register_globals();

			$callback->setname('result_prewhitelist');
			$callback->addParamRef(0, $this->registered);
			$callback->callback();

			if (!($vbulletin->debug AND $vbulletin->GPC['showall']))
			{
				$this->whitelist_filter();
			}

			$callback->setname('result_overwrite');
			$callback->addParamRef(0, $this->registered);
			$callback->callback();

			if ($vbulletin->debug AND $vbulletin->GPC['debug'])
			{
				return '<pre>'.htmlspecialchars(var_export($this->registered, true)).'</pre>' . '<br />' . number_format((memory_get_usage() / 1024)) . 'KB';
			}
			else
			{
				// only render data on final render
				return $this->render_output($suppress_html_comments);
			}
		}
		else
		{
			$callback->setname('result_prerender');
			$callback->addParam(0, $this->template);
			$callback->addParamRef(1, $this->registered);
			$callback->callback();
		}


		return $this->render_token();
	}


	/**
	 * Buffers locally registered vars and returns a token representation of the template.
	 *
	 * @return string
	 */
	protected function render_token()
	{
		if (!isset(self::$registered_templates[$this->template]))
		{
			self::$registered_templates[$this->template] = array();
		}

		// Buffer local vars to be picked up by the parent template
		self::$registered_templates[$this->template][] = $this->registered;

		$index = sizeof(self::$registered_templates[$this->template])-1;

		return self::$token_prefix . $this->template . ':' . $index;
	}


	/**
	 * Renders the output after preperation.
	 * @see vB_Template::render()
	 *
	 * @param boolean	Whether to suppress the HTML comment surrounding option (for JS, etc)
	 * @return string
	 */
	protected function render_output($suppress_html_comments = false)
	{
		return false;
	}

	public static function dump_templates()
	{
		return print_r(self::$registered_templates,1);
	}
}


class vB_Template_XML extends vB_Template_Data
{
	/**
	 * Renders the output after preperation.
	 * @see vB_Template::render()
	 *
	 * @param boolean	Whether to suppress the HTML comment surrounding option (for JS, etc)
	 * @return string
	 */
	protected function render_output($suppress_html_comments = false)
	{
		return xmlrpc_encode($this->registered);
	}
}

class vB_Template_JSON extends vB_Template_Data
{
	/**
	 * Renders the output after preperation.
	 * @see vB_Template::render()
	 *
	 * @param boolean	Whether to suppress the HTML comment surrounding option (for JS, etc)
	 * @return string
	 */
	protected function render_output($suppress_html_comments = false)
	{
		if (!($charset = vB_Template_Runtime::fetchStyleVar('charset')))
		{
			global $vbulletin;
			$charset = $vbulletin->userinfo['lang_charset'];
		}

		$lower_charset = strtolower($charset);
		if ($lower_charset != 'utf-8')
		{
			// Browsers tend to interpret character set iso-8859-1 as windows-1252
			if ($lower_charset == 'iso-8859-1')
			{
				$lower_charset = 'windows-1252';
			}
			$this->processregistered($this->registered, $lower_charset);
		}

		return json_encode($this->registered);
	}

	private function processregistered(&$value, $charset)
	{
		global $VB_API_REQUESTS;

		if (is_array($value))
		{
			foreach ($value AS &$el)
			{
				$this->processregistered($el, $charset);
			}
		}

		if (is_string($value))
		{
			$value = unhtmlspecialchars(to_utf8($value, $charset, true), true);
			$trimmed = trim($value);
			if ($VB_API_REQUESTS['api_version'] > 1 AND ($trimmed == 'checked="checked"' OR $trimmed == 'selected="selected"'))
			{
				$value = 1;
			}
		}

		if ($VB_API_REQUESTS['api_version'] > 1 AND is_bool($value))
		{
			if ($value)
			{
				$value = 1;
			}
			else
			{
				$value = 0;
			}
		}
	}
}

class vB_Template_Runtime
{
	public static $units = array('%', 'px', 'pt', 'em', 'ex', 'pc', 'in', 'cm', 'mm');

	public static function date($timestamp, $format = 'r')
	{
		if (empty($format))
		{
			$format = 'r';
		}
		return vbdate($format, intval($timestamp));
	}

	public static function time($timestamp)
	{
		global $vbulletin;
		if (empty($timestamp)) { $timestamp = 0; }
		return vbdate($vbulletin->options['timeformat'], $timestamp);
	}

	public static function escapeJS($javascript)
	{
		return str_replace("'", "\'", $javascript);
	}

	public static function numberFormat($number, $decimals = 0)
	{
		return vb_number_format($number, $decimals);
	}

	public static function urlEncode($text)
	{
		return urlencode($text);
	}

	public static function parsePhrase($phraseName)
	{
		global $vbphrase;
		$arg_list = func_get_args();
		$arg_list[0] = $vbphrase[$phraseName];
		return construct_phrase_from_array($arg_list);
	}

	public static function addStyleVar($name, $value, $datatype = 'string')
	{
		global $vbulletin;

		switch ($datatype)
		{
			case 'string':
				$vbulletin->stylevars["$name"] = array(
					'datatype' => $datatype,
					'string'   => $value,
				);
			break;
			case 'imgdir':
				$vbulletin->stylevars["$name"] = array(
					'datatype' => $datatype,
					'imagedir' => $value,
				);
			break;
		}
	}

	public static function fetchStyleVar($stylevar)
	{
		global $vbulletin;

		$parts = explode('.', $stylevar);
		$base_stylevar = $vbulletin->stylevars[$parts[0]];

		// this for accessing subparts of a complex data type
		if (isset($parts[1]))
		{
			$types = array(
				'background' => array(
					'backgroundColor' => 'color',
					'backgroundImage' => 'image',
					'backgroundRepeat' => 'repeat',
					'backgroundPositionX' => 'x',
					'backgroundPositionY' => 'y',
					'backgroundPositionUnits' => 'units'
				),

				'font' => array(
					'fontWeight' => 'weight',
					'units' => 'units',
					'fontSize' => 'size',
					'fontFamily' => 'family',
					'fontStyle' => 'style',
					'fontVariant' => 'variant',
				),

				'padding' => array(
					'units' => 'units',
					'paddingTop' => 'top',
					'paddingRight' => 'right',
					'paddingBottom' => 'bottom',
					'paddingLeft' => 'left',
				),

				'margin' => array(
					'units' => 'units',
					'marginTop' => 'top',
					'marginRight' => 'right',
					'marginBottom' => 'bottom',
					'marginLeft' => 'left',
				),

				'border' => array(
					'borderStyle' => 'style',
					'units' => 'units',
					'borderWidth' => 'width',
					'borderColor' => 'color',
				),
			);

			//handle is same for margin and padding -- allows the top value to be
			//used for all padding values
			if (in_array($base_stylevar['datatype'], array('padding', 'margin')) AND $parts[1] <> 'units')
			{
				if (isset($base_stylevar['same']) AND $base_stylevar['same'])
				{
					$parts[1] = $base_stylevar['datatype'] . 'Top';
				}
			}

			if (isset($types[$base_stylevar['datatype']]))
			{
				$mapping = $types[$base_stylevar['datatype']][$parts[1]];
				$output = $base_stylevar[$mapping];
			}
			else
			{
				$output = $base_stylevar;
				for ($i = 1; $i < sizeof($parts); $i++) {
					$output = $output[$parts[$i]];
				}
			}
		}
		else
		{
			$output = '';

			switch($base_stylevar['datatype'])
			{
				case 'color':
					$output = $base_stylevar['color'];
				break;

				case 'background':
					switch ($base_stylevar['x'])
					{
						case 'stylevar-left':
							$base_stylevar['x'] = $vbulletin->stylevars['left']['string'];break;
						case 'stylevar-right':
							$base_stylevar['x'] = $vbulletin->stylevars['right']['string'];break;
						default:
							$base_stylevar['x'] = $base_stylevar['x'].$base_stylevar['units'];break;
					}
					$output = $base_stylevar['color'] . ' ' . (!empty($base_stylevar['image']) ? "$base_stylevar[image]" : 'none') . ' ' .
						$base_stylevar['repeat'] . ' ' .$base_stylevar['x'] . ' ' .
						$base_stylevar['y'] .
						$base_stylevar['units'];
				break;

				case 'textdecoration':
					if ($base_stylevar['none'])
					{
						$output = 'none';
					}
					else
					{
						unset($base_stylevar['datatype'], $base_stylevar['none']);
						$output = implode(' ', array_keys(array_filter($base_stylevar)));
					}
				break;

				case 'font':
					$output = $base_stylevar['style'] . ' ' . $base_stylevar['variant'] . ' ' .
					$base_stylevar['weight'] . ' ' . $base_stylevar['size'] . $base_stylevar['units'] . ' ' .
					$base_stylevar['family'];
				break;

				case 'imagedir':
					$output = $base_stylevar['imagedir'];
				break;

				case 'string':
					$output = $base_stylevar['string'];
				break;

				case 'numeric':
					$output = $base_stylevar['numeric'];
				break;

				case 'size':
					$output =  $base_stylevar['size'] . $base_stylevar['units'];
				break;

				case 'url':
					$output = $base_stylevar['url'];
				break;

				case 'path':
					$output = $base_stylevar['path'];
				break;

				case 'fontlist':
					$output = implode(',', preg_split('/[\r\n]+/', trim($base_stylevar['fontlist']), -1, PREG_SPLIT_NO_EMPTY));
				break;

				case 'border':
					$output = $base_stylevar['width'] . $base_stylevar['units'] . ' ' .
						$base_stylevar['style'] . ' ' . $base_stylevar['color'];
				break;

				case 'dimension':
					$output = 'width: ' . intval($base_stylevar['width'])  . $base_stylevar['units'] .
						'; height: ' . intval($base_stylevar['height']) . $base_stylevar['units'] . ';';
				break;

				case 'padding':
				case 'margin':
					foreach (array('top', 'right', 'bottom', 'left') AS $side)
					{
						if ($base_stylevar[$side] != 'auto')
						{
							$base_stylevar[$side] = $base_stylevar[$side] . $base_stylevar['units'];
						}
					}
					if (isset($base_stylevar['same']) AND $base_stylevar['same'])
					{
						$output = $base_stylevar['top'];
					}
					else
					{
						if (vB_Template_Runtime::fetchStyleVar('textdirection') == 'ltr')
						{
							$output = $base_stylevar['top'] . ' ' . $base_stylevar['right'] . ' ' . $base_stylevar['bottom'] . ' ' . $base_stylevar['left'];
						}
						else
						{
							$output = $base_stylevar['top'] . ' ' . $base_stylevar['left'] . ' ' . $base_stylevar['bottom'] . ' ' . $base_stylevar['right'];
						}
					}
				break;
			}
		}

		return $output;
	}

	public static function runMaths($str)
	{
		//this would usually be dangerous, but none of the units make sense
		//in a math string anyway.  Note that there is ambiguty between the '%'
		//unit and the modulo operator.  We don't allow the latter anyway
		//(though we do allow bitwise operations !?)
		$units_found = null;
		foreach (self::$units AS $unit)
		{
			if (strpos($str, $unit))
			{
				$units_found[] = $unit;
			}
		}

		//mixed units.
		if (count($units_found) > 1)
		{
			return "/* ~~cannot perform math on mixed units ~~ found (" .
				implode(",", $units_found) . ") in $str */";
		}

		$str = preg_replace('#([^+\-*=/\(\)\d\^<>&|\.]*)#', '', $str);

		if (empty($str))
		{
			$str = '0';
		}
		else
		{
			//hack: if the math string is invalid we can get a php parse error here.
			//a bad expression or even a bad variable value (blank instead of a number) can
			//cause this to occur.  This fails quietly, but also sets the status code to 500
			//(but, due to a bug in php only if display_errors is *off* -- if display errors
			//is on, then it will work just fine only $str below will not be set.
			//
			//This can result is say an almost correct css file being ignored by the browser
			//for reasons that aren't clear (and goes away if you turn error reporting on).
			//We can check to see if eval hit a parse error and, if so, we'll attempt to
			//clear the 500 status (this does more harm then good) and send an error
			//to the file.  Since math is mostly used in css, we'll provide error text
			//that works best with that.
			$status = @eval("\$str = $str;");
			if ($status === false)
			{
				if (!headers_sent())
				{
					header($_SERVER['SERVER_PROTOCOL'] . ' 200 OK');
				}
				return "/* Invalid math expression */";
			}

			if (count($units_found) == 1)
			{
				$str = $str.$units_found[0];
			}
		}
		return $str;
	}

	public static function linkBuild($type, $info = array(), $extra = array(), $primaryid = null, $primarytitle = null)
	{
		//allow strings of form of query strings for info or extra.  This allows us to hard code some values
		//in the templates instead of having to pass everything in from the php code.  Limitations
		//in the markup do not allow us to build arrays in the template so we need to use strings.
		//We still can't build strings from variables to pass here so we can't mix hardcoded and
		//passed values, but we do what we can.

		if (is_string($info))
		{
			parse_str($info, $new_vals);
			$info = $new_vals;
		}

		if (is_string($extra))
		{
			parse_str($extra, $new_vals);
			$extra = $new_vals;
		}

		return fetch_seo_url($type, $info, $extra, $primaryid, $primarytitle);
	}
}

// #############################################################################
// misc functions

// #############################################################################
/**
* Feeds database connection errors into the halt() method of the vB_Database class.
*
* @param	integer	Error number
* @param	string	PHP error text string
* @param	strig	File that contained the error
* @param	integer	Line in the file that contained the error
*/
function catch_db_error($errno, $errstr, $errfile, $errline)
{
	global $db;
	static $failures;

	if (strstr($errstr, 'Lost connection') AND $failures < 5)
	{
		$failures++;
		return;
	}

	if (is_object($db))
	{
		$db->halt("$errstr\r\n$errfile on line $errline");
	}
	else
	{
		vb_error_handler($errno, $errstr, $errfile, $errline);
	}
}

// #############################################################################
/**
* Removes the full path from being disclosed on any errors
*
* @param	integer	Error number
* @param	string	PHP error text string
* @param	strig	File that contained the error
* @param	integer	Line in the file that contained the error
*/
function vb_error_handler($errno, $errstr, $errfile, $errline)
{
	global $vbulletin;

	switch ($errno)
	{
		case E_WARNING:
		case E_USER_WARNING:
			/* Don't log warnings due to to the false bug reports about valid warnings that we suppress, but still appear in the log
			require_once(DIR . '/includes/functions_log_error.php');
			$message = "Warning: $errstr in $errfile on line $errline";
			log_vbulletin_error($message, 'php');
			*/

			if (!error_reporting() OR !ini_get('display_errors'))
			{
				return;
			}
			$errfile = str_replace(DIR, '[path]', $errfile);
			$errstr = str_replace(DIR, '[path]', $errstr);
			echo "<br /><strong>Warning</strong>: $errstr in <strong>$errfile</strong> on line <strong>$errline</strong><br />";
		break;

		case E_USER_ERROR:
			require_once(DIR . '/includes/functions_log_error.php');
			$message = "Fatal error: $errstr in $errfile on line $errline";
			log_vbulletin_error($message, 'php');

			if (!headers_sent())
			{
				if (SAPI_NAME == 'cgi' OR SAPI_NAME == 'cgi-fcgi')
				{
					header('Status: 500 Internal Server Error');
				}
				else
				{
					header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
				}
			}

			if (error_reporting() OR ini_get('display_errors'))
			{
				$errfile = str_replace(DIR, '[path]', $errfile);
				$errstr = str_replace(DIR, '[path]', $errstr);
				echo "<br /><strong>Fatal error:</strong> $errstr in <strong>$errfile</strong> on line <strong>$errline</strong><br />";
				if (function_exists('debug_print_backtrace') AND ($vbulletin->userinfo['usergroupid'] == 6 OR ($vbulletin->userinfo['permissions']['adminpermissions'] & $vbulletin->bf_ugp_adminpermissions)))
				{
					// This is needed so IE doesn't show the pretty error messages
					echo str_repeat(' ', 512);
					debug_print_backtrace();
				}
			}
			exit;
		break;
	}
}

// #############################################################################
/**
* Unicode-safe version of htmlspecialchars()
*
* @param	string	Text to be made html-safe
*
* @return	string
*/
function htmlspecialchars_uni($text, $entities = true)
{
	if ($entities)
	{
		$text = preg_replace_callback(
			'/&((#([0-9]+)|[a-z]+);)?/si',
			'htmlspecialchars_uni_callback',
			$text
		);
	}
	else
	{
		$text = preg_replace(
			// translates all non-unicode entities
			'/&(?!(#[0-9]+|[a-z]+);)/si',
			'&amp;',
			$text
		);
	}

	return str_replace(
		// replace special html characters
		array('<', '>', '"'),
		array('&lt;', '&gt;', '&quot;'),
			$text
	);
}

function htmlspecialchars_uni_callback($matches)
{
 	if (count($matches) == 1)
 	{
 		return '&amp;';
 	}

	if (strpos($matches[2], '#') === false)
	{
		// &gt; like
		if ($matches[2] == 'shy')
		{
			return '&shy;';
		}
		else
		{
			return "&amp;$matches[2];";
		}
	}
	else
	{
		// Only convert chars that are in ISO-8859-1
		if (($matches[3] >= 32 AND $matches[3] <= 126)
			OR
			($matches[3] >= 160 AND $matches[3] <= 255))
		{
			return "&amp;#$matches[3];";
		}
		else
		{
			return "&#$matches[3];";
		}
	}
}


function css_escape_string($string)
{
	static $map = null;
	//url(<something>) is valid.

	$checkstr = strtolower(trim($string));
	$add_url = false;
	if ((substr($checkstr, 0, 4) == 'url(') AND (substr($checkstr,-1,1) == ')'))
	{
		//we need to leave the "url()" part alone.
		$add_url = true;
		$string = trim($string);
		$string = substr($string,4, strlen($string)- 5);
		if ((($string[0] == '"') AND (substr($checkstr,-1,1) == '"'))
			OR
			(($string[0] == "'") AND (substr($checkstr,-1,1) == "'")))
		{
			$string = substr($string,1, strlen($string)- 2);
		}
	}

	if(is_null($map))
	{
		$chars = array(
			'\\', '!', '@', '#', '$', '%', '^',  '*', '"', "'",
			'<', '>', ',', '`', '~','/','&', '.',':', ')','(', ';'
		);

		foreach ($chars as $char)
		{
			$map[$char] = '\\' . dechex(ord($char)) . ' ';
		}
		//var_dump($map);
	}

	$string = str_replace(array_keys($map), $map, $string);

	//add back the url() if we need it.
	if ($add_url)
	{
		$string = 'url(\'' . $string . '\')';
	}
	return $string;
}
/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 46878 $
|| ####################################################################
\*======================================================================*/
