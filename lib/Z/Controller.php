<?php
namespace Z;

abstract class Controller {
	public function __construct() {
		
	}
	
	abstract public function before();
	abstract public function after();
}
