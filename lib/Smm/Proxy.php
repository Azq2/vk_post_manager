<?php
namespace Smm;

use \Z\DB;

class Proxy {
	public static function buildCurlProxy($proxy) {
		if ($proxy) {
			if ($proxy['login'] && $proxy['password']) {
				return "socks5://".$proxy['login'].":".$proxy['password']."@".$proxy['host'].":".$proxy['port'];
			} elseif ($proxy['login']) {
				return "socks5://".$proxy['login']."@".$proxy['host'].":".$proxy['port'];
			} else {
				return "socks5://".$proxy['host'].":".$proxy['port'];
			}
		}
	}
	
	public static function get($type) {
		return DB::select()
			->from('vk_proxy')
			->where('type', '=', $type)
			->where('enabled', '=', 1)
			->execute()
			->current();
	}
	
	public static function checkCurlProxy($type, $url) {
		$proxy = self::getCurlProxy($type);
		if ($proxy) {
			$curl = curl_init();
			curl_setopt_array($curl, [
				CURLOPT_URL					=> $url, 
				CURLOPT_RETURNTRANSFER		=> true, 
				CURLOPT_FOLLOWLOCATION		=> true, 
				CURLOPT_VERBOSE				=> false, 
				CURLOPT_USERAGENT			=> "Mozilla/5.0 (Linux; Android 6.0.1; SM-G532G Build/MMB29T) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.83 Mobile Safari/537.36", 
				CURLOPT_IPRESOLVE			=> CURL_IPRESOLVE_V4, 
				CURLOPT_PROXY				=> $proxy, 
				CURLOPT_CONNECTTIMEOUT		=> 2, 
				CURLOPT_TIMEOUT				=> 2
			]);
			$res = curl_exec($curl);
			
			if (preg_match("/CONNECT_CHECK_OK:([\d\.]+)/", $res, $m) && filter_var($m[1], FILTER_VALIDATE_IP))
				return $m[1];
		}
		return false;
	}
}
