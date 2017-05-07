<?php
require dirname(__FILE__)."/../inc/init.php";
$q = new Http;

$re22q = mysql_query("SELECT * FROM `vk_groups` ORDER BY pos ASC");
while ($comm = mysql_fetch_assoc($re22q)) {
	echo "==== ".$comm['name']." ====\n";
	
	$commentators = array();
	$i = 0; $cnt = 0;
	while (true) {
		for ($tt = 0; $tt < 10; ++$tt) {
			$ret = $q->vkApi("wall.get", array(
				'owner_id' => -$comm['id'], 
				'count' => 100, 
				'offset' => $i
			));
			if (!isset($ret->response)) {
				echo "err wall get!\n";
				sleep(1);
				continue;
			}
			break;
		}
		if (!isset($ret->response))
			die("ERROR: ".print_r($ret, 1));
		
		echo "OFFSET: $i / ".$ret->response->count."\n";
		foreach ($ret->response->items as $item) {
			echo "https://vk.com/wall".$item->owner_id."_".$item->id."\n";
			
			mysql_query("REPLACE INTO `vk_posts` SET
				`post_id` = ".(int) $item->id.", 
				`group_id` = ".(int) $item->owner_id.", 
				`likes` = ".(int) $item->likes->count.", 
				`comments` = ".(int) $item->comments->count.", 
				`reposts` = ".(int) $item->reposts->count.", 
				`date` = ".(int) $item->date."
			") or die("err: ".mysql_error());
			
			$j = 0;
			if ($item->comments->count) {
				echo "\tКомментариев: ".$item->comments->count."\n";
				while (true) {
					for ($tt = 0; $tt < 10; ++$tt) {
						$ret2 = $q->vkApi("wall.getComments", array(
							'owner_id' => $item->owner_id, 
							'post_id' => $item->id, 
							'count' => 100, 
							'offset' => $j
						));
						if (!isset($ret2->response)) {
							sleep(1);
							continue;
						}
						break;
					}
					if (!isset($ret2->response))
						die("ERROR: ".print_r($ret2, 1));
					
					foreach ($ret2->response->items as $c) {
						mysql_query("REPLACE INTO `vk_posts_comments` SET
							`id` = ".(int) $c->id.", 
							`post_id` = ".(int) $item->id.", 
							`group_id` = ".(int) $item->owner_id.", 
							`user_id` = ".(int) $c->from_id.", 
							`date` = ".(int) $c->date."
						") or die("err: ".mysql_error());
					}
					
					$j += 100;
					if ($j >= $ret2->response->count)
						break;
				}
			}
			
			$j = 0;
			if ($item->likes->count) {
				echo "\tЛайков: ".$item->likes->count."\n";
				while (true) {
					for ($tt = 0; $tt < 10; ++$tt) {
						$ret2 = $q->vkApi("likes.getList", array(
							'type' => 'post', 
							'owner_id' => $item->owner_id, 
							'item_id' => $item->id, 
							'count' => 100, 
							'offset' => $j
						));
						if (!isset($ret2->response)) {
							sleep(1);
							continue;
						}
						break;
					}
					if (!isset($ret2->response))
						die("ERROR: ".print_r($ret2, 1));
					
					foreach ($ret2->response->items as $c) {
						mysql_query("REPLACE INTO `vk_posts_likes` SET
							`post_id` = ".(int) $item->id.", 
							`group_id` = ".(int) $item->owner_id.", 
							`user_id` = ".(int) $c."
						") or die("err: ".mysql_error());
					}
					
					$j += 100;
					if ($j >= $ret2->response->count)
						break;
				}
			}
			
			$j = 0;
			if ($item->reposts->count) {
				echo "\tРепостов: ".$item->reposts->count."\n";
				while (true) {
					for ($tt = 0; $tt < 10; ++$tt) {
						$ret2 = $q->vkApi("wall.getReposts", array(
							'owner_id' => $item->owner_id, 
							'post_id' => $item->id, 
							'count' => 100, 
							'offset' => $j
						));
						if (!isset($ret2->response)) {
							sleep(1);
							continue;
						}
						break;
					}
					if (!isset($ret2->response))
						die("ERROR: ".print_r($ret2, 1));
					
					foreach ($ret2->response->items as $c) {
						mysql_query("REPLACE INTO `vk_posts_reposts` SET
							`post_id` = ".(int) $item->id.", 
							`group_id` = ".(int) $item->owner_id.", 
							`user_id` = ".(int) $c->from_id.", 
							`date` = ".(int) $c->date.", 
							`likes` = ".(int) @$c->likes->count.", 
							`comments` = ".(int) @$c->comments->count.", 
							`reposts` = ".(int) @$c->reposts->count."
						") or die("err: ".mysql_error());
					}
					
					$j += 100;
					if (!count($ret2->response->items) || count($ret2->response->items) < 100)
						break;
				}
			}
		}
		$i += 100;
		if ($i >= $ret->response->count)
			break;
	}
}