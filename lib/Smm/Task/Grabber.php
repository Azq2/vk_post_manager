<?php
namespace Smm\Task;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;
use \Z\Net\Anticaptcha;

use \Smm\VK\Captcha;

class Grabber extends \Z\Task {
	public function options() {
		return [
			'type'		=> ''
		];
	}
	
	public function run($args) {
		if (!\Smm\Utils\Lock::lock(__CLASS__.":".$args['type'])) {
			echo "Already running.\n";
			return;
		}
		
		// Граббим так же и свои группы, поэтому добавим их в список
		$sources_selfgrab = [];
		foreach (DB::select()->from('vk_groups')->where('deleted', '=', 0)->execute() as $group) {
			DB::insert('vk_grabber_sources')
				->ignore()
				->set([
					'source_type'	=> \Smm\Grabber::SOURCE_VK, 
					'source_id'		=> -$group['id'], 
				])
				->execute();
			
			$sources_selfgrab[\Smm\Grabber::SOURCE_VK."_".-$group['id']] = true;
		}
		
		// Получаем все доступные для граббинга источники
		$sources = [];
		$sources_disabled = [];
		foreach (DB::select()->from('vk_grabber_sources')->execute() as $s) {
			$is_active = isset($sources_selfgrab[$s['source_type']."_".$s['source_id']]);
			if (!$is_active) {
				$is_active = DB::select(['MAX(enabled)', 'enabled'])
					->from('vk_grabber_selected_sources')
					->where('source_id', '=', $s['id'])
					->execute()
					->get('enabled', 0);
			}
			
			if ($is_active) {
				$sources[$s['source_type']][$s['source_id']] = $s;
			} else {
				$sources_disabled[] = $s;
			}
		}
		
		// Удаляем данные для источников, которые больше не используются
		$this->cleanUnclaimedPosts($sources_disabled);
		
		// Удаляем старые неактуальные данные
		$this->cleanOldPosts();
		
		$sort_values = [
			\Smm\Grabber::SOURCE_INSTAGRAM		=> 0, 
			\Smm\Grabber::SOURCE_PINTEREST		=> 1, 
			\Smm\Grabber::SOURCE_VK				=> 2, 
		];
		
		uksort($sources, function ($a, $b) use ($sort_values) {
			return $sort_values[$a] <=> $sort_values[$b];
		});
		
		foreach ($sources as $type => $type_sources) {
			if ($args['type'] && $args['type'] != \Smm\Grabber::$type2name[$type])
				continue;
			
			echo "======================== ".\Smm\Grabber::$type2name[$type]." ========================\n";
			
			switch ($type) {
				case \Smm\Grabber::SOURCE_VK:
					$this->grabVK($type_sources);
				break;
				
				case \Smm\Grabber::SOURCE_INSTAGRAM:
					$this->grabInstagram($type_sources);
				break;
				
				case \Smm\Grabber::SOURCE_PINTEREST:
					$this->grabPinterest($type_sources);
				break;
				
				default:
					echo "ERROR: unknown source type: $type\n";
				break;
			}
		}
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
		
		while (true) {
			$new_posts = 0;
			$code_chunks = [];
			
			// Формируем чанки
			foreach ($sources as $id => $source) {
				do {
					$progress = DB::select()
						->from('vk_grabber_sources_progress')
						->where('source_id', '=', $source['id'])
						->execute()
						->current();
					
					if (!$progress) {
						DB::insert('vk_grabber_sources_progress')
							->ignore()
							->set([
								'source_id'		=> $source['id'], 
								'offset'		=> 0, 
								'done'			=> 0
							])
							->execute();
					}
				} while (!$progress);
				
				if (!isset($ended[$id])) {
					// Режим добавления новых
					if ($progress['done']) {
						$code_chunks[$id] = [
							'group_id'	=> $id, 
							'mode'		=> 'update_new', 
							'chunk'		=> [$offset, $offset + $chunk], 
							'code'		=> '"'.$id.'": API.wall.get({
								"owner_id": '.$id.', 
								"extended": true, 
								"count": '.$chunk.', 
								"offset": '.$offset.'
							})'
						];
					}
					// Режим скачивания всех
					else {
						$code_chunks[$id] = [
							'group_id'	=> $id, 
							'mode'		=> 'fetch_all', 
							'chunk'		=> [$progress['offset'], $progress['offset'] + $chunk], 
							'code'		=> '"'.$id.'": API.wall.get({
								"owner_id": '.$id.', 
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
								
								$ok = $this->insertToDB((object) [
									'source_id'			=> $sources[$item->owner_id]['id'], 
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
									->where('source_id', '=', $sources[$gid]['id'])
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
				
				if (count($ended) != count($sources))
					sleep(10);
			} else {
				echo "done.\n";
				break;
			}
			
			$offset += $chunk;
		}
	}
	
	public function grabInstagram($sources) {
		$ch = curl_init();
		
		$jar = "/tmp/".md5(__FILE__)."-cookies.jar";
		
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER		=> true, 
			CURLOPT_ENCODING			=> "gzip", 
			CURLOPT_COOKIE				=> '', 
			CURLOPT_HEADER				=> false, 
			CURLOPT_VERBOSE				=> false, 
			CURLOPT_CONNECTTIMEOUT		=> 10, 
			CURLOPT_TIMEOUT				=> 30, 
			CURLOPT_COOKIEJAR			=> $jar, 
			CURLOPT_COOKIEFILE			=> $jar, 
			CURLOPT_USERAGENT			=> "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.75 Safari/537.36"
		]);
		
		$fetch_json = function ($ch, $url) {
			$ban_errors = 10;
			$max_tries = 20;
			
			$body = false;
			
			while (true) {
				curl_setopt($ch, CURLOPT_URL, $url);
				
				$body = curl_exec($ch);
				$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				
				if ($code == 200)
					break;
				
				if ($code == 429) {
					echo "ERROR: error 429, wait minute... ($url)\n";
					sleep(60);
					--$ban_errors;
				} else if ($code == 404) {
					echo "ERROR: 404\n";
					break;
				} else if ($code == 302) {
					echo "ERROR: unexpected redirect ($url): ".curl_getinfo($ch, CURLINFO_REDIRECT_URL)."\n";
					break;
				} else if ($code == 0) {
					echo "ERROR: connect error: ".curl_strerror(curl_errno($ch))." ($url)\n";
				} else {
					echo "ERROR: unexpected http code ($url): $code\n$body\n";
				}
				
				if (!$ban_errors || !$max_tries)
					break;
				--$max_tries;
			}
			
			if (!$ban_errors) {
				echo "ERROR: Instagram ban! ($url) :(\n";
				return false;
			}
			
			if (!$max_tries) {
				echo "ERROR: Too many errors! ($url)\n";
				return false;
			}
			
			$json = @json_decode($body);
			if (!$json) {
				echo "ERROR: Can't parse JSON ($url)\n$body\n";
				return false;
			}
			
			return $json;
		};
		
		$ended = [];
		$page = 0;
		
		while (true) {
			++$page;
			
			echo "PAGE: $page\n";
			
			$good = 0;
			foreach ($sources as $id => $source) {
				if (isset($ended[$id]))
					continue;
				
				DB::insert('vk_grabber_data_owners')
					->set([
						'id'		=> \Smm\Grabber::SOURCE_INSTAGRAM."_".$id, 
						'name'		=> "#$id", 
						'url'		=> "https://www.instagram.com/explore/tags/".urlencode($id)."/", 
						'avatar'	=> "https://www.instagram.com/static/images/ico/xxxhdpi_launcher.png/9fc4bab7565b.png"
					])
					->onDuplicateSetValues('url')
					->onDuplicateSetValues('name')
					->onDuplicateSetValues('avatar')
					->execute();
				
				echo "HASHTAG: #$id\n";
				
				$page_url = "https://www.instagram.com/explore/tags/".urlencode($id)."/?__a=1";
				
				$json = $fetch_json($ch, $page_url);
				if (!$json)
					continue;
				
				if (!isset($json->graphql, $json->graphql->hashtag)) {
					echo "ERROR: Can't find graphql->hashtag from JSON ($page_url)\n";
					var_dump($json);
					break;
				}
				
				$good2 = 0;
				
				$edges_lists = [$json->graphql->hashtag->edge_hashtag_to_top_posts->edges, $json->graphql->hashtag->edge_hashtag_to_media->edges];
				foreach ($edges_lists as $edges_list) {
					foreach ($edges_list as $edge) {
						// Получаем ID фотки
						$topic_id = $edge->node->shortcode;
						if (!$topic_id) {
							echo "ERROR: Can't parse topic id ($page_url)\n";
							continue;
						}
						
						// Видео не нужны
						if ($edge->node->is_video)
							continue;
						
						// Ссылка на фотку
						$topic_url = "https://www.instagram.com/p/$topic_id";
						
						// Дата загрузки фотки
						$date = $edge->node->taken_at_timestamp;
						
						// Описание фотки
						$text = "";
						if (isset($edge->node->edge_media_to_caption)) {
							if (isset($edge->node->edge_media_to_caption->edges, $edge->node->edge_media_to_caption->edges[0]))
								$text = $edge->node->edge_media_to_caption->edges[0]->node->text;
						}
						
						// Лайки
						$likes = $edge->node->edge_liked_by->count;
						
						// Комменты
						$comments = $edge->node->edge_media_to_comment->count;
						
						$attaches = [];
						$edges_to_parse = [];
						
						if  (isset($edge->node->__typename) && $edge->node->__typename == "GraphSidecar") {
							$ajax_topic_url = "https://www.instagram.com/p/$topic_id/?__a=1";
							
							$json = $fetch_json($ch, $ajax_topic_url);
							if (!$json)
								continue;
							
							if (!isset($json->graphql, $json->graphql->shortcode_media)) {
								echo "ERROR: Can't find graphql->shortcode_media from JSON ($ajax_topic_url)\n";
								var_dump($json);
								break;
							}
							
							foreach ($json->graphql->shortcode_media->edge_sidecar_to_children->edges as $sub_edge)
								$edges_to_parse[] = $sub_edge;
						} else {
							$edges_to_parse[] = $edge;
						}
						
						foreach ($edges_to_parse as $sub_edge) {
							$src = $sub_edge->node->display_url;
							
							if ($src) {
								$attaches[] = [
									'id'		=> 'photo_'.md5($src), 
									'type'		=> 'photo', 
									'w'			=> $sub_edge->node->dimensions->width, 
									'h'			=> $sub_edge->node->dimensions->height, 
									'thumbs' => [
										$sub_edge->node->dimensions->width => $src
									]
								];
							}
							
						}
						
						if (!$attaches) {
							echo "ERROR: #$topic_id topic without attaches ($topic_url)\n";
							continue;
						}
						
						$ok = $this->insertToDB((object) [
							'source_id'			=> $source['id'], 
							'source_type'		=> \Smm\Grabber::SOURCE_INSTAGRAM, 
							'remote_id'			=> $topic_id, 
							
							'text'				=> $text, 
							'owner'				=> $id, 
							'attaches'			=> $attaches, 
							
							'time'				=> $date, 
							'likes'				=> $likes, 
							'comments'			=> $comments, 
							'reposts'			=> 0, 
							'images_cnt'		=> 1, 
							'gifs_cnt'			=> 0
						]);
						
						echo "OK: $topic_url\n";
						
						if ($ok) {
							++$good;
							++$good2;
						}
					}
					
					if (!$good2)
						$ended[$id] = true;
				}
			}
			
			echo "good=$good\n";
			if (!$good)
				break;
			
			// Временно только одна страница!
			break;
		}
	}
	
	public function grabPinterest($sources) {
		$ch = \Smm\Grabber\Pinterest::instance()->getCurl();
		
		\Smm\Grabber\Pinterest::instance()->setRequestMode('desktop_ajax');
		
		$fetch_json = function ($ch, $url, $post = []) {
			$ban_errors = 10;
			$max_tries = 20;
			
			$body = false;
			
			while (true) {
				if ($post) {
					curl_setopt($ch, CURLOPT_POST, true);
					curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
				}
				
				curl_setopt($ch, CURLOPT_URL, $url);
				
				$body = curl_exec($ch);
				$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				
				if ($post)
					curl_setopt($ch, CURLOPT_POST, false);
				
				if ($code == 200)
					break;
				
				if ($code == 429) {
					echo "ERROR: error 429, wait minute... ($url)\n";
					sleep(60);
					--$ban_errors;
				} else if ($code == 404) {
					echo "ERROR: 404\n";
					break;
				} else if ($code == 302) {
					echo "ERROR: unexpected redirect ($url): ".curl_getinfo($ch, CURLINFO_REDIRECT_URL)."\n";
				} else if ($code == 0) {
					echo "ERROR: connect error: ".curl_strerror(curl_errno($ch))." ($url)\n";
				} else {
					echo "ERROR: unexpected http code ($url): $code\n$body\n";
				}
				
				if (!$ban_errors || !$max_tries)
					break;
				--$max_tries;
			}
			
			if (!$ban_errors) {
				echo "ERROR: Pinterest ban! ($url) :(\n";
				return false;
			}
			
			if (!$max_tries) {
				echo "ERROR: Too many errors! ($url)\n";
				return false;
			}
			
			$json = @json_decode($body);
			if (!$json) {
				echo "ERROR: Can't parse JSON ($url)\n$body\n";
				return false;
			}
			
			return $json;
		};
		
		$next = [];
		$ended = [];
		$page = 0;
		
		while (true) {
			++$page;
			
			echo "PAGE: $page\n";
			
			$good = 0;
			foreach ($sources as $id => $source) {
				if (isset($ended[$id]))
					continue;
				
				DB::insert('vk_grabber_data_owners')
					->set([
						'id'		=> \Smm\Grabber::SOURCE_PINTEREST."_".$id, 
						'name'		=> "$id", 
						'url'		=> "https://www.pinterest.ru/search/pins/?rs=ac&len=2&q=".urlencode($id), 
						'avatar'	=> "https://s.pinimg.com/webapp/style/images/logo_trans_144x144-642179a1.png"
					])
					->onDuplicateSetValues('url')
					->onDuplicateSetValues('name')
					->onDuplicateSetValues('avatar')
					->execute();
				
				echo "SEARCH: $id\n";
				
				$page_url = "https://www.pinterest.ru/resource/BaseSearchResource/get/?".http_build_query([
					'source_url'		=> "/search/pins/?rs=ac&len=2&q=".urlencode($id), 
					'data'				=> json_encode([
						'options'		=> $next[$id] ?? [
							'isPrefetch'		=> false, 
							'query'				=> $id, 
							'scope'				=> 'pins'
						], 
						'context'		=> (object) []
					]), 
					'_'					=> round(microtime(true) * 1000)
				], '', '&');
				
				$json = $fetch_json($ch, $page_url);
				
				$next[$id] = $json->resource->options ?? false;
				
				if (!$next[$id])
					$ended[$id] = true;
				
				if (!$json)
					continue;
				
				if (!isset($json->resource_response, $json->resource_response->data,  $json->resource_response->data->results)) {
					echo "ERROR: Can't find resource_response->data->results from JSON ($page_url)\n";
					var_dump($json);
					break;
				}
				
				$good2 = 0;
				
				foreach ($json->resource_response->data->results as $result) {
					// Получаем ID фотки
					$topic_id = $result->id;
					if (!$topic_id) {
						echo "ERROR: Can't parse topic id ($page_url)\n";
						continue;
					}
					
					if (!isset($result->created_at) || !$result->created_at) {
						echo "ERROR: Can't parse result ($page_url)\n";
						continue;
					}
					
					// Ссылка на фотку
					$topic_url = "https://www.pinterest.ru/pin/$topic_id";
					
					// Дата загрузки фотки
					$date = strtotime($result->created_at);
					
					// Описание фотки
					$text = trim($result->title."\n".$result->description);
					
					// Лайки
					$likes = 0;
					
					// Комменты
					$comments = 0;
					
					$thumbs = [];
					foreach ($result->images as $i)
						$thumbs[$i->width] = $i->url;
					
					$attaches = [];
					
					if ($thumbs) {
						$attaches[] = [
							'id'		=> 'photo_'.md5($result->images->orig->url), 
							'type'		=> 'photo', 
							'w'			=> $result->images->orig->width, 
							'h'			=> $result->images->orig->height, 
							'thumbs'	=> $thumbs
						];
					}
					
					if (!$attaches) {
						echo "ERROR: #$topic_id topic without attaches ($topic_url)\n";
						continue;
					}
					
					$ok = $this->insertToDB((object) [
						'source_id'			=> $source['id'], 
						'source_type'		=> \Smm\Grabber::SOURCE_PINTEREST, 
						'remote_id'			=> $topic_id, 
						
						'text'				=> $text, 
						'owner'				=> $id, 
						'attaches'			=> $attaches, 
						
						'time'				=> $date, 
						'likes'				=> 0, 
						'comments'			=> 0, 
						'reposts'			=> 0, 
						'images_cnt'		=> 1, 
						'gifs_cnt'			=> 0
					]);
					
					echo "OK: $topic_url\n";
					
					if ($ok) {
						++$good;
						++$good2;
					}
				}
				
				if (!$good2)
					$ended[$id] = true;
			}
			
			echo "good=$good\n";
			if (!$good)
				break;
			
			if ($page > 50) {
				echo "Pages limit.\n";
				break;
			}
		}
		
		echo "done.\n";
	}
	
	public function cleanUnclaimedPosts($sources_disabled) {
		foreach ($sources_disabled as $source) {
			do {
				$rows = DB::select('id', 'data_id')
					->from('vk_grabber_data_index')
					->where('source_id', '=', $source['id'])
					->execute()
					->asArray();
				
				$post_ids = array_map(function ($v) { return $v['id']; }, $rows);
				$data_ids = array_map(function ($v) { return $v['data_id']; }, $rows);
				
				if ($rows) {
					echo \Smm\Grabber::$type2name[$source['source_type']]." ".$source['source_id'].": delete ".count($rows)." unclaimed posts...\n";
					DB::delete('vk_grabber_data')
						->where('id', 'IN', $data_ids)
						->execute();
					DB::delete('vk_grabber_data_index')
						->where('id', 'IN', $post_ids)
						->execute();
				}
			} while ($rows);
		}
	}
	
	public function cleanOldPosts() {
		do {
			$rows = DB::select('id', 'data_id')
				->from('vk_grabber_data_index')
				->where('source_type', 'IN', [\Smm\Grabber::SOURCE_INSTAGRAM, \Smm\Grabber::SOURCE_PINTEREST])
				->where('grab_time', '<=', time() - 3600 * 24 * 7)
				->limit(1000)
				->execute()
				->asArray();
			
			$post_ids = array_map(function ($v) { return $v['id']; }, $rows);
			$data_ids = array_map(function ($v) { return $v['data_id']; }, $rows);
			
			if ($rows) {
				echo "delete ".count($rows)." old posts...\n";
				DB::delete('vk_grabber_data')
					->where('id', 'IN', $data_ids)
					->execute();
				DB::delete('vk_grabber_data_index')
					->where('id', 'IN', $post_ids)
					->execute();
			}
			
			$post_ids = array_map(function ($v) { return $v['id']; }, $rows);
			$data_ids = array_map(function ($v) { return $v['data_id']; }, $rows);
			
			if ($rows) {
				echo "delete ".count($rows)." old posts...\n";
				DB::delete('vk_grabber_data')
					->where('id', 'IN', $data_ids)
					->execute();
				DB::delete('vk_grabber_data_index')
					->where('id', 'IN', $post_ids)
					->execute();
			}
		} while ($rows);
	}
	
	public function insertToDB($data) {
		DB::begin();
		
		$old_record = DB::select('data_id', 'source_id')
			->from('vk_grabber_data_index')
			->where('source_type', '=', $data->source_type)
			->where('remote_id', '=', $data->remote_id)
			->execute()
			->current();
		
		$data_id = 0;
		$source_id = $data->source_id;
		
		if (!$old_record) {
			$data_id = DB::insert('vk_grabber_data')
				->set([
					'text'		=> $data->text, 
					'owner'		=> $data->owner, 
					'attaches'	=> gzdeflate(serialize($data->attaches)), 
				])
				->onDuplicateSetValues('text')
				->onDuplicateSetValues('owner')
				->onDuplicateSetValues('attaches')
				->execute()
				->insertId();
		} else {
			$source_id = $old_record['source_id'];
			$data_id = $old_record['data_id'];
			
			DB::update('vk_grabber_data')
				->set([
					'text'		=> $data->text, 
					'owner'		=> $data->owner, 
					'attaches'	=> gzdeflate(serialize($data->attaches)), 
				])
				->where('id', '=', $data_id)
				->execute();
		}
		
		$post_type = 0;
		if ($data->images_cnt > 0 && $data->gifs_cnt > 0)
			$post_type = 3;
		elseif ($data->images_cnt > 0)
			$post_type = 2;
		elseif ($data->gifs_cnt > 0)
			$post_type = 1;
		
		DB::insert('vk_grabber_data_index')
			->set([
				'source_id'			=> $source_id, 
				'data_id'			=> $data_id, 
				'grab_time'			=> time(), 
				'post_type'			=> $post_type, 
				'source_type'		=> $data->source_type, 
				'remote_id'			=> $data->remote_id, 
				'time'				=> $data->time, 
				'likes'				=> $data->likes, 
				'comments'			=> $data->comments, 
				'reposts'			=> $data->reposts, 
				'images_cnt'		=> $data->images_cnt, 
				'gifs_cnt'			=> $data->gifs_cnt, 
				'likes'				=> $data->likes, 
			])
			->onDuplicateSetValues('data_id')
			->onDuplicateSetValues('time')
			->onDuplicateSetValues('grab_time')
			->onDuplicateSetValues('likes')
			->onDuplicateSetValues('comments')
			->onDuplicateSetValues('reposts')
			->onDuplicateSetValues('images_cnt')
			->onDuplicateSetValues('gifs_cnt')
			->execute();
		
		DB::commit();
		
		return !$old_record;
	}
	
	public function createDom($res) {
		$doc = new \DOMDocument('1.0', 'UTF-8');
		$doc->strictErrorChecking = false;
		$doc->encoding = 'UTF-8';
		@$doc->loadHTML('<?xml version="1.1" encoding="UTF-8" ?>'.$res);
		$xpath = new \DOMXPath($doc);
		foreach ($xpath->query('//comment()') as $comment)
			$comment->parentNode->removeChild($comment);
		$scripts = $doc->getElementsByTagName('script');
		foreach ($scripts as $script)
			$script->parentNode->removeChild($script);
		$styles = $doc->getElementsByTagName('style');
		foreach ($styles as $style)
			$style->parentNode->removeChild($style);
		return $doc;
	}
	
	public function parseOkDate($str) {
		$months = [
			'янв'		=> 1, 
			'января'	=> 1, 
			
			'фев'		=> 2, 
			'февраля'	=> 2, 
			
			'мар'		=> 3, 
			'марта'		=> 3, 
			
			'апр'		=> 4, 
			'апреля'	=> 4, 
			
			'май'		=> 5, 
			'мая'		=> 5, 
			
			'июн'		=> 6, 
			'июня'		=> 6, 
			
			'июл'		=> 7, 
			'июля'		=> 7, 
			
			'авг'		=> 8, 
			'августа'	=> 8, 
			
			'сен'		=> 9, 
			'сентября'	=> 9, 
			
			'окт'		=> 10, 
			'октября'	=> 10, 
			
			'ноя'		=> 11, 
			'ноября'	=> 11, 
			
			'дек'		=> 12, 
			'декабря'	=> 12, 
		];
		
		if (preg_match("/вчера\s+(в\s+)?(\d+:\d+)/ui", $str, $m)) { // Вчера
			$date = \DateTime::createFromFormat("H:i", $m[2]);
			if ($date) {
				$date->sub(new \DateInterval('P1D'));
				return $date->format('U');
			}
		} else if (preg_match("/(\d+)\s+(\w+)\s+(\d{4})\s+(в\s+)?(\d+:\d+)/ui", $str, $m)) { // Прошлый год + время
			$month = mb_strtolower($m[2]);
			if (!isset($months[$month]))
				return 0;
			$date = \DateTime::createFromFormat("d/m/Y H:i", $m[1]."/".$months[$month]."/".$m[3]." ".$m[5]);
			if ($date) {
				$date->setTime(0, 0, 0);
				return $date->format('U');
			}
		} else if (preg_match("/(\d+)\s+(\w+)\s+(\d{4})/ui", $str, $m)) { // Прошлый год
			$month = mb_strtolower($m[2]);
			if (!isset($months[$month]))
				return 0;
			$date = \DateTime::createFromFormat("d/m/Y", $m[1]."/".$months[$month]."/".$m[3]);
			if ($date) {
				$date->setTime(0, 0, 0);
				return $date->format('U');
			}
		} else if (preg_match("/(\d+)\s+(\w+)\s+(в\s+)?(\d+:\d+)/ui", $str, $m)) { // Позавчера и далее
			$month = mb_strtolower($m[2]);
			if (!isset($months[$month]))
				return 0;
			$date = \DateTime::createFromFormat("d/m H:i", $m[1]."/".$months[$month]." ".$m[4]);
			if ($date)
				return $date->format('U');
		} else if (preg_match("/(\d+:\d+)/ui", $str, $m)) { // Сегодня
			$date = \DateTime::createFromFormat("H:i", $m[1]);
			if ($date)
				return $date->format('U');
		} else {
			echo "Unknown date format: '$str'\n";
		}
		return 0;
	}
}
