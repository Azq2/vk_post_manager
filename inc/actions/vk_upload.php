<?php
$out = array();

if ($_FILES && isset($_FILES['file'])) {
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
			$out['file'] = Tpl::render("widgets/attach.html", [
				'att'	=> $att, 
				'list'	=> 'suggests'
			]);
		});
		$out['success'] = true;
	}
} else {
	$out['error'] = 'Файл не найден в запросе! o_O';
}

mk_ajax($out);
