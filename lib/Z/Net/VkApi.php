<?php
namespace Z\Net;

class VkApi {
	protected $access_token, $ch, $api_version;
	protected $callbacks = [];
	protected $max_requests_cnt = 0;
	protected $max_requests_period = 0;
	protected $last_request_time = 0;
	protected $requests_cnt = 0;
	
	public function __construct($access_token = '', $api_version = 5.101) {
		$this->ch = curl_init();
		$this->access_token = $access_token;
		$this->api_version = $api_version;
		curl_setopt_array($this->ch, [
			CURLOPT_RETURNTRANSFER		=> true, 
			CURLOPT_USERAGENT			=> 'Mozilla/5.0', 
		]);
	}
	
	public function setLimit($requests_cnt, $period) {
		$this->max_requests_cnt = $requests_cnt;
		$this->max_requests_period = $period;
		$this->last_request_time = 0;
		$this->requests_cnt = 0;
		return $this;
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
		if (!isset($args['v']))
			$args['v'] = $this->api_version;
		
		if (!isset($args['lang']))
			$args['lang'] = 'ru';
		
		if ($this->access_token)
			$args['access_token'] = $this->access_token;
		
		if ($this->max_requests_cnt && $this->max_requests_period) {
			$elapsed = microtime(true) - $this->last_request_time;
			if ($elapsed >= $this->max_requests_period) {
				$this->last_request_time = 0;
			} else if ($this->requests_cnt >= $this->max_requests_cnt) {
				usleep(ceil(($this->max_requests_period - $elapsed) * 100000));
			}
		}
		
		$response = $this->_sendRequest("https://api.vk.com/method/".$method, $args);
		
		if (!$this->last_request_time && $this->max_requests_cnt) {
			$this->last_request_time = microtime(true);
			$this->requests_cnt = 0;
		}
		
		++$this->requests_cnt;
		
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
