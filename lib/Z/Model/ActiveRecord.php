<?php
namespace Z\Model;

use \Z\DB;

// after delete
// before delete

// after save
// before save

abstract class ActiveRecord {
	const STATE_NEW			= 0;
	const STATE_LOADED		= 1;
	const STATE_DELETED		= 2;
	
	protected $fields;
	protected $state		= self::STATE_NEW;
	protected $changed		= [];
	protected $operations	= [];
	protected $readonly		= false;
	
	protected static $table_info_cache = [];
	
	// options
	protected static $database = NULL;
	
	/*
	 * Model create & load
	 * */
	public function __construct(array $fields = NULL) {
		// Load model from fields array
		if ($fields) {
			$default_values = $this->getDefaultValues();
			$this->fields = array_intersect_key($fields, $default_values);
			
			if (count($default_values) != $fields) {
				$err = "Invalid fields count, expected: ".implode(", ", array_keys($default_values)).", but got: ".implode(", ", array_keys($fields));
				throw new ActiveRecord\Exception($err);
			}
			
			$this->state = self::STATE_LOADED;
		}
		// Create new model
		else {
			$this->fields = self::getDefaultValues();
			$this->state = self::STATE_NEW;
		}
	}
	
	// Load one model from database by primary key
	public static function load($data) {
		$data = DB::select()
			->from(static::table())
			->where(self::pk(), '=', $data)
			->execute()
			->current();
		
		if ($data) {
			$class_name = get_called_class();
			return new $class_name($data);
		}
		
		return NULL;
	}
	
	// Load multiple models from database by primary key
	public static function loadMulti(array $data) {
		// Preserve sort
		$models = [];
		foreach ($data as $v)
			$models[$v] = NULL;
		
		$query = DB::select()
			->from(static::table())
			->where(self::pk(), 'IN', $data)
			->execute();
		
		$class_name = get_called_class();
		foreach ($query as $row)
			$models[$row[self::pk()]] = new $class_name($data);
		
		if ($data) {
			$class_name = get_called_class();
			return new $class_name($data);
		}
		
		return NULL;
	}
	
	/*
	 * Model insert & update
	 * */
	public function onBeforeSave() {
		return true;
	}
	
	public function onAfterSave() {
		return true;
	}
	
	public function save() {
		if ($this->isDeletedRecord())
			throw new ActiveRecord\Exception("Model deleted!");
		
		if (!$this->isNewRecord() && !$this->isChanged())
			return true;
		
		if (!$this->validate())
			return false;
		
		
		$db = DB::instance(static::database());
		
		try {
			$db->begin();
			
			if (!$this->_save()) {
				$db->commit();
				return true;
			}
			
			$db->rollback();
		} catch (\Throwable $e) {
			$db->rollback();
			throw $e;
		} catch (\Exception $e) {
			$db->rollback();
			throw $e;
		}
		
		return false;
	}
	
	protected function _save() {
		if (!$this->beforeSave()) {
			return false;
		}
		
		if ($this->isNewRecord()) {
			$result = DB::insert(static::table())
				->set($this->fields)
				->execute();
			
			if (self::autoIncrementKey())
				$this->fields[self::autoIncrementKey()] = $result->insertId();
			
			$this->state = self::STATE_LOADED;
		} else {
			$query = DB::update(static::table())
				->where(self::pk(), '=', $this->fields[self::pk()]);
			
			foreach ($this->changed as $k => $old_value) {
				if (isset($this->operations[$k])) {
					switch ($this->operations[$k][0]) {
						case "incr":
							if ($this->operations[$k][1] >= 0) {
								$query->incr($k, $this->operations[$k][1]);
							} else {
								$query->decr($k, $this->operations[$k][1]);
							}
						break;
						
						case "bits":
							$query->bitUp($k, $this->operations[$k][1]);
							$query->bitDown($k, $this->operations[$k][2]);
						break;
					}
				} else {
					$query->set($k, $this->fields[$k]);
				}
			}
			
			$query->execute();
			
			$this->state = self::STATE_LOADED;
		}
		
		$this->changed = [];
		$this->operations = [];
		
		$this->afterSave();
		
		return true;
	}
	
	/*
	 * Model delete
	 * */
	public function delete() {
		if ($this->isNewRecord() || $this->isChanged())
			throw new ActiveRecord\Exception("Model not saved!");
		
		if ($this->isDeletedRecord())
			throw new ActiveRecord\Exception("Model already deleted!");
		
		DB::delete(static::table())
			->where(self::pk(), '=', $this->fields[self::pk()])
			->execute();
		
		$this->state = self::STATE_DELETED;
		
		return true;
	}
	
	/*
	 * Model state
	 * */
	public function isChanged($key = NULL) {
		return $key ? array_key_exists($key, $this->changed) : !empty($this->changed);
	}
	
	public function isNewRecord() {
		return $this->state == self::STATE_NEW;
	}
	
	public function isDeletedRecord() {
		return $this->state == self::STATE_DELETED;
	}
	
	public function isLoadedRecord() {
		return $this->state == self::STATE_LOADED;
	}
	
	public function isReadOnly() {
		return $this->readonly;
	}
	
	public function setReadOnly($flag) {
		$this->readonly = $flag;
		return $this;
	}
	
	/*
	 * Model data acessors
	 * */
	public function getOldValue($k) {
		return $this->isChanged($k) ? $this->changed[$k] : $this->get($k);
	}
	
	public function get($k) {
		return $this->__get($k);
	}
	
	public function set($k, $v) {
		return $this->__set($k, $v);
	}
	
	public function __set($key, $value) {
		if ($this->isDeletedRecord())
			throw new ActiveRecord\Exception("Model deleted!");
		
		if (isset($this->operations[$key]))
			throw new ActiveRecord\Exception("Key `$key` already atomic changed");
		
		if ($this->isReadOnly())
			throw new ActiveRecord\Exception("Model is readonly!");
		
		if (!array_key_exists($key, $this->fields))
			throw new ActiveRecord\Exception("Key `$key` not found in `".static::table()."` (allowed: ".implode(", ", array_keys($this->fields)).")");
		
		if (!array_key_exists($key, $this->changed) && $this->fields[$key] !== $value)
			$this->changed[$key] = $this->fields[$key];
		$this->fields[$key] = $value;
	}
	
	public function __get($key) {
		if (!array_key_exists($key, $this->fields))
			throw new ActiveRecord\Exception("Key `$key` not found in `".static::table()."` (allowed: ".implode(", ", array_keys($this->fields)).")");
		return $this->fields[$key];
	}
	
	public function __unset($key) {
		throw new ActiveRecord\Exception("Not supported");
	}
	
	public function __isset($key) {
		return array_key_exists($key, $this->fields);
	}
	
	/*
	 * Atomic operations
	 * */
	public function incr($key, $value = 1) {
		return $this->setAtomic($key, '+', $value);
	}
	
	public function decr($key, $value = 1) {
		return $this->setAtomic($key, '-', $value);
	}
	
	public function bitUp($key, $value = 1) {
		return $this->setAtomic($key, '|', $value);
	}
	
	public function bitDown($key, $value = 1) {
		return $this->setAtomic($key, '&~', $value);
	}
	
	protected function setAtomic($key, $operation, $value) {
		if ($this->isDeletedRecord())
			throw new ActiveRecord\Exception("Model deleted!");
		
		if (!array_key_exists($key, $this->fields))
			throw new ActiveRecord\Exception("Key `$key` not found in `".static::table()."` (allowed: ".implode(", ", array_keys($this->fields)).")");
		
		if ($this->isReadOnly())
			throw new ActiveRecord\Exception("Model is readonly!");
		
		if (!isset($this->operations[$key]) && $this->isChanged($key))
			throw new ActiveRecord\Exception("Key `$key` already non-atomic changed");
		
		$old_value = $this->fields[$key];
		
		switch ($operation) {
			case "+":
				if (isset($this->operations[$key])) {
					$this->operations[$key][1] += $value;
				} else {
					if ($this->operations[$key][0] != 'incr')
						throw new ActiveRecord\Exception("Key `$key` already changed by other atomic operation");
					
					$this->operations[$key] = ['incr', $value];
				}
				
				$this->fields[$key] += $value;
			break;
			
			case "-":
				if (isset($this->operations[$key])) {
					$this->operations[$key][1] -= $value;
				} else {
					if ($this->operations[$key][0] != 'incr')
						throw new ActiveRecord\Exception("Key `$key` already changed by other atomic operation");
					
					$this->operations[$key] = ['incr', -$value];
				}
				
				$this->fields[$key] -= $value;
			break;
			
			case "|":
				if (isset($this->operations[$key])) {
					$this->operations[$key][1] |= $value;
					$this->operations[$key][2] &= ~$value;
				} else {
					if ($this->operations[$key][0] != 'bits')
						throw new ActiveRecord\Exception("Key `$key` already changed by other atomic operation");
					
					$this->operations[$key] = ['bits', $value, 0];
				}
				
				$this->fields[$key] |= $value;
			break;
			
			case "&~":
				if (isset($this->operations[$key])) {
					$this->operations[$key][1] &= ~$value;
					$this->operations[$key][2] |= $value;
				} else {
					if ($this->operations[$key][0] != 'bits')
						throw new ActiveRecord\Exception("Key `$key` already changed by other atomic operation");
					
					$this->operations[$key] = ['bits', 0, $value];
				}
				
				$this->fields[$key] &= ~$value;
			break;
		}
		
		if (!array_key_exists($key, $this->changed))
			$this->changed[$key] = $old_value;
		
		return $this;
	}
	
	/*
	 * Model metadata
	 * */
	public static function database() {
		return NULL;
	}
	
	public static abstract function table();
	
	public static function pk() {
		return self::tableInfo(static::table(), 'primary_key');
	}
	
	public static function autoIncrementKey() {
		return self::tableInfo(static::table(), 'auto_increment');
	}
	
	protected static function getDefaultValues() {
		return self::tableInfo(static::table(), 'values');
	}
	
	public static function tableInfo($table, $key = NULL) {
		if (!isset(self::$table_info_cache[$table])) {
			$info = [
				'primary_key'		=> NULL, 
				'auto_increment'	=> NULL, 
				'values'			=> [], 
				'fields'			=> []
			];
			
			$query = DB::query("SHOW COLUMNS IN :table", [":table" => DB::exprTable($table)]);
			foreach ($query->execute() as $row) {
				if ($row['Key'] == 'PRI') {
					if ($info['primary_key'])
						throw new ActiveRecord\Exception("Table $table has combined primary key, but this not supported.");
					$info['primary_key'] = $row["Field"];
				}
				
				if ($row['Extra'] == 'auto_increment')
					$info['auto_increment'] = $row["Field"];
				
				$info['values'][$row["Field"]] = $row["Default"];
				
				$info['fields'][$row["Field"]] = [
					'is_auto_increment'		=> $row['Extra'] == 'auto_increment', 
					'is_primary_key'		=> $row['Key'] == 'PRI', 
					'is_nullable'			=> $row['Null'] == 'YES', 
					'type'					=> $row['Type']
				];
			}
			
			self::$table_info_cache[$table] = $info;
		}
		return self::$table_info_cache[$table][$key];
	}
	
	/*
	 * Model data converters
	 * */
	public function asArray() {
		return $this->fields;
	}
	
	public function asObject() {
		return (object) $this->fields;
	}
}
