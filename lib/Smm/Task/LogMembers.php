<?php
namespace Smm\Task;

use \Z\DB;
use \Z\Config;
use \Z\Util\Url;

use \Smm\VK\Captcha;

class LogMembers extends \Z\Task {
	public function run($args) {
		if (!\Smm\Utils\Lock::lock(__CLASS__)) {
			echo "Already running.\n";
			return;
		}
		
		ini_set('memory_limit', '2G');
		
		echo date("Y-m-d H:i:s")."\n";
		
		$groups = DB::select()
			->from('vk_groups')
			->order('pos', 'ASC')
			->execute();
		
		foreach ($groups as $group)
			$this->checkMembers($group);
	}
	
	public function checkMembers($group) {
		$time = time();
		
		$cache = \Z\Cache::instance();
		
		$vk = new \Smm\VK\API(\Smm\Oauth::getAccessToken('VK'));
		
		$group_access_token = \Smm\Oauth::getGroupAccessToken($group['id']);
		if ($group_access_token) {
			$group_vk = new \Smm\VK\API($group_access_token);
			
			for ($i = 0; $i < 3; ++$i) {
				$res = $group_vk->exec("groups.getOnlineStatus", [
					'group_id'		=> $group['id']
				]);
				
				if ($res->success()) {
					$vk = $group_vk;
					break;
				} else {
					if ($res->errorCode() == 5)
						break;
					sleep(1);
				}
			}
			
			if ($vk !== $group_vk)
				echo "[error] comm auth error, fallback to user access token.\n";
		}
		
		// Получаем ранее сохранённый список юзеров
		$old_users = DB::select('uid', ['UNIX_TIMESTAMP(last_seen)', 'last_seen'])
			->from('vk_comm_users')
			->where('cid', '=', $group['id'])
			->execute()
			->asArray('uid', 'last_seen');
		
		$offset = 0;
		$new_users = [];
		$diff = [];
		$users_cnt = 0;
		$errors = 0;
		
		$total_join = 0;
		$total_leave = 0;
		
		$real_name_array = [];
		$last_seen_array = [];
		$deactivated_array = [];
		
		echo date("H:i:s d/m/Y")." #".$group['id'].": fetch all members\n";
		while (true) {
			$offset = max(0, $offset - 20);
			
			$result = $vk->exec("groups.getMembers", [
				"group_id"		=> $group['id'], 
				"count"			=> 1000, 
				"fields"		=> "last_seen", 
				"offset"		=> $offset
			]);
			
			echo "$offset...\n";
			
			if ($result->success()) {
				$users_cnt = $result->response->count;
				
				foreach ($result->response->items as $u) {
					$new_users[$u->id] = 1;
					
					if ($u->last_seen ?? false)
						$last_seen_array[$u->id] = $u->last_seen->time;
					
					if ($u->deactivated ?? false) {
						$deactivated_array[$u->id] = -1;
						if ($u->deactivated == 'banned')
							$deactivated_array[$u->id] = 1;
						elseif ($u->deactivated == 'deleted') {
							$deactivated_array[$u->id] = 2;
							
							$complete_deleted = DB::select('user_id')
								->from('vk_comm_users_deleted')
								->where('user_id', '=', $u->id)
								->execute()
								->get('user_id', 0);
							
							if ($complete_deleted)
								$deactivated_array[$u->id] = 3;
						}
					}
					
					if ($u->first_name ?? false)
						$real_name_array[$u->id] = $u->first_name."\0".$u->last_name;
					
					if ($old_users && !array_key_exists($u->id, $old_users)) {
						$diff[$u->id] = 1;
						++$total_join;
					}
				}
				
				if ($result->response->count <= $offset + 1000)
					break;
				$offset += 1000;
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
		
		echo "Clean old users...\n";
		
		DB::delete('vk_comm_users')
			->where('cid', '=', $group['id'])
			->execute();
		
		echo "Insert new users...\n";
		
		$offset = 0;
		$chunk_size = 4000;
		while (($chunk = array_slice($new_users, $offset, $chunk_size, true))) {
			$insert = DB::insert('vk_comm_users', ['cid', 'uid', 'first_name', 'last_name', 'last_seen', 'deactivated', 'last_activity', 'join_date']);
			
			// Get last users activity
			$last_activity_dates = DB::select('user_id', ['MAX(date)', 'date'])
				->from('vk_activity_stat')
				->where('owner_id', '=', -$group['id'])
				->where('user_id', 'IN', array_keys($chunk))
				->where('is_active', '=', 1)
				->group('user_id')
				->execute()
				->asArray('user_id', 'date');
			
			// Get join dates
			$join_dates = DB::select('uid', ['MAX(time)', 'time'])
				->from('vk_join_stat')
				->where('cid', '=', $group['id'])
				->where('uid', 'IN', array_keys($chunk))
				->where('type', '=', 1)
				->group('uid')
				->execute()
				->asArray('uid', 'time');
			
			foreach ($chunk as $uid => $_) {
				$last_seen = $old_users[$uid] ?? 0;
				
				if (isset($last_seen_array[$uid]))
					$last_seen = $last_seen_array[$uid];
				
				if (isset($diff[$uid]) && $diff[$uid]) {
					$join_date = $time;
				} else {
					$join_date = $join_dates[$uid] ?? 0;
				}
				
				$last_activity = $last_activity_dates[$uid] ?? NULL;
				
				$deactivated = $deactivated_array[$uid] ?? 0;
				
				list ($first_name, $last_name) = explode("\0", $real_name_array[$uid] ?? "\0");
				
				$insert->values([$group['id'], $uid, $first_name, $last_name, 
					$last_seen ? date("Y-m-d H:i:s", $last_seen) : NULL, $deactivated, $last_activity, date("Y-m-d H:i:s", $join_date)]);
			}
			
			$insert->execute();
			$offset += $chunk_size;
		}
		
		echo "Insert stat...\n";
		
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
