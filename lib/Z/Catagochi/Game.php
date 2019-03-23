<?php
namespace Z\Catagochi;

use \Z\Catagochi;
use \Mysql;

class Game {
	protected $app, $user, $vk, $start_time, $messages, $global_message_args = [];
	
	protected static $instance;
	
	protected function __construct() {
		$this->start_time = microtime(true);
		
		$this->app = Mysql::query("SELECT * FROM vkapp WHERE app = 'catagochi'")
			->fetchObject();
		
		if (!$this->app)
			throw new Exception("Application community not found");
		
		$this->settings = Settings\Model::instance();
		
		$this->messages = [];
		foreach (Mysql::query("SELECT * FROM `vkapp_catlist_messages`")->fetchAll() as $m)
			$this->messages[$m['id']] = $m['message'];
		
		$this->vk = new \Z\Net\VK();
		$this->vk->setCommToken($this->app->token);
	}
	
	public function router($route = NULL, $message = false) {
		if (!$route)
			$route = $this->user()->action;
		
		if (!$message)
			$message = new Catagochi\Message();
		
		if ($message->match("жумхуй")) {
			Mysql::query("DELETE FROM `vkapp_catlist_users` WHERE `user_id` = ?", $this->user->user_id);
			Mysql::query("DELETE FROM `vkapp_catlist_user_cats` WHERE `user_id` = ?", $this->user->user_id);
			Mysql::query("DELETE FROM `vkapp_catlist_money_history` WHERE `user_id` = ?", $this->user->user_id);
			$this->sendMessage($this->user->user_id, "Очищено!");
			return true;
		}
		
		$tmp = explode("/", $route);
		
		$controller_name = implode("", array_map(function ($v) {
			return ucfirst(strtolower($v));
		}, explode("_", $tmp[0])))."Controller";
		
		$action_name = lcfirst(implode("", array_map(function ($v) {
			return ucfirst(strtolower($v));
		}, explode("_", isset($tmp[1]) ? $tmp[1] : 'index'))))."Action";
		
		$controller_class = "\\Z\\Catagochi\\Controllers\\$controller_name";
		
		if (!class_exists($controller_class) || !method_exists($controller_class, $action_name)) {
			$this->log("Not found: %s::%s", $controller_class, $action_name);
			if ($route == 'main/menu')
				throw new \Exception("Internal error, default route not found.");
			$this->user->setAction('main/menu', array())->save();
			return $this->router('main/menu');
		}
		
		$controller = new $controller_class($this);
		$controller->before();
		$retval = $controller->$action_name($message);
		$controller->after();
		
		if (is_string($retval))
			$retval = [$retval];
		
		if (is_array($retval)) {
			$this->user->setAction($retval[0], isset($retval[1]) ? $retval[1] : array())->save();
			return $this->router();
		}
		
		return true;
	}
	
	public function L($text, $args = []) {
		if (!isset($this->messages[$text])) {
			$out = "Неизвестное сообщение! (id=$text)\n";
			foreach ($args as $k => $v)
				$out .= "{$k} => '$v'\n";
			return $out;
		}
		
		$args = $args + $this->global_message_args;
		
		$text = $this->messages[$text];
		
		// {var_name}
		$text = preg_replace_callback("/{([\w\d+_-]+)}/is", function ($m) use ($args) {
			if (isset($args[$m[1]]))
				return $args[$m[1]];
			return $m[0];
		}, $text);
		
		// sex
		$text = preg_replace_callback("/{([\w\d+_-]+_)?sex\|\|(.*?)\|\|(.*?)}/is", function ($m) use ($args) {
			$var_name = $m[1]."sex";
			if (isset($args[$var_name])) {
				$values = [$m[2], $m[3]];
				return $values[$args[$var_name] ? 1 : 0];
			}
			return $m[0];
		}, $text);
		
		// conan
		$text = preg_replace_callback("/{([\w\d+_-]+)\|\|(.*?)\|\|(.*?)\|\|(.*?)}/is", function ($m) use ($args) {
			if (isset($args[$m[1]])) {
				$num = (int) preg_replace("/\D/", "", $args[$m[1]]);
				$titles = [$m[2], $m[3], $m[4]];
				$cases = [2, 0, 1, 1, 1, 2];
				
				$text = $titles[($num % 100 > 4 && $num % 100 < 20 ? 2 : $cases[min($num % 10, 5)])];
				$text = str_replace('$n', $args[$m[1]], $text);
				
				return $text;
			}
			return $m[0];
		}, $text);
		
		return $text;
	}
	
	public function handle($data) {
		switch ($data->type) {
			case "message_new":
				$this->setCurrentUser($data->object->user_id);
				$this->router(NULL, new Catagochi\Message($data->object));
			break;
			
			case "message_allow":
			case "message_deny":
				$user = User::createModel($data->object->from_id);
				if ($user) {
					$user->deny = $data->type == 'message_deny' ? 1 : 0;
					$user->save();
				}
			break;
		}
	}
	
	public function user() {
		return $this->user;
	}
	
	public function vk() {
		return $this->vk;
	}
	
	public function setCurrentUser($id) {
		$this->user = $this->getUser($id);
		
		// Глобальные параметры сообщения
		$this->global_message_args = [
			'first_name'	=> $this->user->first_name, 
			'last_name'		=> $this->user->last_name, 
			'money'			=> $this->user->money, 
			'bonus'			=> round($this->user->bonus), 
			'toilet'		=> round($this->user->toilet)."%", 
			'food'			=> round($this->user->food)."%", 
		];
	}
	
	public function reply($text, $images = []) {
		return $this->sendMessage($this->user->user_id, $text, $images);
	}
	
	public function sendMessage($user_id, $text, $images = []) {
		$buttons = [];
		$text = preg_replace_callback("/\{menu(\|(negative|positive|default|primary))?\}(.*?)\{\/menu\}/i", function ($m) use (&$buttons) {
			$buttons[] = [
				[
					'action'	=> [
						'type'		=> 'text', 
						'payload'	=> '{}', 
						'label'		=> mb_substr(trim($m[3]), 0, 40)
					], 
					'color'		=> $m[2] ? $m[2] : 'default'
				]
			];
			return trim($m[3]);
		}, $text);
		
		$vk = $this->vk();
		$md5sums = [];
		$md5_to_file = [];
		$vk_attaches = [];
		foreach ($images as $file) {
			$md5 = md5_file($file);
			$md5sums[] = $md5;
			$md5_to_file[$md5] = $file;
			$vk_attaches[$md5] = NULL;
		}
		
		if ($md5sums) {
			$req = Mysql::query("SELECT * FROM `vkapp_catlist_files` WHERE `md5` IN (?) AND `time` > ?", 
				$md5sums, time() - 24 * 3600 * 365 * 10);
			foreach ($req->fetchAll() as $f)
				$vk_attaches[$f['md5']] = $f['attach_id'];
			
			$i = 0;
			$files_to_upload = [];
			foreach ($md5_to_file as $md5 => $file) {
				if (!isset($vk_attaches[$md5])) {
					$files_to_upload[] = [
						'key'	=> 'file'.($i % 7), 
						'path'	=> $file, 
						'name'	=> 'file.jpg', 
						'md5'	=> $md5
					];
				}
				++$i;
			}
			
			if ($files_to_upload) {
				$start = microtime(true);
				$this->log("upload %d files", count($files_to_upload));
				foreach (array_chunk($files_to_upload, 7) as $chunk) {
					// Выгружаем фотки
					$tries = 10;
					$api_data = false;
					$errors = [];
					while (--$tries) {
						$server = $vk->execComm('photos.getMessagesUploadServer');
						if (isset($server->response)) {
							$res = $vk->upload($server->response->upload_url, $chunk);
							if ($res->code == 200) {
								if ($json = json_decode($res->body)) {
									if (!isset($json->photo) || !isset($json->server) || !isset($json->hash)) {
										$error = "unknown server answer: ".$res->body;
									} elseif ($json->photo == "[]") {
										$error = "upload error,  photo=[]";
									} else {
										$api_data = [
											'server'	=> $json->server, 
											'hash'		=> $json->hash, 
											'photo'		=> stripslashes($json->photo)
										];
										break;
									}
								} else {
									$error = "JSON parse error: ".$res->body;
								}
							} else {
								$error = "http error: ".$res->code;
							}
						} else {
							$error = $vk->error($server)->error;
						}
						$this->log("upload error: $error");
						$errors[] = $error;
						sleep(1);
					}
					
					if (!$api_data)
						throw new \Exception(implode("\n", $errors));
					
					// Сохраняем фотки
					$tries = 10;
					$errors = [];
					$success = false;
					while (--$tries) {
						$res = $vk->execComm("photos.saveMessagesPhoto", $api_data);
						if ($res && isset($res->response)) {
							foreach ($res->response as $i => $p) {
								$md5 = $chunk[$i]['md5'];
								$vk_attaches[$md5] = 'photo'.$p->owner_id.'_'.$p->id;
								
								Mysql::query("
									INSERT INTO `vkapp_catlist_files` SET
										`md5`		= ?, 
										`time`		= ?, 
										`attach_id`	= ?
									ON DUPLICATE KEY UPDATE
										`time`		= VALUES(`time`), 
										`attach_id`	= VALUES(`attach_id`)
								", $md5, time(), $vk_attaches[$md5]);
							}
							$success = true;
							break;
						} else {
							$error = $vk->error($res)->error;
						}
						$this->log("save error: $error");
						$errors[] = $error;
						sleep(1);
					}
					
					if (!$success)
						throw new \Exception(implode("\n", $errors));
				}
				
				$this->log("upload done (%.04f)", microtime(true) - $start);
				
				foreach ($vk_attaches as $md5 => $p) {
					if (is_null($p))
						throw new \Exception('Not all files uploaded! '.json_encode($vk_attaches));
				}
			}
		}
		
		$res = $vk->execComm("messages.send", [
			'message'		=> $text."\n\n// ".round(microtime(true) - $this->start_time, 4).' s', 
			'user_id'		=> $user_id, 
			'attachment'	=> implode(",", array_values($vk_attaches)), 
			'keyboard'		=> json_encode([
				'one_time'		=> false, 
				'buttons'		=> $buttons
			], JSON_UNESCAPED_UNICODE)
		]);
		
		$error = $vk->error($res);
		if ($error)
			throw new \Exception($error->error);
	}
	
	public function getUser($id) {
		$user = User\Model::createModel($id);
		if (!$user) {
			$user = User\Model::createNew();
			$res = $this->vk->execComm("users.get", [
				'user_ids'	=> $id, 
				'fields'	=> 'first_name,last_name,sex'
			]);
			if (isset($res->response)) {
				$user->user_id		= $res->response[0]->id;
				$user->first_name	= $res->response[0]->first_name;
				$user->last_name	= $res->response[0]->last_name;
				$user->sex			= $res->response[0]->sex;
				$user->action		= "landing/start";
				$user->ctime		= time();
				$user->mtime		= time();
				
				$user->save();
				return $user;
			}
			throw new \Exception("$id - get vk user error: ".json_encode($res));
		}
		return $user;
	}
	
	public static function instance() {
		if (!self::$instance)
			self::$instance = new Game();
		return self::$instance;
	}
	
	public function log() {
		$text = call_user_func_array("sprintf", func_get_args());
		file_put_contents(APP."logs/handler.log", "[".date("d-m-Y H:i:s")."] $text\n", FILE_APPEND | LOCK_EX);
	}
}
