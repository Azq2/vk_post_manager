<?php
$req = Mysql::query("SELECT type, uid, COUNT(IF (type = 0, 1, NULL)) as cnt_leave, COUNT(IF (type = 1, 1, NULL)) as cnt_join
		FROM `vk_join_stat` WHERE cid = $gid
		GROUP BY uid ORDER BY cnt_join DESC");
$users = array();
while ($row = $req->fetch()) {
	if ($row['cnt_join'] > 1 || $row['cnt_leave'] > 1) {
		$stat[] = $row;
		$users[] = $row['uid'];
	}
}
$req->free();
$users = array_unique($users);

mk_page(array(
	'title' => 'Возвраты', 
	'content' => Tpl::render("returns.html", array(
		'stat' => &$stat, 
		'join_stat' => &$join_data, 
		'users' => get_vk_users($users)
	))
));
