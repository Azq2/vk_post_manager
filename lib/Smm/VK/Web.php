<?php
namespace Smm\VK;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;
use \Z\Net\VkApi;

class Web {
	use \Z\Traits\Singleton;
	
	const COOKIES_FILE = APP.'tmp/vk_cookie_jar.txt';
	
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
	
	public function wallEdit($suggest_id, $data) {
		$ret = [
			'success'		=> false, 
			'error'			=> false
		];
		
		if (!$this->checkAuth()) {
			$ret['error'] = 'Не авторизирован!';
			return $ret;
		}
		
		$response = $this->loadHTML("desktop", "https://vk.com/public".(-$data['owner_id']));
		$post_hash = "";
		if (preg_match("/post_hash\"\s*:\s*\"([^\"]+)\"/si", $response, $m))
			$post_hash = $m[1];
		
		if (!$post_hash) {
			$ret['error'] = 'Невозможно получить post_hash!!!';
			return $ret;
		}
		
		if ($suggest_id) {
			$post = [
				'postpone'				=> $data['publish_date'] ?? '', 
				'mark_as_ads'			=> 0, 
				'act'					=> 'post', 
				'suggest'				=> $data['owner_id'].'_'.$suggest_id, 
				'signed'				=> (($data['signed'] ?? false) ? 1 : ''), 
				'close_comments'		=> 0, 
				'mute_notifications'	=> 0, 
				'hash'					=> $post_hash, 
				'to_id'					=> $data['owner_id'], 
				'Message'				=> $data['message'] ?? '', 
				'al'					=> 1, 
				'_ads_group_id'			=> -$data['owner_id']
			];
		} else {
			$post = [
				'act'					=> 'post', 
				'to_id'					=> $data['owner_id'], 
				'type'					=> 'own', 
				'friends_only'			=> '', 
				'status_export'			=> '', 
				'close_comments'		=> '', 
				'mute_notifications'	=> '', 
				'mark_as_ads'			=> 0, 
				'official'				=> 1, 
				'signed'				=> (($data['signed'] ?? false) ? 1 : ''), 
				'anonymous'				=> (($data['signed'] ?? false) ? 1 : ''), 
				'hash'					=> $post_hash, 
				'from'					=> '', 
				'fixed'					=> '', 
				'postpone'				=> $data['publish_date'] ?? '', 
				'update_admin_tips'		=> 0, 
				'Message'				=> $data['message'] ?? '', 
				'al'					=> 1, 
				'_ads_group_id'			=> -$data['owner_id']
			];
		}
		
		$attaches = [];
		
		if (isset($data['lat'], $data['long']) && $data['lat'] && $data['long'])
			$attaches[] = ["map", $data['lat']."_".$data['long']];
		
		if (isset($data['attachments']) && $data['attachments']) {
			foreach (explode(",", $data['attachments']) as $att) {
				$att = trim($att);
				if (substr($att, 0, 4) == 'http') {
					$post['url'] = $att;
				} elseif (preg_match("/^([a-z_-]+)(.*?)$/si", $att, $m)) {
					$attaches[] = [$m[1], $m[2]];
				}
			}
		}
		
		foreach ($attaches as $i => $att) {
			$post['attach'.($i + 1).'_type'] = $att[0];
			$post['attach'.($i + 1)] = $att[1];
		}
		
		$response = iconv("cp1251", "utf-8", $this->loadHTML("desktop_ajax", "https://vk.com/al_wall.php", $post));
		
		if (preg_match_all("/post".$data['owner_id']."_(\d+)/", $response, $m)) {
			$ret['success'] = true;
			$ret['post_id'] = max($m[1]);
			return $ret;
		}
		
		foreach (explode("<!>", $response) as $p) {
			if ($p && !is_numeric($p) && !preg_match("/(\.js|\.css|cmodules)/i", $p) && !preg_match("/<script/i", $p)) {
				$ret['error'] = trim(html_entity_decode(strip_tags($p)));
				break;
			}
		}
		
		if (!$ret['error'])
			$ret['error'] = 'Ошибка добавления поста.';
		
		return $ret;
	}
	
	public function auth($login, $password, $auth_form = false) {
		if ($this->checkAuth())
			$this->logout();
		
		if (!isset($auth_form['action'], $auth_form['fields']))
			$auth_form = false;
		
		$ret = [
			'success'		=> false, 
			'error'			=> false
		];
		
		// Get auth form, if not passed
		if (!$auth_form) {
			$raw = $this->loadHTML("mobile", "https://m.vk.com/");
			$doc = $this->createDom($raw);
			$xpath = new \DOMXPath($doc);
			
			$auth_form = $this->_getAuthForm($doc);
			if ($auth_form) {
				$captcha_img = $xpath->query(".//img[contains(@class, 'captcha_img')]")->item(0);
				if ($captcha_img) {
					$ret['error'] = 'Нужна капча!';
					$ret['captcha'] = $this->_fixUrl($captcha_img->getAttribute("src"));
					$ret['state'] = $auth_form;
					return $ret;
				}
			}
		}
		
		if (!$auth_form) {
			$ret['error'] = 'Не найдена форма авторизации. Попробуйте позже.';
			return $ret;
		}
		
		// Send auth request
		$auth_form['fields']['email'] = $login;
		$auth_form['fields']['pass'] = $password;
		
		$raw = $this->loadHTML("mobile", $auth_form['action'], http_build_query($auth_form['fields'], '', '&'));
		$doc = $this->createDom($raw);
		$xpath = new \DOMXPath($doc);
		
		// Check, if login success
		foreach ($xpath->query('.//*[contains(@class, "owner_panel")]') as $l) {
			if ($l->getAttribute("data-name") && $l->getAttribute("data-href")) {
				$ret['success'] = true;
				$ret['user'] = [
					'screen_name'	=> trim($l->getAttribute("data-href"), "/"), 
					'real_name'		=> $l->getAttribute("data-name"), 
				];
				return $ret;
			}
		}
		
		// Or try find captcha
		$auth_form = $this->_getAuthForm($doc);
		if ($auth_form) {
			$captcha_img = $xpath->query(".//img[contains(@class, 'captcha_img')]")->item(0);
			if ($captcha_img) {
				$ret['error'] = 'Нужна капча!';
				$ret['captcha'] = $this->_fixUrl($captcha_img->getAttribute("src"));
				$ret['state'] = $auth_form;
				return $ret;
			}
		}
		
		// Show form errors
		$errors = [];
		foreach ($xpath->query('.//*[contains(@class, "service_msg_box")]') as $box)
			$errors[] = trim(preg_replace("/\s+/si", " ", $box->textContent));
		$ret['error'] = $errors ? implode("; ", $errors) : 'Неизвестная ошибка авторизации!';
		
		return $ret;
	}
	
	protected function serializeForm($form) {
		$form_params = [];
		foreach ($form->getElementsByTagName("input") as $input) {
			$type = trim(strtolower($input->getAttribute("type")));
			
			switch ($type) {
				case "radio":
				case "checkbox":
					if ($input->getAttribute("checked"))
						$form_params[$input->getAttribute("name")] = $input->getAttribute("value");
				break;
				
				default:
					if (strlen($input->getAttribute("name")))
						$form_params[$input->getAttribute("name")] = $input->getAttribute("value");
				break;
			}
		}
		return $form_params;
	}
	
	public function checkAuth() {
		$doc = $this->createDom($this->loadHTML("mobile", "https://m.vk.com/feed"));
		$xpath = new \DOMXPath($doc);
		
		foreach ($xpath->query('.//*[contains(@class, "owner_panel")]') as $l) {
			if ($l->getAttribute("data-name") && $l->getAttribute("data-href")) {
				return [
					'screen_name'	=> trim($l->getAttribute("data-href"), "/"), 
					'real_name'		=> $l->getAttribute("data-name"), 
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
	
	protected function _getAuthForm($doc) {
		$xpath = new \DOMXPath($doc);
		$form = $xpath->query(".//form[contains(@action, 'login')]")->item(0);
		if ($form) {
			$action = $this->_fixUrl($form->getAttribute("action"));
			return [
				'action'		=> $action, 
				'fields'		=> $this->serializeForm($form)
			];
		}
		return false;
	}
	
	protected function _fixUrl($url) {
		if (!preg_match("/^(http(s?):)?\/\//i", $url))
			return "https://m.vk.com/".$url;
		return $url;
	}
	
	protected function setRequestMode($mode) {
		$this->mode = $mode;
		
		switch ($mode) {
			case "mobile":
				curl_setopt_array($this->curl, [
					CURLOPT_HTTPHEADER			=> [
						"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3", 
						"Encoding: gzip, deflate, br", 
						"Accept-Language: en-US,en;q=0.9,ru;q=0.8", 
						"Upgrade-Insecure-Requests: 1"
					], 
					CURLOPT_USERAGENT			=> "Mozilla/5.0 (Linux; Android 6.0.1; SM-G532G Build/MMB29T) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.83 Mobile Safari/537.36", 
				]);
			break;
			
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
	
	protected function createDom($res) {
		$doc = new \DOMDocument('1.0', 'UTF-8');
		$doc->strictErrorChecking = false;
		$doc->encoding = 'UTF-8';
		@$doc->loadHTML('<?xml version="1.1" encoding="UTF-8" ?>'.$res);
		$xpath = new \DOMXPath($doc);
		foreach ($xpath->query('//comment()') as $comment)
			$comment->parentNode->removeChild($comment);
		$scripts = $doc->getElementsByTagName('script');
		foreach ($scripts as $script)
			$script->parentNode->removeChild($script);
		$styles = $doc->getElementsByTagName('style');
		foreach ($styles as $style)
			$style->parentNode->removeChild($style);
		return $doc;
	}
}
