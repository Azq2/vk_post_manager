<?php
namespace Smm\Task\Grabber;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;
use \Z\Net\Anticaptcha;

use \Smm\VK\Captcha;

class VK extends \Z\Task {
	protected $api, $api_index = 0, $has_free_api = true;
	
	public function run($args) {
		if (!\Smm\Utils\Lock::lock(__CLASS__)) {
			echo "Already running.\n";
			return;
		}
		
		echo date("Y-m-d H:i:s")." - start.\n";
		
		$this->api = [
			new \Smm\VK\API(\Smm\Oauth::getAccessToken('VK_GRABBER')), 
			new \Smm\VK\API(\Smm\Oauth::getAccessToken('VK_GRABBER_2')), 
		];
		
		// Граббим так же и свои группы, поэтому добавим их в список
		$all_groups = DB::select('id', 'name')
			->from('vk_groups')
			->where('deleted', '=', 0)
			->execute();
		
		foreach ($all_groups as $group) {
			DB::insert('vk_grabber_sources')
				->ignore()
				->set([
					'value'			=> -$group['id'], 
					'type'			=> \Smm\Grabber::SOURCE_VK, 
					'name'			=> $group['name'], 
					'url'			=> 'https://vk.com/public'.$group['id'], 
					'avatar'		=> '/images/grabber/avatar/VK.png', 
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
		$max_api_calls = 10;
		$execute_api_limit = 25;
		
		$api_calls = 0;
		$chunk = 100;
		$offset = 0;
		
		$counters = [];
		$ended = [];
		$group_updated = [];
		
		$group_to_source = [];
		foreach ($sources as $id => $source)
			$group_to_source[$source['value']] = $source['id'];
		
		while (true) {
			$new_posts = 0;
			$code_chunks = [];
			
			// Формируем чанки
			foreach ($group_to_source as $vk_id => $source_id) {
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
					
					$time = microtime(true);
					
					$ok = false;
					for ($i = 0; $i < 10; ++$i) {
						$res = $this->getApi()->exec("execute", [
							"code" => 'return {'.implode(",", $js_code).'};'
						]);
						
						$this->printExecuteErrors($res);
						
						if ($this->hasExecuteError($res, \Smm\VK\API\Response::VK_ERR_RATE_LIMIT))
							$this->nextApi();
						
						if ($res->success()) {
							$ok = true;
							echo "=> OK\n";
							break;
						} else {
							$sleep = false;
							
							if ($this->hasExecuteError($res, \Smm\VK\API\Response::VK_ERR_FLOOD))
								break;
							
							if ($this->hasExecuteError($res, \Smm\VK\API\Response::VK_ERR_TOO_FAST))
								$sleep = true;
							
							if (!$this->hasFreeApi() && $this->hasExecuteError($res, \Smm\VK\API\Response::VK_ERR_RATE_LIMIT))
								break;
							
							sleep($sleep ? 60 : 1);
						}
					}
					$time = microtime(true) - $time;
					
					++$api_calls;
					
					if ($ok) {
						echo "=> OK (".round($time, 4)." s)\n";
						
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
								
								$ok = \Smm\Grabber::addNewPost((object) [
									'source_id'			=> $group_to_source[$item->owner_id], 
									'source_type'		=> \Smm\Grabber::SOURCE_VK, 
									'remote_id'			=> $item->owner_id."_".$item->id, 
									
									'text'				=> $item->text, 
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
							
							if (!isset($group_updated[$gid])) {
								foreach ($response->groups as $item) {
									if (-$item->id == $gid) {
										DB::update('vk_grabber_sources')
											->set([
												'name'		=> htmlspecialchars($item->name), 
												'avatar'	=> $item->photo_50
											])
											->where('id', '=', $group_to_source[$gid])
											->execute();
										
										$group_updated[$gid] = true;
										break;
									}
								}
							}
							
							if ($code_chunks[$gid]['mode'] == 'fetch_all') {
								DB::update('vk_grabber_sources_progress')
									->set([
										'offset'		=> $code_chunks[$gid]['chunk'][0] + count($response->items), 
										'done'			=> !count($response->items)
									])
									->where('source_id', '=', $group_to_source[$gid])
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
				
				if (count($ended) != count($group_to_source))
					sleep(10);
			} else {
				echo "done.\n";
				break;
			}
			
			$offset += $chunk;
		}
	}
	
	public function getApi() {
		return $this->api[$this->api_index];
	}
	
	public function nextApi() {
		++$this->api_index;
		
		if ($this->api_index >= count($this->api)) {
			$this->api_index = 0;
			$this->has_free_api = false;
		}
	}
	
	public function hasFreeApi() {
		return $this->has_free_api;
	}
	
	public function hasExecuteError($response, $code) {
		if ($response->errorCode() == $code)
			return true;
		if (isset($response->execute_errors)) {
			foreach ($response->execute_errors as $err) {
				if ($err->error_code == $code)
					return true;
			}
		}
		return false;
	}
	
	
	public function printExecuteErrors($response) {
		if ($response->error())
			echo "=> api error: ".$response->error()."\n";
		
		if (isset($response->execute_errors)) {
			$errors_list = [];
			foreach ($response->execute_errors as $err)
				$errors_list[] = "=> api error: ".$err->method.": #".$err->error_code." ".$err->error_msg;
			$errors_list = array_unique($errors_list);
			
			echo implode("\n", $errors_list)."\n";
		}
	}
}
