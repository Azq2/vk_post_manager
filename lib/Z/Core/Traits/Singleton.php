<?php
namespace Z\Core\Traits;

trait Singleton {
	private static $__instance;
	
	public static function instance() {
		if (!self::$__instance) {
			$class = get_called_class();
			self::$__instance = new $class;
		}
		return self::$__instance;
	}
}
