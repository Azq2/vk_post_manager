<?php
namespace Z\Core;

abstract class App {
	protected $controller, $action;
	
	use \Z\Core\Traits\Singleton;
	
	protected function __construct() {
		$this->init();
	}
	
	public function init() {
		
	}
	
	abstract protected function resolve($controller, $action);
	
	public function controller() {
		return $this->controller;
	}
	
	public function action() {
		return $this->action;
	}
	
	public function execute($controller_name, $action_name) {
		$run = $this->resolve($controller_name, $action_name);
		
		if (!$run)
			throw new App\Exception\NotFound("Controller $controller_name or action $action_name not found.");
		
		if (!class_exists($run['controller']))
			throw new App\Exception\NotFound("Controller $controller_name not found.");
		
		if (!method_exists($run['controller'], $run['action']))
			throw new App\Exception\NotFound("Action $action_name not found in controller $controller_name.");
		
		$class = $run['controller'];
		$method = $run['action'];
		
		$this->controller = $controller_name;
		$this->action = $action_name;
		
		$controller = new $class;
		if ($controller->before() !== false)
			$controller->$method();
		$controller->after();
	}
}
