<?php
if (!\Z\User::instance()->can('user')) {
	header("Location: ?");
	exit;
}

$ALLOWED_APPS = array('catagochi');

$sub_action = array_val($_GET, 'sa', 'index');

switch ($sub_action) {
	case "edit":
		$edit_group_id = (int) array_val($_GET, 'group_id', 0);
		
		$old_app = Mysql::query("SELECT * FROM `vkapp` WHERE group_id = ?", $edit_group_id)
			->fetchAssoc();
		
		$group_id = array_val($_POST, 'group_id', $old_app ? $old_app['group_id'] : '');
		$name = array_val($_POST, 'name', $old_app ? $old_app['name'] : '');
		$token = array_val($_POST, 'token', $old_app ? $old_app['token'] : '');
		$handshake = array_val($_POST, 'handshake', $old_app ? $old_app['handshake'] : '');
		$secret = array_val($_POST, 'secret', $old_app ? $old_app['secret'] : '');
		$app = array_val($_POST, 'app', $old_app ? $old_app['app'] : '');
		
		$error = false;
		if ($_POST) {
			if (!\Z\User::instance()->can('user')) {
				$error = 'Гостевой доступ!';
			} else if (!$group_id || !$token || !$handshake || !$secret || !$name) {
			
			} else if ($old_app && $edit_group_id != $group_id && Mysql::query("SELECT * FROM `vkapp` WHERE group_id = ?", $group_id)->fetchAssoc()) {
				$error = 'Приложение с таким group_id уже существует!';
			} else if (!in_array($app, $ALLOWED_APPS)) {
				$error = 'Неправильный app. Доступны: '.implode(", ", $ALLOWED_APPS);
			} else {
				if ($old_app) {
					Mysql::query("
						UPDATE `vkapp` SET
							group_id = ?, 
							name = ?, 
							token = ?, 
							handshake = ?, 
							secret = ?, 
							app = ?
						WHERE
							group_id = ?
					", $group_id, $name, $token, $handshake, $secret, $app, $edit_group_id);
				} else {
					Mysql::query("
						INSERT INTO `vkapp` SET
							group_id = ?, 
							name = ?, 
							token = ?, 
							handshake = ?, 
							secret = ?, 
							app = ?
						ON DUPLICATE KEY UPDATE
							name = VALUES(name), 
							token = VALUES(token), 
							handshake = VALUES(handshake), 
							secret = VALUES(secret), 
							app = VALUES(app)
					", $group_id, $name, $token, $handshake, $secret, $app);
				}
				
				header("Location: ?a=vkapp/settings");
				exit;
			}
		}
		
		mk_page([
			'title'		=> 'Приложения', 
			'comm_tabs'	=> false, 
			'content'	=> Tpl::render("vkapp/settings/add.html", [
				'form_action'	=> Url::mk()->href(), 
				'name'			=> $name, 
				'group_id'		=> $group_id, 
				'token'			=> $token, 
				'handshake'		=> $handshake, 
				'secret'		=> $secret, 
				'app'			=> $app, 
				'error'			=> $error, 
				'is_edit'		=> $old_app, 
				'apps'			=> $ALLOWED_APPS
			])
		]);
	break;
	
	default:
		mk_page([
			'title'		=> 'Приложения', 
			'comm_tabs'	=> false, 
			'content'	=> Tpl::render("vkapp/settings.html", [
				'apps'			=> Mysql::query("SELECT * FROM `vkapp`")->fetchAll()
			])
		]);
	break;
}


