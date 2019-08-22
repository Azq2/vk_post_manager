<?php
namespace Smm\VK\API;

abstract class Client {
	public function getCodeAuthLink($code, $params = []) {
		return "https://oauth.vk.com/access_token?".http_build_query(array_merge([
			"client_id"			=> $this->getAppId(), 
			"client_secret"		=> $this->getSecretKey(), 
			"redirect_uri"		=> "https://oauth.vk.com/blank.html ", 
			"code"				=> $code, 
		], $params), "", "&");
	}
	
	public function getOauthUrl($params = []) {
		$oauth_url = "https://oauth.vk.com/authorize?".http_build_query(array_merge([
			"client_id"			=> \Z\Config::get("oauth.VK.id"), 
			"redirect_uri"		=> "https://oauth.vk.com/blank.html", 
			"display"			=> "mobile", 
			"scope"				=> "offline,all", 
			"response_type"		=> "code"
		], $params), "", "&");
		return $oauth_url;
	}
	
	public function getDirectAuthLink($login, $password, $params = []) {
		return "https://oauth.vk.com/token?".http_build_query(array_merge([
			"scope"				=> "nohttps,all", 
			"client_id"			=> $this->getAppId(), 
			"client_secret"		=> $this->getSecretKey(), 
			"lang"				=> $this->getApiLang(), 
			"v"					=> $this->getApiVersion(), 
			"2fa_supported"		=> 1, 
			"grant_type"		=> "password", 
			"username"			=> $login, 
			"password"			=> $password, 
		], $params), "", "&");
	}
	
	public abstract function getApiLang();
	
	public abstract function getApiVersion();
	
	public abstract function getAppId();
	
	public abstract function getSecretKey();
	
	public abstract function getServiceKey();
	
	public abstract function getAccessToken();
	
	public abstract function buildRequest($method, $params);
	
	public abstract function getHeaders();
	
	public abstract function getMaxRPS();
}
