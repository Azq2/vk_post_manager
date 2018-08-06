<?php
namespace Z\Core\Net;

class Instagram {
	public $q;
	public $access_token;
	public $client_id;
	
	public function __construct($user = NULL) {
		$this->q = new \Http();
		$this->access_token = $user;
	}
	
	public function setToken($user) {
		$this->access_token = $user;
	}
	
	public function error($res) {
		$out = (object) ['error' => false, 'captcha' => false, 'sleep' => false];
		if (!$res || !is_object($res)) {
			$out->error = 'Invalid response';
			return $out;
		}
		
		if (isset($res->meta) && $res->meta->code == 200)
			return false;
		
		$out->error = 'Unknown response';
		if (isset($res->meta))
			$out->error = "Error #".$res->meta->code.": ".$res->meta->error_message." (".$res->meta->error_type.")";
		
		return $out;
	}
	
	public function get($method, $args = []) {
		if (is_array($method)) {
			$new_method = [];
			foreach ($method as $chain)
				$new_method[] = urlencode($chain);
			$method = implode("/", $new_method);
		}
		
		$args['access_token'] = $this->access_token;
		
		echo "https://api.instagram.com/v1/".$method."?".http_build_query($args, '', '&')."\n";
		
		$res = $this->q->exec("https://api.instagram.com/v1/".$method."?".http_build_query($args, '', '&'));
		return json_decode($res->body);
	}
}
