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
$action = isset($_REQUEST['a']) ? preg_replace("/[^a-z0-9\/_-]+/i", "", $_REQUEST['a']) : 'index';

$curl = new Url($_SERVER['REQUEST_URI']);

$comms = [];
$req = Mysql::query("SELECT * FROM `vk_groups` ORDER BY pos ASC");
while ($res = $req->fetch())
	$comms[$res['id']] = $res;

$gid = (int) array_val($_REQUEST, 'gid', reset($comms)['id']);
if (!isset($comms[$gid])) {
	header("Location: ?");
	exit;
}
$comm = $comms[$gid];

if (!preg_match("/^[a-z_\/-]+$/si", $action) || !file_exists(dirname(__FILE__)."/inc/actions/$action.php"))
	$action = "index";

require_once dirname(__FILE__)."/inc/actions/$action.php";

function add_queued_wall_post(&$out, $attachments, $text) {
	global $q, $gid, $comm;
	
	if (!isset($out['error'])) {
		$req = Mysql::query("SELECT MAX(`fake_date`) FROM `vk_posts_queue`");
		$fake_date = max(time() + 3600 * 24 * 60, $req->num() ? $req->result() : 0) + 3600;
		
		$data = array(
			'owner_id'		=> -$gid, 
			'signed'		=> 0, 
			'message'		=> $text, 
			'attachments'	=> implode(",", $attachments), 
			'publish_date'	=> $fake_date
		);
		
		$res = $q->vkApi("wall.post", $data);
		if (($error = vk_api_error($res))) {
			$out['error'] = $error." fake_date=".date("Y-m-d H:i:s", $fake_date);
			$out['data'] = $data;
		} else {
			$out['link'] = 'https://vk.com/wall-'.$gid.'_'.$res->response->post_id;
			$out['post_id'] = $res->response->post_id;
			
			Mysql::query("
				INSERT INTO `vk_posts_queue`
				SET
					`fake_date`	= $fake_date, 
					`group_id`	= $gid, 
					`id`		= ".(int) $res->response->post_id."
			");
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
	
	$smm_money = Mysql::query("SELECT * FROM `vk_smm_money` WHERE `group_id` = ?", $gid)
		->fetchObject();
	
	$revision = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(H."../static/")) as $file) {
		if (preg_match("/\.js$/i", $file) && is_file($file))
			$revision = max($revision, filemtime($file));
    }
	
	$def = [
		'revision'	=> $revision, 
		'smm_money' => number_format($smm_money->money, 2, ',', ' '), 
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
//				'users_top'			=> 'Топ юзверей', 
//				'users'				=> 'Юзвери', 
//				'activity'			=> 'Активность', 
//				'returns'			=> 'Возвраты', 
				'join_visual'		=> 'График вступления', 
				'game/catlist'		=> 'Котогочи'
			], 
			'active' => isset($_REQUEST['a']) ? $_REQUEST['a'] : 'index'
		]), 
		'mysql'	=> isset($_COOKIE['debug']) ? Mysql::getQueriesList() : []
	];
	header("Content-Type: text/html; charset=UTF-8");
	echo Tpl::render("main.html", array_merge($def, $args));
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
