<?php
namespace Smm\Instagram;

use \Z\Cache;

class API {
	protected $ch;
	protected $freq_limit = [];
	
	protected static $GRAPHQL_QUERIES = [
		'SHORTCODE_MEDIA'		=> [
			'477b65a610463740ccdb83135b2014db', 
			'8c1ccd0d1cab582bafc9df9f5983e80d', 
			'ea0f07e73ad28955150d066bd22ef843', 
			'2b0673e0dc4580674a88d426fe00ea90', 
			'2cc8bfb89429345060d1212147913582', 
			'fead941d698dc1160a298ba7bec277ac', 
			'505f2f2dfcfce5b99cb7ac4155cbf299'
		], 
		'PROFILE_NEXT_PAGE'		=> [
	//		'003056d32c2554def87228bc3fd9668a',
			'44efc15d3c13342d02df0b5a9fa3d33f', 
			'42323d64886122307be10013ad2dcc44', 
			'472f257a40c653c64c666ce877d59d2b', 
			'bd0d6d184eefd4d0ce7036c11ae58ed9'
		], 
		'HASHTAG_NEXT_PAGE'		=> [
			'9b498c08113f1e09617a1703c22b2f32', 
			// '7dabc71d3e758b1ec19ffb85639e427b', 
			// 'f92f56d47dc7a55b606908374b43a314', 
			// 'c769cb6c71b24c8a86590b22402fda50', 
			// '174a5243287c5f3a7de741089750ab3b'
		]
	];
	
	protected $graphql_query_iter = [];
	
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
			CURLOPT_IPRESOLVE			=> CURL_IPRESOLVE_V4,
			CURLOPT_HTTPHEADER			=> []
		]);
	}
	
	public function getUser($name) {
		$response = $this->exec("https://www.instagram.com/web/search/topsearch/", [
			'context'		=> 'blended',
			'query'			=> $name,
			'rank_token'	=> '0.6631398494622207',
			'include_reel'	=> true
		]);
		var_dump($response);
		if (isset($response->users)) {
			foreach ($response->users as $row) {
				if (strcasecmp($row->user->username, $name) === 0)
					return $row->user;
			}
		}
		
		return false;
	}
	
	public function execGraphql($query_hash_type, $variables) {
		return $this->exec("https://www.instagram.com/graphql/query/", [
			'query_hash'		=> $this->nextGraphqlQueryHash($query_hash_type), 
			'variables'			=> json_encode($variables)
		]);
	}
	
	public function exec($url, $args = []) {
		$query_hash = $args['query_hash'] ?? '';
		
		$this->waitForNextCall($query_hash);
		$response = $this->_sendRequest($url."?".http_build_query($args, '', '&'), false);
		$this->incrApiCall($query_hash);
		
		if ($response->error) {
			$json = (object) [
				'error'		=> (object) [
					'error_msg'		=> $response->error, 
					'error_code'	=> 0
				]
			];
		} elseif ($response->redirect && stripos($response->redirect, "login") !== false) {
			$json = (object) [
				'error'		=> (object) [
					'error_msg'		=> 'Instagram required login.', 
					'error_code'	=> $response->code, 
					'redirect'		=> $response->redirect
				]
			];
		} elseif ($response->redirect) {
			$json = (object) [
				'error'		=> (object) [
					'error_msg'		=> 'Instagram required redirect: '.$response->redirect, 
					'error_code'	=> $response->code, 
					'redirect'		=> $response->redirect
				]
			];
		} elseif ($response->code != 200) {
			$json = (object) [
				'error'		=> (object) [
					'error_msg'		=> 'HTTP error: '.$response->code, 
					'error_code'	=> $response->code
				]
			];
		} elseif (!($json = json_decode($response->body))) {
			$json = (object) [
				'error'		=> (object) [
					'error_msg'		=> 'JSON decode error. ['.$response->content_type.']', 
					'error_code'	=> -1
				]
			];
		}
		
		return new API\Response($json);
	}
	
	protected function nextGraphqlQueryHash($type) {
		if (!isset(self::$GRAPHQL_QUERIES[$type]))
			throw new \Exception("Invalid graphql query hash id: $type");
		
		if (!isset($this->graphql_query_iter[$type]))
			$this->graphql_query_iter[$type] = 0;
		
		$pool = self::$GRAPHQL_QUERIES[$type];
		$hash = $pool[$this->graphql_query_iter[$type] % count($pool)];
		++$this->graphql_query_iter[$type];
		
		return $hash;
	}
	
	protected function waitForNextCall($graphql_hash) {
		$last_time = $this->freq_limit[$graphql_hash]['time'] ?? 0;
		$count = $this->freq_limit[$graphql_hash]['count'] ?? 0;
		
		$delta = microtime(true) - $last_time;
		
		list ($max_cnt, $max_interval) = $this->getMaxRPS($graphql_hash);
		
		if ($count >= $max_cnt && $delta <= $max_interval) {
			$to_sleep = max(0, $max_interval - $delta) * 1000000 + 10;
			// echo "sleep [$graphql_hash] $to_sleep us...\n";
			usleep($to_sleep);
		}
	}
	
	protected function incrApiCall($graphql_hash) {
		$last_time = $this->freq_limit[$graphql_hash]['time'] ?? 0;
		$count = $this->freq_limit[$graphql_hash]['count'] ?? 0;
		
		list ($max_cnt, $max_interval) = $this->getMaxRPS($graphql_hash);
		
		$delta = microtime(true) - $last_time;
		
		if ($delta <= $max_interval) {
			++$this->freq_limit[$graphql_hash]['count'];
		} else {
			$this->freq_limit[$graphql_hash] = [
				'time'		=> microtime(true), 
				'count'		=> 1
			];
		}
	}
	
	protected function getMaxRPS($graphql_hash) {
		if (!$graphql_hash)
			return [10, 1];
		return [1, 7];
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
