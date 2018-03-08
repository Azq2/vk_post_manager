<?php
function image_resize($src, $dst, $max) {
	$size = getimagesize($src);
	if (!$size)
		return false;
	
	$type2func = [
		IMAGETYPE_GIF	=> "imagecreatefromgif", 
		IMAGETYPE_JPEG	=> "imagecreatefromjpeg", 
		IMAGETYPE_PNG	=> "imagecreatefrompng", 
		IMAGETYPE_BMP	=> "imagecreatefrombmp"
	];
	if (!isset($type2func[$size[2]]) || !($img = $type2func[$size[2]]($src)))
		return false;
	
	$width = imagesx($img);
	$height = imagesy($img);
	
	if ($width > $max) {
		$new_width = $max;
		$new_height = round($max * ($height / $width));
	} elseif ($height > $max) {
		$new_height = $max;
		$new_width = round($max * ($width / $height));
	} else {
		$ret = copy($src, $dst);
		
		if (!$ret && file_exists($dst))
			@unlink($dst);
		
		return $ret;
	}
	
	$out = imagecreatetruecolor($new_width, $new_height);
	imagealphablending($out, false);
	imagesavealpha($out, true);
	imagecopyresampled($out, $img, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
	
	$ret = imagejpeg($out, $dst, 90);
	imagedestroy($out);
	
	if (!$ret && file_exists($dst))
		@unlink($dst);
	
	return $ret;
}

function vk_api_error($res) {
	if (!$res)
		return "Ошибка подключения к API";
	if (isset($res->error)) {
		if ($res->error->error_code == 5) {
			return "Ошибка авторизации API";
		} else if ($res->error->error_code == 14) {
			$ret->captcha = array(
				'img' => $res->error->captcha_img, 
				'sid' => $res->error->captcha_sid
			);
			return "Нужно ввести капчу";
		}
		return $res->error->error_msg;
	}
	if (!isset($res->response))
		return "Неверный ответ API!";
	return false;
}

function parse_vk_error($res, &$output) {
	if ($res && isset($res->response))
		return true;
	if ($res && isset($res->error)) {
		if ($res->error->error_code == 6) {
			$output['sleep'] = true;
			$output['error'] = $res->error->error_msg;
		} elseif ($res->error->error_code == 14) {
			$output['captcha'] = array(
				'url' => $res->error->captcha_img, 
				'sid' => $res->error->captcha_sid
			);
		} else {
			$output['error'] = $res->error->error_msg;
		}
	} elseif (!$res || !isset($res->response)) {
		$output['error'] = "VK API недоступен. ".json_encode($res);
	}
	return false;
}

function mk_ajax($data) {
//	$out = ob_get_clean();
	header("Content-Type: application/json; charset=UTF-8");
//	$data['stdout'] = $out;
	echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

function pics_uploader(&$out, $q, $gid, $images, $progress = false) {
	$upload_photos = $q->vkApi("photos.getWallUploadServer", array(
		'group_id' => $gid
	));
	$upload_docs = $q->vkApi("docs.getWallUploadServer", array(
		// 'group_id' => $gid
	));
	if (($error = vk_api_error($upload_photos)) || ($error = vk_api_error($upload_docs))) {
		$out['error'] = $error;
	} else if (!isset($upload_photos->response->upload_url) || !isset($upload_docs->response->upload_url)) {
		$out['error'] = "upload_url не найден :(";
	} else {
		if (!$images)
			$out['error'] = "А где же картинки?!??!";
		
		$attachments = [];
		foreach ($images as $i => $img) {
			if ($i)
				usleep(1);
			
			$is_doc = isset($img['document']) && $img['document'];
			
			$upload_raw = $q->vkApiUpload($is_doc ? $upload_docs->response->upload_url : $upload_photos->response->upload_url, [
				['path' => $img['path'], 'name' => $is_doc ? "image.gif" : "image.jpg", 'key' => $is_doc ? 'file' : 'photo']
			])->body;
			$upload = @json_decode($upload_raw);
			
			if (!$upload_raw) {
				$out['error'] = "Ошибка подключения к UPLOAD серверу при загрузке ".($is_doc ? 'документа' : 'фото').
					" #$i! (path: ".$img['path'].", server: ".$res->response->upload_url.")";
				break;
			} else if (!$upload) {
				$out['error'] = '<pre>'.htmlspecialchars($upload_raw).'</pre>';
				break;
			} else if (isset($upload->error)) {
				$out['error'] = "UPLOAD (gid=$gid) ".$upload->error;
				break;
			} else {
				while (true) {
					if ($is_doc) {
						$file = $q->vkApi("docs.save", array(
							'file'			=> $upload->file, 
							'title'			=> isset($img['title']) ? $img['title'] : "", 
							'tags'			=> isset($img['tags']) ? $img['tags'] : "", 
						));
					} else {
						$file = $q->vkApi("photos.saveWallPhoto", array(
							'group_id'		=> $gid, 
							'photo'			=> stripcslashes($upload->photo), 
							'server'		=> $upload->server, 
							'hash'			=> $upload->hash, 
							'caption'		=> isset($img['caption']) ? $img['caption'] : ""
						));
					}
					if (($error = vk_api_error($file))) {
						if ($file->error->error_code == 6) {
							sleep(3);
							continue;
						}
						$out['error'] = "Ошибка сохранения ".($is_doc ? 'документа' : 'фото')." #$i в стене!! (".$error.")";
						break;
					} elseif (!isset($file->response) || !$file->response) {
						$out['error'] = "Ошибка сохранения ".($is_doc ? 'документа' : 'фото')." #$i в стене!!! $upload_raw";
						break;
					} else {
						$att = $is_doc ? 
							'doc'.$file->response[0]->owner_id.'_'.$file->response[0]->id : 
							'photo'.$file->response[0]->owner_id.'_'.$file->response[0]->id;
						$attachments[] = $att;
						
						if ($progress)
							$progress($att, $file->response[0]);
					}
					break;
				}
				if (isset($out['error']))
					break;
			}
		}
	}
	
	return $attachments;
}

function define_oauth() {
	$types = ['VK' => 1, 'OK' => 1, 'VK_SCHED' => 1];
	
	$req = Mysql::query("SELECT * FROM `vk_oauth`");
	while ($res = $req->fetch()) {
		if (isset($types[$res['type']]))
			define($res['type'].'_USER_ACCESS_TOKEN', $res['access_token']);
	}
	
	foreach ($types as $type => $_) {
		if (!defined($type.'_USER_ACCESS_TOKEN'))
			define($type.'_USER_ACCESS_TOKEN', '');
	}
}

function switch_tabs($args) {
	$args = array_merge([
		'tabs'		=> [], 
		'url'		=> '?', 
		'param'		=> 'tab', 
		'active'	=> NULL
	], $args);
	
	if (is_null($args['active']))
		$args['active'] = isset($_GET[$args['param']]) ? $_GET[$args['param']] : "";
	
	$tabs = array();
	$url = Url::mk($args['url']);
	foreach ($args['tabs'] as $k => $title) {
		$tabs[] = array(
			'url'		=> $url->set($args['param'], $k)->url(), 
			'title'		=> $title, 
			'active'	=> $args['active'] == $k, 
			'last'		=> false
		);
	}
	$tabs[count($tabs) - 1]['last'] = true;
	
	return Tpl::render("widgets/tabs.html", array(
		'tabs' => $tabs
	));
}

function get_day_start($time = 0) {
	$time = !$time ? time() : $time;
	return mktime(0, 0, 0, date("m", $time), date("d", $time), date("Y", $time));
}

function array_val($array, $key, $val = NULL) {
	return array_key_exists($key, $array) ? $array[$key] : $val;
}

function e($s) {
	return Mysql::escape($s);
}

function request($ch, $url, $data = NULL) {
	curl_setopt($ch, CURLOPT_URL, $url);
	if ($data !== NULL) {
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	}
	$res = curl_exec($ch);
	curl_setopt($ch, CURLOPT_POST, false);
	return $res;
}

function display_date($time_unix, $full = false, $show_time = true) {
	static $to_russian_week = [6, 0, 1, 2, 3, 4, 5]; 
	static $week_names = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс']; 
	static $month_list = [
		1  => 'Января', 
		2  => 'Февраля', 
		3  => 'Марта', 
		4  => 'Апреля', 
		5  => 'Мая', 
		6  => 'Июня', 
		7  => 'Июля', 
		8  => 'Августа', 
		9  => 'Сентября', 
		10 => 'Октября', 
		11 => 'Ноября', 
		12 => 'Декабря', 
		13 => 'Мартабря', 
	];
	static $month_list_short = [
		1  => 'янв', 
		2  => 'фев', 
		3  => 'мар', 
		4  => 'апр', 
		5  => 'мая', 
		6  => 'июн', 
		7  => 'июл', 
		8  => 'авг', 
		9  => 'сен', 
		10 => 'окт', 
		11 => 'ноя', 
		12 => 'дек', 
	];
	
	$curr_time = localtime(time()); 
	$time = localtime($time_unix); 
	
	if ($full)
		return date("d ".$month_list[$time[4] + 1]." Y в H:i:s", $time_unix); 
	
	// Сегодня
	if ($time[3] == $curr_time[3] && $time[4] == $curr_time[4] && $time[5] == $curr_time[5])
		return $show_time ? date("H:i:s", $time_unix) : "сегодня";
	
	if (time() >= $time_unix) {
		// Вчера
		$yesterday = mktime(0, 0, 0, $curr_time[4] + 1, $curr_time[3] - 1, 1900 + $curr_time[5]); 
		if ($yesterday <= $time_unix)
			return "вчера".($show_time ? " ".date("H:i:s", $time_unix) : "");
		
		// На этой неделе
		$start_week = mktime(0, 0, 0, $curr_time[4] + 1, $curr_time[3] - $to_russian_week[$curr_time[6]], 1900 + $curr_time[5]); 
		if ($start_week <= $time_unix)
			return $week_names[$to_russian_week[$time[6]]].($show_time ? ", ".date("H:i:s", $time_unix) : "");
	}
	
	// В этом году
	if ($curr_time[5] == $time[5])
		return $time[3]." ".$month_list_short[$time[4] + 1].($show_time ? " ".date("H:i:s", $time_unix) : "");
	
	// Хрен знает когда
	return $time[3]." ".$month_list_short[$time[4] + 1]." ".(1900 + $time[5]); 
}

function count_time($time) {
	$out = [];
	
	$years = floor($time / (3600 * 24 * 365));
	if ($years > 0) {
		$time -= $years * 3600 * 24 * 365; 
		$out[] = $years." год"; 
	}
	
	$months = floor($time / (3600 * 24 * 30));
	if ($months > 0) {
		$time -= $months * 3600 * 24 * 30; 
		$out[] = $months." мес"; 
	}
	
	$days = floor($time / (3600 * 24));
	if ($days > 0) {
		$time -= $days * 3600 * 24; 
		$out[] = $days." дн"; 
	}
	
	$hours = floor($time / 3600); 
	if ($hours > 0 || $days > 0) {
		$time -= $hours * 3600; 
		$out[] = $hours." ч"; 
	}
	
	$minutes = floor($time / 60); 
	if ($minutes > 0 || $hours > 0 || $days > 0) {
		$time -= $minutes * 60; 
		$out[] = $minutes." м"; 
	}
	
	if (empty($out) || $time > 0) {
		$seconds = $time; 
		$out[] = $seconds."с"; 
	}
	return implode(', ', array_slice($out, 0, 2)); 
}

function count_delta($time) {
	$out = [];
	
	$years = floor($time / (3600 * 24 * 365));
	$time -= $years * 3600 * 24 * 365;
	
	$months = floor($time / (3600 * 24 * 30));
	$time -= $months * 3600 * 24 * 30; 
	
	$days = floor($time / (3600 * 24));
	$time -= $days * 3600 * 24; 
	
	$hours = floor($time / 3600); 
	$time -= $hours * 3600;
	
	$minutes = floor($time / 60); 
	$time -= $minutes * 60;
	
	$seconds = $time; 
	
	if ($years > 0)
		$out[] = $years." год";
	
	if ($months > 0)
		$out[] = $months." мес.";
	
	if ($days > 0)
		$out[] = $days." день";
	
	if ($hours > 0 || $days > 0 || $minutes > 0) {
		$out[] = sprintf("%02d:%02d", $hours, $minutes);
	}
	
	if (!$out)
		$out[] = sprintf("%02d:%02d:%02d", 0, 0, $seconds);
	
	return implode(" ", $out); 
}

function vk_extract_thumbs($att) {
	$ret = [];
	foreach ($att as $k => $v) {
		if (preg_match('/^photo_(\d+)$/', $k, $m))
			$ret[$m[1]] = $v;
	}
	return $ret;
}

function vk_normalize_attaches($item) {
	$attaches = [];
	$images_cnt = 0;
	$gifs_cnt = 0;
	
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
				
				$thumbs = vk_extract_thumbs($att);
				
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
					'thumbs' => vk_extract_thumbs($att), 
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
					'thumbs' => vk_extract_thumbs($att->thumb), 
					'url' => 'https://vk.com/'.$att->type.$att->owner_id.'_'.$att->id
				];
			} else if ($att->type == 'market_album') {
				$attaches[] = [
					'id' => $att->type.$att->owner_id.'_'.$att->id, 
					'type' => 'market_album', 
					'w' => $att->photo->width, 
					'h' => $att->photo->height, 
					'title' => $att->title, 
					'thumbs' => vk_extract_thumbs($att->photo)
				];
			} else if ($att->type == 'app') {
				$attaches[] = [
					'id' => $att->type.$att->owner_id.'_'.$att->id, 
					'thumbs' => vk_extract_thumbs($att->thumb)
				];
			} else if ($att->type == 'note') {
				$attaches[] = [
					'id' => $att->type.$att->user_id.'_'.$att->id, 
					'title' => $att->title, 
					'text' => $att->text, 
					'thumbs' => vk_extract_thumbs($att->thumb)
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
	
	return (object) [
		'attaches'	=> $attaches, 
		'gifs'		=> $gifs_cnt, 
		'images'	=> $images_cnt
	];
}

function vk($method, $args = array()) {
	static $ch;
	
	if (!$ch) {
		$ch = curl_init(); 
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => true, 
			CURLOPT_ENCODING       => "gzip", 
			CURLOPT_COOKIE         => '', 
			CURLOPT_HEADER         => false, 
			CURLOPT_USERAGENT      => 'Ня :3', 
			CURLOPT_SSL_VERIFYPEER => true, 
			CURLOPT_FORBID_REUSE   => false
		));
	}
	
	$sig = '';
	$args['v'] = '5.33';
	$args['lang'] = 'ru';
	curl_setopt($ch, CURLOPT_URL, "https://api.vk.com/method/".$method);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($args, '', '&'));
	return json_decode(curl_exec($ch));
}

function create_dom($res) {
	$doc = new DOMDocument('1.0', 'UTF-8');
	$doc->strictErrorChecking = false;
	$doc->encoding = 'UTF-8';
	@$doc->loadHTML('<?xml version="1.1" encoding="UTF-8" ?>'.$res);
	$xpath = new DOMXPath($doc);
	foreach ($xpath->query('//comment()') as $comment)
		$comment->parentNode->removeChild($comment);
	$scripts = $doc->getElementsByTagName('script');
	foreach ($scripts as $script)
		$script->parentNode->removeChild($script);
	$styles = $doc->getElementsByTagName('style');
	foreach ($styles as $style)
		$style->parentNode->removeChild($style);
	return $doc;
}
