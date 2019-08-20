<?php
namespace Smm\Task\Tools;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Config;
use \Z\Util\Url;
use \Z\Net\VkApi;
use \Z\Net\Anticaptcha;

use \Smm\VK\Captcha;

class Test extends \Z\Task {
	public function options() {
		return [
			'post'		=> ''
		];
	}
	
	public function run($args) {
		$vk_web = \Smm\VK\Web::instance();
		
		var_dump($vk_web->wallEdit(0, [
			'owner_id'		=> -183242985, 
			'message'		=> '', 
			'publish_date'	=> 3
		]));
		
//		var_dump($vk_web->auth("79996317255", "SIfX3ibrzxzIaGCmiHpcihVmcgtJ 2"));
	}
}
