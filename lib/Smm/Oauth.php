<?php
namespace Smm;

use \Z\DB;

class Oauth {
	// https://vk.com/dev/permissions
	const VK_ACCESS_GROUP_STORIES		= (1 << 0);
	const VK_ACCESS_GROUP_PHOTOS		= (1 << 2);
	const VK_ACCESS_GROUP_APP_WIDGETS	= (1 << 6);
	const VK_ACCESS_GROUP_MESSAGES		= (1 << 12);
	const VK_ACCESS_GROUP_DOCS			= (1 << 17);
	const VK_ACCESS_GROUP_MANAGE		= (1 << 18);
	
	const VK_MINIMAL_GROUP_ACCESS =
		self::VK_ACCESS_GROUP_PHOTOS | 
		self::VK_ACCESS_GROUP_APP_WIDGETS | 
		self::VK_ACCESS_GROUP_MESSAGES | 
		self::VK_ACCESS_GROUP_DOCS | 
		self::VK_ACCESS_GROUP_MANAGE;
	
	public static function getAccessToken($type) {
		return DB::select('access_token')
			->from('vk_oauth')
			->where('type', '=', $type)
			->execute()
			->get('access_token', '');
	}
	
	public static function getGroupAccessToken($id) {
		return DB::select('access_token')
			->from('vk_groups_oauth')
			->where('group_id', '=', $id)
			->execute()
			->get('access_token', '');
	}
}
