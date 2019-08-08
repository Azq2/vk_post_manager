<?php
namespace Smm\Task;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;
use \Z\Net\VkApi;
use \Z\Net\Anticaptcha;

use \Smm\VK\Captcha;

class AutoCommentator extends \Z\Task {
	public function options() {
		return [
			'anticaptcha'	=> 0
		];
	}
	
	public function getCommentsQueue() {
		$now = time();
		$need_to_post = DB::select()
			->from(['vk_posts_comments', 'c'])
			->join(['vk_posts_queue', 'q'], 'INNER')
			->on('c.id', '=', 'q.id')
			->on('c.group_id', '=', 'q.group_id')
			->where('q.real_date', '<=', $now + 120)
			->where('q.real_date', '>=', $now - 300)
			->where('q.real_date', '!=', 0)
			->execute()
			->asArray();
		return $need_to_post;
	}
	
	public function run($args) {
		if (!\Smm\Utils\Lock::lock(__CLASS__))
			return;
		
		echo "=========== ".date("Y-m-d H:i:s")." ===========\n";
		
		Captcha::setMode($args['anticaptcha'] ? 'anticaptcha' : 'cli');
		
		$sched_config = \Z\Config::get('scheduler');
		$api = new VkApi(\Smm\Oauth::getAccessToken('VK_SCHED'));
		
		while (true) {
			$need_to_post = $this->getCommentsQueue();
			
			$group_ids = [];
			foreach ($need_to_post as $queue) {
				if (time() - $queue['real_date'] >= 0)
					$group_ids[] = $queue['group_id'];
			}
			
			$walls = [];
			foreach (array_chunk($group_ids, 25) as $chunk) {
				$code = [];
				echo "=> fetch wall for ".implode(", ", $chunk)."...\n";
				foreach ($chunk as $group_id) {
					$code[] = '
						"'.$group_id.'": API.wall.get({
							owner_id:	-'.$group_id.', 
							filter:		"all", 
							extended:	0, 
							count:		10, 
							offset:		0
						})
					';
				}
				
				for ($i = 0; $i < 3; ++$i) {
					$api_data = ['code' => "return {".implode(", ", $code)."};"];
					
					if (($captcha_code = Captcha::getCode())) {
						$api_data['captcha_key'] = $captcha_code['key'];
						$api_data['captcha_sid'] = $captcha_code['sid'];
					}
					
					$res = $api->exec("execute", $api_data);
					
					if ($res->success()) {
						echo "\t=> OK\n";
						
						foreach ($res->response as $k => $v)
							$walls[$k] = $v;
						break;
					} else {
						Captcha::set($res->captcha());
						echo "\t=> ERROR: ".$res->error()."\n";
					}
					
					usleep(100000);
				}
			}
			
			$posts = [];
			
			foreach ($walls as $wall) {
				if (!$wall)
					continue;
				
				foreach ($wall->items as $item) {
					foreach ($need_to_post as $queue) {
						$delta = $queue['real_date'] - $item->date;
						if (abs($queue['real_date'] - $item->date) <= 60) {
							echo "postpone wall".$item->owner_id."_".$queue['id']." => post wall".$item->owner_id."_".$item->id." with delta: ".$delta." s.\n";
							
							// Try add wall post
							for ($i = 0; $i < $sched_config['max_api_fails']; ++$i) {
								$api_data = [
									'post_id'		=> $item->id, 
									'owner_id'		=> $item->owner_id, 
									'from_group'	=> -$item->owner_id, 
									'message'		=> $queue['text'], 
									'guid'			=> md5($item->owner_id."_".$item->id."_".$queue['text'])
								];
								
								if (($captcha_code = Captcha::getCode())) {
									$api_data['captcha_key'] = $captcha_code['key'];
									$api_data['captcha_sid'] = $captcha_code['sid'];
								}
								
								$comment_api = $api;
								$group_access_token = \Smm\Oauth::getGroupAccessToken(-$item->owner_id);
								if ($group_access_token)
									$comment_api = new \Z\Net\VkApi($group_access_token);
								
								$res = $comment_api->exec("wall.createComment", $api_data);
								if ($res->success()) {
									echo "\t=> #".$item->id." - OK, comment created\n";
									
									DB::delete('vk_posts_comments')
										->where('id', '=', $queue['id'])
										->where('group_id', '=', $queue['group_id'])
										->execute();
									
									break;
								}
								
								Captcha::set($res->captcha());
								echo "\t=> #".$item->id." - ERROR: ".$res->error()."\n";
								
								sleep($i + 1);
							}
						}
					}
				}
			}
			
			$min_date = false;
			$need_to_post = $this->getCommentsQueue();
			foreach ($need_to_post as $queue) {
				if ($min_date == false || $min_date > $queue['real_date'])
					$min_date = $queue['real_date'];
			}
			
			$delta = $min_date - time();
			if ($min_date !== false && $delta < 120) {
				echo "Has items in queue... wait... [delta=$delta, min_date=$min_date]\n";
				sleep(max(3, $delta));
 			} else {
				echo "Done! No items in queue.\n";
				break;
			}
		}
	}
}
