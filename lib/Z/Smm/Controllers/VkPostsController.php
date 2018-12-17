<?php
namespace Z\Smm\Controllers;

use \Z\Core\DB;
use \Z\Core\View;
use \Z\Core\Date;
use \Z\Core\Util\Url;
use \Z\Core\Net\VkApi;

use \Z\Smm\View\Widgets;

class VkPostsController extends \Z\Smm\GroupController {
	public function editAction() {
		$api = new \Z\Core\Net\VkApi(\Z\Smm\Oauth::getAccessToken('VK'));
		
		$this->mode('json');
		
		$id				= intval($_REQUEST['id'] ?? 0);
		$signed			= intval($_REQUEST['signed'] ?? 0);
		$lat			= floatval($_REQUEST['lat'] ?? 0);
		$long			= floatval($_REQUEST['long'] ?? 0);
		$message		= $_REQUEST['message'] ?? '';
		$attachments	= $_REQUEST['attachments'] ?? '';
		$post_type		= $_REQUEST['type'] ?? '';
		
		$this->content['success'] = false;
		$this->content['post_type'] = $post_type;
		
		if (!$this->user->can('user')) {
			$this->content['error'] = 'Гостевой доступ.';
			return;
		}
		
		$api_data = [
			"posts"		=> -$this->group['id']."_".$id
		];
		
		if (($captcha_code = \Z\Smm\VK\Captcha::getCode())) {
			$api_data['captcha_key'] = $captcha_code['key'];
			$api_data['captcha_sid'] = $captcha_code['sid'];
		}
		
		$res = $api->exec("wall.getById", $api_data);
		if ($res->success()) {
			$post = $res->response[0];
			
			$api_data = [
				'post_id'		=> $id, 
				'owner_id'		=> -$this->group['id'], 
				'signed'		=> $signed, 
				'message'		=> $message, 
				'lat'			=> $lat, 
				'long'			=> $long, 
				'attachments'	=> $attachments
			];
			
			if ($post->post_type != 'post')
				$api_data['publish_date'] = $post->date;
			
			if (($captcha_code = \Z\Smm\VK\Captcha::getCode())) {
				$api_data['captcha_key'] = $captcha_code['key'];
				$api_data['captcha_sid'] = $captcha_code['sid'];
			}
			
			$response_edit = $api->exec("wall.edit", $api_data);
			if ($response_edit->success()) {
				$this->content['success'] = true;
			} else {
				$this->content['error'] = $response_edit->error();
				$this->content['captcha'] = $response_edit->captcha();
			}
		} else {
			$this->content['error'] = $res->error();
			$this->content['captcha'] = $res->captcha();
		}
	}
	
	public function queueAction() {
		$api = new \Z\Core\Net\VkApi(\Z\Smm\Oauth::getAccessToken('VK'));
		
		$this->mode('json');
		
		$id				= intval($_REQUEST['id'] ?? 0);
		$signed			= intval($_REQUEST['signed'] ?? 0);
		$lat			= floatval($_REQUEST['lat'] ?? 0);
		$long			= floatval($_REQUEST['long'] ?? 0);
		$message		= $_REQUEST['message'] ?? '';
		$attachments	= $_REQUEST['attachments'] ?? '';
		$post_type		= $_REQUEST['type'] ?? '';
		
		$this->content['success'] = false;
		$this->content['post_type'] = $post_type;
		
		if ($post_type == 'post') {
			$this->content['error'] = 'Ошибка, неправильный тип поста.';
			return;
		}
		
		if (!$this->user->can('user')) {
			$this->content['error'] = 'Гостевой доступ.';
			return;
		}
		
		// Фейковая дата поста
		$fake_date = DB::select(['MAX(`fake_date`)', 'fake_date'])
			->from('vk_posts_queue')
			->execute()
			->get('fake_date', 0);
		$fake_date = max(time() + 3600 * 24 * 60, $fake_date) + 3600;
		
		$api_data = [
			'owner_id'		=> -$this->group['id'], 
			'signed'		=> $signed, 
			'message'		=> $message, 
			'lat'			=> $lat, 
			'long'			=> $long, 
			'attachments'	=> $attachments, 
			'publish_date'	=> $fake_date
		];
		
		if (($captcha_code = \Z\Smm\VK\Captcha::getCode())) {
			$api_data['captcha_key'] = $captcha_code['key'];
			$api_data['captcha_sid'] = $captcha_code['sid'];
		}
		
		if ($id)
			$api_data['post_id'] = $id;
		
		$res = $api->exec(($post_type == 'suggest' || $post_type == 'new') ? "wall.post" : "wall.edit", $api_data);
		if ($res->success()) {
			$vk_post_id = $res->response->post_id ?? $id;
			
			DB::insert('vk_posts_queue')
				->set([
					'fake_date'		=> $fake_date, 
					'group_id'		=> $this->group['id'], 
					'id'			=> $res->response->post_id
				])
				->onDuplicateSetValues('fake_date')
				->execute();
			
			$this->content['success'] = true;
			$this->content['link'] = 'https://m.vk.com/wall-'.$this->group['id'].'_'.$vk_post_id;
			
			$result = \Z\Smm\VK\Posts::getAll($api, $this->group['id']);
			if ($result->success) {
				foreach ($result->postponed as $post) {
					if ($post->id == $vk_post_id)
						$this->content['date'] = $post->date;
				}
			}
		} else {
			$this->content['error'] = $res->error();
			$this->content['captcha'] = $res->captcha();
		}
	}
	
	public function deleteAction() {
		$api = new \Z\Core\Net\VkApi(\Z\Smm\Oauth::getAccessToken('VK'));
		
		$this->mode('json');
		
		$this->content['success'] = false;
		
		$id = intval($_REQUEST['id'] ?? 0);
		$restore = intval($_REQUEST['restore'] ?? 0);
		
		if (!$this->user->can('user')) {
			$this->content['error'] = 'Гостевой доступ.';
			return;
		}
		
		$res = $api->exec($restore ? "wall.restore" : "wall.delete", [
			'owner_id'		=> -$this->group['id'], 
			'post_id'		=> $id
		]);
		if ($res->success()) {
			$this->content['success'] = true;
		} else {
			$this->content['error'] = $res->error();
			$this->content['captcha'] = $res->captcha();
		}
	}
	
	public function next_dateAction() {
		$this->mode('json');
		
		$next_post_date = \Z\Smm\Globals::get($this->group['id'], "next_post_date");
		if (!$next_post_date) {
			$api = new \Z\Core\Net\VkApi(\Z\Smm\Oauth::getAccessToken('VK'));
			$result = \Z\Smm\VK\Posts::getAll($api, $this->group['id']);
			if (!$result->success) {
				$this->content['success'] = false;
				$this->content['error'] = $result->error;
				$this->content['captcha'] = \Z\Smm\VK\Captcha::getLast();
			}
		}
		
		$this->content['date'] = $next_post_date < time() ? time() : $next_post_date;
	}
	
	public function uploadAction() {
		$this->mode('json');
		$this->content['success'] = false;
		$this->content['error'] = false;
		
		if (!$this->user->can('user')) {
			$this->content['error'] = 'Гостевой доступ.';
			return;
		}
		
		$api = new \Z\Core\Net\VkApi(\Z\Smm\Oauth::getAccessToken('VK'));
		
		switch ($_POST['type'] ?? '') {
			// Загрузка по URL
			case "url":
				$id = preg_replace("/[^a-f0-9]/", "", $_REQUEST['id'] ?? "");
				
				if (!$id) {
					$images		= $_REQUEST['images'] ?? [];
					$documents	= $_REQUEST['documents'] ?? [];
					$files		= $_REQUEST['files'] ?? [];
					$cover		= $_FILES['cover'] ?? false;
					$offset		= intval($_REQUEST['offset'] ?? 0);
					
					if (!is_array($files))
						$files = [];
					
					if (!is_array($images))
						$images = [];
					
					if (!is_array($documents))
						$documents = [];
					
					$cover_path = false;
					if ($cover) {
						if ($cover['error']) {
							$this->content['error'] = 'Ошибка загрузки обложки по секретным номером #'.$cover['error'];
						} else if (!getimagesize($cover['tmp_name'])) {
							$this->content['error'] = 'Обложка не является картинкой!';
						} else {
							$cover_path = APP.'/tmp/cover_'.md5_file($cover['tmp_name']);
							if (!file_exists($cover_path)) {
								if (!move_uploaded_file($cover['tmp_name'], $cover_path))
									$this->content['error'] = 'Ошибка переноса обложки.';
							}
						}
					}
					
					if ($this->content['error'])
						return;
					
					if ($images || $documents || $files) {
						$msg = json_encode([
							'images'	=> $images, 
							'documents'	=> $documents, 
							'files'		=> $files, 
							'gid'		=> $this->group['id'], 
							'cover'		=> $cover_path, 
							'offset'	=> $offset
						]);
						
						$id = md5($msg);
						$queue_path = APP."/tmp/download_queue/$id";
						
						@file_put_contents($queue_path, $msg, LOCK_EX);
						@chmod($queue_path, 0666);
						
						if (!file_exists($queue_path)) {
							if ($cover_path)
								@unlink($cover_path);
							$this->content['error'] = 'Ошибка записи очереди: '.$queue_path;
							return;
						}
					}
				}
				
				if ($id) {
					$queue_path = APP."/tmp/download_queue/$id";
					
					$this->content['id'] = $id;
					
					if (file_exists($queue_path)) {
						$fp = fopen($queue_path, "r");
						flock($fp, LOCK_EX);
						$raw = "";
						while (!feof($fp))
							$raw .= fread($fp, 4096);
						$queue = json_decode($raw);
						flock($fp, LOCK_UN);
						fclose($fp);
						
						$status = json_decode($raw, true);
						
						if (isset($status['error'])) {
							$this->content['error'] = $status['error'];
						} elseif (isset($status['attaches'])) {
							$this->content['success'] = true;
							$this->content['attaches'] = $status['attaches'];
							$this->content['data'] = $status['attaches_data'];
						} else {
							$this->content['success'] = true;
							$this->content['queue'] = $status;
						}
					} else {
						$this->content['error'] = 'Очередь скачивания файла уже удалена. ('.$id .')';
					}
				}
			break;
			
			// Загрузка файла
			case "file":
			default:
				if ($_FILES && isset($_FILES['file'])) {
					if ($_FILES['file']['error']) {
						$this->content['error'] = 'Произошла странная ошибка под секретным номером #'.$_FILES['file']['error'];
					} elseif (!getimagesize($_FILES['file']['tmp_name'])) {
						$this->content['error'] = 'Что за дичь? Не очень похоже на пикчу с котиком.';
					} else {
						$ext = explode(".", $_FILES['file']['name']);
						$ext = strtolower(end($ext));
						
						$result = \Z\Smm\VK\Posts::uploadPics($api, $this->group['id'], [[
							'path'		=> $_FILES['file']['tmp_name'], 
							'caption'	=> $_POST['caption'] ?? "", 
							'document'	=> $ext == "gif"
						]]);
						
						if ($result->success) {
							$attachments = [];
							
							foreach ($result->attachments as $key => $attachment) {
								if (strpos($key, "doc") === 0) {
									$attachments[] = (object) [
										'type'	=> 'doc', 
										'doc'	=> $attachment
									];
								} else {
									$attachments[] = (object) [
										'type'	=> 'photo', 
										'photo'	=> $attachment
									];
								}
								
								$this->content['id'] = $key;
								$this->content['file'] = $attachment;
							}
							
							$this->content['success']  = true;
							$this->content['data'] = \Z\Smm\VK\Posts::normalizeAttaches((object) [
								'attachments' => $attachments
							]);
						} else {
							$this->content['error'] = $result->error;
							$this->content['captcha'] = \Z\Smm\VK\Captcha::getLast();
						}
					}
				} else {
					$this->content['error'] = 'Файл не найден в запросе! o_O';
				}
			break;
		}
	}
}
