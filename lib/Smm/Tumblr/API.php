<?php
namespace Smm\Tumblr;

use \Z\Cache;

class API {
	protected $ch;
	protected $oauth;
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
			'scope'				=> 'basic write offline_access',
			'state'				=> '',
		], $params), "", "&");
		return $oauth_url;
	}
	
	public function loginOauthCode($code, $params = []) {
		return $this->exec('POST', 'oauth2/token', array_merge([
			'grant_type'	=> 'authorization_code',
			'code'			=> $code,
			'client_id'		=> \Z\Config::get("oauth.TUMBLR.id"),
			'client_secret'	=> \Z\Config::get("oauth.TUMBLR.secret")
		], $params), false);
	}
	
	public function exec($method, $url, $args = [], $use_auth = true) {
		$response = $this->sendRequest($method, "https://api.tumblr.com/v2/$url", $args, $use_auth);
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
	
	protected function renewOauthKey() {
		$oauth = \Smm\Oauth::getAccessToken('TUMBLR');
		if ($oauth && $oauth['expires'] && $oauth['expires'] - time() <= 600) {
			$result = $this->exec('POST', 'oauth2/token', [
				'grant_type'	=> 'refresh_token',
				'refresh_token'	=> $oauth['refresh_token'],
				'client_id'		=> \Z\Config::get("oauth.TUMBLR.id"),
				'client_secret'	=> \Z\Config::get("oauth.TUMBLR.secret")
			], false);
			
			if ($result->success()) {
				\Z\DB::insert('vk_oauth')
					->set([
						'type'			=> 'TUMBLR', 
						'access_token'	=> $result->access_token, 
						'secret'		=> $result->secret ?? '', 
						'refresh_token'	=> $result->refresh_token ?? '', 
						'expires'		=> $result->expires_in ? time() + $result->expires_in : 0, 
					])
					->onDuplicateSetValues('access_token')
					->onDuplicateSetValues('refresh_token')
					->onDuplicateSetValues('secret')
					->onDuplicateSetValues('expires')
					->execute();
				
				$oauth = \Smm\Oauth::getAccessToken('TUMBLR');
			}
		}
		return $oauth;
	}
	
	protected function sendRequest($method, $url, $post, $use_auth = true) {
		curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $method);
		
		if ($method == 'GET') {
			curl_setopt($this->ch, CURLOPT_URL, $url."?".http_build_query($post, '', '&'));
			curl_setopt($this->ch, CURLOPT_POST, false);
		} else {
			curl_setopt($this->ch, CURLOPT_URL, $url);
			curl_setopt($this->ch, CURLOPT_POST, true);
			curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post);
		}
		
		if ($use_auth) {
			$oauth = $this->renewOauthKey();
			if ($oauth)
				curl_setopt($this->ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer ".$oauth["access_token"]]);
		}
		
		$res = curl_exec($this->ch);
		
		return (object) [
			'body'			=> $res, 
			'code'			=> curl_getinfo($this->ch, CURLINFO_HTTP_CODE), 
			'content_type'	=> curl_getinfo($this->ch, CURLINFO_CONTENT_TYPE)
		];
	}
}
