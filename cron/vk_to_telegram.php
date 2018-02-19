<?php
if (php_sapi_name() != "cli")
	die("Not a CLI :)\n");

require __DIR__."/../inc/init.php";

$lock_fp = fopen(H."../tmp/vk_to_telegram", "w+");
if (!$lock_fp || !flock($lock_fp, LOCK_EX | LOCK_NB))
	die("Lock!\n");

$tg = new \Z\Core\Net\Telegram;
$tg->setBotToken(TELEGRAM_BOT_TOKEN);

$vk = new \Z\Core\Net\VK;
$vk->setUserToken(VK_USER_ACCESS_TOKEN);

$req = Mysql::query("SELECT * FROM `vk_groups` WHERE telegram_channel_id != ''");
while ($comm = $req->fetch()) {
	echo "=========== ".$comm['id']." ===========\n";
	$gid = $comm['id'];
	
	$posts = $vk->execUser("wall.get", [
		'owner_id'	=> -$gid, 
		'offset'	=> 0, 
		'count'		=> !$comm['telegram_last_vk_id'] ? 10 : 100, 
		'filter'	=> 'owner'
	]);
	
	foreach (array_reverse($posts->response->items) as $post) {
		$skip = "";
		$messages = [];
		
		if ($post->id <= $comm['telegram_last_vk_id'])
			continue;
		
		if (!isset($post->is_pinned) || !$post->is_pinned) {
			$comm['telegram_last_vk_id'] = $post->id;
			Mysql::query("UPDATE `vk_groups` SET `telegram_last_vk_id` = ? WHERE `id` = ?", $post->id, $comm['id']);
		}
		
		if (isset($post->is_pinned) && $post->is_pinned) {
			$skip = "pinned";
		} elseif ($post->post_type != "post") {
			$skip = "post_type=".$post->post_type;
		} elseif ($post->marked_as_ads) {
			$skip = "ads";
		} else {
			$first = true;
			
			if (mb_strlen($post->text) > 200 || count($post->attachments) > 1) {
				$msg = [
					'method'	=> 'sendMessage', 
					'name'		=> 'text', 
					'params'	=> [
						'chat_id'					=> $comm['telegram_channel_id'], 
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
								'chat_id'				=> $comm['telegram_channel_id'], 
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
					$max_size = 0;
					$src = null;
					foreach ($att->photo as $k => $v) {
						if (preg_match("/^photo_(\d+)$/", $k, $m)) {
							if ($max_size < $m[1]) {
								$src = $v;
								$max_size = $m[1];
							}
						}
					}
					
					$msg = [
						'method'	=> 'sendPhoto', 
						'name'		=> 'photo'.$att->photo->owner_id.'_'.$att->photo->id, 
						'params'	=> [
							'chat_id'				=> $comm['telegram_channel_id'], 
							'photo'					=> $src, 
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
					$res = $tg->exec($m['method'], $m['params']);
					if (!$res || !$res->ok) {
						echo "\t\terror #".$res->error_code.": ".$res->description."\n";
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

flock($lock_fp, LOCK_UN);
fclose($lock_fp);
