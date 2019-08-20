<?php
namespace Smm\Controllers;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;

use \Smm\View\Widgets;

class IndexController extends \Smm\GroupController {
	public function indexAction() {
		return $this->suggestedAction();
	}
	
	public function testAction() {
		$settings = DB::select()
			->from('vk_groups')
			->where('id', '=', $this->group['id'])
			->execute()
			->current();
		
		$time = strtotime(date("Y-m-d", time() + 3600*24)." 08:11");
		$min_time = $time + 1;
		$max_time = 0;
		
		mt_srand(0);
		
		$posts[] = (object) [
			'date'			=> strtotime(date("Y-m-d")." 2:22"), 
			'special'		=> false, 
			'post_type'		=> 'post', 
			'id'			=> 1
		];
		
		$posts[] = (object) [
			'date'			=> strtotime(date("Y-m-d")." 07:05"), 
			'special'		=> true, 
			'post_type'		=> 'postpone', 
			'id'			=> 2
		];
		
		$posts[] = (object) [
			'date'			=> strtotime(date("Y-m-d")." 08:11"), 
			'special'		=> false, 
			'post_type'		=> 'postpone', 
			'id'			=> 3
		];
		
		$posts[] = (object) [
			'date'			=> strtotime(date("Y-m-d")." 10:05"), 
			'special'		=> false, 
			'post_type'		=> 'postpone', 
			'id'			=> 4
		];
		
		$new_posts = \Smm\VK\Posts::processQueue($posts, $settings);
		
		$last = 0;
		foreach ($new_posts as $post) {
			if (date("H:i", $post->date) == "07:30")
				echo "\n";
			
			if ($last) {
				echo date("+H:i", strtotime("00:00:00") + ($post->date - $last));
			} else {
				echo "      ";
			}
			
			echo "     #".$post->id." - ".date("Y-m-d H:i:s", $post->date).($post->special ? " [SPECIAL]" : "")."\n";
			$last = $post->date;
		}
	}
	
	public function suggestedAction() {
		$api = new \Z\Net\VkApi(\Smm\Oauth::getAccessToken('VK'));
		
		$filter = $_REQUEST['filter'] ?? '';
		
		$filter2list = [
			'accepted'	=> 'postponed', 
			'new'		=> 'suggests', 
			'special'	=> 'specials'
		];
		
		if (!isset($filter2list[$filter]))
			$filter = 'new';
		
		$list = $filter2list[$filter];
		
		$res = \Smm\VK\Posts::getAll($api, $this->group['id']);
		
		$comments = [];
		
		$last_date = "";
		$last_time = 0;
		$last_posted = 0;
		$last_postponed = 0;
		
		$by_week_max = 0;
		$by_week = [];
		
		if ($res->success) {
			$i = 0;
			foreach ($res->{$list} as $item) {
				$from_id = (isset($item->created_by) && $item->created_by ? $item->created_by : (isset($item->from_id) ? $item->from_id : $item->owner_id));
				
				$date = Date::display($item->date, false, false);
				
				if (!isset($by_week[date("Y-W-N", $item->date)]))
					$by_week[date("Y-W-N", $item->date)] = ['n' => date("N", $item->date), 'date' => $date, 'cnt' => 0];
				++$by_week[date("Y-W-N", $item->date)]['cnt'];
				
				$by_week_max = max($by_week_max, $by_week[date("Y-W-N", $item->date)]['cnt']);
				
				if ($date != $last_date)
					$last_time = 0;
				
				if ($item->post_type == 'post')
					$last_posted = max($item->date, $last_posted);
				else if (!$item->special)
					$last_postponed = max($item->date, $last_postponed);
				
				$user = $res->users[$from_id];
				$attaches_info = \Smm\VK\Posts::normalizeAttaches($item);
				
				$comments[] = [
					'id'			=> $item->id, 
					'owner'			=> $item->owner_id, 
					'text'			=> $item->text, 
					'spell'			=> \Smm\Utils\Spellcheck::check($item->text), 
					'attaches'		=> $attaches_info->attaches, 
					'source_id'		=> $item->owner_id, 
					'source_type'	=> 'VK', 
					'remote_id'		=> $item->owner_id.'_'.$item->id, 
					'time'			=> $item->date, 
					'type'			=> $item->post_type, 
					'comment_text'	=> $item->comment_text, 
					'comment_spell'	=> \Smm\Utils\Spellcheck::check($item->comment_text), 
					'likes'			=> 0, 
					'reposts'		=> 0, 
					'comments'		=> 0, 
					'anon'			=> !isset($item->signer_id) || !$item->signer_id, 
					'owner_name'	=> isset($user->name) ? $user->name : $user->first_name." ".$user->last_name, 
					'owner_avatar'	=> $user->photo_50, 
					'owner_url'		=> "/".(isset($user->screen_name) && $user->screen_name ? $user->screen_name : 'id'.$user->id), 
					'images_cnt'	=> $attaches_info->images, 
					'gifs_cnt'		=> $attaches_info->gifs, 
					
					// Параметры очереди
					'first'			=> !$i, 
					'last'			=> $i == count($res->{$list}) - 1, 
					'list'			=> $list, 
					'special'		=> $item->special, 
					'period'		=> $date != $last_date ? $date : false, 
					'delta'			=> $last_time ? $item->date - $last_time : 0, 
					'published'		=> !in_array($item->post_type, ['postpone', 'suggest']), 
					'scheduled'		=> in_array($item->post_type, ['postpone', 'suggest']) && isset($item->orig_date) && abs($item->date - $item->orig_date) <= 60
				];
				
				$last_time = $item->date;
				$last_date = $date;
				
				++$i;
			}
		}
		
		$filter_tabs = new Widgets\Tabs([
			'url'		=> Url::current(), 
			'param'		=> 'filter', 
			'items'		=> [
				'new'		=> 'Новые ('.count($res->suggests).')', 
				'accepted'	=> 'Принятые ('.$res->postponed_cnt.')', 
				'special'	=> 'Рекламные ('.count($res->specials).')', 
			], 
			'active'	=> $filter
		]);
		
		$next_post_time = \Smm\Globals::get($this->group['id'], "next_post_date");
		
		$this->title = 'Предложки';
		$this->content = View::factory('index/suggested', [
			'by_week'	=> [
				'items'	=> array_values($by_week), 
				'max'	=> $by_week_max
			], 
			'list'		=> $list, 
			'gid'		=> $this->group['id'], 
			'tabs'		=> $filter_tabs->render(), 
			'back'		=> Url::current()->url(), 
			
			'last_post_time'				=> $last_posted ? Date::display($last_posted) : 'n/a', 
			'last_delayed_post_time'		=> $last_postponed ? Date::display($last_postponed) : 'n/a', 
			'next_post_time'				=> $next_post_time ? Date::display($next_post_time) : 'n/a', 
			
			'last_post_time_unix'			=> $last_posted, 
			'last_delayed_post_time_unix'	=> $last_postponed, 
			
			'load_error'			=> $res->error, 
			'comments'				=> $comments, 
			'filter'				=> $filter, 
			'from'					=> $this->_parseTime($this->group['period_from']), 
			'to'					=> $this->_parseTime($this->group['period_to']), 
			'interval'				=> $this->_parseTime($this->group['interval']), 
			'special_post_before'	=> $this->_parseTime($this->group['special_post_before']), 
			'special_post_after'	=> $this->_parseTime($this->group['special_post_after']), 
			'deviation'				=> $this->_parseTime($this->group['deviation']), 
			'success'				=> isset($_REQUEST['ok']), 
			'postponed_cnt'			=> $res->postponed_cnt, 
			'suggests_cnt'			=> $res->suggests_cnt
		]);
	}
	
	public function multipicpostAction() {
		$api = new \Z\Net\VkApi(\Smm\Oauth::getAccessToken('VK'));
		
		if ($_FILES && isset($_FILES['file'])) {
			$this->mode('json');
			
			$this->content['success'] = false;
			
			$from_web = intval($_REQUEST['from_web'] ?? 0);
		
			if (!$this->user->can('user')) {
				$this->content['error'] = 'Гостевой доступ!';
			} elseif ($_FILES['file']['error']) {
				$this->content['error'] = 'Произошла странная ошибка под секретным номером #'.$_FILES['file']['error'];
			} elseif (!getimagesize($_FILES['file']['tmp_name'])) {
				$this->content['fatal'] = true;
				$this->content['error'] = 'Что за дичь? Не очень похоже на пикчу с котиком.';
			} else {
				$ext = explode(".", $_FILES['file']['name']);
				$ext = strtolower(end($ext));
				
				$result = \Smm\VK\Posts::uploadPics($api, $this->group['id'], [[
					'path'		=> $_FILES['file']['tmp_name'], 
					'caption'	=> $_POST['caption'] ?? "", 
					'document'	=> $ext == "gif"
				]]);
				
				if ($result->success) {
					// Фейковая дата поста
					$fake_date = DB::select(['MAX(`fake_date`)', 'fake_date'])
						->from('vk_posts_queue')
						->execute()
						->get('fake_date', 0);
					$fake_date = max(time() + 3600 * 24 * 60, $fake_date) + 3600;
					
					$api_data = [
						'owner_id'		=> -$this->group['id'], 
						'signed'		=> 0, 
						'message'		=> $_POST['message'] ?? "", 
						'attachments'	=> implode(",", array_keys($result->attachments)), 
						'publish_date'	=> $fake_date
					];
					
					if (($captcha_code = \Smm\VK\Captcha::getCode())) {
						$api_data['captcha_key'] = $captcha_code['key'];
						$api_data['captcha_sid'] = $captcha_code['sid'];
					}
					
					$post_type = 'new';
					$id = 0;
					
					if ($from_web) {
						$vk_web = \Smm\VK\Web::instance();
						$result = $vk_web->wallEdit($id, $api_data);
						if (!$result['success']) {
							$this->content['error'] = $result['error']." (Для исправления можно снять галочку с [x] Убрать шестернь)";
							return;
						}
						
						// And also edit post with api, for reduce errors
						$post_type = 'post';
						$id = $result['post_id'];
						$api_data['post_id'] = $id;
					}
					
					$res = $api->exec($post_type == 'new' ? "wall.post" : "wall.edit", $api_data);
					if ($res->success()) {
						$vk_post_id = $res->response->post_id ?? $id;
						
						DB::insert('vk_posts_queue')
							->set([
								'fake_date'		=> $fake_date, 
								'group_id'		=> $this->group['id'], 
								'id'			=> $vk_post_id
							])
							->onDuplicateSetValues('fake_date')
							->execute();
						
						$this->content['success']	= true;
						$this->content['link']		= 'https://m.vk.com/wall-'.$this->group['id'].'_'.$vk_post_id;
						$this->content['post_id']	= $vk_post_id;
						
						$result = \Smm\VK\Posts::getAll($api, $this->group['id']);
						if ($result->success) {
							foreach ($result->postponed as $post) {
								if ($post->id == $vk_post_id)
									$this->content['date'] = $post->date;
							}
						}
					} else {
						$this->content['error'] = $res->error();
					}
				} else {
					$this->content['error'] = $result->error;
				}
				
				$this->content['captcha'] = \Smm\VK\Captcha::getLast();
			}
		} else {
			$this->title = 'Мультипикчепостинг';
			$this->content = View::factory('index/multipicpost');
		}
	}
	
	public function settingsAction() {
		if ($this->user->can('user')) {
			switch ($_POST['type'] ?? 'posts') {
				case "meme":
					$settings = json_decode($_POST['settings'] ?? '', true);
					if ($settings) {
						$q = DB::update('vk_groups')
							->set([
								'meme' => json_encode($settings)
							])
							->where('id', '=', $this->group['id'])
							->execute();
					}
				break;
				
				case "posts";
					$from_hh = min(max(0, $_POST['from_hh'] ?? 0), 23);
					$from_mm = min(max(0, $_POST['from_mm'] ?? 0), 59);
					
					$to_hh = min(max(0, $_POST['to_hh'] ?? 0), 23);
					$to_mm = min(max(0, $_POST['to_mm'] ?? 0), 59);
					
					$interval_hh = min(max(0, $_POST['hh'] ?? 0), 23);
					$interval_mm = min(max(0, $_POST['mm'] ?? 0), 59);
					
					$deviation_hh = min(max(0, $_POST['deviation_hh'] ?? 0), 23);
					$deviation_mm = min(max(0, $_POST['deviation_mm'] ?? 0), 59);
					
					$special_post_before_hh = min(max(0, $_POST['special_post_before_hh'] ?? 0), 23);
					$special_post_before_mm = min(max(0, $_POST['special_post_before_mm'] ?? 0), 59);
					
					$special_post_after_hh = min(max(0, $_POST['special_post_after_hh'] ?? 0), 23);
					$special_post_after_mm = min(max(0, $_POST['special_post_after_mm'] ?? 0), 59);
					
					$to						= $to_hh * 3600 + $to_mm * 60;
					$from					= $from_hh * 3600 + $from_mm * 60;
					$interval				= $interval_hh * 3600 + $interval_mm * 60;
					$deviation				= $deviation_hh * 3600 + $deviation_mm * 60;
					$special_post_before	= $special_post_before_hh * 3600 + $special_post_before_mm * 60;
					$special_post_after		= $special_post_after_hh * 3600 + $special_post_after_mm * 60;
					
					$interval = round($interval / 60) * 60;
					$deviation = round($deviation / 60) * 60;
					
					$deviation = max(0, min(round($interval / 2), $deviation));
					
					DB::update('vk_groups')
						->set([
							'period_from'			=> $from, 
							'period_to'				=> $to, 
							'interval'				=> $interval, 
							'deviation'				=> $deviation, 
							'special_post_before'	=> $special_post_before, 
							'special_post_after'	=> $special_post_after, 
						])
						->where('id', '=', $this->group['id'])
						->execute();
				break;
			}
		}
		
		if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
			$this->mode('json');
			$this->content = ['success' => true];
		} else {
			$back = $_REQUEST['return'] ?? '';
			$this->redirect($back);
		}
	}
	
	public function loginAction() {
		$login = $_POST['login'] ?? '';
		$password = $_POST['password'] ?? '';
		$redirect = $_POST['redirect'] ?? '';
		
		if ($_POST) {
			if ($this->user->auth($login, $password)) {
				$redirect = strpos($redirect, "/") === 0 ? $redirect : "/";
				return $this->redirect($redirect);
			}
		}
		
		$this->title = 'Авторизация';
		$this->content = View::factory('index/login', [
			'action'	=> Url::current(), 
			'login'		=> $login, 
			'password'	=> $password, 
			'error'		=> $login || $password ? 'Есть некоторые подозрения, что пароль не подошёл. :(' : false
		]);
	}
	
	public function exitAction() {
		$this->user->logout();
		return $this->redirect(Url::current());
	}
	
	private function _parseTime($t) {
		$h = floor($t / 3600);
		
		$m = floor(($t - $h * 3600) / 60);
		
		return array(
			'hh' => sprintf("%02d", $h), 
			'mm' => sprintf("%02d", $m)
		);
	}
	
	public function accessControl() {
		return [
			'*'			=> ['auth_required' => true], 
			'login'		=> ['auth_required' => false]
		];
	}
}
