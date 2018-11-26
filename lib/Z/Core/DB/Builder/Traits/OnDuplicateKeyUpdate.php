<?php
namespace Z\Core\DB\Builder\Traits;

trait OnDuplicateKeyUpdate {
	protected $on_duplicate = [];
	
	public function onDuplicateSet($field, $op = NULL, $value = NULL) {
		switch (func_num_args()) {
			case 1:
				if (is_array($field)) {
					foreach ($field as $k => $v)
						$this->on_duplicate[$k] = ['=', $v];
					return $this;
				}
			break;
			
			case 2:
				$this->on_duplicate[$field] = ['=', $op];
				return $this;
			break;
			
			case 3:
				$this->on_duplicate[$field] = [$op, $value];
				return $this;
			break;
		}
		
		throw new \Z\Core\DB\Exception("Invalid arguments.");
	}
	
	public function onDuplicateIncr($field, $value = 1) {
		return $this->onDuplicateSet($field, '+', $value);
	}
	
	public function onDuplicateDecr($field, $value = 1) {
		return $this->onDuplicateSet($field, '-', $value);
	}
	
	public function onDuplicateBitUp($field, $bit) {
		return $this->onDuplicateSet($field, '|', $bit);
	}
	
	public function onDuplicateBitDown($field, $bit) {
		return $this->onDuplicateSet($field, '&~', $bit);
	}
	
	public function onDuplicateSetValues($field) {
		if (is_array($field)) {
			foreach ($field as $k)
				$this->on_duplicate[$k] = ['VALUES'];
			return $this;
		}
		$this->on_duplicate[$field] = ['VALUES'];
		return $this;
	}
}
