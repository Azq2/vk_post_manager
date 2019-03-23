<?php
namespace Smm\Task;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Config;
use \Z\Util\Url;
use \Z\Net\VkApi;
use \Z\Net\Anticaptcha;

use \Smm\VK\Captcha;

class VkToTelegram extends \Z\Task {
	public function run($args) {
		if (!\Smm\Utils\Lock::lock(__CLASS__)) {
			echo "Already running.\n";
			return;
		}
		
		$vk = new VkApi(\Smm\Oauth::getAccessToken('VK'));
		
		$telegram_bot_token = Config::get('oauth.TELEGRAM.secret');
		if (!$telegram_bot_token)
			return;
		
		$groups = DB::select()
			->from('vk_groups')
			->where('telegram_channel_id', '!=', '')
			->execute();
		
		foreach ($groups as $group) {
			echo "=========== ".$group['id']." ===========\n";
			
			$posts = $vk->exec("wall.get", [
				'owner_id'	=> -$group['id'], 
				'offset'	=> 0, 
				'count'		=> !$group['telegram_last_vk_id'] ? 10 : 100, 
				'filter'	=> 'owner'
			]);
			
			if (!$posts->success()) {
				echo "Can't get posts: ".$posts->error()."\n";
				continue;
			}
			
			$ch = curl_init();
			curl_setopt_array($ch, [
				CURLOPT_TIMEOUT				=> 60, 
				CURLOPT_CONNECTTIMEOUT		=> 60, 
				CURLOPT_RETURNTRANSFER		=> true, 
				CURLOPT_USERAGENT			=> 'Mozilla/5.0', 
			]);
			
			foreach (array_reverse($posts->response->items) as $post) {
				$skip = "";
				$messages = [];
				
				if ($post->id <= $group['telegram_last_vk_id'])
					continue;
				
				if (!isset($post->is_pinned) || !$post->is_pinned) {
					$group['telegram_last_vk_id'] = $post->id;
					
					DB::update('vk_groups')
						->set('telegram_last_vk_id', $post->id)
						->where('id', '=', $group['id'])
						->execute();
				}
				
				if (isset($post->is_pinned) && $post->is_pinned) {
					$skip = "pinned";
				} elseif ($post->post_type != "post") {
					$skip = "post_type=".$post->post_type;
				} elseif ($post->marked_as_ads) {
					$skip = "ads";
				} else {
					$first = true;
					
					if (mb_strlen($post->text) >= 100 || count($post->attachments) > 1) {
						$msg = [
							'method'	=> 'sendMessage', 
							'name'		=> 'text', 
							'params'	=> [
								'chat_id'					=> $group['telegram_channel_id'], 
								'text'						=> $post->text, 
								'disable_notification'		=> 0, 
								'disable_web_page_preview'	=> 1
							]
						];
						$messages[] = $msg;
						$first = false;
					} else {
						$post->text = preg_replace("/\s*:\s*$/si", "", $post->text);
					}
					
					foreach ($post->attachments as $att) {
						if ($att->type == 'doc') { // Гифка
							if ($att->doc->size > 50 * 1024 * 1024) {
								$skip = "doc size ".round($att->doc->size / 1024 / 1024, 2)." Mb";
								break;
							} elseif (!in_array(strtolower($att->doc->ext), ['gif', 'pdf', 'zip'])) {
								 $skip = "doc ext ".$att->doc-ext;
								break;
							} else {
								$msg = [
									'method'	=> 'sendDocument', 
									'name'		=> 'doc'.$att->doc->owner_id.'_'.$att->doc->id, 
									'params'	=> [
										'chat_id'				=> $group['telegram_channel_id'], 
										'document'				=> $att->doc->url, 
										'disable_notification'	=> 1
									]
								];
								
								if ($first) {
									$msg['params']['caption'] = $post->text;
									$msg['params']['disable_notification'] = 0;
									$first = false;
								}
								
								$messages[] = $msg;
							}
						} else if ($att->type == 'photo') { // Картинка
							$thumbs_info = \Smm\VK\Posts::extractThumbs($att->photo);
							
							if (!$thumbs_info) {
								$skip = "unknown attach content ".json_encode($att);
								break;
							}
							
							$msg = [
								'method'	=> 'sendPhoto', 
								'name'		=> 'photo'.$att->photo->owner_id.'_'.$att->photo->id, 
								'params'	=> [
									'chat_id'				=> $group['telegram_channel_id'], 
									'photo'					=> end($thumbs_info->thumbs), 
									'disable_notification'	=> 1
								]
							];
							
							if ($first) {
								$msg['params']['caption'] = $post->text;
								$msg['params']['disable_notification'] = 0;
								$first = false;
							}
							
							$messages[] = $msg;
						} else {
							$skip = "unknown attach ".$att->type;
							break;
						}
					}
				}
				
				if ($skip) {
					echo "https://vk.com/wall".$post->owner_id."_".$post->id." - SKIP ($skip)\n";
				} else {
					echo "https://vk.com/wall".$post->owner_id."_".$post->id." - OK\n";
					foreach ($messages as $m) {
						echo "\tsend ".$m['name']."\n";
						$i = 3;
						while (true) {
							curl_setopt_array($ch, [
								CURLOPT_URL				=> "https://api.telegram.org/bot".$telegram_bot_token."/".$m['method'], 
								CURLOPT_POST			=> true, 
								CURLOPT_POSTFIELDS		=> $m['params'], 
								CURLOPT_TIMEOUT			=> 60, 
								CURLOPT_CONNECTTIMEOUT	=> 60
							]);
							$res = curl_exec($ch);
							$json = json_decode($res);
							
							if (!$json || !$json->ok) {
								if ($res) {
									echo "\t\terror #".$json->error_code.": ".$json->description."\n";
								} else {
									echo "\t\tunknown response [".url_getinfo($ch, CURLINFO_HTTP_CODE)."]: $res\n";
								}
								
								sleep(1);
								--$i;
								if (!$i) {
									echo "\t\ttoo many errors!\n";
									break;
								}
								continue;
							}
							break;
						}
					}
				}
			}
		}
	}
}
