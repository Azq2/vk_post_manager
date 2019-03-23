<?php
namespace Z\Net;

class VkApi {
	const VK_API_VERSION = 5.87;
	
	protected $access_token, $ch;
	protected $callbacks = [];
	
	public function __construct($access_token = '') {
		$this->ch = curl_init();
		$this->access_token = $access_token;
		curl_setopt_array($this->ch, [
			CURLOPT_RETURNTRANSFER		=> true, 
			CURLOPT_USERAGENT			=> 'Mozilla/5.0', 
		]);
	}
	
	public function onResult(callable $func, $user_data = NULL) {
		$this->callbacks[] = [$func, $user_data];
		return $this;
	}
	
	public function upload($url, $files = []) {
		$args = array();
		$i = 0;
		foreach ($files as $f) {
			$key = isset($f['key']) ? $f['key'] : 'file'.$i;
			$args[$key] = new \CURLFile($f['path']);
			if (isset($f['name']))
				$args[$key]->setPostFilename($f['name']);
			++$i;
		}
		return $this->_sendRequest($url, $args);
	}
	
	public function exec($method, $args = []) {
		$args['v'] = self::VK_API_VERSION;
		$args['lang'] = 'ru';
		
		if ($this->access_token)
			$args['access_token'] = $this->access_token;
		
		$response = $this->_sendRequest("https://api.vk.com/method/".$method, $args);
		if ($response->code != 200) {
			return new VkApi\Response((object) [
				'error'		=> (object) [
					'error_msg'		=> 'http error: '.$response->code, 
					'error_code'	=> -1
				]
			]);
		}
		
		if (!($json = json_decode($response->body))) {
			$json = (object) [
				'error'		=> (object) [
					'error_msg'		=> 'json decode error. ['.$response->content_type.']', 
					'error_code'	=> -1
				]
			];
		}
		
		$response = new VkApi\Response($json);
		
		foreach ($this->callbacks as $callback) {
			$ret = $callback[0](['method' => $method, 'args' => $args, 'result' => $response], $callback[1]);
			if ($ret === false)
				return $response;
			if ($ret instanceof VkApi\Response)
				return $ret;
		}
		
		return $response;
	}
	
	protected function _sendRequest($url, $post) {
		curl_setopt_array($this->ch, [
			CURLOPT_URL				=> $url, 
			CURLOPT_POST			=> count($post) > 0, 
			CURLOPT_POSTFIELDS		=> $post, 
			CURLOPT_TIMEOUT			=> 60, 
			CURLOPT_CONNECTTIMEOUT	=> 60
		]);
		$res = curl_exec($this->ch);
		
		return (object) [
			'body'			=> $res, 
			'code'			=> curl_getinfo($this->ch, CURLINFO_HTTP_CODE), 
			'content_type'	=> curl_getinfo($this->ch, CURLINFO_CONTENT_TYPE)
		];
	}
}
