<?php

$filter = isset($_REQUEST['filter']) ? preg_replace("/[^a-z0-9_-]+/i", "", $_REQUEST['filter']) : 'new';

$res = get_comments($q, $comm);

$url = (new Url("?"))->set([
	'a'		=> 'suggested', 
	'gid'	=> $gid
]);

$filter2list = [
	'accepted'	=> 'postponed', 
	'new'		=> 'suggests', 
	'special'	=> 'specials'
];

if (!isset($filter2list[$filter]))
	die("unknown filter?");

$list = $filter2list[$filter];

$last_date = "";

$json = array();
$comments = array();
$last_time = 0;

$last_posted = 0;
$last_postponed = 0;

$by_week_max = 0;
$by_week = [];

foreach ($res->{$list} as $item) {
	$from_id = (isset($item->created_by) && $item->created_by ? $item->created_by : (isset($item->from_id) ? $item->from_id : $item->owner_id));
	
	$date = display_date($item->date, false, false);
	
	if (!isset($by_week[date("Y-W-N", $item->date)]))
		$by_week[date("Y-W-N", $item->date)] = ['n' => date("N", $item->date), 'date' => $date, 'cnt' => 0];
	++$by_week[date("Y-W-N", $item->date)]['cnt'];
	
	$by_week_max = max($by_week_max, $by_week[date("Y-W-N", $item->date)]['cnt']);
	
	if ($date != $last_date)
		$last_time = 0;
	
	if ($item->post_type == 'post')
		$last_posted = max($item->date, $last_posted);
	else if (!$item->special)
		$last_postponed = max($item->date, $last_postponed);
	/*
	$comments[] = Tpl::render("widgets/comment.html", array(
		'date'			=> display_date($item->date), 
		'text'			=> nl2br(links(check_spell(htmlspecialchars($item->text, ENT_QUOTES)))), 
		'id'			=> $item->id, 
		'gid'			=> abs($item->owner_id), 
		'user'			=> vk_user_widget($res->users[$from_id]), 
		'deleted'		=> false, 
		'post_type'		=> $item->post_type, 
		'list'			=> $list, 
		'geo'			=> isset($item->geo) ? $item->geo : null, 
		'attachments'	=> isset($item->attachments) ? $item->attachments : null, 
		'special'		=> $item->special ? 1 : 0, 
		'period'		=> $date != $last_date ? $date : false, 
		'delta'			=> $last_time ? "+".count_delta($item->date - $last_time)." " : 0, 
		'scheduled'		=> isset($item->orig_date) && abs($item->date - $item->orig_date) <= 60
	));
	*/
	
	$user = $res->users[$from_id];
	$attaches_info = vk_normalize_attaches($item);
	
	$comments[] = [
		'id'			=> $item->id, 
		'owner'			=> $item->owner_id, 
		'text'			=> $item->text, 
		'attaches'		=> $attaches_info->attaches, 
		'source_id'		=> $item->owner_id, 
		'source_type'	=> 'VK', 
		'remote_id'		=> $item->owner_id.'_'.$item->id, 
		'time'			=> $item->date, 
		'type'			=> $item->post_type, 
		'likes'			=> 0, 
		'reposts'		=> 0, 
		'comments'		=> 0, 
		'anon'			=> true, 
		'owner_name'	=> isset($user->name) ? $user->name : $user->first_name." ".$user->last_name, 
		'owner_avatar'	=> $user->photo_50, 
		'owner_url'		=> "/".(isset($user->screen_name) && $user->screen_name ? $user->screen_name : 'id'.$user->id), 
		'images_cnt'	=> $attaches_info->images, 
		'gifs_cnt'		=> $attaches_info->gifs, 
		
		// Параметры очереди
		'list'			=> $list, 
		'special'		=> $item->special, 
		'period'		=> $date != $last_date ? $date : false, 
		'delta'			=> $last_time ? $item->date - $last_time : 0, 
		'scheduled'		=> $item->post_type != 'post' && isset($item->orig_date) && abs($item->date - $item->orig_date) <= 60
	];
	
	$last_time = $item->date;
	$last_date = $date;
}

mk_page(array(
	'title' => 'Предложки', 
	'content' => Tpl::render("suggested.html", array(
		'by_week'	=> [
			'items'	=> array_values($by_week), 
			'max'	=> $by_week_max
		], 
		'list'		=> $list, 
		'gid'		=> $gid, 
		'tabs'		=> switch_tabs([
			'url' => $url, 
			'param' => 'filter', 
			'tabs' => [
				'new'		=> 'Новые ('.count($res->suggests).')', 
				'accepted'	=> 'Принятые ('.$res->postponed_cnt.')', 
				'special'	=> 'Рекламные ('.count($res->specials).')', 
			], 
			'active' => $filter
		]), 
		
		'last_post_time'				=> $last_posted ? display_date($last_posted) : 'n/a', 
		'last_delayed_post_time'		=> $last_postponed ? display_date($last_postponed) : 'n/a', 
		
		'last_post_time_unix'			=> $last_posted, 
		'last_delayed_post_time_unix'	=> $last_postponed, 
		
		'postponed_link'		=> (string) (new Url("?"))->set(array(
			'gid'		=> $gid, 
			'a'			=> 'suggested', 
			'filter'	=> 'accepted'
		)), 
		'back'					=> $_SERVER['REQUEST_URI'], 
		
		'comments'				=> $comments, 
		'filter'				=> $filter, 
		'from'					=> parse_time($comm['period_from']), 
		'to'					=> parse_time($comm['period_to']), 
		'interval'				=> parse_time($comm['interval']), 
		'success'				=> isset($_REQUEST['ok']), 
		'postponed_cnt'			=> $res->postponed_cnt, 
		'suggests_cnt'			=> $res->suggests_cnt
	))
));