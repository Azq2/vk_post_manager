<?php
namespace Smm\VK;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;

class Posts {
	public static function getWallPostMeta($post) {
		$out = [
			'lat'			=> 0, 
			'long'			=> 0, 
			'attachments'	=> [], 
			'post_id'		=> $post->id, 
			'owner_id'		=> $post->owner_id, 
			'post_type'		=> $post->post_type, 
			'publish_date'	=> $post->publish_date ?? 0, 
			'message'		=> $post->text, 
			'signed'		=> $post->signer_id ?? 0
		];
		
		if (isset($post->attachments)) {
			foreach ($post->attachments as $att) {
				if ($att->type == 'link') {
					$out['attachments'][] = $att->link->url;
				} else {
					$att_data = $att->{$att->type};
					if (isset($att_data->owner_id, $att_data->id)) {
						$out['attachments'][] = $att->type.$att_data->owner_id."_".$att_data->id;
					} else {
						// Странный аттач
						return false;
					}
				}
			}
		}
		
		if (isset($post->geo)) {
			if (preg_match("/^([\d\.-]+) ([\d\.-]+)$/", $post->geo->coordinates, $geo)) {
				$out['lat'] = $geo[1];
				$out['long'] = $geo[2];
			} else {
				// Странные гео
				return false;
			}
		}
		
		return $out;
	}
	
	public static function getFakeDate($gid) {
		$fake_date = DB::select(['MAX(`fake_date`)', 'fake_date'])
			->from('vk_posts_queue')
			->where('group_id', '=', $gid)
			->execute()
			->get('fake_date', 0);
		return max(time() + 3600 * 24 * 60, $fake_date) + 3600;
	}
	
	public static function uploadPics(\Smm\VK\API $api, $gid, $images, $progress = false) {
		$result = (object) [
			'success'		=> false, 
			'captcha'		=> false, 
			'error'			=> false, 
			'attachments'	=> []
		];
		
		if (!$images) {
			$result->error = "А где же картинки?!??!";
			return $result;
		}
		
		$data = ['group_id' => $gid, '_' => microtime(true)];
		
		if (($captcha_code = Captcha::getCode())) {
			$data['captcha_key'] = $captcha_code['key'];
			$data['captcha_sid'] = $captcha_code['sid'];
		}
		
		$upload_photos = $api->exec("photos.getWallUploadServer", $data);
		
		usleep(100000);
		
		if (!$upload_photos->success()) {
			$result->error = $upload_photos->error();
			$result->captcha = $upload_photos->captcha();
			Captcha::set($upload_photos->captcha());
			return $result;
		}
		
		$data = [
			// 'group_id' => $gid, 
			'_' => microtime(true)
		];
		
		if (($captcha_code = Captcha::getCode())) {
			$data['captcha_key'] = $captcha_code['key'];
			$data['captcha_sid'] = $captcha_code['sid'];
		}
		
		$upload_docs = $api->exec("docs.getWallUploadServer", $data);
		
		usleep(100000);
		
		if (!$upload_docs->success()) {
			$result->error = $upload_docs->error();
			$result->captcha = $upload_docs->captcha();
			Captcha::set($upload_docs->captcha());
			return $result;
		}
		
		if (!isset($upload_photos->response->upload_url) || !isset($upload_docs->response->upload_url)) {
			$result->error = "Параметр upload_url не найден в ответе VK API!";
			return $result;
		}
		
		$attachments = [];
		foreach ($images as $i => $img) {
			if ($i)
				usleep(100000);
			
			$is_doc = isset($img['document']) && $img['document'];
			$upload = false;
			
			// Загружаем на сервера VK
			for ($i = 0; $i < 3; ++$i) {
				$upload_raw = $api->upload($is_doc ? $upload_docs->response->upload_url : $upload_photos->response->upload_url, [
					['path' => $img['path'], 'name' => $is_doc ? "image.gif" : "image.jpg", 'key' => $is_doc ? 'file' : 'photo']
				]);
				$upload = @json_decode($upload_raw->body);
				
				if ($upload_raw->code != 200) {
					$result->error = "Ошибка подключения к UPLOAD серверу при загрузке ".($is_doc ? 'документа' : 'фото').
						" #$i! (path: ".$img['path'].", server: ".$res->response->upload_url.", code: ".$upload_raw->code.")";
				} else if (!$upload) {
					$result->error = 'UPLOAD ответил ерундой: <pre>'.htmlspecialchars($upload_raw).'</pre>';
				} else if (isset($upload->error)) {
					$result->error = "UPLOAD (gid=$gid) ".$upload->error;
				} else {
					$result->error = false;
					break;
				}
			}
			
			if ($result->error)
				return $result;
			
			for ($i = 0; $i < 3; ++$i) {
				// Сохраняем загруженный файл
				if ($is_doc) {
					$method = "docs.save";
					$data = [
						'file'			=> $upload->file, 
						'title'			=> isset($img['title']) ? $img['title'] : "", 
						'tags'			=> isset($img['tags']) ? $img['tags'] : "", 
					];
				} else {
					$method = "photos.saveWallPhoto";
					$data = [
						'group_id'		=> $gid, 
						'photo'			=> $upload->photo, 
						'server'		=> $upload->server, 
						'hash'			=> $upload->hash, 
						'caption'		=> isset($img['caption']) ? $img['caption'] : ""
					];
				}
				
				if (($captcha_code = Captcha::getCode())) {
					$data['captcha_key'] = $captcha_code['key'];
					$data['captcha_sid'] = $captcha_code['sid'];
				}
				
				$file = $api->exec($method, $data);
				
				if ($file->success()) {
					$att = $is_doc ? 
						'doc'.$file->response->doc->owner_id.'_'.$file->response->doc->id : 
						'photo'.$file->response[0]->owner_id.'_'.$file->response[0]->id;
					
					$attachments[$att] = $is_doc ? $file->response->doc : $file->response[0];
					
					if ($progress)
						$progress($att, $attachments[$att]);
					
					$result->error = false;
					break;
				} else {
					$result->error = $file->error();
					$result->captcha = $file->captcha();
					Captcha::set($file->captcha());
					
					if ($file->errorCode() == \Smm\VK\API\Response::VK_ERR_TOO_FAST)
						sleep(3);
				}
			}
			
			if ($result->error)
				return $result;
		}
		
		$result->success = true;
		$result->attachments = $attachments;
		
		return $result;
	}
	
	public static function getAll(\Smm\VK\API $api, $group_id) {
		$settings = DB::select()
			->from('vk_groups')
			->where('id', '=', $group_id)
			->execute()
			->current();
		
		$result = (object) [
			'success'		=> true, 
			'captcha'		=> false, 
			'error'			=> false, 
			'postponed'		=> [], 
			'postponed_cnt'	=> 0, 
			'suggests'		=> [], 
			'suggests_cnt'	=> 0, 
			'specials'		=> [], 
			'users'			=> [], 
		];
		
		$code = '
			var MAX_API_CNT		= 25;
			var GROUP_ID		= -'.$group_id.';
			
			var postponed_offset = 0;
			var suggests_offset = 0;
			var postponed_total = -1;
			var suggests_total = -1;
			
			var api_cnt = 0;
			
			var results = [];
			
			var while_cond = true;
			while (while_cond) {
				var loaded = false;
				
				if (postponed_total == -1 || postponed_offset < postponed_total) {
					var postponed = API.wall.get({
						owner_id:	GROUP_ID, 
						filter:		"postponed", 
						extended:	1, 
						count:		100, 
						offset:		postponed_offset
					});
					results.push(postponed);
					postponed_total = postponed.count;
					postponed_offset = postponed_offset + postponed.items.length;
					api_cnt = api_cnt + 1;
					loaded = true;
				}
				
				if (suggests_total == -1 || suggests_offset < suggests_total) {
					var suggests = API.wall.get({
						owner_id:	GROUP_ID, 
						filter:		"suggests", 
						extended:	1, 
						count:		100, 
						offset:		suggests_offset
					});
					results.push(suggests);
					suggests_total = suggests.count;
					suggests_offset = suggests_offset + suggests.items.length;
					api_cnt = api_cnt + 1;
					loaded = true;
				}
				
				if (MAX_API_CNT - api_cnt < 4 || !loaded)
					while_cond = false;
			}
			
			var last_comments = API.wall.get({
				owner_id:	GROUP_ID, 
				filter:		"all", 
				extended:	1, 
				count:		3, 
				offset:		0
			});
			
			results.push(last_comments);
			
			var load_user_ids = [];
			
			return {
				results:	results, 
				profiles:	load_user_ids.length ? API.users.get({"user_ids": load_user_ids, fields: "photo_50"}) : []
			};
		';
		
		$out = false;
		
		for ($i = 0; $i < 3; ++$i) {
			$out = $api->exec("execute", array('code' => $code));
			
			if ($out->success())
				break;
			
			if ($out->captcha()) {
				$result->success = false;
				$result->captcha = $out->captcha();
				Captcha::set($out->captcha());
				return $result;
			}
			
			sleep(1);
		}
		
		if (!$out->success()) {
			$result->success = false;
			$result->error = $out->error();
			return $result;
		}
		
		$users = [];
		$items = [];
		$published_items = [];
		foreach ($out->response->results as $chunk) {
			if (isset($chunk->items)) {
				foreach ($chunk->items as $item) {
					if (!in_array($item->post_type, ['postpone', 'suggest'])) {
						$published_items[$item->owner_id.":".$item->id] = $item;
					} else {
						$items[$item->id] = $item;
					}
				}
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
		
		foreach ($out->response->profiles as $u)
			$users[$u->id] = $u;
		
		usort($published_items, function ($a, $b) {
			return $b->date <=> $a->date;
		});
		
		if ($published_items)
			$items[$published_items[0]->id] = $published_items[0];
		
		$items = array_values($items);
		
		$postponed = [];
		$suggests = [];
		$specials = [];
		
		$max_date = 0;
		
		$queue = self::getQueue($group_id);
		$first_comments = DB::select()
			->from('vk_posts_comments')
			->where('group_id', '=', $group_id)
			->execute()
			->asArray('id', 'text');
		
		foreach ($items as $post) {
			$post->special = false;
			if (isset($post->marked_as_ads) && $post->marked_as_ads) {
				$post->special = true;
				$specials[] = $post;
			}
			
			$post->comment_text = $first_comments[$post->id] ?? '';
			$post->orig_date = $post->date;
			
			if (isset($queue[$post->id]) || $post->special || !in_array($post->post_type, ['postpone', 'suggest'])) {
				// Отложенный
				if (in_array($post->post_type, ['postpone', 'suggest']) && !$post->special) {
					$post->date = time() + (100 * 24 * 3600) + (24 * 3600 * $queue[$post->id]['n']); // Костыли для сортировки
				}
				$postponed[$post->id] = $post;
			} else {
				// Предложка
				$suggests[$post->id] = $post;
			}
			
			$max_date = max($max_date, $post->date);
		}
		
		$postponed['__NEXT__'] = (object) [
			'date'			=> $max_date + 10, 
			'special'		=> false, 
			'post_type'		=> 'postpone', 
			'id'			=> '__NEXT__'
		];
		
		$postponed = array_values($postponed);
		$suggests = array_values($suggests);
		
		$postponed = self::processQueue($postponed, $settings);
		
		$new_postponed = [];
		foreach ($postponed as $post) {
			if ($post->id == '__NEXT__') {
				\Smm\Globals::set($group_id, "next_post_date", $post->date);
			} else {
				$new_postponed[] = $post;
			}
		}
		$postponed = $new_postponed;
		
		usort($suggests, function ($a, $b) {
			if ($a->post_type != $b->post_type) {
				if ($a->post_type == 'postpone')
					return -1;
				if ($b->post_type == 'postpone')
					return 1;
			}
			
			return $a->date <=> $b->date;
		});
		
		usort($specials, function ($a, $b) {
			return $a->date <=> $b->date;
		});
		
		$result->postponed		= $postponed;
		$result->postponed_cnt	= $published_items ? count($postponed) - 1 : count($postponed);
		$result->suggests		= $suggests;
		$result->suggests_cnt	= count($suggests);
		$result->specials		= $specials;
		$result->users			= $users;
		
		return $result;
	}
	
	public static function extractThumbs($att) {
		$ret = (object) [
			'thumbs'		=> [], 
			'width'			=> 0, 
			'height'		=> 0
		];
		if (isset($att->sizes)) {
			foreach ($att->sizes as $sz) {
				$ret->thumbs[$sz->width] = $sz->url;
				
				if ($ret->width < $sz->width || $ret->height < $sz->height) {
					$ret->width = $sz->width;
					$ret->height = $sz->height;
				}
			}
		} else {
			$last = false;
			foreach ($att as $k => $v) {
				if (preg_match('/^photo_(\d+)$/', $k, $m)) {
					$ret->thumbs[$m[1]] = $v;
					// $last = $v;
				}
			}
			
			if (isset($att->width)) {
				$ret->width = $att->width;
				$ret->height = $att->height;
			} else if ($last) {
				list ($ret->width, $ret->height) = getimagesize($last);
			}
		}
		
		ksort($ret->thumbs);
		return $ret;
	}
	
	public static function normalizeAttaches($item) {
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
					
					$thumbs_info = self::extractThumbs($att);
					
					if (!$thumbs_info->thumbs) {
						$attaches[] = [
							'id'			=> 'unknown_'.count($attaches), 
							'type'			=> 'unknown', 
							'data'			=> json_encode($att)
						];
					} else {
						$attaches[] = [
							'id'		=> $att->type.$att->owner_id.'_'.$att->id, 
							'type'		=> 'photo', 
							'w'			=> $thumbs_info->width, 
							'h'			=> $thumbs_info->height, 
							'thumbs'	=> $thumbs_info->thumbs 
						];
					}
				} else if ($att->type == 'video') {
					$thumbs_info = self::extractThumbs($att);
					
					$attaches[] = [
						'id'			=> $att->type.$att->owner_id.'_'.$att->id, 
						'type'			=> 'video', 
						'w'				=> isset($att->width) ? $att->width : 0, 
						'h'				=> isset($att->height) ? $att->height : 0, 
						'title'			=> $att->title, 
						'description'	=> $att->description, 
						'thumbs'		=> $thumbs_info->thumbs, 
						'url'			=> "https://vk.com/".$att->type.$att->owner_id.'_'.$att->id
					];
				} else if ($att->type == 'album') {
					$thumbs_info = self::extractThumbs($att->thumb);
					
					$attaches[] = [
						'id'			=> $att->type.$att->owner_id.'_'.$att->id, 
						'type'			=> 'album', 
						'w'				=> $thumbs_info->width, 
						'h'				=> $thumbs_info->height, 
						'title'			=> $att->title, 
						'description'	=> $att->description, 
						'thumbs'		=> $thumbs_info->thumbs, 
						'url'			=> 'https://vk.com/'.$att->type.$att->owner_id.'_'.$att->id
					];
				} else if ($att->type == 'market_album') {
					$thumbs_info = self::extractThumbs($att->photo);
					
					$attaches[] = [
						'id'		=> $att->type.$att->owner_id.'_'.$att->id, 
						'type'		=> 'market_album', 
						'w'			=> $thumbs_info->width, 
						'h'			=> $thumbs_info->height, 
						'title'		=> $att->title, 
						'thumbs'	=> $thumbs_info->thumbs
					];
				} else if ($att->type == 'app') {
					$thumbs_info = self::extractThumbs($att->thumb);
					
					$attaches[] = [
						'id'		=> $att->type.$att->owner_id.'_'.$att->id, 
						'thumbs'	=> $thumbs_info->thumbs
					];
				} else if ($att->type == 'note') {
					$thumbs_info = self::extractThumbs($att->thumb);
					
					$attaches[] = [
						'id'		=> $att->type.$att->user_id.'_'.$att->id, 
						'title'		=> $att->title, 
						'text'		=> $att->text, 
						'thumbs'	=> $thumbs_info->thumbs
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
						'id'		=> $att->type.$att->owner_id.'_'.$att->id, 
						'type'		=> 'doc', 
						'ext'		=> $att->ext, 
						'title'		=> $att->title, 
						'w'			=> $width, 
						'h'			=> $height, 
						'thumbs'	=> $thumbs, 
						'url'		=> $att->url, 
						'mp4'		=> $mp4, 
						'page_url'	=> "https://vk.com/".$att->type.$att->owner_id.'_'.$att->id
					];
				} else if ($att->type == 'page') {
					$attaches[] = [
						'id'		=> $att->type.'-'.$att->group_id.'_'.$att->id, 
						'type'		=> 'page', 
						'title'		=> $att->title, 
						'url'		=> $att->view_url
					];
				} else if ($att->type == 'audio') {
					$attaches[] = [
						'id'		=> $att->type.$att->owner_id.'_'.$att->id, 
						'type'		=> 'audio', 
						'title'		=> $att->artist.' - '.$att->title
					];
				} else if ($att->type == 'poll') {
					$answers = [];
					foreach ($att->answers as $a)
						$answers[] = $a->text;
					
					$attaches[] = [
						'id'		=> $att->type.$att->owner_id.'_'.$att->id, 
						'type'		=> 'poll', 
						'question'	=> $att->question, 
						'answers'	=> $answers, 
						'anon'		=> $att->anonymous
					];
				} else if ($att->type == 'link') {
					$attaches[] = [
						'id'			=> 'link_'.md5($att->url), 
						'type'			=> 'link', 
						'url'			=> $att->url, 
						'title'			=> $att->title, 
						'description'	=> $att->description
					];
				} else if ($att->type == 'market') {
					list ($width, $height) = getimagesize($att->thumb_photo);
					
					$attaches[] = [
						'id'			=> $att->type.$att->owner_id.'_'.$att->id, 
						'type'			=> 'market', 
						'price'			=> $att->price->text, 
						'title'			=> $att->title, 
						'description'	=> $att->description, 
						'thumbs' => [
							$width => $att->thumb_photo
						], 
						'w'				=> $width, 
						'h'				=> $height, 
						'url'			=> "https://vk.com/".$att->type.$att->owner_id.'_'.$att->id
					];
				} else {
					$attaches[] = [
						'id'			=> 'unknown_'.count($attaches), 
						'type'			=> 'unknown', 
						'data'			=> json_encode($att)
					];
				}
			}
		}
		
		return (object) [
			'attaches'	=> $attaches, 
			'gifs'		=> $gifs_cnt, 
			'images'	=> $images_cnt
		];
	}
	
	protected static function getQueue($group_id) {
		$queue = DB::select()
			->from('vk_posts_queue')
			->where('group_id', '=', $group_id)
			->order('nid', 'ASC')
			->execute()
			->asArray('id');
		
		$i = 0;
		foreach ($queue as $id => $q)
			$queue[$id]['n'] = $i++;
		
		return $queue;
	}
	
	public static function deviatePostDate($id, $post_time, $dir, $settings) {
		if (!$settings['deviation'] || $id == "__NEXT__")
			return $post_time;
		
		$deviation_minutes = round($settings['deviation'] / 60);
		
		switch ($dir) {
			case 0:
				$deviation = $deviation_minutes - self::pseudoRand($id, 0, $deviation_minutes * 2);
			break;
			
			case 1:
				$deviation = self::pseudoRand($id, 0, $deviation_minutes);
			break;
			
			case -1:
				$deviation = -self::pseudoRand($id, 0, $deviation_minutes);
			break;
		}
		
		return $post_time + ($deviation * 60);
	}
	
	public static function roundPostDate($post_time, $settings) {
		$day_start = Date::getDayStart($post_time);
		$period_start = $day_start + $settings['period_from'];
		$period_end = $day_start + $settings['period_to'];
		
		$post_time = round($post_time / 60) * 60;
		
		if ($period_end < $period_start) {
			$period_end += 24 * 3600;
			
			if (($day_start + $settings['period_to']) - $post_time >= 60) {
				// Ещё активен предыдущий период
				$period_start -= 24 * 3600;
				$period_end -= 24 * 3600;
			}
		}
		
		// Если раньше начала периода - сдвигаем вперёд на начало
		if ($post_time < $period_start)
			$post_time = $period_start;
		
		// Если не влезло в текущий период - сдвигаем на начало следующего
		if ($post_time - $period_end > 60)
			$post_time = $period_start + 3600 * 24;
		
		return $post_time;
	}
	
	public static function processQueue($posts, $settings) {
		// Разделяем посты на:
		// 1. special	- с фиксированным временем, которое нельзя менять
		// 2. normal	- обычные посты, которые можно двигать во времени
		$normal_posts = [];
		$special_posts = [];
		
		foreach ($posts as $post) {
			if ($post->special) {
				$special_posts[] = $post;
			} else {
				$normal_posts[] = $post;
			}
		}
		
		// Сортируем по ASC
		usort($special_posts, function ($a, $b) {
			return $a->date <=> $b->date;
		});
		usort($normal_posts, function ($a, $b) {
			return $a->date <=> $b->date;
		});
		
		$new_posts = [];
		$prev_post_date = time() - $settings['interval'];
		
		foreach ($normal_posts as $post) {
			// Ранее опубликованный пост
			if (!in_array($post->post_type, ['postpone', 'suggest'])) {
				$prev_post_date = $post->date;
				$new_posts[] = $post;
				continue;
			}
			
			// Рассчитываем дату следующего поста
			$post->date = self::roundPostDate($prev_post_date + $settings['interval'], $settings);
			$post->date = self::deviatePostDate($post->id, $post->date, 0, $settings);
			
			foreach ($special_posts as $special_post) {
				$delta = $special_post->date - $post->date;
				// Если задеваем специальный пост, то переносит после него + special_post_after
				if (($delta >= 0 && $delta < $settings['special_post_before']) || ($delta < 0 && abs($delta) < $settings['special_post_after'])) {
					$post->date = self::roundPostDate($special_post->date + $settings['special_post_after'], $settings);
					
					// Если между постом и рекламных постом достаточно времени для девиации, то разрешаем отклонять время поста в меньшую сторону
					if (($post->date - ($special_post->date + $settings['special_post_after'])) > $settings['deviation']) {
						$post->date = self::deviatePostDate($post->id, $post->date, 0, $settings);
					}
					// Иначе разрешаем отклонять время поста только в большую сторону
					else {
						$post->date = self::deviatePostDate($post->id, $post->date, 1, $settings);
					}
				}
			}
			
			$prev_post_date = $post->date;
			$new_posts[] = $post;
		}
		
		// Подмешиваем обратно special-посты
		foreach ($special_posts as $special_post)
			$new_posts[] = $special_post;
		
		// И заново сортриум по ASC
		usort($new_posts, function ($a, $b) {
			return $a->date <=> $b->date;
		});
		
		return $new_posts;
	}
	
	public static function analyzeComment($comment) {
		$images_cnt = 0;
		$stickers_cnt = 0;
		
		if (!isset($comment->attachments))
			$comment->attachments = [];
		
		foreach ($comment->attachments as $att) {
			if (in_array($att->type, ['photo', 'posted_photo', 'graffiti'])) {
				++$images_cnt;
			} else if ($att->type == 'sticker') {
				++$stickers_cnt;
			} else if ($att->type == 'doc') {
				// 3 — gif;
				// 4 — изображения;
				if ($att->doc->type == 3 || $att->doc->type == 4)
					++$images_cnt;
			}
		}
		
		return [
			'images_cnt'		=> $images_cnt, 
			'stickers_cnt'		=> $stickers_cnt, 
			'attaches_cnt'		=> count($comment->attachments), 
			'text_length'		=> mb_strlen(trim($comment->text))
		];
	}
	
	public static function pseudoRand($val, $min, $max) {
		$md5 = md5("prng:$min-$max-$val");
		$n = hexdec(substr($md5, 10, 4)) % 25;
		$int31 = (hexdec(substr($md5, $n, 8))) & 0x7fffffff;
		return floor($min + (($max - $min + 1) * ($int31 / 0x80000000)));
	}
}
