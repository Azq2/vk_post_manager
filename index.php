<?php
	require "inc/init.php";
	
	// Авторизация
	if (!isset($_COOKIE['password']) || $_COOKIE['password'] !== WEBUI_PASSWORD) {
		if (!isset($_SERVER['PHP_AUTH_USER']) || $_SERVER['PHP_AUTH_PW'] !== WEBUI_PASSWORD) {
			header('WWW-Authenticate: Basic realm="Home porn archive"');
			header('HTTP/1.0 401 Unauthorized');
			echo 'AYKA LOH.';
			exit;
		}
	}
	setcookie('password', WEBUI_PASSWORD, time() + 365 * 24 * 3600 * 2);
	
	
	$q = new Http;
	$action = isset($_REQUEST['a']) ? preg_replace("/[^a-z0-9_-]+/i", "", $_REQUEST['a']) : '';
	
	$curl = new Url($_SERVER['REQUEST_URI']);
	
	$comm_tabs = array();
	$comms = array();
	$req = mysql_query("SELECT * FROM `vk_groups` ORDER BY pos ASC");
	while ($res = mysql_fetch_assoc($req)) {
		$comms[$res['id']] = $res;
		$comm_tabs[$res['id']] = array($res['name'], $curl->set('gid', $res['id'])->url());
	}
	
	$gid = (int) array_val($_REQUEST, 'gid', reset($comms)['id']);
	if (!isset($comms[$gid])) {
		header("Location: ?");
		exit;
	}
	$comm = &$comms[$gid];
	
	switch ($action) {
		case "returns":
			$req = mysql_query("SELECT type, uid, COUNT(IF (type = 0, 1, NULL)) as cnt_leave, COUNT(IF (type = 1, 1, NULL)) as cnt_join
					FROM `vk_join_stat` WHERE cid = $gid
					GROUP BY uid ORDER BY cnt_join DESC");
			$users = array();
			while ($row = mysql_fetch_assoc($req)) {
				if ($row['cnt_join'] > 1 || $row['cnt_leave'] > 1) {
					$stat[] = $row;
					$users[] = $row['uid'];
				}
			}
			mysql_free_result($req);
			$users = array_unique($users);
			
			mk_page(array(
				'title' => 'Пользователи', 
				'content' => Tpl::render("returns.html", array(
					'stat' => &$stat, 
					'join_stat' => &$join_data, 
					'users' => get_vk_users($users), 
					'comm_tabs' => switch_tabs($comm_tabs, $gid)
				))
			));
		break;
		
		case "activity":
			$stat = array();
			
			$date = (new DateTime())
				->setTimestamp(mysql_result(mysql_query("SELECT MIN(date) FROM vk_posts WHERE group_id = -$gid"), 0));
			$date = (new DateTime('2016-01-01'));
			while ($date->getTimestamp() && $date->getTimestamp() < time()) {
				$start = mktime(0, 0, 0, $date->format("n"), $date->format("j"), $date->format("Y"));
				$end = mktime(23, 59, 59, $date->format("n"), $date->format("j"), $date->format("Y"));
				
				// Посты
				$users = array();
				$posts = array();
				$users_posts = array();
				
				$req = mysql_query("SELECT * FROM vk_posts WHERE date >= $start AND date <= $end AND group_id = -$gid");
				while ($row = mysql_fetch_assoc($req))
					$posts[] = $row['post_id'];
				
				// Репосты
				$reposts = 0;
				$req2 = mysql_query("SELECT DISTINCT user_id, post_id FROM vk_posts_reposts WHERE date >= $start AND date <= $end AND group_id = -$gid");
				while ($row = mysql_fetch_assoc($req2)) {
					$users[] = $row['user_id'];
					$users_posts[] = $row['post_id'];
					++$reposts;
				}
				
				// Камменты
				$req2 = mysql_query("SELECT DISTINCT user_id, post_id FROM vk_posts_comments WHERE date >= $start AND date <= $end AND group_id = -$gid");
				$comments = 0;
				while ($row = mysql_fetch_assoc($req2)) {
					$users[] = $row['user_id'];
					$users_posts[] = $row['post_id'];
					++$comments;
				}
				
				// ЛАйки
				$likes = 0;
				if ($posts) {
					$req2 = mysql_query("SELECT DISTINCT user_id, post_id FROM vk_posts_likes WHERE post_id IN (".implode(", ", $posts).") AND group_id = -$gid");
					while ($row = mysql_fetch_assoc($req2)) {
						$users[] = $row['user_id'];
						$users_posts[] = $row['post_id'];
						++$likes;
					}
				}
				
				$users = array_unique($users);
				$users_posts = array_unique($users_posts);

				$c = count($posts);
				$stat[] = array(
					'date' => $date->format("Y-m-d"), 
					'reposts' => $reposts, 
					'likes' => $likes, 
					'comments' => $comments, 
					'active_users' => count($users), 
					'activity' => round($users_posts ? count($users) / count($users_posts) : 0), 
					'posts' => $c
				);
				
				$date->add(date_interval_create_from_date_string('1 day'));
			}
			
			mk_page(array(
				'title' => 'Активность', 
				'content' => Tpl::render("activity.html", array(
					'stat' => &$stat
				))
			));
		break;
		
		case "user_info":
			$user_id = (int) array_val($_GET, 'id', 0);
			
			// Репосты
			$req = mysql_query("SELECT COUNT(*) FROM vk_posts_reposts WHERE user_id = $user_id AND group_id = -$gid");
			$reposts = mysql_result($req, 0);
			
			// Репосты 30 дней
			$req = mysql_query("SELECT COUNT(*) FROM vk_posts_reposts WHERE user_id = $user_id AND group_id = -$gid
				AND date BETWEEN CURDATE() - INTERVAL 30 DAY AND CURDATE()");
			$reposts30 = mysql_result($req, 0);
			
			// Лайки
			$req = mysql_query("SELECT COUNT(*) FROM vk_posts_likes WHERE user_id = $user_id AND group_id = -$gid");
			$likes = mysql_result($req, 0);
			
			// Камменты
			$req = mysql_query("SELECT COUNT(*) FROM vk_posts_comments WHERE user_id = $user_id AND group_id = -$gid");
			$comments = mysql_result($req, 0);
			
			// Камменты 30 дней
			$req = mysql_query("SELECT COUNT(*) FROM vk_posts_comments WHERE user_id = $user_id AND group_id = -$gid
				AND date BETWEEN CURDATE() - INTERVAL 30 DAY AND CURDATE()");
			$comments30 = mysql_result($req, 0);
			
			$joins = array();
			$req = mysql_query("SELECT * FROM `vk_join_stat` WHERE cid = $gid AND uid = $user_id GROUP BY time ASC");
			$last_time = 0;
			$time_in_comm = 0;
			while ($res = mysql_fetch_assoc($req)) {
				if ($res['type'] == 0) {
					$time_in_comm += $res['time'] - $last_time;
					$joins[count($joins) - 1]['time_in_comm'] = 0;
					$res['time_in_comm'] = $res['time'] - $last_time;
					$last_time = 0;
				} else if ($res['type'] == 1) {
					$last_time = $res['time'];
					$res['time_in_comm'] = time() - $res['time'];
				}
				$joins[] = $res;
			}
			if ($last_time)
				$time_in_comm += time() - $last_time;
			
			// array(6) { ["id"]=> string(1) "1" ["cid"]=> string(8) "94594114" ["uid"]=> string(8) "53344747" ["type"]=> string(1) "1" ["time"]=> string(10) "1434800396" ["users_cnt"]=> string(3) "764" }
			
			$user = get_vk_users(array($user_id))[$user_id];			
			mk_page(array(
				'title' => 'Информация о #'.$user_id, 
				'content' => Tpl::render("user_info.html", array(
					'comm_tabs' => switch_tabs($comm_tabs, $gid), 
					'reposts' => $reposts, 
					'reposts30' => $reposts30, 
					'likes' => $likes, 
					'comments' => $comments, 
					'comments30' => $comments30, 
					'time_in_comm' => count_time($time_in_comm), 
					'joins' => $joins, 
					'user' => vk_user_widget($user)
				))
			));
		break;
		
		case "users":
			mk_page(array(
				'title' => 'Пользователи', 
				'content' => Tpl::render("users.html", array(
					'comm_tabs' => switch_tabs($comm_tabs, $gid)
				))
			));
		break;
		
		case "search_users":
			$search = array_val($_GET, 'q', "");
			
			$res = $q->vkApi("users.search", array(
				'q'			=> $search, 
				'group_id'	=> $gid, 
				'fields'	=> 'sex,photo_50,bdate,verified'
			));
			
			$result = array();
			foreach ($res->response->items as $user) {
				$ret = vk_user_widget($user, '?a=user_info&amp;id='.$user->id);
				$ret['id'] = $user->id;
				$result[] = $ret;
			}
			mk_ajax(array('list' => Tpl::render("search_users_result.html", array(
				'users' => $result
			))));
		break;
		
		case "users_top":
			$users = array();
			$stat = array(
				'likes' => array(), 
				'reposts' => array(), 
				'comments' => array()
			);
			
			$filter = array_val($_GET, 'filter', "all");
			
			$req = mysql_query("SELECT user_id, SUM(likes) as likes, SUM(reposts) as reposts, SUM(comments) as comments, COUNT(*) as c 
				FROM `vk_posts_reposts` WHERE group_id = -$gid GROUP BY user_id");
			while ($row = mysql_fetch_assoc($req)) {
				if ($row['c'] > 0) {
					$users[] = $row['user_id'];
					$stat['reposts'][] = $row;
				}
			}
			mysql_free_result($req);
			$users = array_unique($users);
			
			$req = mysql_query("SELECT user_id, COUNT(*) as c FROM `vk_posts_likes` as a WHERE 
				EXISTS(select 0 from vk_join_stat as b WHERE b.uid = a.user_id AND b.cid = $gid) AND group_id = -$gid GROUP BY user_id");
			while ($row = mysql_fetch_assoc($req)) {
				if ($row['c'] > 0) {
					$users[] = $row['user_id'];
					$stat['likes'][] = $row;
				}
			}
			mysql_free_result($req);
			$users = array_unique($users);
			
			$req = mysql_query("SELECT user_id, COUNT(*) as c
				FROM `vk_posts_comments` WHERE group_id = -$gid GROUP BY user_id");
			while ($row = mysql_fetch_assoc($req)) {
				if ($row['c'] > 0) {
					$users[] = $row['user_id'];
					$stat['comments'][] = $row;
				}
			}
			mysql_free_result($req);
			$users = array_unique($users);
			
			$join_data = array();
			if ($users) {
				$users = array_unique($users);
				$req = mysql_query("SELECT * FROM `vk_join_stat` WHERE uid IN(".implode(", ", $users).") AND cid = $gid GROUP BY uid ORDER BY id DESC");
				while ($row = mysql_fetch_assoc($req))
					$join_data[$row['uid']] = $row;
			}
			
			unset($s);
			foreach ($stat as &$s) {
				foreach ($s as $i => $row) {
					if ($filter == 'leaved' && (!isset($join_data[$row['user_id']]) || $join_data[$row['user_id']]['type']))
						unset($s[$i]);
				}
			}
			unset($s);
			
			unset($s);
			foreach ($stat as &$s) {
				usort($s, function (&$a, &$b) {
					return $b['c'] - $a['c'];
				});
				$s = array_slice($s, 0, 200);
			}
			unset($s);
			
			$users_data = get_vk_users($users);
			$users_widgets = array();
			foreach ($users_data as $user)
				$users_widgets[$user_id] = vk_user_widget($user);
			
			mk_page(array(
				'title' => 'Пользователи', 
				'content' => Tpl::render("users_top_list.html", array(
					'tabs' => switch_tabs(array(
						'all' => array('Все', '?a=users&filter=all'), 
						'leaved' => array('Покинувшие', '?a=users&filter=leaved'), 
					), $filter), 
					'filter' => $filter, 
					'stat' => &$stat, 
					'join_stat' => &$join_data, 
					'users' => $users_data, 
					'users_widgets' => $users_widgets, 
					'comm_tabs' => switch_tabs($comm_tabs, $gid)
				))
			));
		break;
		
		case "fix_timeout":
			$output = array();
			fix_comments_timeout($q, $gid, $comm, $output);
			mk_ajax($output);
		break;
		
		case "queue":
			$output = array();
			$id = (int) array_val($_REQUEST, 'id', 0);
			$signed = (int) array_val($_REQUEST, 'signed', 0);
			$lat = (float) array_val($_REQUEST, 'lat', 0);
			$long = (float) array_val($_REQUEST, 'long', 0);
			$message = array_val($_REQUEST, 'message', "");
			$attachments = array_val($_REQUEST, 'attachments', "");
			
			$res = get_comments($q, $gid);
			
			$base_time = max($res->lastPosted, $res->lastPostponed);
			$post_time = get_post_time($base_time, $comm);
			
			if (!isset($res->suggests->items[$id])) {
				$output['error'] = 'Пост #'.$id.' не найден в предложениях!';
			} else {
				$data = array(
					'post_id' => $id, 
					'owner_id' => -$gid, 
					'signed' => $signed, 
					'message' => $message, 
					'lat' => $lat, 
					'long' => $long, 
					'attachments' => $attachments
				);
				
				if ($post_time)
					$data['publish_date'] = $post_time;
				
				$res = $q->vkApi("wall.post", $data);
				if (parse_vk_error($res, $output)) {
					$output['success'] = true;
					$output['date'] = display_date($post_time);
				}
			}
			
			mk_ajax($output);
		break;
		
		case "delete":
			$id = (int) array_val($_REQUEST, 'id', 0);
			$restore = (int) array_val($_REQUEST, 'restore', 0);
			
			$output = array();
			
			$res = $q->vkApi($restore ? "wall.restore" : "wall.delete", array(
				'owner_id' => -$gid, 
				'post_id' => $id
			));
			if (parse_vk_error($res, $output))
				$output['success'] = true;
			mk_ajax($output);
		break;
		
		case "settings":
			if ($_POST) {
				$from_hh = min(max(0, (int) array_val($_POST, 'from_hh', 0)), 23);
				$from_mm = min(max(0, (int) array_val($_POST, 'from_mm', 0)), 59);
				
				$to_hh = min(max(0, (int) array_val($_POST, 'to_hh', 0)), 23);
				$to_mm = min(max(0, (int) array_val($_POST, 'to_mm', 0)), 59);
				
				$interval_hh = min(max(0, (int) array_val($_POST, 'hh', 0)), 23);
				$interval_mm = min(max(0, (int) array_val($_POST, 'mm', 0)), 59);
				
				$to = $to_hh * 3600 + $to_mm * 60;
				$from = $from_hh * 3600 + $from_mm * 60;
				$interval = $interval_hh * 3600 + $interval_mm * 60;
				
				$interval = round($interval / 300) * 300;
				
				mysql_query("UPDATE `vk_groups` SET `period_from` = $from, `period_to` = $to, `interval` = $interval");
			}
			header("Location: ".preg_replace("/[\s:]/si", "", isset($_REQUEST['return']) ? $_REQUEST['return'] : '?'));
		break;
		
		default:
			$filter = isset($_REQUEST['filter']) ? preg_replace("/[^a-z0-9_-]+/i", "", $_REQUEST['filter']) : 'new';
			
			$res = get_comments($q, $gid);
			
			$invalid_interval_cnt = 0;
			$last_post_time = $res->postponed->items ? reset($res->postponed->items)->date : NULL;
			foreach ($res->postponed->items as $post) {
				if ($last_post_time == $post->date) // пропускаем первую запись
					continue;
				
				$norm_time = get_post_time($last_post_time, $comm);
				
				$diff = ($norm_time - $post->date);
				if (abs($diff) > 5) {
					$post->invalid = $diff;
					++$invalid_interval_cnt;
				}
				
				$last_post_time = $post->date;
			}
			
			$users = $res->users;
			
			$url = (new Url("?"))->set(array(
				'gid' => $gid
			));
			
			$list = $res->suggests;
			if ($filter == 'accepted')
				$list = $res->postponed;
			
			$json = array();
			$comments = array();
			foreach ($list->items as $item) {
				$json[$item->id] = get_post_json($item);
				$comments[] = Tpl::render("comment.html", array(
					'date' => display_date($item->date), 
					'text' => nl2br(links(check_spell(htmlspecialchars($item->text, ENT_QUOTES)))), 
					'id' => $item->id, 
					'gid' => abs($item->owner_id), 
					'user' => vk_user_widget($users[isset($item->created_by) && $item->created_by ? $item->created_by : $item->from_id]), 
					'deleted' => false, 
					'invalid' => isset($item->invalid) ? -$item->invalid : false, 
					'post_type' => $item->post_type, 
					'geo' => isset($item->geo) ? $item->geo : null, 
					'attachments' => isset($item->attachments) ? $item->attachments : null
				));
			}
			
			mk_page(array(
				'title' => 'Предложения постов', 
				'content' => Tpl::render("comments.html", array(
					'json' => json_encode($json, JSON_UNESCAPED_UNICODE), 
					'comm_tabs' => switch_tabs($comm_tabs, $gid), 
					'tabs' => switch_tabs(array(
						'new' => array('Новые ('.$res->suggests->count.')', $url->set('filter', 'new')->url()), 
						'accepted' => array('Принятые ('.$res->postponed->count.')', $url->set('filter', 'accepted')->url()), 
					//	'deleted' => array('Отклонённые ('.$total_canceled.')', $url->set('filter', 'deleted')->url()), 
					), $filter), 
					
					'last_post_time' => $res->lastPosted ? display_date($res->lastPosted) : 'n/a', 
					'last_delayed_post_time' => $res->lastPostponed ? display_date($res->lastPostponed) : 'n/a', 
					
					'last_post_time_unix' => $res->lastPosted, 
					'last_delayed_post_time_unix' => $res->lastPostponed, 
					
					'postponed_link' => (string) (new Url("?"))->set(array(
						'gid' => $gid, 
						'filter' => 'accepted'
					)), 
					'invalid_interval_cnt' => $invalid_interval_cnt, 
					'back' => $_SERVER['REQUEST_URI'], 
					
					'comments' => $comments, 
					'filter' => $filter, 
					'from' => parse_time($comm['period_from']), 
					'to' => parse_time($comm['period_to']), 
					'interval' => parse_time($comm['interval']), 
					'success' => isset($_REQUEST['ok']), 
					'postponed_cnt' => $res->postponed->count, 
					'suggests_cnt' => $res->suggests->count
				))
			));
		break;
	}
	
	function get_post_time($base_time, $comm) {
		$post_time = $base_time + $comm['interval'];
		if ($post_time < time() + 60) { // Если последний пост был слишком давно
			$post_time = 0;
		} else {
			$day_start = get_day_start($post_time);
			if (24 * 3600 - ($comm['period_to'] - $comm['period_from']) > 60) { // Есть фиксированный период постинга
				if ($post_time - ($day_start + $comm['period_from']) <= -10) {
					// Если время не попадает под минимальный период, то переносим его на начало периода текущего дня
					$post_time = $day_start + $comm['period_from'];
				} else if ($post_time - ($day_start + $comm['period_to']) >= 10) {
					// Если время превышает границу времени, то переносим на следующий день
					$post_time = $day_start + 24 * 3600 + $comm['period_from'];
				}
			}
		}
		
		if ($post_time && ($post_time % 300 != 0))
			$post_time = round($post_time / 300) * 300;
		
		return $post_time;
	}
	
	function fix_comments_timeout($q, $gid, $comm, &$output) {
		while (true) {
			$res = $q->vkApi("wall.get", array(
				'owner_id' => -$gid, 
				'filter' => 'postponed', 
				'count' => 1
			));
			if ($res->response->count < 2) {
				$output['success'] = true;
				return;
			}
			
			$times = array();
			$base_time = $res->response->items[0]->date;
			$last_time = $base_time;
			$times[] = $last_time;
			for ($i = 0; $i < $res->response->count + 100; ++$i) {
				$last_time = get_post_time($last_time, $comm);
				$times[] = $last_time;
			}
			
			$code = '
				var times = '.json_encode($times).', 
					base_time = '.$base_time.', 
					api_cnt = 0;
				
				var i = 0, 
					flag = true, 
					time_index = 0;
				while (flag) {
					api_cnt = api_cnt + 1;
					var tmp = API.wall.get({
						owner_id: -'.$gid.', 
						filter: "postponed", 
						count: 100, 
						offset: i
					});
					
					var j = 0;
					while (j < tmp.items.length) {
						var post = tmp.items[j];
						
						if (i == 0 && j == 0 && post.date != base_time)
							return {retry: true};
						
						var diff = post.date - times[time_index];
						if (diff > 10 || diff < -10) {
							api_cnt = api_cnt + 1;
							
							// Ненавижу VK Api
							var att_id = 0, attachments = [];
							if (post.attachments) {
								var map = {
									"photo": "photo", 
									"video": "video", 
									"doc": "doc", 
									"audio": "audio", 
									"poll": "poll"
								};
								while (att_id < post.attachments.length) {
									var att = post.attachments[att_id];
									if (map[att.type]) {
										var att_data = att[map[att.type]];
										attachments.push(att.type + att_data.owner_id + "_" + att_data.id);
									} else {
										return {error: "Странный тип аттача: " + att.type};
									}
									att_id = att_id + 1;
								}
							}
							var lat = "", lng = "";
							if (post.geo) {
								var k = 0, state = false;
								while (k < post.geo.coordinates.length) {
									var c = post.geo.coordinates.substr(k, 1);
									if (c == " ") {
										if (state)
											return {error: "Странные координаты: " + post.geo.coordinates};
										state = true;
									} else {
										if (state)
											lng = lng + c;
										else
											lat = lat + c;
									}
									k = k + 1;
								}
								if (lng.length == 0 || lat.length == 0)
									return {error: "Странные координаты: " + post.geo.coordinates};
							}
							
							API.wall.edit({
								owner_id: -'.$gid.', 
								post_id: post.id, 
								publish_date: times[time_index], 
								
								// Тупой VK
								message: post.text, 
								attachments: attachments, 
								signed: post.signer_id ? 1 : 0, 
								lat: lat, "long": lng, 
								post: post
							});
							
							if (api_cnt >= 25)
								return {retry: true};
						}
						
						time_index = time_index + 1;
						j = j + 1;
					}
					
					i = i + tmp.items.length;
					if (i >= tmp.count)
						flag = false;
				}
				
				return {done: true};
			';
			
			$res = $q->vkApi("execute", array('code' => $code));
			if ($res && isset($res->response)) {
			//	if (isset($res->response->retry)) {
			//		continue;
			//	}
				if (isset($res->response->done)) {
					$output['success'] = true;
					break;
				}
				if (isset($res->response->error)) {
					$output['error'] = $res->response->error;
					break;
				}
			} else {
				parse_vk_error($res, $output);
				break;
			}
			usleep(1000000 / 3);
		}
	}
	
	function parse_vk_error($res, &$output) {
		if ($res && isset($res->response))
			return true;
		if ($res && isset($res->error)) {
			if ($res->error->error_code == 14) {
				$output['captcha'] = array(
					'url' => $res->error->captcha_img, 
					'sid' => $res->error->captcha_sid
				);
			} else {
				$output['error'] = $res->error->error_msg;
			}
		} elseif (!$res || !isset($res->response)) {
			$output['error'] = "VK API недоступен. ";
		}
		return false;
	}
	
	function get_comments($q, $gid) {
		$code = '
			var postponed = API.wall.get({
				owner_id: -'.$gid.', 
				filter: "postponed", 
				extended: true, 
				count: 100
			});
			var suggests = API.wall.get({
				owner_id: -'.$gid.', 
				filter: "suggests", 
				extended: true, 
				count: 100
			});
			var last_comment = API.wall.get({
				owner_id: -'.$gid.', 
				filter: "all", 
				count: 2
			});
			
			postponed.profiles = API.users.get({user_ids: postponed.items@.created_by, fields: "photo_50"});
			
			var p1 = last_comment.items.length > 0 ? last_comment.items[0].date : 0;
			var p2 = last_comment.items.length > 1 ? last_comment.items[1].date : 0;
			
			return {
				postponed: postponed, 
				suggests: suggests, 
				lastPostponed: postponed.items ? postponed.items[postponed.items.length - 1].date : 0, 
				lastPosted: p1 > p2 ? p1 : p2
			};
		';
		$out = $q->vkApi("execute", array('code' => $code));
		if (!isset($out->response)) {
			echo '<pre>';
			echo htmlspecialchars(json_encode($out, JSON_PRETTY_PRINT));
			echo '</pre>';
			die();
		}
		
		$res = $out->response;
		
		$users = array();
		if ($res->postponed) {
			foreach ($res->postponed->profiles as $u)
				$users[$u->id] = $u;
			$comments = array();
			foreach ($res->postponed->items as $c)
				$comments[$c->id] = $c;
			$res->postponed->items = $comments;
		}
		if ($res->suggests) {
			foreach ($res->suggests->profiles as $u)
				$users[$u->id] = $u;
			$comments = array();
			foreach ($res->suggests->items as $c)
				$comments[$c->id] = $c;
			$res->suggests->items = array_reverse($comments, true);
		}
		$res->users = $users;
		return $res;
	}
	
	function get_wall_comments($q, $gid, $filter) {
		$res = $q->vkApi("wall.get", array(
			'owner_id' => $gid, 
			'filter' => $filter, 
			'extended' => true
		));
		$users = array();
		foreach ($res->response->profiles as $u)
			$users[$u->id] = $u;
		
		$comments = array();
		foreach ($res->response->items as $c)
			$comments[$u->id] = $c;
		return (object) array('items' => $comments, 'users' => $users);
	}
	
	function mk_ajax($data) {
		header("Content-Type: application/json; charset=UTF-8");
		echo json_encode($data);
	}
	
	function get_post_json($post) {
		$attach_map = array(
			"photo" => "photo", 
			"video" => "video", 
			"doc" => "doc", 
			"audio" => "audio", 
			"poll" => "poll", 
			"album" => "album"
		);
		$out = array(
			'lat' => 0, 
			'long' => 0, 
			'attachments' => array(), 
			'post_id' => $post->id, 
			'owner_id' => $post->owner_id, 
			'publish_date' => isset($post->publish_date) ? $post->publish_date : 0, 
			'message' => $post->text, 
			'signed' => isset($post->signer_id) ? (int) ((bool) $post->signer_id) : 0
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
	
	function links($text) {
		return preg_replace_callback('/(?:([!()?.,\s\n\r]|^)((https?:\/\/)?((?:[a-z0-9_\-]+\.)+(?:[a-z]{2,7}|xn--p1ai|xn--j1amh|xn--80asehdb|xn--80aswg))(\/.*?)?(\#.*?)?)(?:[.!:;,*()]*([\s\r\n]|$))|([!()?.,\s\n\r]|^)((https?:\/\/)?((?:[a-z0-9а-яєґї_\-]+\.)+(?:рф|укр|онлайн|сайт|срб|su))(\/.*?)?(\#.*?)?)(?:[.!:;,*()]*([\s\r\n]|$))|([!()?.,\s\n\r]|^)((https?:\/\/)((?:[a-z0-9а-яєґї_\-]+\.)+(?:[a-z]{2,7}|рф|укр|онлайн|сайт|срб|su))(\/.*?)?(\#.*?)?)(?:[.!:;,*()]*([\s\r\n]|$)))/sium', function ($m) {
			$offset = strlen($m[4]) ? 0 : (strlen($m[7 + 4]) ? 7 : 14);
			$url = $m[$offset + 2];
			return $m[$offset + 1].'<a href="'.$url.'" target="_blank">'.$url.'</a>'.$m[$offset + 7];
		}, $text);
	}
	
	function parse_time($t) {
		$h = floor($t / 3600);
		
		$m = floor(($t - $h * 3600) / 60);
		
		return array(
			'hh' => sprintf("%02d", $h), 
			'mm' => sprintf("%02d", $m)
		);
	}
	
	function check_spell($text) {
		if (function_exists('pspell_new')) {
			$errors = array();
			$text = preg_replace_callback("/([a-zа-яё][a-zа-яё'-]+[a-zа-яё]|[a-zа-яё]+)/siu", function ($m) {
				$lang = "en";
				if (preg_match("/[а-яё]/iu", $m[1]))
					$lang = "ru";
				
				if ($lang != "ru")
					return $m[1];
				
				if (!isset($psell[$lang]))
					$psell[$lang] = pspell_new($lang);
				
				if (!pspell_check($psell[$lang], $m[1])) {
					$errors = pspell_suggest($psell[$lang], $m[1]);
					return '<span class="spell" title="'.implode(", ", $errors).'">'.$m[1].'</span>';
				}
				return $m[1];
			}, $text);
		}
		return $text;
	}
	
	function get_vk_users($need_users) {
		$cache = array();
		$users = array();
		
		$cache_file = "tmp/vk_users_stat.dat";
		$need_users = array_unique($need_users);
		
		if (file_exists($cache_file)) {
			$fp = fopen($cache_file, "r");
			flock($fp, LOCK_EX);
			
			$data = "";
			while (!feof($fp))
				$data .= fread($fp, 2048);
			flock($fp, LOCK_UN);
			fclose($fp);
			
			$cache = unserialize($data);
		}
		
		while (count($need_users)) {
			$users_list = array();
			for ($i = 0; $i < 500; ++$i)
				$users_list[] = array_pop($need_users);
			
			$not_found = array();
			foreach ($users_list as $id) {
				if (!isset($cache[$id])) {
					$not_found[] = $id;
					continue;
				}
				$users[$id] = $cache[$id];
			}
			
			if ($not_found) {
				$vk_users = vk("users.get", array('user_ids' => implode(",", $not_found), 'fields' => 'sex,photo_50,bdate,verified'))->response;
				foreach ($vk_users as $u) {
					$users[$u->id] = $u;
					$cache[$u->id] = $u;
				}
				file_put_contents($cache_file, serialize($cache));
			}
			unset($vk_users);
		}
		
		$fp = fopen($cache_file, "w");
		flock($fp, LOCK_EX);
		ftruncate($fp, 0);
		fwrite($fp, serialize($cache));
		flock($fp, LOCK_UN);
		fclose($fp);
		
		return $users;
	}
	
	function vk_user_widget($user, $link = NULL) {
		return array(
			'widget' => Tpl::render("vk_user.html", array(
				'name' => $user->first_name." ".$user->last_name, 
				'preview' => $user->photo_50, 
				'link' => $link ? $link : "https://vk.com/".(isset($user->screen_name) && strlen($user->screen_name) ? $user->screen_name : 'id'.$user->id)
			)), 
			'avatar' => Tpl::render("vk_user_ava.html", array(
				'preview' => $user->photo_50
			))
		);
	}
