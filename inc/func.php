<?php
	if (!function_exists('mysql_fetch_assoc')) {
		static $_mysql_link;
		function mysql_pconnect($host, $user, $passwd) {
			global $_mysql_link;
			return ($_mysql_link = mysqli_connect("p:".$host, $user, $passwd));
		}
		
		function mysql_connect($host, $user, $passwd) {
			global $_mysql_link;
			return ($_mysql_link = mysqli_connect("p:".$host, $user, $passwd));
		}
		
		function mysql_select_db($db) {
			global $_mysql_link;
			return mysqli_select_db($_mysql_link, $db);
		}
		
		function mysql_set_charset($charset) {
			global $_mysql_link;
			return mysqli_set_charset($_mysql_link, $charset);
		}
		
		function mysql_query($query) {
			global $_mysql_link;
			$req = mysqli_query($_mysql_link, $query);
			if ($req === false)
				die("error($query): ".mysql_error()."\n");
			return $req;
		}
		
		function mysql_error() {
			global $_mysql_link;
			return mysqli_error($_mysql_link);
		}
		
		function mysql_fetch_assoc($query) {
			return mysqli_fetch_assoc($query);
		}
		
		function mysql_num_rows($query) {
			return mysqli_num_rows($query);
		}
		
		function mysql_fetch_row($query) {
			return mysqli_fetch_row($query);
		}
		
		function mysql_fetch_array($query) {
			return mysqli_fetch_array($query);
		}
		
		function mysql_result($query) {
			$row = mysqli_fetch_row($query);
			return $row[0];
		}
		
		function mysql_real_escape_string($query) {
			global $_mysql_link;
			return mysqli_real_escape_string($_mysql_link, $query);
		}
		
		function mysql_free_result($query) {
			return mysqli_free_result($query);
		}
		
		function mysql_num_fields($query) {
			return mysqli_num_fields($query);
		}
		
		function mysql_field_name($query, $i) {
			$res = mysqli_fetch_fields($query);
			return $res[$i]->name;
		}
	}
	
	function switch_tabs($tabs, $active, $class = 'tab', $active_class = 'tab tab-active') {
		$out = '';
		$total = count($tabs);
		foreach ($tabs as $id => $tab) {
			$out .= '<a href="'.htmlspecialchars(!isset($tab[1]) ? '#' : $tab[1]).'" class="'.
				(strcmp($active, $id) == 0 ? $active_class : $class).'" tab-id="'.$id.'">'.$tab[0].'</a>';
			if (--$total)
				$out .= ' | ';
		}
		return $out;
	}
	
	function get_day_start($time = 0) {
		$time = !$time ? time() : $time;
		return mktime(0, 0, 0, date("m", $time), date("d", $time), date("Y", $time));
	}
	
	function array_val($array, $key, $val = NULL) {
		return array_key_exists($key, $array) ? $array[$key] : $val;
	}
	
	function e($s) {
		return mysql_real_escape_string($s);
	}
	
	function request($ch, $url, $data = NULL) {
		curl_setopt($ch, CURLOPT_URL, $url);
		if ($data !== NULL) {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		}
		$res = curl_exec($ch);
		curl_setopt($ch, CURLOPT_POST, false);
		return $res;
	}
	
	function display_date($time_unix, $full = false, $show_time = true) {
		static $to_russian_week = [6, 0, 1, 2, 3, 4, 5]; 
		static $week_names = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс']; 
		static $month_list = [
			1  => 'Января', 
			2  => 'Февраля', 
			3  => 'Марта', 
			4  => 'Апреля', 
			5  => 'Мая', 
			6  => 'Июня', 
			7  => 'Июля', 
			8  => 'Августа', 
			9  => 'Сентября', 
			10 => 'Октября', 
			11 => 'Ноября', 
			12 => 'Декабря', 
			13 => 'Мартабря', 
		];
		static $month_list_short = [
			1  => 'янв', 
			2  => 'фев', 
			3  => 'мар', 
			4  => 'апр', 
			5  => 'мая', 
			6  => 'июн', 
			7  => 'июл', 
			8  => 'авг', 
			9  => 'сен', 
			10 => 'окт', 
			11 => 'ноя', 
			12 => 'дек', 
		];
		
		$curr_time = localtime(time()); 
		$time = localtime($time_unix); 
		
		if ($full)
			return date("d ".$month_list[$time[4] + 1]." Y в H:i:s", $time_unix); 
		
		if (time() >= $time_unix) {
			// Сегодня
			if ($time[3] == $curr_time[3] && $time[4] == $curr_time[4] && $time[5] == $curr_time[5])
				return date("H:i:s", $time_unix); 
			
			// Вчера
			$yesterday = mktime(0, 0, 0, $curr_time[4] + 1, $curr_time[3] - 1, 1900 + $curr_time[5]); 
			if ($yesterday <= $time_unix)
				return "вчера ".date("H:i:s", $time_unix); 
			
			// На этой неделе
			$start_week = mktime(0, 0, 0, $curr_time[4] + 1, $curr_time[3] - $to_russian_week[$curr_time[6]], 1900 + $curr_time[5]); 
			if ($start_week <= $time_unix)
				return $week_names[$to_russian_week[$time[6]]].", ".date("H:i:s", $time_unix); 
		}
		
		// В этом году
		if ($curr_time[5] == $time[5])
			return $time[3]." ".$month_list_short[$time[4] + 1]." ".date("H:i:s", $time_unix); 
		
		// Хрен знает когда
		return $time[3]." ".$month_list_short[$time[4] + 1]." ".(1900 + $time[5]); 
	}
	
	function count_time($time) {
		$out = [];
		
		$years = floor($time / (3600 * 24 * 365));
		if ($years > 0) {
			$time -= $years * 3600 * 24 * 365; 
			$out[] = $years." год"; 
		}
		
		$months = floor($time / (3600 * 24 * 30));
		if ($months > 0) {
			$time -= $months * 3600 * 24 * 30; 
			$out[] = $months." мес"; 
		}
		
		$days = floor($time / (3600 * 24));
		if ($days > 0) {
			$time -= $days * 3600 * 24; 
			$out[] = $days." дн"; 
		}
		
		$hours = floor($time / 3600); 
		if ($hours > 0 || $days > 0) {
			$time -= $hours * 3600; 
			$out[] = $hours." ч"; 
		}
		
		$minutes = floor($time / 60); 
		if ($minutes > 0 || $hours > 0 || $days > 0) {
			$time -= $minutes * 60; 
			$out[] = $minutes." м"; 
		}
		
		if (empty($out) || $time > 0) {
			$seconds = $time; 
			$out[] = $seconds."с"; 
		}
		return implode(', ', array_slice($out, 0, 2)); 
	}
	
	function vk($method, $args = array()) {
		static $ch;
		
		if (!$ch) {
			$ch = curl_init(); 
			curl_setopt_array($ch, array(
				CURLOPT_RETURNTRANSFER => true, 
				CURLOPT_ENCODING       => "gzip", 
				CURLOPT_COOKIE         => '', 
				CURLOPT_HEADER         => false, 
				CURLOPT_USERAGENT      => 'Ня :3', 
				CURLOPT_SSL_VERIFYPEER => true, 
				CURLOPT_FORBID_REUSE   => false
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
	
	function mk_page($args) {
		$def = array();
		header("Content-Type: text/html; charset=UTF-8");
		echo Tpl::render("main.html", $def + $args);
	}
	
	class Tpl {
		private static $globals = array();
		public static function setGlobals($v) {
			self::$globals = $v;
		}
		public static function render($__file, $args = array()) {
			extract(self::$globals);
			extract($args);
			ob_start();
			include H."tpl/".$__file.".php";
			return ob_get_clean();
		}
	}
	
	class Url implements \ArrayAccess, \IteratorAggregate {
		public $scheme = '';
		public $host = '';
		public $port = 80;
		public $user = '';
		public $password = '';
		public $path = '';
		public $query = [];
		public $fragment = '';
		
		public function __construct($url = '', $local = true) {
			$parts = parse_url($url);
			$this->scheme = array_val($parts, 'scheme', 'http');
			$this->host = array_val($parts, 'host', '');
			$this->port = array_val($parts, 'port', 80);
			$this->user = array_val($parts, 'user', '');
			$this->password = array_val($parts, 'password', '');
			$this->path = array_val($parts, 'path', '');
			$this->fragment = array_val($parts, 'fragment', '');
			$this->query = isset($parts['query']) ? self::parseArgs($parts['query']) : [];
		}
		
		public function url($xhtml = true, $separator = ';', $escape = true) {
			$url = '';
			if ($this->scheme && $this->host) {
				$url .= $this->scheme.'://';
				if ($this->user && $this->password)
					$url .= $this->user.':'.$this->password.'@';
				$url .= $this->host;
			}
			$url .= $this->path;
			if ($this->query) {
				$i = 0;
				foreach ($this->query as $k => $v) {
					if (!is_null($v) && $k != "#")
						$url .= (!$i-- ? '?' : $separator).$k.'='.$v;
				}
				if (!$this->fragment && isset($this->query['#']))
					$this->fragment = $this->query['#'];
			}
			if ($this->fragment)
				$url .= '#'.$this->fragment;
			return $xhtml ? htmlspecialchars($url, true) : $url;
		}
		
		public function getQuery() {
			return $this->query;
		}
		
		public function __toString() {
			return $this->url();
		}
		
		public function get($key) {
			return isset($this->query[$key]) ? $this->query[$key] : NULL;
		}
		
		public function set($key, $value = NULL) {
			if (is_array($key)) {
				$this->query += $key;
			} else {
				$this->query[$key] = $value;
			}
			return $this;
		}
		
		public function offsetSet($key, $value) {
			return $this->query[$key] = $value;
		}
		
		public function offsetExists($key) {
			return isset($this->query[$key]);
		}
		
		public function offsetUnset($key) {
			unset($this->query[$key]);
		}
		
		public function offsetGet($key) {
			return isset($this->query[$key]) ? $this->query[$key] : NULL;
		}
		
		public function getIterator() {
			return new \ArrayIterator($this->query);
		}
		
		public static function parseArgs($args) {
			$separator = '&';
			if (strpos($args, '&amp;'))
				$separator = '&amp;';
			$args_array = [];
			$pairs = explode($separator, $args);
			foreach ($pairs as $pair) {
				$data = explode('=', $pair, 2);
				if (!empty($data[0]))
					$args_array[urldecode($data[0])] = isset($data[1]) ? urldecode($data[1]) : '';
			}
			return $args_array;
		}
	}

	function vk_wall_post($q, $message, $files) {
		$res = $q->vkApi("photos.getWallUploadServer", [
			"group_id" => VK_GROUP_ID
		]);
		if (isset($res->response, $res->response->upload_url)) {
			$files_payload = []; $i = 0;
			foreach ($files as $file)
				$files_payload['file'.($i++)] = '@'.$file;
			$res = json_decode($q->exec($res->response->upload_url, $files_payload)->body, true);
			$res = $q->vkApi("photos.saveWallPhoto", array_merge($res, [
				"user_id" => VK_USER_ID, 
				"group_id" => VK_GROUP_ID
			]));
			if (isset($res->response)) {
				$wall_files = array();
				foreach ($res->response as $r)
					$wall_files[] = "photo".$r->owner_id."_".$r->id;
				$res = $q->vkApi("wall.post", [
					'owner_id' => -VK_GROUP_ID, 
					'from_group' => 1, 
					'message' => $message, 
					'attachments' => implode(",", $wall_files)
				]);
				if (isset($res->response, $res->response->post_id))
					return $res->response->post_id;
			}
		}
		var_dump($res);
		return false;
	}
	
	class Http {
		public $ch;
		public $users;
		
		public $last_http_code = 0;
		public $last_http_redirect = '';
		
		const SPACES_API_HOST = "http://spaces.ru";
		
		public function __construct() {
			$this->ch = curl_init(); 
			curl_setopt_array($this->ch, array(
				CURLOPT_RETURNTRANSFER => true, 
				CURLOPT_ENCODING       => "gzip", 
				CURLOPT_COOKIE         => '', 
				CURLOPT_HEADER         => true
			));
		}
		
		public function setUsers($users) {
			$this->users = $users;
			return $this;
		}
		
		public function dumpLastReqState() {
			return $this->last_http_redirect ?
				$this->last_http_code." [".$this->last_http_redirect."]" : $this->last_http_code;
		}
		
		public function exec($url, $post = array(), $xhr = false) {
			if ($xhr) {
				curl_setopt($this->ch, CURLOPT_HTTPHEADER, array(
					'X-Requested-With' => "XMLHttpRequest"
				));
			}
			
			curl_setopt($this->ch, CURLOPT_USERAGENT, "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/34.0.1847.132 Safari/537.36");
			curl_setopt($this->ch, CURLOPT_URL, $url);
			if (is_array($post)) {
				curl_setopt($this->ch, CURLOPT_POST, true);
				@curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post);
			}
			
			$res = curl_exec($this->ch);
			if (is_array($post))
				curl_setopt($this->ch, CURLOPT_POST, false);
			$headers_size = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);
			
			$this->last_http_code = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
			$this->last_http_redirect = null;
			if ($this->last_http_code == 301 || $this->last_http_code == 302) {
				$headers = substr($res, 0, $headers_size);
				if (preg_match("/Location: (.*?)\n/i", $headers, $m))
					$this->last_http_redirect = $m[1];
			}
			
			if ($xhr) {
				curl_setopt($this->ch, CURLOPT_HTTPHEADER, array());
			}
			
			return (object) array(
				'code' => $this->last_http_code, 
				'redirect' => $this->last_http_redirect, 
				'headers' => substr($res, 0, $headers_size), 
				'body' => substr($res, $headers_size)
			);
		}
		
		public function vkApi($method, $args = array(), $open = false) {
			$sig = '';
			$args['v'] = '5.33';
			$args['lang'] = 'ru';
			$args['access_token'] = VK_USER_ACCESS_TOKEN;
			
			if (isset($_REQUEST['vk_captcha_key']))
				$args['captcha_key'] = $_REQUEST['vk_captcha_key'];
			if (isset($_REQUEST['vk_captcha_sid']))
				$args['captcha_sid'] = $_REQUEST['vk_captcha_sid'];
			
			/*
			echo "[vk] $method".http_build_query($args);
			$json = json_decode(shell_exec('curl '.escapeshellarg("https://api.vk.com/$method?".http_build_query($args))));
			echo sprintf(" %.02f\n", $t);
			return $json;
			*/
	//		echo "[vk] $method".http_build_query($args);
			$t = microtime(true);
			$res = $this->exec("https://api.vk.com/method/".$method, $args, true);
			$t = microtime(true) - $t;
	//		echo sprintf(" %.02f\n", $t);
			return json_decode($res->body);
		}
		
		public function spApi($method, $args = array()) {
			$prefix = "api";
			if ($method[0] == "/" || strpos($method, "http:") === 0 || strpos($method, "https:") === 0) {
				$api_url = ($method[0] == "/" ? self::SPACES_API_HOST : "").$method;
			} else {
				if (strpos($method, "/") > 0) {
					$p = explode("/", $method, 2);
					$method = $p[1]; $prefix = $p[0];
				}
				
				$p = explode(".", $method, 2);
				$api_url = self::SPACES_API_HOST."/".$prefix."/".$p[0];
				if (isset($p[1]))
					$args['method'] = $p[1];
			}
			$args['_origin'] = 'http://spaces.ru';
			$res = $this->exec($api_url, $args, true);
			if ($res->code == 200) {
				return json_decode($res->body);
			} else {
				return (object) array(
					'code' => -1, 
					'http_error' => $res->code, 
					'res' => $res
				);
			}
		}
		
		
		
		public function execAdult($url, $post = array()) {
			global $config;
			curl_setopt($this->ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
			
			$last_user = NULL;
		
request_adult_select_user:
			$sid = NULL;
			for ($i = 0; $i < count($this->users) * 3; ++$i) {
				while (($user = array_rand($this->users)) == $last_user);
				$last_user = $user;
				if (!($sid = apc_fetch("spaces_auth:".$user))) {
					curl_setopt($this->ch, CURLOPT_COOKIE, "sid=");
					curl_setopt($this->ch, CURLOPT_URL, "http://spaces.ru/tm/".$this->users[$user][0]);
					$res = curl_exec($this->ch);
					
					$sid = NULL;
					if (preg_match("/sid=(\d+)/", $res, $_sid) > 0) {
						$sid = $_sid[1];
						apc_store("spaces_auth:".$user, $sid, 0);
						break;
					} elseif (strpos($res, '<a href="http://spaces.ru/">here</a>') !== false) {
						// Ебучий баг с авторизацией
						sleep(3);
					}
				}
			}
			
			curl_setopt($this->ch, CURLOPT_COOKIE, "sid=".$sid);
			$res = '';
			for ($i = 0; $i < 3; ++$i) {
				curl_setopt($this->ch, CURLOPT_URL, $url);
				$res = curl_exec($this->ch);
				if (strpos($res, '"pn_nums"') !== false) {
					curl_setopt($this->ch, CURLOPT_URL, "http://spaces.ru/mysite/?pn_nums=".$this->users[$user][1]."&sid=".$sid);
					curl_exec($this->ch);
					continue;
				} elseif (strpos($res, 'list_item">Не так быстро. Подождите пару секунд и обновите страницу') !== false) {
					// usleep(100000);
					// continue;
					goto request_adult_select_user;
				} elseif (strpos($res, 'action="http://spaces.ru/registration/')) {
					apc_delete("spaces_auth:".$user);
					goto request_adult_select_user;
				}
				break;
			}
			
			$headers_size = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);
			$this->last_http_code = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
			if ($this->last_http_code == 301 || $this->last_http_code == 302) {
				$headers = substr($res, 0, $headers_size);
				if (preg_match("/Location: (.*?)\n/i", $headers, $m))
					$this->last_http_redirect = $m[1];
			}
			
			return substr($res, $headers_size);
		}
	}
	