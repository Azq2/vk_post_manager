<?php
namespace Smm\Task\Grabber;

use \Z\DB;
use \Z\Date;
use \Z\Util\Url;

class Tumblr extends \Z\Task {
	public function run($args) {
		if (!\Smm\Utils\Lock::lock(__CLASS__)) {
			echo "Already running.\n";
			return;
		}
		
		echo date("Y-m-d H:i:s")." - start.\n";
		
		list ($sources_enabled, $sources_disabled) = \Smm\Grabber::getSources(\Smm\Grabber::SOURCE_TUMBLR);
		
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
		
		$this->grab($sources_enabled);
		
		echo date("Y-m-d H:i:s")." - done.\n";
	}
	
	public function grab($sources) {
		$api = new \Smm\Tumblr\Web();
		
		foreach ($sources as $id => $source) {
			foreach (['top', 'recent'] as $sort) {
				echo "TAG: #".$source['value']." ($sort)\n";
				
				$response = $api->getTagged($source['value'], $sort);
				if ($response->error) {
					echo "=> error: ".$response->error."\n";
					continue;
				}
				
				if (!isset($response->data->Tagged->timeline->elements)) {
					echo "=> error: timeline not found!\n";
					continue;
				}
				
				$this->parseTimeline($source, $response->data->Tagged->timeline->elements, $sort);
			}
		}
	}
	
	public function parseTimeline($source, $posts, $sort) {
		foreach ($posts as $post) {
			if ($post->objectType != 'post') {
				//echo "=> error: unknown type: ".$post->objectType."\n";
				continue;
			}
			
			$images_cnt = 0;
			$gifs_cnt = 0;
			$attaches = [];
			
			foreach ($post->content as $content) {
				if ($content->type == 'video') {
					if (!isset($content->media)) {
						echo "=> skip unknown video: ".$content->provider."\n";
						continue;
					}
					
					$attaches[] = [
						'id'		=> 'doc_'.$post->id.'_'.count($attaches),
						'type'		=> 'doc',
						'ext'		=> 'mp4',
						'title'		=> 'video.mp4',
						'w'			=> $content->media->width,
						'h'			=> $content->media->height,
						'thumbs'	=> [
							$content->poster[0]->width	=> $content->poster[0]->url
						],
						'video'		=> [
							'has_audio'		=> NULL,
							'duration'		=> 0
						],
						'url'		=> $content->media->url,
						'mp4'		=> $content->media->url,
						'page_url'	=> $post->postUrl
					];
					$gifs_cnt++;
				} else if ($content->type == 'image') {
					$attaches[] = [
						'id'		=> 'photo_'.$post->id.'_'.count($attaches),
						'type'		=> 'photo',
						'w'			=> $content->media[0]->width,
						'h'			=> $content->media[0]->height,
						'thumbs'	=> [
							$content->media[0]->width	=> $content->media[0]->url
						]
					];
					$images_cnt++;
				}
			}
			
			if (!$images_cnt && !$gifs_cnt) {
				echo "[SKIP] ".$post->postUrl."\n";
				continue;
			}
			
			$item = [
				'source_id'			=> $source['id'],
				'source_type'		=> \Smm\Grabber::SOURCE_TUMBLR,
				'remote_id'			=> $post->blogName."|".$post->id,
				
				'text'				=> trim($post->summary ?? ''),
				'attaches'			=> $attaches,
				
				'time'				=> strtotime($post->date),
				'likes'				=> $post->likeCount ?? 0,
				'comments'			=> $post->noteCount ?? 0,
				'reposts'			=> ($post->reblogCount ?? 0) + ($post->replyCount ?? 0),
				'images_cnt'		=> $images_cnt,
				'gifs_cnt'			=> $gifs_cnt,
				'list_type'			=> ($sort == 'top' ? \Smm\Grabber::LIST_TOP : \Smm\Grabber::LIST_NEW)
			];
			
			if (\Smm\Grabber::addNewPost((object) $item))
				echo "[ OK ] ".$post->postUrl."\n";
		}
	}
}
