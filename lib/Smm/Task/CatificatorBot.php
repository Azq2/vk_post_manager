<?php
namespace Smm\Task;

use \Z\DB;
use \Z\Config;
use \Z\Util\Url;
use \Z\Net\VkApi;

use \Smm\VK\Captcha;

class CatificatorBot extends \Z\Task {
	protected $tracks = [];
	protected $triggers = [];
	protected $categories = [];
	
	public function options() {
		return [
			'instance'		=> ''
		];
	}
	
	public function run($args) {
		if (!\Smm\Utils\Lock::lock(__CLASS__.':'.$args['instance']))
			return;
		
		echo date("Y-m-d H:i:s")." - daemon start with pid ".getmygid()."\n";
		
		$amqp = \Z\Net\AMQP::instance();
		$amqp->queue_declare('catificator_queue', false, true);
		
		$last_tracks_check = 0;
		while (1) {
			if (time() - $last_tracks_check > 60) {
				$this->tracks = DB::select()
					->from('catificator_tracks')
					->order('id', 'ASC')
					->execute()
					->asArray('id');
				
				$this->categories = DB::select()
					->from('catificator_categories')
					->execute()
					->asArray('id');
				
				$this->triggers = DB::select()
					->from('catificator_category_triggers')
					->execute();
				$last_tracks_check = time();
			}
			
			$amqp_msg = $amqp->basic_get('catificator_queue', true);
			if ($amqp_msg) {
				$this->handle(json_decode($amqp_msg->body));
			} else {
				usleep(100000);
			}
		}
	}
	
	public function handle($msg) {
		$this->group = DB::select()
			->from('vk_groups')
			->where('id', '=', $msg->group_id)
			->execute()
			->current();
		
		if (!$this->group || $this->group['bot'] != 'catificator') {
			echo "Invalid group id! JSON: ".json_encode($msg, JSON_PRETTY_PRINT)."\n";
			return;
		}
		
		$access_token = \Smm\Oauth::getGroupAccessToken($msg->group_id);
		if (!$access_token) {
			echo "No access token! JSON: ".json_encode($msg, JSON_PRETTY_PRINT)."\n";
			return;
		}
		
		$this->api = new \Z\Net\VkApi($access_token);
		
		switch ($msg->type) {
			case "message_new":
				$this->handleNewMessage($msg);
			break;
			
			default:
				echo "Unknown type! JSON: ".json_encode($msg, JSON_PRETTY_PRINT)."\n";
			break;
		}
	}
	
	public function handleNewMessage($msg) {
		$this->vk_user = $this->getUser($msg->object->from_id);
		
		if (!$this->vk_user)
			return;
		
		$messages = new \Smm\Bot\Messages('catificator');
		$messages->setGlobals([
			'first_name'	=> $this->vk_user->first_name, 
			'last_name'		=> $this->vk_user->last_name, 
			'sex'			=> $this->vk_user->sex == 1, 
			'is_member'		=> $this->vk_user->is_member, 
			'is_guest'		=> !$this->vk_user->is_member, 
		]);
		
		$words = $this->parseText($msg->object->text);
		sort($words);
		
		echo date("Y-m-d H:i:s")." group: ".$msg->group_id.", user: ".$msg->object->from_id.", text: '".$msg->object->text."'\n";
		
		$keyboard = json_encode([
			'one_time'		=> false, 
			'buttons'		=> [
				[
					[
						'action'	=> [
							'type'		=> 'text', 
							'label'		=> 'Помощь', 
							'payload'	=> '{"command": "help"}'
						], 
						'color'		=> 'positive'
					]
				]
			]
		], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
		
		if (count($words) == 1 && $words[0] == "помощь") {
			$this->sendMessage([
				'user_id'		=> $msg->object->from_id, 
				'message'		=> $messages->L("help"), 
				'random_id'		=> microtime(true), 
				'keyboard'		=> $keyboard, 
			]);
			return;
		}
		
		if ($words) {
			$duration = mb_strlen(implode(" ", $words)) * 0.1;
			echo "=> duration: $duration sec.\n"; 
			 
			$matched_categories = [0 => 0];
			foreach ($this->triggers as $trigger) {
				$word = mb_strtolower(trim($trigger['word']));
				
				if (in_array($word, $words)) {
					if (!isset($matched_categories[$trigger['category_id']]))
						$matched_categories[$trigger['category_id']] = 0;
					++$matched_categories[$trigger['category_id']];
				}
			}
			
			// Уникальный id предложения
			$text_uniq = "text2mew:".$msg->object->from_id.":".md5(implode(",", $words));
			
			$attach_id = false;
			$track_id = false;
			
			$cache = \Z\Cache::instance();
			$cached_track = $cache->get($text_uniq);
			
			if ($cached_track) {
				list ($attach_id, $track_id) = $cached_track;
			} else {
				$this->api->exec("messages.setActivity", [
					'user_id'			=> $msg->object->from_id, 
					'type'				=> 'audiomessage'
				]);
				
				arsort($matched_categories);
				
				while (true) {
					$used_tracks = DB::select()
						->from('catificator_used_tracks')
						->where('date', '>=', date("Y-m-d H:i:s", time() - 3600 * 24))
						->where('user_id', '=', $msg->object->from_id)
						->execute()
						->asArray(NULL, 'track_id');
					
					$track_id = false;
					foreach ($matched_categories as $category_id => $cnt) {
						$matched_tracks = [];
						foreach ($this->tracks as $track) {
							if (!$category_id && $this->categories[$track['category_id']]['only_triggers'])
								continue;
							
							if (in_array($track['id'], $used_tracks))
								continue;
							
							if (!$category_id || $track['category_id'] == $category_id) {
								$matched_tracks[] = [
									'track_id'		=> $track['id'], 
									'delta'			=> abs($track['duration'] - $duration)
								];
							}
						}
						
						if ($category_id && $this->categories[$category_id]['random'] && $matched_tracks)
							$matched_tracks = [$matched_tracks[array_rand($matched_tracks)]];
						
						usort($matched_tracks, function ($a, $b) {
							return $a['delta'] <=> $b['delta'];
						});
						
						if ($matched_tracks) {
							echo "=> track ".$matched_tracks[0]['track_id']." with delta ".$matched_tracks[0]['delta']." s.\n";
							$track_id = $matched_tracks[0]['track_id'];
							break;
						}
					}
					
					if (!$track_id && $used_tracks) {
						echo "=> No matched tracks! Try reset...\n";
						DB::delete('catificator_used_tracks')
							->where('user_id', '=', $msg->object->from_id)
							->execute();
					} else {
						break;
					}
				}
				
				if ($track_id) {
					$track = $this->tracks[$track_id];
					$attach_id = $this->getDoc(APP.'www/files/catificator/'.$track['md5'].'.mp3', $msg->object->from_id);
					
					if (!$this->categories[$track['category_id']]['random'])
						$cache->set($text_uniq, [$attach_id, $track_id], 3600 * 24);
				}
			}
			
			if ($attach_id) {
				$track = $this->tracks[$track_id];
				
				$ok = $this->sendMessage([
					'user_id'		=> $msg->object->from_id, 
					'message'		=> $messages->L("result"), 
					'random_id'		=> microtime(true), 
					'attachment'	=> $attach_id, 
					'keyboard'		=> $keyboard, 
				]);
				
				if ($ok) {
					DB::insert('catificator_log')
						->set([
							'user_id'		=> $msg->object->from_id, 
							'track_id'		=> $track['id'], 
							'category_id'	=> $track['category_id'], 
							'date'			=> date("Y-m-d H:i:s", time())
						])
						->onDuplicateSetValues('date')
						->execute();
					
					if (!$this->categories[$track['category_id']]['random']) {
						DB::insert('catificator_used_tracks')
							->set([
								'user_id'		=> $msg->object->from_id, 
								'track_id'		=> $track['id'], 
								'date'			=> date("Y-m-d H:i:s", time())
							])
							->onDuplicateSetValues('date')
							->execute();
					}
				}
			}
		} else {
			echo "=> no parsed words!\n";
		}
	}
	
	public function sendMessage($data) {
		for ($i = 0; $i < 3; ++$i) {
			$send_req = $this->api->exec("messages.send", $data);
			if (!$send_req->success()) {
				echo "=> Can't send message: ".$send_req->error()."\n";
				usleep(100000);
				continue;
			}
			return true;
		}
		return false;
	}
	
	public function getUser($user_id) {
		$code = '
			var is_member = API.groups.isMember({
				group_id:		'.$this->group['id'].', 
				user_id:		'.$user_id.'
			});
			
			var user = API.users.get({
				user_id:		'.$user_id.', 
				fields:			"sex"
			});
			
			return {
				user:		user[0], 
				is_member:	is_member
			};
		';
		
		for ($i = 0; $i < 3; ++$i) {
			$user_req = $this->api->exec("execute", [
				'code'		=> $code
			]);
			
			if (!$user_req->success()) {
				echo "=> Can't get user: ".$user_req->error()."\n";
				usleep(100000);
				continue;
			}
			
			if (!$user_req->response->user) {
				echo "=> Can't get user: ".json_encode($user_req->execute_errors)."\n";
				usleep(100000);
				continue;
			}
			
			$user = $user_req->response->user;
			$user->is_member = $user_req->response->is_member;
			
			break;
		}
		
		return $user;
	}
	
	public function getDoc($file, $peer_id) {
		// Get upload server
		$upload_url = false;
		for ($i = 0; $i < 3; ++$i) {
			$upload_audiomsg = $this->api->exec("docs.getMessagesUploadServer", [
				'_'				=> microtime(true), 
				'type'			=> 'audio_message', 
				'peer_id'		=> $peer_id
			]);
			
			if (!$upload_audiomsg->success()) {
				echo "=> Can't get messages upload server: ".$upload_audiomsg->error()."\n";
				usleep(100000);
				continue;
			}
			
			$upload_url = $upload_audiomsg->response->upload_url;
			break;
		}
		
		if (!$upload_url)
			return false;
		
		// Do upload
		$upload_data = false;
		for ($i = 0; $i < 3; ++$i) {
			$upload_raw = $this->api->upload($upload_audiomsg->response->upload_url, [
				['path' => $file, 'name' => 'audiomsg.mp3', 'key' => 'file']
			]);
			$upload = @json_decode($upload_raw->body);
			
			if ($upload_raw->code != 200) {
				echo "=> Can't connect to ".$upload_audiomsg->response->upload_url." (code: ".$upload_raw->code.")\n";
				usleep(100000);
				continue;
			}
			
			if (!$upload) {
				echo "=> Can't upload to ".$upload_audiomsg->response->upload_url.": invalid response\n";
				usleep(100000);
				continue;
			}
			
			if ($upload->error ?? false) {
				echo "=> Can't upload to ".$upload_audiomsg->response->upload_url.": ".$upload->error."\n";
				usleep(100000);
				continue;
			}
			
			$upload_data = $upload->file;
			break;
		}
		
		if (!$upload_data)
			return false;
		
		$attach_id = false;
		for ($i = 0; $i < 3; ++$i) {
			$save_req = $this->api->exec("docs.save", [
				'file'			=> $upload_data
			]);
			
			if (!$save_req->success()) {
				echo "=> Can't save audiomsg: ".$save_req->error()."\n";
				usleep(100000);
				continue;
			}
			
			$attach_id = "doc".$save_req->response->audio_message->owner_id."_".$save_req->response->audio_message->id;
			break;
		}
		
		return $attach_id;
	}
	
	public function parseText($text) {
		preg_match_all("/([\w\d](?:(?:[\w\d-]+[\w\d])|))/siu", mb_strtolower($text), $matches);
		return $matches[1];
	}
}
