<?php
namespace Smm\Instagram\API;

class Response {
	protected $data;
	
	public function __construct($data) {
		$this->data = $data;
	}
	
	public function success() {
		return !isset($this->data->error);
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
