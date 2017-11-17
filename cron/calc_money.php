<?php
require dirname(__FILE__)."/../inc/init.php";
$q = new Http;

$PER_TOPIC = 1.4;

echo date("Y-m-d H:i:s")."\n";

$req = Mysql::query("SELECT * FROM `vk_groups` ORDER BY pos ASC");
while ($comm = $req->fetch()) {
	Mysql::query("INSERT IGNORE INTO `vk_smm_money` SET `last_date` = ?, `group_id` = ?, `money` = ?", 
		mktime(0, 0, 0, date("n"), 1, date("Y")), $comm['id'], 0);
	
	$smm_money = Mysql::query("SELECT * FROM `vk_smm_money` WHERE `group_id` = ?", $comm['id'])
		->fetchObject();
	
	$i = 0;
	$stop = false;
	$total_topics = 0;
	while (true) {
		for ($tt = 0; $tt < 10; ++$tt) {
			$ret = $q->vkApi("wall.get", array(
				'owner_id'	=> -$comm['id'], 
				'count'		=> 100, 
				'offset'	=> $i
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
		
		foreach ($ret->response->items as $item) {
			if ($item->date < $smm_money->last_date) {
				$stop = true;
			} elseif (!$item->marked_as_ads) {
				++$total_topics;
			}
		}
		
		$i += 100;
		if ($i >= $ret->response->count || $stop)
			break;
	}
	
	Mysql::query("UPDATE `vk_smm_money` SET `money` = ? WHERE `group_id` = ?", ($total_topics * $PER_TOPIC), $comm['id']);
	
	echo $comm['name']." ($total_topics) => ".($total_topics * $PER_TOPIC)." р.\n";
}
