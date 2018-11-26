<?php
namespace Z\Core\Net;

use \Z\Core\Config;

class Anticaptcha {
	protected static $instances = [];
	protected $config;
	
	protected function __construct($instance = NULL) {
		$instance = $instance ?: 'default';
		
		$this->config = Config::get("anticaptcha", $instance);
		
		$this->ch = curl_init();
		curl_setopt_array($this->ch, [
			CURLOPT_RETURNTRANSFER		=> true, 
			CURLOPT_USERAGENT			=> 'Mozilla/5.0', 
		]);
	}
	
	public static function instance($instance = NULL) {
		if (!isset(self::$instances[$instance]))
			self::$instances[$instance] = new Anticaptcha($instance);
		return self::$instances[$instance];
	}
	
	public function resolve($image, $options = []) {
		$options = array_merge([
			"phrase"		=> false,
			"case"			=> false,
			"numeric"		=> false,
			"math"			=> 0,
			"minLength"		=> 0,
			"maxLength"		=> 0, 
			"lang"			=> "en", 
			"queueTimeout"	=> 60000, 
			"resultTimeout"	=> 60000, 
		], $options);
		
		$start = microtime(true);
		while (microtime(true) - $start <= $options['queueTimeout'] / 1000) {
			$res = $this->_sendRequest("https://api.anti-captcha.com/createTask", json_encode([
				'clientKey'		=> $this->config['key'], 
				'languagePool'	=> $options['lang'], 
				'task'			=> [
					'type'			=> 'ImageToTextTask', 
					'body'			=> $image, 
					'phrase'		=> $options['phrase'],
					'case'			=> $options['case'],
					'numeric'		=> $options['numeric'],
					'math'			=> $options['math'],
					'minLength'		=> $options['minLength'],
					'maxLength'		=> $options['maxLength'], 
				]
			]));
			
			if ($res->code == 200 && ($json = json_decode($res->body))) {
				if (isset($json->taskId) && $json->taskId) {
					$start = microtime(true);
					while (microtime(true) - $start <= $options['resultTimeout'] / 1000) {
						$res = $res = $this->_sendRequest("https://api.anti-captcha.com/getTaskResult", json_encode([
							'clientKey'	=> $this->config['key'], 
							'taskId'	=> $json->taskId
						]));
						if ($res->code == 200 && ($json2 = json_decode($res->body)) && isset($json2->solution))
							return $json2->solution->text;
						sleep(1);
					}
				} elseif (isset($json->errorId) && $json->errorId) {
					echo "err: ".$json->errorDescription."\n";
					return false;
				}
			}
			sleep(1);
		}
		return false;
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
