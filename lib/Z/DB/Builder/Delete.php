<?php
namespace Z\DB\Builder;

class Delete extends \Z\DB\Builder {
	protected $table = NULL;
	protected $order = [];
	protected $limit = NULL;
	
	use Traits\Where;
	use Traits\Set;
	
	public function __construct($table = NULL, $db = NULL) {
		$this->setDB($db);
		
		if ($table)
			$this->table($table);
	}
	
	public function table($table) {
		$this->table = $table;
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
	
	public function compile($db = NULL) {
		$db = $this->getDB($db);
		
		$result = [];
		
		if (!is_null($this->table))
			$result[] = "DELETE FROM ".$db->quoteColumn($this->table);
		
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
