<?php
namespace Smm\Task\Grabber;

use \Z\DB;
use \Z\Date;
use \Z\Util\Url;

class Instagram extends \Z\Task {
	public function run($args) {
		if (!\Smm\Utils\Lock::lock(__CLASS__)) {
			echo "Already running.\n";
			return;
		}
		
		echo date("Y-m-d H:i:s")." - start.\n";
		
		list ($sources_enabled, $sources_disabled) = \Smm\Grabber::getSources(\Smm\Grabber::SOURCE_INSTAGRAM);
		
		// Удаляем ненужные посты
		do {
			$deleted = \Smm\Grabber::cleanUnclaimedPosts(array_keys($sources_disabled));
			if ($deleted > 0)
				echo "Deleted $deleted unclaimed posts...\n";
		} while ($deleted > 0);
		
		// Удаляем старые посты
		do {
			$deleted = \Smm\Grabber::cleanOldPosts(array_keys($sources_enabled), [
				'max_age'		=> 7 * 24 * 3600
			]);
			if ($deleted > 0)
				echo "Deleted $deleted old posts...\n";
		} while ($deleted > 0);
		
		do {
			$deleted = \Smm\Grabber::cleanOldPosts(array_keys($sources_enabled), [
				'max_age'		=> 24 * 3600, 
				'post_types'	=> [
					\Smm\Grabber::POST_WITH_TEXT_GIF, 
					\Smm\Grabber::POST_WITH_TEXT_PIC_GIF
				]
			]);
			if ($deleted > 0)
				echo "Deleted $deleted old posts [GIF]...\n";
		} while ($deleted > 0);
		
		$this->grabInstagram($sources_enabled);
		
		echo date("Y-m-d H:i:s")." - done.\n";
	}
	
	public function grabInstagram($sources) {
		$insta_browser = new \Smm\Instagram\RemoteBrowser();
		
		$cache = \Z\Cache::instance();
		
		$last_time_key = "instagram-grabber-last:v9";
		
		foreach ($sources as $id => $source) {
			$last_check = $cache->get("$last_time_key:".$source['value']) ?: 0;
			
			// Add collect request to queue
			if ($source['value'][0] == "#") {
				if (time() - $last_check < 3600 * 8)
					continue;
				
				echo "[ ".$source['value']." ]\n";
				
				$response = $insta_browser->exec("queue-collect", [
					'name'		=> substr($source['value'], 1),
					'type'		=> 'hashtag'
				]);
			} elseif ($source['value'][0] == "@") {
				if (time() - $last_check < 3600 * 8)
					continue;
				
				echo "[ ".$source['value']." ]\n";
				
				$response = $insta_browser->exec("queue-collect", [
					'name'		=> substr($source['value'], 1),
					'type'		=> 'user'
				]);
			}
			
			$cache->set("$last_time_key:".$source['value'], time(), 3600 * 48);
			
			if ($response->status != 200) {
				echo "=> ERROR: instagram browser error: ".$response->error." (".$response->status.")\n";
				continue;
			}
			
			$queue_id = $response->id;
			
			// Wait for collect done
			echo "=> wait for data...\n";
			$response = $insta_browser->waitForStatus($queue_id);
			
			if ($response->status != 200) {
				echo "=> ERROR: instagram browser error: ".$response->error." (".$response->status.")\n";
				continue;
			}
			
			$media_lists = [];
			foreach ($response->result->media as $k => $items) {
				if (preg_match("/top/", $k)) {
					$media_lists[] = [\Smm\Grabber::LIST_TOP, $items];
				} else if (preg_match("/recent/", $k)) {
					$media_lists[] = [\Smm\Grabber::LIST_NEW, $items];
				} else {
					$media_lists[] = [\Smm\Grabber::LIST_UNKNOWN, $items];
				}
			}
			
			if (!$media_lists) {
				echo "=> ERROR: can't get medias: invalid response\n";
				continue;
			}
			
			$totlal_items = 0;
			$totlal_new_items = 0;
			
			foreach ($media_lists as $media_list) {
				foreach ($media_list[1] as $item) {
					$gifs_cnt = 0;
					$images_cnt = 0;
					
					// Получаем ID фотки
					$topic_id = $item->code;
					if (!$topic_id) {
						echo "=> ERROR: can't parse topic id\n";
						continue;
					}
					
					// Ссылка на фотку
					$topic_url = "https://www.instagram.com/p/$topic_id";
					
					echo "=> $topic_url [".$item->product_type."]\n";
					
					// Дата загрузки фотки
					$date = time();
					if (isset($item->taken_at)) {
						$date = $item->taken_at;
					} else {
						echo "=> WARNING: can't parse date!\n";
					}
					
					// Описание фотки
					$text = $item->caption->text ?? '';
					
					// Лайки
					$likes = $item->like_count ?? 0;
					
					// Комменты
					$comments = $item->comment_count ?? 0;
					
					$attaches = [];
					
					$sub_items = [];
					if ($item->product_type == "carousel_container") {
						foreach ($item->carousel_media as $sub_item)
							$sub_items[] = $sub_item;
					} else {
						$sub_items[] = $item;
					}
					
					foreach ($sub_items as $sub_item) {
						switch ($sub_item->media_type) {
							case 1: // photo
								$attaches[] = [
									'id'		=> 'photo_'.$topic_id.'_'.count($attaches), 
									'type'		=> 'photo', 
									'w'			=> $sub_item->original_width, 
									'h'			=> $sub_item->original_height, 
									'thumbs'	=> [
										$sub_item->image_versions2->candidates[0]->width => $sub_item->image_versions2->candidates[0]->url
									]
								];
								
								++$images_cnt;
							break;
							
							case 2: // video
								$attaches[] = [
									'id'		=> 'photo_'.$topic_id.'_'.count($attaches), 
									'type'		=> 'doc', 
									'ext'		=> 'mp4', 
									'title'		=> 'video.mp4', 
									'w'			=> $sub_item->original_width, 
									'h'			=> $sub_item->original_height, 
									'thumbs'	=> [
										$sub_item->image_versions2->candidates[0]->width => $sub_item->image_versions2->candidates[0]->url
									],
									'video'		=> [
										'has_audio'		=> $sub_item->has_audio ?? NULL, 
										'duration'		=> $sub_item->video_duration, 
									], 
									'url'		=> $sub_item->video_versions[0]->url, 
									'mp4'		=> $sub_item->video_versions[0]->url, 
									'page_url'	=> $topic_url
								];
								
								++$gifs_cnt;
								
							break;
							
							default:
								echo "  => ERROR: unknown type: ".$sub_item->media_type."\n";
							break;
						}
					}
					
					if (!$attaches) {
						echo "  => ERROR: topic without attaches\n";
						continue;
					}
					
					$post_data = [
						'source_id'			=> $source['id'], 
						'source_type'		=> \Smm\Grabber::SOURCE_INSTAGRAM, 
						'remote_id'			=> $topic_id, 
						
						'text'				=> $text, 
						'attaches'			=> $attaches, 
						
						'time'				=> $date, 
						'likes'				=> $likes, 
						'comments'			=> $comments, 
						'reposts'			=> 0, 
						'images_cnt'		=> $images_cnt, 
						'gifs_cnt'			=> $gifs_cnt, 
						'list_type'			=> $media_list[0]
					];
					
					$ok = \Smm\Grabber::addNewPost((object) $post_data);
					
					echo "  => OK".($ok ? " - NEW" : "")." [likes: $likes, comments: $comments]\n";
					
					++$totlal_items;
					
					if ($ok)
						++$totlal_new_items;
				}
			}
			
			echo "total collected: $totlal_items (+$totlal_new_items new)\n";
			sleep(660);
		}
	}
}
