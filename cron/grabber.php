<?php
if (php_sapi_name() != "cli")
	die("Not a CLI :)\n");

require dirname(__FILE__)."/../inc/init.php";

if (file_exists(H."../tmp/grabber_lock") && (isset($argv[1]) && $argv[1] != "lock")) {
	echo "Lock file exists!";
	exit;
}
file_put_contents(H."../tmp/grabber_lock", 1);

// Граббим так же и свои группы
$req = Mysql::query("SELECT * FROM `vk_groups`");
$sources_selfgrab = [];
while ($group = $req->fetch()) {
	Mysql::query("
		INSERT IGNORE INTO vk_grabber_sources SET
		source_type = ?, 
		source_id = ?
	", \Z\Smm\Grabber::SOURCE_VK, -$group['id']);
	$sources_selfgrab[-$group['id']] = true;
}

// Получаем все активные источники
$sources_hash = [];
$sources_disabled = [];

$req = Mysql::query("SELECT * FROM `vk_grabber_sources`");
while ($s = $req->fetch()) {
	$is_active = isset($sources_selfgrab[$s['source_id']]) && $s['source_type'] == \Z\Smm\Grabber::SOURCE_VK;
	if (!$is_active) {
		$is_active = Mysql::query("SELECT MAX(enabled) FROM vk_grabber_selected_sources WHERE source_id = ?", $s['id'])
			->result();
	}
	
	if ($is_active)
		$sources_hash[$s['source_type']][$s['source_id']] = $s;
	else
		$sources_disabled[] = $s;
}

echo "clean unclaimed posts...\n";
foreach ($sources_disabled as $source) {
	do {
		$rows = Mysql::query("SELECT id, data_id FROM vk_grabber_data_index WHERE source_id = ? LIMIT 1000", $source['id'])
			->fetchAll();
		
		$post_ids = array_map(function ($v) { return $v['id']; }, $rows);
		$data_ids = array_map(function ($v) { return $v['data_id']; }, $rows);
		
		if ($rows) {
			echo \Z\Smm\Grabber::$type2name[$source['source_type']]." ".$source['source_id'].": delete ".count($rows)." unclaimed posts...\n";
			Mysql::query("DELETE FROM vk_grabber_data WHERE id IN (?)", $data_ids);
			Mysql::query("DELETE FROM vk_grabber_data_index WHERE id IN (?)", $post_ids);
		}
	} while ($rows);
}

echo "clean old posts...\n";
do {
	$rows = Mysql::query("SELECT id, data_id FROM vk_grabber_data_index WHERE source_type = ? AND grab_time <= ? LIMIT 1000", \Z\Smm\Grabber::SOURCE_INSTAGRAM, time() - 3600 * 24 * 7)
		->fetchAll();
	
	$post_ids = array_map(function ($v) { return $v['id']; }, $rows);
	$data_ids = array_map(function ($v) { return $v['data_id']; }, $rows);
	
	if ($rows) {
		echo "delete ".count($rows)." old instagram posts...\n";
		Mysql::query("DELETE FROM vk_grabber_data WHERE id IN (?)", $data_ids);
		Mysql::query("DELETE FROM vk_grabber_data_index WHERE id IN (?)", $post_ids);
	}
} while ($rows);

foreach ($sources_hash as $type => $type_sources) {
	echo "======================== ".\Z\Smm\Grabber::$type2name[$type]." ========================\n";
	
	$q = new Http;
	$q->vkSetUser('VK_GRABBER');
	
	// Граббер VK
	if ($type == \Z\Smm\Grabber::SOURCE_VK) {
		$api_limit = 25;
		$chunk = 100;
		$offset = 0;
		
		$counters = [];
		$ended = [];
		
		while (true) {
			$good = 0;
			$i = 0;
			$code_chunk = [];
			foreach ($type_sources as $id => $source) {
				echo "COMM ID: $id\n";
				if (!isset($ended[$id])) {
					$code_chunk[] = '
						"'.$id.'": API.wall.get({
							"owner_id": '.$id.', 
							"extended": true, 
							"count": '.$chunk.', 
							"offset": '.$offset.'
						})
					';
				}
				
				if (count($code_chunk) == $api_limit || ++$i == count($type_sources)) {
					$time = microtime(true);
					while (true) {
						$res = $q->vkApi("execute", [
							"code" => 'return {'.implode(",", $code_chunk).'};'
						]);
						if (!$res || !isset($res->response) || (isset($res->execute_errors) && $res->execute_errors)) {
							echo "err!\n";
							echo json_encode($res, JSON_PRETTY_PRINT);
							sleep(60 * 30);
						} else
							break;
					}
					$code_chunk = [];
					$time = microtime(true) - $time;
					
					echo "CHUNK: ".$offset." ... ".($offset + $chunk)." (".round($time, 2)." s)\n";
					
					foreach ($res->response as $gid => $response) {
						$counters[$gid] = $response->count;
						
						if (!$response->items)
							$ended[$gid] = true;
						
						$used_owners = [];
						$good2 = 0;
						foreach ($response->items as $item) {
							$att = vk_normalize_attaches($item);
							
							$gifs_cnt = $att->gifs;
							$images_cnt = $att->images;
							$attaches = $att->attaches;
							
							$used_owners[$item->owner_id] = 1;
							
							$ok = insert_to_db((object) [
								'source_id'			=> $type_sources[$item->owner_id]['id'], 
								'source_type'		=> $type, 
								'remote_id'			=> $item->owner_id."_".$item->id, 
								
								'text'				=> $item->text, 
								'owner'				=> $item->owner_id, 
								'attaches'			=> $attaches, 
								
								'time'				=> $item->date, 
								'likes'				=> (isset($item->likes) ? $item->likes->count : 0), 
								'comments'			=> (isset($item->comments) ? $item->comments->count : 0), 
								'reposts'			=> (isset($item->reposts) ? $item->reposts->count : 0), 
								'images_cnt'		=> $images_cnt, 
								'gifs_cnt'			=> $gifs_cnt
							]);
							if ($ok) {
								++$good;
								++$good2;
							}
						}
						
						if (!$good2)
							$ended[$gid] = true;
						
						foreach ($response->profiles as $item) {
							if (!isset($used_owners[$item->id]))
								continue;
							Mysql::query("
								INSERT INTO `vk_grabber_data_owners` SET
									`id` = '".Mysql::escape($type.'_'.$item->id)."', 
									`name` = '".Mysql::escape($item->first_name.' '.$item->last_name)."', 
									`url` = '".Mysql::escape("/".$item->screen_name)."', 
									`avatar` = '".Mysql::escape($item->photo_50)."'
								ON DUPLICATE KEY UPDATE
									`url` = VALUES(`url`), 
									`name` = VALUES(`name`), 
									`avatar` = VALUES(`avatar`)
							");
						}
						
						foreach ($response->groups as $item) {
							if (!isset($used_owners[-$item->id]))
								continue;
							Mysql::query("
								INSERT INTO `vk_grabber_data_owners` SET
									`id` = '".Mysql::escape($type.'_-'.$item->id)."', 
									`name` = '".Mysql::escape($item->name)."', 
									`url` = '".Mysql::escape("/".$item->screen_name)."', 
									`avatar` = '".Mysql::escape($item->photo_50)."'
								ON DUPLICATE KEY UPDATE
									`url` = VALUES(`url`), 
									`name` = VALUES(`name`)
							");
						}
						
						echo "OK: #".$item->id."\n";
					}
					sleep(10);
				}
			}
			$offset += $chunk;
			echo "good==$good\n";
			if (!$good)
				break;
		}
	}
	
	// Граббер OK
	elseif ($type == \Z\Smm\Grabber::SOURCE_OK) {
		$ended = [];
		$page = 0;
		while (true) {
			++$page;
			
			echo "PAGE: $page\n";
			
			$good = 0;
			foreach ($type_sources as $id => $source) {
				if (isset($ended[$id]))
					continue;
				
				echo "COMM_ID: $id\n";
				
				$page_url = "https://ok.ru/group/$id/topics/?st.page=$page";
				$q->timeout(10, 30);
				while (true) {
					$res = $q->exec($page_url);
					if ($res->code == 200)
						break;
					echo "http err: ".$res->code."\n";
					sleep(1);
				}
				$dom = create_dom($res->body);
				$main_xpath = new DOMXPath($dom);
				
				$good2 = 0;
				$title = false;
				
				$posts = $main_xpath->query('//div[contains(@class, "feed-list")]/div');
				if (!$posts->length)
					$posts = $main_xpath->query('//div[contains(@class, "groups_post-w")]');
				
				foreach ($posts as $item) {
					$xpath = $main_xpath;
					
					// Получаем id топика
					$topic_id = $xpath->query('.//*[@data-id1]', $item);
					if (!$topic_id->length) {
						echo "ERROR: Can't parse topic id ($page_url)\n";
						continue;
					}
					$topic_id = (int) $topic_id->item(0)->getAttribute("data-id1");
					
					$topic_url = "https://ok.ru/group/$id/topic/$topic_id";
					
					if ($page == 1 && !$title) {
						// Получаем имя соо
						$title = $xpath->query('//meta[@property="og:title"]');
						if (!$title->length) {
							echo "ERROR: can't get comm title ($page_url)\n";
							continue;
						}
						$title = str_replace(" — Темы | OK.RU", "", $title->item(0)->getAttribute("content"));
					
						// Получаем аватарку
						$avatar = $xpath->query('//meta[@property="og:image"]');
						if (!$avatar->length) {
							echo "ERROR: can't get comm avatar ($page_url)\n";
							continue;
						}
						$avatar = str_replace("http:", "https:", $avatar->item(0)->getAttribute("content"));
						
						Mysql::query("
							INSERT INTO `vk_grabber_data_owners` SET
								`id` = '".Mysql::escape($type.'_'.$id)."', 
								`name` = '".Mysql::escape($title)."', 
								`url` = '".Mysql::escape("https://ok.ru/group/$id")."', 
								`avatar` = '".Mysql::escape($avatar)."'
							ON DUPLICATE KEY UPDATE
								`url` = VALUES(`url`), 
								`name` = VALUES(`name`), 
								`avatar` = VALUES(`avatar`)
						");
					}
					
					// Парсим дату топика
					$date_raw = $xpath->query('.//*[contains(@class, "feed_date")]', $item);
					if (!$date_raw->length) {
						echo "ERROR: #$topic_id Can't parse topic date ($topic_url)\n";
						continue;
					}
					$date_raw = $date_raw->item(0)->textContent;
					
					$date = parse_ok_date($date_raw);
					if (!$date) {
						echo "ERROR: #$topic_id Can't parse topic date: '$date_raw' ($topic_url)\n";
						continue;
					}
					
					// Кол-во комментариев
					$comments = $xpath->query('.//*[contains(@href, "/discussions/")]//*[contains(@class, "js-count")]', $item);
					if (!$comments->length) {
						echo "ERROR: #$topic_id can't get comments cnt ($topic_url)\n";
						continue;
					}
					$comments = (int) preg_replace("/\D/", "", $comments->item(0)->textContent);
					
					// Кол-во репостов
					$reposts = $xpath->query('.//*[contains(@data-type, "RESHARE")]//*[contains(@class, "js-count")]', $item);
					if (!$reposts->length) {
						echo "ERROR: #$topic_id can't get reposts cnt ($topic_url)\n";
						continue;
					}
					$reposts = (int) preg_replace("/\D/", "", $reposts->item(0)->textContent);
					
					// Кол-во лайков
					$likes = $xpath->query('.//*[contains(@class, "controls-list_lk")]//*[contains(@class, "js-count")]', $item);
					if (!$likes->length) {
						echo "ERROR: #$topic_id can't get likes cnt ($topic_url)\n";
						continue;
					}
					
					$likes = (int) preg_replace("/\D/", "", $likes->item(0)->textContent);
					
					// Получаем весь топик
					$q->timeout(10, 30);
					while (true) {
						$res = $q->exec("https://ok.ru/group/$id/topic/$topic_id");
						if ($res->code == 200)
							break;
						echo "http err: ".$res->code."\n";
						sleep(1);
					}
					$topic_dom = create_dom($res->body);
					$xpath = new DOMXPath($topic_dom);
					$item = $xpath->query("//div[@id='cnts_$topic_id']")->item(0);
					
					if (!$item) {
						echo "ERROR: #$topic_id can't get full topic ($topic_url)\n";
						continue;
					}
					
					$attaches = [];
					
					// Получаем гифы
					$gifs_cnt = 0;
					foreach ($xpath->query('.//*[@data-gifsrc]', $item) as $g) {
						$mp4_src = "https:".$g->getAttribute("data-mp4src");
						$gif_src = "https:".$g->getAttribute("data-gifsrc");
						$gif_thumb_src = "https:".$g->getAttribute("data-imagesrc");
						$width = $g->getAttribute("data-width");
						$height = $g->getAttribute("data-height");
						
						$attaches[] = [
							'id' => 'doc_'.md5($gif_src), 
							'type' => 'doc', 
							'ext' => "gif", 
							'title' => "pic.gif", 
							'w' => $width, 
							'h' => $height, 
							'thumbs' => [
								$width => $gif_thumb_src
							], 
							'url' => $gif_src, 
							'mp4' => $mp4_src != "https:" ? $mp4_src : null
						];
						++$gifs_cnt;
					}
					
					// Получаем обычные картинки
					$images_cnt = 0;
					foreach ($xpath->query('.//*[contains(@class, "media-photos_img")]', $item) as $g) {
						$src = "https:".$g->getAttribute("src");
						$width = $g->getAttribute("width");
						$height = $g->getAttribute("height");
						$attaches[] = [
							'id' => 'photo_'.md5($src), 
							'type' => 'photo', 
							'w' => $width, 
							'h' => $height, 
							'thumbs' => [
								$width => $src
							]
						];
						++$images_cnt;
					}
					
					// Получаем внешние картинки
					foreach ($xpath->query('.//*[contains(@class, "media-photos")]//*[contains(@class, "image-hover")]//img', $item) as $g) {
						$src = "https:".$g->getAttribute("src");
						
						list ($width, $height) = getimagesize($src);
						if ($width) {
							$attaches[] = [
								'id' => 'photo_'.md5($src), 
								'type' => 'photo', 
								'w' => $width, 
								'h' => $height, 
								'thumbs' => [
									$width => $src
								]
							];
						} else {
							echo "ERROR: #$topic_id Can't download $src\n";
						}
						++$images_cnt;
					}
					
					// Получаем текст
					$text = [];
					foreach ($xpath->query('.//*[contains(@class, "media-text") and contains(@class, "media-block")]', $item) as $t)
						$text[] = $t->textContent;
					$text = implode("\n", $text);
					
					if (!$attaches && !$text) {
						echo "ERROR: #$topic_id topic without text and attaches ($topic_url)\n";
						continue;
					}
					
					$ok = insert_to_db((object) [
						'source_id'			=> $source['id'], 
						'source_type'		=> $type, 
						'remote_id'			=> $topic_id, 
						
						'text'				=> $text, 
						'owner'				=> $id, 
						'attaches'			=> $attaches, 
						
						'time'				=> $date, 
						'likes'				=> $likes, 
						'comments'			=> $comments, 
						'reposts'			=> $reposts, 
						'images_cnt'		=> $images_cnt, 
						'gifs_cnt'			=> $gifs_cnt
					]);
					if ($ok) {
						++$good;
						++$good2;
					}
					
					echo "OK: #$topic_id\n";
				}
				
				if (!$good2)
					$ended[$id] = true;
			}
			echo "good=$good\n";
			if (!$good)
				break;
		}
	}
	
	// Граббер INSTAGRAM
	elseif ($type == \Z\Smm\Grabber::SOURCE_INSTAGRAM) {
		$ended = [];
		$page = 0;
		
		$q->enableCookies();
		
		while (true) {
			++$page;
			
			echo "PAGE: $page\n";
			
			$good = 0;
			foreach ($type_sources as $id => $source) {
				if (isset($ended[$id]))
					continue;
				
				Mysql::query("
					INSERT INTO `vk_grabber_data_owners` SET
						`id` = '".Mysql::escape($type.'_'.$id)."', 
						`name` = '".Mysql::escape("#$id")."', 
						`url` = '".Mysql::escape("https://www.instagram.com/explore/tags/".urlencode($id)."/")."', 
						`avatar` = '".Mysql::escape("https://www.instagram.com/static/images/ico/xxxhdpi_launcher.png/9fc4bab7565b.png")."'
					ON DUPLICATE KEY UPDATE
						`url` = VALUES(`url`), 
						`name` = VALUES(`name`), 
						`avatar` = VALUES(`avatar`)
				");
				
				echo "COMM_ID: $id\n";
				
				$page_url = "https://www.instagram.com/explore/tags/".urlencode($id)."/?__a=1";
				$q->timeout(10, 30);
				
				$ban_errors = 10;
				$max_tries = 20;
				
				while (true) {
					$res = $q->exec($page_url);
					if ($res->code == 200)
						break;
					echo "http err: ".$res->code."\n";
					
					if ($res->code == 429) {
						echo "wait minute...\n";
						sleep(60);
						--$ban_errors;
					} else if ($res->code == 404) {
						echo "$page_url - 404\n";
						break;
					} else {
						var_dump($res->body);
					}
					
					if (!$ban_errors || !$max_tries)
						break;
					--$max_tries;
				}
				
				if (!$ban_errors) {
					echo "ERROR: Instagram ban! :(\n";
					continue;
				}
				
				$json = @json_decode($res->body);
				if (!$json) {
					echo "ERROR: Can't parse JSON ($page_url)\n$res\n";
					continue;
				}
				
				if (!isset($json->graphql, $json->graphql->hashtag)) {
					echo "ERROR: Can't find graphql->hashtag from JSON ($page_url)\n";
					var_dump($json);
					break;
				}
				
				$good2 = 0;
				
				$edges_lists = [$json->graphql->hashtag->edge_hashtag_to_top_posts->edges, $json->graphql->hashtag->edge_hashtag_to_media->edges];
				foreach ($edges_lists as $edges_list) {
					foreach ($edges_list as $edge) {
						// Получаем ID фотки
						$topic_id = $edge->node->shortcode;
						if (!$topic_id) {
							echo "ERROR: Can't parse topic id ($page_url)\n";
							continue;
						}
						
						// Видео не нужны
						if ($edge->node->is_video)
							continue;
						
						// Ссылка на фотку
						$topic_url = "https://www.instagram.com/p/$topic_id";
						
						// Дата загрузки фотки
						$date = $edge->node->taken_at_timestamp;
						
						// Описание фотки
						$text = "";
						if (isset($edge->node->edge_media_to_caption)) {
							if (isset($edge->node->edge_media_to_caption->edges, $edge->node->edge_media_to_caption->edges[0]))
								$text = $edge->node->edge_media_to_caption->edges[0]->node->text;
						}
						
						// Лайки
						$likes = $edge->node->edge_liked_by->count;
						
						// Комменты
						$comments = $edge->node->edge_media_to_comment->count;
						
						$attaches = [];
						$edges_to_parse = [];
						
						if  (isset($edge->node->__typename) && $edge->node->__typename == "GraphSidecar") {
							$ajax_topic_url = "https://www.instagram.com/p/$topic_id/?__a=1";
							
							$ban_errors = 10;
							$max_tries = 20;
							
							while (true) {
								$res = $q->exec($ajax_topic_url);
								if ($res->code == 200)
									break;
								echo "http err: ".$res->code."\n";
								
								if ($res->code == 429) {
									echo "wait minute...\n";
									sleep(60);
									--$ban_errors;
								} else if ($res->code == 404) {
									echo "$ajax_topic_url - 404\n";
									break;
								} else {
									var_dump($res->body);
								}
								
								if (!$ban_errors || !$max_tries)
									break;
								--$max_tries;
							}
							
							if (!$ban_errors) {
								echo "ERROR: Instagram ban! :( ($ajax_topic_url)\n";
								continue;
							}
							
							$json = @json_decode($res->body);
							if (!$json) {
								echo "ERROR: Can't parse JSON ($ajax_topic_url)\n$res\n";
								continue;
							}
							
							if (!isset($json->graphql, $json->graphql->shortcode_media)) {
								echo "ERROR: Can't find graphql->shortcode_media from JSON ($ajax_topic_url)\n";
								var_dump($json);
								break;
							}
							
							foreach ($json->graphql->shortcode_media->edge_sidecar_to_children->edges as $sub_edge)
								$edges_to_parse[] = $sub_edge;
						} else {
							$edges_to_parse[] = $edge;
						}
						
						foreach ($edges_to_parse as $sub_edge) {
							$src = $sub_edge->node->display_url;
							
							if ($src) {
								$attaches[] = [
									'id'		=> 'photo_'.md5($src), 
									'type'		=> 'photo', 
									'w'			=> $sub_edge->node->dimensions->width, 
									'h'			=> $sub_edge->node->dimensions->height, 
									'thumbs' => [
										$sub_edge->node->dimensions->width => $src
									]
								];
							}
							
						}
						
						if (!$attaches) {
							echo "ERROR: #$topic_id topic without attaches ($topic_url)\n";
							continue;
						}
						
						$ok = insert_to_db((object) [
							'source_id'			=> $source['id'], 
							'source_type'		=> $type, 
							'remote_id'			=> $topic_id, 
							
							'text'				=> $text, 
							'owner'				=> $id, 
							'attaches'			=> $attaches, 
							
							'time'				=> $date, 
							'likes'				=> $likes, 
							'comments'			=> $comments, 
							'reposts'			=> 0, 
							'images_cnt'		=> 1, 
							'gifs_cnt'			=> 0
						]);
						
						echo "OK: $topic_url\n";
						
						if ($ok) {
							++$good;
							++$good2;
						}
					}
					
					if (!$good2)
						$ended[$id] = true;
				}
			}
			
			echo "good=$good\n";
			if (!$good)
				break;
			
			// Временно только одна страница!
			break;
		}
	}
}
unlink(H."../tmp/grabber_lock");

function insert_to_db($data) {
	$req = Mysql::query("SELECT `data_id`, `source_id` FROM `vk_grabber_data_index` WHERE 
		`source_type` = '".Mysql::escape($data->source_type)."' AND 
		`remote_id` = '".Mysql::escape($data->remote_id)."'");
	$old_record = $req->num() ? $req->fetch() : false;
	
	$good = 0;
	$data_id = 0;
	$source_id = $data->source_id;
	
	if (!$old_record) {
		++$good;
		$data_id = Mysql::query("
			INSERT INTO `vk_grabber_data` SET
				`text`			= '".Mysql::escape($data->text)."', 
				`owner`			= '".Mysql::escape($data->owner)."', 
				`attaches`		= '".Mysql::escape(gzdeflate(serialize($data->attaches)))."'
		")->id();
	} else {
		$source_id = $old_record['source_id'];
		$data_id = $old_record['data_id'];
		Mysql::query("
			UPDATE `vk_grabber_data` SET
				`text`			= '".Mysql::escape($data->text)."', 
				`owner`			= '".Mysql::escape($data->owner)."', 
				`attaches`		= '".Mysql::escape(gzdeflate(serialize($data->attaches)))."'
			WHERE
				`id` = ".$data_id."
		");
	}
	
	$post_type = 0;
	if ($data->images_cnt > 0 && $data->gifs_cnt > 0)
		$post_type = 3;
	elseif ($data->images_cnt > 0)
		$post_type = 2;
	elseif ($data->gifs_cnt > 0)
		$post_type = 1;
	
	Mysql::query("
		INSERT INTO `vk_grabber_data_index` SET
			`source_id`		= '".Mysql::escape($source_id)."', 
			`source_type`	= '".Mysql::escape($data->source_type)."', 
			`remote_id`		= '".Mysql::escape($data->remote_id)."', 
			`data_id`		= ".$data_id.", 
			`time`			= ".$data->time.", 
			`grab_time`		= ".time().", 
			`likes`			= ".$data->likes.", 
			`comments`		= ".$data->comments.", 
			`reposts`		= ".$data->reposts.", 
			`images_cnt`	= ".$data->images_cnt.", 
			`gifs_cnt`		= ".$data->gifs_cnt.", 
			`post_type`		= ".$post_type."
		ON DUPLICATE KEY UPDATE
			`data_id`		= VALUES(`data_id`), 
			`time`			= VALUES(`time`), 
			`grab_time`		= VALUES(`grab_time`), 
			`likes`			= VALUES(`likes`), 
			`comments`		= VALUES(`comments`), 
			`reposts`		= VALUES(`reposts`), 
			`images_cnt`	= VALUES(`images_cnt`), 
			`gifs_cnt`		= VALUES(`gifs_cnt`)
	");
	
	return $good;
}


function inner_text($node) {
	if ($node->nodeName == '#text') {
		echo "inner_text: #text\n";
	} else if (isset($node->childNodes)) {
		echo '<'.$node->nodeName.'>';
		foreach ($node->childNodes as $n) {
			inner_text($n);
		}
		echo '</'.$node->nodeName.'>';
	}
}

function parse_ok_date($str) {
	$months = [
		'янв'		=> 1, 
		'января'	=> 1, 
		
		'фев'		=> 2, 
		'февраля'	=> 2, 
		
		'мар'		=> 3, 
		'марта'		=> 3, 
		
		'апр'		=> 4, 
		'апреля'	=> 4, 
		
		'май'		=> 5, 
		'мая'		=> 5, 
		
		'июн'		=> 6, 
		'июня'		=> 6, 
		
		'июл'		=> 7, 
		'июля'		=> 7, 
		
		'авг'		=> 8, 
		'августа'	=> 8, 
		
		'сен'		=> 9, 
		'сентября'	=> 9, 
		
		'окт'		=> 10, 
		'октября'	=> 10, 
		
		'ноя'		=> 11, 
		'ноября'	=> 11, 
		
		'дек'		=> 12, 
		'декабря'	=> 12, 
	];
	
	if (preg_match("/вчера\s+(в\s+)?(\d+:\d+)/ui", $str, $m)) { // Вчера
		$date = DateTime::createFromFormat("H:i", $m[2]);
		if ($date) {
			$date->sub(new DateInterval('P1D'));
			return $date->format('U');
		}
	} else if (preg_match("/(\d+)\s+(\w+)\s+(\d{4})\s+(в\s+)?(\d+:\d+)/ui", $str, $m)) { // Прошлый год + время
		$month = mb_strtolower($m[2]);
		if (!isset($months[$month]))
			return 0;
		$date = DateTime::createFromFormat("d/m/Y H:i", $m[1]."/".$months[$month]."/".$m[3]." ".$m[5]);
		if ($date) {
			$date->setTime(0, 0, 0);
			return $date->format('U');
		}
	} else if (preg_match("/(\d+)\s+(\w+)\s+(\d{4})/ui", $str, $m)) { // Прошлый год
		$month = mb_strtolower($m[2]);
		if (!isset($months[$month]))
			return 0;
		$date = DateTime::createFromFormat("d/m/Y", $m[1]."/".$months[$month]."/".$m[3]);
		if ($date) {
			$date->setTime(0, 0, 0);
			return $date->format('U');
		}
	} else if (preg_match("/(\d+)\s+(\w+)\s+(в\s+)?(\d+:\d+)/ui", $str, $m)) { // Позавчера и далее
		$month = mb_strtolower($m[2]);
		if (!isset($months[$month]))
			return 0;
		$date = DateTime::createFromFormat("d/m H:i", $m[1]."/".$months[$month]." ".$m[4]);
		if ($date)
			return $date->format('U');
	} else if (preg_match("/(\d+:\d+)/ui", $str, $m)) { // Сегодня
		$date = DateTime::createFromFormat("H:i", $m[1]);
		if ($date)
			return $date->format('U');
	} else {
		echo "Unknown date format: '$str'\n";
	}
	return 0;
}
