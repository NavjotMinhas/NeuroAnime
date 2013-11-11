<?php
/*
 * @Author: Navjot Minhas
 */
require_once ('config.php');
class modules {

	static $CONNECTION;

	public static function connectMySQL() {
		modules::$CONNECTION = mysql_connect(config::getServer(), config::getUser(), config::getPassword());
		if(!modules::$CONNECTION) {
			die("Connection error" . mysql_error());
		}
		return true;
	}

	public static function selectDB($dbName) {
		modules::connectMySQL();
		return              mysql_select_db($dbName);
	}

	/*
	 * returns an array always. A 1D array is returned if the LIMIT 1 keyword is used , if it is not the function
	 * will return a 2D array
	 */
	public static function getQueryResults($mysqlStatement,$values) {
		if(!modules::$CONNECTION) {
			if(!modules::selectDB(config::getDatabaseName())) {
				die('Database error: ' . mysql_error());
			}
		}
		$query;
		if(is_array($values)){
			for($i=0;$i<count($values);$i++){
				$values[$i]=modules::sanitize($values[$i]);
			}
			$query=vsprintf($mysqlStatement,$values);
		}else{
			if($values!=false){
				$values=modules::sanitize($values);
				$query=sprintf($mysqlStatement,$values);
			}else{
				$query=$mysqlStatement;
			}
		}
		$result = mysql_query($query);
		
		if($result) {
			if(!strpos($query, "LIMIT 1")) {
				$array = Array();
				if($result != null) {
					while(($temp = mysql_fetch_assoc($result)) != null) {
						$array[] = $temp;
					}
				}
				return $array;
			} else {

				$row = mysql_fetch_assoc($result);
				if($row != null) {
					return $row;
				} else {
					return null;
				}
			}
		} else {
			return null;
		}
	}

	public static function addData($fields, $values, $table) {
		if(!modules::$CONNECTION) {
			if(!modules::selectDB(config::getDatabaseName())) {
				die('Database error: ' . mysql_error());
			}
		}
		if(count($fields) != count($values)) {
			throw new Exception('Error adding data to database both arrays must be of the same size');
		} else {
			for($i=0;$i<count($values);$i++){
				$values[$i]=modules::sanitize($values[$i]);
			}
			//echo "INSERT INTO $table (" . implode(',', $fields) . ') VALUES (\'' . implode('\',\'', $values) . '\')';
			return           mysql_query("INSERT INTO $table (" . implode(',', $fields) . ') VALUES (\'' . implode('\',\'', $values) . '\')');
		}
	}

	public static function updateData($fields, $values, $table, $whereClause) {
		if(!modules::$CONNECTION) {
			if(!modules::selectDB(config::getDatabaseName())) {
				die('Database error: ' . mysql_error());
			}
		}
		if(is_array($fields) && is_array($values)) {
			if((count($fields) != count($values))) {
				throw new Exception('Error updating data in the database both arrays must be of the same size');
			} else {
				$mysqlStatement = 'UPDATE ' . $table . ' SET ';
				for($i = 0; $i < count($fields); $i++) {
					$mysqlStatement .= $fields[$i] . ' = \'' . $values[$i] . '\'';
				}
				echo $mysqlStatement . ' ' . $whereClause;
				return          mysql_query($mysqlStatement);
			}
		} else {
			$mysqlStatement = 'UPDATE ' . $table . ' SET ' . $fields . ' = \'' . $values . '\'' . $whereClause;
			return          mysql_query($mysqlStatement);
		}
	}
	
	public static function deleteRow($mysqlStatement){
		if(!modules::$CONNECTION) {
			if(!modules::selectDB(config::getDatabaseName())) {
				die('Database error: ' . mysql_error());
			}
		}
		return mysql_query($mysqlStatement);
	}

	public static function closeConnection() {
		if(isset(modules::$CONNECTION)) {
			return              mysql_close(modules::$CONNECTION);
		} else {
			return null;
		}
	}

	//--------------------------------------------------//

	public static function getList($field, $table, $uniqueField, $uniqueID) {
		$list = modules::getQueryResults('SELECT ' . $field . ' FROM ' . $table . ' WHERE ' . $uniqueField . '=\'' . $uniqueID . '\' LIMIT 1');
		if($list) {
			return (explode(',', $list[$field]));
		} else {
			return null;
		}

	}

	public static function updateList($field, $value, $table, $uniqueField, $uniqueID) {
		$whereClause = ' WHERE ' . $uniqueField . ' =\'' . $uniqueID . '\'';
		$list = modules::getQueryResults('SELECT ' . $field . ' FROM ' . $table . $whereClause . ' LIMIT 1');
		if($list) {
			$items = explode(',', $list[$field]);
			$items[]=$value;
			return modules::updateData($field, implode(',',$items), $table, $whereClause);
		} else {
			return null;
		}
	}

	public static function removeListItem($field, $value, $table, $uniqueField, $uniqueID) {
		$whereClause = ' WHERE ' . $uniqueField . ' =\'' . $uniqueID . '\'';
		$list = modules::getQueryResults('SELECT ' . $field . ' FROM ' . $table . $whereClause . ' LIMIT 1');
		if($list) {
			if((strrpos($list[$field], $value)) !== false) {
				$items = explode(',', $list[$field]);
				$temp=Array();
				foreach($items as $listItem) {
					echo $listItem;
					if($listItem != $value) {
						$temp[]=$listItem;
					}
				}
				return modules::updateData($field, implode(',',$temp), $table, $whereClause);
			}else{
				return null;
			}
		} else {
			return null;
		}
	}
	public static function cleanInput($input) {

  		$search = array(
    		'@<script[^>]*?>.*?</script>@si',   // Strip out javascript
    		'@<[\/\!]*?[^<>]*?>@si',            // Strip out HTML tags
    		'@<style[^>]*?>.*?</style>@siU',    // Strip style tags properly
    		'@<![\s\S]*?--[ \t\n\r]*>@'         // Strip multi-line comments
  		);
	
    	$output = preg_replace($search, '', $input);
    	return $output;
  	}
  	public static function sanitize($input) {
    	if (is_array($input)) {
        	foreach($input as $var=>$val) {
            	$output[$var] = sanitize($val);
        	}
    	}
    	else {
        	if (get_magic_quotes_gpc()) {
            	$input = stripslashes($input);
       		}
			if(!modules::$CONNECTION) {
				if(!modules::selectDB(config::getDatabaseName())) {
					die('Database error: ' . mysql_error());
				}
		}
        	$input  = modules::cleanInput($input);
        	$output = mysql_real_escape_string($input);
    	}	
    	return $output;
	}
}

//modules::getList('username','user_profile', 'username', 'Navjot');
//modules::updateList('anime_list', 'Array', 'user_profile', 'username', 'Navjot');
//modules::removeListItem('anime_list', 'Array', 'user_profile', 'username', 'Navjot');
?>
