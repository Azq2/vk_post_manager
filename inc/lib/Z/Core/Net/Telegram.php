<?php
namespace Z\Core\Net;

class Telegram {
	public $q;
	public $token;
	
	public function __construct() {
		$this->q = new \Http();
	}
	
	public function setBotToken($token) {
		$this->token = $token;
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
	
	public function exec($method, $args = []) {
		$res = $this->q->exec("https://api.telegram.org/bot".$this->token."/$method", $args, true);
		return json_decode($res->body);
	}
}
