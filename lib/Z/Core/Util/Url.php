<?php
namespace Z\Core\Util;

class Url implements \IteratorAggregate {
	protected $parts = [];
	protected $query = [];
	
	public function __construct($url = NULL, $parent = NULL) {
		$this->parts = array(
			'scheme'	=> '', 
			'host'		=> '', 
			'port'		=> 0, 
			'user'		=> '', 
			'password'	=> '', 
			'path'		=> '', 
			'fragment'	=> '', 
			'query'		=> '', 
		);
		
		if ($parent) {
			$parent_parts = array_merge($this->parts, parse_url("$parent"));
			$this->parts = array_merge($parent_parts, parse_url("$url"));
			
			$parent_query = self::parseQuery($parent_parts['query']);
			$this->query = array_merge($parent_query, self::parseQuery($this->parts['query']));
		} else if ($url) {
			$this->parts = array_merge($this->parts, parse_url("$url"));
			$this->query = self::parseQuery($this->parts['query']);
		}
	}
	
	public static function mk($url, $parent = NULL) {
		return new Url($url, $parent);
	}
	
	public static function merge($old, $new) {
		return new Url($old, $new);
	}
	
	public static function current($absolute = false) {
		if (isset($_SERVER['HTTP_HOST']) && $absolute) {
			$scheme = self::getCurrentScheme();
			return new Url("$scheme://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
		}
		return new Url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : "");
	}
	
	public static function getCurrentScheme() {
		if (isset($_SERVER['REQUEST_SCHEME']))
			return $_SERVER['REQUEST_SCHEME'];
		return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http';
	}
	
	public function href() {
		return $this->build('&amp;');
	}
	
	public function url() {
		return $this->build('&');
	}
	
	public function build($separator = '&') {
		$url = '';
		if (strlen($this->parts['host'])) {
			if (strlen($this->parts['scheme']))
				$url .= $this->parts['scheme'].':';
			
			$url .= '//';
			
			if (strlen($this->parts['user']) && strlen($this->parts['password']))
				$url .= $this->parts['user'].':'.$this->parts['password'].'@';
			$url .= $this->parts['host'];
			
			if ($this->parts['port'] > 0)
				$url .= ":".$this->parts['port'];
		}
		
		$url .= $this->parts['path'];
		if ($this->query) {
			$query = array();
			foreach ($this->query as $k => $v)
				$query[] = $this->_stringifyQuery($k, $v, $separator);
			$url .= $query ? "?".implode($separator, $query) : "";
			
			if (!strlen($this->parts['fragment']) && isset($this->parts['query']['#']))
				$this->parts['fragment'] = $this->parts['query']['#'];
		}
		
		if (strlen($this->parts['fragment']))
			$url .= '#'.$this->parts['fragment'];
		
		return $url;
	}
	
	public function __toString() {
		return $this->url();
	}
	
	private function _stringifyQuery($k, $v, $separator) {
		$query = [];
		if (is_array($v)) {
			$i = 0;
			$arr = true;
			foreach ($v as $kk => $vv) {
				$query[] = $this->_stringifyQuery($k."[".($arr && $i === $kk ? "" : $kk)."]", $vv, $separator);
				if ($i !== $kk)
					$arr = false;
				++$i;
			}
			return implode($separator, $query);
		}
		return strlen($v) ? "$k=".str_replace("%2F", "/", urlencode($v)) : $k;
	}
	
	public function query($new_query = NULL) {
		if ($new_query !== false && $new_query !== NULL) {
			if (is_array($new_query)) {
				$this->query = $new_query;
			} else {
				$this->query = self::parseQuery($new_query);
			}
		}
		return $this->query;
	}
	
	public function get($key) {
		return isset($this->query[$key]) ? $this->query[$key] : NULL;
	}
	
	public function set($key, $value = NULL) {
		if (is_array($key)) {
			$this->query = array_merge($this->query, $key);
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
	
	public function __call($key, $args) {
		if ($key == "domain" || $key == "hostname")
			$key = "host";
		
		if (isset($this->parts[$key])) {
			if ($args && $args[0]) {
				$this->parts[$key] = $args[0];
				return $this;
			}
			return $this->parts[$key];
		}
		throw new \Exception("Unknown method $key");
	}
	
	public function __set($key, $value) {
		$this->query[$key] = $value;
	}
	
	public function __isset($key) {
		return isset($this->query[$key]);
	}
	
	public function __unset($key) {
		unset($this->query[$key]);
	}
	
	public function &__get($key) {
		return $this->query[$key];
	}
	
	public function copy() {
		return clone $this;
	}
	
	public function getIterator() {
		return new \ArrayIterator($this->query);
	}
	
	public static function parseQuery($raw) {
		$args = [];
		parse_str($raw, $args);
		return $args;
	}
}
