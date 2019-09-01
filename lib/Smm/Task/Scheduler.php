<?php
namespace Smm\Task;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;
use \Z\Net\Anticaptcha;

use \Smm\VK\Captcha;

class Scheduler extends \Z\Task {
	public function options() {
		return [
			'anticaptcha'	=> 0, 
			'from_cron'		=> 0
		];
	}
	
	public function getNextRun() {
		$time = time();
		$sched_config = \Z\Config::get('scheduler');
		return $time + ($sched_config['interval'] - ((date("i", $time) * 60 + date("s", $time)) % $sched_config['interval']));
	}
	
	public function run($args) {
		$sched_config = \Z\Config::get('scheduler');
		
		if ($args['from_cron'] && (date("i") % $sched_config['interval']) != 0)
			return;
		
		if (!\Smm\Utils\Lock::lock(__CLASS__)) {
			echo "Already running.\n";
			return;
		}
		
		echo "=========== ".date("Y-m-d H:i:s")." ===========\n";
		
		Captcha::setMode($args['anticaptcha'] ? 'anticaptcha' : 'cli');
		
		$strange_posts = [];
		
		do {
			$need_rerun = false;
			
			$api = new \Smm\VK\API(\Smm\Oauth::getAccessToken('VK_SCHED'));
			
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
				->where('deleted', '=', 0)
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
							
							for ($i = 0; $i < $sched_config['max_api_fails']; ++$i) {
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
						
						for ($i = 0; $i < $sched_config['max_api_fails']; ++$i) {
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
							
							var_dump($post_meta, $api_data);
							
							$deadline = round($this->getNextRun() + $sched_config['interval'] / 2);
							
							if (!isset($strange_posts[$item->owner_id.":".$item->id]) && $deadline >= $item->date) {
								echo "\t=> #".$p->id." - publish date ".date("Y-m-d H:i:s", $item->date)." is too early, possible bug (deadline: ".date("Y-m-d H:i:s", $deadline).").\n";
								$limit = 9999; // выходим из очереди
								$need_rerun = true;
								$strange_posts[$item->owner_id.":".$item->id] = true;
								break;
							}
							
							if (($captcha_code = Captcha::getCode())) {
								$api_data['captcha_key'] = $captcha_code['key'];
								$api_data['captcha_sid'] = $captcha_code['sid'];
							}
							
							$res = $api->exec("wall.edit", $api_data);
							if ($res->success()) {
								echo "\t=> #".$p->id." - OK\n";
								
								DB::update('vk_posts_queue')
									->set([
										'real_date'		=> $api_data['publish_date']
									])
									->where('group_id', '=', $group['id'])
									->where('id', '=', $item->id)
									->execute();
								
								break;
							}
							
							Captcha::set($res->captcha());
							echo "\t=> #".$p->id." - ERROR: ".$res->error()."\n";
							
							sleep($i + 1);
						}
					} else { // Время поста уже верное
						echo "[OLD] QUEUE: #".$item->id." at ".date("d/m/Y H:i", $item->date)."\n";
						
						DB::update('vk_posts_queue')
							->set([
								'real_date'		=> $item->orig_date
							])
							->where('group_id', '=', $group['id'])
							->where('id', '=', $item->id)
							->execute();
					}
					
					++$limit;
					if ($limit >= min($sched_config['limit'], count($comments->postponed)))
						break;
				}
			}
			
			if (!$need_rerun)
				break;
			
			sleep(60);
		} while (true);
		
		DB::begin();
		$old_queue = DB::select()
			->from('vk_posts_queue')
			->where('fake_date', '<=', time() - 3600 * 24 * 7)
			->execute();
		
		foreach ($old_queue as $row) {
			DB::delete('vk_posts_queue')
				->where('id', '=', $row['id'])
				->where('group_id', '=', $row['group_id'])
				->execute();
			DB::delete('vk_posts_comments')
				->where('id', '=', $row['id'])
				->where('group_id', '=', $row['group_id'])
				->execute();
		}
		DB::commit();
	}
}
