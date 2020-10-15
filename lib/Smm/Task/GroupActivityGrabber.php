<?php
namespace Smm\Task;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;
use \Z\Net\Anticaptcha;

use \Smm\VK\Captcha;

class GroupActivityGrabber extends \Z\Task {
	protected $last_check_old_data = 0;
	protected $last_update_changed = 0;
	
	protected $api, $api_index = 0, $has_free_api = true;
	
	public function options() {
		return [
			'fast_update_group_id' => 0
		];
	}
	
	public function run($args) {
		if (!\Smm\Utils\Lock::lock(__CLASS__)) {
			echo "Already running.\n";
			return;
		}
		
		ini_set('memory_limit', '1G');
		
		$this->api = [
			new \Smm\VK\API(\Smm\Oauth::getAccessToken('VK_STAT')), 
			new \Smm\VK\API(\Smm\Oauth::getAccessToken('VK_STAT_2')), 
			new \Smm\VK\API(\Smm\Oauth::getAccessToken('VK')), 
		];
		
		$groups_with_access = false;
		
		foreach ($this->api as $api) {
			$tmp_groups_with_access = [];
			
			for ($i = 0; $i < 3; ++$i) {
				// Обрабатываем только те соо, к которым у нас есть доступ
				$query = $api->exec("groups.get", [
					'filter'		=> 'admin,moder,editor', 
					'offset'		=> 0, 
					'limit'			=> 1000
				]);
				
				if ($query->success()) {
					$tmp_groups_with_access = $query->response->items;
					break;
				} else {
					$this->printExecuteErrors($query);
					sleep(1);
				}
			}
			
			if ($groups_with_access === false)
				$groups_with_access = $tmp_groups_with_access;
			
			$groups_with_access = array_intersect($tmp_groups_with_access, $groups_with_access);
		}
		
		if (!$groups_with_access) {
			echo "No groups with rights.\n";
			return;
		}
		
		foreach (DB::select()->from('vk_groups')->where('deleted', '=', 0)->execute() as $group) {
			echo "**** ".$group['id']." ***\n";
			
			if (!in_array($group['id'], $groups_with_access)) {
				echo "SKIP: no rights in this group.\n";
				continue;
			}
			
			$this->last_check_old_data = 0;
			$this->last_update_changed = 0;
			
			DB::insert('vk_activity_sources')
				->ignore()
				->set([
					'owner_id'		=> -$group['id'], 
					'offset'		=> 0, 
					'count'			=> 0, 
					'init_done'		=> 0
				])
				->execute();
			
			$source = DB::select()
				->from('vk_activity_sources')
				->where('owner_id', '=', -$group['id'])
				->execute()
				->current();
			
			if ($source['init_done']) {
				$cache = \Z\Cache::instance();
				
				if ($group['id'] == $args['fast_update_group_id'] || $args['fast_update_group_id'] == -1) {
					echo "Fast update!\n";
					
					$this->checkPosts($group, true);
					
					for  ($i = 0; $i < 50; ++$i) {
						$this->last_check_old_data = 0;
						$this->last_update_changed = 0;
						$this->updateChangedPosts($group, 2000);
					}
				} else {
					$last_full_check = $cache->get("vk_activity_last_full_check:".$group['id']) ?: 0;
					
					$this->grabPosts($group, 0, false);
					
					if (time() - $last_full_check >= 3600) {
						$this->checkPosts($group, true);
						$cache->set("vk_activity_last_full_check:".$group['id'], time());
						$this->updateChangedPosts($group, 2000);
					} else {
						$this->checkPosts($group, false);
						$this->updateChangedPosts($group, 1000);
					}
				}
			} else {
				if ($source['offset'] > 0) {
					$this->checkPosts($group, false);
					$this->updateChangedPosts($group, 1000);
				}
				$this->grabPosts($group, max(0, $source['offset'] - 50), true);
			}
		}
	}
	
	public function updateChangedPosts($group, $limit) {
		if (time() - $this->last_update_changed < 600)
			return;
		
		echo date("Y-m-d H:i:s")." - update changed posts...\n";
		
		$this->last_update_changed = time();
		
		$posts_query = DB::select()
			->from('vk_activity_posts')
			->where('owner_id', '=', -$group['id'])
			->andOpenGroup()
				->orWhere('need_check_likes', '=', 1)
				->orWhere('need_check_comments', '=', 1)
			->andCloseGroup()
			->order('id', 'DESC')
			->limit($limit);
		
		$posts = $posts_query->execute()->asArray('id');
		
		$fetch_likes = [];
		$fetch_comments = [];
		
		foreach ($posts as $post) {
			if ($post['need_check_likes'])
				$fetch_likes[] = $post['id'];
			
			if ($post['need_check_comments'])
				$fetch_comments[] = [$post['id'], 0];
		}
		
		$fetch_likes_offset = 0;
		$fetch_comments_offset = 0;
		
		while ($fetch_likes || $fetch_comments) {
			echo date("Y-m-d H:i:s")." - fetch likes:".count($fetch_likes).", comments:".count($fetch_comments)."\n";
			
			$js_code = '
				var MAX_API_CNT		= 15;
				var OWNER_ID		= -'.$group['id'].';
				var fetch_likes		= '.json_encode($fetch_likes).';
				var fetch_comments	= '.json_encode($fetch_comments).';
				
				var fetch_likes_offset		= '.$fetch_likes_offset.';
				var fetch_comments_offset	= '.$fetch_comments_offset.';
				
				var result_likes = [];
				var result_comments = [];
				
				var api_calls = 0;
				
				var success = false;
				
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
							
							success = true;
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
							
							success = true;
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
					result_comments:		result_comments, 
					
					success:				success
				};
			';
			
			$ok = false;
			for ($i = 0; $i < 10; ++$i) {
				$exec_result = $this->getApi()->exec("execute", [
					'code'		=> $js_code
				]);
				
				$this->printExecuteErrors($exec_result);
				
				if ($this->hasExecuteError($exec_result, \Smm\VK\API\Response::VK_ERR_RATE_LIMIT))
					$this->nextApi();
				
				if ($exec_result->success() && $exec_result->response->success) {
					$ok = true;
					echo "=> OK\n";
					break;
				} else {
					$sleep = false;
					
					if ($this->hasExecuteError($exec_result, \Smm\VK\API\Response::VK_ERR_FLOOD))
						break;
					
					if ($this->hasExecuteError($exec_result, \Smm\VK\API\Response::VK_ERR_TOO_FAST))
						$sleep = true;
					
					if (!$this->hasFreeApi() && $this->hasExecuteError($exec_result, \Smm\VK\API\Response::VK_ERR_RATE_LIMIT))
						break;
					
					sleep($sleep ? 60 : 1);
				}
			}
			
			if (!$ok)
				break;
			
			$fetch_likes = $exec_result->response->fetch_likes;
			$fetch_comments = $exec_result->response->fetch_comments;
			
			$fetch_likes_offset = $exec_result->response->fetch_likes_offset;
			$fetch_comments_offset = $exec_result->response->fetch_comments_offset;
			
			$query = DB::insert('vk_activity_likes', ['owner_id', 'post_id', 'user_id', 'date', 'dt'])
				->ignore();
			
			$likes_count = [];
			foreach ($exec_result->response->result_likes as $chunk) {
				list ($post_id, $likes) = $chunk;
				
				if (!in_array($post_id, $fetch_likes))
					$likes_count[$post_id] = $likes->count;
				
				if (!isset($posts[$post_id]['date']))
					throw \Exception("$post_id not found!!!");
				
				foreach ($likes->items as $like) {
					$query->values([
						-$group['id'], 
						$post_id, 
						$like, 
						$posts[$post_id]['last_likes_check'] ? date("Y-m-d H:i:s", time())	: date("Y-m-d H:i:s", $posts[$post_id]['date']), 
						$posts[$post_id]['last_likes_check'] ? date("Y-m-d", time())		: date("Y-m-d", $posts[$post_id]['date'])
					]);
				}
			}
			
			if ($query->countValues())
				 $query->execute();
			
			foreach ($likes_count as $post_id => $cnt) {
				DB::update('vk_activity_posts')
					->set([
						'likes'					=> $cnt, 
						'need_check_likes'		=> 0, 
						'last_likes_check'		=> time()
					])
					->where('owner_id', '=', -$group['id'])
					->where('id', '=', $post_id)
					->execute();
			}
			
			$query = DB::insert('vk_activity_comments', ['owner_id', 'post_id', 'comment_id', 'user_id', 'date', 'dt', 'text_length', 'attaches_cnt', 'images_cnt', 'stickers_cnt'])
				->ignore();
			
			$comments_count = [];
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
					
					$meta = \Smm\VK\Posts::analyzeComment($comment);
					
					$query->values([
						-$group['id'], 
						$post_id, 
						$comment->id, 
						$comment->from_id, 
						date("Y-m-d H:i:s", $comment->date), 
						date("Y-m-d", $comment->date), 
						$meta['text_length'], 
						$meta['attaches_cnt'], 
						$meta['images_cnt'], 
						$meta['stickers_cnt']
					]);
				}
			}
			
			if ($query->countValues())
				 $query->execute();
			
			foreach ($comments_count as $post_id => $cnt) {
				DB::update('vk_activity_posts')
					->set([
						'comments'				=> $cnt, 
						'need_check_comments'	=> 0, 
						'last_comments_check'	=> time()
					])
					->where('owner_id', '=', -$group['id'])
					->where('id', '=', $post_id)
					->execute();
			}
		}
	}
	
	public function checkPosts($group, $full) {
		if (time() - $this->last_check_old_data < 600)
			return;
		
		$this->last_check_old_data = time();
		
		$last_post_id = 0;
		
		while (true) {
			$posts_query = DB::select()
				->from('vk_activity_posts')
				->where('owner_id', '=', -$group['id'])
				->andOpenGroup()
					->orWhere('need_check_likes', '=', 0)
					->orWhere('need_check_comments', '=', 0)
				->andCloseGroup()
				->order('id', 'DESC')
				->limit(2500);
			
			if ($last_post_id)
				$posts_query->where('id', '<', $last_post_id);
			
			$posts = $posts_query->execute()->asArray('id');
			
			if (!$posts)
				break;
			
			$last_post_id = array_keys($posts)[count($posts) - 1];
			
			$vk_ids = array_values(array_map(function ($v) {
				return $v['owner_id']."_".$v['id'];
			}, $posts));
			
			$js_code = '
				var results = [];
				var ids = '.json_encode($vk_ids).';
				var success = false;
				var api_calls = 0;
				
				var i = 0;
				while (i < ids.length && api_calls < 15) {
					var chunk = ids.slice(i, i + 100);
					
					var result = API.wall.getById({
						"posts":				chunk, 
						"extended":				false, 
						"copy_history_depth":	1
					});
					
					if (result) {
						i = i + 100;
						success = true;
						results.push(result);
					}
					
					api_calls = api_calls + 1;
				}
				
				return {
					results:		results, 
					api_calls:		api_calls, 
					success:		success
				};
			';
			
			echo date("Y-m-d H:i:s")." - check ".count($vk_ids)." posts\n";
			
			$ok = false;
			for ($i = 0; $i < 10; ++$i) {
				$exec_result = $this->getApi()->exec("execute", [
					'code'		=> $js_code
				]);
				
				$this->printExecuteErrors($exec_result);
				
				if ($this->hasExecuteError($exec_result, \Smm\VK\API\Response::VK_ERR_RATE_LIMIT))
					$this->nextApi();
				
				if ($exec_result->success() && $exec_result->response->success) {
					$ok = true;
					echo "=> OK\n";
					break;
				} else {
					$sleep = false;
					
					if ($this->hasExecuteError($exec_result, \Smm\VK\API\Response::VK_ERR_FLOOD))
						break;
					
					if ($this->hasExecuteError($exec_result, \Smm\VK\API\Response::VK_ERR_TOO_FAST))
						$sleep = true;
					
					if (!$this->hasFreeApi() && $this->hasExecuteError($exec_result, \Smm\VK\API\Response::VK_ERR_RATE_LIMIT))
						break;
					
					sleep($sleep ? 60 : 1);
				}
			}
			
			if (!$ok)
				break;
			
			foreach ($exec_result->response->results as $response) {
				foreach ($response as $post) {
					$likes = $post->likes->count ?? 0;
					$comments = $post->comments->count ?? 0;
					$views = $post->views->count ?? 0;
					$reposts = $post->reposts->count ?? 0;
					
					$need_check_likes = $posts[$post->id]['need_check_likes'];
					$need_check_comments = $posts[$post->id]['need_check_likes'];
					$need_update = false;
					
					if ($posts[$post->id]['views'] != $views) {
						echo "#".$post->id." - changed views [".$posts[$post->id]['views']." != ".$views."]\n";
						$need_update = true;
					}
					
					if ($posts[$post->id]['reposts'] != $reposts) {
						echo "#".$post->id." - changed reposts [".$posts[$post->id]['reposts']." != ".$reposts."]\n";
						$need_update = true;
					}
					
					if ($posts[$post->id]['likes'] != $likes) {
						echo "#".$post->id." - changed likes [".$posts[$post->id]['likes']." != ".$likes."]\n";
						$need_check_likes = 1;
					}
					
					if ($posts[$post->id]['comments'] != $comments) {
						echo "#".$post->id." - changed comments [".$posts[$post->id]['comments']." != ".$comments."]\n";
						$need_check_comments = 1;
					}
					
					if ($need_check_comments || $need_check_likes || $need_update) {
						DB::update('vk_activity_posts')
							->set([
								'need_check_likes'		=> $need_check_likes, 
								'need_check_comments'	=> $need_check_comments, 
								'views'					=> $views, 
								'reposts'				=> $reposts, 
								'last_check'			=> time()
							])
							->where('owner_id', '=', -$group['id'])
							->where('id', '=', $post->id)
							->execute();
					}
				}
			}
			
			if (!$full)
				break;
		}
	}
	
	public function grabPosts($group, $offset, $full) {
		while (true) {
			echo date("Y-m-d H:i:s")." - grab posts, offset: $offset\n";
			
			$js_code = '
				var MAX_API_CNT		= 15;
				var OWNER_ID		= -'.$group['id'].';
				
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
				$exec_result = $this->getApi()->exec("execute", [
					'code'		=> $js_code
				]);
				
				$this->printExecuteErrors($exec_result);
				
				if ($this->hasExecuteError($exec_result, \Smm\VK\API\Response::VK_ERR_RATE_LIMIT))
					$this->nextApi();
				
				if ($exec_result->success() && $exec_result->response->success) {
					$ok = true;
					echo "=> OK\n";
					break;
				} else {
					$sleep = false;
					
					if ($this->hasExecuteError($exec_result, \Smm\VK\API\Response::VK_ERR_FLOOD))
						break;
					
					if ($this->hasExecuteError($exec_result, \Smm\VK\API\Response::VK_ERR_TOO_FAST))
						$sleep = true;
					
					if (!$this->hasFreeApi() && $this->hasExecuteError($exec_result, \Smm\VK\API\Response::VK_ERR_RATE_LIMIT))
						break;
					
					sleep($sleep ? 60 : 1);
				}
			}
			
			if (!$ok)
				break;
			
			$offset = $exec_result->response->offset;
			
			$affected = 0;
			
			foreach ($exec_result->response->results as $response) {
				foreach ($response->items as $post) {
					$likes = $post->likes->count ?? 0;
					$comments = $post->comments->count ?? 0;
					
					$affected += DB::insert('vk_activity_posts')
						->ignore()
						->set([
							'need_check_likes'		=> $likes > 0, 
							'need_check_comments'	=> $comments > 0, 
							'owner_id'				=> -$group['id'], 
							'id'					=> $post->id, 
							'date'					=> $post->date
						])
						->execute()
						->affected();
				}
			}
			
			if ($full) {
				DB::update('vk_activity_sources')
					->set([
						'offset'		=> $offset, 
						'count'			=> $exec_result->response->count, 
						'init_done'		=> $offset >= $exec_result->response->count ? 1 : 0
					])
					->where('owner_id', '=', -$group['id'])
					->execute();
			}
			
			if ($full) {
				$this->checkPosts($group, false);
				$this->updateChangedPosts($group, 1000);
			} else {
				if (!$affected)
					break;
			}
			
			if ($offset >= $exec_result->response->count) {
				echo "done: ".$exec_result->response->count." posts\n";
				break;
			}
		}
	}
	
	public function getApi() {
		return $this->api[$this->api_index];
	}
	
	public function nextApi() {
		++$this->api_index;
		
		if ($this->api_index >= count($this->api)) {
			$this->api_index = 0;
			$this->has_free_api = false;
		}
	}
	
	public function hasFreeApi() {
		return $this->has_free_api;
	}
	
	public function hasExecuteError($response, $code) {
		if ($response->errorCode() == $code)
			return true;
		if (isset($response->execute_errors)) {
			foreach ($response->execute_errors as $err) {
				if ($err->error_code == $code)
					return true;
			}
		}
		return false;
	}
	
	
	public function printExecuteErrors($response) {
		if ($response->error())
			echo "=> api error: ".$response->error()."\n";
		
		if (isset($response->execute_errors)) {
			$errors_list = [];
			foreach ($response->execute_errors as $err)
				$errors_list[] = "=> api error: ".$err->method.": #".$err->error_code." ".$err->error_msg;
			$errors_list = array_unique($errors_list);
			
			echo implode("\n", $errors_list)."\n";
		}
	}
}
