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
			echo "Deleted $deleted unclaimed posts...\n";
		} while ($deleted > 0);
		
		// Удаляем старые посты
		do {
			$deleted = \Smm\Grabber::cleanOldPosts(array_keys($sources_enabled), 7 * 24 * 3600);
			if ($deleted > 0)
				echo "Deleted $deleted old posts...\n";
		} while ($deleted > 0);
		
		$this->grabPinterest($sources_enabled);
		
		echo date("Y-m-d H:i:s")." - done.\n";
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
			
			echo "-------------- PAGE: $page -------------- \n";
			
			$good = 0;
			foreach ($sources as $source) {
				$id = $source['source_id'];
				
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
					
					$ok = \Smm\Grabber::addNewPost((object) [
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
}
