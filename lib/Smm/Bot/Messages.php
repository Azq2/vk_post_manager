<?php
namespace Smm\Bot;

use \Z\DB;

class Messages {
	protected static $instances = [];
	protected $vars = [], $messages = [], $type;
	
	public function __construct($type) {
		$this->messages = DB::select()
			->from('vk_bots_messages')
			->where('type', '=', $type)
			->execute()
			->asArray('id', 'text');
		$this->type = $type;
	}
	
	public static function instance($type) {
		if (!isset(self::$instances[$type]))
			self::$instances[$type] = new Messages($type);
		return self::$instances[$type];
	}
	
	public function setGlobals($vars) {
		$this->vars = $vars;
		return $this;
	}
	
	public function L($id, $args = []) {
		if (!isset($this->messages[$id])) {
			$tmp = [];
			foreach (array_keys($args) as $k)
				$tmp[] = "$k:{$k}";
			
			$this->messages[$id] = $tmp ? "$id: ".implode(", ", $tmp) : "$id";
			
			DB::insert('vk_bots_messages')
				->ignore()
				->set([
					'id'		=> $id, 
					'text'		=> $this->messages[$id], 
					'type'		=> $this->type
				])
				->execute();
		}
		
		return \Smm\Utils\Text::prepareMacroses($this->messages[$id], array_merge([], $this->vars, $args));
	}
}
