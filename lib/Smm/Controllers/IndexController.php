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
					'attaches'		=> $attaches_info->attaches, 
					'source_id'		=> $item->owner_id, 
					'source_type'	=> 'VK', 
					'remote_id'		=> $item->owner_id.'_'.$item->id, 
					'time'			=> $item->date, 
					'type'			=> $item->post_type, 
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
					'list'			=> $list, 
					'special'		=> $item->special, 
					'period'		=> $date != $last_date ? $date : false, 
					'delta'			=> $last_time ? $item->date - $last_time : 0, 
					'scheduled'		=> $item->post_type != 'post' && isset($item->orig_date) && abs($item->date - $item->orig_date) <= 60
				];
				
				$last_time = $item->date;
				$last_date = $date;
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
			
			'last_post_time_unix'			=> $last_posted, 
			'last_delayed_post_time_unix'	=> $last_postponed, 
			
			'load_error'			=> $res->error, 
			'comments'				=> $comments, 
			'filter'				=> $filter, 
			'from'					=> $this->_parseTime($this->group['period_from']), 
			'to'					=> $this->_parseTime($this->group['period_to']), 
			'interval'				=> $this->_parseTime($this->group['interval']), 
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
					$queue_result = \Smm\VK\Posts::queueWallPost($this->group['id'], array_keys($result->attachments), $_POST['message'] ?? "");
					if ($queue_result->success) {
						$this->content['success'] = true;
						$this->content['link'] = 'https://vk.com/wall-'.$this->group['id'].'_'.$queue_result->post->post_id;
						$this->content['post_id'] = $queue_result->post->post_id;
					} else {
						$this->content['error'] = $queue_result->error;
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
					
					$to = $to_hh * 3600 + $to_mm * 60;
					$from = $from_hh * 3600 + $from_mm * 60;
					$interval = $interval_hh * 3600 + $interval_mm * 60;
					
					$interval = round($interval / 60) * 60;
					
					DB::update('vk_groups')
						->set([
							'period_from'	=> $from, 
							'period_to'		=> $to, 
							'interval'		=> $interval
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
