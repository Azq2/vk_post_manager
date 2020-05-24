<?php
namespace Smm\Task;

use \Z\DB;
use \Z\Config;
use \Z\Util\Url;

class TestModel extends \Z\Model\ActiveRecord {
	public static function table() {
		return "vk_widget_top_users";
	}
}

class Test extends \Z\Task {
	public function run($args) {
		var_dump(new TestModel());
	}
}
