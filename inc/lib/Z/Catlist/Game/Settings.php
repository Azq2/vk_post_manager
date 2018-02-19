<?php
namespace Z\Catlist\Game;

use \Mysql;

class Settings {
	protected static $instance;
	protected static $defaults = [
		'max_cats' => [
			'type'	=> 'int', 
			'title'	=> 'Максимальное количество котов у пользователя', 
			'value'	=> 10
		], 
		'max_free_cats' => [
			'type'	=> 'int', 
			'title'	=> 'Максимальное количество БЕСПЛАТНЫХ котов у пользователя', 
			'value'	=> 2
		], 
		'feed_count' => [
			'type'	=> 'int', 
			'title'	=> 'Сколько раз в день ест кот', 
			'value'	=> 3
		], 
		'bath_count' => [
			'type'	=> 'int', 
			'title'	=> 'Через сколько дней нужно купать кота', 
			'value'	=> 7
		], 
		'on_page' => [
			'type'	=> 'int', 
			'title'	=> 'Количество элементов на странице', 
			'value'	=> 10
		]
	];
	protected $values, $changed;
	
	protected function __construct() {
		$this->values = [];
		
		$req = Mysql::query("SELECT * FROM `vkapp_catlist_settings`");
		while ($row = $req->fetchObject())
			$this->values[$row->key] = self::cast(self::$defaults[$row->key], $row->value);
		
		foreach (self::$defaults as $k => $d) {
			if (!isset($this->values[$k]))
				$this->values[$k] = self::cast($d, $d['value']);
		}
	}
	
	public function __unset($key) {
		throw new Exception("Wtf???");
	}
	
	public function __isset($key) {
		return isset($this->values[$key]);
	}
	
	public function __set($key, $value) {
		if (!isset($this->values[$key]))
			throw new Exception("Unknown settings key `$key`.");
		
		$value = self::cast(self::$defaults[$key], $value);
		if ($this->values[$key] !== $value) {
			$this->changed[$key] = $this->values[$key];
			$this->values[$key] = $value;
		}
	}
	
	public function __get($key) {
		if (!isset($this->values[$key]))
			throw new Exception("Unknown settings key `$key`.");
		return $this->values[$key];
	}
	
	public function save() {
		if ($this->isChanged()) {
			$pairs = [];
			foreach ($this->changed as $k => $_)
				$pairs[] = "('$k', ".Mysql::value($this->values[$k]).")";
			Mysql::query("
				INSERT INTO `vkapp_catlist_settings` (`key`, `value`)
				VALUES ".implode(", ", $pairs)."
				ON DUPLICATE KEY UPDATE
					`value` = VALUES(`value`)
			");
			$this->changed = [];
		}
		return true;
	}
	
	public function isChanged() {
		return count($this->changed) > 0;
	}
	
	public function asArray() {
		$ret = [];
		foreach (self::$defaults as $k => $v) {
			$v['value'] = $this->values[$k];
			$ret[$k] = $v;
		}
		return $ret;
	}
	
	public static function cast($def, $value) {
		switch ($def['type']) {
			case "int":
				return floor($value);
			case "float":
				return (int) $value;
			case "boolean":
				return (bool) $value;
			case "enum":
				return !isset($def['values'][$value]) ? $def['value'] : $value;
		}
		return $value;
	}
	
	public static function instance() {
		if (!self::$instance)
			self::$instance = new Settings;
		return self::$instance;
	}
}
