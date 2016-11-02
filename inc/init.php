<?php
define('H', dirname(__FILE__)."/");

require_once "config.php";
require_once "func.php";

ini_set('memory_limit', '128M');
@mysql_connect(DB_HOST, DB_USER, DB_PASS) or die("mysql_connect: ".mysql_error());
mysql_select_db(DB_NAME) or die("mysql_select_db: ".mysql_error());
mysql_set_charset('UTF8');
date_default_timezone_set('Europe/Moscow');
