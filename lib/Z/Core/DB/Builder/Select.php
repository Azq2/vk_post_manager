<?php
namespace Z\Core\DB\Builder;

class Select extends \Z\Core\DB\Builder {
	protected $select = [];
	protected $order = [];
	protected $group = [];
	protected $for_update = false;
	protected $from = [];
	protected $limit = NULL;
	protected $offset = NULL;
	protected $calc_found_rows = false;
	protected $db;
	
	use Traits\Where;
	use Traits\Having;
	use Traits\Join;
	
	public function __construct($select = [], $db = NULL) {
		$this->db = $db;
		
		if ($select)
			$this->selectArray($select);
	}
	
	public function select() {
		return $this->selectArray(func_get_args());
	}
	
	public function selectArray(array $select) {
		foreach ($select as $field)
			$this->select[] = $field;
		return $this;
	}
	
	public function from($table) {
		$this->from = func_get_args();
		return $this;
	}
	
	public function calcFoundRows($calc_found_rows = true) {
		$this->calc_found_rows = $calc_found_rows;
		return $this;
	}
	
	public function forUpdate($for_update = true) {
		$this->for_update = $for_update;
		return $this;
	}
	
	public function limit($limit) {
		$this->limit = $limit;
		return $this;
	}
	
	public function offset($offset) {
		$this->offset = $offset;
		return $this;
	}
	
	public function order($field, $order = NULL) {
		$this->order[] = [$field, $order];
		return $this;
	}
	
	public function group($field) {
		$this->group[] = $field;
		return $this;
	}
	
	public function compile($db = NULL) {
		$db = $this->getDB($db);
		
		$result = ["SELECT"];
		
		if ($this->calc_found_rows)
			$result[] = "SQL_CALC_FOUND_ROWS";
		
		$result[] = $this->select ? implode(", ", array_map([$db, 'quoteColumn'], $this->select)) : "*";
		
		if ($this->from)
			$result[] = "FROM ".implode(", ", array_map([$db, 'quoteTable'], $this->from));
		
		if ($this->join) {
			$tmp = [];
			foreach ($this->join as $s) {
				$tmp[] =
					($s[1] ? $s[1]." JOIN" : "JOIN")." ".$db->quoteTable($s[0]).
					($s[2] ? " ON (".$this->compileJoinPredicate($db, $s[2]).")" : "");
			}
			$result[] = implode(", ", $tmp);
		}
		
		if ($this->where)
			$result[] = "WHERE ".$this->compilePredicate($db, $this->where);
		
		if ($this->group)
			$result[] = "GROUP BY ".implode(", ", array_map([$db, 'quoteColumn'], $this->group));
		
		if ($this->having)
			$result[] = "HAVING ".$this->compilePredicate($db, $this->having);
		
		if ($this->order) {
			$tmp = [];
			foreach ($this->order as $s)
				$tmp[] = $db->quoteColumn($s[0]).($s[1] ? " ".$s[1] : "");
			$result[] = "ORDER BY ".implode(", ", $tmp);
		}
		
		if (!is_null($this->limit))
			$result[] = "LIMIT ".$this->limit;
		
		if (!is_null($this->offset))
			$result[] = "OFFSET ".$this->offset;
		
		if ($this->for_update)
			$result[] = "FOR UPDATE";
		
		return implode(" ", $result);
	}
}
