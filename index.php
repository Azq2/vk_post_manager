<?php
require __DIR__."/inc/init.php";
require __DIR__."/inc/vk_posts.php";

error_reporting(E_ALL);
ini_set('display_errors', 'On');

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
			if (!preg_match("/^[\w\d_]+$/i", $type))
				die('type: '.htmlspecialchars($type));
			
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
				} else if ($_GET['state'] == 'VK' || $_GET['state'] == 'VK_SCHED') {
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
					echo "RAW: ".$raw;
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
							'scope'			=> 'offline wall groups photos docs', 
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
		$sort = array_val($_REQUEST, 'sort', 'DESC');
		$mode = array_val($_REQUEST, 'mode', 'external');
		$content_filter = array_val($_REQUEST, 'content', 'pics');
		
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
				$source_id = isset($_POST['source_id']) ? $_POST['source_id'] : '';
				$source_type = isset($_POST['source_type']) ? $_POST['source_type'] : '';
				$remote_id = isset($_POST['remote_id']) ? $_POST['remote_id'] : '';
				
				if ($source_id && $source_type && $remote_id) {
					mysql_query("
						INSERT IGNORE INTO `vk_grabber_blacklist` SET
							group_id = $gid, 
							source_id = '".mysql_real_escape_string($source_id)."', 
							source_type = '".mysql_real_escape_string($source_type)."', 
							remote_id = '".mysql_real_escape_string($remote_id)."'
					");
				}
				mk_ajax(['success' => true]);
				exit;
			break;
			
			case "load":
				$O = isset($_REQUEST['O']) ? (int) $_REQUEST['O'] : 0;
				$L = isset($_REQUEST['L']) ? (int) $_REQUEST['L'] : 10;
				
				$where = [];
				$order = '';
				
				$sources_hash = [];
				foreach ($sources as $s) {
					if ($s['enabled'])
						$sources_hash[$s['type']][] = "'".mysql_real_escape_string($s['id'])."'";
				}
				
				$sources_where = [];
				foreach ($sources_hash as $type => $ids)
					$sources_where[] = "(d.`source_type` = '".mysql_real_escape_string($type)."' AND d.`source_id` IN (".implode(",", $ids)."))\n";
				
				// Фильтр по типу контента
				if ($content_filter == 'pics')
					$where[] = '(d.`images_cnt` > 0 OR d.`gifs_cnt` > 0)';
				elseif ($content_filter == 'only_gif')
					$where[] = 'd.`gifs_cnt` > 0';
				elseif ($content_filter == 'without_gif')
					$where[] = 'd.`images_cnt` > 0';
				
				if ($sort == 'DESC') {
					$order = 'ORDER BY d.`time` DESC';
				} else if ($sort == 'ASC') {
					$order = 'ORDER BY d.`time` ASC';
				} else if ($sort == 'RAND') {
					if (isset($_REQUEST['exclude'])) {
						$exclude = [];
						foreach (explode(",", $_REQUEST['exclude']) as $t)
							$exclude[] = "'".mysql_real_escape_string($t)."'";
						if ($exclude)
							$where[] = 'CONCAT(d.`source_type`, "_", d.`remote_id`) NOT IN ('.implode(", ", $exclude).')';
					}
					$order = 'ORDER BY RAND()';
				} else if ($sort == 'LIKES') {
					$order = 'ORDER BY d.`likes` DESC';
				} else if ($sort == 'REPOSTS') {
					$order = 'ORDER BY d.`reposts` DESC';
				} else if ($sort == 'COMMENTS') {
					$order = 'ORDER BY d.`comments` DESC';
				}
				
				if ($mode == 'internal') {
					// Из своего сообщества
					$where[] = '(d.`source_type` = "VK" AND d.`source_id` = -'.$gid.')';
				} else {
					// Из чужих сообществ
					if ($sources_where)
						$where[] = '('.implode(" OR ", $sources_where).')';
				}
				
				$time_list = microtime(true);
				$where[] = 'NOT EXISTS (
					SELECT 0 FROM `vk_grabber_blacklist` as b WHERE
						b.group_id = '.$gid.' AND
						b.source_id = d.source_id AND
						b.source_type = d.source_type AND
						b.remote_id = d.remote_id
				)';
				
				$sql = "
					SELECT SQL_CALC_FOUND_ROWS d.* FROM `vk_grabber_data_index` as `d`
						".($where ? "WHERE ".implode(" AND ", $where) : "")."
					$order
					LIMIT $O, $L
				";
				
				$req = mysql_query($sql);
				if (!$req) {
					mk_ajax([
						'success' 	=> false, 
						'sql'		=> $sql, 
						'error'		=> mysql_error()
					]);
					exit;
				}
				
				$req2 = mysql_query("SELECT FOUND_ROWS()");
				$count = mysql_result($req2, 0);
				
				// Получаем массив id данных и 
				$meta = [];
				$items = [];
				while ($res = mysql_fetch_assoc($req)) {
					$items[$res['data_id']] = 1;
					$meta[$res['data_id']] = $res;
				}
				mysql_free_result($req);
				mysql_free_result($req2);
				$time_list = microtime(true) - $time_list;
				
				if ($items) {
					// Получаем овнеров
					$owners = [];
					$req = mysql_query("SELECT * FROM `vk_grabber_data_owners`");
					while ($res = mysql_fetch_assoc($req))
						$owners[$res['id']] = $res;
					mysql_free_result($req);
					
					$time_data = microtime(true);
					$req = mysql_query("SELECT * FROM `vk_grabber_data` WHERE `id` IN (".implode(",", array_keys($items)).")");
					while ($res = mysql_fetch_assoc($req)) {
						$res = array_merge($res, $meta[$res['id']]);
						$owner = $owners[$res['source_type'].'_'.$res['owner']];
						$res['owner_name'] = $owner['name'];
						$res['owner_url'] = $owner['url'];
						$res['owner_avatar'] = $owner['avatar'];
						$res['attaches'] = unserialize(gzinflate($res['attaches']));
						$items[$res['id']] = $res;
					}
					mysql_free_result($req);
					$time_data = microtime(true) - $time_data;
				}
				
				mk_ajax(['success' => true, 'sql' => $sql, 'owners' => $owners, 'items' => array_values($items), 'total' => (int) $count, 
							'time_data' => $time_data, 'time_list' => $time_list]);
				exit;
			break;
			
			case "queue_done":
				$id = isset($_REQUEST['id']) ? preg_replace("/[^a-f0-9]/", "", $_REQUEST['id']) : '';
				$out = [];
				if (file_exists(H.'../tmp/post_queue/'.$id)) {
					$status = json_decode(file_get_contents(H.'../tmp/post_queue/'.$id), true);
					if (isset($status['out'], $status['out']['error'])) {
						$out['error'] = $status['out']['error'];
					} elseif (isset($status['attaches'])) {
						if (!isset($status['post_id'])) {
							// Добавляем пост
							add_queued_wall_post($out, $status['attaches'], $status['text']);
							
							if (!isset($out['error'])) {
								$status['post_id'] = $out['post_id'];
								file_put_contents(H.'../tmp/post_queue/'.$id, json_encode($status));
							}
						}
						
						if (!isset($out['error'])) {
							$out['link'] = 'https://vk.com/wall-'.$gid.'_'.$status['post_id'];
							$out['post_id'] = $status['post_id'];
						}
					} else {
						$out['queue'] = $status;
					}
				} else {
					$out['error'] = 'Очередь скачивания файла уже удалена. ('.$id .')';
				}
				mk_ajax($out);
				exit;
			break;
			
			case "queue":
				$out = [];
				$text = isset($_POST['text']) ? $_POST['text'] : '';
				
				if (isset($_POST['documents']) || isset($_POST['images'])) {
					$msg = json_encode([
						'images'	=> isset($_POST['images']) ? $_POST['images'] : [], 
						'documents'	=> isset($_POST['documents']) ? $_POST['documents'] : [], 
						'text'		=> $text, 
						'gid'		=> $gid
					]);
					
					file_put_contents(H.'../tmp/post_queue/'.md5($msg), $msg);
					chmod(H.'../tmp/post_queue/'.md5($msg), 0666);
					
					$out['id'] = md5($msg);
					$out['success'] = true;
				}
				
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
							$source_id = -$res->response[0]->id;
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
				$url = 'https://vk.com/public'.(-$s['id']);
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
	
	case "queue":
		$output = [];
		
		$id				= (int) array_val($_REQUEST, 'id', 0);
		$signed			= (int) array_val($_REQUEST, 'signed', 0);
		$lat			= (float) array_val($_REQUEST, 'lat', 0);
		$long			= (float) array_val($_REQUEST, 'long', 0);
		$message		= array_val($_REQUEST, 'message', "");
		$attachments	= array_val($_REQUEST, 'attachments', "");
		$post_type		= array_val($_REQUEST, 'type', "");
		
		if ($post_type == 'post')
			die;
		
		$req = mysql_query("SELECT MAX(`fake_date`) FROM `vk_posts_queue`");
		$fake_date = max(time() + 3600 * 24 * 60, mysql_num_rows($req) ? mysql_result($req, 0) : 0) + 3600;
		
		$res = $q->vkApi($post_type == 'suggest' ? "wall.post" : "wall.edit", [
			'post_id'		=> $id, 
			'owner_id'		=> -$gid, 
			'signed'		=> $signed, 
			'message'		=> $message, 
			'lat'			=> $lat, 
			'long'			=> $long, 
			'attachments'	=> $attachments, 
			'publish_date'	=> $fake_date
		]);
		
		$output['post_type'] = $post_type;
		if (parse_vk_error($res, $output)) {
			$output['success'] = true;
			$output['date'] = display_date($fake_date);
			
			mysql_query("
				INSERT INTO `vk_posts_queue`
				SET
					`fake_date`	= $fake_date, 
					`group_id`	= $gid, 
					`id`		= ".(isset($res->response->post_id) ? (int) $res->response->post_id : $id)."
			") or die("ERROR: ".mysql_error());
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
			
			mysql_query("UPDATE `vk_groups` SET `period_from` = $from, `period_to` = $to, `interval` = $interval WHERE `id` = $gid");
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
				$attachments = pics_uploader($out, $q, $gid, [
					'path'		=> $_FILES['file']['tmp_name'], 
					'caption'	=> isset($_POST['caption']) ? $_POST['caption'] : ""
				]);
				add_queued_wall_post($out, $attachments, isset($_POST['message']) ? $_POST['message'] : "");
			}
			
			mk_ajax($out);
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
		$res = get_comments($q, $comm);
		
		$url = (new Url("?"))->set(array(
			'gid' => $gid
		));
		
		$filter2list = [
			'accepted'	=> 'postponed', 
			'new'		=> 'suggests', 
			'special'	=> 'specials'
		];
		
		if (!isset($filter2list[$filter]))
			die("unknown filter?");
		
		$list = $filter2list[$filter];
		
		$last_date = "";
		
		$json = array();
		$comments = array();
		$last_time = 0;
		
		$last_posted = 0;
		$last_postponed = 0;
		
		$by_week_max = 0;
		$by_week = [];
		
		foreach ($res->{$list} as $item) {
			$from_id = (isset($item->created_by) && $item->created_by ? $item->created_by : (isset($item->from_id) ? $item->from_id : $item->owner_id));
			
			$date = display_date($item->date, false, false);
			
			if (!isset($by_week[date("Y-W-N", $item->date)]))
				$by_week[date("Y-W-N", $item->date)] = ['n' => date("N", $item->date), 'date' => $date, 'cnt' => 0];
			++$by_week[date("Y-W-N", $item->date)]['cnt'];
			
			$by_week_max = max($by_week_max, $by_week[date("Y-W-N", $item->date)]['cnt']);
			
			if ($date != $last_date)
				$last_time = 0;
			
			if ($item->post_type == 'post')
				$last_posted = max($item->date, $last_posted);
			else if (!isset($special_posts[$item->id]))
				$last_postponed = max($item->date, $last_postponed);
			
			$json[$item->id] = get_post_json($item);
			$comments[] = Tpl::render("widgets/comment.html", array(
				'date'			=> display_date($item->date), 
				'text'			=> nl2br(links(check_spell(htmlspecialchars($item->text, ENT_QUOTES)))), 
				'id'			=> $item->id, 
				'gid'			=> abs($item->owner_id), 
				'user'			=> vk_user_widget($res->users[$from_id]), 
				'deleted'		=> false, 
				'post_type'		=> $item->post_type, 
				'list'			=> $list, 
				'geo'			=> isset($item->geo) ? $item->geo : null, 
				'attachments'	=> isset($item->attachments) ? $item->attachments : null, 
				'special'		=> isset($special_posts[$item->id]) ? 1 : 0, 
				'period'		=> $date != $last_date ? $date : false, 
				'delta'			=> $last_time ? "+".count_delta($item->date - $last_time)." " : 0, 
				'scheduled'		=> isset($item->orig_date) && abs($item->date - $item->orig_date) <= 60
			));
			
			$last_time = $item->date;
			$last_date = $date;
		}
		
		mk_page(array(
			'title' => 'Предложения постов', 
			'content' => Tpl::render("comments.html", array(
				'by_week'	=> [
					'items'	=> array_values($by_week), 
					'max'	=> $by_week_max
				], 
				'list'		=> $list, 
				'gid'		=> $gid, 
				'json'		=> json_encode($json, JSON_UNESCAPED_UNICODE), 
				'tabs'		=> switch_tabs([
					'url' => $url, 
					'param' => 'filter', 
					'tabs' => [
						'new'		=> 'Новые ('.count($res->suggests).')', 
						'accepted'	=> 'Принятые ('.$res->postponed_cnt.')', 
						'special'	=> 'Рекламные ('.count($res->specials).')', 
					], 
					'active' => $filter
				]), 
				
				'last_post_time'				=> $last_posted ? display_date($last_posted) : 'n/a', 
				'last_delayed_post_time'		=> $last_postponed ? display_date($last_postponed) : 'n/a', 
				
				'last_post_time_unix'			=> $last_posted, 
				'last_delayed_post_time_unix'	=> $last_postponed, 
				
				'postponed_link'		=> (string) (new Url("?"))->set(array(
					'gid'		=> $gid, 
					'filter'	=> 'accepted'
				)), 
				'back'					=> $_SERVER['REQUEST_URI'], 
				
				'comments'				=> $comments, 
				'filter'				=> $filter, 
				'from'					=> parse_time($comm['period_from']), 
				'to'					=> parse_time($comm['period_to']), 
				'interval'				=> parse_time($comm['interval']), 
				'success'				=> isset($_REQUEST['ok']), 
				'postponed_cnt'			=> $res->postponed_cnt, 
				'suggests_cnt'			=> $res->suggests_cnt
			))
		));
	break;
}

function add_queued_wall_post(&$out, $attachments, $text) {
	global $q, $gid, $comm;
	
	if (!isset($out['error'])) {
		$req = mysql_query("SELECT MAX(`fake_date`) FROM `vk_posts_queue`");
		$fake_date = max(time() + 3600 * 24 * 60, mysql_num_rows($req) ? mysql_result($req, 0) : 0) + 3600;
		
		$data = array(
			'owner_id'		=> -$gid, 
			'signed'		=> 0, 
			'message'		=> $text, 
			'attachments'	=> implode(",", $attachments), 
			'publish_date'	=> $fake_date
		);
		
		$res = $q->vkApi("wall.post", $data);
		if (($error = vk_api_error($res))) {
			$out['error'] = $error;
			$out['data'] = $data;
		} else {
			$out['link'] = 'https://vk.com/wall-'.$gid.'_'.$res->response->post_id;
			$out['post_id'] = $res->response->post_id;
			
			mysql_query("
				INSERT INTO `vk_posts_queue`
				SET
					`fake_date`	= $fake_date, 
					`group_id`	= $gid, 
					`id`		= ".(int) $res->response->post_id."
			") or die("ERROR: ".mysql_error());
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
			'name' => $user->id == 289678746 ? 'Выдрочка' : (isset($user->name) ? $user->name : $user->first_name." ".$user->last_name), 
			'preview' => $user->photo_50, 
			'link' => $link ? $link : "https://vk.com/".(isset($user->screen_name) && strlen($user->screen_name) ? $user->screen_name : 'id'.$user->id)
		)), 
		'avatar' => Tpl::render("widgets/vk_user_ava.html", array(
			'preview' => $user->photo_50
		))
	);
}
