<?php
namespace Z\Catagochi;

class Message {
	private $message;
	
	public function __construct($message = false) {
		$this->message = $message;
	}
	
	public function body() {
		if ($this->message)
			return $this->message->body;
		return "";
	}
	
	public function userId() {
		if ($this->message)
			return $this->message->user_id;
		return 0;
	}
	
	public function router($menu) {
		foreach ($menu as $match => $action) {
			if ($this->match($match) !== false)
				return $action;
		}
		return isset($menu['*']) ? $menu['*'] : false;
	}
	
	public function match($words_list) {
		$words = [];
		foreach (explode("|", $words_list) as $w)
			$words[str_replace("ё", "е", mb_strtolower($w))] = 1;
		
		preg_match_all("/([a-zа-яё'-]+|[\d]+)/ui", $this->body(), $m);
		foreach ($m[1] as $w) {
			$w = str_replace("ё", "е", mb_strtolower($w));
			if (isset($words[$w]))
				return $w;
		}
		
		return false;
	}
}
