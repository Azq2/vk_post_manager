<?php
namespace Smm\Tumblr;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;

class Web {
	use \Z\Traits\Singleton;
	
	const COOKIES_FILE = APP.'tmp/tmblr_cookie_jar.txt';
	
	protected $curl;
	
	public function __construct() {
		if (!file_exists(self::COOKIES_FILE)) {
			$euconsent = "CPZIljIPZIlmhECABAENCPCgAPLAAHLAAKiQIwtd_X__bX9n-_7_7ft0eY1f9_r3_-QzjhfNs-8F3L_W_L0X32E7NF36tq".
				"4KuR4ku3bBIQNtHMnUTUmxaolVrzHsak2cpyNKJ7LkknsZe2dYGH9Pn9lD-YKZ7_5___f53T___9_-39z3_9f___d__-__-vjf_599n_".
				"v9fV_7___________-_________gjAASYal5AF2ZY4MmkaRQogRhWEhVAoAKKAYWiKwAcHBTsrAJdQQsAEAqQjAiBBiCjBgEAAgkASER".
				"ASAFggEQBEAgABAAiAQgAImAQWAFgYBAAKAaFiAFAAIEhBkQERymBAVIlFBLZWIJQV7GmEAdZ4AUCiMioAESSQgkBASFg5jgCQEvFkga".
				"YoXyAEYIUAAAAA";
			
			file_put_contents(self::COOKIES_FILE, implode("\n", [
				implode("\t", [".tumblr.com", "TRUE", "/", "TRUE", time() + 3600 * 24 * 30 * 12 * 10, "euconsent-v2", urlencode($euconsent)]),
				implode("\t", [".tumblr.com", "TRUE", "/", "TRUE", time() + 3600 * 24 * 30 * 12 * 10, "euconsent-v2-noniab", urlencode("AAVE")]),
			])."\n");
			
			chmod(self::COOKIES_FILE, 0666);
		}
		
		$this->curl = curl_init();
		curl_setopt_array($this->curl, [
			CURLOPT_RETURNTRANSFER		=> true, 
			CURLOPT_COOKIEJAR			=> self::COOKIES_FILE, 
			CURLOPT_COOKIEFILE			=> self::COOKIES_FILE, 
			CURLOPT_FOLLOWLOCATION		=> true, 
			CURLOPT_VERBOSE				=> false, 
			CURLOPT_HTTPHEADER			=> [
				"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3", 
				"Encoding: gzip, deflate, br", 
				"Accept-Language: en-US,en;q=0.9,ru;q=0.8", 
				"Upgrade-Insecure-Requests: 1"
			], 
			CURLOPT_USERAGENT			=> "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/102.0.5005.49 Safari/537.36", 
			CURLOPT_IPRESOLVE			=> CURL_IPRESOLVE_V4
		]);
	}
	
	public function getUser($user) {
		$url = "https://www.tumblr.com/blog/view/".urlencode($user);
		$html = $this->loadHTML($url);
		if (!$html)
			return (object) ['error' => "Can't fetch $url"];
		
		if (!preg_match('/<script[^>]+>\s*window\[\'___INITIAL_STATE___\'\]\s*=\s*(.*?);\s*\<\/script>/si', $html, $m))
			return (object) ['error' => "Can't find ___INITIAL_STATE___"];
		
		$json = json_decode(str_replace(":undefined", ":null", $m[1]));
		if (!$json)
			return (object) ['error' => "Invalid JSON"];
		
		return (object) ['error' => false, 'data' => $json];
	}
	
	public function getTagged($tag, $sort = "top") {
		$url = "https://www.tumblr.com/tagged/".urlencode($tag)."?sort=".urlencode($sort);
		$html = $this->loadHTML($url);
		if (!$html)
			return (object) ['error' => "Can't fetch $url"];
		
		if (!preg_match('/<script[^>]+>\s*window\[\'___INITIAL_STATE___\'\]\s*=\s*(.*?);\s*\<\/script>/si', $html, $m))
			return (object) ['error' => "Can't find ___INITIAL_STATE___"];
		
		$json = json_decode(str_replace(":undefined", ":null", $m[1]));
		if (!$json)
			return (object) ['error' => "Invalid JSON"];
		
		return (object) ['error' => false, 'data' => $json];
	}
	
	protected function loadHTML($url, $post = false) {
		for ($j = 0; $j < 3; ++$j) {
			curl_setopt($this->curl, CURLOPT_URL, $url);
			
			if ($post !== false) {
				curl_setopt($this->curl, CURLOPT_POST, true);
				curl_setopt($this->curl, CURLOPT_POSTFIELDS, $post);
			} else {
				curl_setopt($this->curl, CURLOPT_POST, false);
			}
			
			$res = curl_exec($this->curl);
			$status = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
			
			if ($status >= 200 && $status <= 299)
				return $res;
			sleep(1);
		}
		return false;
	}
	
	protected function createDom($res) {
		$doc = new \DOMDocument('1.0', 'UTF-8');
		$doc->strictErrorChecking = false;
		$doc->encoding = 'UTF-8';
		@$doc->loadHTML('<?xml version="1.1" encoding="UTF-8" ?>'.$res);
		$xpath = new \DOMXPath($doc);
		foreach ($xpath->query('//comment()') as $comment)
			$comment->parentNode->removeChild($comment);
		$scripts = $doc->getElementsByTagName('script');
		foreach ($scripts as $script)
			$script->parentNode->removeChild($script);
		$styles = $doc->getElementsByTagName('style');
		foreach ($styles as $style)
			$style->parentNode->removeChild($style);
		return $doc;
	}
}
