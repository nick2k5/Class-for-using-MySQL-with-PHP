<?php

/*
 * Nick Alexander
 * nick.alexander@duke.edu
 * 
 * A class for using MYSQL with PHP
 * Written: May 16, 2009
 * 
 * http://www.opensource.org/licenses/mit-license.php
 * 
 * Sample use:
 * <?php
 * 
 * 		********* Setup ********
 * 		If you are running your database on a single server, simply edit the location, user, password, and database name instance variables at the top of the class
 * 		* For instances when you see the arguemnt $db for a method, simply ignore it (this is always an optional argument)
 * 		
 * 		If you have you database broken up onto more than 1 server, then you will need to customize the getConnectionInfo() method details
 * 		* You will be able to use the $db argument to pass info on how to connect to the appropriate database in the getConnectionInfo method 
 * 		
 * 		******** 3 main methods: select, insert, update ***********
 * 		** all values entered into the database are sanitized for you (check sanitize method to see if it meets your standards)	
 * 		** check comments with specific methods for more details	
 * 
 * 		$select = database::select("user_id, email", "users", "register_type >", $type, "interests =", $user_interest, "LIMIT 0, 10", $db);
 *		// OR
 *		$select = database::select($desired_fields, $table, "$field1 >", $value1, "field2 =", $value2, .. (additional fields/values)...., "LIMIT 0, 10", $db);
 *		
 *		// returns rows in an array
 *		foreach ($select as $user)
 *		{
 *			$email = $user['email'];
 *			$id = $user['id'];
 *		}		
 *
 *		$insert = database::insert("users", "email", "nick@nick.com", "name", "nick", $db);
 *		//OR
 *		$insert = database::insert($table, $field1, $value1, $field2, $value2,.. (additional fields/values).... , $db);
 * 		
 * 		// return
 * 		$id = $insert; 	// returns mysql_insert_id()	
 * 
 * 		$update = database::update("users", "email", $email, "name", $name, "WHERE", "id >",15, $db)
 * 		// OR
 * 		update($table, $field2, $value2, $field2, $value2, ...(additional fields/values to update).... , "WHERE", "$field1 >",$value1, ....(additional fields/values for condition to update)... , $db)
 * 		
 * 		
 * 
 * 		********* For more complex queries, you can also enter the SQL directly *********
 * 
 * 		// NB: you must sanitize inputs yourself if you use this method (use sanitize() method)
 * 		database::query("SELECT user_ids FROM users WHERE name IN (SELECT name FROM friends WHERE user_id2 = $user_id1)", "select", $db);
 * 		
 * 		
 * 
 * ?>
 */

class database
{
	
	// these are customized
	private static $location = "LOCATION OF YOUR DB (probably localhost)";
	private static $user = "USERNAME";
	private static $password = "PASSWORD";
	private static $database = "DATABASE NAME";
	
	public static function query($sql, $type = "", $db = false)
	{
		// $type = type of query.... determintes return type
		// $db = database identifying info... to use with getConnectionInfo method
		
		$connection_info = self::getConnectionInfo($db);
		$link = mysql_connect($connection_info['location'], $connection_info['user'], $connection_info['password']);
		if (!link)
		{
			self::error("Could not connect to database");
			return false;
		}
		
		$db_selected = mysql_select_db($connection_info['database'], $link);
		if (!$db_selected)
		{
			self::error("Could not connect to database");
			return false;
		}
		
		$result = mysql_query($sql, $link);	
		if (mysql_error())
		{
			self::error("SQL: $sql ERROR: ". mysql_error());
			return false;
		}
		if ($type == "select")
		{
			$out = self::sqlOutputToArray($result);
			mysql_free_result($result);
		}
		elseif ($type == "insert")
		{
			$out = mysql_insert_id($link);
		}
		elseif ($type == "update")
		{
			$out = intval(mysql_affected_rows($link));
		}
		else
		{
			$out = $result;
		}
		mysql_close($link);
		return $out;
	}
	
	public static function select($select_fields, $table /* conditional parameters, extra parameters, and db selection info (optional) */)
	{
		/*
		$select_fields = a comma delimited string of the fields to be returned
		$table = name of the table(s) to select from (comma delimted if more than 1)
		
		* next arguments are conditional statements in pairs in the form "column_name comparator", "value"
		** e.g. select($select_fields, $table, "column1 >", 2, "column2 =", "cats" , ...... , $etc, $db);
		** N.B. These parameters must be IN PAIRS! 
		**		To do a query such as "SELECT * FROM users WHERE 1", you must add a blank string as a second argument before the "1"
				e.g. select('*', users, '', 1, ''); 
		
		***	sanitization will be performed on the values being entered	
		
		
		* after all of the conditionals, the next argument is $etc
		$etc = ordering, grouping, limiting, etc statements
		** e.g. "ORDER BY user_id DESC LIMIT 0, 10"
		** N.B. this parameter is REQUIRED. You must at least provide a blank string for it!
		
		* the last argument is $db, and it is optional
		$db = database identified (optional)
		** this argument is used in your getConnectionInfo() method to select your database
		** it is up to you how to define this parameter, depeding on the complexity of your network of databases
		** if your database is all on 1 server (95% of users), you can ignore this parameter
		
		
		return value is an array of associative array in the form return[0]['field_name'] = value 
		 
		 
		*/
		
		$argv = func_get_args();
		if (count($argv) < 2)
		{
			self::error("Too few aruguments");
			return false;
		}
		
		if (count($argv) % 2 == 0) 
		{
			// $db parameter is provided
			$etc_parameters = $argv[count($argv) - 2];
			$db = $argv[count($argv) - 1];	
			
			$end_of_conditionals = count($argv) - 2;
		}
		else 
		{
			// $db parameter is not provided
			$etc_parameters = $argv[count($argv) - 1];
			$db = false;
			
			$end_of_conditionals = count($argv) - 1;
		}
		
		$where_array = array();
		for ($i = 2; $i < $end_of_conditionals; $i = $i + 2)
		{
			$value = self::sanitize($argv[$i + 1]);
			$where_array[] = $argv[$i] . (is_string($value) ? ("'" . $value . "'") : $value );	// add quotes if value is a string
		}
		
		$where_clause = implode(" AND ", $where_array);
		$sql = "SELECT $select_fields FROM $table WHERE " . $where_clause . ( (strlen($etc_parameters) > 0) ? (" " . $etc_parameters) : ""); 
		
		return self::query($sql, "select", $db);
	}
	
	public static function sanitize($x)
	{
		if (is_string($x))
		{
			$link = self::getDbLink();
			$x = mysql_real_escape_string($x, $link);
			mysql_close($link);
			return $x;
		}
		
		elseif (is_scalar($x)) return $x;	// float/int/boolean
		
		else 
		{
			// array, object, resource, null... not sure what to do with these
			self::error("Bad input type");
			return "";	
		}
	}
	
	public static function insert($table /* additional arguments... */)
	{
		/*
		  $table = table name
		  
		  * next arguments are pairs of columns, values to insert
		  ** e.g. insert("users", "email", $email, "name", $name, ....... , $db)
		  
		  ***	sanitization will be performed on the values being entered	
		  
		 
		 * last argument is $db, and is optional
		 $db = database identifier (optional)
		  
		  
		 */
		
		$argv = func_get_args();
		if (count($argv) < 1)
		{
			self::error("Too few aruguments");
			return false;
		}
		
		if (count($argv) % 2 == 1) 
		{
			// $db parameter is provided
			$db = $argv[count($argv) - 1];	
			$end_of_values = count($argv) - 1;
		}
		else 
		{
			// $db parameter is not provided
			$db = false;			
			$end_of_values = count($argv);
		}
		
		$fields = array();
		$values = array();
		for ($i = 1; $i < $end_of_values; $i = $i + 2)
		{
			$value = self::sanitize($argv[$i + 1]);
			$fields[] = $argv[$i];
			$values[] = "" . (is_string($value) ? ("'" . $value . "'") : $value);
		}
		
		$fields = implode(",", $fields);
		$values = implode(",", $values);
		
		$sql = "INSERT into $table ($fields) VALUES ($values)";
		
		return self::query($sql, "insert", $db);
	}
	
	public static function update($table /* additional arguments */)
	{
		/*
			$table = table name
		  
			* next arguments are pairs of columns, values to be updated
			** e.g. update("users", "email", $email, "name", $name, ....... , "WHERE", "id >",15, ....... , $db)
			
			* after all columns, values to be updated, include the an argument with value $arg = "WHERE" to seperate the next series of arguments
			
			* after the "WHERE" seperator, next arguments are conditional statements in pairs in the form "column_name comparator", "value"
			** e.g. select($select_fields, $table, "column1 >", 2, "column2 =", "cats" , ...... , $etc, $db);
			** N.B. These parameters must be IN PAIRS! 
			**		To do a query such as "SELECT * FROM users WHERE 1", you must add a blank string as a second argument before the "1"
				e.g. select('*', users, '', 1, ''); 
		
			***	sanitization will be performed on the values being entered	
			
			 
			* last argument is $db, and is optional
			$db = database identifier (optional)
		*/ 
		
		$argv = func_get_args();
		if (count($argv) < 3)
		{
			self::error("Too few aruguments");
			return false;
		}
		
		if (count($argv) % 2 == 1) 
		{
			// $db parameter is provided
			$db = $argv[count($argv) - 1];	
			$end_of_conditionals = count($argv) - 1;
		}
		else 
		{
			// $db parameter is not provided
			$db = false;			
			$end_of_conditionals = count($argv);
		}
		
		$where_pos = array_search("WHERE", $argv);
		if ($where_pos === false)
		{
			self::error("No 'true' seperator found in update call");
			return false;
		}
		
		$update_array = array();
		for ($i = 1; $i < $where_pos; $i = $i + 2)
		{
			$value = self::sanitize($argv[$i + 1]);
			$update_array[] = $argv[$i] . "=" . (is_string($value) ? ("'" . $value . "'") : $value);
		}
		
		$where_array = array();
		for ($i = $where_pos + 1; $i < $end_of_conditionals; $i = $i +2)
		{
			$value = self::sanitize($argv[$i + 1]);
			$where_array[] = $argv[$i] . (is_string($value) ? ("'" . $value . "'") : $value );	// add quotes if value is a string
		}
		
		$update = implode(",", $update_array);
		$where = implode(",", $where_array);
		
		$sql = "UPDATE $table SET " . $update . " WHERE " . $where;
		
		return self::query($sql, "update", $db);
	}
	
	private static function sqlOutputToArray($result)
	{
		$output = array();
		while ($row = mysql_fetch_assoc($result))
		{
			$output[] = $row;
		}
		return $output;
	}
	
	private static function error($x)
	{
		// custom
		echo "ERROR: $x";
	}
	
	private static function getConnectionInfo($id)
	{
		// this part is custom
		return array("location" => self::$location, "user" => self::$user, "password" => self::$password, "database" => self::$database);
	}
	
	private static function getDbLink()
	{
		// custom
		$link = mysql_connect(self::$location, self::$user, self::$password);
		return $link;
	}
}

?>