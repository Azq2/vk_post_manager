<?php
namespace Smm\Task;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;
use \Z\Net\VkApi;
use \Z\Net\Anticaptcha;

use \Smm\VK\Captcha;

class AggregationStat extends \Z\Task {
	public function options() {
		return [
			'full' 			=> 0
		];
	}
	
	public function run($args) {
		if (!\Smm\Utils\Lock::lock(__CLASS__)) {
			echo "Already running.\n";
			return;
		}
		
		foreach (DB::select()->from('vk_groups')->where('deleted', '=', 0)->execute() as $group) {
			echo date("Y-m-d H:i:s")." - #".$group['id']." [ GROUP: ".$group['name']." ]\n";
			
			if (!\Smm\Globals::get($group['id'], "activity_stat_done") && $group['id'] != 94594114) {
				echo "SKIP - stat not complete!\n";
				continue;
			}
			
			$last_time = 0;
			
			if (!$args['full']) {
				$last_time = DB::select(['UNIX_TIMESTAMP(MAX(date))', 'last_time'])
					->from('vk_users_stat')
					->where('group_id', '=', $group['id'])
					->execute()
					->get('last_time', 0);
			}
			
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
		}
	}
}
