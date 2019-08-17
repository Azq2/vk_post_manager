<?php
namespace Smm\Task;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;
use \Z\Net\VkApi;
use \Z\Net\Anticaptcha;

use \Smm\VK\Captcha;

class GroupActivityAggregator extends \Z\Task {
	public function run($args) {
		if (!\Smm\Utils\Lock::lock(__CLASS__)) {
			echo "Already running.\n";
			return;
		}
		
		echo date("Y-m-d H:i:s")." - start\n";
		$start = microtime(true);
		
		$cache = \Z\Cache::instance();
		
		$last_dirty_date = DB::select(['MIN(date)', 'date'])
			->from('vk_activity_posts')
			->orOpenGroup()
				->where('need_check_comments', '=', 1)
				->where('last_comments_check', '=', 0)
			->orCloseGroup()
			->orOpenGroup()
				->where('need_check_likes', '=', 1)
				->where('last_likes_check', '=', 0)
			->orCloseGroup()
			->execute()
			->get('date', NULL);
		
		$has_dirty_stat = ($last_dirty_date && strtotime(date("Y-m-d 00:00:00")) > strtotime("$last_dirty_date 00:00:00"));
		if ($has_dirty_stat)
			$cache->set("group_activity_check_time", 0);
		
		$last_full_check_time = $cache->get("group_activity_check_time") ?: 0;
		if (time() - $last_full_check_time >= 3600 * 24) {
			$dates = DB::select(['MIN(dt)', 'min'], ['MAX(dt)', 'max'])
				->from('vk_activity_likes')
				->execute()
				->current();
			
			if (!$has_dirty_stat)
				$cache->set("group_activity_check_time", time());
		} else {
			$dates = [
				'min'		=> date("Y-m-00", time() - 3600 * 24 * 32), 
				'max'		=> date("Y-m-t", time())
			];
		}
		
		if ($dates['min'] && $dates['max']) {
			$cursor = strtotime($dates['min']." 00:00:00");
			$end = strtotime($dates['max']." 00:00:00");
			
			$insert_likes = DB::insert('vk_activity_stat', ['date', 'owner_id', 'user_id', 'likes'])
				->onDuplicateSetValues('likes');
			
			$insert_comments = DB::insert('vk_activity_stat', ['date', 'owner_id', 'user_id', 'comments', 'comments_meaningful'])
				->onDuplicateSetValues('comments')
				->onDuplicateSetValues('comments_meaningful');
			
			while ($cursor <= $end) {
				echo date("Y-m-d", $cursor)." - ".date("Y-m-t", $cursor)."\n";
				
				$likers = DB::select('dt', 'owner_id', 'user_id', ['COUNT(*)', 'cnt'])
					->from('vk_activity_likes')
					->where('dt', 'BETWEEN', [date("Y-m-d", $cursor), date("Y-m-t", $cursor)])
					->group('dt')
					->group('owner_id')
					->group('user_id');
				
				foreach ($likers->execute() as $row) {
					$insert_likes->values([$row['dt'], $row['owner_id'], $row['user_id'], $row['cnt']]);
					if ($insert_likes->countValues() >= 10000) {
						$insert_likes->execute();
						$insert_likes->setValues([]);
					}
				}
				
				$commentators = DB::select(
					'dt', 'owner_id', 'user_id', ['COUNT(*)', 'comments'], 
					['SUM(IF(text_length > 2 OR images_cnt > 0, 1, 0))', 'comments_meaningful']
				)
					->from('vk_activity_comments')
					->where('dt', 'BETWEEN', [date("Y-m-d", $cursor), date("Y-m-t", $cursor)])
					->group('dt')
					->group('owner_id')
					->group('user_id');
				
				
				
				foreach ($commentators->execute() as $row) {
					$insert_comments->values([$row['dt'], $row['owner_id'], $row['user_id'], $row['comments'], $row['comments_meaningful']]);
					if ($insert_comments->countValues() >= 10000) {
						$insert_comments->execute();
						$insert_comments->setValues([]);
					}
				}
				
				$cursor = strtotime(date("Y-m-t 23:59:59", $cursor)) + 1;
			}
			
			if ($insert_likes->countValues() > 0)
				$insert_likes->execute();
			
			if ($insert_comments->countValues() > 0)
				$insert_comments->execute();
		}
		
		$elapsed = microtime(true) - $start;
		echo date("Y-m-d H:i:s")." - done, ".round($elapsed, 2)." s.\n";
	}
}
