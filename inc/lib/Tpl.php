<?php

class Tpl {
	private static $globals = array();
	public static function setGlobals($v) {
		self::$globals = $v;
	}
	public static function render($__file, $args = array()) {
		extract(self::$globals);
		extract($args);
		ob_start();
		include H."tpl/".$__file.".php";
		return ob_get_clean();
	}
}
