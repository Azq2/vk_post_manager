<?php
namespace Smm;

use \Z\View;
use \Z\DB;
use \Z\Util\Url;
use \Smm\View\Widgets;

class BaseController extends \Z\Controller {
	protected $content;
	protected $title = 'SMM';
	protected $user;
	protected $mode = 'html';
	protected $group;
	protected $redirect;
	protected $show_comm_tabs = false;
	
	public function before() {
		$this->user = User::instance();
		
		if ($this->user->logged() && $this->user->login == 'guest') {
			header("HTTP/1.1 500 Internal Server Error");
			die("PHP Parse error:  syntax error, unexpected '::' (T_PAAMAYIM_NEKUDOTAYIM), expecting end of file in /var/www/cats_memes/lib/Http.php on line 78\n");
		}
		
		View::setGlobal([
			'logged'		=> $this->user->logged(), 
			'user'	=> [
				'read_only'		=> $this->user->can('guest')
			]
		]);
		
		$acl = $this->accessControl();
		$action = App::instance()->action();
		$action_acl = array_merge(isset($acl['*']) ? $acl['*'] : [], isset($acl[$action]) ? $acl[$action] : []);
		
		if (isset($action_acl['auth_required']) && $action_acl['auth_required'] && !$this->user->logged()) {
			$login_url = Url::mk('/')->set([
				'a'			=> 'index/login', 
				'redirect'	=> Url::current()->url()
			]);
			return $this->redirect($login_url);
		}
		
		if (isset($action_acl['users']) && $action_acl['users'] && !$this->user->can($action_acl['users']))
			return $this->error('Нет доступа');
	}
	
	public function redirect($url) {
		$this->redirect = $url;
		return false;
	}
	
	public function error($message) {
		switch ($this->mode) {
			case "json":
				$this->content = [
					'error'		=> $message
				];
			break;
			
			default:
				$this->mode('html');
				$this->title = 'Нет доступа';
				$this->content = View::factory('error/index')->set([
					'message'		=> $message
				]);
			break;
		}
		return false;
	}
	
	public function accessControl() {
		return ['*' => ['auth_required' => true]];
	}
	
	public function setActiveGroup($group) {
		$this->group = $group;
		return $this;
	}
	
	public function mode($mode) {
		$this->mode = $mode;
		return $this;
	}
	
	public function after() {
		if ($this->redirect) {
			header('Content-Type: text/html; charset=utf-8');
			header('Location: '.$this->redirect);
			return;
		}
		
		switch ($this->mode) {
			case "html":
				$comm_tabs = $sections_tabs = NULL;
				
				if ($this->user->logged()) {
					if ($this->show_comm_tabs) {
						$comm_tabs = new Widgets\Tabs([
							'url'		=> Url::current(), 
							'param'		=> 'gid', 
							'active'	=> $this->group ? $this->group['id'] : false
						]);
						
						$groups = DB::select('id', 'name')
							->from('vk_groups')
							->order('pos', 'ASC')
							->execute();
						foreach ($groups as $group) {
							$comm_tabs->addTab($group['id'], [
								'name' => $group['name']
							]);
						}
					}
					
					$base_url = Url::mk('/');
					
					if ($this->group)
						$base_url->set('gid', $this->group['id']);
					
					$sections_tabs = new Widgets\Tabs([
						'url'		=> $base_url, 
						'param'		=> 'a', 
						'items'		=> [
							'index'						=> 'Предложки', 
							'index/multipicpost'		=> 'Мультипикчепостинг', 
							'grabber'					=> 'Граббер', 
							'statistic/join_visual'		=> 'Вступления', 
						//	'game/catlist'				=> 'Котогочи', 
							'settings/index'			=> 'Настройки'
						], 
						'active'	=> isset($_REQUEST['a']) ? $_REQUEST['a'] : 'index'
					]);
				}
				
				header('Content-Type: text/html; charset=utf-8');
				echo View::factory('main', [
					'title'				=> $this->title, 
					'content'			=> $this->content instanceof \Z\View ? $this->content->render() : $this->content, 
					'group'				=> $this->group, 
					'sections_tabs'		=> $sections_tabs ? $sections_tabs->render() : "", 
					'comm_tabs'			=> $comm_tabs ? $comm_tabs->render() : ""
				])->render();
			break;
			
			case "json":
				$this->content['__stdout'] = ob_get_clean();
				
				header('Content-Type: application/json; charset=utf-8');
				echo json_encode($this->content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
			break;
		}
	}
}
