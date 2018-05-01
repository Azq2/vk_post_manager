<?php
	require_once "config.php";
	
	set_time_limit(0);
	ini_set('memory_limit', '1G');
	@mysql_connect(DB_HOST, DB_USER, DB_PASSWORD);
	mysql_select_db(DB_NAME);
	mysql_set_charset('UTF-8');
	date_default_timezone_set('Europe/Moscow');

	function vk($method, $args = array()) {
		static $ch;
		
		if (!$ch) {
			$ch = curl_init(); 
			curl_setopt_array($ch, array(
				CURLOPT_RETURNTRANSFER => true, 
				CURLOPT_ENCODING       => "gzip", 
				CURLOPT_COOKIE         => '', 
				CURLOPT_HEADER         => false, 
				CURLOPT_USERAGENT      => 'Ня :3'
			));
		}
		
		$sig = '';
		$args['v'] = '5.33';
		$args['lang'] = 'ru';
		curl_setopt($ch, CURLOPT_URL, "https://api.vk.com/method/".$method);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($args, '', '&'));
		return json_decode(curl_exec($ch));
	}
	
	function bulk_insert($query, $values, $limit) {
		$offset = 0;
		do {
			$tmp = array_slice($values, $offset, $limit);
			if (count($tmp)) {
				mq($query." ".implode(", ", $tmp));
			} else {
				break;
			}
			$offset += $limit;
		} while (true);
	}
	
	function mq($q) {
		$req = mysql_query($q);
		if ($req === false)
			throw Exception(mysql_error());
		return $req;
	}
