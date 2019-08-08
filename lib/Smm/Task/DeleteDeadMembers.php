<?php
namespace Smm\Task;

use \Z\DB;
use \Z\Config;
use \Z\Util\Url;
use \Z\Net\VkApi;

use \Smm\VK\Captcha;

class DeleteDeadMembers extends \Z\Task {
	public function options() {
		return [
			'group_id' => 0
		];
	}
	
	public function run($args) {
		echo date("Y-m-d H:i:s")."\n";
		
		$vk = new VkApi(\Smm\Oauth::getAccessToken('VK'));
		
		$offset = 0;
		$errors = 0;
		
		echo date("H:i:s d/m/Y")." #".$args['group_id'].": fetch all members\n";
		while (true) {
			$result = $vk->exec("groups.getMembers", [
				"group_id"		=> $args['group_id'], 
				"count"			=> 1000, 
				"fields"		=> "last_seen", 
				"offset"		=> $offset
			]);
			
			if ($result->success()) {
				$users_cnt = $result->response->count;
				
				foreach ($result->response->items as $u) {
					if ($u->deactivated ?? false) {
						if (!isset($dead_stat[$u->deactivated]))
							$dead_stat[$u->deactivated] = 0;
						++$dead_stat[$u->deactivated];
					} else if ($u->last_seen) {
						if (time() - $u->last_seen->time >= 3600 * 24 * 7) {
							if (!isset($dead_stat['last_seen']))
								$dead_stat['last_seen'] = 0;
							++$dead_stat['last_seen'];
						}
					} else {
						if (!isset($dead_stat['last_seen2']))
							$dead_stat['last_seen2'] = 0;
						++$dead_stat['last_seen2'];
					}
				}
				
				if ($result->response->count <= $offset + 1000)
					break;
				$offset += 1000;
				usleep(100000);
			} else {
				if (++$errors > 5) {
					echo date("H:i:s d/m/Y")." too many error! :(\n";
					return;
				}
				
				echo date("H:i:s d/m/Y")." ".$result->error()."\n";
				sleep(10);
			}
		}
		
		var_dump($dead_stat, $users_cnt);
	}
}
