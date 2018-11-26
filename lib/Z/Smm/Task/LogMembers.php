<?php
namespace Z\Smm\Task;

use \Z\Core\DB;
use \Z\Core\Config;
use \Z\Core\Util\Url;
use \Z\Core\Net\VkApi;

use \Z\Smm\VK\Captcha;

class LogMembers extends \Z\Core\Task {
	public function run($args) {
		if (!\Z\Smm\Utils\Lock::lock(__CLASS__)) {
			echo "Already running.\n";
			return;
		}
		
		echo date("Y-m-d H:i:s")."\n";
		
		$vk = new VkApi(\Z\Smm\Oauth::getAccessToken('VK'));
		
		$groups = DB::select()
			->from('vk_groups')
			->order('pos', 'ASC')
			->execute();
		
		foreach ($groups as $group) {
			$time = time();
			
			// Получаем ранее сохранённый список юзеров
			$old_users = DB::select()
				->from('vk_comm_users')
				->where('cid', '=', $group['id'])
				->execute()
				->asArray('uid', 'uid');
			
			$offset = 0;
			$new_users = [];
			$diff = [];
			$users_cnt = 0;
			$errors = 0;
			
			$total_join = 0;
			$total_leave = 0;
			
			echo date("H:i:s d/m/Y")." #".$group['id'].": fetch all members\n";
			while (true) {
				$result = $vk->exec("groups.getMembers", [
					"group_id"		=> $group['id'], 
					"count"			=> 1000, 
					"offset"		=> $offset
				]);
				
				if ($result->success()) {
					$users_cnt = $result->response->count;
					
					foreach ($result->response->items as $uid) {
						$new_users[$uid] = 1;
						if ($old_users && !isset($old_users[$uid])) {
							$diff[$uid] = 1;
							++$total_join;
						}
					}
					
					if ($result->response->count <= $offset + 1000)
						break;
					$offset += 1000;
					usleep(500000);
				} else {
					if (++$errors > 5) {
						echo date("H:i:s d/m/Y")." too many error! :(\n";
						return;
					}
					
					echo date("H:i:s d/m/Y")." ".$result->error()."\n";
					sleep(10);
				}
			}
			
			foreach ($old_users as $uid => $_) {
				if (!isset($new_users[$uid])) {
					$diff[$uid] = 0;
					++$total_leave;
				}
			}
			
			DB::begin();
			
			DB::delete('vk_comm_users')
				->where('cid', '=', $group['id'])
				->execute();
			
			$offset = 0;
			$chunk_size = 4000;
			while (($chunk = array_slice($new_users, $offset, $chunk_size, true))) {
				$insert = DB::insert('vk_comm_users', ['cid', 'uid']);
				foreach ($chunk as $uid => $_)
					$insert->values([$group['id'], $uid]);
				$insert->execute();
				$offset += $chunk_size;
			}
			
			if ($old_users) {
				$offset = 0;
				$chunk_size = 4000;
				while (($chunk = array_slice($diff, $offset, $chunk_size, true))) {
					$insert = DB::insert('vk_join_stat', ['cid', 'uid', 'type', 'time', 'users_cnt']);
					foreach ($chunk as $uid => $type)
						$insert->values([$group['id'], $uid, $type, $time, $users_cnt]);
					$insert->execute();
					$offset += $chunk_size;
				}
			}
			DB::commit();
			
			echo date("H:i:s d/m/Y")." #".$group['id'].": join=$total_join, leave=$total_leave\n";
		}
	}
}
