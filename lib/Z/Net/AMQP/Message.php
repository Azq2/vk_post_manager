<?php
namespace Z\Net\AMQP;

use \AMQPConnection;
use \AMQPQueue;
use \AMQPMessage;
use \AMQPChannel;
use \AMQPExchange;

class Message {
	protected $headers = [];
	protected $delivery_info = [];
	
	public $body;
	public $delivery_tag;
	public $is_redelivery = false;
	
	protected static $allowed_headers = [
		'content_type'				=> true, 
		'content_encoding'			=> true, 
		'message_id'				=> true, 
		'user_id'					=> true, 
		'app_id'					=> true, 
		'delivery_mode'				=> true, 
		'priority'					=> true, 
		'timestamp'					=> true, 
		'expiration'				=> true, 
		'type'						=> true, 
		'reply_to'					=> true, 
		'correlation_id'			=> true, 
		'application_headers'		=> true, 
	];
	
	protected static $allowed_delivery_info = [
		'channel'				=> true, 
		'consumer_tag'			=> true, 
		'delivery_tag'			=> true, 
		'redelivered'			=> true, 
		'exchange'				=> true, 
		'routing_key'			=> true, 
	];
	
	public function __construct($body, $headers = [], $delivery_info = []) {
		$this->body = $body;
		
		foreach ($headers as $k => $v)
			$this->{$k} = $v;
		
		foreach ($delivery_info as $k => $v)
			$this->{$k} = $v;
	}
	
	public function getHeaders() {
		return $this->headers;
	}
	
	public function getDeliveryInfo() {
		return $this->headers;
	}
	
	public function __get($key) {
		if (isset(self::$allowed_headers[$key]))
			return $this->headers[$key] ?? NULL;
		
		if (isset(self::$allowed_delivery_info[$key]))
			return $this->delivery_info[$key] ?? NULL;
		
		throw new \OutOfBoundsException('Unknown header "'.$key.'".');
	}
	
	public function __set($key, $value) {
		if (isset(self::$allowed_headers[$key])) {
			$this->headers[$key] = $value;
		} elseif (isset(self::$allowed_delivery_info[$key])) {
			$this->delivery_info[$key] = $value;
		} else {
			throw new \OutOfBoundsException('Unknown header "'.$key.'".');
		}
	}
	
	public function __isset($key) {
		return isset(self::$allowed_headers[$key]) || isset(self::$allowed_delivery_info[$key]);
	}
	
	public function __unset($key) {
		if (isset(self::$allowed_headers[$key])) {
			unset($this->headers[$key]);
		} elseif (isset(self::$allowed_delivery_info[$key])) {
			unset($this->delivery_info[$key]);
		} else {
			throw new \OutOfBoundsException('Unknown header "'.$key.'".');
		}
	}
	
	public function __debugInfo() {
		return array_merge([
			'body'				=> $this->body
		], $this->headers, $this->delivery_info);
	}
}
