<?php
	require_once "init.php";
	
	if (PHP_SAPI != "cli") {
		if (!isset($_GET['password']) || $_GET['password'] != CRON_PASSWORD)
			die("Injvalid password!");
		$commit = isset($_GET['commit']) && $_GET['commit'];
	} else {
		$commit = isset($argv[1]) && strcmp($argv[1], '1') === 0;
	}
	
	foreach ($COMMS as $cid) {
		$old_users = array();
		$req = mq("SELECT * FROM `vk_comm_users` WHERE `cid` = ".$cid);
		while ($res = mysql_fetch_assoc($req))
			$old_users[$res['uid']] = 1;
		mysql_free_result($req);
		
		$offset = 0; $new_users = array();
		$diff = array(); $users_cnt = 0;
		$errors = 0;
		while (true) {
			$res = vk("groups.getMembers", array(
				"group_id" => $cid, 
				"count" => 1000, 
				"offset" => $offset
			));
			
			$users_cnt = $res->response->count;
			if ($res && isset($res->response, $res->response->items) && count($res->response->items)) {
				foreach ($res->response->items as $uid) {
					$new_users[$uid] = 1;
					if ($old_users && !isset($old_users[$uid]))
						$diff[$uid] = 1;
				}
				
				if ($res->response->count <= $offset + 1000)
					break;
				$offset += 1000;
			} else {
				echo date("H:i:s d/m/Y", time())." Зобанели?\n";
				if (++$errors > 5) {
					var_dump($$res);
					die("Странная джигурда. \n");
				}
				sleep(10);
			}
		}
		
		foreach ($old_users as $uid => $_) {
			if (!isset($new_users[$uid]))
				$diff[$uid] = 0;
		}
		
		$query = array();
		foreach ($new_users as $uid => $_)
			$query[] = '('.$cid.', '.$uid.')';
		
		$t = microtime(true);
		mq("START TRANSACTION");
		try {
			if ($commit) {
				mq("DELETE FROM `vk_comm_users` WHERE `cid` = ".$cid);
				bulk_insert("INSERT INTO `vk_comm_users` (`cid`, `uid`) VALUES", $query, 4000);
				
				$time = time();
				if ($old_users) {
					$query = array();
					foreach ($diff as $uid => $type)
						$query[] = '('.$cid.', '.$uid.', '.$type.', '.$time.', '.$users_cnt.')';
					if ($query)
						bulk_insert("INSERT INTO `vk_join_stat` (`cid`, `uid`, `type`, `time`, `users_cnt`) VALUES", $query, 4000);
				}
			} else {
				echo "TEST MODE!\n";
			}
			mq("COMMIT");
		} catch (Exception $e) {
			echo "error: ".$e."\n";
			mq("ROLLBACK");
		}
	}
	
	echo date("H:i:s d/m/Y", time()).": OK\n";
	