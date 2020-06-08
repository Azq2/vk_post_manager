<?php
namespace Smm\Controllers;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;

use \Smm\View\Widgets;

class StatisticController extends \Smm\GroupController {
	public function indexAction() {
		$this->title = 'Статистика';
		
		$base_url = Url::mk('/')->set('gid', $this->group['id']);
		
		$this->content = View::factory('statistic/index', [
			'join_visual_url'		=> $base_url->copy()->set('a', 'statistic/join_visual')->href(), 
			'join_list_url'			=> $base_url->copy()->set('a', 'statistic/join_list')->href(), 
			'activity_url'			=> $base_url->copy()->set('a', 'statistic/activity')->href(), 
		]);
	}
	
	public function activityAction() {
		$raw_stat = DB::select(
			['DATE(FROM_UNIXTIME(date))', 'date'], 
			['SUM(likes)', 'likes'], 
			['SUM(comments)', 'comments'], 
			['SUM(reposts)', 'reposts'], 
			['SUM(views)', 'views'], 
			['COUNT(*)', 'posts']
		)
			->from('vk_activity_posts')
			->where('owner_id', '=', -$this->group['id'])
			->where('views', '>', 0)
			->group(DB::expr('DATE(FROM_UNIXTIME(date))'))
			->order('date', 'ASC')
			->execute()
			->asArray();
		
		$users_cnt_stat = DB::select(['DATE(FROM_UNIXTIME(time))', 'date'], ['MAX(users_cnt)', 'users_cnt'])
			->from('vk_join_stat')
			->where('cid', '=', $this->group['id'])
			->group(DB::expr('DATE(FROM_UNIXTIME(time))'))
			->execute()
			->asArray('date', 'users_cnt');
		
		$stat = [];
		$users_cnt = 0;
		foreach ($raw_stat as $k => $row) {
			$users_cnt = $users_cnt_stat[$row['date']] ?? $users_cnt;
			$stat[] = [
				'date'			=> $row['date'], 
				'posts'			=> $row['posts'], 
				'er_post'		=> round($users_cnt ? ($row['likes'] + $row['reposts'] + $row['comments']) / $users_cnt / $row['posts'] : 0, 4), 
				'er_view'		=> round(($row['likes'] + $row['reposts'] + $row['comments']) / $row['views'], 4), 
				'er_day'		=> round($users_cnt ? ($row['likes'] + $row['reposts'] + $row['comments']) / $users_cnt : 0, 4), 
				'lr'			=> round($users_cnt ? $row['likes'] / $users_cnt / $row['posts'] : 0, 4), 
				'tl'			=> round($users_cnt ? $row['comments'] / $users_cnt / $row['posts'] : 0, 4), 
			];
		}
		
		$chart = new Widgets\Amcharts\Serial("activity", [
			'valueAxes'		=> [
				[
					'id'		=> 'er_post', 
					'position'	=> 'left'
				], 
				[
					'id'		=> 'er_view', 
					'position'	=> 'left'
				], 
				[
					'id'		=> 'er_day', 
					'position'	=> 'left'
				], 
				[
					'id'		=> 'lr', 
					'position'	=> 'left'
				], 
				[
					'id'		=> 'tl', 
					'position'	=> 'left'
				], 
				[
					'id'		=> 'posts', 
					'position'	=> 'left'
				]
			]
		]);
		$chart->addGraph('ERpost', 'er_post', [
			'valueAxis'		=> 'er_post'
		]);
		$chart->addGraph('ERview', 'er_view', [
			'valueAxis'		=> 'er_view'
		]);
		$chart->addGraph('ERday', 'er_day', [
			'valueAxis'		=> 'er_day'
		]);
		$chart->addGraph('Love Rate', 'lr', [
			'valueAxis'		=> 'lr'
		]);
		$chart->addGraph('Talk Rate', 'tl', [
			'valueAxis'		=> 'tl'
		]);
		$chart->addGraph('Posts', 'posts', [
			'valueAxis'		=> 'posts'
		]);
		
		$chart->data($stat);
		
		/*
		$raw_stat = DB::select(
			['DATE(FROM_UNIXTIME(date))', 'date'], 
			['ROUND(SUM(likes) / COUNT(*))', 'likes'], 
			['ROUND(SUM(comments) / COUNT(*))', 'comments'], 
			['ROUND(SUM(reposts) / COUNT(*))', 'reposts'], 
			['ROUND(SUM(views) / COUNT(*))', 'views'], 
			['ROUND(SUM(likes + reposts + comments) / views, 2)', 'erview'], 
			['COUNT(*)', 'posts']
		)
			->from('vk_activity_posts')
			->where('owner_id', '=', -$this->group['id'])
			->where('views', '>', 0)
			->group(DB::expr('DATE(FROM_UNIXTIME(date))'))
			->order('date', 'ASC')
			->execute()
			->asArray();
		
		$chart = new Widgets\Amcharts\Serial("activity", [
			'valueAxes'		=> [
				[
					'id'		=> 'likes_axis', 
					'position'	=> 'left'
				], 
				[
					'id'		=> 'comments_axis', 
					'position'	=> 'left'
				], 
				[
					'id'		=> 'reposts_axis', 
					'position'	=> 'left'
				], 
				[
					'id'		=> 'views_axis', 
					'position'	=> 'left'
				], 
				[
					'id'		=> 'posts_axis', 
					'position'	=> 'left'
				], 
				[
					'id'		=> 'erview_axis', 
					'position'	=> 'left'
				]
			]
		]);
		$chart->addGraph('Лайки', 'likes', [
			'valueAxis'		=> 'likes_axis'
		]);
		$chart->addGraph('Коменты', 'comments', [
			'valueAxis'		=> 'comments_axis'
		]);
		$chart->addGraph('Репосты', 'reposts', [
			'valueAxis'		=> 'reposts_axis'
		]);
		$chart->addGraph('Просмотры', 'views', [
			'valueAxis'		=> 'views_axis'
		]);
		$chart->addGraph('Посты', 'posts', [
			'valueAxis'		=> 'posts_axis'
		]);
		$chart->addGraph('ERview', 'erview', [
			'valueAxis'		=> 'erview_axis'
		]);
		
		$chart->data($stat);
		*/
		
		$this->title = 'Активность';
		$this->content = View::factory('statistic/activity', [
			'chart'			=> $chart->render()
		]);
	}
	
	public function join_listAction() {
		$stat = [];
		
		$period = $_GET['period'] ?? 'now';
		
		$date_start = 0;
		$date_end = 0;
		
		if ($period == 'now') {
			$time = localtime(time());
			$date_start = mktime(0, 0, 0, $time[4] + 1, $time[3], 1900 + $time[5]);
		} elseif ($period == 'yesterday') {
			$time = localtime(time());
			$date_start = mktime(0, 0, 0, $time[4] + 1, $time[3] - 1, 1900 + $time[5]);
			$date_end = mktime(0, 0, 0, $time[4] + 1, $time[3], 1900 + $time[5]);
		} elseif ($period == 'week') {
			$time = localtime(time());
			$offsets = [6, 0, 1, 2, 3, 4, 5];
			$date_start = mktime(0, 0, 0, $time[4] + 1, $time[3] - $offsets[$time[6]], 1900 + $time[5]);
		} elseif (preg_match("/(\d+)day/", $period, $m)) {
			$date_start = time() - 3600 * 24 * $m[1];
		} elseif (preg_match("/(\d+)year/", $period, $m)) {
			$date_start = time() - 3600 * 24 * 365 * $m[1];
		} else {
			$time = localtime(time());
			$date_start = mktime(0, 0, 0, $time[4] + 1, $time[3], 1900 + $time[5]);
		}
		
		$stat_query = DB::select()
			->from('vk_join_stat')
			->where('cid', '=', $this->group['id'])
			->order('id', 'DESC');
		
		if ($date_start)
			$stat_query->where('time', '>=', $date_start);
		if ($date_end)
			$stat_query->where('time', '<=', $date_end);
		
		$users = $stat_query->execute()->asArray();
		$users_ids = array_unique(array_column($users, 'uid'));
		
		$vk_users = [];
		$api = new \Smm\VK\API(\Smm\Oauth::getAccessToken('VK'));
		foreach (array_chunk($users_ids, 1000) as $users_ids_chunk) {
			$res = $api->exec("users.get", [
				"user_ids"		=> implode(",", $users_ids_chunk), 
				"fields"		=> "photo_50,screen_name"
			]);
			
			foreach ($res->response as $u)
				$vk_users[$u->id] = $u;
		}
		
		$users_last_join = [];
		if ($users_ids) {
			$users_last_join = DB::select('uid', ['MAX(time)', 'last_join_time'], ['COUNT(*)', 'joins_cnt'])
				->from('vk_join_stat')
				->where('cid', '=', $this->group['id'])
				->where('uid', 'IN', $users_ids)
				->where('type', '=', 1)
				->group('uid')
				->execute()
				->asArray('uid');
		}
		
		$users_activity = [];
		if ($users_ids) {
			$users_activity = DB::select('user_id', ['SUM(likes)', 'likes'], ['SUM(comments)', 'comments'])
				->from('vk_activity_stat')
				->where('owner_id', '=', -$this->group['id'])
				->where('user_id', 'IN', $users_ids)
				->group('user_id')
				->execute()
				->asArray('user_id');
		}
		
		$total_join = 0;
		$total_leave = 0;
		
		$last_date = false;
		
		$users_list = [];
		foreach ($users as $row) {
			$id = $row['uid'];
			
			$vk_user = $vk_users[$id] ?? (object) [
				'photo_50'			=> false, 
				'first_name'		=> "Пользователь", 
				'last_name'			=> "id$id", 
				'screen_name'		=> ""
			];
			
			$date = \Z\Date::display($row['time'], true, false);
			
			$header = false;
			if ($last_date != $date)
				$header = $date;
			
			$last_join = false;
			if (isset($users_last_join[$id])) {
				$last_join = [
					'time_in_group'		=> \Z\Date::countTime($row['time'] - $users_last_join[$id]['last_join_time']), 
					'joins_cnt'			=> $users_last_join[$id]['joins_cnt']
				];
			}
			
			$users_list[] = [
				'header'			=> $header, 
				'date'				=> $date, 
				'time'				=> date("H:i", $row['time']), 
				'avatar'			=> $vk_user->photo_50, 
				'name'				=> $vk_user->first_name.' '.$vk_user->last_name, 
				'url'				=> "https://vk.com/".(isset($vk_user->screen_name) && $vk_user->screen_name ? $vk_user->screen_name : "id$id"), 
				'type'				=> $row['type'], 
				'last_join'			=> $last_join, 
				'likes'				=> $users_activity[$row['uid']]['likes'] ?? 0, 
				'comments'			=> $users_activity[$row['uid']]['comments'] ?? 0, 
			];
			
			$row['type'] ? ++$total_join : ++$total_leave;
			
			$last_date = $date;
		}
		
		$period_tabs = new Widgets\Tabs([
			'url'		=> Url::current(), 
			'param'		=> 'period', 
			'items'		=> [
				'now'		=> 'Сегодня', 
				'yesterday'	=> 'Вчера', 
				'week'		=> 'Эта неделя', 
				'7day'		=> '7 дней', 
				'30day'		=> '30 дней', 
			], 
			'active'	=> $period
		]);
		
		$this->title = 'Вступления (лог)';
		$this->content = View::factory('statistic/join_list', [
			'total_join'	=> $total_join, 
			'total_leave'	=> $total_leave, 
			'period_tabs'	=> $period_tabs->render(), 
			'users_list'	=> $users_list
		]);
	}
	
	public function join_visualAction() {
		$stat = [];
		
		$type = $_GET['type'] ?? 'diff';
		$period = $_GET['period'] ?? '1year';
		$output = $_GET['output'] ?? 'month';
		
		$date_start = 0;
		$date_end = 0;
		
		if ($period == 'now') {
			$time = localtime(time());
			$date_start = mktime(0, 0, 0, $time[4] + 1, $time[3], 1900 + $time[5]);
		} elseif ($period == 'yesterday') {
			$time = localtime(time());
			$date_start = mktime(0, 0, 0, $time[4] + 1, $time[3] - 1, 1900 + $time[5]);
			$date_end = mktime(0, 0, 0, $time[4] + 1, $time[3], 1900 + $time[5]);
		} elseif ($period == 'week') {
			$time = localtime(time());
			$offsets = [6, 0, 1, 2, 3, 4, 5];
			$date_start = mktime(0, 0, 0, $time[4] + 1, $time[3] - $offsets[$time[6]], 1900 + $time[5]);
		} elseif (preg_match("/(\d+)day/", $period, $m)) {
			$date_start = time() - 3600 * 24 * $m[1];
		} elseif (preg_match("/(\d+)year/", $period, $m)) {
			$date_start = time() - 3600 * 24 * 365 * $m[1];
		}
		
		$stat_query = DB::select()
			->from('vk_join_stat')
			->where('cid', '=', $this->group['id'])
			->order('id', 'DESC');
		
		if ($date_start)
			$stat_query->where('time', '>=', $date_start);
		if ($date_end)
			$stat_query->where('time', '<=', $date_end);
		
		$output_list = [
			'hour'	=> 'по часам', 
			'day'	=> 'по дням', 
			'week'	=> 'по неделям', 
			'month'	=> 'по месяцам', 
		];

		$show_output = array_keys($output_list);
		if ($date_start) {
			$show_output = [];
			
			$ndays = (($date_end ? $date_end : time()) - $date_start) / (24 * 3600);
			
			if ($ndays > 1)
				unset($output_list['hour']);
			
			if ($ndays < 2)
				unset($output_list['day']);
			
			if ($ndays < 8)
				unset($output_list['week']);
			
			if ($ndays < 30)
				unset($output_list['month']);
		} else {
			unset($output_list['hour']);
		}

		if (!isset($output_list[$output])) {
			$tmp = array_keys($output_list);
			$output = reset($tmp);
		}

		$total_join = 0;
		$total_leave = 0;

		foreach ($stat_query->execute() as $row) {
			$date = false;
			if ($output == 'day')
				$key = date("Y-m-d", $row['time']);
			elseif ($output == 'hour') {
				$key = date("Y-m-d H:00", $row['time']);
				$date = date("H:00", $row['time']);
			} elseif ($output == 'month')
				$key = date("Y-m", $row['time']);
			elseif ($output == 'year')
				$key = date("Y", $row['time']);
			elseif ($output == 'week') {
				$key = date("Y-W", $row['time']);
				$date = date("Y-m-d", $row['time']);
			}
			
			if (!$date)
				$date = $key;
			
			if (!isset($stat[$key])) {
				$stat[$key] = [
					'date'		=> $date, 
					'join'		=> 0, 
					'leave'		=> 0, 
					'diff'		=> 0
				];
			}
			
			$row['type'] ? ++$stat[$key]['join'] : ++$stat[$key]['leave'];
			$row['type'] ? ++$total_join : ++$total_leave;
			
			$stat[$key]['diff'] = $stat[$key]['join'] - $stat[$key]['leave'];
		}

		if ($type == 'joins') {
			$fields = [
				'join'		=> ['title' => 'Вступили', 'color' => ['#00ff00', '#00ff00']], 
				'leave'		=> ['title' => 'Покинули', 'color' => ['#ff0000', '#ff0000']], 
			];
		} else if ($type == 'diff') {
			$fields = [
				'diff'		=> ['title' => 'Разница', 'color' => ['#00ff00', '#ff0000']], 
			];
		}
		
		$chart = new Widgets\Amcharts\Serial("joins");
		foreach ($fields as $k => $g) {
			$chart->addGraph($g['title'], $k, [
				'lineColor'				=> $g['color'][0], 
				'negativeLineColor'		=> $g['color'][1], 
			]);
		}
		
		$type_tabs = new Widgets\Tabs([
			'url'		=> Url::current(), 
			'param'		=> 'type', 
			'items'		=> [
				'joins'	=> 'Вступления', 
				'diff'	=> 'Разница'
			], 
			'active'	=> $type
		]);
		
		$output_tabs = new Widgets\Tabs([
			'url'		=> Url::current(), 
			'param'		=> 'output', 
			'items'		=> $output_list, 
			'active'	=> $output
		]);
		
		$period_tabs = new Widgets\Tabs([
			'url'		=> Url::current(), 
			'param'		=> 'period', 
			'items'		=> [
				'now'		=> 'Сегодня', 
				'yesterday'	=> 'Вчера', 
				'week'		=> 'Эта неделя', 
				'7day'		=> '7 дней', 
				'30day'		=> '30 дней', 
				'90day'		=> '90 дней', 
				'180day'	=> '180 дней', 
				'1year'		=> '1 год', 
				'all'		=> 'Всё время', 
			], 
			'active'	=> $period
		]);
		
		$total_cnt = DB::select(['COUNT(*)', 'cnt'])
			->from('vk_comm_users')
			->where('cid', '=', $this->group['id'])
			->execute()
			->get('cnt', 0);
		
		$banned_cnt = DB::select(['COUNT(*)', 'cnt'])
			->from('vk_comm_users')
			->where('deactivated', '=', 1)
			->where('cid', '=', $this->group['id'])
			->execute()
			->get('cnt', 0);
		
		$deleted_cnt = DB::select(['COUNT(*)', 'cnt'])
			->from('vk_comm_users')
			->where('deactivated', '=', 3)
			->where('cid', '=', $this->group['id'])
			->execute()
			->get('cnt', 0);
		
		$inactive_6m_cnt = DB::select(['COUNT(*)', 'cnt'])
			->from('vk_comm_users')
			->where('last_seen', '<=', date("Y-m-d H:i:s", time() - 3600 * 24 * 30 * 3))
			->where('last_seen', '>', "2000-01-01 00:00:00")
			->where('deactivated', '=', 0)
			->where('cid', '=', $this->group['id'])
			->execute()
			->get('cnt', 0);
		
		$chart->data(array_reverse(array_values($stat)));
		
		$this->title = 'Вступления (график)';
		$this->content = View::factory('statistic/join_visual', [
			'chart'			=> $chart->render(), 
			'total_join'	=> $total_join, 
			'total_leave'	=> $total_leave, 
			
			'total_cnt'				=> $total_cnt, 
			'banned_cnt'			=> $banned_cnt, 
			'deleted_cnt'			=> $deleted_cnt, 
			'inactive_6m_cnt'		=> $inactive_6m_cnt, 
			
			'type_tabs'		=> $type_tabs->render(), 
			'output_tabs'	=> $output_tabs->render(), 
			'period_tabs'	=> $period_tabs->render(), 
		]);
	}
}
