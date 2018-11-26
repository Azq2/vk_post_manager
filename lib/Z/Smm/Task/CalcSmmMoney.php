<?php
namespace Z\Smm\Task;

use \Z\Core\DB;
use \Z\Core\Config;
use \Z\Core\Util\Url;
use \Z\Core\Net\VkApi;

use \Z\Smm\VK\Captcha;

class CalcSmmMoney extends \Z\Core\Task {
	const PER_TOPIC = 1.4;
	
	public function run($args) {
		if (!\Z\Smm\Utils\Lock::lock(__CLASS__)) {
			echo "Already running.\n";
			return;
		}
		
		echo date("Y-m-d H:i:s")."\n";
		
		$vk = new VkApi(\Z\Smm\Oauth::getAccessToken('VK'));
		
		$groups = DB::select()
			->from('vk_groups')
			->order('pos', 'ASC')
			->execute();
		
		foreach ($groups as $group) {
			DB::insert('vk_smm_money')
				->ignore()
				->set([
					'last_date'		=> mktime(0, 0, 0, date("n"), 1, date("Y")), 
					'group_id'		=> $group['id'], 
					'money'			=> 0
				])
				->execute();
			
			$smm_money = DB::select()
				->from('vk_smm_money')
				->where('group_id', '=', $group['id'])
				->execute()
				->current();
			
			$i = 0;
			$stop = false;
			$total_topics = 0;
			while (true) {
				for ($tt = 0; $tt < 10; ++$tt) {
					$result = $vk->exec("wall.get", [
						'owner_id'	=> -$group['id'], 
						'count'		=> 100, 
						'offset'	=> $i
					]);
					if ($result->success())
						break;
					
					echo $group['id']." - can't get messages from wall: ".$result->error()."\n";
					sleep(1);
				}
				
				if (!$result->success()) {
					echo $group['id']." - fatal, skip group...\n";
					break;
				}
				
				foreach ($result->response->items as $item) {
					if (isset($item->is_pinned) && $item->is_pinned)
						continue;
					
					if ($item->date < $smm_money['last_date']) {
						$stop = true;
					} elseif (!$item->marked_as_ads) {
						++$total_topics;
					}
				}
				
				$i += 100;
				if ($i >= $result->response->count || $stop)
					break;
			}
			
			DB::update('vk_smm_money')
				->set('money', $total_topics * self::PER_TOPIC)
				->where('group_id', '=', $group['id'])
				->execute();
			
			echo $group['name']." ($total_topics) => ".($total_topics * self::PER_TOPIC)." р.\n";
		}
	}
}
