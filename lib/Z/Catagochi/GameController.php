<?php
namespace Z\Catagochi;

class GameController {
	protected $app, $user;
	
	public function __construct($app) {
		$this->app = $app;
		$this->user = $app->user();
	}
	
	public function before() {
		
	}
	
	public function after() {
		
	}
}
