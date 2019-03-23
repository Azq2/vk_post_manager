<?php
define('H', dirname(__FILE__)."/");
define('APP', dirname(__FILE__)."/../");

require_once "config.php";
require_once "func.php";

ini_set('memory_limit', '128M');
date_default_timezone_set('Europe/Moscow');
mb_internal_encoding('UTF-8');

// Автозагрузка классов
spl_autoload_register(function ($class) {
	$class = H.'lib/'.str_replace("\\", "/", $class).".php";
	echo $class;
	if (file_exists($class))
		include_once $class;
});

Mysql::connect([
	'host'	=> DB_HOST, 
	'user'	=> DB_USER, 
	'pass'	=> DB_PASS, 
	'db'	=> DB_NAME
]);
