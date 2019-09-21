<?php
namespace Smm\Grabber;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;

class Pinterest {
	use \Z\Traits\Singleton;
	
	const COOKIES_FILE = APP.'tmp/pinterest_cookie_jar.txt';
	
	protected $curl;
	
	public function __construct() {
		$this->initCurl();
	}
	
	public function logout() {
		curl_close($this->curl);
		if (file_exists(self::COOKIES_FILE))
			unlink(self::COOKIES_FILE);
		$this->initCurl();
	}
	
	public function auth($cookies) {
		$ret = [
			'success'		=> false, 
			'error'			=> false
		];
		
		$this->logout();
		
		foreach ($cookies as $k => $v) {
			$max_age = 3600 * 24 * 365 * 10;
			$expires = time() + $max_age;
			curl_setopt($this->curl, CURLOPT_COOKIELIST, "Set-Cookie: ".urlencode($k)."=".urlencode($v)."; ".
				"domain=.pinterest.ru; expires=".gmdate('D, d-M-Y H:i:s', $expires)." GMT; path=/; max-age=$expires");
		}
		
		if (!$this->checkAuth()) {
			$ret['error'] = 'Ошибка авторизации.';
		} else {
			$ret['success'] = true;
		}
		
		return $ret;
	}
	
	public function checkAuth() {
		$doc = $this->loadHTML("desktop", "https://www.pinterest.ru/settings/");
		
		if (preg_match('#"viewer":\s*\{(.*?)\}#si', $doc, $m)) {
			if (preg_match('#"username":\s*"([^"]+)"#si', $m[1], $mm)) {
				return [
					'real_name'		=> $mm[1], 
					'screen_name'	=> $mm[1], 
				];
			}
		}
		
		return false;
	}
	
	protected function loadHTML($mode, $url, $post = false) {
		$this->setRequestMode($mode);
		
		for ($j = 0; $j < 3; ++$j) {
			curl_setopt($this->curl, CURLOPT_URL, $url);
			
			if ($post !== false) {
				curl_setopt($this->curl, CURLOPT_POST, true);
				curl_setopt($this->curl, CURLOPT_POSTFIELDS, $post);
			} else {
				curl_setopt($this->curl, CURLOPT_POST, false);
			}
			
			$res = curl_exec($this->curl);
			$status = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
			
			if ($status >= 200 && $status <= 299)
				return $res;
			sleep(1);
		}
		return false;
	}
	
	public function getCurl() {
		return $this->curl;
	}
	
	protected function initCurl() {
		if (!file_exists(self::COOKIES_FILE)) {
			touch(self::COOKIES_FILE);
			chmod(self::COOKIES_FILE, 0666);
		}
		
		$this->curl = curl_init();
		curl_setopt_array($this->curl, [
			CURLOPT_RETURNTRANSFER		=> true, 
			CURLOPT_COOKIEJAR			=> self::COOKIES_FILE, 
			CURLOPT_COOKIEFILE			=> self::COOKIES_FILE, 
			CURLOPT_FOLLOWLOCATION		=> true, 
			CURLOPT_VERBOSE				=> false, 
			CURLOPT_HTTPHEADER			=> [
				"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3", 
				"Encoding: gzip, deflate, br", 
				"Accept-Language: en-US,en;q=0.9,ru;q=0.8", 
				"Upgrade-Insecure-Requests: 1"
			], 
			CURLOPT_USERAGENT			=> "Mozilla/5.0 (Linux; Android 6.0.1; SM-G532G Build/MMB29T) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.83 Mobile Safari/537.36", 
			CURLOPT_IPRESOLVE			=> CURL_IPRESOLVE_V4
		]);
	}
	
	public function setRequestMode($mode) {
		$this->mode = $mode;
		
		switch ($mode) {
			case "desktop":
				curl_setopt_array($this->curl, [
					CURLOPT_HTTPHEADER			=> [
						"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3", 
						"Encoding: gzip, deflate, br", 
						"Accept-Language: en-US,en;q=0.9,ru;q=0.8", 
						"Upgrade-Insecure-Requests: 1"
					], 
					CURLOPT_USERAGENT			=> "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.100 Safari/537.36", 
				]);
			break;
			
			case "desktop_ajax":
				curl_setopt_array($this->curl, [
					CURLOPT_HTTPHEADER			=> [
						"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3", 
						"Encoding: gzip, deflate, br", 
						"Accept-Language: en-US,en;q=0.9,ru;q=0.8", 
						"Upgrade-Insecure-Requests: 1", 
						"X-Requested-With: XMLHttpRequest", 
						"Origin: https://vk.com"
					], 
					CURLOPT_USERAGENT			=> "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.100 Safari/537.36", 
				]);
			break;
		}
	}
}
