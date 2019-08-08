<?php
namespace Smm\Controllers;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;
use \Z\Net\VkApi;

use \Smm\View\Widgets;

use PhpAmqpLib\Message\AMQPMessage;

class VkPostsController extends \Smm\GroupController {
	// Время, до которого можно успеть обновить время публикации поста
	private function getPostDeadLine($time) {
		$sched_interval = \Z\Config::get("scheduler.interval") * 60;
		
		// Период обновления щедулера после поста
		$next_sched_update = $time + ($sched_interval - ((date("i", $time) * 60 + date("s", $time)) % $sched_interval));
		
		// Период обновления щедулера перед постом
		$next_sched_update -= $sched_interval;
		
		// Если между периодом обновления и постом менее минуты, то получаешь предыдущий период
		if ($time - $sched_interval < 60)
			$next_sched_update -= $sched_interval;
		
		return $next_sched_update;
	}
	
	public function spellcheckAction() {
		$this->mode('json');
		
		$text = $_POST['text'] ?? '';
		
		$this->content['success'] = true;
		$this->content['spell2'] = mb_strlen($text);
		$this->content['spell'] = \Smm\Utils\Spellcheck::check($text);
	}
	
	public function moveAction() {
		$api = new \Z\Net\VkApi(\Smm\Oauth::getAccessToken('VK'));
		
		$id = intval($_REQUEST['id'] ?? 0);
		$dir = $_REQUEST['dir'] ?? 'up';
		
		$this->mode('json');
		
		$res = \Smm\VK\Posts::getAll($api, $this->group['id']);
		
		$before = NULL;
		$after = NULL;
		$current = NULL;
		
		$this->content['success'] = false;
		
		if (!$res->success) {
			$this->content['error'] = 'Ошибка, невозможно получить список постов из VK. Попробуйте снова.';
			return;
		}
		
		foreach ($res->postponed as $post) {
			if (!in_array($post->post_type, ['postpone', 'suggest']))
				continue;
			
			if ($post->id == $id) {
				$current = $post;
			} else {
				if (!$current)
					$before = $post;
				
				if ($current && !$after)
					$after = $post;
				}
		}
		
		if (!$current) {
			$this->content['error'] = 'Ошибка, пост уже был опубликован или удалён.';
			return;
		}
		
		if ($this->getPostDeadLine($current->orig_date) - time() < 60) {
			$this->content['error'] = 'Ошибка, не успеем передвинуть этот пост до его публикации. '.
				'Крайнее время: '.date("Y-m-d H:i:s", $this->getPostDeadLine($current->orig_date)).', сейчас: '.date("Y-m-d H:i:s");
			return;
		}
		
		if ($dir == 'up') {
			if (!$before) {
				$this->content['error'] = 'Ошибка, пост уже и так первый в списке.';
				return;
			}
			
			if ($this->getPostDeadLine($before->orig_date) - time() < 60) {
				$this->content['error'] = 'Ошибка, не успеем передвинуть предыдущий пост до его публикации. '.
					'Крайнее время: '.date("Y-m-d H:i:s", $this->getPostDeadLine($before->orig_date)).', сейчас: '.date("Y-m-d H:i:s");
				return;
			}
		}
		
		if ($dir == 'down') {
			if (!$after) {
				$this->content['error'] = 'Ошибка, пост уже и так последний в списке.';
				return;
			}
			
			if ($this->getPostDeadLine($after->orig_date) - time() < 60) {
				$this->content['error'] = 'Ошибка, не успеем передвинуть следующий пост до его публикации. '.
					'Крайнее время: '.date("Y-m-d H:i:s", $this->getPostDeadLine($after->orig_date)).', сейчас: '.date("Y-m-d H:i:s");
				return;
			}
		}
		
		$sibling = $dir == 'up' ? $before : $after;
		
		DB::begin();
		
		$current_entry = DB::select()
			->forUpdate()
			->from('vk_posts_queue')
			->where('group_id', '=', $this->group['id'])
			->where('id', '=', $current->id)
			->execute()
			->current();
		
		$sibling_entry = DB::select()
			->forUpdate()
			->from('vk_posts_queue')
			->where('group_id', '=', $this->group['id'])
			->where('id', '=', $sibling->id)
			->execute()
			->current();
		
		if (!$current_entry || !$sibling_entry) {
			$this->content['error'] = 'Ошибка, пост не найден в очереди. WTF?';
			return;
		}
		
		DB::delete('vk_posts_queue')
			->where('nid', 'IN', [$current_entry['nid'], $sibling_entry['nid']])
			->execute();
		
		DB::insert('vk_posts_queue')
			->set([
				'nid'			=> $sibling_entry['nid'], 
				'id'			=> $current_entry['id'], 
				'group_id'		=> $current_entry['group_id'], 
				'fake_date'		=> $current_entry['fake_date'], 
			])
			->execute();
		
		DB::insert('vk_posts_queue')
			->set([
				'nid'			=> $current_entry['nid'], 
				'id'			=> $sibling_entry['id'], 
				'group_id'		=> $sibling_entry['group_id'], 
				'fake_date'		=> $sibling_entry['fake_date'], 
			])
			->execute();
		
		DB::commit();
		
		$this->content['success'] = true;
	}
	
	public function editAction() {
		$api = new \Z\Net\VkApi(\Smm\Oauth::getAccessToken('VK'));
		
		$this->mode('json');
		
		$id				= intval($_REQUEST['id'] ?? 0);
		$signed			= intval($_REQUEST['signed'] ?? 0);
		$lat			= floatval($_REQUEST['lat'] ?? 0);
		$long			= floatval($_REQUEST['long'] ?? 0);
		$message		= $_REQUEST['message'] ?? '';
		$attachments	= $_REQUEST['attachments'] ?? '';
		$post_type		= $_REQUEST['type'] ?? '';
		$comment		= trim($_REQUEST['comment'] ?? '');
		
		$this->content['success'] = false;
		$this->content['post_type'] = $post_type;
		
		if (!$this->user->can('user')) {
			$this->content['error'] = 'Гостевой доступ.';
			return;
		}
		
		$api_data = [
			"posts"		=> -$this->group['id']."_".$id
		];
		
		if (($captcha_code = \Smm\VK\Captcha::getCode())) {
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
			
			$fake_date = DB::select('fake_date')
				->from('vk_posts_queue')
				->where('group_id', '=', $this->group['id'])
				->where('id', '=', $id)
				->execute()
				->get('fake_date', 0);
			
			if (strlen($comment) > 0) {
				DB::insert('vk_posts_comments')
					->set([
						'text'			=> $comment, 
						'group_id'		=> $this->group['id'], 
						'id'			=> $id
					])
					->onDuplicateSetValues('text')
					->execute();
			} else {
				DB::delete('vk_posts_comments')
					->where('group_id', '=', $this->group['id'])
					->where('id', '=', $id)
					->execute();
			}
			
			if ($post->post_type != 'post')
				$api_data['publish_date'] = $post->date;
			
			if (($captcha_code = \Smm\VK\Captcha::getCode())) {
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
		$api = new \Z\Net\VkApi(\Smm\Oauth::getAccessToken('VK'));
		
		$this->mode('json');
		
		$id				= intval($_REQUEST['id'] ?? 0);
		$signed			= intval($_REQUEST['signed'] ?? 0);
		$lat			= floatval($_REQUEST['lat'] ?? 0);
		$long			= floatval($_REQUEST['long'] ?? 0);
		$message		= $_REQUEST['message'] ?? '';
		$attachments	= $_REQUEST['attachments'] ?? '';
		$post_type		= $_REQUEST['type'] ?? '';
		$comment		= trim($_REQUEST['comment'] ?? '');
		
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
		
		if (($captcha_code = \Smm\VK\Captcha::getCode())) {
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
			
			if (strlen($comment) > 0) {
				DB::insert('vk_posts_comments')
					->set([
						'text'			=> $comment, 
						'group_id'		=> $this->group['id'], 
						'id'			=> $res->response->post_id
					])
					->onDuplicateSetValues('text')
					->execute();
			}
			
			$this->content['success'] = true;
			$this->content['link'] = 'https://m.vk.com/wall-'.$this->group['id'].'_'.$vk_post_id;
			
			$result = \Smm\VK\Posts::getAll($api, $this->group['id']);
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
		$api = new \Z\Net\VkApi(\Smm\Oauth::getAccessToken('VK'));
		
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
		
		$next_post_date = \Smm\Globals::get($this->group['id'], "next_post_date");
		if (!$next_post_date) {
			$api = new \Z\Net\VkApi(\Smm\Oauth::getAccessToken('VK'));
			$result = \Smm\VK\Posts::getAll($api, $this->group['id']);
			if (!$result->success) {
				$this->content['success'] = false;
				$this->content['error'] = $result->error;
				$this->content['captcha'] = \Smm\VK\Captcha::getLast();
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
		
		$api = new \Z\Net\VkApi(\Smm\Oauth::getAccessToken('VK'));
		
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
						$msg = (object) [
							'images'	=> $images, 
							'documents'	=> $documents, 
							'files'		=> $files, 
							'gid'		=> $this->group['id'], 
							'cover'		=> $cover_path, 
							'offset'	=> $offset, 
							'ctime'		=> time()
						];
						
						$id = md5(json_encode($msg));
						
						\Z\Cache::instance()->set("download_queue:$id", $msg, 3600);
						
						$amqp = \Z\Net\AMQP::instance();
						$amqp->queue_declare('download_queue', false, true);
						$amqp->basic_publish(new AMQPMessage($id), '', 'download_queue');
					}
				}
				
				if ($id) {
					$status = \Z\Cache::instance()->get("download_queue:$id");
					
					$this->content['id'] = $id;
					
					if ($status) {
						if (isset($status->error)) {
							$this->content['error'] = $status->error;
						} elseif (isset($status->attaches)) {
							$this->content['success'] = true;
							$this->content['attaches'] = $status->attaches;
							$this->content['data'] = $status->attaches_data;
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
						
						$result = \Smm\VK\Posts::uploadPics($api, $this->group['id'], [[
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
							$this->content['data'] = \Smm\VK\Posts::normalizeAttaches((object) [
								'attachments' => $attachments
							]);
						} else {
							$this->content['error'] = $result->error;
							$this->content['captcha'] = \Smm\VK\Captcha::getLast();
						}
					}
				} else {
					$this->content['error'] = 'Файл не найден в запросе! o_O';
				}
			break;
		}
	}
}
