<?php
if ($_POST) {
	$from_hh = min(max(0, (int) array_val($_POST, 'from_hh', 0)), 23);
	$from_mm = min(max(0, (int) array_val($_POST, 'from_mm', 0)), 59);
	
	$to_hh = min(max(0, (int) array_val($_POST, 'to_hh', 0)), 23);
	$to_mm = min(max(0, (int) array_val($_POST, 'to_mm', 0)), 59);
	
	$interval_hh = min(max(0, (int) array_val($_POST, 'hh', 0)), 23);
	$interval_mm = min(max(0, (int) array_val($_POST, 'mm', 0)), 59);
	
	$to = $to_hh * 3600 + $to_mm * 60;
	$from = $from_hh * 3600 + $from_mm * 60;
	$interval = $interval_hh * 3600 + $interval_mm * 60;
	
	$interval = round($interval / 300) * 300;
	
	Mysql::query("UPDATE `vk_groups` SET `period_from` = $from, `period_to` = $to, `interval` = $interval WHERE `id` = $gid");
}
header("Location: ".preg_replace("/[\s:]/si", "", isset($_REQUEST['return']) ? $_REQUEST['return'] : '?'));
