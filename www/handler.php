<?php
require dirname(__FILE__)."/../inc/init.php";

$handler = new \Z\VkApp\Handler();
$handler->handle(file_get_contents('php://input'));
