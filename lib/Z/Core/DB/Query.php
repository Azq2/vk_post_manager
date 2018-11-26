<?php
namespace Z\Core\DB;

class Query {
	protected $db, $params;
	protected $fetch_type = Result::FETCH_ASSOC;
	
	public function __construct($query = NULL, $params = NULL, $db = NULL) {
		$this->query = $query;
		$this->params = $params;
		$this->db = $db;
	}
	
	public function compile() {
		return $this->params ? strtr($this->query, $this->params) : $this->query;
	}
	
	public function __toString() {
		return $this->compile();
	}
	
	public function asList() {
		$this->fetch_type = Result::FETCH_ARRAY;
		return $this;
	}
	
	public function asArray() {
		$this->fetch_type = Result::FETCH_ASSOC;
		return $this;
	}
	
	public function asObject($class_name = 'stdClass') {
		$this->fetch_type = $class_name;
		return $this;
	}
	
	public function getDB($db = NULL) {
		if (is_null($db))
			$db = $this->db;
		if (!is_object($db))
			$db = \Z\Core\DB::instance($db);
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
