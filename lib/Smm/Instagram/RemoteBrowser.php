<?php
namespace Smm\Instagram;

use \Z\Config;

class RemoteBrowser {
	const FREQ_INTERVAL		= 1333;
	const FREQ_MAX_COUNT	= 1;
	
	protected $ch;
	protected $freq_limit_count = 0;
	protected $freq_limit_time = 0;
	
	public function __construct() {
		$this->ch = curl_init();
		curl_setopt_array($this->ch, [
			CURLOPT_RETURNTRANSFER		=> true,
			CURLOPT_FOLLOWLOCATION		=> false,
			CURLOPT_VERBOSE				=> false,
			CURLOPT_TIMEOUT				=> 60,
			CURLOPT_CONNECTTIMEOUT		=> 60,
			CURLOPT_ENCODING			=> "gzip,deflate",
			CURLOPT_USERAGENT			=> "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.41 Safari/537.36",
			CURLOPT_HTTPHEADER			=> []
		]);
		
		$this->url_prefix = Config::get("instagram", "url");
	}
	
	public function exec($url, $args = []) {
		$this->waitForNextCall();
		$response = $this->_sendRequest($this->url_prefix.$url."?".http_build_query($args, '', '&'), false);
		$this->incrApiCall();
		
		if ($response->error) {
			$json = (object) ['error' => $response->error, 'status' => 400];
		} elseif ($response->code < 200 || $response->code > 299) {
			$json = (object) ['error' => 'HTTP error: '.$response->code, 'status' => $response->code];
		} elseif (!($json = json_decode($response->body))) {
			$json = (object) ['error' => 'JSON decode error. ['.$response->content_type.']', 'status' => 0];
		}
		
		return $json;
	}
	
	public function waitForStatus($id, $timeout = 1200) {
		$start = microtime(true);
		$max_errors = 15;
		do {
			$response = $this->exec("status", ['id' => $id]);
			
			if ($response->status == 200)
				return $response;
			
			if ($response->status < 200 || $response->status > 299) {
				$max_errors--;
				if (!$max_errors)
					return $response;
			}
		} while (microtime(true) - $start < $timeout);
		
		return ['error' => 'Timeout', 'status' => 0];
	}
	
	protected function waitForNextCall() {
		$delta = microtime(true) - $this->freq_limit_time;
		
		$freq_limit_count = Config::get("instagram", "freq_limit_count");
		$freq_limit_interval = Config::get("instagram", "freq_limit_interval");
		
		if ($this->freq_limit_count >= $freq_limit_count && $delta <= $freq_limit_interval) {
			$to_sleep = max(0, $freq_limit_interval - $delta) * 1000000 + 10;
			usleep($to_sleep);
		}
	}
	
	protected function incrApiCall() {
		$delta = microtime(true) - $this->freq_limit_time;
		
		$freq_limit_count = Config::get("instagram", "freq_limit_count");
		$freq_limit_interval = Config::get("instagram", "freq_limit_interval");
		
		if ($delta <= $freq_limit_interval) {
			++$this->freq_limit_count;
		} else {
			$this->freq_limit_count = 1;
			$this->freq_limit_time = microtime(true);
		}
	}
	
	protected function _sendRequest($url, $post) {
		curl_setopt($this->ch, CURLOPT_URL, $url);
		
		if ($post !== false) {
			curl_setopt($this->ch, CURLOPT_POSTFIELDS, $post);
			curl_setopt($this->ch, CURLOPT_POST, true);
		} else {
			curl_setopt($this->ch, CURLOPT_POSTFIELDS, NULL);
			curl_setopt($this->ch, CURLOPT_POST, false);
		}
		
		$res = curl_exec($this->ch);
		
		$http_code = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
		
		return (object) [
			'body'			=> $res,
			'code'			=> $http_code,
			'content_type'	=> curl_getinfo($this->ch, CURLINFO_CONTENT_TYPE),
			'redirect'		=> curl_getinfo($this->ch, CURLINFO_REDIRECT_URL),
			'error'			=> !$http_code ? curl_error($this->ch) : false
		];
	}
}
