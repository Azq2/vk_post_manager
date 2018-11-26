<?php
namespace Z\Core\DB\Builder\Traits;

trait Join {
	protected $join = [];
	
	public function join($table, $type = NULL) {
		$this->join[] = [$table, $type, []];
		return $this;
	}
	
	public function on($field, $cond, $value) {
		return $this->andOn($field, $cond, $value);
	}
	
	public function openOnGroup() {
		return $this->andOpenOnGroup();
	}
	
	public function closeOnGroup() {
		return $this->andCloseOnGroup();
	}
	
	public function orOn($field, $cond, $value) {
		return $this->_joinPredicate('OR', [$field, $cond, $value]);
	}
	
	public function andOn($field, $cond, $value) {
		return $this->_joinPredicate('AND', [$field, $cond, $value]);
	}
	
	public function andOpenOnGroup() {
		return $this->_joinPredicate('AND', '(');
	}
	
	public function orOpenOnGroup() {
		return $this->_joinPredicate('OR', '(');
	}
	
	public function andCloseOnGroup() {
		return $this->_joinPredicate('AND', ')');
	}
	
	public function orCloseOnGroup() {
		return $this->_joinPredicate('OR', ')');
	}
	
	private function _joinPredicate($op, $type) {
		if (!$this->join)
			throw new \Z\Core\DB\Exception("Can't use ".__FUNCTION__." without opened JOIN.");
		$this->join[count($this->join) - 1][2][] = [$op, $type];
		return $this;
	}
}
