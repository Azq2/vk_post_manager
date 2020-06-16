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
		
		$need_clean_old = array_filter($sources_enabled, function ($v) {
			return $v['source_id'][0] == '#';
		});
		
		// Удаляем старые посты
		do {
			$deleted = \Smm\Grabber::cleanOldPosts(array_keys($need_clean_old), 7 * 24 * 3600);
			if ($deleted > 0)
				echo "Deleted $deleted old posts...\n";
		} while ($deleted > 0);
		
		$this->grabInstagram($sources_enabled);
		
		echo date("Y-m-d H:i:s")." - done.\n";
	}
	
	public function getInstagramUser($insta_api, $login) {
		$found_user = false;
		$max_tries = 15;
		
		while (true) {
			$response = $insta_api->exec("https://www.instagram.com/web/search/topsearch/", [
				'query'		=> $login
			]);
			
			if ($response->success())
				break;
			
			echo "=> ERROR: can't get user ($login): ".$response->error()."\n";
			
			if (in_array($response->errorCode(), [400, 404, 403]))
				break;
			
			if (!$max_tries)
				break;
			
			--$max_tries;
			
			sleep($response->errorCode() == 429 ? 3 * 60 : 1);
		}
		
		if (!$response->success())
			return false;
		
		if (!isset($response->users)) {
			echo "=> ERROR: can't get user ($login): invalid response\n";
			return false;
		}
		
		foreach ($response->users as $row) {
			if ($row->user->username === $login) {
				$found_user = $row->user;
				break;
			}
		}
		
		return $found_user;
	}
	
	public function grabInstagram($sources) {
		$insta_api = new \Smm\Instagram\API();
		
		$instagram_sources = [];
		foreach ($sources as $source) {
			$id = $source['source_id'];
			
			if ($id[0] == "#") {
				$value = substr($id, 1);
				$url = "https://www.instagram.com/explore/tags/".urlencode($value);
				$type = 'hashtag';
			} elseif ($id[0] == "@") {
				$value = substr($id, 1);
				$url = "https://www.instagram.com/".urlencode($value);
				$type = 'user';
				
				if (!$source['internal_id']) {
					echo "Get user_id for $id...\n";
					
					$user = $this->getInstagramUser($insta_api, $value);
					if (!$user || !isset($user->pk)) {
						echo "=> ERROR: can't get user_id.\n";
						continue;
					}
					
					echo "=> OK, user_id: ".$user->pk."\n";
					
					$source['internal_id'] = $user->pk;
					
					DB::update('vk_grabber_sources')
						->set([
							'internal_id'		=> $user->pk
						])
						->where('id', '=', $source['id'])
						->execute();
				}
			} else {
				echo "Invalid ID: $id\n";
				continue;
			}
			
			$instagram_sources[$source['id']] = [
				'id'		=> $source['id'], 
				'owner'		=> $id, 
				'value'		=> $value, 
				'url'		=> $url, 
				'type'		=> $type, 
				'user_id'	=> $source['internal_id']
			];
			
			DB::insert('vk_grabber_data_owners')
				->set([
					'id'		=> \Smm\Grabber::SOURCE_INSTAGRAM."_".$id, 
					'name'		=> $id, 
					'url'		=> $url, 
					'avatar'	=> "https://www.instagram.com/static/images/ico/xxxhdpi_launcher.png/9fc4bab7565b.png"
				])
				->onDuplicateSetValues('url')
				->onDuplicateSetValues('name')
				->onDuplicateSetValues('avatar')
				->execute();
		}
		
		$next = [];
		$ended = [];
		$page = 0;
		
		$cache = \Z\Cache::instance();
		
		while (true) {
			++$page;
			
			echo "-------------- PAGE: $page -------------- \n";
			
			$good = 0;
			
			foreach ($instagram_sources as $id => $source) {
				if (isset($ended[$id]))
					continue;
				
				echo "[ ".$source['owner']." ]\n";
				
				$max_tries = 15;
				
				while (true) {
					if ($source['type'] == "hashtag") {
						if (isset($next[$id])) {
							$response = $insta_api->execGraphql('HASHTAG_NEXT_PAGE', [
								'tag_name'	=> $source['value'], 
								'first'		=> 50, 
								'after'		=> $next[$id], 
							]);
						} else {
							$response = $insta_api->execGraphql('HASHTAG_NEXT_PAGE', [
								'tag_name'	=> $source['value'], 
								'first'		=> 12, 
							]);
						}
					} elseif ($source['type'] == "user") {
						if (isset($next[$id])) {
							$response = $insta_api->execGraphql('PROFILE_NEXT_PAGE', [
								'id'		=> $source['user_id'], 
								'first'		=> 50, 
								'after'		=> $next[$id], 
							]);
						} else {
							$response = $insta_api->execGraphql('PROFILE_NEXT_PAGE', [
								'id'		=> $source['user_id'], 
								'first'		=> 5
							]);
						}
					}
					
					if ($response->success())
						break;
					
					echo "=> ERROR: can't get edges: ".$response->error()."\n";
					
					if (in_array($response->errorCode(), [400, 404, 403]))
						break;
					
					if (!$max_tries)
						break;
					
					--$max_tries;
					
					sleep($response->errorCode() == 429 ? 3 * 60 : 1);
				}
				
				if (!$response->success()) {
					$ended[$id] = true;
					continue;
				}
				
				$graphql = $response->graphql ?? $response->data ?? [];
				
				$edges_lists = [];
				if (isset($graphql->user->edge_owner_to_timeline_media->edges))
					$edges_lists[] = $graphql->user->edge_owner_to_timeline_media->edges;
				
				if (isset($graphql->hashtag->edge_hashtag_to_top_posts->edges))
					$edges_lists[] = $graphql->hashtag->edge_hashtag_to_top_posts->edges;
				
				if (isset($graphql->hashtag->edge_hashtag_to_media->edges))
					$edges_lists[] = $graphql->hashtag->edge_hashtag_to_media->edges;
				
				$next[$id] = false;
				
				if (isset($graphql->user->edge_owner_to_timeline_media->page_info)) {
					if ($graphql->user->edge_owner_to_timeline_media->page_info->has_next_page)
						$next[$id] = $graphql->user->edge_owner_to_timeline_media->page_info->end_cursor;
				}
				
				/*
				if (isset($graphql->hashtag->edge_hashtag_to_media->page_info)) {
					if ($graphql->hashtag->edge_hashtag_to_media->page_info->has_next_page)
						$next[$id] = $graphql->hashtag->edge_hashtag_to_media->page_info->end_cursor;
				}
				*/
				
				if (!$edges_lists) {
					echo "=> ERROR: can't get edges: invalid graphql response\n";
					$ended[$id] = true;
					continue;
				}
				
				$totlal_items = 0;
				$totlal_new_items = 0;
				
				foreach ($edges_lists as $edges_list) {
					foreach ($edges_list as $edge) {
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
						
						echo "=> $topic_url\n";
						
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
						} else {
							echo "=> WARNING: can't parse comments!\n";
						}
						
						$attaches = [];
						$edges_to_parse = [];
						
						// Для некоторых типов (карусель, видео) нужно получить доп. инфу по shortcode
						if (in_array($edge->node->__typename, ['GraphSidecar', 'GraphVideo'])) {
							$max_tries = 10;
							
							echo "  => need get additional info! (".$edge->node->__typename.")\n";
							
							while (true) {
								$response = $insta_api->execGraphql('SHORTCODE_MEDIA', [
									'shortcode'				=> $topic_id, 
									'child_comment_count'	=> 3, 
									'fetch_comment_count'	=> 40, 
									'parent_comment_count'	=> 24, 
									'has_threaded_comments'	=> true
								]);
								
								if ($response->success())
									break;
								
								echo "  => ERROR: ".$response->error()."\n";
								
								if (in_array($response->errorCode(), [400, 404, 403]))
									break;
								
								if (!$max_tries)
									break;
								
								--$max_tries;
								sleep($response->errorCode() == 429 ? 3 * 60 : 1);
							}
							
							if (!$response->success())
								continue;
							
							$graphql = $response->graphql ?? $response->data ?? [];
							
							if (!isset($graphql->shortcode_media)) {
								echo "  => ERROR: can't find data->shortcode_media from JSON\n";
								continue;
							}
							
							if ($edge->node->__typename == "GraphSidecar") {
								if (!isset($graphql->shortcode_media->edge_sidecar_to_children)) {
									echo "  => ERROR: can't find data->shortcode_media->edge_sidecar_to_children from JSON\n";
									continue;
								}
								
								foreach ($graphql->shortcode_media->edge_sidecar_to_children->edges as $sub_edge)
									$edges_to_parse[] = $sub_edge;
							} else {
								$edges_to_parse[] = (object) ['node' => $graphql->shortcode_media];
							}
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
							$ended[$id] = true;
							continue;
						}
						
						$post_data = [
							'source_id'			=> $source['id'], 
							'source_type'		=> \Smm\Grabber::SOURCE_INSTAGRAM, 
							'remote_id'			=> $topic_id, 
							
							'text'				=> $text, 
							'owner'				=> $source['owner'], 
							'attaches'			=> $attaches, 
							
							'time'				=> $date, 
							'likes'				=> $likes, 
							'comments'			=> $comments, 
							'reposts'			=> 0, 
							'images_cnt'		=> $images_cnt, 
							'gifs_cnt'			=> $gifs_cnt
						];
						
						$ok = \Smm\Grabber::addNewPost((object) $post_data);
						
						echo "  => OK".($ok ? " - NEW" : "")." [likes: $likes, comments: $comments]\n";
						
						++$totlal_items;
						
						if ($ok)
							++$totlal_new_items;
					}
					
					$good += $totlal_new_items;
					
					if (!$totlal_items || $totlal_new_items < $totlal_items || !$next[$id])
						$ended[$id] = true;
				}
			}
			
			echo "good=$good\n";
			if (!$good)
				break;
		}
	}
}
