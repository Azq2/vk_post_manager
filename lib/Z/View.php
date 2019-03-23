<?php
namespace Z;

class View {
	protected static $global_params = [];
	protected $data = [];
	protected $name;
	
	public static function factory($name, $args = []) {
		$view = new View($name);
		if ($args)
			$view->set($args);
		return $view;
	}
	
	public function __construct($name) {
		$this->name = $name;
	}
	
	public static function setGlobal($k, $v = NULL) {
		if (is_array($k)) {
			foreach ($k as $key => $value)
				self::$global_params[$key] = $value;
		} else {
			self::$global_params[$k] = $v;
		}
	}
	
	public function set($k, $v = NULL) {
		if (is_array($k)) {
			foreach ($k as $key => $value)
				$this->data[$key] = $value;
		} else {
			$this->data[$k] = $v;
		}
		return $this;
	}
	
	public function get($k) {
		return isset($this->data[$k]) ? $this->data[$k] : NULL;
	}
	
	public function render() {
		extract($this->data, EXTR_REFS);
		extract(self::$global_params, EXTR_REFS | EXTR_SKIP);
		ob_start();
		require APP.'views/'.$this->name.'.php';
		return ob_get_clean();
	}
	
	public function __toString() {
		return $this->render();
	}
	
	public function remove($k) {
		unset($this->data[$k]);
		return $this;
	}
	
	public function __set($k, $v) {
		$this->set($k, $v);
	}
	
	public function __unset($k) {
		$this->remove($k);
	}
	
	public function isset($k) {
		return isset($this->data[$k]);
	}
	
	public function __get($k) {
		return $this->get($k);
	}
}

