<?php
$search = array_val($_GET, 'q', "");

$res = $q->vkApi("users.search", array(
	'q'			=> $search, 
	'group_id'	=> $gid, 
	'fields'	=> 'sex,photo_50,bdate,verified'
));

$result = array();
foreach ($res->response->items as $user) {
	$ret = vk_user_widget($user, '?a=user_info&amp;id='.$user->id);
	$ret['id'] = $user->id;
	$result[] = $ret;
}
mk_ajax(array('list' => Tpl::render("widgets/search_users_result.html", array(
	'users' => $result
))));
