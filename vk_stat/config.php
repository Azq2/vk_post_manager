<?php
	define('DB_HOST', '127.0.0.1');
	define('DB_USER', 'root');
	define('DB_NAME', 'test');
	define('DB_PASSWORD', 'qwerty');
	define('CRON_PASSWORD', '********');
	
	$COMMS = array(
		'45481833', // Мания умиления
		'94594114', // catlist
		'141583571'
	);

	if (!function_exists('mysql_fetch_assoc')) {
		static $_mysql_link;
		function mysql_pconnect($host, $user, $passwd) {
			global $_mysql_link;
			return ($_mysql_link = mysqli_connect("p:".$host, $user, $passwd));
		}
		
		function mysql_connect($host, $user, $passwd) {
			global $_mysql_link;
			return ($_mysql_link = mysqli_connect("p:".$host, $user, $passwd));
		}
		
		function mysql_select_db($db) {
			global $_mysql_link;
			return mysqli_select_db($_mysql_link, $db);
		}
		
		function mysql_set_charset($charset) {
			global $_mysql_link;
			return mysqli_set_charset($_mysql_link, $charset);
		}
		
		function mysql_query($query) {
			global $_mysql_link;
			return mysqli_query($_mysql_link, $query);
		}
		
		function mysql_error() {
			global $_mysql_link;
			return mysqli_error($_mysql_link);
		}
		
		function mysql_fetch_assoc($query) {
			return mysqli_fetch_assoc($query);
		}
		
		function mysql_num_rows($query) {
			return mysqli_num_rows($query);
		}
		
		function mysql_fetch_row($query) {
			return mysqli_fetch_row($query);
		}
		
		function mysql_fetch_array($query) {
			return mysqli_fetch_array($query);
		}
		
		function mysql_result($query) {
			$row = mysqli_fetch_row($query);
			return $row[0];
		}
		
		function mysql_real_escape_string($query) {
			global $_mysql_link;
			return mysqli_real_escape_string($_mysql_link, $query);
		}
		
		function mysql_free_result($query) {
			return mysqli_free_result($query);
		}
		
		function mysql_num_fields($query) {
			return mysqli_num_fields($query);
		}
		
		function mysql_field_name($query, $i) {
			$res = mysqli_fetch_fields($query);
			return $res[$i]->name;
		}
	}
	
