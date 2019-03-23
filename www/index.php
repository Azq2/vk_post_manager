<?php
require_once __DIR__."/../bootstrap.php";

if (php_sapi_name() == "cli") {
	\Z\Task\Runner::instance()->run($argv);
} else {
	\Smm\App::instance()->route();
}
