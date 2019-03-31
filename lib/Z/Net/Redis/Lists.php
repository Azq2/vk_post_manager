<?php
namespace Z\Net\Redis;

use \Z\Config;

class Lists {
	protected $parent;
	
	public function __construct(\Z\Net\Redis $parent) {
		$this->parent = $parent;
	}
	
	public function push($key, $value, $if_exists = false) {
		if ($if_exists) {
			$this->parent->redis()->rPushX($key, $value);
		} else {
			$this->parent->redis()->rPush($key, $value);
		}
		return $this;
	}
	
	public function unshift($key, $value, $if_exists = false) {
		if ($if_exists) {
			$this->parent->redis()->lPushX($key, $value);
		} else {
			$this->parent->redis()->lPush($key, $value);
		}
		return $this;
	}
	
	public function pop($key, $timeout = 0) {
		if ($timeout > 0) {
			return $this->parent->redis()->brPop($key, $timeout);
		} else {
			return $this->parent->redis()->rPop($key);
		}
	}
	
	public function shift($key, $timeout = 0) {
		if ($timeout > 0) {
			return $this->parent->redis()->blPop($key, $timeout);
		} else {
			return $this->parent->redis()->lPop($key);
		}
	}
	
	public function count($key) {
		return $this->parent->redis()->lSize($key);
	}
	
	public function get($key, $index) {
		return $this->parent->redis()->lGet($key, $index);
	}
	
	public function set($key, $index) {
		return $this->parent->redis()->lSet($key, $index, $value);
	}
	
	public function trim($key, $start, $end) {
		return $this->parent->redis()->lTrim($key, $start, $end);
	}
	
	public function range($key, $start, $end) {
		return $this->parent->redis()->lRange($key, $start, $end);
	}
	
	public function popUnshift($src, $dst, $timeout = 0) {
		if ($timeout > 0) {
			$this->parent->redis()->bRPopLPush($src, $dst, $timeout);
		} else {
			$this->parent->redis()->rPopLPush($src, $dst);
		}
	}
}
