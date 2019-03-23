<?php
namespace Z\Model;

use \Mysql;
use \MysqlResult;

class ModelException extends \Exception {  }

abstract class ActiveRecord {
	const STATE_NEW			= 0;
	const STATE_LOADED		= 1;
	const STATE_DELETED		= 2;
	
	protected $__fields;
	protected $__state			= self::STATE_NEW;
	protected $__changed		= [];
	protected $__operations		= [];
	
	protected static $pk;
	protected static $ai;
	protected static $table;
	
	protected function __construct($fields = NULL) {
		$this->__state = self::STATE_NEW;
		$this->__fields = self::getDefaultValues();
		
		if ($fields) {
			foreach ($fields as $k => $v) {
				if (array_key_exists($k, $this->__fields))
					$this->__fields[$k] = $v;
			}
			$this->__state = self::STATE_LOADED;
		}
	}
	
	public static function createNew() {
		$class = get_called_class();
		return new $class();
	}
	
	public static function createModel($data) {
		if (!is_array($data) && !is_object($data))
			$data = Mysql::query("SELECT * FROM `".static::$table."` WHERE `".static::$pk."` = ?", $data);
		
		if ($data instanceof MysqlResult) {
			if (!$data->num())
				return NULL;
			$data = $data->fetchAssoc();
		}
		
		if (is_array($data)) {
			$class = get_called_class();
			return new $class($data);
		}
		
		throw new ModelException("Unknown data type: ".gettype($data));
	}
	
	public static function createModels($data) {
		$models = [];
		if (is_array($data) && $data && !is_array($data[0])) {
			foreach ($data as $v)
				$models[$v] = NULL;
			$data = Mysql::query("SELECT * FROM `".static::$table."` WHERE `".static::$pk."` IN (?)", $data);
		}
		
		if ($data instanceof MysqlResult) {
			$req = $data;
			if (!$req->num())
				return $models;
			$data = [];
			while ($row = $req->fetchAssoc())
				$data[] = $row;
		}
		
		if (is_array($data)) {
			$class = get_called_class();
			foreach ($data as $row) {
				if (!is_array($row))
					throw new ModelException("Unknown data type: ".gettype($row));
				
				$pk = $row[static::$pk];
				$models[$pk] = new $class($row);
			}
			return $models;
		}
		
		throw new ModelException("Unknown data type: ".gettype($data));
	}
	
	public function save() {
		if (!$this->isChanged())
			return true;
		
		if ($this->isDeletedRecord())
			throw new ModelException("Model deleted!");
		
		if ($this->isNewRecord()) {
			$req = Mysql::query(
				"INSERT INTO `".static::$table."` SET ".
				self::_buildQuery($this->__fields, $this->__fields, true)
			);
			if (static::$ai)
				$this->__fields[static::$pk] = $req->id();
			$this->__state = self::STATE_LOADED;
		} else {
			Mysql::query(
				"UPDATE `".static::$table."` SET ".self::_buildQuery($this->__changed, $this->__fields)." ".
				"WHERE `".static::$pk."` = ".Mysql::value($this->__fields[static::$pk])
			);
		}
		
		$this->onAfterSave();
		
		$this->__changed = [];
		$this->__operations = [];
		
		return true;
	}
	
	public function onAfterSave() { }
	
	public function delete() {
		if ($this->isNewRecord() || $this->isChanged())
			throw new ModelException("Model not saved!");
		
		if ($this->isDeletedRecord())
			throw new ModelException("Model already deleted!");
		
		Mysql::query("DELETE FROM `".static::$table."` WHERE `".static::$pk."` = ".Mysql::value($this->__fields[static::$pk]));
		$this->__state = self::STATE_DELETED;
		
		return true;
	}
	
	public function isChanged($key = NULL) {
		return $key ? array_key_exists($key, $this->__changed) : !empty($this->__changed);
	}
	
	private function _buildQuery($keys, $values, $insert = false) {
		$pairs = [];
		foreach ($keys as $k => $_) {
			if (isset($this->__operations[$k]) && !$insert) {
				$pairs[] = "`$k` = `$k` ".$this->__operations[$k][0]." ".$this->__operations[$k][1];
			} else {
				$pairs[] = "`$k` = ".Mysql::value($values[$k]);
			}
		}
		return implode(", ", $pairs);
	}
	
	public function getDefaultValues() {
		$defaults = apcu_fetch("ar:meta:fields:".static::$table);
		if (!$defaults) {
			$defaults = [];
			$req = Mysql::query("SHOW COLUMNS IN `".static::$table."`");
			while ($field = $req->fetchArray())
				$defaults[$field[0]] = $field[4];
			apcu_store("ar:meta:fields:".static::$table, $defaults);
		}
		return $defaults;
	}
	
	public function isNewRecord() {
		return $this->__state == self::STATE_NEW;
	}
	
	public function isDeletedRecord() {
		return $this->__state == self::STATE_DELETED;
	}
	
	public function isLoadedRecord() {
		return $this->__state == self::STATE_LOADED;
	}
	
	public function getOldValue($k) {
		return $this->isChanged($k) ? $this->__changed[$k] : $this->get($k);
	}
	
	public function get($k) {
		return $this->__get($k);
	}
	
	public function set($k, $v) {
		return $this->__set($k, $v);
	}
	
	public function __set($key, $value) {
		if ($this->isDeletedRecord())
			throw new ModelException("Model deleted!");
		
		if (isset($this->__operations[$key]))
			throw new ModelException("Key `$key` already atomic changed");
		
		if (!array_key_exists($key, $this->__fields))
			throw new ModelException("Key `$key` not found in `".static::$table."` (allowed: ".implode(", ", array_keys($this->__fields)).")");
		
		if (!array_key_exists($key, $this->__changed) && $this->__fields[$key] !== $value)
			$this->__changed[$key] = $this->__fields[$key];
		$this->__fields[$key] = $value;
	}
	
	public function __get($key) {
		if (!array_key_exists($key, $this->__fields))
			throw new ModelException("Key `$key` not found in `".static::$table."` (allowed: ".implode(", ", array_keys($this->__fields)).")");
		return $this->__fields[$key];
	}
	
	public function __unset($key) {
		throw new ModelException("Not supported");
	}
	
	public function __isset($key) {
		return array_key_exists($key, $this->__fields);
	}
	
	public function decr($key, $value = 1) {
		return $this->incr($key, -$value);
	}
	
	public function incr($key, $value = 1) {
		if (!array_key_exists($key, $this->__fields))
			throw new ModelException("Key `$key` not found in `".static::$table."` (allowed: ".implode(", ", array_keys($this->__fields)).")");
		
		if (isset($this->__operations[$key])) {
			if ($this->__operations[$key][0] != '+')
				throw new ModelException("Key `$key` already non-atomic changed");
			$this->__operations[$key][1] += $value;
		} else {
			if ($this->isChanged($key))
				throw new ModelException("Key `$key` already non-atomic changed");
			$this->__operations[$key] = ['+', $value];
		}
		
		if (!array_key_exists($key, $this->__changed))
			$this->__changed[$key] = $this->__fields[$key];
		$this->__fields[$key] += $value;
		
		return $this;
	}
	
	public function toArray() {
		return $this->__fields;
	}
	
	public function toObject() {
		return (object) $this->__fields;
	}
}
