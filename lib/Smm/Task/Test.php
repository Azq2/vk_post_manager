<?php
namespace Smm\Task;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;
use \Z\Net\AMQP;

class Test extends \Z\Task {
	public function run($args) {
		$amqp = AMQP::instance();
		$amqp->declareQueue('posts_downloader', ['durable' => true]);
		
		$amqp->publish('ololo', '', 'posts_downloader');
		
		$amqp->consume('posts_downloader', function () {
			var_dump(func_get_args());
			return false;
		});
		
		var_dump(new \Z\Net\AMQP\Message('ololo'));
	}
}
