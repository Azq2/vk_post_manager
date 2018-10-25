<?php
error_reporting(E_ALL);

require dirname(__FILE__)."/../inc/init.php";

$type2id = [
	"VK"			=> 0, 
	"OK"			=> 1, 
	"INSTAGRAM"		=> 2, 
];


$values = [];
$last_data_id = 0;

while (true) {
	echo "last_data_id=$last_data_id\n";
	
	$cnt = 0;
	
	$sources = [];
	
	$req = Mysql::query("SELECT * FROM `vk_grabber_data_index` WHERE data_id > ? ORDER BY data_id ASC LIMIT 10000", $last_data_id);
	while ($row = $req->fetchAssoc()) {
		if (!Mysql::query("SELECT id FROM vk_grabber_data WHERE id = ?", $row['id'])->result()) {
			echo $row['remote_id']."\n";
			
			$sources[$row['source_id']] = 1;
		}
		
		$last_data_id = $row['data_id'];
		++$cnt;
	}
	
	if (!$cnt) {
		echo "done!\n";
		echo implode(",", array_keys($sources))."\n";
		exit;
	}
}
exit;

/*
$req = Mysql::query("SELECT * FROM `vk_grabber_blacklist2`");
while ($row = $req->fetchAssoc()) {
	$source_type = $type2id[$row['source_type']];
	
	$source = Mysql::query("SELECT * FROM vk_grabber_sources WHERE source_type = ? AND source_id = ?", $source_type, $row['source_id'])
		->fetchAssoc();
	
	$post = Mysql::query("SELECT * FROM vk_grabber_data_index WHERE source_type = ? AND remote_id = ?", $source_type, $row['remote_id'])
		->fetchAssoc();
	
	if ($post) {
		Mysql::query("
			INSERT IGNORE INTO vk_grabber_blacklist SET
			source_type = ?, 
			remote_id = ?, 
			group_id = ?, 
			time = ?
		", $source_type, $row['remote_id'], $row['group_id'], time() - 3600*24);
	}
}
exit;
*/

$values = [];
$last_data_id = 0;

while (true) {
	echo "last_data_id=$last_data_id\n";
	
	$req = Mysql::query("SELECT * FROM `vk_grabber_data_index22` WHERE data_id > ? ORDER BY data_id ASC LIMIT 10000", $last_data_id);
	while ($row = $req->fetchAssoc()) {
		$source_type = $type2id[$row['source_type']];
		
		if ($source_type == 2)
			continue;
		
		$source = Mysql::query("SELECT * FROM vk_grabber_sources WHERE source_type = ? AND source_id = ?", $source_type, $row['source_id'])
			->fetchAssoc();
		
		$post_type = 0;
		if ($row['images_cnt'] > 0 && $row['gifs_cnt'] > 0)
			$post_type = 3;
		elseif ($row['images_cnt'] > 0)
			$post_type = 2;
		elseif ($row['gifs_cnt'] > 0)
			$post_type = 1;
		
		$values[] = "(".implode(", ", [
			Mysql::value($source['id']), 
			Mysql::value($source_type), 
			Mysql::value($row['remote_id']), 
			Mysql::value($row['time']), 
			Mysql::value($row['likes']), 
			Mysql::value($row['reposts']), 
			Mysql::value($row['comments']), 
			Mysql::value($row['images_cnt']), 
			Mysql::value($row['gifs_cnt']), 
			Mysql::value($row['data_id']), 
			$post_type
		]).")";
		
		$last_data_id = $row['data_id'];
	}
	$req->free();
	
	if ($values) {
		Mysql::query("
			INSERT IGNORE INTO vk_grabber_data_index (
				source_id, 
				source_type, 
				remote_id, 
				time, 
				likes, 
				reposts, 
				comments, 
				images_cnt, 
				gifs_cnt, 
				data_id, 
				post_type
			)
			VALUES ".implode(",", $values)."
		");
		$values = [];
	} else {
		echo "done\n";
		exit;
	}
}

