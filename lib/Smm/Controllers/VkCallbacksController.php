<?php
namespace Smm\Controllers;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;

use \Smm\View\Widgets;

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
			case "wall_repost":
				DB::insert('vk_user_reposts')
					->ignore()
					->set([
						'group_id'		=> $this->group['id'], 
						'post_id'		=> $this->input->object->id, 
						'user_id'		=> $this->input->object->from_id, 
						'date'			=> date("Y-m-d H:i:s", $this->input->object->date)
					])
					->execute();
			break;
			
			case "wall_reply_new":
			case "wall_reply_edit":
			case "wall_reply_restore":
				$meta = \Smm\VK\Posts::analyzeComment($this->input->object);
				DB::insert('vk_user_comments')
					->set([
						'group_id'		=> $this->group['id'], 
						'comment_id'	=> $this->input->object->id, 
						'post_id'		=> $this->input->object->post_id, 
						'user_id'		=> $this->input->object->from_id, 
						'date'			=> date("Y-m-d H:i:s", $this->input->object->date), 
						'images_cnt'	=> $meta['images_cnt'], 
						'stickers_cnt'	=> $meta['stickers_cnt'], 
						'attaches_cnt'	=> $meta['attaches_cnt'], 
						'text_length'	=> $meta['text_length'], 
					])
					->onDuplicateSetValues('date')
					->onDuplicateSetValues('images_cnt')
					->onDuplicateSetValues('stickers_cnt')
					->onDuplicateSetValues('attaches_cnt')
					->onDuplicateSetValues('text_length')
					->execute();
			break;
			
			case "wall_reply_delete":
				DB::delete('vk_user_comments')
					->where('group_id', '=', $this->group['id'])
					->where('comment_id', '=', $this->input->object->id)
					->execute();
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
