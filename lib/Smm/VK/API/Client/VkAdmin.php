<?php
namespace Smm\VK\API\Client;

class VkAdmin extends \Smm\VK\API\Client {
	protected $params;
	
	public function __construct($params) {
		$this->params = array_merge([
			'access_token'			=> ''
		], $params);
	}
	
	public function getDirectAuthLink($login, $password, $params = []) {
		return "https://oauth.vk.com/token?".http_build_query(array_merge([
			"grant_type"		=> "password", 
			"client_id"			=> $this->getAppId(), 
			"client_secret"		=> $this->getSecretKey(), 
			"2fa_supported"		=> 0, 
			"username"			=> $login, 
			"password"			=> $password, 
			"lang"				=> $this->getApiLang(), 
			"v"					=> $this->getApiLang() ,
			"access_token"		=> "null", 
		], $params), "", "&");
	}
	
	public function getApiLang() {
		return 'ru';
	}
	
	public function getApiVersion() {
		return '5.131';
	}
	
	public function getAppId() {
		return 6121396;
	}
	
	public function getSecretKey() {
		return 'L3yBidmMBtFRKO9hPCgF';
	}
	
	public function getServiceKey() {
		return '';
	}
	
	public function getAccessToken() {
		return $this->params['access_token'];
	}
	
	public function getMaxRPS() {
		return 9;
	}
	
	public function buildRequest($method, $params) {
		// VK Admin always send this params at end
		if (!isset($params['lang']))
			$params['lang'] = $this->getApiLang();
		
		if (!isset($params['v']))
			$params['v'] = $this->getApiVersion();
		
		if (!isset($params['access_token']))
			$params['access_token'] = $this->params['access_token'];
		
		return [
			'url'		=> "https://api.vk.com/method/$method?".http_build_query($params, "", "&"), 
			'post'		=> false
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
