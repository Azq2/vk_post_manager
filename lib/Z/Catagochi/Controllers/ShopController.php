<?php
namespace Z\Catagochi\Controllers;

use \Z\Catagochi;

class ShopController extends Catagochi\GameController {
	public function indexAction($message) {
		$action = $message->router([
			"1|породистые|коты"			=> ["shop/cats", ['type' => 'branded']],
			"2|корм|еда"				=> ["shop/items", ['type' => 'food']],
			"3|мебель"					=> ["shop/items", ['type' => 'furniture']],
			"4|игрушки"					=> ["shop/items", ['type' => 'toys']],
			"0|меню|назад"				=> "main/menu",
		]);
		if ($action)
			return $action;
		
		$this->app->reply($this->app->L("shop_menu"));
	}
}

