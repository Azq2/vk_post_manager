<?php
namespace Z\DB\Builder\Traits;

trait Set {
	protected $set = [];
	protected $set_expr = [];
	
	public function set($field, $op = NULL, $value = NULL) {
		switch (func_num_args()) {
			case 1:
				if (is_array($field)) {
					foreach ($field as $k => $v) {
						$this->set[$k] = $v;
						unset($this->set_expr[$k]);
					}
					return $this;
				}
			break;
			
			case 2:
				$this->set[$field] = $op;
				unset($this->set_expr[$field]);
				return $this;
			break;
			
			case 3:
				$this->set_expr[$field] = [$op, $value];
				unset($this->set[$field]);
				return $this;
			break;
		}
		
		throw new \Z\DB\Exception("Invalid arguments.");
	}
}
