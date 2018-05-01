<?php
if ($_FILES && isset($_FILES['file'])) {
	$out = array();
	if (!\Z\User::instance()->can('user')) {
		$out['error'] = 'Гостевой доступ!';
	} elseif ($_FILES['file']['error']) {
		$out['error'] = 'Произошла странная ошибка под секретным номером #'.$_FILES['file']['error'];
	} elseif (!getimagesize($_FILES['file']['tmp_name'])) {
		$out['fatal'] = true;
		$out['error'] = 'Что за дичь? Не очень похоже на пикчу с котиком.';
	} else {
		$ext = explode(".", $_FILES['file']['name']);
		$ext = strtolower(end($ext));
		
		$attachments = pics_uploader($out, $q, $gid, [[
			'path'		=> $_FILES['file']['tmp_name'], 
			'caption'	=> isset($_POST['caption']) ? $_POST['caption'] : "", 
			'document'	=> $ext == "gif"
		]]);
		add_queued_wall_post($out, $attachments, isset($_POST['message']) ? $_POST['message'] : "");
	}
	
	mk_ajax($out);
	exit;
}

mk_page(array(
	'title' => 'Пользователи', 
	'content' => Tpl::render("multipicpost.html")
));
