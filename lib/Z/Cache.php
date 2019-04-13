<?php
namespace Z;

use \Z\Config;
use \Memcached;

class Cache {
	protected $mc;
	protected static $instances = [];
	
	protected function __construct($name = 'default') {
		$this->config = Config::get('cache', $name);
		
		$this->mc = new Memcached();
		
		foreach ($this->config['servers'] as $s) {
			$parts = explode(":", $s, 2);
			$this->mc->addServer($parts[0], $parts[1] ?? 11211);
		}
		
		$this->mc->setOption(Memcached::OPT_SERIALIZER, Memcached::SERIALIZER_IGBINARY);
		$this->mc->setOption(Memcached::OPT_BINARY_PROTOCOL, true);
		$this->mc->setOption(Memcached::OPT_COMPRESSION, true);
		
		if (isset($this->config['prefix']))
			$this->mc->setOption(Memcached::OPT_PREFIX_KEY, $this->config['prefix']);
	}
	
	public function get($key) {
		return $this->mc->get($key);
	}
	
	public function set($key, $value, $ttl = 0) {
		return $this->mc->set($key, $value, $ttl);
	}
	
	public function add($key, $value, $ttl = 0) {
		return $this->mc->add($key, $value, $ttl);
	}
	
	public function delete($key) {
		return $this->mc->delete($key);
	}
	
	public function getMulti($keys) {
		return $this->mc->getMulti($keys);
	}
	
	public function setMulti($values, $ttl = 0) {
		return $this->mc->setMulti($values, $ttl);
	}
	
	public function deleteMulti($keys) {
		return $this->mc->deleteMulti($keys);
	}
	
	public function incr($key, $offset = 1, $initial_value = 0, $ttl = 0) {
		return $this->mc->increment($key, $offset, $initial_value, $ttl);
	}
	
	public function decr($key, $offset = 1, $initial_value = 0, $ttl = 0) {
		return $this->mc->decrement($key, $offset, $initial_value, $ttl);
	}
	
	public function getStats() {
		return $this->mc->getStats();
	}
	
	public static function instance($name = 'default') {
		if (!isset(self::$instances[$name])) {
			$class = get_called_class();
			self::$instances[$name] = new $class($name);
		}
		return self::$instances[$name];
	}
}
