<?php
namespace Z\DB\Builder\Traits;

trait Where {
	protected $where = [];
	
	public function where($field, $cond, $value) {
		return $this->andWhere($field, $cond, $value);
	}
	
	public function openGroup() {
		return $this->andOpenGroup();
	}
	
	public function closeGroup() {
		return $this->andCloseGroup();
	}
	
	public function andWhere($field, $cond, $value) {
		$this->where[] = ['AND', [$field, $cond, $value]];
		return $this;
	}
	
	public function orWhere($field, $cond, $value) {
		$this->where[] = ['OR', [$field, $cond, $value]];
		return $this;
	}
	
	public function andOpenGroup() {
		$this->where[] = ['AND', '('];
		return $this;
	}
	
	public function orOpenGroup() {
		$this->where[] = ['OR', '('];
		return $this;
	}
	
	public function andCloseGroup() {
		$this->where[] = ['AND', ')'];
		return $this;
	}
	
	public function orCloseGroup() {
		$this->where[] = ['OR', ')'];
		return $this;
	}
}
