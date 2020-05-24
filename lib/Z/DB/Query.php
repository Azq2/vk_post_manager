<?php
namespace Z\DB;

abstract class Query {
	protected $db, $fetch_params;
	protected $fetch_type = Result::FETCH_ASSOC;
	
	public abstract function compile($db = NULL);
	
	public function __toString() {
		return $this->compile();
	}
	
	public function asArray() {
		$this->fetch_type = Result::FETCH_ARRAY;
		return $this;
	}
	
	public function asAssoc() {
		$this->fetch_type = Result::FETCH_ASSOC;
		return $this;
	}
	
	public function asObject($class_name = 'stdClass', $class_params = []) {
		$this->fetch_type = $class_name;
		$this->fetch_params = $class_params;
		return $this;
	}
	
	public function setDB($db = NULL) {
		$this->db = $db;
		return $this;
	}
	
	protected function getDB($db = NULL) {
		if (is_null($db))
			$db = $this->db;
		if (!is_object($db))
			$db = \Z\DB::instance($db);
		return $db;
	}
	
	public function execute($db = NULL) {
		$db = $this->getDB($db);
		$sql = $this->compile($db);
		
		$result = $db->exec($sql);
		
		if (is_string($this->fetch_type)) {
			$result->setFetchMode(Result::FETCH_OBJECT, $this->fetch_type);
		} else {
			$result->setFetchMode($this->fetch_type);
		}
		
		return $result;
	}
}
