<?php
namespace Smm;

use \Z\View;
use \Z\DB;
use \Z\Util\Url;
use \Smm\View\Widgets;

abstract class VkAppController extends \Z\Controller {
	protected $content;
	protected $title = 'SMM';
	protected $mode = 'html';
	protected $redirect;
	
	public function before() {
		
	}
	
	public function checkStartupParams() {
		parse_str(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY), $request);
		
		$vk_params = [];
		foreach ($request as $k => $v) {
			if ($k != "sign" && $k != "hash" && $k != "a" && $k != "api_result")
				$vk_params[$k] = $v;
		}
		
		$sign = $_GET['sign'] ?? '';
		$app_id = intval($_GET['api_id'] ?? 0);
		
		$oauth_config = \Z\Config::get("oauth", $this->getAppType());
		
		$error = false;
		
		$real_sign = hash_hmac('sha256', implode("", array_values($vk_params)), $oauth_config['secret']); 
		
		if ($oauth_config['id'] != $app_id) {
			return "Приложение с id $app_id не найдено в конфиге.";
		} else if ($real_sign != $sign) {
			return "Ошибка подписи параметров VK.";
		}
		
		return false;
	}
	
	abstract public function getAppType();
	
	public function redirect($url) {
		$this->redirect = $url;
		return false;
	}
	
	public function mode($mode) {
		$this->mode = $mode;
		return $this;
	}
	
	public function after() {
		if ($this->redirect) {
			header('Content-Type: text/html; charset=utf-8');
			header('Location: '.$this->redirect);
			return;
		}
		
		switch ($this->mode) {
			case "html":
				header('Content-Type: text/html; charset=utf-8');
				echo View::factory('vk_apps/root', [
					'title'				=> $this->title, 
					'content'			=> $this->content instanceof \Z\View ? $this->content->render() : $this->content, 
				])->render();
			break;
			
			case "json":
				$this->content['__stdout'] = ob_get_clean();
				
				header('Content-Type: application/json; charset=utf-8');
				echo json_encode($this->content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			break;
		}
	}
}
