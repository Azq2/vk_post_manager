<?php
namespace Z\Net;

use \Z\Config;

class Redis {
	protected static $instances = [];
	protected $redis;
	
	protected function __construct($instance = NULL) {
		$instance = $instance ?: 'default';
		$this->config = array_merge([
			'host'			=> '127.0.0.1',
			'port'			=> 6379,
			'persistent'	=> true,
			'prefix'		=> ''
		], Config::get("redis", $instance));
		
		$this->lists = new Redis\Lists($this);
		$this->channel = new Redis\Channel($this);
	}
	
	public function redis() {
		if (!$this->redis) {
			$method = $this->config['persistent'] ? 'pconnect' : 'connect';
			
			$this->redis = new \Redis();
			$this->redis->$method($this->config['host'], $this->config['port']);
			$this->redis->setOption(\Redis::OPT_PREFIX, $this->config['prefix']);
			$this->redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);
		}
		return $this->redis;
	}
	
	/* Get / set */
	public function get($key, $default = false) {
		$v = $this->redis()->get($key);
		return $v === false ? $default : $v;
	}
	
	public function set($key, $value, $ttl = NULL) {
		if ($ttl !== NULL) {
			$this->redis()->pSetEx($key, round($ttl * 1000), $value);
		} else {
			$this->redis()->set($key, $value);
		}
		return $this;
	}
	
	public function getSet($key, $value) {
		$this->redis()->getSet($key, $value);
		return $this;
	}
	
	public function expire($key, $ttl) {
		$this->redis()->pexpire($key, round($ttl * 1000));
		return $this;
	}
	
	public function delete($key) {
		$this->redis()->delete($key);
		return $this;
	}
	
	public function exists($key) {
		$this->redis()->exists($key);
		return $this;
	}
	
	/* Atomic */
	public function append($key, $data) {
		$this->redis()->append($key, $data);
		return $this;
	}
	
	public function incr($key, $by = 1) {
		$this->redis()->incrBy($key, $by);
		return $this;
	}
	
	public function decr($key, $by = 1) {
		$this->redis()->decrBy($key, $by);
		return $this;
	}
	
	public function incrFloat($key, $by = 1) {
		$this->redis()->incrByFloat($key, $by);
		return $this;
	}
	
	public function decrFloat($key, $by = 1) {
		$this->redis()->decrByFloat($key, $by);
		return $this;
	}
	
	/* Bulk */
	public function getMulti(array $keys) {
		$result = $this->redis()->getMultiple($keys);
		return array_combine($keys, $result);
	}
	
	public function setMulti(array $values, $ttl = NULL) {
		$r = $this->redis();
		$r->mSetNx($values);
		
		if ($ttl !== NULL) {
			foreach ($values as $k => $_)
				$r->pexpire($k, round($ttl * 1000));
		}
		
		return $this;
	}
	
	public function deleteMulti(array $keys) {
		$this->redis()->delete($keys);
		return $this;
	}
	
	public function existsMulti(array $keys) {
		$r = $this->redis();
		$ret = [];
		foreach ($keys as $k)
			$ret[$k] = $r->exists($k);
		return $ret;
	}
	
	/* Serialized Data */
	public function getData($key, $default = false) {
		return $this->_serialized('get', func_get_args());
	}
	
	public function setData($key, $value, $ttl = NULL) {
		return $this->_serialized('set', func_get_args());
	}
	
	public function getDataMulti(array $keys) {
		return $this->_serialized('getMulti', func_get_args());
	}
	
	public function setDataMulti(array $values, $ttl = NULL) {
		return $this->_serialized('setMulti', func_get_args());
	}
	
	protected function _serialized($method, $args) {
		$redis = $this->redis();
		$redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_IGBINARY);
		$ret = call_user_func_array([$this, $method], $args);
		$redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);
		return $ret;
	}
	
	public static function instance($instance = NULL) {
		if (!isset(self::$instances[$instance]))
			self::$instances[$instance] = new Redis($instance);
		return self::$instances[$instance];
	}
}
