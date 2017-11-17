<?php
error_reporting(E_ALL);

use \Z\Core\Model\ActiveRecord;

require dirname(__FILE__)."/../inc/init.php";

$app = Mysql::query("SELECT * FROM `vkapp` WHERE `app` = 'catlist'")->fetchObject();
$game = new \Z\Catlist\Game($app->group_id);
$game->cronVkLikes();
