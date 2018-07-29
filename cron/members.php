<?php
if (php_sapi_name() != "cli")
	die("Not a CLI :)\n");

require __DIR__."/../inc/init.php";

$lock_fp = fopen(H."../tmp/members", "w+");
if (!$lock_fp || !flock($lock_fp, LOCK_EX | LOCK_NB))
	die("Lock!\n");

$q = new Http;
$q->vkSetUser('VK');

	Mysql::debug(true);
	
$commit = true;
$comms_req = Mysql::query("SELECT * FROM `vk_groups`");
while ($comm = $comms_req->fetch()) {
	$time = time();
	
	// Получаем ранее сохранённый список юзеров
	$old_users = [];
	$old_users_req = Mysql::query("SELECT * FROM `vk_comm_users` WHERE `cid` = ?", $comm['id']);
	while ($u = $old_users_req->fetch())
		$old_users[$u['uid']] = 1;
	
	$offset = 0;
	$new_users = [];
	$diff = [];
	$users_cnt = 0;
	$errors = 0;
	
	$total_join = 0;
	$total_leave = 0;
	
	echo date("H:i:s d/m/Y")." #".$comm['id'].": fetch all members\n";
	while (true) {
		$res = $q->vkApi("groups.getMembers", array(
			"group_id" => $comm['id'], 
			"count" => 1000, 
			"offset" => $offset
		));
		
		if ($res && isset($res->response, $res->response->items) && count($res->response->items)) {
			$users_cnt = $res->response->count;
			
			foreach ($res->response->items as $uid) {
				$new_users[$uid] = 1;
				if ($old_users && !isset($old_users[$uid])) {
					$diff[$uid] = 1;
					++$total_join;
				}
			}
			
			if ($res->response->count <= $offset + 1000)
				break;
			$offset += 1000;
			usleep(500000);
		} else {
			if (++$errors > 5) {
				echo date("H:i:s d/m/Y")." too many error! :(\n";
				var_dump($res);
				exit;
			}
			
			if (isset($res->error, $res->error->error_msg)) {
				echo date("H:i:s d/m/Y")." ".$res->error->error_msg."\n";
				sleep(10);
			} else {
				echo date("H:i:s d/m/Y")." invalid response\n";
				var_dump($res);
			}
		}
	}
	
	foreach ($old_users as $uid => $_) {
		if (!isset($new_users[$uid])) {
			$diff[$uid] = 0;
			++$total_leave;
		}
	}
	
	Mysql::begin();
	try {
		Mysql::query("DELETE FROM `vk_comm_users` WHERE `cid` = ?", $comm['id']);
		
		$offset = 0;
		$chunk_size = 4000;
		while (($chunk = array_slice($new_users, $offset, $chunk_size, true))) {
			$query = [];
			foreach ($chunk as $uid => $_)
				$query[] = '('.$comm['id'].', '.$uid.')';
			Mysql::query("INSERT INTO `vk_comm_users` (`cid`, `uid`) VALUES ".implode(", ", $query));
			$offset += $chunk_size;
		}
		
		if ($old_users) {
			$offset = 0;
			$chunk_size = 4000;
			while (($chunk = array_slice($diff, $offset, $chunk_size, true))) {
				$query = [];
				foreach ($chunk as $uid => $type)
					$query[] = '('.$comm['id'].', '.$uid.', '.$type.', '.$time.', '.$users_cnt.')';
				Mysql::query("INSERT INTO `vk_join_stat` (`cid`, `uid`, `type`, `time`, `users_cnt`) VALUES ".implode(", ", $query));
				$offset += $chunk_size;
			}
		}
		
		Mysql::commit();
	} catch (Exception $e) {
		echo date("H:i:s d/m/Y")." exception:\n$e\n";
		Mysql::rollback();
	}
	
	echo date("H:i:s d/m/Y")." #".$comm['id'].": join=$total_join, leave=$total_leave\n";
}

flock($lock_fp, LOCK_UN);
fclose($lock_fp);
