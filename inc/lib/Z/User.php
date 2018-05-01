<?php
namespace Z;

class User {
	private static $_instance;
	
	public function __construct() {
		$this->auth();
	}
	
	public function logout() {
		setcookie('login', 1, time() - 365 * 24 * 3600 * 2);
		setcookie('password', 1, time() - 365 * 24 * 3600 * 2);
	}
	
	public function auth($login = false, $password = false) {
		if ($login && $password) {
			$_COOKIE['login'] = $login;
			$_COOKIE['password'] = $password;
			setcookie('login', $login, time() + 365 * 24 * 3600 * 2);
			setcookie('password', $password, time() + 365 * 24 * 3600 * 2);
		}
		
		$user = strtolower(isset($_COOKIE['login']) ? $_COOKIE['login'] : 'guest');
		$users = \Z\Core\Config::get("users");
		$this->user = isset($users[$user]) ? $users[$user] : $users['guest'];
		$this->user['login'] = $user;
		
		return $this->logged();
	}
	
	public function logged() {
		$password = isset($_COOKIE['password']) ? $_COOKIE['password'] : (isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '');
		return $this->user && $password === $this->user['password'];
	}
	
	public function can($name) {
		return $this->logged() && in_array($name, $this->user['groups']);
	}
	
	public function __get($k) {
		return $this->user[$k];
	}
	
	public function __isset($k) {
		return isset($this->user[$k]);
	}
	
	public static function instance() {
		if (!self::$_instance)
			self::$_instance = new self;
		return self::$_instance;
	}
}
