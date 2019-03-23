<?php
namespace Z;

abstract class Task {
	abstract public function run($args);
	
	public function help($self) {
		echo "usage: $self [args]\n";
		return 0;
	}
	
	public function options() {
		return [];
	}
}
