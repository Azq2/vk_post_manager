<?php
class MysqlResult {
	const RES_HASH = 0;
	const RES_ARRAY = 1;
	const RES_OBJECT = 2;
	
	protected $id;
	protected $res;
	
	public function __construct($db, $res) {
		$this->db = $db;
		$this->res = $res;
		$this->id = $this->db->insert_id;
	}
	
	public function row($assoc = true) {
		return $assoc ? $this->res->fetch_assoc() : $this->res->fetch_array(MYSQLI_NUM);
	}
	
	public function fetch($assoc = true) {
		return $assoc ? $this->res->fetch_assoc() : $this->res->fetch_array(MYSQLI_NUM);
	}
	
	public function fetchArray() {
		return $this->res->fetch_array(MYSQLI_NUM);
	}
	
	public function fetchAssoc() {
		return $this->res->fetch_assoc();
	}
	
	public function fetchObject($class_name = NULL, $params = NULL) {
		return $this->res->fetch_object($class_name, $params);
	}
	
	public function fetchAll($assoc = true) {
		$out = array();
		while ($res = ($assoc ? $this->res->fetch_assoc() : $this->res->fetch_array(MYSQLI_NUM)))
			$out[] = $res;
		return $out;
	}
	
	public function fetchAllObject($class_name = NULL, $params = NULL) {
		$out = array();
		while ($res = $this->res->fetch_object($class_name, $params))
			$out[] = $res;
		return $out;
	}
	
	public function rows($assoc = true) {
		return $this->fetchAll($assoc);
	}
	
	public function result($num = 0, $seek = 0) {
		if ($seek)
			$this->res->data_seek($seek);
		$res = $this->res->fetch_array(MYSQLI_NUM);
		return $res && isset($res[$num]) ? $res[$num] : NULL;
	}
	
	public function resultArray($num = 0) {
		$ret = array();
		while ($res = $this->res->fetch_array(MYSQLI_NUM))
			$ret[] = isset($res[$num]) ? $res[$num] : NULL;
		return $ret;
	}
	
	public function id() {
		return $this->id;
	}
	
	public function num() {
		return $this->res->num_rows;
	}
	
	public function affected() {
		return $this->db->affected_rows;
	}
	
	public function numFields() {
		return $this->res->field_count;
	}
	
	public function fields($i = NULL) {
		return $this->res->fetch_fields();
	}
	
	public function __toString() {
		$i = 0;
		$dump = __CLASS__."(".$this->num()."):\n";
		while ($res = $this->row()) {
			$dump .= "-------------- row #".$i." --------------\n";
			foreach ($res as $k => $v)
				$dump .= "$k: \"".addcslashes($v, "\n\t\t\"\0")."\"\n";
			++$i;
		}
		return $dump;
	}
	
	public function dump() {
		return $this->__toString();
	}
	
	public function free() {
		return $this->res->free();
	}
}
