<?php
$sub_action = array_val($_GET, 'sa', 'messages');

$tabs = switch_tabs([
	'param' => 'sa', 
	'tabs' => [
		'users'			=> 'Игроки', 
		'messages'		=> 'Сообщения', 
		'cats'			=> 'Котейки', 
		'shop'			=> 'Магазин', 
		'settings'		=> 'Настройки'
	], 
	'url' => Url::mk('?')->set('a', 'game/catlist'), 
	'active' => $sub_action
]);

switch ($sub_action) {
	case "shop_delete":
		$id		= (int) array_val($_GET, 'id', 0);
		$flag	= (int) array_val($_GET, 'flag', 0);
		$type	= array_val($_GET, 'type', 'food');
		
		if (\Z\User::instance()->can('user')) {
			$product = \Z\Catlist\Game\Shop\Product::createModel($id);
			if ($product) {
				$product->deleted = $flag;
				$product->save();
			}
		}
		
		header("Location: ".Url::mk()->remove('id')->set('sa', 'shop')->set('type', $type)->url());
	break;
	
	case "shop_add":
		$id				= isset($_POST['id']) ? (int) $_POST['id'] : 0;
		$title			= isset($_POST['title']) ? $_POST['title'] : '';
		$description	= isset($_POST['description']) ? $_POST['description'] : '';
		$amount			= isset($_POST['amount']) ? (int) $_POST['amount'] : 0;
		$price			= isset($_POST['price']) ? (float) $_POST['price'] : 0;
		$type			= array_val($_GET, 'type', 'food');
		
		$out = ['success' => false];
		$product = $id ? \Z\Catlist\Game\Shop\Product::createModel($id) : \Z\Catlist\Game\Shop\Product::createNew();
		$allow = [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_BMP];
		
		if (!\Z\User::instance()->can('user')) {
			$out['error'] = 'Гостевой доступ!';
		} elseif ($id && !$product) {
			$out['error'] = '#'.$id.' - не найдено!';
		} elseif (!$title) {
			$out['error'] = 'Название то где?!';
		} elseif (strlen($description) > 1024) {
			$out['error'] = 'А не много ли для описания?!';
		} elseif (!$id && !isset($_FILES['photo'])) {
			$out['error'] = 'Файл потерялся';
		} elseif (isset($_FILES['photo']) && $_FILES['photo']['error']) {
			$out['error'] = 'Странная ошибка под секретным номером #'.$_FILES['file']['error'];
		} elseif (isset($_FILES['photo']) && (!($size = getimagesize($_FILES['photo']['tmp_name'])) || !in_array($size[2], $allow))) {
			$out['error'] = 'Файл - не картинка =/';
		} else {
			if (isset($_FILES['photo'])) {
				$md5 = md5_file($_FILES['photo']['tmp_name']);
				$file = H.'../files/catlist/shop/'.$md5.'.jpg';
				
				if ($product->checkPhotoExists($md5, $type))
					$out['error'] = 'Уже есть товар с такой фоткой!!!';
				elseif (!image_resize($_FILES['photo']['tmp_name'], $file, 1024))
					$out['error'] = 'Ошибка обработки фотки!';
			} else {
				$size = [$product->width, $product->height];
				$md5 = $product->photo;
			}
			
			$out['width'] = $size[0];
			$out['height'] = $size[1];
			$out['photo'] = "$md5.jpg";
			
			if (!isset($out['error'])) {
				$product->title			= $title;
				$product->description	= $description;
				$product->width			= $size[0];
				$product->height		= $size[1];
				$product->price			= $price;
				$product->photo			= $md5;
				$product->amount		= $amount;
				$product->type			= $type;
				$product->save();
				
				$out['success'] = true;
			}
		}
		
		mk_ajax($out);
	break;
	
	case "shop":
		$type = array_val($_GET, 'type', 'food');
		$deleted = array_val($_GET, 'deleted', 0);
		
		mk_page([
			'title'		=> 'Кошачьи тамагочи - магазин', 
			'comm_tabs'	=> false, 
			'content'	=> Tpl::render("catlist/shop.html", [
				'tabs'			=> $tabs, 
				'type_tabs'	=> switch_tabs([
					'param' => 'type', 
					'tabs' => [
						'food'		=> 'Еда', 
						'furniture'	=> 'Мебель', 
						'toys'		=> 'Игрушки', 
					], 
					'url' => Url::mk('?')->set('a', 'game/catlist')->set('sa', 'shop'), 
					'active' => $type
				]), 
				'delete_tabs'	=> switch_tabs([
					'param' => 'deleted', 
					'tabs' => [
						0	=> 'Активные', 
						1	=> 'Удалённые', 
					], 
					'url' => Url::mk('?')->set('a', 'game/catlist')->set('sa', 'shop'), 
					'active' => $deleted
				]), 
				'type'			=> $type, 
				'items'			=> \Z\Catlist\Game\Shop\Product::findAll([
					'type'		=> $type, 
					'deleted'	=> $deleted
				]), 
				'form_action'	=> Url::mk()->set('sa', 'shop_add')->href(), 
			])
		]);
	break;
	
	case "settings":
		$settings = \Z\Catlist\Game\Settings::instance();
		
		if ($_POST && \Z\User::instance()->can('user')) {
			foreach ($_POST as $k => $v) {
				if (isset($settings->{$k}))
					$settings->{$k} = $v;
			}
			$settings->save();
			header("Location: ".Url::mk()->remove('id')->set('sa', 'settings')->url());
		}
		
		mk_page([
			'title'		=> 'Кошачьи тамагочи - настройки', 
			'comm_tabs'	=> false, 
			'content'	=> Tpl::render("catlist/settings.html", [
				'tabs'			=> $tabs, 
				'settings'		=> $settings->asArray(), 
				'form_action'	=> Url::mk()->set('sa', 'settings')->href(), 
			])
		]);
	break;
	
	case "cats_delete":
		$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
		
		if (\Z\User::instance()->can('user')) {
			$cats = Mysql::query("SELECT * FROM `vkapp_catlist_cats` WHERE `id` = ?", $id)
					->fetchObject();
			
			if ($cats) {
				Mysql::query("DELETE FROM `vkapp_catlist_cats` WHERE `id` = ?", $id);
				$used = Mysql::query("SELECT COUNT(*) FROM `vkapp_catlist_user_cats` WHERE `photo` = ? LIMIT 1", $cats->photo)
					->result();
				if (!$used)
					@unlink(H.'../files/catlist/cats/'.$cats->photo);
			}
		}
		
		header("Location: ".Url::mk()->remove('id')->set('sa', 'cats')->url());
	break;
	
	case "cats_add":
		$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
		$name = isset($_POST['name']) ? $_POST['name'] : '';
		$text = isset($_POST['text']) ? $_POST['text'] : '';
		$sex = isset($_POST['sex']) ? (int) $_POST['sex'] : 0;
		$price = isset($_POST['price']) ? (float) $_POST['price'] : 0;
		
		$out = ['success' => false];
		
		$cat = NULL;
		if ($id) {
			$cat = Mysql::query("SELECT * FROM `vkapp_catlist_cats` WHERE `id` = ?", $id)
				->fetchObject();
		}
		
		$allow = [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_BMP];
		
		if (!\Z\User::instance()->can('user')) {
			$out['error'] = 'Гостевой доступ!';
		} elseif ($id && !$cat) {
			$out['error'] = '#'.$id.' - не найдено!';
		} elseif (!$name) {
			$out['error'] = 'Имя то где?!';
		} elseif (strlen($text) > 1024) {
			$out['error'] = 'А не много ли для описания?!';
		} elseif (!$cat && !isset($_FILES['photo'])) {
			$out['error'] = 'Файл потерялся';
		} elseif (isset($_FILES['photo']) && $_FILES['photo']['error']) {
			$out['error'] = 'Странная ошибка под секретным номером #'.$_FILES['file']['error'];
		} elseif (isset($_FILES['photo']) && (!($size = getimagesize($_FILES['photo']['tmp_name'])) || !in_array($size[2], $allow))) {
			$out['error'] = 'Файл - не картинка =/';
		} else {
			if (isset($_FILES['photo'])) {
				$md5 = md5_file($_FILES['photo']['tmp_name']);
				$file = H.'../files/catlist/cats/'.$md5.'.jpg';
				
				if ($cat) {
					$exists = Mysql::query("SELECT COUNT(*) FROM `vkapp_catlist_cats` WHERE `photo` = ? AND `id` != ?", $md5, $id)
						->result();
				} else {
					$exists = Mysql::query("SELECT COUNT(*) FROM `vkapp_catlist_cats` WHERE `photo` = ?", $md5)
						->result();
				}
				
				if ($exists)
					$out['error'] = 'Уже есть котик с такой фоткой!!!';
				elseif (!image_resize($_FILES['photo']['tmp_name'], $file, 1024))
					$out['error'] = 'Ошибка обработки фотки!';
			} else {
				$size = [$cat->width, $cat->height];
				$md5 = $cat->photo;
			}
			
			$out['width'] = $size[0];
			$out['height'] = $size[1];
			$out['photo'] = "$md5.jpg";
			
			if (!isset($out['error'])) {
				if (!$cat) {
					Mysql::query("
						INSERT INTO `vkapp_catlist_cats` SET
							`name` = ?, 
							`descr` = ?, 
							`photo` = ?, 
							`width` = ?, 
							`height` = ?, 
							`sex` = ?, 
							`price` = ?
					", $name, $text, $md5, $size[0], $size[1], $sex, $price);
				} else {
					Mysql::query("
						UPDATE `vkapp_catlist_cats` SET
							`name` = ?, 
							`descr` = ?, 
							`photo` = ?, 
							`width` = ?, 
							`height` = ?, 
							`sex` = ?, 
							`price` = ?
						WHERE `id` = ?
					", $name, $text, $md5, $size[0], $size[1], $sex, $price, $id);
				}
				
				if ($cat) {
					$used = Mysql::query("SELECT COUNT(*) FROM `vkapp_catlist_user_cats` WHERE `photo` = ? LIMIT 1", $cat->photo)
						->result();
					if (!$used)
						@unlink(H.'../files/catlist/cats/'.$cat->photo);
				}
				
				$out['success'] = true;
			}
		}
		
		mk_ajax($out);
	break;
	
	case "message_delete":
		if (isset($_GET['ok']) && \Z\User::instance()->can('user')) {
			$id = isset($_GET['id']) ? $_GET['id'] : 0;
			Mysql::query("DELETE FROM `vkapp_catlist_messages` WHERE `id` = ?", $id);
		}
		mk_ajax(['success' => true]);
	break;
	
	case "message_add":
		$id = isset($_POST['id']) ? $_POST['id'] : '';
		$message = isset($_POST['text']) ? $_POST['text'] : '';
		
		$out = ['success' => false];
		if (!\Z\User::instance()->can('user')) {
			$out['error'] = "Гостевой доступ!";
		} elseif (preg_match("/^[\w\d_]+$/i", $id) && strlen($message)) {
			Mysql::query("
				INSERT INTO `vkapp_catlist_messages` SET
					`id` = ?, 
					`message` = ?
				ON DUPLICATE KEY UPDATE
					`message` = VALUES(`message`)
			", $id, $message);
			$out['succes'] = true;
		} else {
			$out['error'] = "А сообщение ввести?!";
		}
		
		if (isset($_GET['ajax'])) {
			mk_ajax($out);
		} else {
			header("Location: ".Url::mk()->set('sa', 'messages')->url());
		}
	break;
	
	case "cats":
		$type = array_val($_GET, 'type', '');
		
		if ($type == 'catshop') {
			$sql = 'WHERE `price` > 0';
		} else {
			$type = 'shelter';
			$sql = 'WHERE `price` = 0';
		}
		
		mk_page([
			'title'		=> 'Кошачьи тамагочи - котейки', 
			'comm_tabs'	=> false, 
			'content'	=> Tpl::render("catlist/cats.html", [
				'tabs'			=> $tabs, 
				'price_tabs'	=> switch_tabs([
					'param' => 'type', 
					'tabs' => [
						'shelter'	=> 'Приют', 
						'catshop'	=> 'Магазин', 
					], 
					'url' => Url::mk('?')->set('a', 'game/catlist')->set('sa', 'cats'), 
					'active' => $type
				]), 
				'type'			=> $type, 
				'cats'			=> Mysql::query("SELECT * FROM `vkapp_catlist_cats` $sql ORDER BY `id` DESC")->fetchAll(), 
				'form_action'	=> Url::mk()->set('sa', 'cats_add')->href(), 
			])
		]);
	break;
	
	default:
	case "messages":
		$filter_prefix = array_val($_GET, 'prefix', '');
		
		$sections = [
			'start'			=> 'Старт', 
			'shelter'		=> 'Приют', 
			'catshop'		=> 'Магазин котов', 
			'censored'		=> 'Цензура', 
		];
		
		$messages = [];
		foreach (Mysql::query("SELECT * FROM `vkapp_catlist_messages`")->fetchAll() as $msg) {
			$id = $msg['id'];
			$prefix = 'other';
			if (preg_match("/^([^_]+)/", $id, $m)) {
				$prefix = $m[1];
				if (!isset($sections[$prefix]) && isset($messages[$prefix]))
					$sections[$prefix] = $prefix;
			}
			$messages[$prefix][] = $msg;
		}
		
		$sections['other'] = 'Другие';
		
		foreach ($messages as $k => $m) {
			if (count($m) < 2) {
				$messages['other'][] = $m[0];
				unset($messages[$k]);
			}
		}
		
		foreach ($sections as $k => $v) {
			if (isset($messages[$k])) {
				$sections[$k] = "$v (".count($messages[$k]).")";
			} else {
				unset($sections[$k]);
			}
		}
		
		if ($filter_prefix && isset($messages[$filter_prefix])) {
			$messages = [$filter_prefix => $messages[$filter_prefix]];
		} else {
			$Efilter_prefix = '';
		}
		
		mk_page([
			'title'		=> 'Кошачьи тамагочи - сообщения', 
			'comm_tabs'	=> false, 
			'content'	=> Tpl::render("catlist/messages.html", [
				'msg_tabs'		=> switch_tabs([
					'param' => 'prefix', 
					'tabs' => array_merge([
						''				=> 'Все'
					], $sections), 
					'url' => Url::mk('?')->set('a', 'game/catlist')->set('sa', 'messages'), 
					'active' => $filter_prefix
				]), 
				'tabs'			=> $tabs, 
				'form_action'	=> Url::mk()->set('sa', 'message_add')->href(), 
				'messages'		=> $messages, 
				'sections'		=> $sections
			])
		]);
	break;
}
