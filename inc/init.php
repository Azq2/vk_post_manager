<?php
define('H', dirname(__FILE__)."/");

require_once "config.php";
require_once "func.php";

ini_set('memory_limit', '128M');
date_default_timezone_set('Europe/Moscow');

// Автозагрузка классов
spl_autoload_register(function ($class) {
	require_once H.'lib/'.str_replace("\\", "/", $class).".php";
});

Mysql::connect([
	'host'	=> DB_HOST, 
	'user'	=> DB_USER, 
	'pass'	=> DB_PASS, 
	'db'	=> DB_NAME
]);
