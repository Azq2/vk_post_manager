<?php
$out = [];

$type = array_val($_REQUEST, 'type', '');

switch ($type) {
	// Загрузка по URL
	case "url":
		$id = isset($_REQUEST['id']) ? preg_replace("/[^a-f0-9]/", "", $_REQUEST['id']) : '';
		
		if (!\Z\User::instance()->can('user')) {
			$out['error'] = 'Гостевой доступ!';
			$id = false;
		} elseif (!$id) {
			$images		= isset($_REQUEST['images']) ? $_REQUEST['images'] : [];
			$documents	= isset($_REQUEST['documents']) ? $_REQUEST['documents'] : [];
			$files		= isset($_REQUEST['files']) ? $_REQUEST['files'] : [];
			$cover		= isset($_FILES['cover']) ? $_FILES['cover'] : [];
			
			$cover_path = false;
			if ($cover) {
				if ($cover['error']) {
					$out['error'] = 'Ошибка загрузки обложки по секретным номероv #'.$cover['error'];
				} else if (!getimagesize($cover['tmp_name'])) {
					$out['error'] = 'Обложка не является картинкой!';
				} else {
					$cover_path = '../tmp/cover_'.md5_file($cover['tmp_name']);
					if (!file_exists(H.$cover_path)) {
						if (!move_uploaded_file($cover['tmp_name'], H.$cover_path)) {
							$out['error'] = 'Ошибка переноса обложки.';
						}
					}
				}
			}
			
			if (!isset($out['error']) && ($images || $documents || $files)) {
				$msg = json_encode([
					'images'	=> $images, 
					'documents'	=> $documents, 
					'files'		=> $files, 
					'gid'		=> $gid, 
					'cover'		=> $cover_path
				]);
				
				$id = md5($msg);
				if (!file_exists(H.'../tmp/post_queue/'.$id)) {
					file_put_contents(H.'../tmp/post_queue/'.$id, $msg);
					chmod(H.'../tmp/post_queue/'.$id, 0666);
				}
			} else {
				$out['error'] = 'Не выбраны файлы.';
			}
		}
		
		if ($id) {
			$out['id'] = $id;
			if (file_exists(H.'../tmp/post_queue/'.$id)) {
				$status = json_decode(file_get_contents(H.'../tmp/post_queue/'.$id), true);
				if (isset($status['out'], $status['out']['error'])) {
					$out['error'] = $status['out']['error'];
				} elseif (isset($status['attaches'])) {
					$out['attaches'] = $status['attaches'];
					$out['data'] = $status['attaches_data'];
				} else {
					$out['queue'] = $status;
				}
			} else {
				$out['error'] = 'Очередь скачивания файла уже удалена. ('.$id .')';
			}
		}
	break;
	
	// Загрузка файла
	case "file":
	default:
		if (!\Z\User::instance()->can('user')) {
			$out['error'] = 'Гостевой доступ!';
		} elseif ($_FILES && isset($_FILES['file'])) {
			if ($_FILES['file']['error']) {
				$out['error'] = 'Произошла странная ошибка под секретным номером #'.$_FILES['file']['error'];
			} elseif (!getimagesize($_FILES['file']['tmp_name'])) {
				$out['error'] = 'Что за дичь? Не очень похоже на пикчу с котиком.';
			} else {
				$ext = explode(".", $_FILES['file']['name']);
				$ext = strtolower(end($ext));
				
				$attachments = pics_uploader($out, $q, $gid, [[
					'path'		=> $_FILES['file']['tmp_name'], 
					'caption'	=> isset($_POST['caption']) ? $_POST['caption'] : "", 
					'document'	=> $ext == "gif"
				]], function ($key, $file) use (&$out) {
					if (strpos($key, "doc") === 0) {
						$att = (object) [
							'type'	=> 'doc', 
							'doc'	=> $file
						];
					} else {
						$att = (object) [
							'type'	=> 'photo', 
							'photo'	=> $file
						];
					}
					
					$out['id'] = $key;
					$out['file'] = $file;
					$out['data'] = vk_normalize_attaches((object) [
						'attachments' => [$att]
					]);
				});
				$out['success'] = true;
			}
		} else {
			$out['error'] = 'Файл не найден в запросе! o_O';
		}
	break;
}

mk_ajax($out);
