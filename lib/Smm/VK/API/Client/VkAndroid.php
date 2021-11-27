<?php
namespace Smm\VK\API\Client;

class VkAndroid extends \Smm\VK\API\Client {
	protected $params;
	
	public function __construct($params) {
		$this->params = array_merge([
			'access_token'		=> '', 
			'secret'			=> ''
		], $params);
	}
	
	public function getDirectAuthLink($login, $password, $params = []) {
		return "https://oauth.vk.com/token?".http_build_query(array_merge([
			"scope"				=> "nohttps,all", 
			"client_id"			=> $this->getAppId(), 
			"client_secret"		=> $this->getSecretKey(), 
			"2fa_supported"		=> 1, 
			"lang"				=> $this->getApiLang(), 
			"device_id"			=> substr(md5("vk_client:$login:$password"), 0, 16), 
			"grant_type"		=> "password", 
			"username"			=> $login, 
			"password"			=> $password, 
			"libverify_support"	=> 1
		], $params), "", "&");
	}
	
	public function getApiLang() {
		return 'ru';
	}
	
	public function getApiVersion() {
		return '5.131';
	}
	
	public function getAppId() {
		return 2274003;
	}
	
	public function getSecretKey() {
		return 'hHbZxrka2uZ6jB1inYsH';
	}
	
	public function getServiceKey() {
		return '';
	}
	
	public function getAccessToken() {
		return $this->params['access_token'];
	}
	
	public function getMaxRPS() {
		return 12;
	}
	
	public function buildRequest($method, $params) {
		// VK always send this params at begin
		$params = array_merge([
			'v'			=> $this->getApiVersion(), 
			'lang'		=> $this->getApiLang(), 
			'https'		=> 1
		], $params);
		
		// VK always send this param at end
		if (!isset($params['access_token']))
			$params['access_token'] = $this->params['access_token'];
		
		$sig = [];
		foreach ($params as $k => $v)
			$sig[] = $k.'='.$v;
		
		$params['sig'] = md5("/method/$method?".implode("&", $sig).$this->params['secret']);
		
		return [
			'url'		=> "https://api.vk.com/method/$method", 
			'post'		=> http_build_query($params, "", "&")
		];
	}
	
	public function getHeaders() {
		return [
			"Accept:", 
			"X-Get-Processing-Time: 1", 
			"User-Agent: VKAndroidApp/4.13.1-1193 (Android 4.2.2; SDK 17; armeabi-v7a; LENOVO Lenovo A850; ru)", 
			"Connection: Keep-Alive", 
			"Accept-Encoding: gzip", 
		];
	}
}
