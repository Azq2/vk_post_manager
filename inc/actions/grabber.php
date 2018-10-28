<?php
$sub_action = array_val($_GET, 'sa', '');
$sort = array_val($_REQUEST, 'sort', 'DESC');
$mode = array_val($_REQUEST, 'mode', 'external');
$content_filter = array_val($_REQUEST, 'content', 'pics');
$include = isset($_REQUEST['include']) && is_array($_REQUEST['include']) ? $_REQUEST['include'] : array();
$exclude = isset($_REQUEST['exclude']) && is_array($_REQUEST['exclude']) ? $_REQUEST['exclude'] : array();

$sources = [];
$req = Mysql::query("
	SELECT ss.*, s.source_type, s.source_id as source_our_id FROM `vk_grabber_selected_sources` as ss
	INNER JOIN vk_grabber_sources as s ON s.id = ss.source_id
	WHERE ss.group_id = ? ORDER BY s.id DESC
", $gid);
while ($res = $req->fetch())
	$sources[$res['id']] = $res;

$view = [
	'sa'			=> $sub_action, 
	'form_action'	=> Url::mk()->set('sa', 'add')->href(), 
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
		$post_id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
		$restore = isset($_POST['restore']) ? (int) $_POST['restore'] : 0;
		
		$post = Mysql::query("SELECT * FROM vk_grabber_data_index WHERE id = ?", $post_id)
			->fetch();
		
		$output = [];
		if (!\Z\User::instance()->can('user')) {
			$output['error'] = 'Гостевой доступ!';
		} else if ($post) {
			if ($restore) {
				Mysql::query("
					DELETE FROM `vk_grabber_blacklist` WHERE
						group_id = ? AND
						source_type = ? AND
						remote_id = ?
				", $gid, $post['source_type'], $post['remote_id']);
			} else {
				Mysql::query("
					INSERT IGNORE INTO `vk_grabber_blacklist` SET
						group_id = ?, 
						source_type = ?, 
						remote_id = ?, 
						time = ?
				", $gid, $post['source_type'], $post['remote_id'], time());
			}
		} else {
			$output['error'] = 'Пост не найден :(';
		}
		
		mk_ajax($output);
		exit;
	break;
	
	case "load":
		$O = isset($_REQUEST['O']) ? (int) $_REQUEST['O'] : 0;
		$L = isset($_REQUEST['L']) ? (int) $_REQUEST['L'] : 10;
		
		$where = [];
		$order = '';
		
		$sources_ids = [];
		foreach ($sources as $s) {
			if ($s['enabled']) {
				$key = \Z\Smm\Grabber::$type2name[$s['source_type']].'_'.$s['source_our_id'];
				if ($include && !in_array($key, $include))
					continue;
				if ($exclude && in_array($key, $exclude))
					continue;
				
				$sources_ids[$s['source_id']] = 1;
			}
		}
		$sources_ids = array_keys($sources_ids);
		
		// Фильтр по типу контента
		if ($content_filter == 'pics')
			$where[] = 'post_type > 0';
		elseif ($content_filter == 'only_gif')
			$where[] = 'post_type IN (1, 3)';
		elseif ($content_filter == 'without_gif')
			$where[] = 'post_type IN (2)';
		
		if ($sort == 'DESC') {
			$order = 'ORDER BY time DESC';
		} else if ($sort == 'ASC') {
			$order = 'ORDER BY time ASC';
		} else if ($sort == 'RAND') {
			if (isset($_REQUEST['exclude_posts'])) {
				$exclude = [];
				foreach (explode(",", $_REQUEST['exclude_posts']) as $t) {
					if ($t > 0)
						$exclude[] = (int) $t;
				}
				
				if ($exclude)
					$where[] = 'id NOT ('.implode(' OR ', $exclude).')';
			}
			$order = 'ORDER BY RAND()';
		} else if ($sort == 'LIKES') {
			$order = 'ORDER BY `likes` DESC';
		} else if ($sort == 'REPOSTS') {
			$order = 'ORDER BY `reposts` DESC';
		} else if ($sort == 'COMMENTS') {
			$order = 'ORDER BY `comments` DESC';
		}
		
		if ($mode == 'internal') {
			$req = Mysql::query('SELECT id FROM vk_grabber_sources WHERE source_type = ? AND source_id = ?', \Z\Smm\Grabber::SOURCE_VK, -$gid);
			$source_id = $req->result();
			
			// Из своего сообщества
			$where[] = $source_id ? 'source_id = '.$source_id : '0';
		} else {
			// Из чужих сообществ
			$where[] = $sources_ids ? 'source_id IN ('.implode(",", $sources_ids).')' : '0';
		}
		
		$time_list = microtime(true);
		
		$sql = "
			SELECT ".($sort == "RAND" ? "" : "SQL_CALC_FOUND_ROWS")." id FROM `vk_grabber_data_index` as `d`
				".($where ? "WHERE ".implode(" AND ", $where) : "")."
			$order
			".($sort == "RAND" ? "LIMIT $L" : "LIMIT $O, $L")."
		";
		
		$req = Mysql::query($sql);
		if (!$req) {
			mk_ajax([
				'success' 	=> false, 
				'sql'		=> $sql
			]);
			exit;
		}
		
		$posts_ids = [];
		foreach ($req->fetchAll() as $row)
			$posts_ids[] = $row['id'];
		
		$time_list = microtime(true) - $time_list;
		
		$time_count = microtime(true);
		
		if ($sort == "RAND") {
			$count = 0;
		} else {
			$req2 = Mysql::query("SELECT FOUND_ROWS()");
			$count = $req2->result();
		}
		
		$time_count = microtime(true) - $time_count;
		
		// Получаем массив id данных и 
		$meta = [];
		$items = [];
		
		if ($posts_ids) {
			$req = Mysql::query("SELECT * FROM `vk_grabber_data_index` WHERE id IN (?)", $posts_ids);
			while ($res = $req->fetch())
				$meta[$res['data_id']] = $res;
			$req->free();
		}
		
		$owners = [];
		$blacklist_ids = [];
		$time_data = microtime(true);
		
		if ($meta) {
			// Получаем овнеров
			$req = Mysql::query("SELECT * FROM `vk_grabber_data_owners`");
			while ($res = $req->fetch())
				$owners[$res['id']] = $res;
			$req->free();
			
			$req = Mysql::query("SELECT * FROM `vk_grabber_data` WHERE `id` IN (".implode(",", array_keys($meta)).")");
			while ($post_data = $req->fetch()) {
				$post = $meta[$post_data['id']];
				$source_type = \Z\Smm\Grabber::$type2name[$post['source_type']];
				$owner = $owners[$post['source_type'].'_'.$post_data['owner']];
				
				if (!isset($blacklist_ids[$post['source_type']]))
					$blacklist_ids[$post['source_type']] = [];
				$blacklist_ids[$post['source_type']][] = $post['remote_id'];
				
				$items[$post['id']] = [
					'id'				=> $post['id'], 
					'remote_id'			=> $post['remote_id'], 
					'source_id'			=> $post['source_id'], 
					'source_type'		=> $source_type, 
					'source_type_id'	=> $post['source_type'], 
					'time'				=> $post['time'], 
					'likes'				=> $post['likes'], 
					'comments'			=> $post['comments'], 
					'reposts'			=> $post['reposts'], 
					'gifs_cnt'			=> $post['gifs_cnt'], 
					'images_cnt'		=> $post['images_cnt'], 
					'text'				=> $post_data['text'], 
					'owner_name'		=> $owner['name'], 
					'owner_url'			=> $owner['url'], 
					'owner_avatar'		=> $owner['avatar'], 
					'attaches'			=> unserialize(gzinflate($post_data['attaches']))
				];
			}
			$req->free();
		}
		
		$time_data = microtime(true) - $time_data;
		
		$time_blacklist = microtime(true);
		
		$blacklist_where = [];
		foreach ($blacklist_ids as $source_type => $ids) {
			$ids = array_map(function ($v) {
				return Mysql::value($v);
			}, $ids);
			$blacklist_where[] = "(source_type = ".Mysql::value($source_type)." AND remote_id IN (".implode(",", $ids)."))";
		}
		
		$blacklist_filtered = [];
		
		if ($blacklist_where) {
			$req = Mysql::query("SELECT * FROM vk_grabber_blacklist WHERE group_id = $gid AND (".implode(" OR ", $blacklist_where).")");
			foreach ($req->fetchAll() as $row)
				$blacklist_filtered[$row['source_type'].":".$row['remote_id']] = 1;
		}
		
		$items_array = array();
		foreach ($posts_ids as $post_id) {
			$item = $items[$post_id];
			if (!isset($blacklist_filtered[$item['source_type_id'].":".$item['remote_id']]))
				$items_array[] = $item;
		}
		
		$time_blacklist = microtime(true) - $time_blacklist;
		
		mk_ajax(['success' => true, 'sql' => $sql, 'items' => $items_array, 'total' => (int) $count, 
					'time_data' => $time_data, 'time_list' => $time_list, 'time_count' => $time_count, 'time_blacklist' => $time_blacklist, 'blacklist_filtered' => count($blacklist_filtered)]);
		exit;
	break;
	
	case "queue_done":
		$id = isset($_REQUEST['id']) ? preg_replace("/[^a-f0-9]/", "", $_REQUEST['id']) : '';
		$out = [];
		if (!\Z\User::instance()->can('user')) {
			$out['error'] = 'Гостевой доступ!';
		} elseif (file_exists(H.'../tmp/post_queue/'.$id)) {
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
		
		$images = isset($_POST['images']) ? $_POST['images'] : [];
		$documents = isset($_POST['documents']) ? $_POST['documents'] : [];
		
		if (!\Z\User::instance()->can('user')) {
			$out['error'] = 'Гостевой доступ!';
		} elseif ($images || $documents) {
			$msg = json_encode([
				'images'	=> $images, 
				'documents'	=> $documents, 
				'text'		=> $text, 
				'gid'		=> $gid
			]);
			
			file_put_contents(H.'../tmp/post_queue/'.md5($msg), $msg);
			chmod(H.'../tmp/post_queue/'.md5($msg), 0666);
			
			$out['id'] = md5($msg);
			$out['success'] = true;
		} else {
			// Сразу добавляем пост, если у него нет аттачей
			add_queued_wall_post($out, [], $text);
		}
		
		mk_ajax($out);
		exit;
	break;
	
	case "on":
	case "off":
		$source_id = array_val($_GET, 'id', '');
		
		if (\Z\User::instance()->can('user')) {
			Mysql::query("UPDATE `vk_grabber_selected_sources` SET `enabled` = ".($sub_action == 'on' ? 1 : 0)."
				WHERE source_id = '".Mysql::escape($source_id)."' AND group_id = $gid");
		}
		
		header("Location: ".Url::mk('?')->set('a', 'grabber')->set('gid', $gid)->url());
		exit;
	break;
	
	case "delete":
		$source_id = array_val($_GET, 'id', '');
		$source_type = array_val($_GET, 'type', '');
		
		if (\Z\User::instance()->can('user')) {
			Mysql::query("DELETE FROM `vk_grabber_selected_sources`
				WHERE source_id = '".Mysql::escape($source_id)."' AND group_id = $gid");
		}
		
		header("Location: ".Url::mk('?')->set('a', 'grabber')->set('gid', $gid)->url());
		exit;
	break;
	
	case "add":
		$url = trim(array_val($_POST, 'url', ''));
		
		$view['form_url'] = $url;
		
		// Инстаграмм
		if ($url && $url[0] == '#')
			$url = "https://www.instagram.com/explore/tags/".urlencode(substr($url, 1))."/";
		
		if (substr($url, 0, 4) != "http")
			$url = "https://$url";
		
		$parts = parse_url($url);
		
		$source_id = false;
		$source_type = false;
		
		if (!\Z\User::instance()->can('user')) {
			$view['form_error'] = 'Гостевой доступ!';
		} elseif ($url == '') {
			$view['form_error'] = 'Сейчас бы тыкать на кнопки ничего не введя.';
		} elseif (isset($parts['host']) && isset($parts['path'])) {
			$type = '';
			if (preg_match("/ok.ru|odnoklassniki.ru/i", $parts['host'])) {
				$data = @file_get_contents("https://m.ok.ru".$parts['path']);
				if (preg_match("/groupId=(\d+)/i", $data, $m)) {
					$source_id = $m[1];
					if (preg_match('/(group_name|itemprop="name")[^>]*>(.*?)</sim', $data, $m)) {
						$source_type = \Z\Smm\Grabber::SOURCE_OK;
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
					$source_type = \Z\Smm\Grabber::SOURCE_VK;
					$source_name = $res->response[0]->name;
				} else if (isset($res->error)) {
					$view['form_error'] = $res->error->error_msg;
				} else {
					$view['form_error'] = 'VK API вернул странную дичь =\\';
				}
			} elseif (preg_match("/instagram.com/i", $parts['host'])) {
				$tag_name = substr($parts['path'], 1);
				if (preg_match("/tags\/([^\?\/]+)/i", $tag_name, $m))
					$tag_name = urldecode($m[1]);
				
				$data = @file_get_contents("https://www.instagram.com/explore/tags/".urlencode($tag_name)."/?__a=1");
				if (json_decode($data)) {
					$source_id = $tag_name;
					$source_type = \Z\Smm\Grabber::SOURCE_INSTAGRAM;
					$source_name = "#$tag_name";
				} else if (isset($res->error)) {
					$view['form_error'] = $res->error->error_msg;
				} else {
					$view['form_error'] = 'Instagram вернул странную дичь или тег не найден =\\ (тег: '.$tag_name.', ссылка: '.$url.')';
				}
			} else {
				$view['form_error'] = '<b>'.$parts['host'].'</b> - чё за сосайт? Не знаю такой!';
			}
		} else {
			$view['form_error'] = 'Чё за дичь!? =\ Не очень похоже на URL.';
		}
		
		if ($source_type !== false) {
			Mysql::query("INSERT IGNORE INTO `vk_grabber_sources` SET source_type = ?, source_id = ?", $source_type, $source_id);
			
			$source = Mysql::query("SELECT * FROM vk_grabber_sources WHERE source_type = ? AND source_id = ?", $source_type, $source_id)
				->fetchAssoc();
			
			Mysql::query("INSERT INTO `vk_grabber_selected_sources`
				SET
					source_id = ?, 
					group_id = ?, 
					name = ?, 
					enabled = 1
				ON DUPLICATE KEY UPDATE
					name = VALUES(name)", $source['id'], $gid, $source_name);
			
			header("Location: ".Url::mk('?')->set('a', 'grabber')->set('gid', $gid)->url());
			exit;
		}
	break;
}

$view['include'] = $include;
$view['exclude'] = $exclude;

$view['mode'] = $mode;
$view['sort'] = $sort;
$view['content_filter'] = $content_filter;
$view['sources'] = [];
$view['sources_ids'] = [];

foreach ($sources as $s) {
	switch ($s['source_type']) {
		case \Z\Smm\Grabber::SOURCE_OK:
			$url = 'https://ok.ru/group/'.$s['source_id'];
			$icon = 'https://ok.ru/favicon.ico';
		break;
		
		case \Z\Smm\Grabber::SOURCE_VK:
			$url = 'https://vk.com/public'.(-$s['source_id']);
			$icon = 'https://vk.com/favicon.ico';
		break;
		
		case \Z\Smm\Grabber::SOURCE_INSTAGRAM:
			$url = 'https://www.instagram.com/explore/tags/'.urlencode($s['source_id']).'/';
			$icon = 'https://www.instagram.com/static/images/ico/favicon.ico/36b3ee2d91ed.ico';
		break;
	}
	
	$view['sources'][\Z\Smm\Grabber::$type2name[$s['source_type']].'_'.$s['source_our_id']] = [
		'id'			=> $s['source_our_id'],
		'enabled'		=> $s['enabled'], 
		'name'			=> $s['name'], 
		'type'			=> \Z\Smm\Grabber::$type2name[$s['source_type']], 
		'url'			=> $url, 
		'icon'			=> $icon, 
		'on_url'		=> Url::mk()
			->set('sa', 'on')
			->set('id', $s['source_id']), 
		'off_url'		=> Url::mk()
			->set('sa', 'off')
			->set('id', $s['source_id']), 
		'delete_url'	=> Url::mk()
			->set('sa', 'delete')
			->set('id', $s['source_id'])
			->href(), 
	];
	
	if ($s['enabled'])
		$view['sources_ids'][] = $s['source_id'];
}

if ($mode == 'internal')
	$view['sources_ids'] = [[\Z\Smm\Grabber::SOURCE_VK, $gid]];

mk_page(array(
	'title' => 'Граббер корованов 2000', 
	'content' => Tpl::render("grabber.html", $view)
));
