<?php
namespace Smm\Utils;

class Lock {
	protected static $locks = [];
	
	public static function lock($title) {
		$key = md5($title);
		
		if (!self::$locks) {
			register_shutdown_function(function () {
				foreach (self::$locks as $lock) {
					flock($lock['fp'], LOCK_EX | LOCK_NB);
					fclose($lock['fp']);
					unlink($lock['file']);
				}
			});
		}
		
		$fp = fopen(APP."/tmp/lock.$key", "w+");
		if (!$fp || !flock($fp, LOCK_EX | LOCK_NB))
			return false;
		
		self::$locks[$key] = [
			'fp'	=> $fp, 
			'file'	=> APP."/tmp/lock.$key"
		];
		
		return true;
	}
}
