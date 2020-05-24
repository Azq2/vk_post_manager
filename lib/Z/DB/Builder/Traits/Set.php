<?php
namespace Z\DB\Builder\Traits;

trait Set {
	protected $set = [];
	protected $set_expr = [];
	
	public function set($field, $op = NULL, $value = NULL) {
		switch (func_num_args()) {
			/*
				set([
					'a'	=> 1, 
					'b'	=> 2, 
				])
			*/
			case 1:
				if (is_array($field)) {
					foreach ($field as $k => $v) {
						$this->set[$k] = $v;
						unset($this->set_expr[$k]);
					}
					return $this;
				}
			break;
			
			/*
				set('a', 1)
			*/
			case 2:
				$this->set[$field] = $op;
				unset($this->set_expr[$field]);
				return $this;
			break;
			
			/*
				incr/decr/bitUp/bitDown/...
			*/
			case 3:
				$this->set_expr[$field][] = [$op, $value];
				return $this;
			break;
		}
		
		throw new \Z\DB\Exception("Invalid arguments.");
	}
}
