<?php
define('APP', __DIR__."/");

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 'On');
ini_set('memory_limit', '128M');
date_default_timezone_set('Europe/Moscow');
mb_internal_encoding('UTF-8');

spl_autoload_register(function ($class) {
	$class = APP.'lib/'.str_replace("\\", "/", $class).".php";
	if (file_exists($class))
		include_once $class;
});
