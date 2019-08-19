<?php
namespace Smm\Controllers;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;

use \Smm\View\Widgets;

use PhpAmqpLib\Message\AMQPMessage;

class VkCallbacksController extends \Z\Controller {
	protected $group, $callback, $input;
	protected $content;
	
	public function before() {
		$this->input = json_decode(file_get_contents('php://input'));
		
		if (!$this->input) {
			$this->content = "Invalid JSON.";
			return false;
		}
		
		$group_id = $this->input->group_id ?? 0;
		$secret = $this->input->secret ?? "";
		
		$this->group = DB::select()
			->from('vk_groups')
			->where('id', '=', $group_id)
			->execute()
			->current();
		
		$this->callback = DB::select()
			->from('vk_callbacks')
			->where('type', '=', \Smm\App::instance()->action())
			->where('group_id', '=', $group_id)
			->execute()
			->current();
		
		if (!$this->group || !$this->callback) {
			header('Content-Type: text/plain; charset=utf-8');
			$this->content = "Invalid group or callback.";
			return false;
		}
		
		if ($this->callback['secret'] !== $secret) {
			$this->content = "Invalid secret.";
			return false;
		}
		
		if ($this->input->type == 'confirmation') {
			$this->content = $this->callback['install_ack'];
			return false;
		}
	}
	
	public function after() {
		header('Content-Type: text/plain; charset=utf-8');
		echo $this->content;
	}
	
	public function activity_statAction() {
		switch ($this->input->type) {
			case "message_new":
			case "message_allow":
			case "message_deny":
				switch ($this->group['bot']) {
					case "catificator":
						$amqp = \Z\Net\AMQP::instance();
						$amqp->queue_declare('catificator_queue', false, true);
						$amqp->basic_publish(new AMQPMessage(json_encode($this->input)), '', 'catificator_queue');
					break;
				}
			break;
		}
		
		$this->content = 'ok';
	}
	
	public function accessControl() {
		return [
			'*'		=> ['auth_required' => false], 
		];
	}
}
