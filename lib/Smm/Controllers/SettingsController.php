<?php
namespace Smm\Controllers;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;

use \Smm\View\Widgets;

class SettingsController extends \Smm\GroupController {
	public function groupsAction() {
		$this->title = 'Настройки : Группы';
		
		$error = false;
		
		$base_url = Url::mk('/')->set('gid', $this->group['id']);
		
		$url = $_POST['url'] ?? '';
		
		if ($_POST['do_add'] ?? false) {
			$id = false;
			if (preg_match("#/(public|club)(\d+)#i", $url, $m)) {
				$id = $m[2];
			} else if (preg_match("#/([\w\d_-]+)#i", $url, $m)) {
				$id = $m[1];
			}
			
			if ($id) {
				$api = new \Smm\VK\API(\Smm\Oauth::getAccessToken('VK'));
				$res = $api->exec("groups.getById", [
					"group_ids"		=> $id
				]);
				
				if (!$res->success()) {
					$error = "API ответил ошибкой: ".$res->error()." (group_ids=".htmlspecialchars($id).")";
				} else {
					$group_id = $res->response[0]->id;
					$group_name = htmlspecialchars($res->response[0]->name);
					
					$pos = DB::select(['MAX(pos)', 'pos'])
						->from('vk_groups')
						->execute()
						->get('pos', -1) + 1;
					
					DB::insert('vk_groups')
						->set([
							'id'						=> $group_id, 
							'name'						=> $group_name, 
							'pos'						=> $pos, 
							'telegram_channel_id'		=> 0, 
							'deleted'					=> 0
						])
						->onDuplicateSetValues('name')
						->onDuplicateSetValues('deleted')
						->execute();
					
					$redirect = $base_url->copy()->set('a', 'settings/groups')->url();
					return $this->redirect($redirect);
				}
			} else {
				$error = "Некорректная ссылка на группу, невозможно распарсить ID.";
			}
		} else if ($_POST['do_save'] ?? false) {
			$id = $_POST['id'] ?? 0;
			$pos = $_POST['pos'] ?? 0;
			$name = htmlspecialchars(trim($_POST['name'] ?? ''));
			
			$group = DB::select()
				->from('vk_groups')
				->where('id', '=', $id)
				->execute()
				->current();
			
			if ($group) {
				DB::update('vk_groups')
					->set([
						'name'		=> strlen($name) ? $name : $group['name'], 
						'pos'		=> $pos
					])
					->where('id', '=', $id)
					->execute();
				
				$redirect = $base_url->copy()->set('a', 'settings/groups')->url();
				return $this->redirect($redirect);
			} else {
				$error = 'Группа не найдена.';
			}
		} else if ($_POST['do_delete'] ?? false) {
			$id = $_POST['id'] ?? 0;
			
			$group = DB::select()
				->from('vk_groups')
				->where('id', '=', $id)
				->execute()
				->current();
			
			if ($group) {
				DB::begin();
				
				DB::update('vk_groups')
					->set([
						'deleted'	=> 1
					])
					->where('id', '=', $id)
					->execute();
				
				DB::delete('vk_grabber_selected_sources')
					->where('group_id', '=', $id)
					->execute();
				
				DB::delete('vk_grabber_blacklist')
					->where('group_id', '=', $id)
					->execute();
				
				DB::delete('vk_posts_queue')
					->where('group_id', '=', $id)
					->execute();
				
				DB::delete('vk_globals')
					->where('group_id', '=', $id)
					->execute();
				
				DB::commit();
				
				$redirect = $base_url->copy()->set('a', 'settings/groups')->url();
				return $this->redirect($redirect);
			} else {
				$error = 'Группа не найдена.';
			}
		}
		
		$groups = DB::select()
			->from('vk_groups')
			->where('deleted', '=', 0)
			->order('pos', 'ASC')
			->execute();
		
		$groups_list = [];
		foreach ($groups as $group) {
			$groups_list[] = [
				'id'		=> $group['id'], 
				'url'		=> "https://vk.com/public".$group['id'], 
				'name'		=> $group['name'], 
				'pos'		=> $group['pos'], 
			];
		}
		
		$this->content = View::factory('settings/groups', [
			'groups_list'			=> $groups_list, 
			'error'					=> $error, 
			'url'					=> htmlspecialchars($url)
		]);
	}
	
	public function auth_saveAction() {
		$this->mode('json');
		
		$key				= $_REQUEST['type'] ?? '';
		$code				= $_REQUEST['code'] ?? '';
		$code_2fa			= $_REQUEST['code_2fa'] ?? '';
		$login				= $_REQUEST['login'] ?? '';
		$password			= $_REQUEST['password'] ?? '';
		$captcha_key		= $_REQUEST['captcha_key'] ?? '';
		$captcha_sid		= $_REQUEST['captcha_sid'] ?? '';
		$force_sms			= $_REQUEST['force_sms'] ?? '';
		
		$oauth_users = \Z\Config::get('oauth_users');
		
		$this->content['success'] = false;
		
		if (!isset($oauth_users[$key])) {
			$this->content['error'] = 'Неизвестный тип авторизации.';
			return;
		}
		
		$oauth = $oauth_users[$key];
		
		switch ($oauth['type']) {
			case "VK":
				$api = new \Smm\VK\API([
					'client'		=> $oauth['client']
				]);
				
				$params = [];
				
				if ($captcha_sid) {
					$params['captcha_key'] = $captcha_key;
					$params['captcha_sid'] = $captcha_sid;
				}
				
				if ($code_2fa)
					$params['code'] = $code_2fa;
				
				if ($force_sms)
					$params['force_sms'] = $force_sms;
				
				$device_id = substr(md5("vk_client:$login:$password"), 0, 16);
				
				if ($oauth['auth'] == 'code') {
					$result = $api->loginOauthCode($code, $params);
				} else {
					$params['device_id'] = $device_id;
					$result = $api->loginOauthDirect($login, $password, $params);
				}
				
				if ($result->success()) {
					$user_api = new \Smm\VK\API([
						'client'			=> $oauth['client'], 
						'access_token'		=> $result->access_token, 
						'secret'			=> $result->secret ?? ''
					]);
					
					switch ($oauth['client']) {
						case "vk_admin":
							// Simulate some api requests
							$api_result = $user_api->exec('execute.getUserLogin', [
								'user_ids'			=> $result->user_id, 
								'fields'			=> 'uid, nickname, screen_name, sex, bdate, city, country,photo, photo_medium_rec, timezone, photo_50, photo_100, photo_200_orig, photo_max, has_mobile, contacts, education, online, counters, relation, last_seen, status, can_write_private_message, can_see_all_posts, can_post, universities, activities, interests, music, movies, tv, books, games, about, quotes', 
							]);
							
							if (isset($api_result->response->admin_groups->items)) {
								foreach ($api_result->response->admin_groups->items as $g) {
									$user_api->exec('execute.getMainUserData', [
										'group_id'			=> $g->id, 
										'fields'			=> 'uid, nickname, screen_name, sex, bdate, city, country,photo, photo_medium_rec, timezone, photo_50, photo_100, photo_200_orig, photo_max, has_mobile, contacts, education, online, counters, relation, last_seen, status, can_write_private_message, can_see_all_posts, can_post, universities, activities, interests, music, movies, tv, books, games, about, quotes', 
									]);
									$user_api->exec('execute.getGroupNew', [
										'group_id'			=> $g->id, 
										'filter'			=> 'all', 
										'fields'			=> 'uid, nickname, screen_name, sex, bdate, city, country,photo, photo_medium_rec, timezone, photo_50, photo_100, photo_200_orig, photo_max, has_mobile, contacts, education, online, counters, relation, last_seen, status, can_write_private_message, can_see_all_posts, can_post, universities, activities, interests, music, movies, tv, books, games, about, quotes', 
									]);
								}
							}
							
							DB::insert('vk_oauth')
								->set([
									'type'			=> $key, 
									'access_token'	=> $result->access_token, 
									'secret'		=> $result->secret ?? '', 
									'refresh_token'	=> $result->refresh_token ?? '', 
									'expires'		=> $result->expires_in ?? 0, 
								])
								->onDuplicateSetValues('access_token')
								->onDuplicateSetValues('refresh_token')
								->onDuplicateSetValues('secret')
								->onDuplicateSetValues('expires')
								->execute();
							
							$this->content['success'] = true;
						break;
						
						case "vk_android":
							// Simulate some api requests
							$user_api->exec('execute.getUserInfo', [
								'fields'				=> 'photo_100,photo_50,exports,country,sex,status,bdate,first_name_gen,last_name_gen,verified', 
								'info_fields'			=> 'audio_ads,audio_background_limit,country,debug_available,gif_autoplay,https_required,intro,lang,money_clubs_p2p,money_p2p,money_p2p_params,music_intro,audio_restrictions,profiler_settings,raise_to_record_enabled,stories,masks,subscriptions,support_url,video_autoplay,video_player,vklive_app,community_comments', 
								'androidVersion'		=> 17, 
								'androidManufacturer'	=> 'LENOVO', 
								'androidModel'			=> 'Lenovo A850', 
								'func_v'				=> 9
							]);
							
							$user_api->exec('internal.getNotifications', [
								'device'				=> 'Lenovo A850', 
								'vendor'				=> 'LENOVO', 
								'system'				=> 0, 
								'os'					=> '17,4.2.2', 
								'app_version'			=> '1193', 
								'locale'				=> 'ru', 
								'ads_device_id'			=> -1
							]);
							
							$user_api->exec('internal.getUserNotifications', [
								'device'				=> 'Lenovo A850', 
								'vendor'				=> 'LENOVO', 
								'system'				=> 0, 
								'os'					=> '17,4.2.2', 
								'app_version'			=> '1193', 
								'locale'				=> 'ru', 
								'ads_device_id'			=> -1, 
								'fields'				=> 'photo_100,photo_50', 
								'extended'				=> 1, 
								'photo_sizes'			=> 1, 
								'connection_type'		=> 'wifi', 
								'connection_subtype'	=> 'unknown', 
								'user_options'			=> '{"autoplay_video":{"value":"always"},"autoplay_gif":{"value":"always"}}'
							]);
							
							$user_api->exec('execute.getNewsfeedSmart', [
								'func_v'				=> 2, 
								'connection_type'		=> 'wifi', 
								'connection_subtype'	=> 'unknown', 
								'user_options'			=> '{"autoplay_video":{"value":"always"},"autoplay_gif":{"value":"always"}}', 
								'start_from'			=> 0, 
								'count'					=> 20, 
								'fields'				=> 'id,first_name,first_name_dat,last_name,last_name_dat,sex,screen_name,photo_50,photo_100,online,video_files', 
								'forced_notifications'	=> 1, 
								'filters'				=> 'post,photo,photo_tag,friends_recomm,app_widget,ads_app,ads_site,ads_post,ads_app_slider,ads_app_video,ads_post_pretty_cards', 
								'photo_sizes'			=> 1, 
								'device_info'			=> '{"device_model":"Lenovo A850","app_version":"4.13.1","manufacturer":"LENOVO","system_version":"4.2.2","system_name":"Android"}', 
								'app_package_id'		=> ' com.vkontakte.android'
							]);
							
							$user_api->exec('execute', [
								'code'					=> 'API.account.setOnline({push_count: 0});API.stats.trackEvents({events:"[{\"lon\":\"35.4297\",\"cell_type\":\"gsm\",\"e\":\"geo_data\",\"cell_id\":4341,\"ts\":'.time().',\"accuracy\":'.mt_rand(2790, 4000).',\"lat\":\"46.5686\"}]"});'
							]);
							
							// Upgrade access token
							$refresh_result = $user_api->exec('auth.refreshToken', [
								'receipt'				=> '', 
								'receipt2'				=> '', 
								'nonce'					=> '', 
								'timestamp'				=> '', 
								'device_id'				=> $device_id, 
								'access_token'			=> $result->access_token
							]);
							
							if ($refresh_result->success()) {
								DB::insert('vk_oauth')
									->set([
										'type'			=> $key, 
										'access_token'	=> $refresh_result->response->token, 
										'secret'		=> $refresh_result->response->secret ?? $result->secret ?? '', 
										'refresh_token'	=> '', 
										'expires'		=> 0, 
									])
									->onDuplicateSetValues('access_token')
									->onDuplicateSetValues('refresh_token')
									->onDuplicateSetValues('secret')
									->onDuplicateSetValues('expires')
									->execute();
								
								$this->content['success'] = true;
							} else {
								$this->content['error'] = 'Ошибка обновления токена: '.$refresh_result->error();
							}
						break;
						
						default:
							DB::insert('vk_oauth')
								->set([
									'type'			=> $key, 
									'access_token'	=> $result->access_token, 
									'secret'		=> $result->secret ?? '', 
									'refresh_token'	=> $result->refresh_token ?? '', 
									'expires'		=> $result->expires_in ?? 0, 
								])
								->onDuplicateSetValues('access_token')
								->onDuplicateSetValues('refresh_token')
								->onDuplicateSetValues('secret')
								->onDuplicateSetValues('expires')
								->execute();
							
							$this->content['success'] = true;
						break;
					}
				} else {
					if ($result->captcha()) {
						$this->content['captcha'] = $result->captcha();
						$this->content['error'] = 'Введите код с картинки.';
					} else if ($result->errorCode() == 'need_validation') {
						if ($result->validation_type ?? false) {
							if ($result->validation_type == '2fa_sms') {
								$this->content['error'] = 'Подтвердите 2fa авторизацию по SMS! ('.$result->phone_mask.')';
								$this->content['sms_2fa'] = true;
							} else if ($result->validation_type == '2fa_app') {
								$this->content['error'] = 'Подтвердите 2fa авторизацию через приложение! ('.$result->phone_mask.')';
								$this->content['code_2fa'] = true;
							}
						} else {
							$this->content['error'] = 'Необходимо подтверждение!<br />'.
								$result->error_description.'<br />'.
								'<a href="'.$result->redirect_uri.'" rel="noopener noreferrer" target="_blank">'.$result->redirect_uri.'</a><br />'.
								'Затем нужно повторить вход.';
						}
					} else {
						$this->content['error'] = $result->error();
					}
				}
			break;
			
			case "VK_WEB":
				$auth_state = [];
				parse_str($_REQUEST['captcha_sid'] ?? '', $auth_state);
				
				if ($captcha_key)
					$auth_state['fields']['captcha_key'] = $captcha_key;
				
				$vk_web = \Smm\VK\Web::instance();
				$result = $vk_web->auth($login, $password, $auth_state);
				
				if ($result['success']) {
					$this->content['success'] = true;
				} else if (isset($result['captcha'])) {
					$this->content['error'] = 'Введите код с картинки.';
					$this->content['captcha'] = [
						'url'			=> $result['captcha'], 
						'sid'			=> http_build_query($result['state'] ?? [], '', '&')
					];
				} else {
					$this->content['error'] = 'Ошибка WEB auth: '.$result['error'];
				}
			break;
		}
	}
	
	public function authAction() {
		$this->title = 'Настройки : Авторизация';
		
		// Авторизация аккаунтов
		$oauth_list = [];
		foreach (\Z\Config::get('oauth_users') as $key => $oauth) {
			switch ($oauth['type']) {
				case "VK":
					$api = new \Smm\VK\API([
						'client'		=> $oauth['client']
					]);
					
					if ($oauth['auth'] == 'code') {
						$oauth_list[] = [
							'type'				=> $key, 
							'required'			=> $oauth['required'], 
							'form'				=> 'CODE', 
							'title'				=> $oauth['title'], 
							'oauth_url'			=> $api->getOauthUrl([
								'scope'		=> 'offline wall groups photos docs'
							]), 
							'user'				=> false, 
							'help'				=> [
								'1. Переходим по <b>Запросить доступ</b> и соглашаемся.', 
								'2. Попадаем на белый экран, копируем значение из <b>#code=</b> в адресной строке.', 
								'3. Возвращаемся и вводим его в поле <b>code</b>, жмём кнопку <b>GO</b>.', 
								'4. Готово!'
							]
						];
					} else {
						$oauth_list[] = [
							'type'				=> $key, 
							'required'			=> $oauth['required'], 
							'form'				=> 'DIRECT', 
							'title'				=> $oauth['title'], 
							'user'				=> false, 
							'help'				=> [
								'1. Вводим телефон (не e-mail) и пароль, жмём <b>Войти</b>.', 
								'2. Готово!'
							]
						];
					}
				break;
				
				case "VK_WEB":
					$vk_web = \Smm\VK\Web::instance();
					
					$auth = $vk_web->checkAuth();
					
					$oauth_list[] = [
						'type'				=> $key, 
						'required'			=> $oauth['required'], 
						'form'				=> 'DIRECT', 
						'title'				=> $oauth['title'], 
						'user'				=> $auth['real_name'] ?? false, 
						'help'				=> [
							'1. Вводим телефон (не e-mail) и пароль, жмём <b>Войти</b>.', 
							'2. Готово!'
						]
					];
				break;
			}
		}
		
		$base_url = Url::mk('/')->set('gid', $this->group['id']);
		
		$this->content = View::factory('settings/auth', [
			'oauth_list'		=> $oauth_list, 
			'oauth_action'		=> $base_url->copy()->set('a', 'settings/auth_save')->href()
		]);
	}
	
	public function oauthAction() {
		$this->title = 'Настройки : oauth';
		
		$ok					= $_REQUEST['ok'] ?? 0;
		$type				= $_REQUEST['type'] ?? '';
		$access_token		= $_REQUEST['access_token'] ?? '';
		$refresh_token		= $_REQUEST['refresh_token'] ?? '';
		$expires			= $_REQUEST['expires'] ?? '';
		$code				= $_REQUEST['code'] ?? '';
		$state				= $_REQUEST['state'] ?? '';
		$login				= $_REQUEST['login'] ?? '';
		$password			= $_REQUEST['password'] ?? '';
		$direct				= $_REQUEST['direct'] ?? 0;
		
		$base_url = Url::mk('/')->set('gid', $this->group['id']);
		
		$error = false;
		
		$types = [
			'VK'			=> 'ВК (предложки и прочее)', 
			'VK_SCHED'		=> 'ВК (щедулер)', 
			'VK_GRABBER'	=> 'ВК (граббер)', 
			'VK_STAT'		=> 'ВК (стата)', 
			'OK'			=> 'Одноклассники (не используется)'
		];
		
		$redirect_url = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'].'/auth.php';
		
		$vk_web = \Smm\VK\Web::instance();
		
		$web_captcha_url = false;
		$web_auth_state = false;
		
		if ($_REQUEST['do_web_auth'] ?? false) {
			$auth_state = [];
			parse_str($_REQUEST['auth_state'] ?? '', $auth_state);
			
			if (isset($_REQUEST['captcha']))
				$auth_state['fields']['captcha_key'] = $_REQUEST['captcha'];
			
			$result = $vk_web->auth($login, $password, $auth_state);
			if ($result['success']) {
				$redirect = $base_url->copy()->set('a', 'settings/oauth')->set('ok', '1')->url();
				return $this->redirect($redirect);
			} else if (isset($result['captcha'])) {
				$error = 'Нужно ввести капчу!';
				$web_captcha_url = $result['captcha'];
				$web_auth_state = http_build_query($result['state'] ?? [], '', '&');
			} else {
				$error = 'Ошибка WEB auth: '.$result['error'];
			}
		} elseif (isset($types[$type]) && $direct) {
			$ch = curl_init();
			curl_setopt_array($ch, [
				CURLOPT_POST				=> false, 
				CURLOPT_RETURNTRANSFER		=> true, 
				CURLOPT_SSL_VERIFYPEER		=> true, 
				CURLOPT_CONNECTTIMEOUT		=> 10, 
				CURLOPT_TIMEOUT				=> 30
			]);
			
			$client_secret = \Z\Config::get('oauth.VK_OFFICIAL_APP.secret');
			$client_id = \Z\Config::get('oauth.VK_OFFICIAL_APP.id');
			
			curl_setopt($ch, CURLOPT_URL, "https://oauth.vk.com/token?".http_build_query([
				'grant_type'		=> 'password', 
				'client_id'			=> $client_id, 
				'client_secret'		=> $client_secret, 
				'username'			=> $login, 
				'password'			=> $password, 
				'v'					=> '5.101', 
				'2fa_supported'		=> 1
			], '', '&'));
			
			$raw = curl_exec($ch);
			$res = @json_decode($raw);
			
			if ($res && isset($res->access_token)) {
				$redirect = $base_url
					->copy()
					->set([
						'a'					=> 'settings/oauth', 
						'type'				=> $type, 
						'access_token'		=> $res->access_token, 
						'refresh_token'		=> isset($res->refresh_token) ? $res->refresh_token : '', 
						'expires'			=> isset($res->expires_in) && $res->expires_in ? time() + $res->expires_in : 0
					])
					->url();
				return $this->redirect($redirect);
			} else {
				$error = 'Ошибка получения access_token';
			}
		} elseif (isset($types[$type]) && $access_token) {
			DB::insert('vk_oauth')
				->set([
					'type'			=> $type, 
					'access_token'	=> $access_token, 
					'refresh_token'	=> $refresh_token, 
					'expires'		=> $expires, 
				])
				->onDuplicateSetValues('access_token')
				->onDuplicateSetValues('refresh_token')
				->onDuplicateSetValues('expires')
				->execute();
			
			$redirect = $base_url->copy()->set('a', 'settings/oauth')->set('ok', '1')->url();
			return $this->redirect($redirect);
		} else if ($code && $state) {
			$ch = curl_init();
			curl_setopt_array($ch, [
				CURLOPT_POST				=> true, 
				CURLOPT_RETURNTRANSFER		=> true, 
				CURLOPT_SSL_VERIFYPEER		=> true, 
				CURLOPT_CONNECTTIMEOUT		=> 10, 
				CURLOPT_TIMEOUT				=> 30
			]);
			
			switch ($state) {
				case "OK":
					curl_setopt($ch, CURLOPT_URL, "https://api.ok.ru/oauth/token.do");
					curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
						'client_id'		=> \Z\Config::get('oauth.OK.id'), 
						'client_secret'	=> \Z\Config::get('oauth.OK.secret'), 
						'redirect_uri'	=> $redirect_url, 
						'code'			=> $code, 
						'grant_type'	=> 'authorization_code'
					]));
				break;
				
				case "VK":
				case "VK_SCHED":
				case "VK_GRABBER":
				case "VK_STAT":
					curl_setopt($ch, CURLOPT_URL, "https://oauth.vk.com/access_token");
					curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
						'client_id'		=> \Z\Config::get('oauth.VK.id'), 
						'client_secret'	=> \Z\Config::get('oauth.VK.secret'), 
						'redirect_uri'	=> 'https://oauth.vk.com/blank.html', 
						'code'			=> $code
					]));
				break;
			}
			
			$raw = curl_exec($ch);
			$res = @json_decode($raw);
			
			if ($res && isset($res->access_token)) {
				$redirect = $base_url
					->copy()
					->set([
						'a'					=> 'settings/oauth', 
						'type'				=> $state, 
						'access_token'		=> $res->access_token, 
						'refresh_token'		=> isset($res->refresh_token) ? $res->refresh_token : '', 
						'expires'			=> isset($res->expires_in) && $res->expires_in ? time() + $res->expires_in : 0
					])
					->url();
				return $this->redirect($redirect);
			} else {
				$error = 'Ошибка получения access_token';
			}
		}
		
		$oauth_groups_list = [];
		
		$vk_groups = DB::select()
			->from('vk_groups')
			->where('deleted', '=', 0)
			->execute();
		
		$VK_COMM_MINI_APP = \Z\Config::get("oauth", "VK_COMM_MINI_APP");
		
		foreach ($vk_groups as $group) {
			$access_token = \Smm\Oauth::getGroupAccessToken($group['id']);
			
			if (!$access_token) {
				$status = 'not_set';
			} else {
				$api = new \Smm\VK\API($access_token);
				$res = $api->exec("groups.getTokenPermissions");
				
				if ($res->success()) {
					if (($res->response->mask & \Smm\Oauth::VK_MINIMAL_GROUP_ACCESS) != \Smm\Oauth::VK_MINIMAL_GROUP_ACCESS) {
						$status = 'expired';
					} else {
						$status = 'success';
					}
				} else {
					$status = 'error';
				}
			}
			
			$oauth_groups_list[] = [
				'title'				=> htmlspecialchars($group['name']), 
				'oauth_url'			=> 'https://vk.com/app'.$VK_COMM_MINI_APP['id'].'_-'.$group['id'], 
				'status'			=> $status
			];
		}
		
		$oauth_list = [];
		
		foreach ($types as $type => $title) {
			switch ($type) {
				case "OK":
					$oauth_url = 'https://oauth.vk.com/authorize?'.http_build_query([
						'client_id'			=> \Z\Config::get('oauth.OK.id'), 
						'redirect_uri'		=> $redirect_url, 
						'layout'			=> 'm', 
						'scope'				=> 'VALUABLE_ACCESS;LONG_ACCESS_TOKEN', 
						'response_type'		=> 'code', 
						'state'				=> $type
					], '', '&amp;');
					
					$oauth_list[] = [
						'type'				=> $type, 
						'title'				=> $title, 
						'oauth_url'			=> $oauth_url, 
						'user'				=> false, 
						'help'				=> [
							'1. Переходим по ссылке и соглашаемся дать авторизацию.', 
							'2. Готово!'
						]
					];
				break;
				
				case "VK":
				case "VK_SCHED":
				case "VK_GRABBER":
				case "VK_STAT":
					$api = new \Smm\VK\API(\Smm\Oauth::getAccessToken($type));
					$res = $api->exec("users.get");
					
					$oauth_url = 'https://oauth.vk.com/authorize?'.http_build_query([
						'client_id'			=> \Z\Config::get('oauth.VK.id'), 
						'redirect_uri'		=> 'https://oauth.vk.com/blank.html', 
						'display'			=> 'mobile', 
						'scope'				=> 'offline wall groups photos docs', 
						'response_type'		=> 'code', 
						'state'				=> $type
					], '', '&amp;');
					
					$oauth_list[] = [
						'type'				=> $type, 
						'title'				=> $title, 
						'oauth_url'			=> $oauth_url, 
						'oauth_direct'		=> true, 
						'user'				=> isset($res->response) && $res->response ? $res->response[0]->first_name.' '.$res->response[0]->last_name : '- не установлено -', 
						'help'				=> [
							'1. Переходим по ссылке и соглашаемся дать авторизацию.', 
							'2. Попадаем на белу страницу, это нормально.', 
							'3. Копируем значение параметра <b>code=</b> из адрессной строки.', 
							'4. Возвращаемся сюда и вводим его в поле <b>code</b>.', 
							'5. Жмём кнопку <b>GO</b>', 
							'6. Готово!'
						]
					];
				break;
			}
		}
		
		$this->content = View::factory('settings/oauth', [
			'ok'				=> $ok, 
			'error'				=> $error, 
			'oauth_list'		=> $oauth_list, 
			'oauth_groups_list'	=> $oauth_groups_list, 
			'groups_app_id'		=> $VK_COMM_MINI_APP['id'], 
			'web_auth'			=> $vk_web->checkAuth(), 
			'web_captcha_url'	=> $web_captcha_url, 
			'web_auth_state'	=> $web_auth_state, 
			'login'				=> $login, 
			'password'			=> $password
		]);
	}
	
	public function callbacksAction() {
		$this->title = 'Настройки : callbacks';
		
		$ok					= $_GET['ok'] ?? 0;
		$type				= $_GET['type'] ?? '';
		$access_token		= $_GET['access_token'] ?? '';
		$refresh_token		= $_GET['refresh_token'] ?? '';
		$expires			= $_GET['expires'] ?? '';
		$code				= $_GET['code'] ?? '';
		$state				= $_GET['state'] ?? '';
		
		$base_url = Url::mk('/')->set('gid', $this->group['id']);
		
		$error = false;
		
		$types = [
			'activity_stat'		=> [
				'title'				=> 'Активность участников', 
				'enable'			=> [
					'<b>Записи на стене:</b> Репост', 
					'<b>Комментарии на стене:</b> Добавление', 
					'<b>Комментарии на стене:</b> Редактирование', 
					'<b>Комментарии на стене:</b> Удаление', 
					'<b>Комментарии на стене:</b> Восстановление', 
					'<b>Сообщения:</b> Входящее сообщение', 
					'<b>Сообщения:</b> Разрешение на получение', 
					'<b>Сообщения:</b> Запрет на получение', 
				], 
				'help'				=> [
					'1. <b>Сообщество</b> &raquo; <b>Настройки</b> &raquo; <b>Работа с API</b> &raquo; <b>Callback API</b>', 
					'2. Добавить сервер с URL, который указан выше', 
					'3. Включить все <b>Типы событий</b>, указанные здесь', 
					'4. Заполнить здесь <b>Строка, которую должен вернуть сервер</b>', 
					'5. Придумать <b>Секретный ключ</b> и заполнить его в VK и здесь', 
					'6. Нажать <b>Подтвердить</b> внастройках сервера <b>Callback API</b>'
				]
			]
		];
		
		if ($_POST['do_save'] ?? false) {
			$type = $_POST['type'] ?? '';
			$secret = $_POST['secret'] ?? '';
			$install_ack = $_POST['install_ack'] ?? '';
			
			$group = DB::select()
				->from('vk_groups')
				->where('id', '=', $id)
				->execute()
				->current();
			
			if (isset($types[$type])) {
				DB::insert('vk_callbacks')
					->set([
						'type'				=> $type, 
						'secret'			=> $secret, 
						'install_ack'		=> $install_ack, 
						'group_id'			=> $this->group['id']
					])
					->onDuplicateSetValues('secret')
					->onDuplicateSetValues('install_ack')
					->execute();
				
				$redirect = $base_url->copy()->set('a', 'settings/callbacks')->url();
				return $this->redirect($redirect);
			} else {
				$error = 'Callback не найден.';
			}
		}
		
		$callbacks = DB::select()
			->from('vk_callbacks')
			->where('group_id', '=', $this->group['id'])
			->execute()
			->asArray('type');
		
		$callbacks_list = [];
		
		foreach ($types as $type => $data) {
			$callbacks_list[] = [
				'type'			=> $type, 
				'title'			=> $data['title'], 
				'enable'		=> $data['enable'], 
				'help'			=> $data['help'], 
				'url'			=> Url::mk("http://".$_SERVER['HTTP_HOST']."/")->set('a', 'vk_callbacks/'.$type)->href(), 
				'secret'		=> $callbacks[$type]['secret'] ?? '', 
				'install_ack'	=> $callbacks[$type]['install_ack'] ?? ''
			];
		}
		
		$this->content = View::factory('settings/callbacks', [
			'error'				=> $error, 
			'callbacks_list'	=> $callbacks_list
		]);
	}
	
	public function indexAction() {
		$this->title = 'Настройки';
		
		$base_url = Url::mk('/')->set('gid', $this->group['id']);
		
		$this->content = View::factory('settings/index', [
			'exit_url'			=> $base_url->copy()->set('a', 'index/exit')->href(), 
			'oauth_url'			=> $base_url->copy()->set('a', 'settings/oauth')->href(), 
			'groups_url'		=> $base_url->copy()->set('a', 'settings/groups')->href(), 
			'callbacks_url'		=> $base_url->copy()->set('a', 'settings/callbacks')->href(), 
			'catificator_url'	=> $base_url->copy()->set('a', 'catificator/index')->href(), 
			'is_admin'			=> $this->user->can('admin'), 
			'login'				=> $this->user->login
		]);
	}
	
	public function accessControl() {
		return [
			'*' => [
				'auth_required'		=> true, 
				'users'				=> 'admin'
			], 
			'index' => [
				'auth_required'		=> true, 
				'users'				=> ''
			]
		];
	}
}
