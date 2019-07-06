<?php
namespace Smm\VK;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;
use \Z\Net\VkApi;

class AppsWidget {
	public static function get($group_id) {
		$group = DB::select()
			->from('vk_groups')
			->where('id', '=', $group_id)
			->execute()
			->current();
		
		switch ($group['widget']) {
			case "top_users":
				
				
				
				return [
					'type'			=> 'text', 
					'code'			=> '
						return { 
							"title": "Цитата", 
							"text": "Текст цитаты" 
						};
					'
				];
			break;
		}
		
		return false;
	}
}
