<?php

if (!\Z\User::instance()->can('admin'))
	die("Доступ только админам!");

$history = [];
$req = Mysql::query("SELECT * FROM `vk_smm_money_out` WHERE `group_id` = ?", $gid);

while ($row = $req->fetchObject())
	$history[] = $row;

if (isset($_POST['do'])) {
	Mysql::query("START TRANSACTION");
	
	$smm_money = Mysql::query("SELECT * FROM `vk_smm_money` WHERE `group_id` = ? FOR UPDATE", $gid)
		->fetchObject();
	
	Mysql::query("
		INSERT INTO `vk_smm_money_out` SET
			time		= ".time().", 
			last_time	= ".$smm_money->last_date.", 
			group_id	= $gid, 
			sum			= ".$smm_money->money."
	");
	
	Mysql::query("
		UPDATE `vk_smm_money` SET
			last_date	= ".time().", 
			money		= 0
		WHERE
			group_id	= $gid
	");
	
	Mysql::query("COMMIT");
	
	header("Location: ?a=smm_money");
} else {
	mk_page(array(
		'title' => 'Выплаты', 
		'content' => Tpl::render("smm_money.html", [
			'history'	=> $history
		])
	));
}
