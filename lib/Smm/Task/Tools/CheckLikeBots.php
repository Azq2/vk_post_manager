<?php
namespace Smm\Task\Tools;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Config;
use \Z\Net\Anticaptcha;

use \Smm\VK\Captcha;

class CheckLikeBots extends \Z\Task {
	public function options() {
		return [
			'post'		=> ''
		];
	}
	
	public function run($args) {
		if (!preg_match("/([-\d]+)_(\d+)/", $args['post'], $m))
			throw new \Exception("Unknown post url!");
		
		$owner_id = $m[1];
		$id = $m[2];
		
		$api = new \Smm\VK\API(\Smm\Oauth::getAccessToken('VK_GRABBER'));
		
		$res = $api->exec("wall.getById", [
			'posts'		=> $owner_id."_".$id
		]);
		$api->setLimit(3, 1.2);
		
		if (!$res->success()) {
			echo $res->error();
			return;
		}
		
		$post = $res->response[0];
		
		$fetch_likes = [[$post->id]];
		$fetch_comments = [[$post->id, 0]];
		
		$fetch_likes_offset = 0;
		$fetch_comments_offset = 0;
		
		$likers_uids = [];
		$commentators_uids = [];
		
		echo "https://vk.com/wall".$post->owner_id."_".$post->id."\n";
		
		while ($fetch_likes || $fetch_comments) {
			echo date("Y-m-d H:i:s")." - fetch likes:".count($fetch_likes).", comments:".count($fetch_comments)."\n";
			
			$js_code = '
				var MAX_API_CNT		= 25;
				var OWNER_ID		= '.$post->owner_id.';
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
				$exec_result = $api->exec("execute", [
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
			
			echo "fetch_likes_offset=$fetch_likes_offset\n";
			echo "fetch_comments_offset=$fetch_comments_offset\n";
			
			foreach ($exec_result->response->result_likes as $chunk) {
				list ($post_id, $likes) = $chunk;
				
				foreach ($likes->items as $like)
					$likers_uids[] = $like;
			}
			
			foreach ($exec_result->response->result_comments as $chunk) {
				list ($post_id, $comment_id, $raw_comments) = $chunk;
				
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
					
					$commentators_uids[] = $comment->from_id;
				}
			}
		}
		
		$likers_uids = array_unique($likers_uids);
		$commentators_uids = array_unique($commentators_uids);
		
		$uids = [];
		foreach ($commentators_uids as $uid)
			$uids[] = $uid;
		foreach ($likers_uids as $uid)
			$uids[] = $uid;
		$uids = array_unique($uids);
		
		$users = [];
		
		foreach (array_chunk($uids, 1000) as $chunk) {
			do {
				$ok = false;
				for ($i = 0; $i < 10; ++$i) {
					$exec_result = $api->exec("users.get", [
						'user_ids'		=> implode(",", $chunk), 
						'fields'		=> 'last_seen'
					]);
					if ($exec_result->success()) {
						$ok = true;
						echo "=> OK\n";
						break;
					} else {
						if ($exec_result->error())
							echo "=> fetch users error: ".$exec_result->error()."\n";
						
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
					throw new \Exception("Can't fetch users!");
				
				foreach ($exec_result->response as $u)
					$users[$u->id] = $u;
				
				$new_chunk = [];
				foreach ($chunk as $uid) {
					if (!isset($users[$uid]))
						$new_chunk[] = $uid;
				}
				$chunk = $new_chunk;
			} while ($chunk);
		}
		
		$members = [];
		
		foreach (array_chunk($uids, 1000) as $chunk) {
			do {
				$ok = false;
				for ($i = 0; $i < 10; ++$i) {
					$exec_result = $api->exec("groups.isMember", [
						'user_ids'		=> implode(",", $chunk), 
						'group_id'		=> -$post->owner_id
					]);
					if ($exec_result->success()) {
						$ok = true;
						echo "=> OK\n";
						break;
					} else {
						if ($exec_result->error())
							echo "=> fetch members error: ".$exec_result->error()."\n";
						
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
					throw new \Exception("Can't fetch members!");
				
				foreach ($exec_result->response as $u)
					$members[$u->user_id] = $u->member;
				
				$new_chunk = [];
				foreach ($chunk as $uid) {
					if (!isset($members[$uid]))
						$new_chunk[] = $uid;
				}
				$chunk = $new_chunk;
			} while ($chunk);
		}
		
		$like_users = [
			'banned'		=> [], 
			'deleted'		=> [], 
			'bot'			=> [], 
			'user'			=> [], 
			'member'		=> [], 
			'guest'			=> []
		];
		
		foreach ($likers_uids as $uid) {
			$u = $users[$uid];
			
			if ($u->deactivated ?? false) {
				$like_users[$u->deactivated][] = $u;
			} else if ($u->last_seen->time < $post->date) {
				$like_users['bot'][] = $u;
			} else {
				$like_users['user'][] = $u;
			}
			
			if ($members[$uid]) {
				$like_users['member'][] = $u;
			} else {
				$like_users['guest'][] = $u;
			}
		}
		
		echo "******** Лайки ********\n";
		echo "Всего лайкеров: ".count($likers_uids)."\n";
		echo "Удалены: ".count($like_users['deleted'])." (".round(count($like_users['deleted']) / count($likers_uids) * 100, 2)."%)\n";
		echo "Забанены: ".count($like_users['deleted'])." (".round(count($like_users['deleted']) / count($likers_uids) * 100, 2)."%)\n";
		echo "Боты: ".count($like_users['bot'])." (".round(count($like_users['bot']) / count($likers_uids) * 100, 2)."%)\n";
		echo "Живые: ".count($like_users['user'])." (".round(count($like_users['user']) / count($likers_uids) * 100, 2)."%)\n";
		echo "Участник: ".count($like_users['member'])." (".round(count($like_users['member']) / count($likers_uids) * 100, 2)."%)\n";
		echo "Гость: ".count($like_users['guest'])." (".round(count($like_users['guest']) / count($likers_uids) * 100, 2)."%)\n";
		
		$comment_users = [
			'banned'		=> [], 
			'deleted'		=> [], 
			'bot'			=> [], 
			'user'			=> [], 
			'member'		=> [], 
			'guest'			=> []
		];
		
		foreach ($commentators_uids as $uid) {
			$u = $users[$uid];
			
			if ($u->deactivated ?? false) {
				$comment_users[$u->deactivated][] = $u;
			} else if ($u->last_seen->time < $post->date) {
				$comment_users['bot'][] = $u;
			} else {
				$comment_users['user'][] = $u;
			}
			
			if ($members[$uid]) {
				$comment_users['member'][] = $u;
			} else {
				$comment_users['guest'][] = $u;
			}
		}
		
		echo "******** Комменты ********\n";
		echo "Всего комментаторов: ".count($commentators_uids)."\n";
		echo "Удалены: ".count($comment_users['deleted'])." (".round(count($comment_users['deleted']) / count($commentators_uids) * 100, 2)."%)\n";
		echo "Забанены: ".count($comment_users['deleted'])." (".round(count($comment_users['deleted']) / count($commentators_uids) * 100, 2)."%)\n";
		echo "Боты: ".count($comment_users['bot'])." (".round(count($comment_users['bot']) / count($commentators_uids) * 100, 2)."%)\n";
		echo "Живые: ".count($comment_users['user'])." (".round(count($comment_users['user']) / count($commentators_uids) * 100, 2)."%)\n";
		echo "Участник: ".count($comment_users['member'])." (".round(count($comment_users['member']) / count($commentators_uids) * 100, 2)."%)\n";
		echo "Гость: ".count($comment_users['guest'])." (".round(count($comment_users['guest']) / count($commentators_uids) * 100, 2)."%)\n";
	}
}
