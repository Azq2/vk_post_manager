<?php
namespace Z\DB\Builder;

class Update extends \Z\DB\Builder {
	protected $table = NULL;
	protected $order = [];
	protected $ignore = false;
	protected $limit = NULL;
	protected $db;
	
	use Traits\Where;
	use Traits\Set;
	
	public function __construct($table = NULL, $db = NULL) {
		$this->db = $db;
		
		if ($table)
			$this->table($table);
	}
	
	public function table($table) {
		$this->table = $table;
		return $this;
	}
	
	public function ignore($flag = true) {
		$this->ignore = $flag;
		return $this;
	}
	
	public function limit($limit) {
		$this->limit = $limit;
		return $this;
	}
	
	public function order($field, $order = NULL) {
		$this->order[] = [$field, $order];
		return $this;
	}
	
	public function incr($field, $value = 1) {
		return $this->set($field, '+', $value);
	}
	
	public function decr($field, $value = 1) {
		return $this->set($field, '-', $value);
	}
	
	public function bitUp($field, $bit) {
		return $this->set($field, '|', $bit);
	}
	
	public function bitDown($field, $bit) {
		return $this->set($field, '&~', $bit);
	}
	
	public function compile($db = NULL) {
		$db = $this->getDB($db);
		
		$result = [];
		
		if (!is_null($this->table))
			$result[] = "UPDATE ".($this->ignore ? "IGNORE " : "").$db->quoteColumn($this->table);
		
		if ($this->set || $this->set_expr)
			$result[] = "SET ".$this->compileSet($db, $this->set, $this->set_expr);
		
		if ($this->where)
			$result[] = "WHERE ".$this->compilePredicate($db, $this->where);
		
		if ($this->order) {
			$tmp = [];
			foreach ($this->order as $s)
				$tmp[] = $db->quoteColumn($s[0]).($s[1] ? " ".$s[1] : "");
			$result[] = "ORDER BY ".implode(", ", $tmp);
		}
		
		if (!is_null($this->limit))
			$result[] = "LIMIT ".$this->limit;
		
		return implode(" ", $result);
	}
}
