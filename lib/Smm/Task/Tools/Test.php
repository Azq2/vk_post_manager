<?php
namespace Smm\Task\Tools;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Config;
use \Z\Util\Url;
use \Z\Net\Anticaptcha;

use \Smm\VK\Captcha;

class Test extends \Z\Task {
	public function options() {
		return [
			'post'		=> ''
		];
	}
	
	public function run($args) {
		
	}
}
