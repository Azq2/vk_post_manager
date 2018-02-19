<?php
$users = array();
$stat = array(
	'likes' => array(), 
	'reposts' => array(), 
	'comments' => array()
);

$filter = array_val($_GET, 'filter', "all");

$req = Mysql::query("SELECT user_id, SUM(likes) as likes, SUM(reposts) as reposts, SUM(comments) as comments, COUNT(*) as c 
	FROM `vk_posts_reposts` WHERE group_id = -$gid GROUP BY user_id");
while ($row = $req->fetch()) {
	if ($row['c'] > 0) {
		$users[] = $row['user_id'];
		$stat['reposts'][] = $row;
	}
}
$req->free();
$users = array_unique($users);

$req = Mysql::query("SELECT user_id, COUNT(*) as c FROM `vk_posts_likes` as a WHERE 
	EXISTS(select 0 from vk_join_stat as b WHERE b.uid = a.user_id AND b.cid = $gid) AND group_id = -$gid GROUP BY user_id");
while ($row = $req->fetch()) {
	if ($row['c'] > 0) {
		$users[] = $row['user_id'];
		$stat['likes'][] = $row;
	}
}
$req->free();
$users = array_unique($users);

$req = Mysql::query("SELECT user_id, COUNT(*) as c
	FROM `vk_posts_comments` WHERE group_id = -$gid GROUP BY user_id");
while ($row = $req->fetch()) {
	if ($row['c'] > 0) {
		$users[] = $row['user_id'];
		$stat['comments'][] = $row;
	}
}
$req->free();
$users = array_unique($users);

$join_data = array();
if ($users) {
	$users = array_unique($users);
	$req = Mysql::query("SELECT * FROM `vk_join_stat` WHERE uid IN(".implode(", ", $users).") AND cid = $gid GROUP BY uid ORDER BY id DESC");
	while ($row = $req->fetch())
		$join_data[$row['uid']] = $row;
}

unset($s);
foreach ($stat as &$s) {
	foreach ($s as $i => $row) {
		if ($filter == 'leaved' && (!isset($join_data[$row['user_id']]) || $join_data[$row['user_id']]['type']))
			unset($s[$i]);
	}
}
unset($s);

unset($s);
foreach ($stat as &$s) {
	usort($s, function (&$a, &$b) {
		return $b['c'] - $a['c'];
	});
	$s = array_slice($s, 0, 200);
}
unset($s);

$users_data = get_vk_users($users);
$users_widgets = array();
foreach ($users_data as $user)
	$users_widgets[$user_id] = vk_user_widget($user);

mk_page(array(
	'title' => 'Пользователи', 
	'content' => Tpl::render("users_top_list.html", array(
		'tabs' => switch_tabs([
			'param' => 'filter', 
			'tabs' => [
				'all'		=> 'Все', 
				'leaved'	=> 'Покинувшие', 
			], 
			'url' => '?', 
			'active' => $filter
		]), 
		'filter' => $filter, 
		'stat' => &$stat, 
		'join_stat' => &$join_data, 
		'users' => $users_data, 
		'users_widgets' => $users_widgets
	))
));
