<?php
require "../inc/init.php";

$req2 = mysql_query("SELECT * FROM `vk_groups`");
while ($comm = mysql_fetch_assoc($req2)) {
	$gid = -$comm['id'];
	
	$date = (new DateTime())
		->setTimestamp(mysql_result(mysql_query("SELECT MIN(date) FROM vk_posts WHERE group_id = $gid"), 0));

	while ($date->getTimestamp() < time()) {
		$start = mktime(0, 0, 0, $date->format("n"), $date->format("j"), $date->format("Y"));
		$end = mktime(23, 59, 59, $date->format("n"), $date->format("j"), $date->format("Y"));
		
		// Посты
		$posts = array();
		$req = mysql_query("SELECT * FROM vk_posts WHERE date >= $start AND date <= $end AND group_id = $gid");
		while ($row = mysql_fetch_assoc($req))
			$posts[] = $row['post_id'];
		
		// Репосты
		$req2 = mysql_query("SELECT COUNT(*) FROM vk_posts_reposts WHERE date >= $start AND date <= $end AND group_id = $gid");
		$reposts = mysql_result($req2, 0);
		
		// Камменты
		$req2 = mysql_query("SELECT COUNT(*) FROM vk_posts_comments WHERE date >= $start AND date <= $end AND group_id = $gid");
		$comments = mysql_result($req2, 0);
		
		
		echo $date->format("Y-m-d").": ".count($posts)." | $reposts | $comments\n";
		
		$date->add(date_interval_create_from_date_string('1 day'));
	}
}