<?php
$stat = array();

$date = (new DateTime())
	->setTimestamp(Mysql::query("SELECT MIN(date) FROM vk_posts WHERE group_id = -$gid")->result());
$date = (new DateTime('2016-01-01'));
while ($date->getTimestamp() && $date->getTimestamp() < time()) {
	$start = mktime(0, 0, 0, $date->format("n"), $date->format("j"), $date->format("Y"));
	$end = mktime(23, 59, 59, $date->format("n"), $date->format("j"), $date->format("Y"));
	
	// Посты
	$users = array();
	$posts = array();
	$users_posts = array();
	
	$req = Mysql::query("SELECT * FROM vk_posts WHERE date >= $start AND date <= $end AND group_id = -$gid");
	while ($row = $req->fetch())
		$posts[] = $row['post_id'];
	
	// Репосты
	$reposts = 0;
	$req2 = Mysql::query("SELECT DISTINCT user_id, post_id FROM vk_posts_reposts WHERE date >= $start AND date <= $end AND group_id = -$gid");
	while ($row = $req2->fetch()) {
		$users[] = $row['user_id'];
		$users_posts[] = $row['post_id'];
		++$reposts;
	}
	
	// Камменты
	$req2 = Mysql::query("SELECT DISTINCT user_id, post_id FROM vk_posts_comments WHERE date >= $start AND date <= $end AND group_id = -$gid");
	$comments = 0;
	while ($row = $req2->fetch()) {
		$users[] = $row['user_id'];
		$users_posts[] = $row['post_id'];
		++$comments;
	}
	
	// ЛАйки
	$likes = 0;
	if ($posts) {
		$req2 = Mysql::query("SELECT DISTINCT user_id, post_id FROM vk_posts_likes WHERE post_id IN (".implode(", ", $posts).") AND group_id = -$gid");
		while ($row = $req2->fetch()) {
			$users[] = $row['user_id'];
			$users_posts[] = $row['post_id'];
			++$likes;
		}
	}
	
	$users = array_unique($users);
	$users_posts = array_unique($users_posts);

	$c = count($posts);
	$stat[] = array(
		'date' => $date->format("Y-m-d"), 
		'reposts' => $reposts, 
		'likes' => $likes, 
		'comments' => $comments, 
		'active_users' => count($users), 
		'activity' => round($users_posts ? count($users) / count($users_posts) : 0), 
		'posts' => $c
	);
	
	$date->add(date_interval_create_from_date_string('1 day'));
}

mk_page(array(
	'title' => 'Активность', 
	'content' => Tpl::render("activity.html", array(
		'stat' => &$stat
	))
));
