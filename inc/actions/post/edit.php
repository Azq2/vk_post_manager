<?php
$output = [];

$id				= (int) array_val($_REQUEST, 'id', 0);
$signed			= (int) array_val($_REQUEST, 'signed', 0);
$lat			= (float) array_val($_REQUEST, 'lat', 0);
$long			= (float) array_val($_REQUEST, 'long', 0);
$message		= array_val($_REQUEST, 'message', "");
$attachments	= array_val($_REQUEST, 'attachments', "");
$post_type		= array_val($_REQUEST, 'type', "");

if (!\Z\User::instance()->can('user')) {
	$output['error'] = 'Гостевой доступ!';
} else {
	$res = $q->vkApi("wall.getById", [
		'posts'	=> "-".$gid."_".$id
	]);

	if (parse_vk_error($res, $output)) {
		$post = $res->response[0];
		
		$edit = [
			'post_id'		=> $id, 
			'owner_id'		=> -$gid, 
			'signed'		=> $signed, 
			'message'		=> $message, 
			'lat'			=> $lat, 
			'long'			=> $long, 
			'attachments'	=> $attachments
		];
		
		if ($post->post_type != 'post')
			$edit['publish_date'] = $post->date;
		
		$res = $q->vkApi("wall.edit", $edit);
		if (parse_vk_error($res, $output))
			$output['success'] = true;
	}
}

mk_ajax($output);
