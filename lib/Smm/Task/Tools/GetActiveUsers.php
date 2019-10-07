<?php
namespace Smm\Task\Tools;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;
use \Z\Net\Anticaptcha;

use \Smm\VK\Captcha;

class GetActiveUsers extends \Z\Task {
	protected $last_check_old_data = 0;
	protected $last_update_changed = 0;
	
	public function options() {
		return [
			'groups' => ''
		];
	}
	
	public function run($args) {
		$this->api = new \Smm\VK\API(\Smm\Oauth::getAccessToken('VK_GRABBER'));
		
		$groups = trim($args['groups']);
		$groups = $groups ? preg_split('/\s*,\s*/', $groups) : [];
		
		foreach ($groups as $group_id) {
			$ret = $this->grabPosts($group_id);
			
			$chunk_likes = array_chunk($ret['likes'], 50);
			$chunk_comments = array_chunk($ret['comments'], 50);
			
			echo "# Актив сообщества: $group_id\n";
			foreach ($this->fetchUsers($group_id, $ret['likes'], $ret['comments']) as $uid)
				echo "$uid\n";
		}
	}
	
	public function fetchUsers($group_id, $fetch_likes, $fetch_comments) {
		$fetch_likes_offset = 0;
		$fetch_comments_offset = 0;
		
		$users = [];
		
		while ($fetch_likes || $fetch_comments) {
			echo "# ".date("Y-m-d H:i:s")." - fetch likes:".count($fetch_likes).", comments:".count($fetch_comments)."\n";
			
			$js_code = '
				var MAX_API_CNT		= 25;
				var OWNER_ID		= -'.$group_id.';
				var fetch_likes		= '.json_encode($fetch_likes).';
				var fetch_comments	= '.json_encode($fetch_comments).';
				
				var fetch_likes_offset		= '.$fetch_likes_offset.';
				var fetch_comments_offset	= '.$fetch_comments_offset.';
				
				var result_likes = [];
				var result_comments = [];
				
				var api_calls = 0;
				
				while (api_calls < MAX_API_CNT && (fetch_likes.length > 0 || fetch_comments.length > 0)) {
					if (fetch_likes.length > 0 && api_calls < MAX_API_CNT) {
						var result = API.likes.getList({
							"type":		"post", 
							"owner_id":	OWNER_ID, 
							"item_id":	fetch_likes[0], 
							"offset":	fetch_likes_offset, 
							"count":	1000
						});
						
						if (result) {
							fetch_likes_offset = fetch_likes_offset + 1000;
							result_likes.push([fetch_likes[0], result]);
							
							if (fetch_likes_offset >= result.count) {
								fetch_likes.shift();
								fetch_likes_offset = 0;
							}
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
						
						if (result) {
							fetch_comments_offset = fetch_comments_offset + 100;
							result_comments.push([fetch_comments[0][0], fetch_comments[0][1], result]);
							
							if (fetch_comments_offset >= result.current_level_count) {
								fetch_comments.shift();
								fetch_comments_offset = fetch_comments[0][1] ? 10 : 0;
							}
						}
						
						api_calls = api_calls + 1;
					}
				}
				
				return {
					fetch_likes:			fetch_likes, 
					fetch_comments:			fetch_comments, 
					
					fetch_likes_offset:		fetch_likes_offset, 
					fetch_comments_offset:	fetch_comments_offset, 
					
					result_likes:			result_likes, 
					result_comments:		result_comments
				};
			';
			
			$ok = false;
			for ($i = 0; $i < 10; ++$i) {
				$exec_result = $this->api->exec("execute", [
					'code'		=> $js_code
				]);
				if ($exec_result->success()) {
					$ok = true;
					echo "=> OK\n";
					break;
				} else {
					if ($exec_result->error())
						echo "=> fetch metadata error: ".$exec_result->error()."\n";
					
					$flood = false;
					$sleep = false;
					
					if ($exec_result->errorCode() == \Smm\VK\API\Response::VK_ERR_FLOOD)
						$flood = true;
					if ($exec_result->errorCode() == \Smm\VK\API\Response::VK_ERR_TOO_FAST)
						$sleep = true;
					
					if (isset($exec_result->execute_errors)) {
						foreach ($exec_result->execute_errors as $err) {
							echo "=> fetch metadata error: ".$err->method.": #".$err->error_code." ".$err->error_msg."\n";
							if ($err->error_code == \Smm\VK\API\Response::VK_ERR_FLOOD)
								$flood = true;
							if ($err->error_code == \Smm\VK\API\Response::VK_ERR_TOO_FAST)
								$sleep = true;
						}
					}
					
					if ($flood)
						break;
					
					sleep($sleep ? 1 : 60);
				}
			}
			
			if (!$ok)
				break;
			
			$fetch_likes = $exec_result->response->fetch_likes;
			$fetch_comments = $exec_result->response->fetch_comments;
			
			$fetch_likes_offset = $exec_result->response->fetch_likes_offset;
			$fetch_comments_offset = $exec_result->response->fetch_comments_offset;
			
			foreach ($exec_result->response->result_likes as $chunk) {
				list ($post_id, $likes) = $chunk;
				foreach ($likes->items as $like)
					$users[] = $like;
			}
			
			foreach ($exec_result->response->result_comments as $chunk) {
				list ($post_id, $comment_id, $raw_comments) = $chunk;
				
				if (!$comment_id)
					$comments_count[$post_id] = $raw_comments->count;
				
				$comments = [];
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
				
				foreach ($comments as $comment) {
					if ($comment->deleted ?? false)
						continue;
					
					if ($comment->from_id > 0)
						$users[] = $comment->from_id;
				}
			}
		}
		
		return array_unique($users);
	}
	
	public function grabPosts($group_id) {
		$ret = [
			'likes'		=> [], 
			'comments'	=> []
		];
		
		$offset = 0;
		
		while (true) {
			echo date("Y-m-d H:i:s")." - grab posts, offset: $offset\n";
			
			$js_code = '
				var MAX_API_CNT		= 1;
				var OWNER_ID		= -'.$group_id.';
				
				var results = [];
				var offset = '.json_encode(max(0, $offset - 2)).';
				var count = 0;
				
				var api_calls = 0;
				var loop = true;
				var success = false;
				
				while (loop && api_calls < MAX_API_CNT) {
					var result = API.wall.get({
						"extended":		false, 
						"owner_id":		OWNER_ID, 
						"offset":		offset, 
						"count":		100
					});
					
					if (result) {
						offset = offset + 100;
						results.push(result);
						
						if (offset >= result.count)
							loop = false;
						
						count = result.count;
						success = true;
					}
					
					api_calls = api_calls + 1;
				}
				
				return {
					results:		results, 
					api_calls:		api_calls, 
					count:			count, 
					offset:			offset, 
					success:		success
				};
			';
			
			$ok = false;
			for ($i = 0; $i < 10; ++$i) {
				$exec_result = $this->api->exec("execute", [
					'code'		=> $js_code
				]);
				if ($exec_result->success()) {
					$ok = true;
					echo "=> OK\n";
					break;
				} else {
					if ($exec_result->error())
						echo "=> fetch metadata error: ".$exec_result->error()."\n";
					
					$flood = false;
					$sleep = false;
					
					if ($exec_result->errorCode() == \Smm\VK\API\Response::VK_ERR_FLOOD)
						$flood = true;
					if ($exec_result->errorCode() == \Smm\VK\API\Response::VK_ERR_TOO_FAST)
						$sleep = true;
					
					if (isset($exec_result->execute_errors)) {
						foreach ($exec_result->execute_errors as $err) {
							echo "=> fetch metadata error: ".$err->method.": #".$err->error_code." ".$err->error_msg."\n";
							if ($err->error_code == \Smm\VK\API\Response::VK_ERR_FLOOD)
								$flood = true;
							if ($err->error_code == \Smm\VK\API\Response::VK_ERR_TOO_FAST)
								$sleep = true;
						}
					}
					
					if ($flood)
						break;
					
					sleep($sleep ? 1 : 60);
				}
			}
			
			if (!$ok)
				break;
			
			$offset = $exec_result->response->offset;
			$stop = false;
			
			foreach ($exec_result->response->results as $response) {
				foreach ($response->items as $post) {
					$likes = $post->likes->count ?? 0;
					$comments = $post->comments->count ?? 0;
					
					if ($post->date >= time() - 3600 * 24 * 7) {
						if ($likes > 0)
							$ret['likes'][] = $post->id;
					
						if ($comments > 0)
							$ret['comments'][] = [$post->id, 0];
					} else {
						$stop = true;
					}
				}
			}
			
			if ($stop)
				break;
		}
		
		return $ret;
	}
}
