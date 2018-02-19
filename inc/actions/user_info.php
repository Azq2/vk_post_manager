<?php
$user_id = (int) array_val($_GET, 'id', 0);

// Репосты
$req = Mysql::query("SELECT COUNT(*) FROM vk_posts_reposts WHERE user_id = $user_id AND group_id = -$gid");
$reposts = $req->result();

// Репосты 30 дней
$req = Mysql::query("SELECT COUNT(*) FROM vk_posts_reposts WHERE user_id = $user_id AND group_id = -$gid
	AND date BETWEEN CURDATE() - INTERVAL 30 DAY AND CURDATE()");
$reposts30 = $req->result();

// Лайки
$req = Mysql::query("SELECT COUNT(*) FROM vk_posts_likes WHERE user_id = $user_id AND group_id = -$gid");
$likes = $req->result();

// Камменты
$req = Mysql::query("SELECT COUNT(*) FROM vk_posts_comments WHERE user_id = $user_id AND group_id = -$gid");
$comments = $req->result();

// Камменты 30 дней
$req = Mysql::query("SELECT COUNT(*) FROM vk_posts_comments WHERE user_id = $user_id AND group_id = -$gid
	AND date BETWEEN CURDATE() - INTERVAL 30 DAY AND CURDATE()");
$comments30 = $req->result();

$joins = array();
$req = Mysql::query("SELECT * FROM `vk_join_stat` WHERE cid = $gid AND uid = $user_id GROUP BY time ASC");
$last_time = 0;
$time_in_comm = 0;
while ($res = $req->fetch()) {
	if ($res['type'] == 0) {
		$time_in_comm += $res['time'] - $last_time;
		$joins[count($joins) - 1]['time_in_comm'] = 0;
		$res['time_in_comm'] = $res['time'] - $last_time;
		$last_time = 0;
	} else if ($res['type'] == 1) {
		$last_time = $res['time'];
		$res['time_in_comm'] = time() - $res['time'];
	}
	$joins[] = $res;
}
if ($last_time)
	$time_in_comm += time() - $last_time;

// array(6) { ["id"]=> string(1) "1" ["cid"]=> string(8) "94594114" ["uid"]=> string(8) "53344747" ["type"]=> string(1) "1" ["time"]=> string(10) "1434800396" ["users_cnt"]=> string(3) "764" }

$user = get_vk_users(array($user_id))[$user_id];			
mk_page(array(
	'title' => 'Информация о #'.$user_id, 
	'content' => Tpl::render("user_info.html", array(
		'reposts' => $reposts, 
		'reposts30' => $reposts30, 
		'likes' => $likes, 
		'comments' => $comments, 
		'comments30' => $comments30, 
		'time_in_comm' => count_time($time_in_comm), 
		'joins' => $joins, 
		'user' => vk_user_widget($user)
	))
));
