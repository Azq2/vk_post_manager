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
	
		foreach ($sources as $id => $source) {
			$last_check = $cache->get("instagram-grabber-last:".$source['value']) ?: 0;
			
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
			
			$cache->set("instagram-grabber-last:".$source['value'], time(), 3600 * 48);
			
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
			
			$edges_lists = [];
			
			if (!empty($response->result->graphql->all))
				$edges_lists[] = [\Smm\Grabber::LIST_UNKNOWN, $response->result->graphql->all];
			
			if (!empty($response->result->graphql->top))
				$edges_lists[] = [\Smm\Grabber::LIST_TOP, $response->result->graphql->top];
			
			if (!empty($response->result->graphql->new))
				$edges_lists[] = [\Smm\Grabber::LIST_NEW, $response->result->graphql->new];
			
			if (!$edges_lists) {
				echo "=> ERROR: can't get edges: invalid graphql response\n";
				continue;
			}
			
			$totlal_items = 0;
			$totlal_new_items = 0;
			
			foreach ($edges_lists as $edges_list) {
				foreach ($edges_list[1] as $edge) {
					$gifs_cnt = 0;
					$images_cnt = 0;
					
					// Получаем ID фотки
					$topic_id = $edge->node->shortcode;
					if (!$topic_id) {
						echo "=> ERROR: can't parse topic id\n";
						continue;
					}
					
					// Ссылка на фотку
					$topic_url = "https://www.instagram.com/p/$topic_id";
					
					echo "=> $topic_url [".$edge->node->__typename."]\n";
					
					// Дата загрузки фотки
					$date = time();
					if (isset($edge->node->taken_at_timestamp)) {
						$date = $edge->node->taken_at_timestamp;
					} else {
						echo "=> WARNING: can't parse date!\n";
					}
					
					// Описание фотки
					$text = "";
					if (isset($edge->node->edge_media_to_caption->edges, $edge->node->edge_media_to_caption->edges[0]))
						$text = $edge->node->edge_media_to_caption->edges[0]->node->text;
					
					// Лайки
					$likes = 0;
					if (isset($edge->node->edge_liked_by)) {
						$likes = $edge->node->edge_liked_by->count;
					} elseif (isset($edge->node->edge_media_preview_like)) {
						$likes = $edge->node->edge_media_preview_like->count;
					} else {
						echo "=> WARNING: can't parse likes!\n";
					}
					
					// Комменты
					$comments = 0;
					if (isset($edge->node->edge_media_to_comment)) {
						$comments = $edge->node->edge_media_to_comment->count;
					} elseif (isset($edge->node->edge_media_to_parent_comment)) {
						$comments = $edge->node->edge_media_to_parent_comment->count;
					} else {
						echo "=> WARNING: can't parse comments!\n";
					}
					
					$attaches = [];
					$edges_to_parse = [];
					
					if ($edge->node->__typename == "GraphSidecar") {
						if (!isset($edge->node->edge_sidecar_to_children)) {
							echo "  => ERROR: can't find data->shortcode_media->edge_sidecar_to_children from JSON\n";
							continue;
						}
						
						foreach ($edge->node->edge_sidecar_to_children->edges as $sub_edge)
							$edges_to_parse[] = $sub_edge;
					} elseif ($edge->node->__typename == "GraphVideo") {
						if (!isset($edge->node->video_url)) {
							echo "  => ERROR: can't edge->node->video_url from JSON\n";
							continue;
						}
						
						$edges_to_parse[] = $edge;
					} else {
						$edges_to_parse[] = $edge;
					}
					
					foreach ($edges_to_parse as $sub_edge) {
						switch ($sub_edge->node->__typename) {
							case "GraphVideo":
								$attaches[] = [
									'id'		=> 'doc_'.md5($sub_edge->node->display_url), 
									'type'		=> 'doc', 
									'ext'		=> 'mp4', 
									'title'		=> 'video.mp4', 
									'w'			=> $sub_edge->node->dimensions->width, 
									'h'			=> $sub_edge->node->dimensions->height, 
									'thumbs'	=> [
										$sub_edge->node->dimensions->width => $sub_edge->node->display_url
									], 
									'video'		=> [
										'has_audio'		=> $sub_edge->node->has_audio ?? NULL, 
										'duration'		=> $sub_edge->node->video_duration ?? 0, 
									], 
									'url'		=> $sub_edge->node->video_url, 
									'mp4'		=> $sub_edge->node->video_url, 
									'page_url'	=> $topic_url
								];
								
								++$gifs_cnt;
							break;
							
							case "GraphImage":
								$attaches[] = [
									'id'		=> 'photo_'.md5($sub_edge->node->display_url), 
									'type'		=> 'photo', 
									'w'			=> $sub_edge->node->dimensions->width, 
									'h'			=> $sub_edge->node->dimensions->height, 
									'thumbs' => [
										$sub_edge->node->dimensions->width => $sub_edge->node->display_url
									]
								];
								
								++$images_cnt;
							break;
							
							default:
								echo "  => ERROR: unknown type: ".$sub_edge->node->__typename."\n";
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
						'list_type'			=> $edges_list[0]
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
