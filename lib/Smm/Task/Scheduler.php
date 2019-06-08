<?php
namespace Smm\Task;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;
use \Z\Net\VkApi;
use \Z\Net\Anticaptcha;

use \Smm\VK\Captcha;

class Scheduler extends \Z\Task {
	const SCHED_LIMIT		= 2;
	const API_FAIL_TRIES	= 10;
	
	public function options() {
		return [
			'anticaptcha' => 0
		];
	}
	
	public function run($args, $bug = false) {
		if (!\Smm\Utils\Lock::lock(__CLASS__) && !$bug) {
			echo "Already running.\n";
			return;
		}
		
		echo "=========== ".date("Y-m-d H:i:s")." ===========\n";
		
		Captcha::setMode($args['anticaptcha'] ? 'anticaptcha' : 'cli');
		
		$api = new VkApi(\Smm\Oauth::getAccessToken('VK_SCHED'));
		
		$groups_with_queue = DB::select('group_id')
			->from('vk_posts_queue')
			->group('group_id')
			->execute()
			->asArray(NULL, 'group_id');
		
		if (!$groups_with_queue) {
			echo "No groups with queue.\n";
			return;
		}
		
		$groups = DB::select()
			->from('vk_groups')
			->where('id', 'IN', $groups_with_queue)
			->execute();
		
		foreach ($groups as $group) {
			echo "[VK:".$group['id']."]\n";
			
			for ($i = 0; $i < 10; ++$i) {
				$comments = \Smm\VK\Posts::getAll($api, $group['id']);
				if ($comments->success)
					break;
				echo "Can't get VK postponed posts: ".$comments->error."\n";
			}
			
			if (!$comments->success) {
				// Пропускаем это сообщество
				echo "skip this buggy comm...\n";
				continue;
			}
			
			echo "postponed:\n";
			foreach ($comments->postponed as $p) {
				echo "[".$p->post_type."] https://vk.com/wall".$p->owner_id."_".$p->id." | ".date("d/m/Y H:i", $p->date)." | ".date("d/m/Y H:i", $p->orig_date)." ".($p->special ? "[SPECIAL]" : "")."\n";
			}
			
			echo "suggests:\n";
			foreach ($comments->suggests as $p) {
				echo "[".$p->post_type."] https://vk.com/wall".$p->owner_id."_".$p->id." | ".date("d/m/Y H:i", $p->date)." | ".date("d/m/Y H:i", $p->orig_date)." ".($p->special ? "[SPECIAL]" : "")."\n";
			}
			
			$limit = 0;
			foreach ($comments->postponed as $item) {
				if (!in_array($item->post_type, ['postpone', 'suggest']) || $item->special)
					continue;
				
				if (abs($item->date - $item->orig_date) > 60) { // Нужно пофиксить время
					echo "[NEW] QUEUE: #".$item->id." at ".date("d/m/Y H:i", $item->date)." (diff=".($item->date - $item->orig_date).")\n";
					
					// Ищем посты, которые перекрывают нужный нам
					$overlaps = [];
					foreach (array_merge($comments->postponed, $comments->suggests) as $p) {
						if ($p->post_type == 'postpone' && !$p->special && abs($item->date - $p->orig_date) <= 60) { // Нужны только отложенные посты
							if ($p->id != $item->id) // Пропускаем себя же
								$overlaps[] = $p;
						}
					}
					
					// Меняем время таким постам на левое
					foreach ($overlaps as $p) {
						$fake_date = \Smm\VK\Posts::getFakeDate($group['id']);
						
						echo "\t=> fix overlaped post #".$p->id." ".date("d/m/Y H:i", $p->date)." -> ".date("d/m/Y H:i", $fake_date)."\n";
						
						$p->date = $fake_date;
						
						$post_meta = \Smm\VK\Posts::getWallPostMeta($p);
						if (!$post_meta) {
							echo "\t=> invalid post data: ".json_encode($p, JSON_PRETTY_PRINT)."\n";
							continue;
						}
						
						for ($i = 0; $i < self::API_FAIL_TRIES; ++$i) {
							$api_data = [
								'post_id'		=> $p->id, 
								'owner_id'		=> $p->owner_id, 
								'signed'		=> $post_meta['signed'], 
								'message'		=> $post_meta['message'], 
								'lat'			=> $post_meta['lat'], 
								'long'			=> $post_meta['long'], 
								'attachments'	=> implode(",", $post_meta['attachments']), 
								'publish_date'	=> $fake_date
							];
							
							if (($captcha_code = Captcha::getCode())) {
								$api_data['captcha_key'] = $captcha_code['key'];
								$api_data['captcha_sid'] = $captcha_code['sid'];
							}
							
							$res = $api->exec("wall.edit", $api_data);
							if ($res->success()) {
								echo "\t\t=> #".$p->id." - OK\n";
								break;
							}
							
							Captcha::set($res->captcha());
							echo "\t\t=> #".$p->id." - ERROR: ".$res->error()."\n";
							
							sleep($i + 1);
						}
						
						// Обновляем фейковое время в БД
						DB::update('vk_posts_queue')
							->set('fake_date', $fake_date)
							->where('group_id', '=', $group['id'])
							->where('id', '=', $p->id)
							->execute();
					}
					
					$post_meta = \Smm\VK\Posts::getWallPostMeta($item);
					if (!$post_meta) {
						echo "\t=> invalid post data: ".json_encode($item, JSON_PRETTY_PRINT)."\n";
						continue;
					}
					
					for ($i = 0; $i < self::API_FAIL_TRIES; ++$i) {
						$api_data = [
							'post_id'		=> $item->id, 
							'owner_id'		=> $item->owner_id, 
							'signed'		=> $post_meta['signed'], 
							'message'		=> $post_meta['message'], 
							'lat'			=> $post_meta['lat'], 
							'long'			=> $post_meta['long'], 
							'attachments'	=> implode(",", $post_meta['attachments']), 
							'publish_date'	=> $item->date <= time() + 60 ? time() + 60 : $item->date
						];
						
						if ($item->date <= time() + 60) {
							echo "\t=> #".$p->id." - FORCE PUBLISH POST\n";
							$limit = 9999; // выходим из очереди
						}
						
						if (($captcha_code = Captcha::getCode())) {
							$api_data['captcha_key'] = $captcha_code['key'];
							$api_data['captcha_sid'] = $captcha_code['sid'];
						}
						
						$res = $api->exec("wall.edit", $api_data);
						if ($res->success()) {
							echo "\t=> #".$p->id." - OK\n";
							break;
						}
						
						Captcha::set($res->captcha());
						echo "\t=> #".$p->id." - ERROR: ".$res->error()."\n";
						
						sleep($i + 1);
					}
				} else { // Время поста уже верное
					echo "[OLD] QUEUE: #".$item->id." at ".date("d/m/Y H:i", $item->date)."\n";
				}
				
				++$limit;
				if ($limit >= min(self::SCHED_LIMIT, count($comments->postponed)))
					break;
			}
		}
	}
	
	public function log() {
		$message = date("Y-m-d H:i:s | ").call_user_func_array("sprintf", func_get_args());
		echo "$message\n";
	}
}
