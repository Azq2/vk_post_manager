<?php
namespace Smm\Task\Tools;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Config;
use \Z\Net\Anticaptcha;

use \Smm\VK\Captcha;

class CheckLikeBots extends \Z\Task {
	public function options() {
		return [
			'post'		=> ''
		];
	}
	
	public function run($args) {
		if (!preg_match("/([-\d]+)_(\d+)/", $args['post'], $m))
			throw new \Exception("Unknown post url!");
		
		$owner_id = $m[1];
		$id = $m[2];
		
		$start = $id;
		
		$api = new \Smm\VK\API(\Smm\Oauth::getAccessToken('VK_GRABBER'));
		
		while ($start > 0) {
			$chunk = [];
			while (count($chunk) < 100 && $start > 0) {
				$chunk[] = $owner_id."_".$start;
				--$start;
			}
			
			for ($i = 0; $i < 3; ++$i) {
				$res = $api->exec("wall.getById", [
					'posts'		=> implode(",", $chunk)
				]);
				if ($res->success())
					break;
			}
			
			if (!$res->success()) {
				echo $res->error();
				return;
			}
			
			foreach ($res->response as $post) {
				if ($post->post_type == 'post_ads')
					echo date("Y-m-d H:i:s", $post->date)." - https://vk.com/wall".$post->owner_id."_".$post->id."\n";
			}
		}
	}
}
