<?php
if (php_sapi_name() != "cli")
	die("Not a CLI :)\n");

umask(0);

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
		
		$image_types = ['image/png', 'image/jpg', 'image/jpeg', 'image/bmp', 'image/webp'];
		
		if (!isset($queue['attaches']) && !$delete && !isset($queue['out'], $queue['out']['error'])) {
			++$n;
			
			$queue['out'] = [];
			$queue['total'] = count($queue['images']) + count($queue['documents']) + count($queue['files']);
			$queue['downloaded'] = 0;
			$queue['uploaded'] = 0;
			file_put_contents($file, json_encode($queue));
			
			if ($queue['cover']) {
				$cover = imagecreatefromfile($queue['cover']);
				if (!$cover)
					$queue['out']['error'] = 'GD не смог прочитать обложку.';
				
				unlink($queue['cover']);
			}
			
			// Скачиваем хуй пойми что
			if (is_array($queue['files']) && !isset($queue['out']['error'])) {
				foreach ($queue['files'] as $i => $img) {
					echo "=> download: $img [".($i + 1)." / ".count($queue['files'])."]\n";
					
					$tmp_file = realpath(H."../tmp/download")."/file_".md5($id.$img).".bin";
					$time = microtime(true);
					system("wget --tries 10 ".escapeshellarg($img)." -O ".escapeshellarg($tmp_file), $x);
					$time = microtime(true) - $time;
					if ($x != 0 || !file_exists($tmp_file) || !filesize($tmp_file)) {
						$queue['out']['error'] = 'wget('.$img.') = '.$x;
						break;
					} else {
						$mime = strtolower(mime_content_type($tmp_file));
						
						if (in_array($mime, $image_types)) {
							if ($cover) {
								$error = image_watermark($tmp_file, $cover, $queue['offset']);
								if ($error) {
									unlink($tmp_file);
									$queue['out']['error'] = $error;
									break;
								}
							}
							
							// Картинки
							$images[] = [
								'path' => realpath($tmp_file), 
								'caption' => ''
							];
						} else if ($mime == 'image/gif') {
							// Документы
							$images[] = [
								'path' => realpath($tmp_file), 
								'title' => $url ? "vk.com$url" : "image.gif", 
								'document' => true
							];
						} else {
							$queue['out']['error'] = 'error ('.$img.') = неизвестный тип файла ['.$mime.']';
							break;
						}
					}
					echo "==> OK (".round($time, 2)." s)\n";
					
					++$queue['downloaded'];
					file_put_contents($file, json_encode($queue));
				}
			}
			file_put_contents($file, json_encode($queue));
			
			// Скачиваем картинки
			if (is_array($queue['images']) && !isset($queue['out']['error'])) {
				foreach ($queue['images'] as $i => $img) {
					echo "=> download: $img [".($i + 1)." / ".count($queue['images'])."]\n";
					
					$tmp_file = realpath(H."../tmp/download")."/doc_".md5($id.$img).".png";
					$time = microtime(true);
					system("wget --tries 10 ".escapeshellarg($img)." -O ".escapeshellarg($tmp_file), $x);
					$time = microtime(true) - $time;
					if ($x != 0 || !file_exists($tmp_file) || !filesize($tmp_file)) {
						$queue['out']['error'] = 'wget('.$img.') = '.$x;
						break;
					} else {
						if ($cover) {
							$error = image_watermark($tmp_file, $cover, $queue['offset']);
							if ($error) {
								unlink($tmp_file);
								$queue['out']['error'] = $error;
								break;
							}
						}
						
						$images[] = [
							'path' => realpath($tmp_file), 
							'caption' => ''
						];
					}
					echo "==> OK (".round($time, 2)." s)\n";
					
					++$queue['downloaded'];
					file_put_contents($file, json_encode($queue));
				}
			}
			file_put_contents($file, json_encode($queue));
			
			$url = Mysql::query("SELECT `url` FROM `vk_grabber_data_owners` WHERE `id` = 'VK_-".$queue['gid']."'")->result();
			
			// Скачиваем документы
			if (is_array($queue['documents']) && !isset($queue['out']['error'])) {
				foreach ($queue['documents'] as $i => $img) {
					echo "=> download: $img [".($i + 1)." / ".count($queue['documents'])."]\n";
					
					$tmp_file = realpath(H."../tmp/download")."/doc_".md5($id.$img).".png";
					$time = microtime(true);
					system("wget ".escapeshellarg($img)." -O ".escapeshellarg($tmp_file), $x);
					$time = microtime(true) - $time;
					if ($x != 0 || !file_exists($tmp_file) || !filesize($tmp_file)) {
						$queue['out']['error'] = 'wget('.$img.') = '.$x;
						break;
					} else {
						$images[] = [
							'path' => realpath($tmp_file), 
							'title' => $url ? "vk.com$url" : "image.gif", 
							'document' => true
						];
					}
					echo "==> OK (".round($time, 2)." s)\n";
					
					++$queue['downloaded'];
					file_put_contents($file, json_encode($queue));
				}
			}
			file_put_contents($file, json_encode($queue));
			
			$attaches = [];
			$attaches_ids = [];
			if (!isset($queue['out']['error'])) {
				// Pагружаем в ВК
				pics_uploader($queue['out'], $q, $queue['gid'], $images, function ($att, $att_obj) use (&$queue, $file, &$attaches, &$attaches_ids) {
					++$queue['uploaded'];
					
					$attaches_ids[] = $att;
					
					if (strpos($att, "doc") === 0) {
						$attaches[] = (object) [
							'type'	=> 'doc', 
							'doc'	=> $att_obj
						];
					} else {
						$attaches[] = (object) [
							'type'	=> 'photo', 
							'photo'	=> $att_obj
						];
					}
					
					echo "=> upload: $att [".$queue['uploaded']." / ".$queue['total']."]\n";
					file_put_contents($file, json_encode($queue));
				});
				file_put_contents($file, json_encode($queue));
			}
			
			if (isset($queue['out']['error'])) {
				echo "ERROR: ".$queue['out']['error']."\n";
			} else {
				$queue['attaches'] = $attaches_ids;
				$queue['attaches_data'] = vk_normalize_attaches((object) [
					'attachments' => $attaches
				]);
			}
			file_put_contents($file, json_encode($queue));
		} else {
			echo "=> already done\n";
		}
		
		foreach ($images as $img) {
			echo "=> delete: ".basename($img['path'], realpath(H."../"))."\n";
			if (file_exists($img['path']))
				unlink($img['path']);
		}
		
		if ($delete)
			unlink($file);
	}
	
	if (!$n)
		break;
}

function image_watermark($tmp_file, $cover, $offset) {
	$image = imagecreatefromfile($tmp_file);
	if (!$image)
		return 'GD не смог откыть '.$img;
	
	if (imagesy($image) < imagesy($cover)) {
		$new_image = imagecreatetruecolor(imagesx($cover), imagesy($cover));
		imagecopyresampled($new_image, $image, 0, $offset, 0, 0, imagesx($image), imagesy($image), imagesx($image), imagesy($image));
		imagecopyresampled($new_image, $cover, 0, 0, 0, 0, imagesx($new_image), imagesy($new_image), imagesx($cover), imagesy($cover));
		$image = $new_image;
	} else {
		imagecopyresampled($image, $cover, 0, 0, 0, 0, imagesx($image), imagesy($image), imagesx($cover), imagesy($cover));
	}
	
	if (!imagepng($image, $tmp_file))
		return 'GD не смог сохранить '.$img;
	
	return false;
}
