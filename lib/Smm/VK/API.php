<?php
namespace Smm\VK;

use \Z\Cache;

class API {
	protected $ch;
	protected $client;
	
	public function __construct($params) {
		$params = array_merge([
			'client'		=> 'standalone'
		], $params);
		
		$client_class = "\\Smm\\VK\\API\\Client\\".implode("", array_map("ucfirst", explode("_", $params['client'])));
		if (class_exists($client_class)) {
			$this->client = new $client_class($params);
		} else {
			throw new \Exception("VK api client $client_class not found!");
		}
		
		$this->ch = curl_init();
		curl_setopt_array($this->ch, [
			CURLOPT_RETURNTRANSFER		=> true, 
			CURLOPT_FOLLOWLOCATION		=> false, 
			CURLOPT_VERBOSE				=> false, 
			CURLOPT_TIMEOUT				=> 60, 
			CURLOPT_CONNECTTIMEOUT		=> 60, 
			CURLOPT_ENCODING			=> "gzip,deflate", 
			CURLOPT_HTTPHEADER			=> $this->client->getHeaders(), 
			CURLOPT_IPRESOLVE			=> CURL_IPRESOLVE_V4, 
			CURLOPT_HTTP_VERSION		=> CURL_HTTP_VERSION_1_1 // 2.0 зависает в oauth.vk.com
		]);
	}
	
	public function setProxy($url) {
		curl_setopt($this->ch, CURLOPT_PROXY, $url);
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
			if (isset($f['mime']))
				$args[$key]->setMimeType($f['mime']);
			++$i;
		}
		return $this->_sendRequest($url, $args);
	}
	
	public function getOauthUrl($params = []) {
		return $this->client->getOauthUrl($params);
	}
	
	public function loginOauthCode($code, $params = []) {
		$direct_auth = $this->client->getCodeAuthLink($code, $params);
		if (!$direct_auth)
			throw new \Exception('Authorization code flow unsupported!');
		
		$response = $this->_sendRequest($direct_auth, []);
		
		if (!($json = json_decode($response->body))) {
			$json = (object) [
				'error'					=> 'json_error', 
				'error_description'		=> 'json decode error. [content_type='.$response->content_type.', status='.$response->code.']'
			];
		}
		
		return new API\OauthResponse($json);
	}
	
	public function loginOauthDirect($login, $password, $params = []) {
		$direct_auth = $this->client->getDirectAuthLink($login, $password, $params);
		if (!$direct_auth)
			throw new \Exception('Authorization direct flow unsupported!');
		
		$response = $this->_sendRequest($direct_auth, []);
		
		if (!($json = json_decode($response->body))) {
			$json = (object) [
				'error'					=> 'json_error', 
				'error_description'		=> 'json decode error. [content_type='.$response->content_type.', status='.$response->code.']'
			];
		}
		
		return new API\OauthResponse($json);
	}
	
	public function exec($method, $args = []) {
		$request = $this->client->buildRequest($method, $args);
		
		$this->waitForNextCall();
		$response = $this->_sendRequest($request['url'], $request['post']);
		$this->incrApiCall();
		
		if ($response->code != 200) {
			return new API\Response((object) [
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
		
		return new API\Response($json);
	}
	
	protected function waitForNextCall() {
		$access_token = $this->client->getAccessToken();
		$cache = Cache::instance();
		
		$start = microtime(true);
		while (microtime(true) - $start < 10) {
			$k_time = "vk_api:".md5($access_token).":time";
			$k_count = "vk_api:".md5($access_token).":count";
			
			$data = $cache->getMulti([$k_time, $k_count]);
			$last_time = $data[$k_time] ?? 0;
			$count = $data[$k_count] ?? 0;
			$delta = microtime(true) - $last_time;
			
			if ($count < $this->client->getMaxRPS() || $delta > 1)
				break;
			
			if ($delta <= 1)
				usleep(max(0, 1 - $delta) * 1000000 + 10);
		}
	}
	
	protected function incrApiCall() {
		$access_token = $this->client->getAccessToken();
		$cache = Cache::instance();
		
		$k_time = "vk_api:".md5($access_token).":time";
		$k_count = "vk_api:".md5($access_token).":count";
		
		$data = $cache->getMulti([$k_time, $k_count]);
		$last_time = $data[$k_time] ?? 0;
		$count = $data[$k_count] ?? 0;
		
		$delta = microtime(true) - $last_time;
		
		if ($delta <= 1) {
			$cache->incr($k_count, 1, 1, 60);
		} else {
			$cache->setMulti([
				$k_time		=> microtime(true), 
				$k_count	=> 1
			], 60);
		}
	}
	
	protected function _sendRequest($url, $post) {
		curl_setopt_array($this->ch, [
			CURLOPT_URL				=> $url, 
			CURLOPT_POST			=> !empty($post), 
			CURLOPT_POSTFIELDS		=> $post
		]);
		$res = curl_exec($this->ch);
		
		return (object) [
			'body'			=> $res, 
			'code'			=> curl_getinfo($this->ch, CURLINFO_HTTP_CODE), 
			'content_type'	=> curl_getinfo($this->ch, CURLINFO_CONTENT_TYPE)
		];
	}
}
