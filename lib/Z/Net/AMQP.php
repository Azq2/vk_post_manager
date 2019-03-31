<?php
namespace Z\Net;

use \AMQPConnection;
use \AMQPQueue;
use \AMQPMessage;
use \AMQPChannel;
use \AMQPExchange;

class AMQP {
    const DELIVERY_MODE_NON_PERSISTENT	= 1;
    const DELIVERY_MODE_PERSISTENT		= 2;
	
	protected static $instances = [];
	protected $amqp, $channel, $config;
	protected $queue, $exchange;
	
	protected function __construct($instance) {
		$this->config = array_merge([
			'host'				=> '127.0.0.1', 
			'login'				=> 'guest', 
			'password'			=> 'guest', 
			'port'				=> 5672, 
			'vhost'				=> ''
		], \Z\Config::get("amqp", $instance));
	}
	
	protected function connect() {
		$this->amqp = new AMQPConnection();
		$this->amqp->setHost($this->config['host']);
		$this->amqp->setLogin($this->config['login']);
		$this->amqp->setPassword($this->config['password']);
		$this->amqp->setPort($this->config['port']);
		$this->amqp->setVhost($this->config['vhost']);
		$this->amqp->connect();
		
		$this->channel = new AMQPChannel($this->amqp);
		$this->queue = new AMQPQueue($this->channel);
		$this->exchange = new AMQPExchange($this->channel);
	}
	
	protected function channel() {
		if (!$this->channel)
			$this->connect();
		return $this->channel;
	}
	
	protected function queue() {
		if (!$this->channel)
			$this->connect();
		return $this->queue;
	}
	
	protected function exchange() {
		if (!$this->channel)
			$this->connect();
		return $this->exchange;
	}
	
	/* Basic */
	public function ack($delivery_tag, $params = []) {
		$params = array_merge([
			'multiple'		=> false, 
		], $params);
		
		$flags = 0;
		if ($params['multiple'])
			$flags |= AMQP_MULTIPLE;
		
		return $this->queue()->ack($delivery_tag, $flags);
	}
	
	public function nack($delivery_tag, $multiple = false) {
		$params = array_merge([
			'multiple'		=> false, 
			'requeue'		=> false
		], $params);
		
		$flags = 0;
		if ($params['multiple'])
			$flags |= AMQP_MULTIPLE;
		if ($params['requeue'])
			$flags |= AMQP_REQUEUE;
		
		return $this->queue()->nack($delivery_tag, $flags);
	}
	
	public function cancel($consumer_tag) {
		return $this->queue()->cancel($consumer_tag);
	}
	
	public function consume($queue, $callback, $consumer_tag = '', $params = []) {
		$params = array_merge([
			'no_ack'		=> false, 
			'no_local'		=> false, 
			'exclusive'		=> false, 
		], $params);
		
		$flags = 0;
		if (!$params['no_ack'])
			$flags |= AMQP_AUTOACK;
		if ($params['no_local'])
			$flags |= AMQP_NOLOCAL;
		if ($params['exclusive'])
			$flags |= AMQP_EXCLUSIVE;
		
		$this->queue()->setName($queue);
		return $this->queue()->consume(function ($envelope) use ($callback, $consumer_tag) {
			return $callback($envelope ? $this->buildMessage($envelope, $consumer_tag) : false);
		}, $flags, $consumer_tag);
	}
	
	public function get($queue, $params = []) {
		$params = array_merge([
			'no_ack'		=> false, 
		], $params);
		
		$flags = 0;
		if (!$params['no_ack'])
			$flags |= AMQP_AUTOACK;
		
		$this->queue()->setName($queue);
		$envelope = $this->queue()->get($flags);
		return $envelope ? $this->buildMessage($envelope, false) : false;
	}
	
	protected function buildMessage($envelope, $consumer_tag) {
		$msg = new AMQP\Message($envelope->getBody(), [
			'content_type'				=> $envelope->getContentType(), 
			'content_encoding'			=> $envelope->getContentEncoding(), 
			'message_id'				=> $envelope->getMessageId(), 
			'user_id'					=> $envelope->getUserId(), 
			'app_id'					=> $envelope->getAppId(), 
			'delivery_mode'				=> $envelope->getDeliveryMode(), 
			'priority'					=> $envelope->getPriority(), 
			'timestamp'					=> $envelope->getTimestamp(), 
			'expiration'				=> $envelope->getExpiration(), 
			'type'						=> $envelope->getType(), 
			'reply_to'					=> $envelope->getReplyTo(), 
			'correlation_id'			=> $envelope->getCorrelationId(), 
			'application_headers'		=> $envelope->getHeaders(), 
		], [
			'consumer_tag'		=> $consumer_tag, 
			'delivery_tag'		=> $envelope->getDeliveryTag(), 
			'redelivered'		=> $envelope->isRedelivery(), 
			'exchange'			=> $envelope->getExchangeName(), 
			'routing_key'		=> $envelope->getRoutingKey(), 
		]);
		return $msg;
	}
	
	public function publish($msg, $exchange = '', $routing_key = '', $params = []) {
		if (!($msg instanceof AMQP\Message))
			$msg = new AMQP\Message($msg);
		
		$params = array_merge([
			'mandatory'		=> false, 
			'immediate'		=> false, 
		], $params);
		
		$flags = 0;
		if ($params['mandatory'])
			$flags |= AMQP_MANDATORY;
		if ($params['immediate'])
			$flags |= AMQP_IMMEDIATE;
		
		$this->exchange()->setName($exchange);
		
		$headers = $msg->getHeaders();
		if (isset($headers['application_headers'])) {
			$headers['headers'] = $headers['application_headers'];
			unset($headers['application_headers']);
		}
		
		return $this->exchange()->publish($msg->body, $routing_key, $flags, $headers);
	}
	
	/* Queue */
	public function declareQueue($name, $params = [], $argumets = []) {
		$params = array_merge([
			'passive'		=> false, 
			'durable'		=> false, 
			'exclusive'		=> false, 
			'auto_delete'	=> false, 
		], $params);
		
		$flags = 0;
		if ($params['passive'])
			$flags |= AMQP_PASSIVE;
		if ($params['durable'])
			$flags |= AMQP_DURABLE;
		if ($params['exclusive'])
			$flags |= AMQP_EXCLUSIVE;
		if ($params['auto_delete'])
			$flags |= AMQP_AUTODELETE;
		
		$this->queue()->setName($name);
		$this->queue()->setFlags($flags);
		$this->queue()->setArguments($argumets);
		return $this->queue()->declareQueue();
	}
	
	public function deleteQueue($name, $params = []) {
		$params = array_merge([
			'if_unused'		=> false, 
			'if_empty'		=> false, 
		], $params);
		
		$flags = 0;
		if ($params['if_unused'])
			$flags |= AMQP_IFUNUSED;
		if ($params['if_empty'])
			$flags |= AMQP_IFEMPTY;
		
		$this->queue()->setName($name);
		return $this->queue()->delete($flags);
	}
	
	public function purgeQueue($name) {
		$this->queue()->setName($name);
		return $this->queue()->purge();
	}
	
	public function bindQueue($name, $exchange, $routing_key = '', $arguments = []) {
		$this->queue()->setName($name);
		return $this->queue()->bind($exchange, $routing_key, $arguments);
	}
	
	public function unbindQueue($name, $exchange, $routing_key = '', $arguments = []) {
		$this->queue()->setName($name);
		return $this->queue()->unbind($exchange, $routing_key, $arguments);
	}
	
	/* Exchange */
	public function declareExchange($name, $type = 'direct', $params = [], $arguments = []) {
		$params = array_merge([
			'passive'		=> false, 
			'durable'		=> false, 
			'auto_delete'	=> false, 
			'internal'		=> false, 
		], $params);
		
		$flags = 0;
		if ($params['passive'])
			$flags |= AMQP_PASSIVE;
		if ($params['durable'])
			$flags |= AMQP_DURABLE;
		if ($params['internal'])
			$flags |= AMQP_INTERNAL;
		if ($params['auto_delete'])
			$flags |= AMQP_AUTODELETE;
		
		$this->exchange()->setName($name);
		$this->exchange()->setFlags($flags);
		$this->exchange()->setType($type);
		$this->exchange()->setArguments($argumets);
		return $this->exchange()->declareExchange();
	}
	
	public function deleteExchange($name, $params = []) {
		$params = array_merge([
			'if_unused'		=> false, 
			'nowait'		=> false, 
		], $params);
		
		$flags = 0;
		if ($params['if_unused'])
			$flags |= AMQP_IFUNUSED;
		if ($params['nowait'])
			$flags |= AMQP_NOWAIT;
		
		return $this->exchange()->delete($name, $flags);
	}
	
	public function bindExchange($dst, $src, $routing_key = '', $arguments = []) {
		$this->exchange()->setName($dst);
		return $this->exchange()->bind($src, $routing_key, $arguments);
	}
	
	public function unbindExchange($dst, $src, $routing_key = '', $arguments = []) {
		$this->exchange()->setName($dst);
		return $this->exchange()->unbind($src, $routing_key, $arguments);
	}
	
	public static function instance($instance = 'default') {
		if (!isset(self::$instances[$instance]))
			self::$instances[$instance] = new AMQP($instance);
		return self::$instances[$instance];
	}
}
