<?php
require_once __DIR__."/../bootstrap.php";

if (php_sapi_name() == "cli") {
	\Z\Core\Task\Runner::instance()->run($argv);
} else {
	\Z\Smm\App::instance()->route();
}
