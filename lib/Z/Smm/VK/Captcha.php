<?php
namespace Z\Smm\VK;

use \Z\Core\DB;
use \Z\Core\View;
use \Z\Core\Date;
use \Z\Core\Util\Url;
use \Z\Core\Net\VkApi;
use \Z\Core\Net\Anticaptcha;

class Captcha {
	protected static $mode = 'http';
	protected static $captcha = false;
	
	public static function setMode($mode) {
		self::$mode = $mode;
	}
	
	public static function set($captcha) {
		self::$captcha = $captcha;
	}
	
	public static function getLast() {
		return self::$captcha;
	}
	
	public static function getCode() {
		switch (self::$mode) {
			case "anticaptcha":
				if (self::$captcha) {
					$anticaptcha = Anticaptcha::instance();
					
					$sid = self::$captcha['sid'];
					$image = base64_encode(file_get_contents(self::$captcha['url']));
					
					self::$captcha = false;
					
					return [
						'key'		=> $anticaptcha->resolve($image), 
						'sid'		=> $sid, 
					];
				}
			break;
			
			case "cli":
				if (self::$captcha) {
					echo "\n------------------------------------------------\n";
					echo "Captcha url: ".self::$captcha['url']."\n";
					echo "Captcha sid: ".self::$captcha['sid']."\n";
					
					$sid = self::$captcha['sid'];
					
					while (true) {
						echo "Enter captcha code: ";
						$code = trim(fgets(STDIN));
						if ($code) {
							echo "OK, code: '$code'\n";
							break;
						}
					};
					
					echo "------------------------------------------------\n";
					self::$captcha = false;
					
					return [
						'key'		=> $code, 
						'sid'		=> $sid, 
					];
				}
			break;
			
			default:
				if (isset($_REQUEST['vk_captcha_key']) && isset($_REQUEST['vk_captcha_sid'])) {
					return [
						'key'		=> $_REQUEST['vk_captcha_key'], 
						'sid'		=> $_REQUEST['vk_captcha_sid'], 
					];
				}
			break;
		}
		return false;
	}
}
