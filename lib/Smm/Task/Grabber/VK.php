<?php
namespace Smm\Task\Grabber;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;
use \Z\Net\Anticaptcha;

use \Smm\VK\Captcha;

class VK extends \Z\Task {
	public function run($args) {
		exit;
		
		if (!\Smm\Utils\Lock::lock(__CLASS__)) {
			echo "Already running.\n";
			return;
		}
		
		echo date("Y-m-d H:i:s")." - start.\n";
		
		// Граббим так же и свои группы, поэтому добавим их в список
		$all_groups = DB::select('id', 'name')
			->from('vk_groups')
			->where('deleted', '=', 0)
			->execute();
		
		foreach ($all_groups as $group) {
			DB::insert('vk_grabber_sources')
				->ignore()
				->set([
					'source_type'	=> \Smm\Grabber::SOURCE_VK, 
					'source_id'		=> -$group['id'], 
				])
				->execute();
		}
		
		list ($sources_enabled, $sources_disabled) = \Smm\Grabber::getSources(\Smm\Grabber::SOURCE_VK);
		
		// Удаляем ненужные посты
		do {
			$deleted = \Smm\Grabber::cleanUnclaimedPosts(array_keys($sources_disabled));
			if ($deleted > 0)
				echo "Deleted $deleted unclaimed posts...\n";
		} while ($deleted > 0);
		
		$this->grabVK($sources_enabled);
		
		echo date("Y-m-d H:i:s")." - done.\n";
	}
	
	public function grabVK($sources) {
		$api = new \Smm\VK\API(\Smm\Oauth::getAccessToken('VK_GRABBER'));
		
		$max_api_calls = 5;
		$execute_api_limit = 25;
		
		$api_calls = 0;
		$chunk = 100;
		$offset = 0;
		
		$counters = [];
		$ended = [];
		
		$source2id = [];
		foreach ($sources as $id => $source)
			$source2id[$source['source_id']] = $source['id'];
		
		while (true) {
			$new_posts = 0;
			$code_chunks = [];
			
			// Формируем чанки
			foreach ($source2id as $vk_id => $source_id) {
				do {
					$progress = DB::select()
						->from('vk_grabber_sources_progress')
						->where('source_id', '=', $source_id)
						->execute()
						->current();
					
					if (!$progress) {
						DB::insert('vk_grabber_sources_progress')
							->ignore()
							->set([
								'source_id'		=> $source_id, 
								'offset'		=> 0, 
								'done'			=> 0
							])
							->execute();
					}
				} while (!$progress);
				
				if (!isset($ended[$vk_id])) {
					// Режим добавления новых
					if ($progress['done']) {
						$code_chunks[$vk_id] = [
							'group_id'	=> $vk_id, 
							'mode'		=> 'update_new', 
							'chunk'		=> [$offset, $offset + $chunk], 
							'code'		=> '"'.$vk_id.'": API.wall.get({
								"owner_id": '.$vk_id.', 
								"extended": true, 
								"count": '.$chunk.', 
								"offset": '.$offset.'
							})'
						];
					}
					// Режим скачивания всех
					else {
						$code_chunks[$vk_id] = [
							'group_id'	=> $vk_id, 
							'mode'		=> 'fetch_all', 
							'chunk'		=> [$progress['offset'], $progress['offset'] + $chunk], 
							'code'		=> '"'.$vk_id.'": API.wall.get({
								"owner_id": '.$vk_id.', 
								"extended": true, 
								"count": '.$chunk.', 
								"offset": '.$progress['offset'].'
							})'
						];
					}
				}
			}
			
			// Загружаем чанки из VK
			if ($code_chunks) {
				foreach (array_chunk($code_chunks, $execute_api_limit) as $code_chunk) {
					$js_code = [];
					
					echo date("Y-m-d H:i:s")." - LOAD NEW CHUNK:\n";
					foreach ($code_chunk as $code) {
						echo "  - id:".$code['group_id'].", ".$code['chunk'][0]." ... ".$code['chunk'][1]." ".$code['mode']."\n";
						$js_code[] = $code['code'];
					}
					
					$res = 0;
					$time = microtime(true);
					for ($i = 0; $i < 10; ++$i) {
						$res = $api->exec("execute", [
							"code" => 'return {'.implode(",", $js_code).'};'
						]);
						if ($res->success()) {
							break;
						} else {
							echo "=> fetch posts error: ".$res->error()."\n";
							sleep(60);
						}
					}
					$time = microtime(true) - $time;
					
					++$api_calls;
					
					if (isset($res->response)) {
						echo "=> OK (".round($time, 4)." s)\n";
						
						$used_owners = [];
						
						foreach ($res->response as $gid => $response) {
							if (!$response) {
								echo "ERROR: Can't load chunk for $gid\n";
								continue;
							}
							
							$new_posts_chunk = 0;
							
							foreach ($response->items as $item) {
								$att = \Smm\VK\Posts::normalizeAttaches($item);
								
								$gifs_cnt = $att->gifs;
								$images_cnt = $att->images;
								$attaches = $att->attaches;
								
								$used_owners[$item->owner_id] = 1;
								
								$ok = \Smm\Grabber::addNewPost((object) [
									'source_id'			=> $source2id[$item->owner_id], 
									'source_type'		=> \Smm\Grabber::SOURCE_VK, 
									'remote_id'			=> $item->owner_id."_".$item->id, 
									
									'text'				=> $item->text, 
									'owner'				=> $item->owner_id, 
									'attaches'			=> $attaches, 
									
									'time'				=> $item->date, 
									'likes'				=> (isset($item->likes) ? $item->likes->count : 0), 
									'comments'			=> (isset($item->comments) ? $item->comments->count : 0), 
									'reposts'			=> (isset($item->reposts) ? $item->reposts->count : 0), 
									'images_cnt'		=> $images_cnt, 
									'gifs_cnt'			=> $gifs_cnt
								]);
								
								if ($ok) {
									++$new_posts;
									++$new_posts_chunk;
								}
							}
							
							foreach ($response->profiles as $item) {
								if (!isset($used_owners[$item->id]))
									continue;
								
								DB::insert('vk_grabber_data_owners')
									->set([
										'id'		=> \Smm\Grabber::SOURCE_VK."_".$item->id, 
										'name'		=> $item->first_name." ".$item->last_name, 
										'url'		=> "/".$item->screen_name, 
										'avatar'	=> $item->photo_50
									])
									->onDuplicateSetValues('url')
									->onDuplicateSetValues('name')
									->onDuplicateSetValues('avatar')
									->execute();
							}
							
							foreach ($response->groups as $item) {
								if (!isset($used_owners[-$item->id]))
									continue;
								
								DB::insert('vk_grabber_data_owners')
									->set([
										'id'		=> \Smm\Grabber::SOURCE_VK."_-".$item->id, 
										'name'		=> $item->name, 
										'url'		=> "/".$item->screen_name, 
										'avatar'	=> $item->photo_50
									])
									->onDuplicateSetValues('url')
									->onDuplicateSetValues('name')
									->onDuplicateSetValues('avatar')
									->execute();
							}
							
							if ($code_chunks[$gid]['mode'] == 'fetch_all') {
								DB::update('vk_grabber_sources_progress')
									->set([
										'offset'		=> $code_chunks[$gid]['chunk'][0] + count($response->items), 
										'done'			=> !count($response->items)
									])
									->where('source_id', '=', $source2id[$gid])
									->execute();
								if (!count($response->items))
									$ended[$gid] = true;
							} else {
								if (!$new_posts_chunk)
									$ended[$gid] = true;
							}
						}
					} else {
						echo "=> ERROR (".round($time, 4)." s)\n";
					}
				}
				
				if ($api_calls >= $max_api_calls) {
					echo "done. (maximum api calls - $max_api_calls)\n";
					break;
				}
				
				if (count($ended) != count($source2id))
					sleep(10);
			} else {
				echo "done.\n";
				break;
			}
			
			$offset += $chunk;
		}
	}
}
