<?php
$output = [];

$id				= (int) array_val($_REQUEST, 'id', 0);
$signed			= (int) array_val($_REQUEST, 'signed', 0);
$lat			= (float) array_val($_REQUEST, 'lat', 0);
$long			= (float) array_val($_REQUEST, 'long', 0);
$message		= array_val($_REQUEST, 'message', "");
$attachments	= array_val($_REQUEST, 'attachments', "");
$post_type		= array_val($_REQUEST, 'type', "");

if ($post_type == 'post')
	die;

if (!\Z\User::instance()->can('user')) {
	$output['error'] = 'Гостевой доступ!';
} else {
	$req = Mysql::query("SELECT MAX(`fake_date`) FROM `vk_posts_queue`");
	$fake_date = max(time() + 3600 * 24 * 60, $req->num() ? $req->result() : 0) + 3600;

	$api_data = [
		'owner_id'		=> -$gid, 
		'signed'		=> $signed, 
		'message'		=> $message, 
		'lat'			=> $lat, 
		'long'			=> $long, 
		'attachments'	=> $attachments, 
		'publish_date'	=> $fake_date
	];

	if ($id)
		$api_data['post_id'] = $id;

	$res = $q->vkApi(($post_type == 'suggest' || $post_type == 'new') ? "wall.post" : "wall.edit", $api_data);

	$output['post_type'] = $post_type;
	if (parse_vk_error($res, $output)) {
		$output['success'] = true;
		$output['date'] = display_date($fake_date);
		
		Mysql::query("
			INSERT INTO `vk_posts_queue`
			SET
				`fake_date`	= $fake_date, 
				`group_id`	= $gid, 
				`id`		= ".(isset($res->response->post_id) ? (int) $res->response->post_id : $id)."
		");
	}
}

mk_ajax($output);
