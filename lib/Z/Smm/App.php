<?php
namespace Z\Smm;

use \Z\Core\View;

class App extends \Z\Core\App {
	public function init() {
		$revision = 0;
		foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(APP."/www/static/")) as $file) {
			if (preg_match("/\.js$/i", $file) && is_file($file))
				$revision = max($revision, filemtime($file));
		}
		
		View::setGlobal([
			'static_path'		=> '/static/', 
			'logged'			=> false, 
			'revision'			=> $revision
		]);
	}
	
	public function resolve($controller, $action) {
		$controller = "\\Z\\Smm\\Controllers\\".implode("", array_map("ucfirst", explode("_", $controller)))."Controller";
		return ['controller' => $controller, 'action' => $action.'Action'];
	}
	
	public function route() {
		$action = isset($_GET['a']) ? strtolower(preg_replace("/[^\w\d_\/]+/", "", $_GET['a'])) : 'index';
		$sub_action = isset($_GET['sa']) ? strtolower(preg_replace("/[^\w\d_\/]+/", "", $_GET['sa'])) : '';
		
		if (strlen($sub_action))
			$action = "$action/$sub_action";
		
		$parts = explode("/", $action);
		
		try {
			$this->execute($parts[0], isset($parts[1]) ? $parts[1] : 'index');
		} catch (\Z\Core\App\Exception\NotFound $e) {
			$this->execute('index', 'index');
		}
	}
}
