<?php
namespace Z\Net;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class AMQP {
    const DELIVERY_MODE_NON_PERSISTENT	= 1;
    const DELIVERY_MODE_PERSISTENT		= 2;
	
	protected static $instances = [];
	protected $channel, $config;
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
	
	protected function channel() {
		if (!$this->channel) {
			$connection = new AMQPStreamConnection($this->config['host'], $this->config['port'], $this->config['login'], $this->config['password'], $this->config['vhost']);
			$this->channel = $connection->channel();
		}
		return $this->channel;
	}
	
	/* Basic */
	public function ack($delivery_tag, $params = []) {
		$params = array_merge([
			'multiple'		=> false, 
		], $params);
		
		return $this->channel()->basic_ack($delivery_tag, $params['multiple']);
	}
	
	public function nack($delivery_tag, $params = []) {
		$params = array_merge([
			'multiple'		=> false, 
			'requeue'		=> false
		], $params);
		
		return $this->channel()->basic_nack($delivery_tag, $params['multiple'], $params['requeue']);
	}
	
	public function cancel($consumer_tag, $params = []) {
		$params = array_merge([
			'nowait'		=> false, 
			'noreturn'		=> false
		], $params);
		
		return $this->channel()->cancel($consumer_tag, $params['nowait'], $params['noreturn']);
	}
	
	public function consume($queue, $callback, $consumer_tag = '', $params = [], $arguments = []) {
		$params = array_merge([
			'no_ack'		=> false, 
			'no_local'		=> false, 
			'exclusive'		=> false, 
			'nowait'		=> false
		], $params);
		
		return $this->channel()->basic_consume($queue, $consumer_tag, $params['no_local'], $params['no_ack'], $params['exclusive'], $params['nowait'], $callback, NULL, $arguments);
	}
	
	public function get($queue, $params = []) {
		$params = array_merge([
			'no_ack'		=> false
		], $params);
		
		return $this->channel()->get($queue, $params['no_ack']);
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
		if (($msg instanceof AMQP\Message)) {
			$payload = new AMQPMessage($msg->body, $msg->getHeaders());
		} else {
			$payload = new AMQPMessage($msg, []);
		}
		
		$params = array_merge([
			'mandatory'		=> false, 
			'immediate'		=> false, 
		], $params);
		
		return $this->channel()->publish($payload, $exchange, $routing_key, $params['mandatory'], $params['immediate']);
	}
	
	/* Queue */
	public function declareQueue($name, $params = [], $argumets = []) {
		$params = array_merge([
			'passive'		=> false, 
			'durable'		=> false, 
			'exclusive'		=> false, 
			'auto_delete'	=> false, 
			'nowait'		=> false
		], $params);
		
		return $this->channel()->queue_declare($name, $params['passive'], $params['durable'], $params['exclusive'], $params['auto_delete'], $params['nowait'], $arguments);
	}
	
	public function deleteQueue($name, $params = []) {
		$params = array_merge([
			'if_unused'		=> false, 
			'if_empty'		=> false, 
			'nowait'		=> false
		], $params);
		
		return $this->channel()->queue_delete($name, $params['if_unused'], $params['if_empty'], $params['nowait']);
	}
	
	public function purgeQueue($name) {
		$params = array_merge([
			'nowait'		=> false
		], $params);
		
		return $this->channel()->queue_purge($name, $params['nowait']);
	}
	
	public function bindQueue($name, $exchange, $routing_key = '', $params = [], $arguments = []) {
		$params = array_merge([
			'nowait'		=> false
		], $params);
		
		return $this->channel()->queue_purge($name, $exchange, $routing_key, $params['nowait'], $arguments);
	}
	
	public function unbindQueue($name, $exchange, $arguments = []) {
		return $this->channel()->queue_purge($name, $exchange, $routing_key, $arguments);
	}
	
	/* Exchange */
	public function declareExchange($name, $type = 'direct', $params = [], $arguments = []) {
		$params = array_merge([
			'passive'		=> false, 
			'durable'		=> false, 
			'auto_delete'	=> false, 
			'internal'		=> false, 
			'nowait'		=> false
		], $params);
		
		return $this->channel()->exchange_declare($name, $type, $params['passive'], $params['durable'], $params['auto_delete'], $params['internal'], $params['nowait'], $arguments);
	}
	
	public function deleteExchange($name, $params = []) {
		$params = array_merge([
			'if_unused'		=> false, 
			'nowait'		=> false, 
		], $params);
		
		return $this->channel()->exchange_delete($name, $params['if_unused'], $params['nowait']);
	}
	
	public function bindExchange($dst, $src, $routing_key = '', $params = [], $arguments = []) {
		$params = array_merge([
			'if_unused'		=> false, 
			'nowait'		=> false, 
		], $params);
		
		return $this->channel()->exchange_bind($dst, $src, $routing_key, $params['nowait'], $arguments);
	}
	
	public function unbindExchange($dst, $src, $routing_key = '', $params = [], $arguments = []) {
		$params = array_merge([
			'if_unused'		=> false, 
			'nowait'		=> false, 
		], $params);
		
		return $this->channel()->exchange_unbind($dst, $src, $routing_key, $params['nowait'], $arguments);
	}
	
	public static function instance($instance = 'default') {
		if (!isset(self::$instances[$instance]))
			self::$instances[$instance] = new AMQP($instance);
		return self::$instances[$instance];
	}
}
