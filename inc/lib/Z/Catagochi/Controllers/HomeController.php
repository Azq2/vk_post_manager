<?php
namespace Z\Catagochi\Controllers;

use \Z\Catagochi;

class HomeController extends Catagochi\GameController {
	public function indexAction($message) {
		$action = $message->router([
			"1|миска|мыска|еда"			=> "home/food", 
			"2|ванная|ванна"			=> "home/bathroom", 
			"3|зал"						=> "home/hall", 
			"4|двор|дворик"				=> "home/yard", 
			"0|меню|назад"				=> "menu", 
		]);
		if ($action)
			return $action;
		
		$this->app->reply($this->app->L("home_menu"));
	}
	
	public function bathroomAction($message) {
		$action = $message->router([
			"1|лоток|туалет"						=> "home/toilet",
			"2|купать|покупать|искупать|купать"		=> "home/wash_cat",
			"0|назад|дом"							=> "home/index",
			"меню"									=> "main/menu", 
		]);
		if ($action)
			return $action;
		
		$this->app->reply($this->app->L("bathroom_menu"));
	}
	
	public function hallAction($message) {
		$action = $message->router([
			"меню"					=> "main/menu", 
			"0|назад|дом"			=> "home/index", 
			"1|магазин|купить"		=> "shop/index"
		]);
		if ($action)
			return $action;
		
		$this->app->reply($this->app->L("hall_empty"));
	}
}
