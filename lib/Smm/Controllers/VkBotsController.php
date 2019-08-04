<?php
namespace Smm\Controllers;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;

use \Smm\View\Widgets;

class VkBotsController extends \Smm\BaseController {
	public function deleteAction() {
		$type = preg_replace("/[^\w\d_-]+/si", "", $_GET['type'] ?? '');
		$id = preg_replace("/[^\w\d_-]+/si", "", $_GET['id'] ?? '');
		
		DB::delete('vk_bots_messages')
			->where('id', '=', $id)
			->where('type', '=', $type)
			->execute();
		
		$base_url = Url::mk('/')->set('type', $type);
		$redirect = $base_url->copy()->set('a', 'vk_bots/messages')->url();
		return $this->redirect($redirect);
	}
	
	public function addAction() {
		$type = preg_replace("/[^\w\d_-]+/si", "", $_GET['type'] ?? '');
		$id = preg_replace("/[^\w\d_-]+/si", "", $_POST['id'] ?? '');
		$text = trim($_POST['text'] ?? '');
		
		if ($id && $type) {
			DB::insert('vk_bots_messages')
				->set([
					'id'		=> $id, 
					'type'		=> $type, 
					'text'		=> $text, 
				])
				->onDuplicateSetValues('text')
				->execute();
		}
		
		if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
			$this->mode('json');
			$this->content['success'] = true;
		} else {
			$base_url = Url::mk('/')->set('type', $type);
			$redirect = $base_url->copy()->set('a', 'vk_bots/messages')->url();
			return $this->redirect($redirect);
		}
	}
	
	public function messagesAction() {
		$type = preg_replace("/[^\w\d_-]+/si", "", $_GET['type'] ?? '');
		
		$base_url = Url::mk('/')->set('type', $type);
		
		$messages_query = DB::select()
			->from('vk_bots_messages')
			->where('type', '=', $type)
			->order('id');
		
		$messages = [];
		foreach ($messages_query->execute() as $row) {
			$messages[] = [
				'id'			=> $row['id'], 
				'text'			=> $row['text'], 
				'delete_link'	=> $base_url->copy()->set('a', 'vk_bots/delete')
					->set('id', $row['id'])->href(), 
			];
		}
		
		$this->title = 'VK Bots : '.ucfirst($type).' : Сообщения';
		
		$this->content = View::factory('vk_bots/messages', [
			'form_action'		=> $base_url->copy()->set('a', 'vk_bots/add')->href(), 
			'messages'			=> $messages, 
			'type'				=> ucfirst($type)
		]);
	}
	
	public function accessControl() {
		return [
			'*'		=> ['auth_required' => true, 'users' => 'admin'], 
		];
	}
}
