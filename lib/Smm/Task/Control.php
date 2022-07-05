<?php
namespace Smm\Task;

use \Z\DB;
use \Z\Date;
use \Z\Util\Url;

class Control extends \Z\Task {
	public function run($args) {
		if (!\Smm\Utils\Lock::lock(__CLASS__)) {
			echo "Already running.\n";
			return;
		}
		
		echo date("Y-m-d H:i:s")." - start.\n";
		if (file_exists(APP."www/files/kill-daemons")) {
			unlink(APP."www/files/kill-daemons");
			system("killall php");
			echo "Killing all daemons...\n";
		}
		echo date("Y-m-d H:i:s")." - done.\n";
	}
}
