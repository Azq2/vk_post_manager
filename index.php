<?php
require "inc/init.php";

// Авторизация
if (!isset($_COOKIE['password']) || $_COOKIE['password'] !== WEBUI_PASSWORD) {
	if (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_PW'] !== WEBUI_PASSWORD) {
		header('WWW-Authenticate: Basic realm="Home porn archive"');
		header('HTTP/1.0 401 Unauthorized');
		echo 'AYKA LOH.';
		exit;
	}
}
setcookie('password', WEBUI_PASSWORD, time() + 365 * 24 * 3600 * 2);

$q = new Http;
$action = isset($_REQUEST['a']) ? preg_replace("/[^a-z0-9_-]+/i", "", $_REQUEST['a']) : 'index';

$curl = new Url($_SERVER['REQUEST_URI']);

$comms = [];
$req = mysql_query("SELECT * FROM `vk_groups` ORDER BY pos ASC");
while ($res = mysql_fetch_assoc($req))
	$comms[$res['id']] = $res;

$gid = (int) array_val($_REQUEST, 'gid', reset($comms)['id']);
if (!isset($comms[$gid])) {
	header("Location: ?");
	exit;
}
$comm = $comms[$gid];

switch ($action) {
	case "oauth":
		$type = array_val($_GET, 'type', '');
		$access_token = array_val($_GET, 'access_token', '');
		$refresh_token = array_val($_GET, 'refresh_token', '');
		$expires = (int) array_val($_GET, 'expires', '');
		
		if ($type && $access_token) {
			if (!preg_match("/^[\w\d]+$/i", $type))
				die;
			
			mysql_query("
				INSERT INTO `vk_oauth` SET
					`type`			= '".mysql_real_escape_string($type)."', 
					`access_token`	= '".mysql_real_escape_string($access_token)."', 
					`refresh_token`	= '".mysql_real_escape_string($refresh_token)."', 
					`expires`		= $expires
				ON DUPLICATE KEY UPDATE
					`access_token`	= VALUES(`access_token`), 
					`refresh_token`	= VALUES(`refresh_token`), 
					`expires`		= VALUES(`expires`)
			") or die("MYSQL ERROR: ".mysql_error());
			header("Location: ?a=oauth&ok=1");
		} else {
			$redirect_url = str_replace("/index.php", "/", $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].$_SERVER['DOCUMENT_URI']."auth.php");
			
			if (isset($_GET['code']) && isset($_GET['state'])) {
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
				
				if ($_GET['state'] == 'OK') {
					curl_setopt($ch, CURLOPT_URL, "https://api.ok.ru/oauth/token.do");
					curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
						'client_id'		=> OK_APP_ID, 
						'client_secret'	=> OK_APP_SECRET, 
						'redirect_uri'	=> $redirect_url, 
						'code'			=> $_GET['code'], 
						'grant_type'	=> 'authorization_code'
					]));
				} else if ($_GET['state'] == 'VK') {
					curl_setopt($ch, CURLOPT_URL, "https://oauth.vk.com/access_token");
					curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
						'client_id'		=> VK_APP_ID, 
						'client_secret'	=> VK_APP_SECRET, 
						'redirect_uri'	=> 'https://oauth.vk.com/blank.html', 
						'code'			=> $_GET['code']
					]));
				}
				
				$raw = curl_exec($ch);
				$res = json_decode($raw);
				
				if ($res && isset($res->access_token)) {
					header("Location: ?".http_build_query([
						'a'				=> 'oauth', 
						'type'			=> $_GET['state'], 
						'access_token'	=> $res->access_token, 
						'refresh_token'	=> isset($res->refresh_token) ? $res->refresh_token : '', 
						'expires'		=> isset($res->expires_in) && $res->expires_in ? time() + $res->expires_in : 0
					]));
				} else {
					echo $raw;
					exit;
				}
			} else {
				mk_page(array(
					'title' => 'Активность', 
					'content' => Tpl::render("oauth.html", [
						'ok' => isset($_GET['ok']), 
						'vk_oauth' => 'https://oauth.vk.com/authorize?'.http_build_query([
							'client_id'		=> VK_APP_ID, 
							'redirect_uri'	=> 'https://oauth.vk.com/blank.html', 
							'display'		=> 'mobile', 
							'scope'			=> 'offline wall groups photos', 
							'response_type'	=> 'code'
						]), 
						'ok_oauth' => 'https://connect.ok.ru/oauth/authorize?'.http_build_query([
							'client_id'		=> OK_APP_ID, 
							'response_type'	=> 'code', 
							'redirect_uri'	=> $redirect_url, 
							'layout'		=> 'm', 
							'scope'			=> 'VALUABLE_ACCESS;LONG_ACCESS_TOKEN', 
							'response_type'	=> 'code', 
							'state'			=> 'OK'
						])
					])
				));
			}
		}
	break;
	
	case "grabber":
		$sub_action = array_val($_GET, 'sa', '');
		$sort = array_val($_GET, 'sort', 'DESC');
		$mode = array_val($_GET, 'mode', 'external');
		$content_filter = array_val($_GET, 'content', 'pics');
		
		if ($mode != "internal")
			$mode = "external";
		
		if ($sort != "ASC" && $sort != "RAND")
			$sort = "DESC";
		
		if ($content_filter != "all")
			$content_filter = "pics";
		
		$sources = [];
		$req = mysql_query("SELECT * FROM `vk_grabber_sources` WHERE group_id = $gid ORDER BY id DESC");
		while ($res = mysql_fetch_assoc($req))
			$sources[$res['id']] = $res;
		
		$view = [
			'sa'			=> $sub_action, 
			'form_action'	=> Url::mk()->set('sa', 'add')->url(), 
			'form_error'	=> false, 
			'form_url'		=> '', 
			'gid'			=> $gid, 
			'mode_tabs'		=> switch_tabs([
				'param' => 'mode', 
				'tabs' => [
					'external'		=> 'Граббер', 
					'internal'		=> 'Из своего сообщества', 
				], 
				'url' => Url::mk(), 
				'active' => $mode
			]), 
			'content_tabs'		=> switch_tabs([
				'param' => 'content', 
				'tabs' => [
					'all'			=> 'Любые', 
					'pics'			=> 'Картинки', 
					'only_gif'		=> 'Только GIF', 
					'without_gif'	=> 'Без GIF', 
				], 
				'url' => Url::mk(), 
				'active' => $content_filter
			]), 
			'sort_tabs'		=> switch_tabs([
				'param' => 'sort', 
				'tabs' => [
					'DESC'		=> 'Начало', 
					'ASC'		=> 'Конец', 
					'RAND'		=> 'Рандо&#x301;мно', 
					'LIKES'		=> 'Топ Лайки', 
					'REPOSTS'	=> 'Топ Репосты', 
					'COMMENTS'	=> 'Топ Комы', 
				], 
				'url' => Url::mk(), 
				'active' => $sort
			])
		];
		
		switch ($sub_action) {
			case "blacklist":
				$blacklist_id = isset($_POST['blacklist']) ? $_POST['blacklist'] : '';
				if ($blacklist_id) {
					mysql_query("INSERT IGNORE INTO `vk_grabber_blacklist` SET group_id = $gid, 
						object = '".mysql_real_escape_string($blacklist_id)."'");
				}
				mk_ajax(['success' => true]);
				exit;
			break;
			
			case "queue":
				$out = [];
				$text = isset($_POST['text']) ? $_POST['text'] : '';
				$blacklist_id = isset($_POST['blacklist']) ? $_POST['blacklist'] : '';
				if (isset($_POST['images']) && is_array($_POST['images'])) {
					foreach ($_POST['images'] as $img) {
						$data = file_get_contents($img);
						if ($data) {
							$file = "tmp/pic_".md5($img).".png";
							$fp = fopen($file, "w");
							if ($fp) {
								flock($fp, LOCK_EX);
								fwrite($fp, $data);
								flock($fp, LOCK_UN);
								fclose($fp);
								
								$images[] = [
									'path' => $file, 
									'caption' => ''
								];
							} else {
								$out['error'] = 'fopen('.$file.')';
								break;
							}
						} else {
							$out['error'] = 'file_get_contents('.$img.')';
							break;
						}
					}
				}
				
				save_pic_post($out, $images, $text);
				
				if (!isset($out['error']) && $blacklist_id) {
					mysql_query("INSERT IGNORE INTO `vk_grabber_blacklist` SET group_id = $gid, 
						object = '".mysql_real_escape_string($blacklist_id)."'");
				}
				
				foreach ($images as $img)
					@unlink($img['path']);
				
				mk_ajax($out);
				exit;
			break;
			
			case "on":
			case "off":
				$source_id = array_val($_GET, 'id', '');
				$source_type = array_val($_GET, 'type', '');
				
				mysql_query("UPDATE `vk_grabber_sources` SET `enabled` = ".($sub_action == 'on' ? 1 : 0)."
					WHERE
						id = '".mysql_real_escape_string($source_id)."' AND
						type = '".mysql_real_escape_string($source_type)."' AND 
						group_id = $gid");
				
				header("Location: ".Url::mk('?')->set('a', 'grabber')->set('gid', $gid)->url());
				exit;
			break;
			
			case "delete":
				$source_id = array_val($_GET, 'id', '');
				$source_type = array_val($_GET, 'type', '');
				
				mysql_query("DELETE FROM `vk_grabber_sources`
					WHERE
						id = '".mysql_real_escape_string($source_id)."' AND
						type = '".mysql_real_escape_string($source_type)."' AND 
						group_id = $gid");
				
				header("Location: ".Url::mk('?')->set('a', 'grabber')->set('gid', $gid)->url());
				exit;
			break;
			
			case "add":
				$url = trim(array_val($_POST, 'url', ''));
				if (substr($url, 0, 4) != "http")
					$url = "https://$url";
				
				$parts = parse_url($url);
				
				$view['form_url'] = $url;
				
				$source_id = false;
				$source_type = false;
				
				if ($url == '') {
					$view['form_error'] = 'Сейчас бы тыкать на кнопки ничего не введя.';
				} elseif (isset($parts['host']) && isset($parts['path'])) {
					$type = '';
					if (preg_match("/ok.ru|odnoklassniki.ru/i", $parts['host'])) {
						$data = file_get_contents("https://m.ok.ru".$parts['path']);
						if (preg_match("/groupId=(\d+)/i", $data, $m)) {
							$source_id = $m[1];
							if (preg_match('/(group_name|itemprop="name")[^>]*>(.*?)</sim', $data, $m)) {
								$source_type = 'OK';
								$source_name = htmlspecialchars_decode($m[2]);
							} else {
								$view['form_error'] = 'Невозможно спарсить имя группы ОК. Нужно срочно пнуть ЖумарИна! (при этом groupId спарислся)';
							}
						} else {
							$view['form_error'] = 'Невозможно спарсить OK groupId. Может, этой группы вообще нет?';
						}
					} elseif (preg_match("/vk.com|vkontakte.ru|vk.me/i", $parts['host'])) {
						$group_id = substr($parts['path'], 1);
						if (preg_match("/^(public|club)(\d+)$/i", $group_id, $m))
							$group_id = $m[2];
						
						$res = $q->vkApi("groups.getById", array(
							'group_ids'	=> $group_id
						));
						if (isset($res->response) && $res->response) {
							$source_id = $res->response[0]->id;
							$source_type = 'VK';
							$source_name = $res->response[0]->name;
						} else if (isset($res->error)) {
							$view['form_error'] = $res->error->error_msg;
						} else {
							$view['form_error'] = 'VK API вернул странную дичь =\\';
						}
					} else {
						$view['form_error'] = '<b>'.$parts['host'].'</b> - чё за сосайт? Не знаю такой!';
					}
				} else {
					$view['form_error'] = 'Чё за дичь!? =\ Не очень похоже на URL.';
				}
				
				if ($source_type) {
					mysql_query("INSERT INTO `vk_grabber_sources`
						SET
							id = '".mysql_real_escape_string($source_id)."', 
							name = '".mysql_real_escape_string($source_name)."', 
							type = '".mysql_real_escape_string($source_type)."', 
							group_id = $gid
						ON DUPLICATE KEY UPDATE
							name = VALUES(name)");
					header("Location: ".Url::mk('?')->set('a', 'grabber')->set('gid', $gid)->url());
					exit;
				}
			break;
		}
		
		$view['mode'] = $mode;
		$view['sort'] = $sort;
		$view['content_filter'] = $content_filter;
		$view['sources'] = [];
		$view['sources_ids'] = [];
		
		foreach ($sources as $s) {
			if ($s['type'] == 'OK') {
				$url = 'https://ok.ru/group/'.$s['id'];
			} else if ($s['type'] == 'VK') {
				$url = 'https://vk.com/public'.$s['id'];
			}
			
			$view['sources'][] = [
				'enabled'		=> $s['enabled'], 
				'name'			=> $s['name'], 
				'type'			=> $s['type'], 
				'url'			=> $url, 
				'on_url'		=> Url::mk()
					->set('sa', 'on')
					->set('id', $s['id'])
					->set('type', $s['type']), 
				'off_url'		=> Url::mk()
					->set('sa', 'off')
					->set('id', $s['id'])
					->set('type', $s['type']), 
				'delete_url'	=> Url::mk()
					->set('sa', 'delete')
					->set('id', $s['id'])
					->set('type', $s['type'])
					->url(), 
			];
			
			if ($s['enabled'])
				$view['sources_ids'][] = [$s['type'], $s['id']];
		}
		
		if ($mode == 'internal')
			$view['sources_ids'] = [['VK', $gid]];
		
		$view['blacklist'] = [];
		
		$req = mysql_query("SELECT * FROM `vk_grabber_blacklist` WHERE group_id = $gid");
		while ($res = mysql_fetch_assoc($req))
			$view['blacklist'][$res['object']] = 1;
		
		mk_page(array(
			'title' => 'Граббер корованов 2000', 
			'content' => Tpl::render("grabber.html", $view)
		));
	break;
	
	case "returns":
		$req = mysql_query("SELECT type, uid, COUNT(IF (type = 0, 1, NULL)) as cnt_leave, COUNT(IF (type = 1, 1, NULL)) as cnt_join
				FROM `vk_join_stat` WHERE cid = $gid
				GROUP BY uid ORDER BY cnt_join DESC");
		$users = array();
		while ($row = mysql_fetch_assoc($req)) {
			if ($row['cnt_join'] > 1 || $row['cnt_leave'] > 1) {
				$stat[] = $row;
				$users[] = $row['uid'];
			}
		}
		mysql_free_result($req);
		$users = array_unique($users);
		
		mk_page(array(
			'title' => 'Пользователи', 
			'content' => Tpl::render("returns.html", array(
				'stat' => &$stat, 
				'join_stat' => &$join_data, 
				'users' => get_vk_users($users)
			))
		));
	break;
	
	case "activity":
		$stat = array();
		
		$date = (new DateTime())
			->setTimestamp(mysql_result(mysql_query("SELECT MIN(date) FROM vk_posts WHERE group_id = -$gid"), 0));
		$date = (new DateTime('2016-01-01'));
		while ($date->getTimestamp() && $date->getTimestamp() < time()) {
			$start = mktime(0, 0, 0, $date->format("n"), $date->format("j"), $date->format("Y"));
			$end = mktime(23, 59, 59, $date->format("n"), $date->format("j"), $date->format("Y"));
			
			// Посты
			$users = array();
			$posts = array();
			$users_posts = array();
			
			$req = mysql_query("SELECT * FROM vk_posts WHERE date >= $start AND date <= $end AND group_id = -$gid");
			while ($row = mysql_fetch_assoc($req))
				$posts[] = $row['post_id'];
			
			// Репосты
			$reposts = 0;
			$req2 = mysql_query("SELECT DISTINCT user_id, post_id FROM vk_posts_reposts WHERE date >= $start AND date <= $end AND group_id = -$gid");
			while ($row = mysql_fetch_assoc($req2)) {
				$users[] = $row['user_id'];
				$users_posts[] = $row['post_id'];
				++$reposts;
			}
			
			// Камменты
			$req2 = mysql_query("SELECT DISTINCT user_id, post_id FROM vk_posts_comments WHERE date >= $start AND date <= $end AND group_id = -$gid");
			$comments = 0;
			while ($row = mysql_fetch_assoc($req2)) {
				$users[] = $row['user_id'];
				$users_posts[] = $row['post_id'];
				++$comments;
			}
			
			// ЛАйки
			$likes = 0;
			if ($posts) {
				$req2 = mysql_query("SELECT DISTINCT user_id, post_id FROM vk_posts_likes WHERE post_id IN (".implode(", ", $posts).") AND group_id = -$gid");
				while ($row = mysql_fetch_assoc($req2)) {
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
	break;
	
	case "user_info":
		$user_id = (int) array_val($_GET, 'id', 0);
		
		// Репосты
		$req = mysql_query("SELECT COUNT(*) FROM vk_posts_reposts WHERE user_id = $user_id AND group_id = -$gid");
		$reposts = mysql_result($req, 0);
		
		// Репосты 30 дней
		$req = mysql_query("SELECT COUNT(*) FROM vk_posts_reposts WHERE user_id = $user_id AND group_id = -$gid
			AND date BETWEEN CURDATE() - INTERVAL 30 DAY AND CURDATE()");
		$reposts30 = mysql_result($req, 0);
		
		// Лайки
		$req = mysql_query("SELECT COUNT(*) FROM vk_posts_likes WHERE user_id = $user_id AND group_id = -$gid");
		$likes = mysql_result($req, 0);
		
		// Камменты
		$req = mysql_query("SELECT COUNT(*) FROM vk_posts_comments WHERE user_id = $user_id AND group_id = -$gid");
		$comments = mysql_result($req, 0);
		
		// Камменты 30 дней
		$req = mysql_query("SELECT COUNT(*) FROM vk_posts_comments WHERE user_id = $user_id AND group_id = -$gid
			AND date BETWEEN CURDATE() - INTERVAL 30 DAY AND CURDATE()");
		$comments30 = mysql_result($req, 0);
		
		$joins = array();
		$req = mysql_query("SELECT * FROM `vk_join_stat` WHERE cid = $gid AND uid = $user_id GROUP BY time ASC");
		$last_time = 0;
		$time_in_comm = 0;
		while ($res = mysql_fetch_assoc($req)) {
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
	break;
	
	case "users":
		mk_page(array(
			'title' => 'Пользователи', 
			'content' => Tpl::render("users.html")
		));
	break;
	
	case "search_users":
		$search = array_val($_GET, 'q', "");
		
		$res = $q->vkApi("users.search", array(
			'q'			=> $search, 
			'group_id'	=> $gid, 
			'fields'	=> 'sex,photo_50,bdate,verified'
		));
		
		$result = array();
		foreach ($res->response->items as $user) {
			$ret = vk_user_widget($user, '?a=user_info&amp;id='.$user->id);
			$ret['id'] = $user->id;
			$result[] = $ret;
		}
		mk_ajax(array('list' => Tpl::render("widgets/search_users_result.html", array(
			'users' => $result
		))));
	break;
	
	case "users_top":
		$users = array();
		$stat = array(
			'likes' => array(), 
			'reposts' => array(), 
			'comments' => array()
		);
		
		$filter = array_val($_GET, 'filter', "all");
		
		$req = mysql_query("SELECT user_id, SUM(likes) as likes, SUM(reposts) as reposts, SUM(comments) as comments, COUNT(*) as c 
			FROM `vk_posts_reposts` WHERE group_id = -$gid GROUP BY user_id");
		while ($row = mysql_fetch_assoc($req)) {
			if ($row['c'] > 0) {
				$users[] = $row['user_id'];
				$stat['reposts'][] = $row;
			}
		}
		mysql_free_result($req);
		$users = array_unique($users);
		
		$req = mysql_query("SELECT user_id, COUNT(*) as c FROM `vk_posts_likes` as a WHERE 
			EXISTS(select 0 from vk_join_stat as b WHERE b.uid = a.user_id AND b.cid = $gid) AND group_id = -$gid GROUP BY user_id");
		while ($row = mysql_fetch_assoc($req)) {
			if ($row['c'] > 0) {
				$users[] = $row['user_id'];
				$stat['likes'][] = $row;
			}
		}
		mysql_free_result($req);
		$users = array_unique($users);
		
		$req = mysql_query("SELECT user_id, COUNT(*) as c
			FROM `vk_posts_comments` WHERE group_id = -$gid GROUP BY user_id");
		while ($row = mysql_fetch_assoc($req)) {
			if ($row['c'] > 0) {
				$users[] = $row['user_id'];
				$stat['comments'][] = $row;
			}
		}
		mysql_free_result($req);
		$users = array_unique($users);
		
		$join_data = array();
		if ($users) {
			$users = array_unique($users);
			$req = mysql_query("SELECT * FROM `vk_join_stat` WHERE uid IN(".implode(", ", $users).") AND cid = $gid GROUP BY uid ORDER BY id DESC");
			while ($row = mysql_fetch_assoc($req))
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
	break;
	
	case "fix_timeout":
		$output = array('gid' => $gid);
		fix_comments_timeout($q, $gid, $comm, $output);
		mk_ajax($output);
	break;
	
	case "queue":
		$output = array();
		$id = (int) array_val($_REQUEST, 'id', 0);
		$signed = (int) array_val($_REQUEST, 'signed', 0);
		$lat = (float) array_val($_REQUEST, 'lat', 0);
		$long = (float) array_val($_REQUEST, 'long', 0);
		$message = array_val($_REQUEST, 'message', "");
		$attachments = array_val($_REQUEST, 'attachments', "");
		
		$res = get_comments($q, $gid);
		
		$base_time = max($res->lastPosted, $res->lastPostponed);
		$post_time = get_post_time($base_time, $comm, $res->lastSpecial);
		
		if (!isset($res->suggests->items[$id])) {
			$output['error'] = 'Пост #'.$id.' не найден в предложениях!';
		} else {
			$data = array(
				'post_id' => $id, 
				'owner_id' => -$gid, 
				'signed' => $signed, 
				'message' => $message, 
				'lat' => $lat, 
				'long' => $long, 
				'attachments' => $attachments
			);
			
			if ($post_time)
				$data['publish_date'] = $post_time;
			
			$res = $q->vkApi("wall.post", $data);
			if (parse_vk_error($res, $output)) {
				$output['success'] = true;
				$output['date'] = display_date($post_time);
			}
		}
		
		mk_ajax($output);
	break;
	
	case "special_post":
		$id = (int) array_val($_REQUEST, 'id', 0);
		$state = (int) array_val($_REQUEST, 'state', 0);
		if ($state) {
			mysql_query("INSERT IGNORE INTO `vk_special_posts` SET group_id = $gid, post_id = $id");
		} else {
			mysql_query("DELETE FROM `vk_special_posts` WHERE group_id = $gid AND post_id = $id");
		}
		mk_ajax(['success' => true]);
	break;
	
	case "delete":
		$id = (int) array_val($_REQUEST, 'id', 0);
		$restore = (int) array_val($_REQUEST, 'restore', 0);
		
		$output = array();
		
		$res = $q->vkApi($restore ? "wall.restore" : "wall.delete", array(
			'owner_id' => -$gid, 
			'post_id' => $id
		));
		if (parse_vk_error($res, $output))
			$output['success'] = true;
		mk_ajax($output);
	break;
	
	case "settings":
		if ($_POST) {
			$from_hh = min(max(0, (int) array_val($_POST, 'from_hh', 0)), 23);
			$from_mm = min(max(0, (int) array_val($_POST, 'from_mm', 0)), 59);
			
			$to_hh = min(max(0, (int) array_val($_POST, 'to_hh', 0)), 23);
			$to_mm = min(max(0, (int) array_val($_POST, 'to_mm', 0)), 59);
			
			$interval_hh = min(max(0, (int) array_val($_POST, 'hh', 0)), 23);
			$interval_mm = min(max(0, (int) array_val($_POST, 'mm', 0)), 59);
			
			$to = $to_hh * 3600 + $to_mm * 60;
			$from = $from_hh * 3600 + $from_mm * 60;
			$interval = $interval_hh * 3600 + $interval_mm * 60;
			
			$interval = round($interval / 300) * 300;
			
			mysql_query("UPDATE `vk_groups` SET `period_from` = $from, `period_to` = $to, `interval` = $interval");
		}
		header("Location: ".preg_replace("/[\s:]/si", "", isset($_REQUEST['return']) ? $_REQUEST['return'] : '?'));
	break;
	
	case "multipicpost":
		if ($_FILES && isset($_FILES['file'])) {
			$out = array();
			if ($_FILES['file']['error']) {
				$out['error'] = 'Произошла странная ошибка под секретным номером #'.$_FILES['file']['error'];
			} elseif (!getimagesize($_FILES['file']['tmp_name'])) {
				$out['fatal'] = true;
				$out['error'] = 'Что за дичь? Не очень похоже на пикчу с котиком.';
			} else {
				save_pic_post($out, [
					[
						'path'		=> $_FILES['file']['tmp_name'], 
						'caption'	=> isset($_POST['caption']) ? $_POST['caption'] : ""
					]
				], isset($_POST['message']) ? $_POST['message'] : "");
			}
			
			echo json_encode($out);
			exit;
		}
		
		mk_page(array(
			'title' => 'Пользователи', 
			'content' => Tpl::render("multipicpost.html")
		));
	break;
	
	default:
		$filter = isset($_REQUEST['filter']) ? preg_replace("/[^a-z0-9_-]+/i", "", $_REQUEST['filter']) : 'new';
		
		$special_posts = get_special_posts($gid);
		
		$res = get_comments($q, $gid);
		
		$invalid_interval_cnt = 1;
		$last_post_time = 0;
		$last_is_special = false;
		foreach ($res->postponed->items as $post) {
			if (!$last_post_time)
				$last_post_time = $post->date;
			
			$norm_time = get_post_time($last_post_time, $comm, $last_is_special);
			
			if (!isset($special_posts[$post->id]) && $last_post_time != $post->date) {
				$diff = ($norm_time - $post->date);
				if (abs($diff) > 5) {
					$post->invalid = $diff;
					$post->expected_date = $norm_time;
					++$invalid_interval_cnt;
				}
			}
			
			$last_post_time = $post->date;
			$last_is_special = isset($special_posts[$post->id]) ? $post->date : false;
		}
		
		$users = $res->users;
		
		$url = (new Url("?"))->set(array(
			'gid' => $gid
		));
		
		$list = $res->suggests;
		if ($filter == 'accepted')
			$list = $res->postponed;
		
		$json = array();
		$comments = array();
		foreach ($list->items as $item) {
			$json[$item->id] = get_post_json($item);
			$comments[] = Tpl::render("widgets/comment.html", array(
				'date' => display_date($item->date), 
				'text' => nl2br(links(check_spell(htmlspecialchars($item->text, ENT_QUOTES)))), 
				'id' => $item->id, 
				'gid' => abs($item->owner_id), 
				'user' => vk_user_widget($users[isset($item->created_by) && $item->created_by ? $item->created_by : $item->from_id]), 
				'deleted' => false, 
				'invalid' => isset($item->invalid) ? -$item->invalid : false, 
				'expected_date' => isset($item->expected_date) ? display_date($item->expected_date) : false, 
				'post_type' => $item->post_type, 
				'geo' => isset($item->geo) ? $item->geo : null, 
				'attachments' => isset($item->attachments) ? $item->attachments : null, 
				'special' => isset($special_posts[$item->id]) ? 1 : 0
			));
		}
		
		mk_page(array(
			'title' => 'Предложения постов', 
			'content' => Tpl::render("comments.html", array(
				'gid' => $gid, 
				
				'json' => json_encode($json, JSON_UNESCAPED_UNICODE), 
				
				'tabs' => switch_tabs([
					'url' => $url, 
					'param' => 'filter', 
					'tabs' => [
						'new'		=> 'Новые ('.$res->suggests->count.')', 
						'accepted'	=> 'Принятые ('.$res->postponed->count.')'
					], 
					'active' => $filter
				]), 
				
				'last_post_time' => $res->lastPosted ? display_date($res->lastPosted) : 'n/a', 
				'last_delayed_post_time' => $res->lastPostponed ? display_date($res->lastPostponed) : 'n/a', 
				
				'last_post_time_unix' => $res->lastPosted, 
				'last_delayed_post_time_unix' => $res->lastPostponed, 
				
				'postponed_link' => (string) (new Url("?"))->set(array(
					'gid' => $gid, 
					'filter' => 'accepted'
				)), 
				'invalid_interval_cnt' => $invalid_interval_cnt, 
				'back' => $_SERVER['REQUEST_URI'], 
				
				'comments' => $comments, 
				'filter' => $filter, 
				'from' => parse_time($comm['period_from']), 
				'to' => parse_time($comm['period_to']), 
				'interval' => parse_time($comm['interval']), 
				'success' => isset($_REQUEST['ok']), 
				'postponed_cnt' => $res->postponed->count, 
				'suggests_cnt' => $res->suggests->count
			))
		));
	break;
}

function save_pic_post(&$out, $images, $text) {
	global $q, $gid, $comm;
	
	$res = $q->vkApi("photos.getWallUploadServer", array(
		'group_id' => $gid
	));
	if (($error = vk_api_error($res))) {
		$out['error'] = $error;
	} else if (!isset($res->response->upload_url)) {
		$out['error'] = "upload_url не найден :(";
	} else {
		if (!$images)
			$out['error'] = "А где же картинки?!??!";
		
		$attachments = [];
		foreach ($images as $i => $img) {
			$upload_raw = $q->vkApiUpload($res->response->upload_url, [
				['path' => $img['path'], 'name' => "cat.jpg"]
			])->body;
			$upload = @json_decode($upload_raw);
			
			if (!$upload_raw) {
				$out['error'] = "Ошибка подключения к UPLOAD серверу при загрузке фото #$i! (path: ".$img['path'].", server: ".$res->response->upload_url.")";
				break;
			} else if (!$upload) {
				$out['error'] = '<pre>'.htmlspecialchars($upload_raw).'</pre>';
				break;
			} else if ($upload->photo == "[]") {
				$out['error'] = "Сервер не отдал фотографию для #$i! <pre>".htmlspecialchars($upload_raw).'</pre>';
				break;
			} else {
				$file = $q->vkApi("photos.saveWallPhoto", array(
					'group_id'		=> $gid, 
					'photo'			=> stripcslashes($upload->photo), 
					'server'		=> $upload->server, 
					'hash'			=> $upload->hash, 
					'caption'		=> isset($img['caption']) ? $img['caption'] : ""
				));
				if (!isset($file->response) || !$file->response) {
					$out['error'] = "Ошибка сохранения фото #$i в стене!!!";
					break;
				} else {
					$attachments[] = 'photo'.$file->response[0]->owner_id.'_'.$file->response[0]->id;
				}
			}
		}
		
		if (!isset($out['error'])) {
			$comments = get_comments($q, $gid);
			
			$base_time = max($comments->lastPosted, $comments->lastPostponed);
			$post_time = get_post_time($base_time, $comm, $comments->lastSpecial);
			
			$data = array(
				'owner_id'		=> -$gid, 
				'signed'		=> 0, 
				'message'		=> $text, 
				'attachments'	=> implode(",", $attachments)
			);
			
			if ($post_time)
				$data['publish_date'] = $post_time;
			
			$res = $q->vkApi("wall.post", $data);
			if (($error = vk_api_error($res))) {
				$out['error'] = $error;
				$out['data'] = $data;
			} else {
				$out['link'] = 'https://vk.com/wall-'.$gid.'_'.$res->response->post_id;
				$out['date'] = display_date($post_time);
			}
		}
	}
	$out['success'] = !isset($out['error']);
	
	return $out;
}

function mk_page($args) {
	global $gid, $curl, $comms;
	
	$comm_tabs = [];
	foreach ($comms as $comm)
		$comm_tabs[$comm['id']] = $comm['name'];
	
	$def = [
		'comm_tabs'	=> switch_tabs([
			'url' => Url::mk()->set('gid', $gid), 
			'param' => 'gid', 
			'tabs' => $comm_tabs
		], $gid), 
		'sections_tabs' => switch_tabs([
			'url' => Url::mk('?')->set('gid', $gid), 
			'param' => 'a', 
			'tabs' => [
				'index'				=> 'Предложки', 
				'multipicpost'		=> 'Мультипикчепостинг', 
				'grabber'			=> 'Граббер', 
				'users_top'			=> 'Топ юзверей', 
				'users'				=> 'Юзвери', 
				'activity'			=> 'Активность', 
				'returns'			=> 'Возвраты', 
			], 
			'active' => isset($_REQUEST['a']) ? $_REQUEST['a'] : 'index'
		])
	];
	header("Content-Type: text/html; charset=UTF-8");
	echo Tpl::render("main.html", $def + $args);
}

function get_special_posts($gid) {
	return [];
	
	$special_posts = [];
	$req = mysql_query("SELECT * FROM `vk_special_posts` WHERE group_id = $gid");
	while ($res = mysql_fetch_assoc($req))
		$special_posts[$res['post_id']] = 1;
	return $special_posts;
}

function get_post_time($base_time, $comm, $last_special_post, $incr = true) {
	$base_time = max(time() + 60, $base_time);
	if ($incr) {
		if (!$last_special_post || $last_special_post - $base_time > $comm['interval']) {
			// Между специальным и обычным топиком поместится целый пост
			$post_time = $base_time + $comm['interval'];
		} else {
			// Между специальным и обычным топиком не поместится целый пост
			$post_time = $last_special_post + 3600;
		}
	}
	
	// Фиксим по заданным периодам публикации
	$day_start = get_day_start($post_time);
	if (24 * 3600 - ($comm['period_to'] - $comm['period_from']) > 60) { // Есть фиксированный период постинга
		if ($post_time - ($day_start + $comm['period_from']) <= -10) {
			// Если время не попадает под минимальный период, то переносим его на начало периода текущего дня
			$post_time = $day_start + $comm['period_from'];
		} else if ($post_time - ($day_start + $comm['period_to']) >= 10) {
			// Если время превышает границу времени, то переносим на следующий день
			$post_time = $day_start + 24 * 3600 + $comm['period_from'];
		}
	}
	
	// Выравниваем по 10 минут
	if ($post_time && ($post_time % 300 != 0))
		$post_time = round($post_time / 300) * 300;
	
	return $post_time;
}

function fix_comments_timeout($q, $gid, $comm, &$output) {
	$code = '
		var postponed = API.wall.get({
			owner_id:	-'.$gid.', 
			filter:		"postponed", 
			extended:	true, 
			count:		100
		});
		var last_comment = API.wall.get({
			owner_id: -'.$gid.', 
			filter: "all", 
			count: 2
		});
		
		var p1 = last_comment.items.length > 0 ? last_comment.items[0].date : 0;
		var p2 = last_comment.items.length > 1 ? last_comment.items[1].date : 0;
		
		return {
			postponed: postponed, 
			lastPosted: p1 > p2 ? p1 : p2
		};
	';
	
	$wall = $q->vkApi("execute", ['code' => $code]);
	
	if ($gid != 144599756) {
		$output['error'] = 'Invalid gid: '.$gid;
		return;
	}
	
	if (!isset($wall->response)) {
		parse_vk_error($wall, $output);
		return;
	}
	
	if (!isset($wall->response->lastPosted)) {
		$output['error'] = 'lastPosted not found';
		return;
	}
	
	if (!isset($wall->response->postponed)) {
		$output['error'] = 'postponed not found';
		return;
	}
	
	$output['fixed'] = 0;
	$output['processed'] = 0;
	$output['total'] = $wall->response->postponed->count;
	
	$special_posts = get_special_posts($gid);
	
	$attach_map = array(
		"photo"		=> 1, 
		"video"		=> 1, 
		"doc"		=> 1, 
		"audio"		=> 1, 
		"page"		=> 1, 
		"note"		=> 1, 
		"poll"		=> 1, 
		"album"		=> 1, 
		"link"		=> 1, 
	);
	
	$cnt = 0;
	$last_post_time = 0;
	$last_is_special = 0;
	$error = false;
	foreach ($wall->response->postponed->items as $item) {
		++$output['processed'];
		
		if (!$last_post_time)
			$last_post_time = $item->date;
		
		$post_time = get_post_time($last_post_time, $comm, $last_is_special, $last_post_time != $item->date);
		
		// У специальных постов нельзя менять publish_date
		if ($last_is_special)
			$post_time = $item->date;
		
		$last_is_special = isset($special_posts[$item->id]) ? $item->date : 0;
		$last_post_time = $post_time;
		
		echo '#'.$item->id.' '.(isset($special_posts[$item->id]) ? '(special)' : '')." => ".display_date($post_time)."\n";
		
		$publish_diff = abs($item->date - $post_time);
		if ($publish_diff > 5 && !isset($special_posts[$item->id])) {
			// Парсим аттачи
			$attachments = [];
			if (isset($item->attachments)) {
				foreach ($item->attachments as $att) {
					if (!isset($attach_map[$att->type])) {
						$error = 'Неизвестный аттач: '.$att->type;
						break;
					}
					$att_data = $att->{$att->type};
					if ($att->type == 'link') {
						$attachments[] = $att_data->url;
					} else {
						$attachments[] = $att->type.$att_data->owner_id.'_'.$att_data->id;
					}
				}
			}
			
			// Парсим geo
			$place_id = $lat = $lng = false;
			if (isset($item->geo) && $item->geo) {
				if (isset($geo->place_id)) {
					$place_id= $geo->place_id;
				} elseif (preg_match("/^-?([\d\.]+) -?([\d\.]+)$/", $item->geo->coordinates, $m)) {
					$lat = $m[1];
					$lng = $m[2];
				} else {
					$error = 'Неизвестный формат координат: '.$item->geo->coordinates;
				}
			}
			
			$api_data = [
				'owner_id'		=> $item->owner_id, 
				'post_id'		=> $item->id, 
				'publish_date'	=> $post_time, 
				'attachments'	=> implode(",", $attachments), 
				'message'		=> $item->text, 
				'signed'		=> $item->signer_id ? 1 : 0, 
			];
			
			if ($lat !== false) {
				$api_data['lat'] = $lat;
				$api_data['lng'] = $lng;
			}
			
			if ($place_id)
				$api_data['place_id'] = $place_id;
			
			if (isset($item->mark_as_ads))
				$api_data['mark_as_ads'] = $item->mark_as_ads ? 1 : 0;
			
			if (isset($item->friends_only))
				$api_data['friends_only'] = $item->friends_only ? 1 : 0;
			/*
			$res = $q->vkApi("wall.edit", $api_data);
			if ($res && isset($res->response)) {
				++$output['fixed'];
			} else {
				parse_vk_error($res, $output);
				$output['post'] = $item;
				$output['api_data'] = $api_data;
				$output['publish_diff'] = $publish_diff;
				break;
			}
			*/
		}
		
		++$cnt;
		
		if ($error)
			break;
	}
	
	if (!isset($output['error'])) {
		if (!$error && !$cnt)
			$error = "Не найдено отложенных постов.";
		if ($error) {
			$output['error'] = $error;
		} else {
			$output['success'] = true;
		}
	}
	
	var_dump($output);
	
	exit;
}

function parse_vk_error($res, &$output) {
	if ($res && isset($res->response))
		return true;
	if ($res && isset($res->error)) {
		if ($res->error->error_code == 6) {
			$output['sleep'] = true;
			$output['error'] = $res->error->error_msg;
		} elseif ($res->error->error_code == 14) {
			$output['captcha'] = array(
				'url' => $res->error->captcha_img, 
				'sid' => $res->error->captcha_sid
			);
		} else {
			$output['error'] = $res->error->error_msg;
		}
	} elseif (!$res || !isset($res->response)) {
		$output['error'] = "VK API недоступен. ".json_encode($res);
	}
	return false;
}

function get_comments($q, $gid) {
	$code = '
		var postponed = API.wall.get({
			owner_id: -'.$gid.', 
			filter: "postponed", 
			extended: true, 
			count: 100
		});
		var suggests = API.wall.get({
			owner_id: -'.$gid.', 
			filter: "suggests", 
			extended: true, 
			count: 100
		});
		var last_comment = API.wall.get({
			owner_id: -'.$gid.', 
			filter: "all", 
			count: 2
		});
		
		if (!postponed)
			postponed = {};
		
		postponed.profiles = API.users.get({user_ids: postponed.items@.created_by, fields: "photo_50"});
		
		var p1 = last_comment.items.length > 0 ? last_comment.items[0].date : 0;
		var p2 = last_comment.items.length > 1 ? last_comment.items[1].date : 0;
		
		return {
			postponed: postponed, 
			suggests: suggests, 
			lastPosted: p1 > p2 ? p1 : p2
		};
	';
	$out = $q->vkApi("execute", array('code' => $code));
	if (!isset($out->response)) {
		echo '<pre>';
		echo htmlspecialchars(json_encode($out, JSON_PRETTY_PRINT));
		echo '</pre>';
		die();
	}
	
	$res = $out->response;
	
	$special_posts = get_special_posts($gid);
	
	$users = array();
	$res->lastPostponed = 0;
	$res->lastSpecial = 0;
	if ($res->postponed) {
		foreach ($res->postponed->profiles as $u)
			$users[$u->id] = $u;
		$comments = array();
		foreach ($res->postponed->items as $c) {
			if (!isset($special_posts[$c->id])) {
				$res->lastPostponed = $c->date;
				$res->lastSpecial = false;
			} else {
				if (!$res->lastSpecial)
					$res->lastSpecial = $c->date;
			}
			$comments[$c->id] = $c;
		}
		$res->postponed->items = $comments;
	}
	if ($res->suggests) {
		foreach ($res->suggests->profiles as $u)
			$users[$u->id] = $u;
		$comments = array();
		foreach ($res->suggests->items as $c)
			$comments[$c->id] = $c;
		$res->suggests->items = array_reverse($comments, true);
	}
	$res->users = $users;
	
	return $res;
}

function get_wall_comments($q, $gid, $filter) {
	$res = $q->vkApi("wall.get", array(
		'owner_id' => $gid, 
		'filter' => $filter, 
		'extended' => true
	));
	$users = array();
	foreach ($res->response->profiles as $u)
		$users[$u->id] = $u;
	
	$comments = array();
	foreach ($res->response->items as $c)
		$comments[$u->id] = $c;
	return (object) array('items' => $comments, 'users' => $users);
}

function mk_ajax($data) {
	header("Content-Type: application/json; charset=UTF-8");
	echo json_encode($data, JSON_PRETTY_PRINT);
}

function vk_api_error($res) {
	if (!$res)
		return "Ошибка подключения к API";
	if (isset($res->error)) {
		if ($res->error->error_code == 5) {
			return "Ошибка авторизации API";
		} else if ($res->error->error_code == 14) {
			$ret->captcha = array(
				'img' => $res->error->captcha_img, 
				'sid' => $res->error->captcha_sid
			);
			return "Нужно ввести капчу";
		}
		return $res->error->error_msg;
	}
	if (!isset($res->response))
		return "Неверный ответ API!";
	return false;
}

function get_post_json($post) {
	$attach_map = array(
		"photo" => "photo", 
		"video" => "video", 
		"doc" => "doc", 
		"audio" => "audio", 
		"poll" => "poll", 
		"album" => "album"
	);
	$out = array(
		'lat' => 0, 
		'long' => 0, 
		'attachments' => array(), 
		'post_id' => $post->id, 
		'owner_id' => $post->owner_id, 
		'publish_date' => isset($post->publish_date) ? $post->publish_date : 0, 
		'message' => $post->text, 
		'signed' => isset($post->signer_id) ? (int) ((bool) $post->signer_id) : 0
	);
	if (isset($post->attachments)) {
		foreach ($post->attachments as $att) {
			if (isset($attach_map[$att->type])) {
				$att_data = $att->{$att->type};
				$out['attachments'][] = $att->type.$att_data->owner_id."_".$att_data->id;
			} else if ($att->type == 'link') {
				$out['attachments'][] = $att->link->url;
			} else {
				die("Unknown attach error: <pre>".htmlspecialchars(print_r($att, 1)).'</pre>');
			}
		}
	}
	if (isset($post->geo)) {
		if (!preg_match("/^([\d\.-]+) ([\d\.-]+)$/", $post->geo->coordinates, $geo))
			die("Invalid geo: ".$post->geo->coordinates);
		$out['lat'] = $geo[1];
		$out['long'] = $geo[2];
	}
	return $out;
}						

function links($text) {
	return preg_replace_callback('/(?:([!()?.,\s\n\r]|^)((https?:\/\/)?((?:[a-z0-9_\-]+\.)+(?:[a-z]{2,7}|xn--p1ai|xn--j1amh|xn--80asehdb|xn--80aswg))(\/.*?)?(\#.*?)?)(?:[.!:;,*()]*([\s\r\n]|$))|([!()?.,\s\n\r]|^)((https?:\/\/)?((?:[a-z0-9а-яєґї_\-]+\.)+(?:рф|укр|онлайн|сайт|срб|su))(\/.*?)?(\#.*?)?)(?:[.!:;,*()]*([\s\r\n]|$))|([!()?.,\s\n\r]|^)((https?:\/\/)((?:[a-z0-9а-яєґї_\-]+\.)+(?:[a-z]{2,7}|рф|укр|онлайн|сайт|срб|su))(\/.*?)?(\#.*?)?)(?:[.!:;,*()]*([\s\r\n]|$)))/sium', function ($m) {
		$offset = strlen($m[4]) ? 0 : (strlen($m[7 + 4]) ? 7 : 14);
		$url = $m[$offset + 2];
		return $m[$offset + 1].'<a href="'.$url.'" target="_blank">'.$url.'</a>'.$m[$offset + 7];
	}, $text);
}

function parse_time($t) {
	$h = floor($t / 3600);
	
	$m = floor(($t - $h * 3600) / 60);
	
	return array(
		'hh' => sprintf("%02d", $h), 
		'mm' => sprintf("%02d", $m)
	);
}

function check_spell($text) {
	if (function_exists('pspell_new')) {
		$errors = array();
		$text = preg_replace_callback("/([a-zа-яё][a-zа-яё'-]+[a-zа-яё]|[a-zа-яё]+)/siu", function ($m) {
			$lang = "en";
			if (preg_match("/[а-яё]/iu", $m[1]))
				$lang = "ru";
			
			if ($lang != "ru")
				return $m[1];
			
			if (!isset($psell[$lang]))
				$psell[$lang] = pspell_new($lang);
			
			if (!pspell_check($psell[$lang], $m[1])) {
				$errors = pspell_suggest($psell[$lang], $m[1]);
				return '<span class="spell" title="'.implode(", ", $errors).'">'.$m[1].'</span>';
			}
			return $m[1];
		}, $text);
	}
	return $text;
}

function get_vk_users($need_users) {
	$cache = array();
	$users = array();
	
	$cache_file = "tmp/vk_users_stat.dat";
	$need_users = array_unique($need_users);
	
	if (file_exists($cache_file)) {
		$fp = fopen($cache_file, "r");
		flock($fp, LOCK_EX);
		
		$data = "";
		while (!feof($fp))
			$data .= fread($fp, 2048);
		flock($fp, LOCK_UN);
		fclose($fp);
		
		$cache = unserialize($data);
	}
	
	while (count($need_users)) {
		$users_list = array();
		for ($i = 0; $i < 500; ++$i)
			$users_list[] = array_pop($need_users);
		
		$not_found = array();
		foreach ($users_list as $id) {
			if (!isset($cache[$id])) {
				$not_found[] = $id;
				continue;
			}
			$users[$id] = $cache[$id];
		}
		
		if ($not_found) {
			$vk_users = vk("users.get", array('user_ids' => implode(",", $not_found), 'fields' => 'sex,photo_50,bdate,verified'))->response;
			foreach ($vk_users as $u) {
				$users[$u->id] = $u;
				$cache[$u->id] = $u;
			}
			file_put_contents($cache_file, serialize($cache));
		}
		unset($vk_users);
	}
	
	$fp = fopen($cache_file, "w");
	flock($fp, LOCK_EX);
	ftruncate($fp, 0);
	fwrite($fp, serialize($cache));
	flock($fp, LOCK_UN);
	fclose($fp);
	
	return $users;
}

function vk_user_widget($user, $link = NULL) {
	return array(
		'widget' => Tpl::render("widgets/vk_user.html", array(
			'name' => $user->id == 289678746 ? 'Выдрочка' : $user->first_name." ".$user->last_name, 
			'preview' => $user->photo_50, 
			'link' => $link ? $link : "https://vk.com/".(isset($user->screen_name) && strlen($user->screen_name) ? $user->screen_name : 'id'.$user->id)
		)), 
		'avatar' => Tpl::render("widgets/vk_user_ava.html", array(
			'preview' => $user->photo_50
		))
	);
}
