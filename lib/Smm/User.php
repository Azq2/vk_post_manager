<?php
namespace Smm;

class User {
	use \Z\Traits\Singleton;
	
	public function __construct() {
		$this->auth();
	}
	
	public function logout() {
		setcookie('login', 1, time() - 365 * 24 * 3600 * 2);
		setcookie('password', 1, time() - 365 * 24 * 3600 * 2);
	}
	
	public function getSessionCred() {
		$cookie_login = $_COOKIE['login'] ?? '';
		$cookie_password = $_COOKIE['password'] ?? '';
		
		if (!$cookie_login)
			$cookie_login = 'guest';
		
		if (!$cookie_password && isset($_SERVER['PHP_AUTH_PW']))
			$cookie_password = $_SERVER['PHP_AUTH_PW'];
		
		$users = \Z\Config::get("users");
		
		return [
			'login'		=> isset($users[$cookie_login]) ? strtolower(trim($cookie_login)) : 'guest', 
			'password'	=> trim($cookie_password), 
		];
	}
	
	public function auth($login = false, $password = false) {
		if ($login !== false && $password !== false) {
			$_COOKIE['login'] = $login;
			$_COOKIE['password'] = $password;
			
			setcookie('login', $login, time() + 365 * 24 * 3600 * 2);
			setcookie('password', $password, time() + 365 * 24 * 3600 * 2);
		}
		
		$users = \Z\Config::get("users");
		$cred = $this->getSessionCred();
		
		$this->user = $users[$cred['login']];
		$this->user['login'] = $cred['login'];
		
		return $this->logged();
	}
	
	public function logged() {
		$cred = $this->getSessionCred();
		return $this->user && $cred['password'] === $this->user['password'];
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
}
