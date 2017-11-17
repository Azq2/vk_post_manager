<?php

class Url implements \ArrayAccess, \IteratorAggregate {
	public $scheme = '';
	public $host = '';
	public $port = 80;
	public $user = '';
	public $password = '';
	public $path = '';
	public $query = [];
	public $fragment = '';
	
	public function __construct($url = '') {
		$parts = parse_url($url);
		$this->scheme = array_val($parts, 'scheme', 'http');
		$this->host = array_val($parts, 'host', '');
		$this->port = array_val($parts, 'port', 80);
		$this->user = array_val($parts, 'user', '');
		$this->password = array_val($parts, 'password', '');
		$this->path = array_val($parts, 'path', '');
		$this->fragment = array_val($parts, 'fragment', '');
		$this->query = isset($parts['query']) ? self::parseArgs($parts['query']) : [];
	}
	
	public static function mk($url = NULL) {
		return new Url(is_null($url) ? $_SERVER['REQUEST_URI'] : $url);
	}
	
	public function href() {
		return $this->str(true);
	}
	
	public function url() {
		return $this->str(false);
	}
	
	public function str($xhtml = false, $separator = '&') {
		$url = '';
		if ($this->scheme && $this->host) {
			$url .= $this->scheme.'://';
			if ($this->user && $this->password)
				$url .= $this->user.':'.$this->password.'@';
			$url .= $this->host;
		}
		$url .= $this->path;
		if ($this->query) {
			$i = 0;
			foreach ($this->query as $k => $v) {
				if (!is_null($v) && $k != "#")
					$url .= (!$i-- ? '?' : $separator).$k.'='.$v;
			}
			if (!$this->fragment && isset($this->query['#']))
				$this->fragment = $this->query['#'];
		}
		if ($this->fragment)
			$url .= '#'.$this->fragment;
		return $xhtml ? htmlspecialchars($url, ENT_QUOTES) : $url;
	}
	
	public function getQuery() {
		return $this->query;
	}
	
	public function __toString() {
		return $this->url();
	}
	
	public function get($key) {
		return isset($this->query[$key]) ? $this->query[$key] : NULL;
	}
	
	public function set($key, $value = NULL) {
		if (is_array($key)) {
			array_merge($this->query, $key);
		} else {
			$this->query[$key] = $value;
		}
		return $this;
	}
	
	public function remove($key) {
		if (is_array($key)) {
			foreach ($key as $k)
				unset($this->query[$k]);
		} else {
			unset($this->query[$key]);
		}
		return $this;
	}
	
	public function offsetSet($key, $value) {
		return $this->query[$key] = $value;
	}
	
	public function offsetExists($key) {
		return isset($this->query[$key]);
	}
	
	public function offsetUnset($key) {
		unset($this->query[$key]);
	}
	
	public function offsetGet($key) {
		return isset($this->query[$key]) ? $this->query[$key] : NULL;
	}
	
	public function getIterator() {
		return new \ArrayIterator($this->query);
	}
	
	public static function parseArgs($args) {
		$args_array = [];
		$pairs = preg_split("/&amp;|&|;/i", $args);
		foreach ($pairs as $pair) {
			$data = explode('=', $pair, 2);
			if (!empty($data[0]))
				$args_array[urldecode($data[0])] = isset($data[1]) ? urldecode($data[1]) : '';
		}
		return $args_array;
	}
}
