<?php
namespace Z;

class DB {
	public static function instance($name = 'default') {
		return DB\Connection::instance($name);
	}
	
	public static function __callStatic($method, $args) {
		return call_user_func_array(array(DB\Connection::instance(), $method), $args);
	}
}
