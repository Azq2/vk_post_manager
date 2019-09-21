<?php
return [
	'VK'			=> [
		'title'		=> 'ВК (системный)', 
		'type'		=> 'VK', 
		'client'	=> 'standalone', 
		'auth'		=> 'code', 
		'required'	=> true
	], 
	'VK_SCHED'			=> [
		'title'		=> 'ВК (щедулер)', 
		'type'		=> 'VK', 
		'client'	=> 'standalone', 
		'auth'		=> 'code', 
		'required'	=> true
	], 
	'VK_GRABBER'		=> [
		'title'		=> 'ВК (граббер)', 
		'type'		=> 'VK', 
		'client'	=> 'standalone', 
		'auth'		=> 'code', 
		'required'	=> true
	], 
	'VK_STAT'			=> [
		'title'		=> 'ВК (стата)', 
		'type'		=> 'VK', 
		'client'	=> 'standalone', 
		'auth'		=> 'code', 
		'required'	=> true
	], 
	'VK_WEB'		=> [
		'title'		=> 'ВК (посты от vk.com)', 
		'type'		=> 'VK_WEB', 
		'required'	=> false
	], 
	'VK_ANDROID'	=> [
		'title'		=> 'ВК (посты от android)', 
		'type'		=> 'VK', 
		'client'	=> 'vk_android', 
		'auth'		=> 'direct', 
		'required'	=> false
	], 
	'VK_VKADMIN'	=> [
		'title'		=> 'ВК (посты от VK Admin)', 
		'type'		=> 'VK', 
		'client'	=> 'vk_admin', 
		'auth'		=> 'direct', 
		'required'	=> false
	], 
	'PINTEREST_COOKIE'	=> [
		'title'		=> 'Pinterest (граббер)', 
		'type'		=> 'PINTEREST_COOKIE', 
		'auth'		=> 'cookie', 
		'required'	=> false
	]
];
