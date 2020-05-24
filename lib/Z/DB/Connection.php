<?php
namespace Z\DB;

class Connection {
	protected static $instances = [];
	
	protected $in_transaction = false;
	
	protected function __construct($name) {
		$config = \Z\Config::get('database', $name);
		$provider = strpos($config['provider'], "\\") !== false ? $config['provider'] : '\\Z\\DB\\Provider\\'.$config['provider'];
		$this->provider = new $provider($config);
	}
	
	public static function instance($name = 'default') {
		if (!isset(self::$instances[$name]))
			self::$instances[$name] = new Connection($name);
		return self::$instances[$name];
	}
	
	public static function getInstances() {
		return self::$instances;
	}
	
	public function exec($value) {
		return $this->provider->query($value);
	}
	
	public function inTransaction() {
		return $this->in_transaction;
	}
	
	public function begin() {
		$this->in_transaction = true;
		return $this->provider->begin();
	}
	
	public function commit() {
		$this->in_transaction = false;
		return $this->provider->commit();
	}
	
	public function rollback() {
		$this->in_transaction = false;
		return $this->provider->rollback();
	}
	
	public function quoteTable($value) {
		if ($value instanceof Builder\Expression) {
			return $value->compile($this);
		} elseif ($value instanceof Query) {
			return "(".$value->compile($this).")";
		} elseif (is_array($value)) {
			switch (count($value)) {
				// ['expression', 'field'] => "expression AS `field`"
				case 2:
					return $value[0]." AS ".$this->quoteTable($value[1]);
				break;
				
				// ['expression' => 'field'] => "`expression` AS `field`"
				case 1:
					$key = array_key_first($value);
					return $this->quoteTable($key)." AS ".$this->quoteTable($value[$key]);
				break;
			}
			
			throw new \InvalidArgumentException("Invalid arguments.");
		}
		return implode(".", array_map(function ($v) {
			return $this->provider->quoteTable($v);
		}, explode(".", "$value")));
	}
	
	public function quoteColumn($value) {
		if ($value instanceof Builder\Expression) {
			return $value->compile($this);
		} elseif ($value instanceof Query) {
			return "(".$value->compile($this).")";
		} elseif (is_array($value)) {
			switch (count($value)) {
				// ['expression', 'field'] => "expression AS `field`"
				case 2:
					return $value[0]." AS ".$this->quoteTable($value[1]);
				break;
				
				// ['expression' => 'field'] => "`expression` AS `field`"
				case 1:
					$key = array_key_first($value);
					return $this->quoteTable($key)." AS ".$this->quoteTable($value[$key]);
				break;
			}
			
			throw new \InvalidArgumentException("Invalid arguments.");
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
			if ($v instanceof Builder\Expression) {
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
	
	public function query($query, $args = []) {
		return new Builder\Query($query, $args, $this);
	}
	
	public function expr($query, $params = []) {
		return new Builder\Expression($query, $params, $this);
	}
	
	public function exprTable($table) {
		return new Builder\Expression($this->quoteTable($table), [], $this);
	}
	
	public function exprColumn($table) {
		return new Builder\Expression($this->quoteColumn($table), [], $this);
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
