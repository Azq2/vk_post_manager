<?php
namespace Z\DB;

class Connection {
	protected static $instances = [];
	protected $config;
	
	protected function __construct($name) {
		$this->config = \Z\Config::get('database', $name);
		$provider = strpos($this->config['provider'], "\\") !== false ? $this->config['provider'] : '\\Z\\DB\\Provider\\'.$this->config['provider'];
		$this->provider = new $provider($this->config);
	}
	
	public static function instance($name = 'default') {
		if (!isset(self::$instances[$name]))
			self::$instances[$name] = new Connection($name);
		return self::$instances[$name];
	}
	
	public function exec($value, $params = []) {
		if ($params)
			$value = strtr($value, $params);
		return $this->provider->query($value);
	}
	
	public function begin() {
		return $this->provider->begin();
	}
	
	public function commit() {
		return $this->provider->commit();
	}
	
	public function rollback() {
		return $this->provider->rollback();
	}
	
	public function quoteTable($value) {
		if ($value instanceof Expression) {
			return $value->compile($this);
		} elseif ($value instanceof Query) {
			return "(".$value->compile($this).")";
		} elseif (is_array($value)) {
			return $value[0]." AS ".$this->quoteTable($value[1]);
		}
		return implode(".", array_map(function ($v) {
			return $this->provider->quoteTable($v);
		}, explode(".", "$value")));
	}
	
	public function quoteColumn($value) {
		if ($value instanceof Expression) {
			return $value->compile($this);
		} elseif ($value instanceof Query) {
			return "(".$value->compile($this).")";
		} elseif (is_array($value)) {
			return $value[0]." AS ".$this->quoteColumn($value[1]);
		}
		return implode(".", array_map(function ($v) {
			return $this->provider->quoteColumn($v);
		}, explode(".", "$value")));
	}
	
	public function quote($v) {
		if (is_null($v)) {
			return "NULL";
		} elseif (is_bool($v)) {
			return $v ? "'1'" : "'0'";
		} elseif (is_object($v)) {
			if ($v instanceof Expression) {
				return $v->compile($this);
			} elseif ($v instanceof Query) {
				return "(".$v->compile($this).")";
			}
			
			if (method_exists($v, '__toString'))
				return $this->quote($v->__toString());
		} elseif (is_int($v) || is_float($v)) {
			return "$v";
		}
		return $this->provider->quote($v);
	}
	
	public function escape($v) {
		return $this->provider->quote($v);
	}
	
	public function query() {
		$args = func_get_args();
		$sql = array_shift($args);
		return new Builder\Query($sql, $args, $this);
	}
	
	public function expr($query = NULL, $params = NULL) {
		return new Expression($query, $params, $this);
	}
	
	public function select() {
		return new Builder\Select(func_get_args(), $this);
	}
	
	public function update($table) {
		return new Builder\Update($table, $this);
	}
	
	public function insert($table, $fields = false) {
		return $fields ? new Builder\BulkInsert($table, $fields, $this) : new Builder\Insert($table, $this);
	}
	
	public function delete($table) {
		return new Builder\Delete($table, $this);
	}
}
