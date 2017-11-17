<?php
namespace Z\Core\Net;

class Anticaptcha {
	public static function resolve($image, $args = []) {
		$args = array_merge([
			"phrase"		=> false,
			"case"			=> false,
			"numeric"		=> false,
			"math"			=> 0,
			"minLength"		=> 0,
			"maxLength"		=> 0, 
			"lang"			=> "en", 
			"queueTimeout"	=> 60000, 
			"resultTimeout"	=> 60000, 
		], $args);
		
		$http = new \Http();
		
		$start = microtime(true);
		while (microtime(true) - $start <= $args['queueTimeout'] / 1000) {
			$res = $http->exec("https://api.anti-captcha.com/createTask", json_encode([
				'clientKey'		=> ANTICAPTCHA_KEY, 
				'languagePool'	=> $args['lang'], 
				'task'			=> [
					'type'			=> 'ImageToTextTask', 
					'body'			=> $image, 
					'phrase'		=> $args['phrase'],
					'case'			=> $args['case'],
					'numeric'		=> $args['numeric'],
					'math'			=> $args['math'],
					'minLength'		=> $args['minLength'],
					'maxLength'		=> $args['maxLength'], 
				]
			]));
			if ($res->code == 200 && ($json = json_decode($res->body)) && isset($json->taskId) && $json->taskId) {
				$start = microtime(true);
				while (microtime(true) - $start <= $args['resultTimeout'] / 1000) {
					$res = $http->exec("https://api.anti-captcha.com/getTaskResult", json_encode([
						'clientKey'	=> ANTICAPTCHA_KEY, 
						'taskId'	=> $json->taskId
					]));
					if ($res->code == 200 && ($json2 = json_decode($res->body)) && isset($json2->solution))
						return $json2->solution->text;
				}
			}
		}
		return false;
	}
}
