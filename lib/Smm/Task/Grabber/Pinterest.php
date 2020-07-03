<?php
namespace Smm\Task\Grabber;

use \Z\DB;
use \Z\Date;
use \Z\Util\Url;

class Pinterest extends \Z\Task {
	public function run($args) {
		if (!\Smm\Utils\Lock::lock(__CLASS__)) {
			echo "Already running.\n";
			return;
		}
		
		echo date("Y-m-d H:i:s")." - start.\n";
		
		list ($sources_enabled, $sources_disabled) = \Smm\Grabber::getSources(\Smm\Grabber::SOURCE_PINTEREST);
		
		// Удаляем ненужные посты
		do {
			$deleted = \Smm\Grabber::cleanUnclaimedPosts(array_keys($sources_disabled));
			if ($deleted > 0)
				echo "Deleted $deleted unclaimed posts...\n";
		} while ($deleted > 0);
		
		// Удаляем старые посты
		do {
			$deleted = \Smm\Grabber::cleanOldPosts(array_keys($sources_enabled), [
				'max_age'		=> 0
			]);
			if ($deleted > 0)
				echo "Deleted $deleted old posts...\n";
		} while ($deleted > 0);
		
		do {
			$deleted = \Smm\Grabber::cleanOldPosts(array_keys($sources_enabled), [
				'max_age'		=> 0, 
				'post_types'	=> [
					\Smm\Grabber::POST_WITH_TEXT_GIF, 
					\Smm\Grabber::POST_WITH_TEXT_PIC_GIF
				]
			]);
			if ($deleted > 0)
				echo "Deleted $deleted old posts [GIF]...\n";
		} while ($deleted > 0);
		
		$this->grabPinterest($sources_enabled);
		
		echo date("Y-m-d H:i:s")." - done.\n";
	}
	
	public function grabPinterest($sources) {
		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER		=> true, 
			CURLOPT_FOLLOWLOCATION		=> true, 
			CURLOPT_VERBOSE				=> false, 
			CURLOPT_HTTPHEADER			=> [
				"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3", 
				"Encoding: gzip, deflate, br", 
				"Accept-Language: en-US,en;q=0.9,ru;q=0.8", 
				"Upgrade-Insecure-Requests: 1", 
				"X-Requested-With: XMLHttpRequest", 
				"Origin: https://pinterest.ru"
			], 
			CURLOPT_USERAGENT			=> "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.100 Safari/537.36", 
			CURLOPT_IPRESOLVE			=> CURL_IPRESOLVE_V4
		]);
		
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
			
			echo "-------------- PAGE: $page -------------- \n";
			
			$good = 0;
			foreach ($sources as $id => $source) {
				$id = $source['id'];
				
				if (isset($ended[$id]))
					continue;
				
				echo "FETCH RELATED: ".$source['url']."\n";
				
				$options = $next[$id] ?? [
					'isPrefetch'				=> false, 
					'pin_id'					=> $source['value'], 
					'context_pin_ids'			=> [], 
					'search_query'				=> '', 
					'source'					=> 'deep_linking', 
					'top_level_source'			=> 'deep_linking', 
					'top_level_source_depth'	=> 1
				];
				
				$page_url = "https://pinterest.ru/resource/RelatedModulesResource/get/?".http_build_query([
					'source_url'		=> "/pin/".$source['value']."/", 
					'data'				=> json_encode([
						'options'		=> $options, 
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
				
				if (!isset($json->resource_response->data)) {
					echo "=> ERROR: Can't find resource_response->data from JSON ($page_url)\n";
					var_dump($json);
					break;
				}
				
				$good2 = 0;
				
				foreach ($json->resource_response->data as $result) {
					if (!in_array($result->type, ["pin"])) {
						echo "=> skip unsupported type: ".$result->type."\n";
						continue;
					}
					
					// Получаем ID фотки
					$topic_id = $result->id;
					if (!$topic_id) {
						echo "=> ERROR: Can't parse topic id ($page_url)\n";
						continue;
					}
					
					if (!isset($result->created_at) || !$result->created_at) {
						echo "=> ERROR: Can't parse result ($page_url)\n";
						continue;
					}
					
					// Ссылка на фотку
					$topic_url = "https://pinterest.ru/pin/$topic_id";
					
					// Дата загрузки фотки
					$date = strtotime($result->created_at);
					
					// Описание фотки
					$text = trim($result->title."\n".$result->description);
					
					// Лайки
					$likes = $result->repin_count;
					
					// Комменты
					$comments = $result->comment_count;
					
					// Репосты
					$reposts = 0;
					
					// Кол-во GIF
					$gifs_cnt = 0;
					
					$attaches = [];
					
					/*
					if ($result->videos) {
						$thumbs = [];
						foreach ($result->images as $i)
							$thumbs[$i->width] = $i->url;
						
						if (!isset($result->videos->video_list->V_HLSV4)) {
							echo "=> ERROR: Can't parse V_HLSV4 ($page_url)\n";
							continue;
						}
						
						$attaches[] = [
							'id'		=> 'doc_'.$result->id.'_'.count($attaches), 
							'type'		=> 'doc', 
							'ext'		=> 'mp4', 
							'title'		=> 'video.mp4', 
							'w'			=> $result->videos->video_list->V_HLSV4->width, 
							'h'			=> $result->videos->video_list->V_HLSV4->height, 
							'thumbs'	=> $thumbs, 
							'video'		=> [
								'has_audio'		=> $sub_edge->node->has_audio ?? NULL, 
								'duration'		=> $result->videos->video_list->V_HLSV4->duration ?? 0, 
							], 
							'url'		=> $result->videos->video_list->V_HLSV4->url, 
							'mp4'		=> $result->videos->video_list->V_HLSV4->url, 
							'page_url'	=> $topic_url
						];
						
						++$gifs_cnt;
					} else
					*/
					
					if ($result->images) {
						$thumbs = [];
						foreach ($result->images as $i)
							$thumbs[$i->width] = $i->url;
						
						$attaches[] = [
							'id'		=> 'photo_'.$result->id.'_'.count($attaches), 
							'type'		=> 'photo', 
							'w'			=> $result->images->orig->width, 
							'h'			=> $result->images->orig->height, 
							'thumbs'	=> $thumbs
						];
					}
					
					if (!$attaches) {
						echo "=> ERROR: #$topic_id topic without attaches ($topic_url)\n";
						continue;
					}
					
					$item = [
						'source_id'			=> $source['id'], 
						'source_type'		=> \Smm\Grabber::SOURCE_PINTEREST, 
						'remote_id'			=> $topic_id, 
						
						'text'				=> $text, 
						'attaches'			=> $attaches, 
						
						'time'				=> time(), 
						'likes'				=> $likes, 
						'comments'			=> $comments, 
						'reposts'			=> $reposts, 
						'images_cnt'		=> 1, 
						'gifs_cnt'			=> $gifs_cnt
					];
					
					$ok = \Smm\Grabber::addNewPost((object) $item);
					
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
			
			if ($page > 100) {
				echo "Pages limit.\n";
				break;
			}
		}
		
		echo "done.\n";
	}
}
