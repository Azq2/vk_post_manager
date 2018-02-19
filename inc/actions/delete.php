<?php
$id = (int) array_val($_REQUEST, 'id', 0);
$restore = (int) array_val($_REQUEST, 'restore', 0);

$output = array();

$res = $q->vkApi($restore ? "wall.restore" : "wall.delete", array(
	'owner_id' => -$gid, 
	'post_id' => $id
));
if (parse_vk_error($res, $output))
	$output['success'] = true;
mk_ajax($output);
