<?php
namespace Z\DB\Builder\Traits;

trait Having {
	protected $having = [];
	
	public function having($field, $cond, $value) {
		return $this->andHaving($field, $cond, $value);
	}
	
	public function openHavingGroup() {
		return $this->andOpenHavingGroup();
	}
	
	public function closeHavingGroup() {
		return $this->andCloseHavingGroup();
	}
	
	public function andHaving($field, $cond, $value) {
		$this->having[] = ['AND', [$field, $cond, $value]];
		return $this;
	}
	
	public function orHaving($field, $cond, $value) {
		$this->having[] = ['OR', [$field, $cond, $value]];
		return $this;
	}
	
	public function andOpenHavingGroup() {
		$this->having[] = ['AND', '('];
		return $this;
	}
	
	public function orOpenHavingGroup() {
		$this->having[] = ['OR', '('];
		return $this;
	}
	
	public function andCloseHavingGroup() {
		$this->having[] = ['AND', ')'];
		return $this;
	}
	
	public function orCloseHavingGroup() {
		$this->having[] = ['OR', ')'];
		return $this;
	}
}
