<?php
namespace Z\Core;

abstract class Controller {
	public function __construct() {
		
	}
	
	abstract public function before();
	abstract public function after();
}
