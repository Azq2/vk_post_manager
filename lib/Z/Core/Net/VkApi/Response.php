<?php
namespace Z\Core\Net\VkApi;

class Response {
	const VK_ERR_TOO_FAST		= 6;
	const VK_ERR_NEED_CAPTCHA	= 14;
	
	protected $data;
	
	public function __construct($data) {
		$this->data = $data;
	}
	
	public function success() {
		return isset($this->data->response);
	}
	
	public function error() {
		if ($this->success())
			return false;
		
		if (isset($this->data->error)) {
			return '#'.$this->data->error->error_code.' '.$this->data->error->error_msg;
		} else {
			return 'Invalid reponse.';
		}
	}
	
	public function errorCode() {
		if ($this->success())
			return false;
		
		if (isset($this->data->error)) {
			return $this->data->error->error_code;
		} else {
			return -1;
		}
	}
	
	public function captcha() {
		if ($this->success())
			return false;
		
		if (isset($this->data->error)) {
			if ($this->data->error->error_code == self::VK_ERR_NEED_CAPTCHA) {
				return [
					'url' => $this->data->error->captcha_img, 
					'sid' => $this->data->error->captcha_sid
				];
			}
		}
		
		return false;
	}
	
	public function __isset($k) {
		return isset($this->data->{$k});
	}
	
	public function __get($k) {
		return $this->data->{$k};
	}
	
	public function __set($k, $v) {
		throw new \Exception("Readonly!");
	}
	
	public function __unset($k) {
		throw new \Exception("Readonly!");
	}
}
