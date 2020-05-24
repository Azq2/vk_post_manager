<?php
namespace Z\DB\Builder;

/*
 * #paramName - int
 * ##paramName - int array
 * 
 * :paramName - string
 * ::paramName - string array
 * */

class Query extends \Z\DB\Builder {
	protected $args;
	protected $query;
	
	public function __construct($query, $args = [], $db = NULL) {
		$this->setDB($db);
		
		$this->query = $query;
		$this->args = $args;
	}
	
	public function param($key, $value = NULL) {
		switch (func_num_args()) {
			case 1:
				if (is_array($key)) {
					foreach ($key as $k => $v)
						$this->args[$k] = $v;
					return $this;
				}
			break;
			
			case 2:
				$this->args[$key] = $value;
				return $this;
			break;
		}
		
		throw new \InvalidArgumentException("Invalid arguments.");
	}
	
	public function compile($db = NULL) {
		$db = $this->getDB($db);
		
		$replace_pairs = [];
		foreach ($this->args as $k => $v) {
			if ($k[0] == "#") {
				if ($k[1] == "#") {
					$replace_pairs[$k] = implode(", ", array_map(function ($vv) {
						return (float) $vv;
					}, $v));
				} else {
					$replace_pairs[$k] = (float) $v;
				}
			} elseif ($k[0] == ":") {
				if ($k[1] == ":") {
					$replace_pairs[$k] = implode(", ", array_map(function ($vv) use (&$db) {
						return $db->quote($vv);
					}, $v));
				} else {
					$replace_pairs[$k] = $db->quote($v);
				}
			} else {
				$replace_pairs[$k] = $db->quote($v);
			}
		}
		return strtr($this->query, $replace_pairs);
	}
}
