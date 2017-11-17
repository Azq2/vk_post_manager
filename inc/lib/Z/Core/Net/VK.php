<?php
namespace Z\Core\Net;

class VK {
	public $q;
	public $comm_access_token;
	public $user_access_token;
	
	public function __construct() {
		$this->q = new \Http();
	}
	
	public function setUserToken($user) {
		$this->user_access_token = $user;
	}
	
	public function setCommToken($user) {
		$this->comm_access_token = $user;
	}
	
	public function error($res) {
		$out = (object) ['error' => false, 'captcha' => false, 'sleep' => false];
		if (!$res || !is_object($res)) {
			$out->error = 'Invalid response';
			return $out;
		}
		
		if (isset($res->response))
			return false;
		
		$out->error = 'Unknown response';
		if (isset($res->error)) {
			$out->error = $res->error->error_msg;
			
			if ($res->error->error_code == 6) {
				$out->sleep = true;
			} elseif ($res->error->error_code == 14) {
				$out->captcha = array(
					'url' => $res->error->captcha_img, 
					'sid' => $res->error->captcha_sid
				);
			}
		}
		
		return $out;
	}
	
	public function upload($url, $files = []) {
		$args = array(); $i = 0;
		foreach ($files as $f) {
			$key = isset($f['key']) ? $f['key'] : 'file'.$i;
			$args[$key] = new \CURLFile($f['path']);
			if (isset($f['name']))
				$args[$key]->setPostFilename($f['name']);
			++$i;
		}
		return $this->q->exec($url, $args);
	}
	
	public function execUser($method, $args = []) {
		if ($this->user_access_token)
			$args['access_token'] = $this->user_access_token;
		return $this->exec($method, $args);
	}
	
	public function execComm($method, $args = []) {
		if ($this->comm_access_token)
			$args['access_token'] = $this->comm_access_token;
		return $this->exec($method, $args);
	}
	
	public function exec($method, $args = []) {
		$sig = '';
		$args['v'] = '5.63';
		$args['lang'] = 'ru';
		
		if (isset($_REQUEST['vk_captcha_key']))
			$args['captcha_key'] = $_REQUEST['vk_captcha_key'];
		if (isset($_REQUEST['vk_captcha_sid']))
			$args['captcha_sid'] = $_REQUEST['vk_captcha_sid'];
		
		$res = $this->q->exec("https://api.vk.com/method/".$method, $args, true);
		
		return json_decode($res->body);
	}
}