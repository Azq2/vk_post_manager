<?php

class Http {
	public $ch;
	public $users;
	public $vk_user = 'VK';
	
	public $last_http_code = 0;
	public $last_http_redirect = '';
	
	private static $oauth = false;
	
	public function __construct() {
		$this->ch = curl_init();
		curl_setopt_array($this->ch, array(
			CURLOPT_RETURNTRANSFER		=> true, 
			CURLOPT_ENCODING			=> "gzip", 
			CURLOPT_COOKIE				=> '', 
			CURLOPT_HEADER				=> true, 
			CURLOPT_VERBOSE				=> false,
			CURLOPT_CONNECTTIMEOUT			=> 30,
			CURLOPT_TIMEOUT				=> 60
		));
		
		if (!self::$oauth) {
			define_oauth();
			self::$oauth = true;
		}
	}
	
	public function dumpLastReqState() {
		return $this->last_http_redirect ?
			$this->last_http_code." [".$this->last_http_redirect."]" : $this->last_http_code;
	}
	
	public function timeout($connect, $download) {
		curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, $connect);
		curl_setopt($this->ch, CURLOPT_TIMEOUT, $download);
		return $this;
	}
	
	public function vkSetUser($user) {
		$this->vk_user = $user;
	}
	
	public function enableCookies() {
		$jar = "/tmp/".md5(__FILE__)."-cookies.jar";
		curl_setopt($this->ch, CURLOPT_COOKIEFILE, $jar);
		curl_setopt($this->ch, CURLOPT_COOKIEJAR, $jar);
	}
	
	public function exec($url, $post = NULL, $xhr = false) {
		if ($xhr) {
			curl_setopt($this->ch, CURLOPT_HTTPHEADER, array(
				'X-Requested-With' => "XMLHttpRequest"
			));
		}
		
		curl_setopt($this->ch, CURLOPT_USERAGENT, "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/34.0.1847.132 Safari/537.36");
		curl_setopt($this->ch, CURLOPT_URL, $url);
		if ($post) {
			curl_setopt($this->ch, CURLOPT_POST, true);
			@curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post);
		}
		
		$res = curl_exec($this->ch);
		if ($post)
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
	
	public function vkApiUpload($url, $files = array()) {
		$args = array(); $i = 0;
		foreach ($files as $f) {
			$key = isset($f['key']) ? $f['key'] : 'file'.$i;
			$args[$key] = new CURLFile($f['path']);
			if (isset($f['name']))
				$args[$key]->setPostFilename($f['name']);
			++$i;
		}
		return $this->exec($url, $args);
	}
	
	public function vkOpenApi($method, $args = array()) {
		$args['v'] = '5.63';
		$args['lang'] = 'ru';
		
		if (isset($_REQUEST['vk_captcha_key']))
			$args['captcha_key'] = $_REQUEST['vk_captcha_key'];
		if (isset($_REQUEST['vk_captcha_sid']))
			$args['captcha_sid'] = $_REQUEST['vk_captcha_sid'];
		
		$res = $this->exec("https://api.vk.com/method/".$method, $args, true);
		return json_decode($res->body);
	}
	
	public function vkApi($method, $args = array(), $open = false) {
		$sig = '';
		$args['v'] = '5.63';
		$args['lang'] = 'ru';
		$args['access_token'] = constant($this->vk_user.'_USER_ACCESS_TOKEN');
		
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
	
	public function okApi($method, $args = array()) {
		$args['format'] = 'JSON';
		$args['__online'] = 'false';
		$args['application_key'] = OK_APP_PUBLIC;
		
		ksort($args);
		
		$sig_raw = "";
		foreach ($args as $k => $v)
			$sig_raw .= "$k=$v";
		
		$args['sig'] = md5($sig_raw.md5(OK_USER_ACCESS_TOKEN.OK_APP_SECRET));
		$args['access_token'] = OK_USER_ACCESS_TOKEN;
		
		$res = $this->exec("https://api.ok.ru/api/".str_replace(".", "/", $method), $args, true);
		return json_decode($res->body);
	}
}
