<?php
namespace Smm\VK\API;

class OauthResponse {
	protected $data;
	
	public function __construct($data) {
		$this->data = $data;
	}
	
	public function success() {
		return property_exists($this->data, 'access_token');
	}
	
	public function error() {
		if ($this->success())
			return false;
		
		if (isset($this->data->error)) {
			return $this->data->error_description ?? $this->data->error_type ?? $this->data->error;
		} else {
			return 'Invalid response.';
		}
	}
	
	public function errorCode() {
		if ($this->success())
			return false;
		
		return $this->data->error ?? 'unknown';
	}
	
	public function captcha() {
		if ($this->success())
			return false;
		
		if (isset($this->data->error)) {
			if ($this->data->error == "need_captcha") {
				return [
					'url' => $this->data->captcha_img, 
					'sid' => $this->data->captcha_sid
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
	
	public function asObject() {
		return $this->data;
	}
}
