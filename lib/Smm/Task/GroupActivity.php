<?php
namespace Smm\Task;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;
use \Z\Net\VkApi;
use \Z\Net\Anticaptcha;

use \Smm\VK\Captcha;

class GroupActivity extends \Z\Task {
	public function options() {
		return [
			'full' 			=> 0, 
			'only_likes'	=> 0, 
			'instance'		=> ''
		];
	}
	
	public function run($args) {
		if (!\Smm\Utils\Lock::lock(__CLASS__.":".$args['instance'])) {
			echo "Already running.\n";
			return;
		}
		
		foreach (DB::select()->from('vk_groups')->where('deleted', '=', 0)->execute() as $group) {
			echo date("Y-m-d H:i:s")." - #".$group['id']." [ GROUP: ".$group['name']." ]\n";
			
			$full_fetch = $args['full'];
			
			if (!$full_fetch)
				$full_fetch = !\Smm\Globals::get($group['id'], "activity_stat_done");
			
			if ($full_fetch)
				echo "ATTENTION: full-fetch mode!\n";
			
			$api = new VkApi(\Smm\Oauth::getAccessToken('VK'));
			$api->setLimit(3, 1.1);
			
			$CHUNK_SIZE = 100;
			$offset = 0;
			
			while (true) {
				echo "LOAD: $offset ... ".($offset + $CHUNK_SIZE)."\n";
				
				for ($i = 0; $i < 10; ++$i) {
					$all_posts = $api->exec("wall.get", [
						"owner_id"		=> -$group['id'], 
						"extended"		=> false, 
						"count"			=> $CHUNK_SIZE, 
						"offset"		=> $offset
					]);
					if ($all_posts->success()) {
						break;
					} else {
						echo "=> fetch posts error: ".$all_posts->error()."\n";
						sleep(60);
					}
				}
				
				$fetch_likes = [];
				$fetch_comments = [];
				$fetch_reposts = [];
				
				foreach ($all_posts->response->items as $post) {
					if (!$args['only_likes']) {
						if ($post->comments->count)
							$fetch_comments[] = [$post->id, 0];
					}
					
					if ($post->likes->count)
						$fetch_likes[] = $post->id;
					
					if (!$args['only_likes']) {
						if ($post->reposts->count)
							$fetch_reposts[] = $post->id;
					}
				}
				
				echo "posts with likes: ".count($fetch_likes).", with comments: ".count($fetch_comments).", with reposts: ".count($fetch_reposts)."\n";
				
				$fetch_likes_offset = 0;
				$fetch_comments_offset = 0;
				$fetch_reposts_offset = 0;
				
				$affected = 0;
				
				while ($fetch_likes || $fetch_comments || $fetch_reposts) {
					echo "=> fetch likes:".count($fetch_likes).", comments:".count($fetch_comments).", reposts:".count($fetch_reposts)."\n";
					
					$js_code = '
						var MAX_API_CNT		= 25;
						var OWNER_ID		= -'.$group['id'].';
						var fetch_likes		= '.json_encode($fetch_likes).';
						var fetch_comments	= '.json_encode($fetch_comments).';
						var fetch_reposts	= '.json_encode($fetch_reposts).';
						
						var fetch_likes_offset		= '.$fetch_likes_offset.';
						var fetch_comments_offset	= '.$fetch_comments_offset.';
						var fetch_reposts_offset	= '.$fetch_likes_offset.';
						
						var result_likes = [];
						var result_comments = [];
						var result_reposts = [];
						
						var api_calls = 0;
						
						while (api_calls < MAX_API_CNT && (fetch_likes.length > 0 || fetch_comments.length > 0 || fetch_reposts.length > 0)) {
							if (fetch_likes.length > 0 && api_calls < MAX_API_CNT) {
								var result = API.likes.getList({
									"type":		"post", 
									"owner_id":	OWNER_ID, 
									"item_id":	fetch_likes[0], 
									"offset":	fetch_likes_offset, 
									"count":	1000
								});
								fetch_likes_offset = fetch_likes_offset + 1000;
								result_likes.push([fetch_likes[0], result]);
								
								if (fetch_likes_offset >= result.count) {
									fetch_likes.shift();
									fetch_likes_offset = 0;
								}
								
								api_calls = api_calls + 1;
							}
							
							if (fetch_comments.length > 0 && api_calls < MAX_API_CNT) {
								var result = API.wall.getComments({
									"owner_id":				OWNER_ID, 
									"post_id":				fetch_comments[0][0], 
									"comment_id":			fetch_comments[0][1], 
									"offset":				fetch_comments_offset, 
									"preview_length":		0, 
									"thread_items_count":	10, 
									"count":				100
								});
								fetch_comments_offset = fetch_comments_offset + 100;
								result_comments.push([fetch_comments[0][0], result]);
								
								if (fetch_comments_offset >= result.current_level_count) {
									fetch_comments.shift();
									fetch_comments_offset = fetch_comments[0][1] ? 10 : 0;
								}
								
								api_calls = api_calls + 1;
							}
							
							if (fetch_reposts.length > 0 && api_calls < MAX_API_CNT) {
								var result = API.wall.getReposts({
									"owner_id":	OWNER_ID, 
									"post_id":	fetch_reposts[0], 
									"offset":	fetch_reposts_offset, 
									"count":	1000
								});
								fetch_reposts_offset = fetch_reposts_offset + 1000;
								result_reposts.push([fetch_reposts[0], result]);
								
								if (fetch_reposts_offset >= result.count) {
									fetch_reposts.shift();
									fetch_reposts_offset = 0;
								}
								
								api_calls = api_calls + 1;
							}
						}
						
						return {
							fetch_likes:			fetch_likes, 
							fetch_comments:			fetch_comments, 
							fetch_reposts:			fetch_reposts, 
							
							fetch_likes_offset:		fetch_likes_offset, 
							fetch_comments_offset:	fetch_comments_offset, 
							fetch_reposts_offset:	fetch_reposts_offset, 
							
							result_likes:			result_likes, 
							result_comments:		result_comments, 
							result_reposts:			result_reposts
						};
					';
					
					for ($i = 0; $i < 10; ++$i) {
						$exec_result = $api->exec("execute", [
							'code'		=> $js_code
						]);
						if ($exec_result->success() && !($exec_result->execute_errors ?? false)) {
							break;
						} else {
							if ($exec_result->error())
								echo "=> fetch metadata error: ".$exec_result->error()."\n";
							
							if (isset($exec_result->execute_errors)) {
								foreach ($exec_result->execute_errors as $err)
									echo "=> fetch metadata error: ".$err->method.": #".$err->error_code." ".$err->error_msg."\n";
							}
							
							sleep(60);
						}
					}
					
					if ($exec_result->error())
						break;
					
					$fetch_likes = $exec_result->response->fetch_likes;
					$fetch_comments = $exec_result->response->fetch_comments;
					$fetch_reposts = $exec_result->response->fetch_reposts;
					
					$fetch_likes_offset = $exec_result->response->fetch_likes_offset;
					$fetch_comments_offset = $exec_result->response->fetch_comments_offset;
					$fetch_reposts_offset = $exec_result->response->fetch_reposts_offset;
					
					$query = DB::insert('vk_user_likes', ['group_id', 'post_id', 'user_id', 'date'])
						->ignore();
					
					$query_items = 0;
					
					foreach ($exec_result->response->result_likes as $chunk) {
						list ($post_id, $likes) = $chunk;
						
						if ($likes) {
							foreach ($likes->items as $like) {
								$query->values([
									$group['id'], 
									$post_id, 
									$like, 
									$full_fetch ? date("Y-m-d H:i:s", $post->date) : date("Y-m-d H:i:s", time())
								]);
								++$query_items;
							}
						}
					}
					
					if ($query_items)
						$affected += $query->execute()->affected();
					
					$query = DB::insert('vk_user_comments', ['group_id', 'post_id', 'comment_id', 'user_id', 'date', 'text_length', 'attaches_cnt', 'images_cnt', 'stickers_cnt'])
						->ignore();
					
					$query_items = 0;
					
					foreach ($exec_result->response->result_comments as $chunk) {
						list ($post_id, $raw_comments) = $chunk;
						
						$comments = [];
						
						if ($raw_comments) {
							foreach ($raw_comments->items as $comment) {
								$comments[] = $comment;
								
								if (isset($comment->thread)) {
									foreach ($comment->thread->items as $comment2)
										$comments[] = $comment2;
									
									if (count($comment->thread->items) < $comment->thread->count)
										$fetch_comments[] = [$post_id, $comment->id];
									
									if (isset($comment2->thread) && $comment2->thread->count)
										$fetch_comments[] = [$post_id, $comment2->id];
									
									unset($comment->thread);
								}
							}
						}
						
						foreach ($comments as $comment) {
							if ($comment->deleted ?? false)
								continue;
							
							$meta = \Smm\VK\Posts::analyzeComment($comment);
							
							$query->values([
								$group['id'], 
								$post_id, 
								$comment->id, 
								$comment->from_id, 
								date("Y-m-d H:i:s", $comment->date), 
								$meta['text_length'], 
								$meta['attaches_cnt'], 
								$meta['images_cnt'], 
								$meta['stickers_cnt']
							]);
							++$query_items;
						}
					}
					
					if ($query_items)
						$affected += $query->execute()->affected();
					
					$query = DB::insert('vk_user_reposts', ['group_id', 'post_id', 'user_id', 'date'])
						->ignore();
					
					$query_items = 0;
					
					foreach ($exec_result->response->result_reposts as $chunk) {
						list ($post_id, $reposts) = $chunk;
						
						if ($reposts) {
							foreach ($reposts->items as $repost) {
								$query->values([
									$group['id'], 
									$post_id, 
									$repost->from_id, 
									date("Y-m-d H:i:s", $repost->date)
								]);
								++$query_items;
							}
						}
					}
					
					if ($query_items)
						$affected += $query->execute()->affected();
					
					sleep(8);
				}
				
				if (!$affected && !$full_fetch) {
					echo "=> done - no more new likes, comments or reposts.\n";
					break;
				}
				
				if ($offset + $CHUNK_SIZE >= $all_posts->response->count) {
					echo "=> done - no more posts.\n";
					\Smm\Globals::set($group['id'], "activity_stat_done", 1);
					break;
				}
				
				$offset += $CHUNK_SIZE;
				sleep(1);
			}
		}
	}
}
