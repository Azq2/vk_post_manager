<?php
namespace Smm\Controllers;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;

use \Smm\View\Widgets;

class VkWidgetsController extends \Smm\GroupController {
	protected static $AVAIL_WIDGETS = [
		'top_users'		=> 'Виджет ТОП юзеров'
	];
	
	protected static $AVAIL_BOTS = [
		'catificator'	=> 'Котификатор'
	];
	
	public function indexAction() {
		$base_url = Url::mk('/')->set('gid', $this->group['id']);
		
		$widgets = [];
		foreach (self::$AVAIL_WIDGETS as $widget_id => $title) {
			$widgets[] = [
				'title'			=> $title, 
				'url'			=> $base_url->set('a', 'vk_widgets/'.$widget_id)->href(), 
				'install_url'	=> $base_url->set('a', 'vk_widgets/install')->set('type', $widget_id)->href(), 
				'delete_url'	=> $base_url->set('a', 'vk_widgets/install')->set('type', '')->href(), 
				'installed'		=> $this->group['widget'] == $widget_id
			];
		}
		
		$bots = [];
		foreach (self::$AVAIL_BOTS as $bot_id => $title) {
			$bots[] = [
				'title'			=> $title, 
				'url'			=> $base_url->set('a', $bot_id.'/index')->href(), 
				'install_url'	=> $base_url->set('a', 'vk_widgets/bot_install')->set('type', $bot_id)->href(), 
				'delete_url'	=> $base_url->set('a', 'vk_widgets/bot_install')->set('type', '')->href(), 
				'installed'		=> $this->group['bot'] == $bot_id
			];
		}
		
		$this->title = 'Виджеты сообществ VK';
		$this->content = View::factory('vk_widgets/index', [
			'widgets'		=> $widgets, 
			'bots'			=> $bots
		]);
	}
	
	public function bot_installAction() {
		$type = $_GET['type'] ?? '';
		
		DB::begin();
		
		DB::update('vk_groups')
			->set([
				'bot'		=> isset(self::$AVAIL_BOTS[$type]) ? $type : ''
			])
			->where('id', '=', $this->group['id'])
			->execute();
		
		DB::commit();
		
		$base_url = Url::mk('/')
			->set('gid', $this->group['id'])
			->set('a', 'vk_widgets/index');
		
		return $this->redirect($base_url->url());
	}
	
	public function installAction() {
		$type = $_GET['type'] ?? '';
		
		DB::begin();
		
		switch ($type) {
			case "top_users":
				DB::insert('vk_widget_top_users')
					->ignore()
					->set([
						'group_id'		=> $this->group['id']
					])
					->execute();
			break;
		}
		
		DB::update('vk_groups')
			->set([
				'widget'		=> isset(self::$AVAIL_WIDGETS[$type]) ? $type : ''
			])
			->where('id', '=', $this->group['id'])
			->execute();
		
		DB::commit();
		
		$base_url = Url::mk('/')
			->set('gid', $this->group['id'])
			->set('a', 'vk_widgets/index');
		
		return $this->redirect($base_url->url());
	}
	
	public function top_users_blacklistAction() {
		$user_id = $_GET['user_id'] ?? 0;
		
		if ($_GET['delete'] ?? false) {
			DB::delete('vk_widget_top_users_blacklist')
				->where('group_id', '=', $this->group['id'])
				->where('user_id', '=', $user_id)
				->execute();
		} else {
			DB::insert('vk_widget_top_users_blacklist')
				->ignore()
				->set([
					'group_id'		=> $this->group['id'], 
					'user_id'		=> $user_id
				])
				->execute();
		}
		
		$base_url = Url::mk('/')
			->set('gid', $this->group['id'])
			->set('a', 'vk_widgets/top_users');
		
		$redirect = $base_url->url();
		return $this->redirect($redirect);
	}
	
	public function top_usersAction() {
		$widget = DB::select()
			->from('vk_widget_top_users')
			->where('group_id', '=', $this->group['id'])
			->execute()
			->current();
		
		if (!$widget)
			return $this->error('Виджет не установлен.');
		
		$tiles = $widget['tiles'] ? explode(",", $widget['tiles']) : [];
		
		while (count($tiles) < $widget['tiles_n'])
			$tiles[] = "";
		
		$error_settings = false;
		$error_upload = false;
		
		$base_url = Url::mk('/')
			->set('gid', $this->group['id'])
			->set('a', 'vk_widgets/top_users');
		
		$ALLOWED_SIZES = ['480x480', '480x720'];
		
		// Common settings
		if ($_POST['do_upload_images'] ?? false) {
			$file_id = intval($_POST['file_id']);
			
			$new_path = APP.'www/files/vk_widget/top_users_'.$this->group['id'].'_'.$file_id.'.png';
			
			if (!file_exists(dirname($new_path))) {
				umask(0);
				mkdir(dirname($new_path), 0777, true);
			}
			
			if ($file_id < 0 || $file_id >= $widget['tiles_n']) {
				$error_upload = 'Ошибка в параметрах.';
			} elseif (!isset($_FILES['file'])) {
				$error_upload = 'Файл не обнаружен!';
			} elseif ($_FILES['file']['error']) {
				$error_upload = 'Ошибка загрузки #'.$_FILES['file']['error'];
			} elseif ($_FILES['file']['size'] >= 1024 * 1024) {
				$error_upload = 'Слишком большой файл.';
			} else {
				$image = imagecreatefrompng($_FILES['file']['tmp_name']);
				
				$width = $image ? imagesx($image) : 0;
				$height = $image ? imagesy($image) : 0;
				
				if (!$image) {
					$error_upload = 'Картинка должна быть в формате PNG.';
				} elseif (!in_array($width."x".$height, $ALLOWED_SIZES)) {
					$error_upload = 'Неправильный размер. Допустимы только: '.implode(', ', $ALLOWED_SIZES).' (загружен: '.$width.'x'.$height.')';
				} elseif (!move_uploaded_file($_FILES['file']['tmp_name'], $new_path)) {
					$error_upload = 'Невозможно сохранить файл на диск.';
				} else {
					$tiles[$file_id] = basename($new_path);
					
					DB::update('vk_widget_top_users')
						->set([
							'tiles'					=> implode(",", $tiles), 
							'mtime'					=> time()
						])
						->where('group_id', '=', $widget['group_id'])
						->execute();
					
					$redirect = $base_url->url();
					return $this->redirect($redirect);
				}
			}
		}
		
		// Common settings
		elseif ($_POST['do_save_settings'] ?? false) {
			$widget['tiles_n']			= intval($_POST['tiles_n'] ?? 0);
			$widget['cost_likes']		= intval($_POST['cost_likes'] ?? 0);
			$widget['cost_comments']	= intval($_POST['cost_comments'] ?? 0);
			$widget['title']			= $_POST['title'] ?? '';
			$widget['days']				= intval($_POST['days'] ?? 0);
			$widget['tile_title']		= $_POST['tile_title'] ?? '';
			$widget['tile_descr']		= $_POST['tile_descr'] ?? '';
			$widget['tile_link']		= $_POST['tile_link'] ?? '';
			
			$widget['tiles_n']	= min(20, max(0, $widget['tiles_n']));
			$widget['days']		= min(365, max(0, $widget['days']));
			
			DB::update('vk_widget_top_users')
				->set([
					'cost_likes'			=> $widget['cost_likes'], 
					'cost_comments'			=> $widget['cost_comments'], 
					'title'					=> $widget['title'], 
					'days'					=> $widget['days'], 
					'tile_title'			=> $widget['tile_title'], 
					'tile_descr'			=> $widget['tile_descr'], 
					'tile_link'				=> $widget['tile_link'], 
					'tiles_n'				=> $widget['tiles_n'], 
					'mtime'					=> time()
				])
				->where('group_id', '=', $widget['group_id'])
				->execute();
			
			$redirect = $base_url->url();
			return $this->redirect($redirect);
		}
		
		$date_to = time();
		$date_from = $date_to - 3600 * 24 * ($widget['days'] - 1);
		
		$formula = '(SUM(likes) * '.$widget['cost_likes'].' + SUM(comments_meaningful) * '.$widget['cost_comments'].')';
		
		$blacklist = DB::select()
			->from('vk_widget_top_users_blacklist')
			->where('group_id', '=', $this->group['id'])
			->execute()
			->asArray(NULL, 'user_id');
		
		$users = DB::select(
			'user_id', 
			['SUM(likes)', 'likes'], 
			['SUM(comments_meaningful)', 'comments_meaningful'], 
			[$formula, 'points']
		)
			->from('vk_activity_stat')
			->where('date', 'BETWEEN', [date("Y-m-d", $date_from), date("Y-m-d", $date_to)])
			->where('owner_id', '=', -$this->group['id'])
			->where('user_id', '>', 0)
			->order('points', 'DESC')
			->group('user_id')
			->limit(100);
		
		/*
		if ($blacklist)
			$users->where('user_id', 'NOT IN', $blacklist);
		*/
		
		$users = $users->execute()->asArray('user_id');
	
		$api = new \Smm\VK\API(\Smm\Oauth::getAccessToken('VK'));
		
		$res = $api->exec("users.get", [
			"user_ids"		=> implode(",", array_keys($users)), 
			"fields"		=> "photo_50,screen_name"
		]);
		
		$vk_users = [];
		foreach ($res->response as $u)
			$vk_users[$u->id] = $u;
		
		$users_list = [];
		foreach ($users as $id => $user) {
			$vk_user = $vk_users[$id] ?? (object) [
				'photo_50'			=> false, 
				'first_name'		=> "Пользователь", 
				'last_name'			=> "id$id", 
				'screen_name'		=> ""
			];
			
			$users_list[] = [
				'avatar'			=> $vk_user->photo_50, 
				'name'				=> $vk_user->first_name.' '.$vk_user->last_name, 
				'url'				=> "https://vk.com/".($vk_user->screen_name ?: "id$id"), 
				'points'			=> $user['points'], 
				'likes'				=> $user['likes'], 
				'comments'			=> $user['comments_meaningful'], 
				'blacklisted'		=> in_array($id, $blacklist), 
				'blacklist_url'		=> $base_url->copy()
					->set('a', 'vk_widgets/top_users_blacklist')
					->set('user_id', $id)
					->href(), 
				'unblacklist_url'	=> $base_url->copy()
					->set('a', 'vk_widgets/top_users_blacklist')
					->set('delete', 1)
					->set('user_id', $id)
					->href()
			];
		}
		
		$images = [];
		foreach ($tiles as $tile) {
			if ($tile) {
				list ($width, $height) = getimagesize(APP.'www/files/vk_widget/'.$tile);
				$images[] = [
					'src'		=> '/files/vk_widget/'.$tile.'?'.$widget['mtime'], 
					'width'		=> $width, 
					'height'	=> $height, 
				];
			} else {
				$images[] = [
					'src'		=> false, 
					'width'		=> 480, 
					'height'	=> 480, 
				];
			}
		}
		
		$this->title = 'Виджеты сообществ VK : ТОП юзеров';
		$this->content = View::factory('vk_widgets/top_users', [
			'error_settings'	=> $error_settings, 
			'error_upload'		=> $error_upload, 
			'cost_likes'		=> $widget['cost_likes'], 
			'cost_comments'		=> $widget['cost_comments'], 
			'images'			=> $images, 
			'title'				=> htmlspecialchars($widget['title']), 
			'days'				=> $widget['days'], 
			'tiles_n'			=> $widget['tiles_n'], 
			'tile_title'		=> htmlspecialchars($widget['tile_title']), 
			'tile_descr'		=> htmlspecialchars($widget['tile_descr']), 
			'tile_link'			=> htmlspecialchars($widget['tile_link']), 
			'allowed_sizes'		=> $ALLOWED_SIZES, 
			'users_list'		=> $users_list
		]);
	}
	
	public function accessControl() {
		return [
			'*'		=> ['auth_required' => true, 'users' => 'admin'], 
		];
	}
}
