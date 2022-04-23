<?php
namespace Smm\Tumblr;

use \Z\Cache;

class API {
	protected $ch;
	protected $client;
	
	public function __construct() {
		$this->ch = curl_init();
		curl_setopt_array($this->ch, [
			CURLOPT_RETURNTRANSFER		=> true, 
			CURLOPT_FOLLOWLOCATION		=> false, 
			CURLOPT_VERBOSE				=> false, 
			CURLOPT_TIMEOUT				=> 60, 
			CURLOPT_CONNECTTIMEOUT		=> 60, 
			CURLOPT_ENCODING			=> "gzip,deflate", 
			CURLOPT_IPRESOLVE			=> CURL_IPRESOLVE_V4, 
			CURLOPT_HTTP_VERSION		=> CURL_HTTP_VERSION_1_1
		]);
	}
	
	public function getOauthUrl($params = []) {
		$oauth_url = "https://www.tumblr.com/oauth2/authorize?".http_build_query(array_merge([
			'client_id'			=> \Z\Config::get("oauth.TUMBLR.id"),
			'response_type'		=> 'code',
			'scope'				=> 'basic write',
			'state'				=> '',
		], $params), "", "&");
		return $oauth_url;
	}
	
	public function loginOauthCode($code, $params = []) {
		return $this->exec('oauth2/token', array_merge([
			'grant_type'	=> 'authorization_code',
			'code'			=> $code,
			'client_id'		=> \Z\Config::get("oauth.TUMBLR.id"),
			'client_secret'	=> \Z\Config::get("oauth.TUMBLR.secret")
		], $params));
	}
	
	public function exec($method, $args = []) {
		$response = $this->_sendRequest("https://api.tumblr.com/v2/$method", $args);
		$code = $response->code;
		
		if (!($json = json_decode($response->body))) {
			$code = -1;
			$json = (object) [
				'errors'	=>  [
					(object) ['code' => -1, 'title' => 'json decode error. ['.$response->content_type.']']
				]
			];
		}
		
		return new API\Response($code, $json);
	}
	
	protected function _sendRequest($url, $post) {
		curl_setopt_array($this->ch, [
			CURLOPT_URL				=> $url, 
			CURLOPT_POST			=> !empty($post), 
			CURLOPT_POSTFIELDS		=> $post
		]);
		
		$auth = \Smm\Oauth::getAccessToken('TUMBLR');
		if ($auth)
			curl_setopt($this->ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer ".$auth["access_token"]]);
		
		$res = curl_exec($this->ch);
		
		return (object) [
			'body'			=> $res, 
			'code'			=> curl_getinfo($this->ch, CURLINFO_HTTP_CODE), 
			'content_type'	=> curl_getinfo($this->ch, CURLINFO_CONTENT_TYPE)
		];
	}
}
