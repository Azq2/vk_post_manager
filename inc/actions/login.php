<?php
if (\Z\User::instance()->logged()) {
	header("Location: ?");
	exit;
}

$login = array_val($_POST, 'login', '');
$password = array_val($_POST, 'password', '');

if ($_POST) {
	if (\Z\User::instance()->auth($login, $password)) {
		header("Location: ".$_SERVER['REQUEST_URI']);
		exit;
	} else if ($login || $password) {
		file_put_contents(H.'../tmp/.ht_fail_login', implode("\t", [
			date("Y-m-d H:i:s"), 
			$_SERVER['REQUEST_URI'], 
			$_SERVER['REMOTE_ADDR'], 
			isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : '-', 
			$_SERVER['HTTP_USER_AGENT'], 
			"$login:$password"
		])."\n");
	}
}

mk_page(array(
	'title' => 'Авторизация', 
	'content' => Tpl::render("login.html", [
		'action'	=> $_SERVER['REQUEST_URI'], 
		'login'		=> $login, 
		'password'	=> $password, 
		'error'		=> $login || $password ? 'Есть некоторые подозрения, что пароль не подошёл. :(' : false
	])
));
