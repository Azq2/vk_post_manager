<?php
if (php_sapi_name() != "cli")
	die("Not a CLI :)\n");

require dirname(__FILE__)."/../inc/init.php";
$q = new Http;

while (true) {
	$n = 0;
	$dir = opendir(H."../tmp/post_queue/");
	while ($id = readdir($dir)) {
		$file = H."../tmp/post_queue/$id";
		
		if (!is_file($file))
			continue;
		
		echo "[queue: $id]\n";
		
		$images = [];
		
		$queue = json_decode(file_get_contents($file), true);
		if (!$queue) {
			unlink($file);
			continue;
		}
		
		$delete = false;
		if (time() - filectime($file) > 600) {
			$queue['out']['error'] = 'expired!';
			$delete = true;
		}
		
		if (!isset($queue['attaches']) && !$delete && !isset($queue['out'], $queue['out']['error'])) {
			++$n;
			
			$queue['out'] = [];
			$queue['total'] = count($queue['images']) + count($queue['documents']);
			$queue['downloaded'] = 0;
			$queue['uploaded'] = 0;
			file_put_contents($file, json_encode($queue));
			
			// Скачиваем картинки
			if (is_array($queue['images']) && !isset($queue['out']['error'])) {
				foreach ($queue['images'] as $i => $img) {
					echo "=> download: $img [".($i + 1)." / ".count($queue['images'])."]\n";
					
					$tmp_file = H."../tmp/doc_".md5($id.$img).".png";
					$time = microtime(true);
					system("wget ".escapeshellcmd($img)." -O ".escapeshellcmd($tmp_file), $x);
					$time = microtime(true) - $time;
					if ($x != 0 || !file_exists($tmp_file) || !filesize($tmp_file)) {
						$queue['out']['error'] = 'wget('.$img.')';
					} else {
						$images[] = [
							'path' => realpath($tmp_file), 
							'caption' => ''
						];
					}
					echo "==> OK (".round($time, 2)." s)\n";
					
					/*
					$time = microtime(true);
					$data = file_get_contents($img);
					$time = microtime(true) - $time;
					echo "==> OK (".round($time, 2)." s)\n";
					if ($data) {
						$tmp_file = H."../tmp/pic_".md5($id.$img).".png";
						$fp = fopen($tmp_file, "w");
						if ($fp) {
							flock($fp, LOCK_EX);
							fwrite($fp, $data);
							flock($fp, LOCK_UN);
							fclose($fp);
							
							$images[] = [
								'path' => realpath($tmp_file), 
								'caption' => ''
							];
						} else {
							$queue['out']['error'] = 'fopen('.$tmp_file.')';
							break;
						}
					} else {
						$queue['out']['error'] = 'file_get_contents('.$img.')';
						break;
					}
					*/
					
					++$queue['downloaded'];
					file_put_contents($file, json_encode($queue));
				}
			}
			
			$url = mysql_result(mysql_query("SELECT `url` FROM `vk_grabber_data_owners` WHERE `id` = 'VK_-".$queue['gid']."'"), 0);
			
			// Скачиваем документы
			if (is_array($queue['documents']) && !isset($queue['out']['error'])) {
				foreach ($queue['documents'] as $i => $img) {
					echo "=> download: $img [".($i + 1)." / ".count($queue['documents'])."]\n";
					
					$tmp_file = H."../tmp/doc_".md5($id.$img).".png";
					$time = microtime(true);
					system("wget ".escapeshellcmd($img)." -O ".escapeshellcmd($tmp_file), $x);
					$time = microtime(true) - $time;
					if ($x != 0 || !file_exists($tmp_file) || !filesize($tmp_file)) {
						$queue['out']['error'] = 'wget('.$img.')';
					} else {
						$images[] = [
							'path' => realpath($tmp_file), 
							'title' => $url ? "vk.com$url" : "image.gif", 
							'document' => true
						];
					}
					echo "==> OK (".round($time, 2)." s)\n";
					/*
					$time = microtime(true);
					$data = file_get_contents($img);
					$time = microtime(true) - $time;
					echo "==> OK (".round($time, 2)." s)\n";
					if ($data) {
						$tmp_file = H."../tmp/doc_".md5($id.$img).".png";
						$fp = fopen($tmp_file, "w");
						if ($fp) {
							flock($fp, LOCK_EX);
							fwrite($fp, $data);
							flock($fp, LOCK_UN);
							fclose($fp);
							
							$images[] = [
								'path' => realpath($tmp_file), 
								'title' => $url ? "vk.com$url" : "image.gif", 
								'document' => true
							];
						} else {
							$queue['out']['error'] = 'fopen('.$tmp_file.')';
							break;
						}
					} else {
						$queue['out']['error'] = 'file_get_contents('.$img.')';
						break;
					}
					*/
					
					++$queue['downloaded'];
					file_put_contents($file, json_encode($queue));
				}
			}
			
			if (!isset($queue['out']['error'])) {
				// Pагружаем в ВК
				$queue['attaches'] = pics_uploader($queue['out'], $q, $queue['gid'], $images, function ($att) use (&$queue, $file) {
					++$queue['uploaded'];
					echo "=> upload: $att [".$queue['uploaded']." / ".$queue['total']."]\n";
					file_put_contents($file, json_encode($queue));
				});
				file_put_contents($file, json_encode($queue));
			}
			
			if (isset($queue['out']['error'])) {
				echo "ERROR: ".$queue['out']['error']."\n";
				unset($queue['attaches']);
				file_put_contents($file, json_encode($queue));
			}
		} else {
			echo "=> already done\n";
		}
		
		foreach ($images as $img) {
			echo "=> delete: ".basename($img['path'], realpath(H."../"))."\n";
			unlink($img['path']);
		}
		
		if ($delete)
			unlink($file);
	}
	
	if (!$n)
		break;
}
