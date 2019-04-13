<?php
namespace Z\Net;

use \PhpAmqpLib\Connection\AMQPStreamConnection;

class AMQP {
	protected static $instances = [];
	
	protected function __construct($instance) {
		
	}
	
	public static function instance($instance = 'default') {
		if (!isset(self::$instances[$instance])) {
			$config = array_merge([
				'host'				=> '127.0.0.1', 
				'login'				=> 'guest', 
				'password'			=> 'guest', 
				'port'				=> 5672, 
				'vhost'				=> ''
			], \Z\Config::get("amqp", $instance));
			
			$connection = new AMQPStreamConnection($config['host'], $config['port'], $config['login'], $config['password'], $config['vhost']);
			self::$instances[$instance] = $connection->channel();
		}
		return self::$instances[$instance];
	}
}
