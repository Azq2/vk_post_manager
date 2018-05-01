<?php
$type = array_val($_GET, 'type', '');
$access_token = array_val($_GET, 'access_token', '');
$refresh_token = array_val($_GET, 'refresh_token', '');
$expires = (int) array_val($_GET, 'expires', '');

if (!\Z\User::instance()->can('admin'))
	die("Доступ только админам!");

if ($type && $access_token) {
	if (!preg_match("/^[\w\d_]+$/i", $type))
		die('type: '.htmlspecialchars($type));
	
	Mysql::query("
		INSERT INTO `vk_oauth` SET
			`type`			= '".Mysql::escape($type)."', 
			`access_token`	= '".Mysql::escape($access_token)."', 
			`refresh_token`	= '".Mysql::escape($refresh_token)."', 
			`expires`		= $expires
		ON DUPLICATE KEY UPDATE
			`access_token`	= VALUES(`access_token`), 
			`refresh_token`	= VALUES(`refresh_token`), 
			`expires`		= VALUES(`expires`)
	");
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
