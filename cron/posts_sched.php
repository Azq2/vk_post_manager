<?php
if (php_sapi_name() != "cli")
	die("Not a CLI :)\n");

require __DIR__."/../inc/init.php";
require __DIR__."/../inc/vk_posts.php";

$lock_fp = fopen(H."../tmp/posts_sched", "w+");
if (!$lock_fp || !flock($lock_fp, LOCK_EX | LOCK_NB))
	die("Lock!\n");

$q = new Http;
$q->vkSetUser('VK_SCHED');

$SCHED_LIMIT = 2;

$req = Mysql::query("SELECT * FROM `vk_groups` as `g` WHERE EXISTS (SELECT 0 FROM `vk_posts_queue` as `s` WHERE `s`.group_id = `g`.id LIMIT 1)");
while ($comm = $req->fetch()) {
	echo "=========== ".$comm['id']." ===========\n";
	$gid = $comm['id'];
	$comments = get_comments($q, $comm);
	
	$limit = 0;
	foreach ($comments->postponed as $item) {
		if ($item->post_type == 'post' || $item->special)
			continue;
		
		if (abs($item->date - $item->orig_date) > 60) { // Нужно пофиксить время
			echo "[NEW] QUEUE: #".$item->id." at ".date("d/m/Y H:i", $item->date)." (diff=".($item->date - $item->orig_date).")\n";
			
			// Ищем посты, которые перекрывают нужный нам
			$overlaps = [];
			foreach (array_merge($comments->postponed, $comments->suggests) as $p) {
				if ($p->post_type == 'postpone' && !$p->special && abs($item->date - $p->orig_date) <= 60) { // Нужны только отложенные посты
					if ($p->id != $item->id) // Пропускаем себя же
						$overlaps[] = $p;
				}
			}
			
			// Меняем время таким постам на левое
			foreach ($overlaps as $p) {
				$req = Mysql::query("SELECT MAX(`fake_date`) FROM `vk_posts_queue`");
				$fake_date = max(time() + 3600 * 24 * 60, $req->num() ? $req->result() : 0) + 3600;
				
				echo "\t=> fix overlaped post #".$p->id." ".date("d/m/Y H:i", $p->date)." -> ".date("d/m/Y H:i", $fake_date)."\n";
				
				$p->date = $fake_date;
				
				$json = get_post_json($p);
				
				for ($i = 0; $i < 10; ++$i) {
					$res = $q->vkApi("wall.edit", [
						'post_id'		=> $p->id, 
						'owner_id'		=> $p->owner_id, 
						'signed'		=> $json['signed'], 
						'message'		=> $json['message'], 
						'lat'			=> $json['lat'], 
						'long'			=> $json['long'], 
						'attachments'	=> implode(",", $json['attachments']), 
						'publish_date'	=> $fake_date
					]);
					if (parse_vk_error($res, $output)) {
						echo "\t\t=> #".$p->id." - OK\n";
						break;
					}
					echo "\t\t=> #".$p->id." - ERROR: ".$output['error']."\n";
					
					if (isset($output['captcha']))
						sleep(120);
					
					sleep($i + 1);
				}
				
				// Обновляем фейковое время в БД
				Mysql::query("UPDATE `vk_posts_queue` SET `fake_date` WHERE `group_id` = $gid AND `id` = ".$p->id);
			}
			
			for ($i = 0; $i < 10; ++$i) {
				$output = [];
				$json = get_post_json($item);
				
				$res = $q->vkApi("wall.edit", [
					'post_id'		=> $item->id, 
					'owner_id'		=> $item->owner_id, 
					'signed'		=> $json['signed'], 
					'message'		=> $json['message'], 
					'lat'			=> $json['lat'], 
					'long'			=> $json['long'], 
					'attachments'	=> implode(",", $json['attachments']), 
					'publish_date'	=> $item->date <= time() + 60 ? time() + 60 : $item->date
				]);
				
				unset($_POST['vk_captcha_key']);
				unset($_POST['vk_captcha_sid']);
				
				if (parse_vk_error($res, $output)) {
					echo "\t=> #".$item->id." - OK\n";
					break;
				}
				
				if (isset($output['error']))
					echo "\t=> #".$item->id." - ERROR: ".$output['error']."\n";
				
				if (isset($output['captcha'])) {
					echo "\t=> #".$item->id." - CAPCHA: ".$output['captcha']['url']."\n";
					
					if (!isset($_SERVER['XUJ'])) { // Ввод с антикапчей
						echo "\tTRY ANICAPTCHA...\n";
						$code = \Z\Core\Net\Anticaptcha::resolve(base64_encode(file_get_contents($output['captcha']['url'])));
						if ($code) {
							$_REQUEST['vk_captcha_sid'] = $output['captcha']['sid'];
							$_REQUEST['vk_captcha_key'] = $code;
							echo "\tok, code = ".$_REQUEST['vk_captcha_key']."\n";
						} else {
							echo "\terror, code not resolved. Trying sleep 120 sec...\n";
							sleep(120);
						}
					} else { // Ручной ввод
						echo "\tCODE: ";
						
						$_REQUEST['vk_captcha_sid'] = $output['captcha']['sid'];
						$_REQUEST['vk_captcha_key'] = trim(fgets(STDIN));
						
						echo "\tok, code = ".$_REQUEST['vk_captcha_key']."\n";
					}
					continue;
				}
				
				sleep($i + 1);
			}
		} else { // Время поста уже верное
			echo "[OLD] QUEUE: #".$item->id." at ".date("d/m/Y H:i", $item->date)."\n";
		}
		
		++$limit;
		if ($limit >= min($SCHED_LIMIT, count($comments->postponed)))
			break;
	}
}

flock($lock_fp, LOCK_UN);
fclose($lock_fp);
