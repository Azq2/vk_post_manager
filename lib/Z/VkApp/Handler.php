<?php
namespace Z\VkApp;

use \Mysql;

class Handler {
	public function __construct() {
		error_reporting(E_ALL);
		
		// Логгируем варны
		set_error_handler(function ($errno, $errstr, $errfile, $errline) {
		//	if (error_reporting() == 0)
		//		return;
			
			$err = [
				E_ERROR					=> "error", 
				E_WARNING				=> "warning", 
				E_NOTICE				=> "notice", 
				E_DEPRECATED			=> "deprecated", 
				
				E_USER_ERROR			=> "error", 
				E_USER_WARNING			=> "warning", 
				E_USER_NOTICE			=> "notice", 
				E_USER_DEPRECATED		=> "deprecated", 
				
				E_STRICT				=> "strict", 
				E_RECOVERABLE_ERROR		=> "error", 
			];
			file_put_contents(APP."logs/handler.log", "[".date("d-m-Y H:i:s")."] [".$err[$errno]."] $errstr at $errfile:$errline\n", FILE_APPEND | LOCK_EX);
		}, E_ALL);
		
		// Логгируем ошибки и исключения
		set_exception_handler(function ($e) {
			file_put_contents(APP."logs/handler.log", "[".date("d-m-Y H:i:s")."] [exception] $e\n", FILE_APPEND | LOCK_EX);
		});
		
		// Логгируем весь вывод скрипта
		ob_start();
		ob_start(function ($b) {
			file_put_contents(APP."logs/handler.log", $b, FILE_APPEND | LOCK_EX);
			return $b;
		}, 1);
	}
	
	public function getCommApp($id) {
		return Mysql::query("SELECT * FROM `vkapp` WHERE `group_id` = ?", $id)->fetchObject();
	}
	
	public function handle($raw_data) {
		$data = json_decode($raw_data);
		if (!$data || !isset($data->type)) {
			$this->log("invalid data: %s", $raw_data);
			return;
		}
		$this->log(json_encode($data, JSON_PRETTY_PRINT));
		
		$app = $this->getCommApp($data->group_id);
		if (!$app) {
			$this->error("#%d - app not found", $data->group_id);
			return;
		}
		
		switch ($data->type) {
			case "confirmation":
				echo $app->handshake;
				$this->log("%d - handshake", $data->group_id);
			break;
			
			default:
				if ($app->secret !== $data->secret) {
					$this->error("#%d - invalid secret (%s)", $data->group_id, $data->secret);
					return;
				}
				
				$class_name = "\\Z\\".ucfirst($app->app)."\\Game";
				$class_name::instance()->handle($data);
				
				ob_end_clean();
				ob_end_clean();
				
				echo "ok"; // для VK
			break;
		}
	}
	
	public function error() {
		$msg = call_user_func_array("sprintf", func_get_args());
		file_put_contents(APP."logs/handler.log", "E ".date("Y-m-d H:i:s")." $msg\n", FILE_APPEND | LOCK_EX);
	}
	
	public function log() {
		$msg = call_user_func_array("sprintf", func_get_args());
		file_put_contents(APP."logs/handler.log", "I ".date("Y-m-d H:i:s")." $msg\n", FILE_APPEND | LOCK_EX);
	}
}
