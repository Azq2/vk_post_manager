<?php
namespace Z;

class Config {
	private static $cache = [];
	
	public static function get() {
		$parts = explode(".", implode(".", func_get_args()));
		$file = array_shift($parts);
		
		if (!isset(self::$cache[$file]))
			self::$cache[$file] = require APP.'config/'.$file.'.php';
		
		$ref = &self::$cache[$file];
		foreach ($parts as $p) {
			if (!isset($ref[$p]))
				return NULL;
			$ref = &$ref[$p];
		}
		
		return $ref;
	}
}
