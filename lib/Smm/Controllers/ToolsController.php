<?php
namespace Smm\Controllers;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;

use \Smm\View\Widgets;

use PhpAmqpLib\Message\AMQPMessage;

class ToolsController extends \Smm\GroupController {
	public function indexAction() {
		$this->title = 'SMM Tools';
		
		$base_url = Url::mk('/')->set('gid', $this->group['id']);
		
		$this->content = View::factory('tools/index', [
			'multipicpost_url'		=> $base_url->copy()->set('a', 'index/multipicpost')->href(), 
			'duplicate_finder_url'	=> $base_url->copy()->set('a', 'tools/duplicate_finder')->href(), 
		]);
	}
	
	public function duplicate_finderAction() {
		$this->title = 'SMM Tools : Поиск баянов';
		
		$this->content = View::factory('tools/duplicate_finder', [
			
		]);
	}
	
	public function duplicate_finder_queueAction() {
		$this->mode('json');
		$this->content['success'] = false;
		$this->content['error'] = false;
		
		if (!$this->user->can('user')) {
			$this->content['error'] = 'Гостевой доступ.';
			return;
		}
		
		$id = preg_replace("/[^a-f0-9]/", "", $_REQUEST['id'] ?? "");
		$photo = $_REQUEST['photo'] ?? '';
		
		if (!$id) {
			if (!preg_match("/^photo([\d-]+)_(\d+)$/", $photo)) {
				$this->content['error'] = 'Invalid photo!';
			} else {
				$msg = (object) [
					'photo'		=> $photo, 
					'ctime'		=> time()
				];
				
				$id = md5(json_encode($msg));
				
				\Z\Cache::instance()->set("duplicate_finder_queue:$id", $msg, 3600);
				
				$amqp = \Z\Net\AMQP::instance();
				$amqp->queue_declare('duplicate_finder_queue', false, true);
				$amqp->basic_publish(new AMQPMessage($id), '', 'duplicate_finder_queue');
			}
		}
		
		if ($id) {
			$status = \Z\Cache::instance()->get("duplicate_finder_queue:$id");
			
			$this->content['id'] = $id;
			
			if ($status) {
				if (isset($status->error)) {
					$this->content['error'] = $status->error;
				} else {
					$this->content['success'] = true;
					$this->content['queue'] = $status;
				}
			} else {
				$this->content['error'] = 'Очередь проверки уже удалена. ('.$id .')';
			}
		}
	}
	
	public function accessControl() {
		return [
			'*' => [
				'auth_required'		=> true, 
				'users'				=> ''
			]
		];
	}
}
