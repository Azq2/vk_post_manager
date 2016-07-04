<?php
	define('H', dirname(__FILE__)."/");
	
	require_once "config.php";
	require_once "func.php";
	
	@mysql_connect("127.0.0.1", "root", "qwerty");
	mysql_select_db('test');
	mysql_set_charset('UTF8');
	date_default_timezone_set('Europe/Moscow');
	