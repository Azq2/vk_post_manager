<?php
if (php_sapi_name() != "cli")
	die("Not a CLI :)\n");

require __DIR__."/../inc/init.php";
require __DIR__."/../inc/vk_posts.php";

$q = new Http;
$q->vkSetUser('VK');

$req = Mysql::query("SELECT * FROM `vk_groups` as `g` WHERE EXISTS (SELECT 0 FROM `vk_posts_queue` as `s` WHERE `s`.group_id = `g`.id LIMIT 1)");
while ($comm = $req->fetch()) {
	echo "=========== ".$comm['id']." ===========\n";
	$gid = $comm['id'];
	$comments = get_comments($q, $comm);
	
	echo "postponed_cnt=".$comments->postponed_cnt."\n";
	
	foreach ($comments->postponed as $item) {
		$queue = Mysql::query("SELECT * FROM `vk_posts_queue` WHERE `group_id` = $gid AND id = ".$item->id)
			->fetchAssoc();
		if ($queue) {
			if ($queue['fake_date'] != $item->orig_date) {
				echo "https://vk.com/wall".$item->owner_id."_".$item->id." - ".date("Y-m-d H:i:s", $queue['fake_date'])." => ".date("Y-m-d H:i:s", $item->orig_date)."\n";
				Mysql::query("UPDATE `vk_posts_queue` SET `fake_date` = ".$item->orig_date." WHERE `group_id` = $gid AND `id` = ".$item->id);
			} else {
				echo "https://vk.com/wall".$item->owner_id."_".$item->id." - OK\n";
			}
		} else {
			echo "https://vk.com/wall".$item->owner_id."_".$item->id." - Not found\n";
		}
	}
}
