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
			'mode'		=> 'fast'
		];
	}
	
	public function run($args) {
		if (!\Smm\Utils\Lock::lock(__CLASS__.":".$args['mode'])) {
			echo "Already running.\n";
			return;
		}
		
		foreach (DB::select()->from('vk_groups')->where('deleted', '=', 0)->execute() as $group) {
			// Stat collect progress
			DB::insert('vk_users_stat_progress')
				->ignore()
				->set([
					'group_id'		=> $group['id'], 
					'offset'		=> 0, 
					'done'			=> 0
				])
				->execute();
			
			$progress = DB::select()
				->from('vk_users_stat_progress')
				->where('group_id', '=', $group['id'])
				->execute()
				->current();
			
			switch ($args['mode']) {
				case "aggregation":
					echo date("Y-m-d H:i:s")." - #".$group['id']." [ GROUP: ".$group['name']." ]\n";
					$start = microtime(true);
					$this->aggregateStat($group, $progress['done'] && !$progress['stat_done']);
					$elapsed = microtime(true) - $start;
					echo date("Y-m-d H:i:s")." - chunk done, ".round($elapsed, 2)."\n";
				break;
				
				case "likes":
					echo date("Y-m-d H:i:s")." - #".$group['id']." [ GROUP: ".$group['name']." ]\n";
					
					$start = microtime(true);
					$this->grabStat($group, 0, 100, true);
					$elapsed = microtime(true) - $start;
					
					echo date("Y-m-d H:i:s")." - chunk done, ".round($elapsed, 2)."\n";
				break;
				
				case "archive":
					if (!$progress['done']) {
						echo date("Y-m-d H:i:s")." - #".$group['id']." [ GROUP: ".$group['name']." ]\n";
						
						$start = microtime(true);
						$status = $this->grabStat($group, $progress['offset'], 100, false);
						$elapsed = microtime(true) - $start;
						
						echo date("Y-m-d H:i:s")." - chunk done, ".round($elapsed, 2)."\n";
						
						if ($status != 'error') {
							DB::update('vk_users_stat_progress')
								->set([
									'done'		=> $status == 'no_more_posts' ? 0 : 1, 
									'offset'	=> $status == 'no_more_posts' ? $progress['offset'] + 100 : 0
								])
								->execute();
						}
					}
				break;
			}
		}
	}
	
	public function aggregateStat($group, $full) {
		$last_time = 0;
		
		if (!$full) {
			$last_time = DB::select(['UNIX_TIMESTAMP(MAX(date))', 'last_time'])
				->from('vk_users_stat')
				->where('group_id', '=', $group['id'])
				->execute()
				->get('last_time', 0);
		}
		
		if ($last_time)
			echo "Last stat date: ".date("Y-m-d 00:00:00", $last_time)."\n";
		
		// Do aggregate comments stat
		$stat = DB::select(
			['DATE(date)', 'dt'], 
			'user_id', 
			['COUNT(*)', 'comments'], 
			['SUM(IF(text_length > 2 OR images_cnt > 0, 1, 0))', 'comments_meaningful']
		)
			->from('vk_user_comments')
			->where('group_id', '=', $group['id'])
			->group('user_id')
			->group('dt');
		
		if ($last_time)
			$stat->where('date', '>=', date("Y-m-d 00:00:00", $last_time));
		
		$insert = DB::insert('vk_users_stat', ['date', 'group_id', 'user_id', 'comments', 'comments_meaningful'])
			->onDuplicateSetValues('comments')
			->onDuplicateSetValues('comments_meaningful');
		
		foreach ($stat->execute() as $row) {
			$insert->values([$row['dt'], $group['id'], $row['user_id'], $row['comments'], $row['comments_meaningful']]);
			if ($insert->countValues() >= 1000) {
				$insert->execute();
				$insert->setValues([]);
			}
		}
		
		if ($insert->countValues() > 0)
			$insert->execute();
		
		// Do aggregate likes stat
		$stat = DB::select(
			['DATE(date)', 'dt'], 
			'user_id', 
			['COUNT(*)', 'likes']
		)
			->from('vk_user_likes')
			->where('group_id', '=', $group['id'])
			->group('user_id')
			->group('dt');
		
		if ($last_time)
			$stat->where('date', '>=', date("Y-m-d 00:00:00", $last_time));
		
		$insert = DB::insert('vk_users_stat', ['date', 'group_id', 'user_id', 'likes'])
			->onDuplicateSetValues('likes');
		
		foreach ($stat->execute() as $row) {
			$insert->values([$row['dt'], $group['id'], $row['user_id'], $row['likes']]);
			if ($insert->countValues() >= 1000) {
				$insert->execute();
				$insert->setValues([]);
			}
		}
		
		if ($insert->countValues() > 0)
			$insert->execute();
		
		
		// Do aggregate reposts stat
		$stat = DB::select(
			['DATE(date)', 'dt'], 
			'user_id', 
			['COUNT(*)', 'reposts']
		)
			->from('vk_user_reposts')
			->where('group_id', '=', $group['id'])
			->group('user_id')
			->group('dt');
		
		if ($last_time)
			$stat->where('date', '>=', date("Y-m-d 00:00:00", $last_time));
		
		$insert = DB::insert('vk_users_stat', ['date', 'group_id', 'user_id', 'reposts'])
			->onDuplicateSetValues('reposts');
		
		foreach ($stat->execute() as $row) {
			$insert->values([$row['dt'], $group['id'], $row['user_id'], $row['reposts']]);
			if ($insert->countValues() >= 1000) {
				$insert->execute();
				$insert->setValues([]);
			}
		}
		
		if ($insert->countValues() > 0)
			$insert->execute();
		
		if ($full) {
			DB::update('vk_users_stat_progress')
				->set([
					'stat_done'	=> 1
				])
				->execute();
		}
	}
	
	public function grabStat($group, $offset, $limit, $only_likes) {
		$api = new VkApi(\Smm\Oauth::getAccessToken('VK'));
		$api->setLimit(3, 1.1);
		
		echo "LOAD: $offset ... ".($offset + $limit)."\n";
		
		for ($i = 0; $i < 10; ++$i) {
			$all_posts = $api->exec("wall.get", [
				"owner_id"		=> -$group['id'], 
				"extended"		=> false, 
				"count"			=> $limit, 
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
			if (!$only_likes) {
				if ($post->comments->count)
					$fetch_comments[] = [$post->id, 0];
			}
			
			if ($post->likes->count)
				$fetch_likes[] = $post->id;
			
			if (!$only_likes) {
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
					
					$flood = false;
					
					if ($exec_result->errorCode() == \Z\Net\VkApi\Response::VK_ERR_FLOOD || $exec_result->errorCode() == \Z\Net\VkApi\Response::VK_ERR_TOO_FAST)
						$flood = true;
					
					if (isset($exec_result->execute_errors)) {
						foreach ($exec_result->execute_errors as $err) {
							echo "=> fetch metadata error: ".$err->method.": #".$err->error_code." ".$err->error_msg."\n";
							if ($err->error_code == \Z\Net\VkApi\Response::VK_ERR_FLOOD || $err->error_code == \Z\Net\VkApi\Response::VK_ERR_TOO_FAST)
								$flood = true;
						}
					}
					
					if ($flood)
						return 'error';
					
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
							$offset ? date("Y-m-d H:i:s", $post->date) : date("Y-m-d H:i:s", time())
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
			
			if ($only_likes) {
				sleep(3);
			} else {
				sleep(60);
			}
		}
		
		if ($offset + $limit >= $all_posts->response->count)
			return 'no_more_posts';
		
		return 'ok';
	}
}
