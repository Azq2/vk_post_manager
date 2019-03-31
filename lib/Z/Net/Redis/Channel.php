<?php
namespace Z\Net\Redis;

use \Z\Config;

class Channel {
	protected $parent;
	
	public function __construct(\Z\Net\Redis $parent) {
		$this->parent = $parent;
	}
	
	public function publish($key, $value) {
		$this->parent->redis()->publish($key, $value);
		return $this;
	}
	
	public function subscribe($key, $callback) {
		$this->parent->redis()->subscribe(is_array($key) ? $key : [$key], function ($redis, $channel, $message) use ($callback) {
			$callback($this->parent, $channel, $message);
		});
		return $this;
	}
}
