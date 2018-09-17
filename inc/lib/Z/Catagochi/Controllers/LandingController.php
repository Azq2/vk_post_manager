<?php
namespace Z\Catagochi\Controllers;

use \Z\Catagochi;

class LandingController extends Catagochi\GameController {
	public function startAction($message) {
		if ($message->match("играть|го|go|давай|старт"))
			return 'main/menu';
		
		$this->app->reply($this->app->L("landing"));
	}
}
