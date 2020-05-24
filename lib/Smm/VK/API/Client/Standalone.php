<?php
namespace Smm\VK\API\Client;

class Standalone extends \Smm\VK\API\Client {
	protected $params;
	
	public function __construct($params) {
		$this->params = array_merge([
			'access_token'			=> '', 
			'access_token_type'		=> 'user'
		], $params);
	}
	
	public function getApiLang() {
		return 'ru';
	}
	
	public function getApiVersion() {
		return '5.124';
	}
	
	public function getAppId() {
		return \Z\Config::get("oauth.VK.id");
	}
	
	public function getSecretKey() {
		return \Z\Config::get("oauth.VK.secret");
	}
	
	public function getServiceKey() {
		return \Z\Config::get("oauth.VK.service_key");
	}
	
	public function getAccessToken() {
		return $this->params['access_token'];
	}
	
	public function getMaxRPS() {
		switch ($this->params['access_token_type']) {
			case "community":
				return 20;
			
			default:
				return 3;
		}
	}
	
	public function buildRequest($method, $params) {
		$params = array_merge([
			'v'			=> $this->getApiVersion(), 
			'lang'		=> $this->getApiLang(), 
			'access_token'	=> $this->params['access_token']
		], $params);
		
		return [
			'url'		=> "https://api.vk.com/method/$method", 
			'post'		=> http_build_query($params, "", "&")
		];
	}
	
	public function getHeaders() {
		return [
			"Accept:", 
			"User-Agent: Dalvik/1.6.0 (Linux; U; Android 4.2.2; Lenovo A850 Build/JDQ39)", 
			"Connection: Keep-Alive", 
			"Accept-Encoding: gzip", 
		];
	}
}
