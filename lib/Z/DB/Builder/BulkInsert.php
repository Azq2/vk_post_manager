<?php
namespace Z\DB\Builder;

class BulkInsert extends \Z\DB\Builder {
	protected $table = NULL;
	protected $fields = [];
	protected $values = [];
	protected $ignore = false;
	protected $db;
	
	use Traits\OnDuplicateKeyUpdate;
	
	public function __construct($table = NULL, $fields = NULL, $db = NULL) {
		$this->db = $db;
		
		if ($table)
			$this->table($table);
		
		if ($fields)
			$this->fieldsArray($fields);
	}
	
	public function table($table) {
		$this->table = $table;
		return $this;
	}
	
	public function fields($fields) {
		return $this->fieldsArray($fields);
	}
	
	public function fieldsArray($fields) {
		$this->fields = $fields;
		return $this;
	}
	
	public function ignore($flag = true) {
		$this->ignore = $flag;
		return $this;
	}
	
	public function values($arr) {
		if (count($arr) != count($this->fields))
			throw new \Z\DB\Exception("Values count (".count($arr).") does not match fields count (".count($this->fields).").");
		$this->values[] = $arr;
		return $this;
	}
	
	public function valuesAssoc($arr) {
		$value = [];
		foreach ($this->fields as $k) {
			if (!array_key_exists($k, $arr))
				throw new \Z\DB\Exception("Key `$k` not found in values.");
			$value[] = $arr[$k];
		}
		$this->values[] = $value;
		return $this;
	}
	
	public function setValues($arr) {
		$this->values = $arr;
		return $this;
	}
	
	public function countValues() {
		return count($this->values);
	}
	
	public function compile($db = NULL) {
		$db = $this->getDB($db);
		
		$result = [];
		
		if (!is_null($this->table))
			$result[] = "INSERT ".($this->ignore ? "IGNORE " : "")."INTO ".$db->quoteColumn($this->table);
		
		if ($this->fields)
			$result[] = "(".implode(", ", array_map([$db, 'quoteTable'], $this->fields)).")";
		
		if ($this->values) {
			$count = count($this->values);
			$result[] = "VALUES";
			foreach ($this->values as $row)
				$result[] = "(".implode(", ", array_map([$db, 'quote'], $row)).")".(--$count ? "," : "");
		}
		
		if ($this->on_duplicate)
			$result[] = "ON DUPLICATE KEY UPDATE ".$this->compileOnDuplicate($db, $this->on_duplicate);
		
		return implode(" ", $result);
	}
}
