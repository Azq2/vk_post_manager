<?php
namespace Z\Catagochi\Controllers;

use \Z\Catagochi;

class MainController extends Catagochi\GameController {
	public function menuAction($message) {
		$action = $message->router([
			"1|дом"				=> "home/index", 
			"2|магазин"			=> "shop/index", 
			"3|приют"			=> ["shop/cats", ['type' => 'shelter']], 
			"4|оповещения"		=> "main/notifications", 
			"5|рейтинг"			=> "rating", 
			"6|бонусы|услуги"	=> "services"
		]);
		if ($action)
			return $action;
		
		$text = $this->app->L($this->user->cats ? "start_has_cats" : "start_no_cats", [
			'menu'	=> $this->app->L("menu")
		]);
		$this->app->reply($text);
	}
	
	public function notificationsAction($message) {
		$n = $message->match("1|2|3|4|5|6");
		if ($n) {
			$this->user->notify = $n - 1;
			$this->user->save();
			$this->app->reply($this->app->L("notifications_saved"));
			return 'main/menu';
		} else if ($message->match("0|меню|назад") !== false) {
			return 'main/menu';
		}
		$this->app->reply($this->app->L("notifications_menu"));
	}
}
