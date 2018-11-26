<?php
namespace Z\Smm\VK;

use \Z\Core\DB;
use \Z\Core\View;
use \Z\Core\Date;
use \Z\Core\Util\Url;
use \Z\Core\Net\VkApi;

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
	
	public static function queueWallPost(VkApi $api, $gid, $attachments, $text) {
		$result = (object) [
			'success'		=> false, 
			'captcha'		=> false, 
			'error'			=> false
		];
		
		// Фейковая дата поста
		$fake_date = self::getFakeDate($gid);
		
		$data = [
			'owner_id'		=> -$gid, 
			'signed'		=> 0, 
			'message'		=> $text, 
			'attachments'	=> implode(",", $attachments), 
			'publish_date'	=> $fake_date
		];
		
		if (($captcha_code = Captcha::getCode())) {
			$data['captcha_key'] = $captcha_code['key'];
			$data['captcha_sid'] = $captcha_code['sid'];
		}
		
		$res = $api->exec("wall.post", $data);
		if ($res->success()) {
			$result->success = true;
			$result->post = $res->response;
			
			DB::insert('vk_posts_queue')
				->set([
					'fake_date'		=> $fake_date, 
					'group_id'		=> $gid, 
					'id'			=> $res->response->post_id
				])
				->onDuplicateSetValues('fake_date')
				->execute();
			
			return $result;
		} else {
			$result->error = $res->error();
			$result->captcha = $res->captcha();
			Captcha::set($result->captcha());
		}
		
		return $out;
	}
	
	public static function uploadPics(VkApi $api, $gid, $images, $progress = false) {
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
		
		$data = ['group_id' => $gid];
		
		if (($captcha_code = Captcha::getCode())) {
			$data['captcha_key'] = $captcha_code['key'];
			$data['captcha_sid'] = $captcha_code['sid'];
		}
		
		$upload_photos = $api->exec("photos.getWallUploadServer", $data);
		
		if (!$upload_photos->success()) {
			$result->error = $upload_photos->success();
			$result->captcha = $upload_photos->captcha();
			Captcha::set($upload_photos->captcha());
			return $result;
		}
		
		$data = [
			// 'group_id' => $gid
		];
		
		if (($captcha_code = Captcha::getCode())) {
			$data['captcha_key'] = $captcha_code['key'];
			$data['captcha_sid'] = $captcha_code['sid'];
		}
		
		$upload_docs = $api->exec("docs.getWallUploadServer", $data);
		
		if (!$upload_docs->success()) {
			$result->error = $upload_docs->success();
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
				usleep(1);
			
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
						'photo'			=> stripcslashes($upload->photo), 
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
						'doc'.$file->response[0]->owner_id.'_'.$file->response[0]->id : 
						'photo'.$file->response[0]->owner_id.'_'.$file->response[0]->id;
					
					$attachments[$att] = $file->response[0];
					
					if ($progress)
						$progress($att, $file->response[0]);
					
					$result->error = false;
					break;
				} else {
					$result->error = $file->error();
					$result->captcha = $file->captcha();
					Captcha::set($file->captcha());
					
					if ($file->errorCode() == VkApi\Response::VK_ERR_TOO_FAST)
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
	
	public static function getAll(VkApi $api, $group_id) {
		$settings = DB::select('interval', 'period_from', 'period_to')
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
			'last'			=> false, 
			'users'			=> [], 
		];
		
		$code = '
			var api_cnt = 0, 
				gid = -'.$group_id.';
			
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
				profiles:	ids.length ? API.users.get({user_ids: ids, fields: "photo_50"}) : [], 
				last:		last_comment
			};
		';
		
		$out = false;
		
		for ($i = 0; $i < 2; ++$i) {
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
		if ($out->response->last && count($out->response->last->items)) {
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
		
		$queue = self::getQueue($group_id);
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
				\Z\Smm\Globals::set($group_id, "next_post_date", $post->date);
			} else {
				$new_postponed[] = $post;
			}
		}
		$postponed = $new_postponed;
		
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
		
		$result->postponed		= $postponed;
		$result->postponed_cnt	= $last_post ? count($postponed) - 1 : count($postponed);
		$result->suggests		= $suggests;
		$result->suggests_cnt	= count($suggests);
		$result->specials		= $specials;
		$result->last			= $last_post;
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
			foreach ($att as $k => $v) {
				if (preg_match('/^photo_(\d+)$/', $k, $m))
					$ret->thumbs[$m[1]] = $v;
			}
			
			if (isset($att->width)) {
				$ret->width = $att->width;
				$ret->height = $att->height;
			} else {
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
	
	protected static function fixPostDate($post_time, $settings) {
		$day_start = Date::getDayStart($post_time);
		
		// Указан дополнительный интервал
		$fix_after = 0;
		if ($settings['period_to'] < $settings['period_from']) {
			$fix_after = $settings['period_to'];
			$settings['period_to'] = 3600 * 24;
		}
		
		if (24 * 3600 - ($settings['period_to'] - $settings['period_from']) > 60) { // Есть фиксированный период постинга
			if ($post_time - ($day_start + $settings['period_to']) >= 10) {
				// Если время превышает границу времени, то переносим на следующий день
				$post_time = $day_start + 24 * 3600 + $settings['period_from'];
			} elseif ($post_time - ($day_start + $settings['period_from']) <= -10) {
				if (!$fix_after || $post_time - ($day_start + $fix_after) > 10) { // Дополнительный интервал
					// Если время не попадает под минимальный период, то переносим его на начало периода текущего дня
					$post_time = $day_start + $settings['period_from'];
				}
			}
		}
		
		// Выравниваем по 10 минут
		if ($post_time && ($post_time % 60 != 0))
			$post_time = round($post_time / 60) * 60;
		
		return $post_time;
	}
	
	protected static function processQueue($posts, $settings) {
		$SPECIAL_POST_AFTER_PAD = min(3600, $settings['interval']);
		$SPECIAL_POST_BEFORE_PAD = 3600;
		$SPECIAL_POST_FIX = 60;
		
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
				$cur->date = self::fixPostDate(max($prev_date + $settings['interval'], time()), $settings);
	//			echo "#".$cur->id." set date ".date("d/m/Y H:i", $cur->date)."\n";
				
				$need_recalc = 0;
				if ($pass > 0) {
					// Предыдущий пост - специальный и до него меньше часа
					if ($prev && $prev->special && $cur->date - $prev->date < ($SPECIAL_POST_BEFORE_PAD - $SPECIAL_POST_FIX)) {
						// Увеличиваем промежуток до часа
						$cur->date = self::fixPostDate($cur->date + ($SPECIAL_POST_BEFORE_PAD - ($cur->date - $prev->date)), $settings);
						
	//					echo "\t#".$cur->id." fix date ".date("d/m/Y H:i", $cur->date)." (diff=".($cur->date - $prev->date).") by prev SPECIAL\n";
					}
					
					// Следующий пост - специальный и до него меньше часа
					if ($next && $next->special && $next->date - $cur->date < ($SPECIAL_POST_AFTER_PAD - $SPECIAL_POST_FIX)) {
						for ($i = $i + 1; $i < $l; ++$i) {
							$next_topic = $posts[$ids[$i]];
							if ($next_topic->date - $cur->date < ($SPECIAL_POST_AFTER_PAD - $SPECIAL_POST_FIX)) {
								// Передвигаем этот пост ЗА топик + 1h
								$cur->date = self::fixPostDate($next_topic->date + $SPECIAL_POST_AFTER_PAD, $settings);
								
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
}
