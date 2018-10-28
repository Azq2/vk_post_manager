<?php
$next_post_date = \Z\Smm\Globals::get($gid, "next_post_date");
if (!$next_post_date) {
	get_comments($q, $comm);
	$next_post_date = \Z\Smm\Globals::get($gid, "next_post_date");
}

mk_ajax([
	'gid'	=> $gid, 
	'date'	=> $next_post_date < time() ? time() : $next_post_date
]);
