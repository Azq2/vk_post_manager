<?php
namespace Smm\Task;

use \Z\DB;
use \Z\Config;
use \Z\Util\Url;

use \Smm\VK\Captcha;

class DeleteDeadMembers extends \Z\Task {
	public function options() {
		return [
			'group_id'		=> 0, 
			'type'			=> '', 
			'commit'		=> 0
		];
	}
	
	public function run($args) {
		echo date("Y-m-d H:i:s")."\n";
		
		$for_delete = [];
		
		switch ($args['type']) {
			case "dead":
				$for_delete = DB::select()
					->from('vk_comm_users')
					
					->orOpenGroup()
						->where('deactivated', 'IN', [1, 3])
						->where('cid', '=', $args['group_id'])
					->orCloseGroup()
					
					->orOpenGroup()
						->where('last_seen', '<=', date("Y-m-d H:i:s", time() - 3600 * 24 * 30 * 6))
						->where('last_seen', '>', "2000-01-01 00:00:00")
						->where('deactivated', '=', 0)
						->where('cid', '=', $args['group_id'])
					->orCloseGroup()
					
					->execute()
					->asArray();
			break;
			
			case "passive":
				$for_delete = DB::select()
					->from('vk_comm_users')
					->andOpenGroup()
						->orWhere('last_activity', '=', NULL)
						->orWhere('last_activity', '<', date('Y-m-d H:i:s', time() - 3600 * 24 * 30 * 5))
					->andCloseGroup()
					->where('join_date', '<=', date('Y-m-d H:i:s', time() - 3600 * 24 * 30 * 2))
					->where('cid', '=', $args['group_id'])
					->order('join_date', 'ASC')
					->execute()
					->asArray();
			break;
		}
		
		$api = new \Smm\VK\API(\Smm\Oauth::getAccessToken('VK'));
		
		echo "For delete: ".count($for_delete)."\n";
		
		foreach ($for_delete as $u) {
			$info = $u['last_seen'];
			if ($u['deactivated'] == 1) {
				$info = 'Заблокирован';
			} elseif ($u['deactivated'] == 3) {
				$info = 'Заблокирован';
			}
			
			echo "https://vk.com/id".$u['uid']." ".$u['first_name']." ".$u['last_name']." $info\n";
			
			if ($args['commit']) {
				$res = $api->exec("groups.removeUser", [
					'user_id'		=> $u['uid'], 
					'group_id'		=> $args['group_id']
				]);
				for ($i = 0; $i < 3; ++$i) {
					if ($res->success()) {
						echo "\t=>deleted\n";
						break;
					} else {
						echo "\t=>error: ".$res->error()."\n";
					}
				}
				usleep(300000);
			}
		}
	}
}
