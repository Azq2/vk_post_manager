<?php

function get_best_fake_date($q, $gid, $offset = 0) {
		$postponed = $q->vkApi("wall.get", [
			'owner_id'		=> -$gid, 
			'filter'		=> 'postponed', 
			'offset'		=> $offset, 
			'count'			=> 100
		]);
		if (!$offset && $postponed->response->count > 100)
			return get_best_fake_date($q, $gid, $postponed->response->count - 1);
		
		$generic_date = time() + 3600 * 24 * 60;
		if (!$postponed->response->count)
			return $generic_date;
		
		return max($generic_date, $postponed->response->items[count($postponed->response->items) - 1]->date + 3600);
}

function get_posts_queue($gid) {
	$queue = [];
	
	$i = 0;
	
	$req = Mysql::query("SELECT * FROM `vk_posts_queue` WHERE `group_id` = ".(int) $gid." ORDER BY `nid` ASC");
	while ($res = $req->fetch()) {
		$res['n'] = $i++;
		$queue[$res['id']] = $res;
	}
	
	return $queue;
}

function get_comments($q, $comm) {
	$gid = $comm['id'];
	
	$code = '
		var api_cnt = 0, 
			gid = -'.$gid.';
		
		var last_comment = API.wall.get({
			owner_id: gid, 
			filter: "all", 
			count: 2
		});
		
		api_cnt = api_cnt + 1;
		
		var empty_postponed = 0;
		var empty_suggests = 0;
		var total_postponed = 0;
		var total_suggests = 0;
		var postponed_arr = [];
		var suggests_arr = [];
		var while_cond = true;
		
		while (while_cond) {
			var cond = 0;
			
			if (!empty_postponed && (!total_postponed || postponed_arr.length * 100 < total_postponed)) {
				var postponed = API.wall.get({
					owner_id: gid, 
					filter: "postponed", 
					extended: true, 
					count: 100
				});
				total_postponed = postponed.count;
				postponed_arr.push(postponed);
				
				api_cnt = api_cnt + 1;
				cond = cond + 1;
				
				if (!total_postponed)
					empty_postponed = true;
			}
			
			if (!empty_suggests && (!total_suggests || suggests_arr.length * 100 < total_suggests)) {
				var suggests = API.wall.get({
					owner_id: gid, 
					filter: "suggests", 
					extended: true, 
					count: 100
				});
				total_suggests = suggests.count;
				suggests_arr.push(suggests);
				
				api_cnt = api_cnt + 1;
				cond = cond + 1;
				
				if (!total_suggests)
					empty_suggests = true;
			}
			
			if (api_cnt < 2 || !cond) {
				// Упёрлись в лимит или закончили
				while_cond = false;
			}
		}
		
		var arr = [postponed_arr, suggests_arr];
		
		var ids = [], i = 0, j = 0, k = 0;
		while (i < arr.length) {
			while (j < arr[i].length) {
				while (k < arr[i][j].items.length) {
					if (arr[i][j].items[k].created_by)
						ids.push(arr[i][j].items[k].created_by);
					if (arr[i][j].items[k].from_id)
						ids.push(arr[i][j].items[k].from_id);
					k = k + 1;
				}
				j = j + 1;
			}
			i = i + 1;
		}
		
		i = 0;
		while (i < last_comment.items.length) {
			if (last_comment.items[i].created_by)
				ids.push(last_comment.items[i].created_by);
			if (last_comment.items[i].from_id)
				ids.push(last_comment.items[i].from_id);
			i = i + 1;
		}
		
		return {
			postponed:	postponed_arr, 
			suggests:	suggests_arr, 
			profiles:	ids.length ? API.users.get({user_ids: ids, fields: "photo_50"}) : {items: []}, 
			last:		last_comment
		};
	';
	for ($i = 0; $i < 10; ++$i) {
		$out = $q->vkApi("execute", array('code' => $code));
		if (isset($out->response))
			break;
		sleep(1);
	}
	
	if (($error = vk_api_error($out)))
		die("!!!!!!!!!!!!!! get_comments: $error\n");
	
	$users = [];
	$items = [];
	foreach ([$out->response->postponed, $out->response->suggests] as $list) {
		foreach ($list as $chunk) {
			if (isset($chunk->items)) {
				foreach ($chunk->items as $item)
					$items[] = $item;
			}
			
			if (isset($chunk->profiles)) {
				foreach ($chunk->profiles as $u)
					$users[$u->id] = $u;
			}
			
			if (isset($chunk->groups)) {
				foreach ($chunk->groups as $u)
					$users[-$u->id] = $u;
			}
		}
	}
	
	foreach ($out->response->profiles as $u)
		$users[$u->id] = $u;
	
	$last_post = NULL;
	if (count($out->response->last->items)) {
		$last_post = $out->response->last->items[0];
		if (count($out->response->last->items) > 1 && $out->response->last->items[1]->date > $out->response->last->items[0]->date)
			$last_post = $out->response->last->items[1];
	}
	
	if ($last_post)
		$items[] = $last_post;
	
	$postponed = [];
	$suggests = [];
	$specials = [];
	
	$max_date = 0;
	
	$queue = get_posts_queue($gid);
	foreach ($items as $post) {
		$post->special = false;
		if (isset($post->marked_as_ads) && $post->marked_as_ads) {
			$post->special = true;
			$specials[] = $post;
		}
		
		if (isset($queue[$post->id]) || $post->special || $post->post_type == 'post') {
			// Отложенный
			if ($post->post_type != 'post' && !$post->special) {
				$post->orig_date = $post->date;
				$post->date = time() + (100 * 24 * 3600) + (24 * 3600 * $queue[$post->id]['n']); // Костыли для сортировки
			}
			$postponed[$post->id] = $post;
		} else {
			// Предложка
			$suggests[$post->id] = $post;
		}
		
		$max_date = max($max_date, $post->date);
	}
	
	$postponed['__NEXT__'] = (object) array(
		'date'			=> $max_date + 10, 
		'special'		=> false, 
		'post_type'		=> 'postpone', 
		'id'			=> '__NEXT__'
	);
	
	$postponed = array_values($postponed);
	$suggests = array_values($suggests);
	
	$postponed = process_queue($comm, $postponed);
	
	$next_post_dummy = array_pop($postponed);
	
	\Z\Smm\Globals::set($gid, "next_post_date", $next_post_dummy->date);
	
	usort($suggests, function ($a, $b) {
		if ($a->date == $b->date)
			return 0;
		return $a->date > $b->date ? 1 : -1;
	});
	
	usort($specials, function ($a, $b) {
		if ($a->date == $b->date)
			return 0;
		return $a->date > $b->date ? 1 : -1;
	});
	
	return (object) [
		'postponed'		=> $postponed, 
		'postponed_cnt'	=> $last_post ? count($postponed) - 1 : count($postponed), 
		'suggests'		=> $suggests, 
		'suggests_cnt'	=> count($suggests), 
		'specials'		=> $specials, 
		'last'			=> $last_post, 
		'users'			=> $users
	];
}

function fix_post_date($post_time, $comm) {
	$day_start = get_day_start($post_time);
	
	// Указан дополнительный интервал
	$fix_after = 0;
	if ($comm['period_to'] < $comm['period_from']) {
		$fix_after = $comm['period_to'];
		$comm['period_to'] = 3600 * 24;
	}
	
	if (24 * 3600 - ($comm['period_to'] - $comm['period_from']) > 60) { // Есть фиксированный период постинга
		if ($post_time - ($day_start + $comm['period_to']) >= 10) {
			// Если время превышает границу времени, то переносим на следующий день
			$post_time = $day_start + 24 * 3600 + $comm['period_from'];
		} elseif ($post_time - ($day_start + $comm['period_from']) <= -10) {
			if (!$fix_after || $post_time - ($day_start + $fix_after) > 10) { // Дополнительный интервал
				// Если время не попадает под минимальный период, то переносим его на начало периода текущего дня
				$post_time = $day_start + $comm['period_from'];
			}
		}
	}
	
	// Выравниваем по 10 минут
	if ($post_time && ($post_time % 60 != 0))
		$post_time = round($post_time / 60) * 60;
	
	return $post_time;
}

function process_queue($comm, $posts) {
	$SPECIAL_POST_AFTER_PAD = min(3600, $comm['interval']);
	$SPECIAL_POST_BEFORE_PAD = 3600;
	$SPECIAL_POST_FIX = 60;
	
	$gid = $comm['id'];
	
	$pass = 0;
	
	do {
		$fixes = 0;
		
//		echo "\nprocess_queue [$pass]:\n";
		
		// Сначала сортируем по ASC
		usort($posts, function ($a, $b) {
			if ($a->date == $b->date)
				return 0;
			return $a->date > $b->date ? 1 : -1;
		});
		
		$prev_date = 0;
		$ids = array_keys($posts);
		for ($i = 0, $l = count($ids); $i < $l; ++$i) {
			$cur = $posts[$ids[$i]];
			$prev = $i > 0 ? $posts[$ids[$i - 1]] : NULL;
			$next = $i < $l - 1 ? $posts[$ids[$i + 1]] : NULL;
			
			// Специальный пост с точной датой
			if ($cur->special || $cur->post_type == 'post') {
//				echo "#".$cur->id." - SKIP SPECIAL (".date("d/m/Y H:i", $cur->date).")\n";
				
				if ($pass > 0 || !$cur->special)
					$prev_date = $cur->date;
				
				continue;
			}
			
			$old_date = $cur->date;
			
			// Дата прошлого поста
			$cur->date = fix_post_date(max($prev_date + $comm['interval'], time()), $comm);
//			echo "#".$cur->id." set date ".date("d/m/Y H:i", $cur->date)."\n";
			
			$need_recalc = 0;
			if ($pass > 0) {
				// Предыдущий пост - специальный и до него меньше часа
				if ($prev && $prev->special && $cur->date - $prev->date < ($SPECIAL_POST_BEFORE_PAD - $SPECIAL_POST_FIX)) {
					// Увеличиваем промежуток до часа
					$cur->date = fix_post_date($cur->date + ($SPECIAL_POST_BEFORE_PAD - ($cur->date - $prev->date)), $comm);
					
//					echo "\t#".$cur->id." fix date ".date("d/m/Y H:i", $cur->date)." (diff=".($cur->date - $prev->date).") by prev SPECIAL\n";
				}
				
				// Следующий пост - специальный и до него меньше часа
				if ($next && $next->special && $next->date - $cur->date < ($SPECIAL_POST_AFTER_PAD - $SPECIAL_POST_FIX)) {
					for ($i = $i + 1; $i < $l; ++$i) {
						$next_topic = $posts[$ids[$i]];
						if ($next_topic->date - $cur->date < ($SPECIAL_POST_AFTER_PAD - $SPECIAL_POST_FIX)) {
							// Передвигаем этот пост ЗА топик + 1h
							$cur->date = fix_post_date($next_topic->date + $SPECIAL_POST_AFTER_PAD, $comm);
							
//							echo "\t#".$cur->id." fix date ".date("d/m/Y H:i", $cur->date)." by next SPECIAL\n";
							
							++$need_recalc;
							continue;
						}
						break;
					}
				}
			}
			
			$prev_date = $cur->date;
			
			if ($old_date != $cur->date)
				++$fixes;
			
			if ($need_recalc) {
				// Изменился порядок, нужно пересчитать время
				++$fixes;
				break;
			}
		}
		
		++$pass;
	} while ($fixes > 0 || $pass < 2);
	
	return $posts;
}

function get_post_json($post) {
	$attach_map = array(
		"photo"		=> "photo", 
		"video"		=> "video", 
		"doc"		=> "doc", 
		"audio"		=> "audio", 
		"poll"		=> "poll", 
		"album"		=> "album"
	);
	$out = array(
		'lat'			=> 0, 
		'long'			=> 0, 
		'attachments'	=> array(), 
		'post_id'		=> $post->id, 
		'owner_id'		=> $post->owner_id, 
		'post_type'		=> $post->post_type, 
		'publish_date'	=> isset($post->publish_date) ? $post->publish_date : 0, 
		'message'		=> $post->text, 
		'signed'		=> isset($post->signer_id) ? (int) ((bool) $post->signer_id) : 0
	);
	if (isset($post->attachments)) {
		foreach ($post->attachments as $att) {
			if (isset($attach_map[$att->type])) {
				$att_data = $att->{$att->type};
				$out['attachments'][] = $att->type.$att_data->owner_id."_".$att_data->id;
			} else if ($att->type == 'link') {
				$out['attachments'][] = $att->link->url;
			} else {
				die("Unknown attach error: <pre>".htmlspecialchars(print_r($att, 1)).'</pre>');
			}
		}
	}
	if (isset($post->geo)) {
		if (!preg_match("/^([\d\.-]+) ([\d\.-]+)$/", $post->geo->coordinates, $geo))
			die("Invalid geo: ".$post->geo->coordinates);
		$out['lat'] = $geo[1];
		$out['long'] = $geo[2];
	}
	return $out;
}						
