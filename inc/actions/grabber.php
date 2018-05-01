<?php
$sub_action = array_val($_GET, 'sa', '');
$sort = array_val($_REQUEST, 'sort', 'DESC');
$mode = array_val($_REQUEST, 'mode', 'external');
$content_filter = array_val($_REQUEST, 'content', 'pics');

$sources = [];
$req = Mysql::query("SELECT * FROM `vk_grabber_sources` WHERE group_id = $gid ORDER BY id DESC");
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
		$source_id = isset($_POST['source_id']) ? $_POST['source_id'] : '';
		$source_type = isset($_POST['source_type']) ? $_POST['source_type'] : '';
		$remote_id = isset($_POST['remote_id']) ? $_POST['remote_id'] : '';
		$restore = isset($_POST['restore']) ? $_POST['restore'] : '';
		
		$output = [];
		if (!\Z\User::instance()->can('user')) {
			$output['error'] = 'Гостевой доступ!';
		} elseif ($source_id && $source_type && $remote_id) {
			if ($restore) {
				Mysql::query("
					DELETE FROM `vk_grabber_blacklist` WHERE
						group_id = $gid AND
						source_id = '".Mysql::escape($source_id)."' AND
						source_type = '".Mysql::escape($source_type)."' AND
						remote_id = '".Mysql::escape($remote_id)."'
				");
			} else {
				Mysql::query("
					INSERT IGNORE INTO `vk_grabber_blacklist` SET
						group_id = $gid, 
						source_id = '".Mysql::escape($source_id)."', 
						source_type = '".Mysql::escape($source_type)."', 
						remote_id = '".Mysql::escape($remote_id)."'
				");
			}
			$output['success'] = true;
		} else {
			$output['error'] = 'wtf';
		}
		
		mk_ajax($output);
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
				$sources_hash[$s['type']][] = "'".Mysql::escape($s['id'])."'";
		}
		
		$sources_where = [];
		foreach ($sources_hash as $type => $ids)
			$sources_where[] = "(d.`source_type` = '".Mysql::escape($type)."' AND d.`source_id` IN (".implode(",", $ids)."))\n";
		
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
					$exclude[] = "'".Mysql::escape($t)."'";
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
			SELECT d.* FROM `vk_grabber_data_index` as `d`
				".($where ? "WHERE ".implode(" AND ", $where) : "")."
			$order
			LIMIT $O, $L
		";
		
		$req = Mysql::query($sql);
		if (!$req) {
			mk_ajax([
				'success' 	=> false, 
				'sql'		=> $sql
			]);
			exit;
		}
		
		$req2 = Mysql::query("
			SELECT COUNT(*) FROM `vk_grabber_data_index` as `d`
				".($where ? "WHERE ".implode(" AND ", $where) : "")."
			$order
		");
		$count = $req2->result();
		
		// Получаем массив id данных и 
		$meta = [];
		$items = [];
		while ($res = $req->fetch()) {
			$items[$res['data_id']] = 1;
			$meta[$res['data_id']] = $res;
		}
		$req->free();
		$req2->free();
		$time_list = microtime(true) - $time_list;
		
		$owners = [];
		$time_data = 0;
		
		if ($items) {
			// Получаем овнеров
			$req = Mysql::query("SELECT * FROM `vk_grabber_data_owners`");
			while ($res = $req->fetch())
				$owners[$res['id']] = $res;
			$req->free();
			
			$time_data = microtime(true);
			$req = Mysql::query("SELECT * FROM `vk_grabber_data` WHERE `id` IN (".implode(",", array_keys($items)).")");
			while ($res = $req->fetch()) {
				$res = array_merge($res, $meta[$res['id']]);
				$owner = $owners[$res['source_type'].'_'.$res['owner']];
				$res['owner_name'] = $owner['name'];
				$res['owner_url'] = $owner['url'];
				$res['owner_avatar'] = $owner['avatar'];
				$res['attaches'] = unserialize(gzinflate($res['attaches']));
				$items[$res['id']] = $res;
			}
			$req->free();
			$time_data = microtime(true) - $time_data;
		}
		
		mk_ajax(['success' => true, 'sql' => $sql, 'owners' => $owners, 'items' => array_values($items), 'total' => (int) $count, 
					'time_data' => $time_data, 'time_list' => $time_list]);
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
		$source_type = array_val($_GET, 'type', '');
		
		if (\Z\User::instance()->can('user')) {
			Mysql::query("UPDATE `vk_grabber_sources` SET `enabled` = ".($sub_action == 'on' ? 1 : 0)."
				WHERE
					id = '".Mysql::escape($source_id)."' AND
					type = '".Mysql::escape($source_type)."' AND 
					group_id = $gid");
		}
		
		header("Location: ".Url::mk('?')->set('a', 'grabber')->set('gid', $gid)->url());
		exit;
	break;
	
	case "delete":
		$source_id = array_val($_GET, 'id', '');
		$source_type = array_val($_GET, 'type', '');
		
		if (\Z\User::instance()->can('user')) {
			Mysql::query("DELETE FROM `vk_grabber_sources`
				WHERE
					id = '".Mysql::escape($source_id)."' AND
					type = '".Mysql::escape($source_type)."' AND 
					group_id = $gid");
		}
		
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
		
		if (!\Z\User::instance()->can('user')) {
			$view['form_error'] = 'Гостевой доступ!';
		} elseif ($url == '') {
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
			Mysql::query("INSERT INTO `vk_grabber_sources`
				SET
					id = '".Mysql::escape($source_id)."', 
					name = '".Mysql::escape($source_name)."', 
					type = '".Mysql::escape($source_type)."', 
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
			->href(), 
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
