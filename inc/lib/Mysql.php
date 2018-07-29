<?php
class Mysql {
	private static $queries = [];
	private static $link;
	private static $config;
	private static $last_time;
	private static $debug;
	
	public static function connect($db_config) {
		self::$config = $db_config;
		self::$link = NULL;
		if (is_null(self::$debug))
			self::$debug = php_sapi_name() != "cli";
	}
	
	public static function debug($debug) {
		self::$debug = (bool) $debug;
	}
	
	public static function link() {
		if (self::$link && time() - self::$last_time > 60 && !self::$link->ping()) {
			// Коннект к mysql отвалился по таймауту
			self::$link = NULL;
		}
		
		if (!self::$link) {
			$db_config = self::$config;
			$parts = explode(":", $db_config['host']);
			$link = new mysqli($parts[0], $db_config['user'], $db_config['pass'], NULL, isset($parts[1]) ? $parts[1] : NULL);
			if ($link->connect_error)
				die("MYSQL CONNECT: ".$link->connect_error);
			$link->set_charset('utf8mb4');
			$link->select_db($db_config['db']);
			self::$link = $link;
		}
		self::$last_time = time();
		
		return self::$link;
	}
	
	public static function prepare($query, $argv = array()) {
		if ($argv) {
			$argc = count($argv);
			$len = strlen($query);
			$pos = 0; $arg_n = 0;
			while (($pos = strpos($query, '?', $pos)) !== false) {
				$value = self::value($argv[$arg_n]);
				$query = substr_replace($query, $value, $pos, 1);
				$pos += strlen($value) - 1;
				++$arg_n;
				if ($arg_n > $argc)
					break;
			}
		}
		return $query;
	}
	
	public static function escape($str) {
		return self::link()->real_escape_string($str);
	}
	
	public static function value($str) {
		if (is_array($str)) {
			$tmp = [];
			foreach ($str as $v)
				$tmp[] = self::value($v);
			return implode(", ", $tmp);
		}
		if (is_null($str))
			return "NULL";
		if (is_bool($str))
			return $str ? 1 : 0;
		return "'".self::link()->real_escape_string($str)."'";
	}
	
	public static function begin() {
		self::query("START TRANSACTION");
	}
	
	public static function commit() {
		self::query("COMMIT");
	}
	
	public static function rollback() {
		self::query("ROLLBACK");
	}
	
	public static function query($query, $args = []) {
		$args = func_get_args();
		array_shift($args);
		$query = self::prepare($query, $args);
		// echo "\n\n[SQL] ".preg_replace("/\s+/", " ", $query)."\n\n";
		
		$link = self::link();
		
		$start = microtime(true);
		$r = $link->query($query);
		$end = microtime(true);
		
		$result = new MysqlResult($link, $r);
		
		if (!$r)
			throw new Exception("MYSQL ERROR: ".$link->error.", QUERY: \n".$query."\n");
		
		if (self::$debug && !preg_match("/^\s*(FLUSH|EXPLAIN|SHOW|FLUSH)/i", $query)) {
			$cost = self::query("SHOW SESSION STATUS LIKE 'Last_query_cost'")->result(1);
			self::$queries[] = array(
				'query' => $query, 
				'time' => $end - $start, 
				'cost' => $cost
			);
		}
		
		return $result;
	}
	
	public static function getQueriesList() {
		return self::$queries;
	}
}
