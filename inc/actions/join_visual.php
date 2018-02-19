<?php
$stat = [];

$type = array_val($_GET, 'type', 'diff');
$period = array_val($_GET, 'period', '1year');
$output = array_val($_GET, 'output', 'month');

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
	$offsets = array(6, 0, 1, 2, 3, 4, 5);
	$date_start = mktime(0, 0, 0, $time[4] + 1, $time[3] - $offsets[$time[6]], 1900 + $time[5]);
} elseif (preg_match("/(\d+)day/", $period, $m)) {
	$date_start = time() - 3600 * 24 * $m[1];
} elseif (preg_match("/(\d+)year/", $period, $m)) {
	$date_start = time() - 3600 * 24 * 365 * $m[1];
}

$where = "";
if ($date_start)
	$where .= " AND `time` >= $date_start";
if ($date_end)
	$where .= " AND `time` <= $date_end";

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

$req = Mysql::query("SELECT * FROM `vk_join_stat` WHERE `cid` = $gid $where ORDER BY `id` DESC");
while ($res = $req->fetch()) {
	$date = false;
	if ($output == 'day')
		$key = date("Y-m-d", $res['time']);
	elseif ($output == 'hour') {
		$key = date("Y-m-d H:00", $res['time']);
		$date = date("H:00", $res['time']);
	} elseif ($output == 'month')
		$key = date("Y-m", $res['time']);
	elseif ($output == 'year')
		$key = date("Y", $res['time']);
	elseif ($output == 'week') {
		$key = date("Y-W", $res['time']);
		$date = date("Y-m-d", $res['time']);
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
	
	$res['type'] ? ++$stat[$key]['join'] : ++$stat[$key]['leave'];
	$res['type'] ? ++$total_join : ++$total_leave;
	
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

$graphs = [];
foreach ($fields as $k => $g) {
	$graphs[] = [
		'title'					=> $g['title'], 
		'lineColor'				=> $g['color'][0], 
		'negativeLineColor'		=> $g['color'][1], 
		'lineThickness'			=> 1.5, 
		'bulletSize'			=> 5, 
		'valueField'			=> $k
	];
}

mk_page(array(
	'title' => 'Пользователи', 
	'content' => Tpl::render("join_visual.html", [
		'stat'			=> array_reverse(array_values($stat)), 
		'graphs'		=> $graphs, 
		'total_join'	=> $total_join, 
		'total_leave'	=> $total_leave, 
		
		// Тип
		'type_tabs'	=> switch_tabs([
			'url' => Url::mk()->href(), 
			'param' => 'type', 
			'tabs' => [
				'joins'	=> 'Вступления', 
				'diff'	=> 'Разница'
			], 
			'active' => $type
		]), 
		
		// Вывод
		'output_tabs'	=> switch_tabs([
			'url' => Url::mk()->href(), 
			'param' => 'output', 
			'tabs' => $output_list, 
			'active' => $output
		]), 
		
		// Период
		'period_tabs'	=> switch_tabs([
			'url' => Url::mk()->href(), 
			'param' => 'period', 
			'tabs' => [
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
			'active' => $period
		]), 
	])
));
