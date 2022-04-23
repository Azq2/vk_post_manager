<?php
namespace Smm\Tumblr\API;

class Response {
	protected $code;
	protected $data;
	
	public function __construct($code, $data) {
		$this->code = $code;
		$this->data = $data;
	}
	
	public function success() {
		return $this->code == 200;
	}
	
	public function error() {
		if ($this->success())
			return false;
		
		if (isset($this->error_description)) {
			return $this->error_description;
		} elseif (isset($this->error)) {
			return $this->error;
		} elseif (isset($this->errors)) {
			$error_msg = [];
			foreach ($this->errors as $error) {
				$error_msg[] = $error->code.": ".$error->title;
			}
			return implode("; ", $error_msg);
		} else {
			return 'Invalid reponse. Code: '.$this->code;
		}
	}
	
	public function errorCode() {
		if ($this->success())
			return false;
		return $this->code;
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
