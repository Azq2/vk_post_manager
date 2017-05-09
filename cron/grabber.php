<?php
if (php_sapi_name() != "cli")
	die("Not a CLI :)\n");

require dirname(__FILE__)."/../inc/init.php";

if (file_exists(H."../tmp/grabber_lock") && (isset($argv[1]) && $argv[1] != "lock")) {
	echo "Lock file exists!";
	exit;
}
file_put_contents(H."../tmp/grabber_lock", 1);

$q = new Http;

$sources_hash = [];
$req = mysql_query("SELECT * FROM `vk_grabber_sources`");
while ($s = mysql_fetch_assoc($req)) {
	$sources_hash[$s['type']][$s['id']] = 1;
	$sources_hash['VK'][-$s['group_id']] = 1; // Ещё и граббим свою группу
}

foreach ($sources_hash as $type => $type_sources) {
	echo "======================== $type ========================\n";
	
	// Граббер VK
	if ($type == 'VK') {
		$api_limit = 25;
		$chunk = 100;
		$offset = 0;
		
		$counters = [];
		$ended = [];
		
		while (true) {
			$good = 0;
			$i = 0;
			$code_chunk = [];
			foreach ($type_sources as $id => $s) {
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
						if (!$res || !isset($res->response))
							echo "err!\n";
						else
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
							$gifs_cnt = 0;
							$images_cnt = 0;
							$attaches = [];
							
							if (isset($item->geo)) {
								list ($lat, $lng) = explode(" ", $item->geo->coordinates);
								$attaches[] = [
									'id' => 'geo'.md5($lat.','.$lng), 
									'type' => 'geo', 
									'lat' => $lat, 
									'lng' => $lng
								];
							}
							
							if (isset($item->attachments)) {
								foreach ($item->attachments as $att_data) {
									$att = $att_data->{$att_data->type};
									$att->type = $att_data->type;
									
									if ($att->type == 'photo' || $att->type == 'posted_photo' || $att->type == 'graffiti') {
										++$images_cnt;
										
										$thumbs = extract_vk_thumbs($att);
										
										if (!$thumbs) {
											var_dump($item);
											die("Photo without thumbs!!!!\n");
										}
										
										if (!isset($att->width)) {
											$last = end($thumbs);
											list ($att->width, $att->height) = getimagesize($last);
										}
										
										$attaches[] = [
											'id' => $att->type.$att->owner_id.'_'.$att->id, 
											'type' => 'photo', 
											'w' => $att->width, 
											'h' => $att->height, 
											'thumbs' => $thumbs 
										];
									} else if ($att->type == 'video') {
										$attaches[] = [
											'id' => $att->type.$att->owner_id.'_'.$att->id, 
											'type' => 'video', 
											'w' => isset($att->width) ? $att->width : 0, 
											'h' => isset($att->height) ? $att->height : 0, 
											'title' => $att->title, 
											'description' => $att->description, 
											'thumbs' => extract_vk_thumbs($att), 
											'url' => "https://vk.com/".$att->type.$att->owner_id.'_'.$att->id
										];
									} else if ($att->type == 'album') {
										$attaches[] = [
											'id' => $att->type.$att->owner_id.'_'.$att->id, 
											'type' => 'album', 
											'w' => $att->thumb->width, 
											'h' => $att->thumb->height, 
											'title' => $att->title, 
											'description' => $att->description, 
											'thumbs' => extract_vk_thumbs($att->thumb), 
											'url' => 'https://vk.com/'.$att->type.$att->owner_id.'_'.$att->id
										];
									} else if ($att->type == 'market_album') {
										$attaches[] = [
											'id' => $att->type.$att->owner_id.'_'.$att->id, 
											'type' => 'market_album', 
											'w' => $att->photo->width, 
											'h' => $att->photo->height, 
											'title' => $att->title, 
											'thumbs' => extract_vk_thumbs($att->photo)
										];
									} else if ($att->type == 'app') {
										$attaches[] = [
											'id' => $att->type.$att->owner_id.'_'.$att->id, 
											'thumbs' => extract_vk_thumbs($att->thumb)
										];
									} else if ($att->type == 'note') {
										$attaches[] = [
											'id' => $att->type.$att->user_id.'_'.$att->id, 
											'title' => $att->title, 
											'text' => $att->text, 
											'thumbs' => extract_vk_thumbs($att->thumb)
										];
									} else if ($att->type == 'doc') {
										if (preg_match("/^(gif|png|jpg|jpeg|bmp)$/i", $att->ext)) {
											if (preg_match("/^(gif)$/i", $att->ext))
												++$gifs_cnt;
											else
												++$images_cnt;
										}
										
										$width = 0;
										$height = 0;
										$thumbs = [];
										if (isset($att->preview, $att->preview->photo)) {
											foreach ($att->preview->photo->sizes as $p) {
												if ($width < $p->width) {
													$width = $p->width;
													$height = $p->height;
												}
												$thumbs[$p->width] = $p->src;
											}
										}
										
										$mp4 = null;
										if (isset($att->preview, $att->preview->video))
											$mp4 = $att->preview->video->src;
										
										$attaches[] = [
											'id' => $att->type.$att->owner_id.'_'.$att->id, 
											'type' => 'doc', 
											'ext' => $att->ext, 
											'title' => $att->title, 
											'w' => $width, 
											'h' => $height, 
											'thumbs' => $thumbs, 
											'url' => $att->url, 
											'mp4' => $mp4, 
											'page_url' => "https://vk.com/".$att->type.$att->owner_id.'_'.$att->id
										];
									} else if ($att->type == 'page') {
										$attaches[] = [
											'id' => $att->type.'-'.$att->group_id.'_'.$att->id, 
											'type' => 'page', 
											'title' => $att->title, 
											'url' => $att->view_url
										];
									} else if ($att->type == 'audio') {
										$attaches[] = [
											'id' => $att->type.$att->owner_id.'_'.$att->id, 
											'type' => 'audio', 
											'title' => $att->artist.' - '.$att->title
										];
									} else if ($att->type == 'poll') {
										$answers = [];
										foreach ($att->answers as $a)
											$answers[] = $a->text;
										
										$attaches[] = [
											'id' => $att->type.$att->owner_id.'_'.$att->id, 
											'type' => 'poll', 
											'question' => $att->question, 
											'answers' => $answers, 
											'anon' => $att->anonymous
										];
									} else if ($att->type == 'link') {
										$attaches[] = [
											'id' => 'link_'.md5($att->url), 
											'type' => 'link', 
											'url' => $att->url, 
											'title' => $att->title, 
											'description' => $att->description
										];
									} else if ($att->type == 'market') {
										list ($width, $height) = getimagesize($att->thumb_photo);
										
										$attaches[] = [
											'id' => $att->type.$att->owner_id.'_'.$att->id, 
											'type' => 'market', 
											'price' => $att->price->text, 
											'title' => $att->title, 
											'description' => $att->description, 
											'thumbs' => [
												$width => $att->thumb_photo
											], 
											'w' => $width, 
											'h' => $height, 
											'url' => "https://vk.com/".$att->type.$att->owner_id.'_'.$att->id
										];
									} else {
										var_dump($att);
										die("UNK ATTACH: ".$att->type);
									}
								}
							}
							
							$used_owners[$item->owner_id] = 1;
							
							$ok = insert_to_db((object) [
								'source_id'			=> $item->owner_id, 
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
							mysql_query("
								INSERT INTO `vk_grabber_data_owners` SET
									`id` = '".mysql_real_escape_string($type.'_'.$item->id)."', 
									`name` = '".mysql_real_escape_string($item->first_name.' '.$item->last_name)."', 
									`url` = '".mysql_real_escape_string("/".$item->screen_name)."', 
									`avatar` = '".mysql_real_escape_string($item->photo_50)."'
								ON DUPLICATE KEY UPDATE
									`url` = VALUES(`url`), 
									`name` = VALUES(`name`), 
									`avatar` = VALUES(`avatar`)
							") or die("MYSQL ERROR: ".mysql_error());
						}
						
						foreach ($response->groups as $item) {
							if (!isset($used_owners[-$item->id]))
								continue;
							mysql_query("
								INSERT INTO `vk_grabber_data_owners` SET
									`id` = '".mysql_real_escape_string($type.'_-'.$item->id)."', 
									`name` = '".mysql_real_escape_string($item->name)."', 
									`url` = '".mysql_real_escape_string("/".$item->screen_name)."', 
									`avatar` = '".mysql_real_escape_string($item->photo_50)."'
								ON DUPLICATE KEY UPDATE
									`url` = VALUES(`url`), 
									`name` = VALUES(`name`)
							") or die("MYSQL ERROR: ".mysql_error());
						}
						
						echo "OK: #".$item->id."\n";
					}
				}
			}
			$offset += $chunk;
			echo "good==$good\n";
			if (!$good)
				break;
		}
	} elseif ($type == 'OK') {
		$ended = [];
		$page = 0;
		while (true) {
			++$page;
			
			echo "PAGE: $page\n";
			
			$good = 0;
			foreach ($type_sources as $id => $s) {
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
						
						mysql_query("
							INSERT INTO `vk_grabber_data_owners` SET
								`id` = '".mysql_real_escape_string($type.'_'.$id)."', 
								`name` = '".mysql_real_escape_string($title)."', 
								`url` = '".mysql_real_escape_string("https://ok.ru/group/$id")."', 
								`avatar` = '".mysql_real_escape_string($avatar)."'
							ON DUPLICATE KEY UPDATE
								`url` = VALUES(`url`), 
								`name` = VALUES(`name`), 
								`avatar` = VALUES(`avatar`)
						") or die("MYSQL ERROR: ".mysql_error());
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
						'source_id'			=> $id, 
						'source_type'		=> $type, 
						'remote_id'			=> $topic_id, 
						
						'text'				=> $text, 
						'owner'				=> $reposts, 
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
}
unlink(H."../tmp/grabber_lock");

function insert_to_db($data) {
	$req = mysql_query("SELECT `data_id` FROM `vk_grabber_data_index` WHERE 
		`source_id` = '".mysql_real_escape_string($data->source_id)."' AND 
		`source_type` = '".mysql_real_escape_string($data->source_type)."' AND 
		`remote_id` = '".mysql_real_escape_string($data->remote_id)."'");
	if (!$req)
		die("MYSQL ERROR 1: ".mysql_error());
	
	$data_id = mysql_num_rows($req) ? mysql_result($req, 0) : 0;
	
	$good = 0;
	if (!$data_id) {
		++$good;
		mysql_query("
			INSERT INTO `vk_grabber_data` SET
				`text`			= '".mysql_real_escape_string($data->text)."', 
				`owner`			= '".mysql_real_escape_string($data->owner)."', 
				`attaches`		= '".mysql_real_escape_string(gzdeflate(serialize($data->attaches)))."'
		") or die("MYSQL ERROR 2: ".mysql_error());
		$data_id = mysql_insert_id();
	} else {
		mysql_query("
			UPDATE `vk_grabber_data` SET
				`text`			= '".mysql_real_escape_string($data->text)."', 
				`owner`			= '".mysql_real_escape_string($data->owner)."', 
				`attaches`		= '".mysql_real_escape_string(gzdeflate(serialize($data->attaches)))."'
			WHERE
				`id` = ".$data_id."
		") or die("MYSQL ERROR 3: ".mysql_error());
	}
	
	mysql_query("
		INSERT INTO `vk_grabber_data_index` SET
			`source_id`		= '".mysql_real_escape_string($data->source_id)."', 
			`source_type`	= '".mysql_real_escape_string($data->source_type)."', 
			`remote_id`		= '".mysql_real_escape_string($data->remote_id)."', 
			`data_id`		= ".$data_id.", 
			`time`			= ".$data->time.", 
			`likes`			= ".$data->likes.", 
			`comments`		= ".$data->comments.", 
			`reposts`		= ".$data->reposts.", 
			`images_cnt`	= ".$data->images_cnt.", 
			`gifs_cnt`		= ".$data->gifs_cnt."
		ON DUPLICATE KEY UPDATE
			`data_id`		= VALUES(`data_id`), 
			`time`			= VALUES(`time`), 
			`likes`			= VALUES(`likes`), 
			`comments`		= VALUES(`comments`), 
			`reposts`		= VALUES(`reposts`), 
			`images_cnt`	= VALUES(`images_cnt`), 
			`gifs_cnt`		= VALUES(`gifs_cnt`)
	") or die("MYSQL ERROR 4: ".mysql_error());
	
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

function extract_vk_thumbs($att) {
	$ret = [];
	foreach ($att as $k => $v) {
		if (preg_match('/^photo_(\d+)$/', $k, $m))
			$ret[$m[1]] = $v;
	}
	return $ret;
}
