<?php
namespace Z\DB\Builder;

class Insert extends \Z\DB\Builder {
	protected $table = NULL;
	protected $ignore = false;
	protected $db;
	
	use Traits\Where;
	use Traits\Set;
	use Traits\OnDuplicateKeyUpdate;
	
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
	
	public function compile($db = NULL) {
		$db = $this->getDB($db);
		
		$result = [];
		
		if (!is_null($this->table))
			$result[] = "INSERT ".($this->ignore ? "IGNORE " : "")."INTO ".$db->quoteColumn($this->table);
		
		if ($this->set || $this->set_expr)
			$result[] = "SET ".$this->compileSet($db, $this->set, $this->set_expr);
		
		if ($this->on_duplicate)
			$result[] = "ON DUPLICATE KEY UPDATE ".$this->compileOnDuplicate($db, $this->on_duplicate);
		
		return implode(" ", $result);
	}
}
