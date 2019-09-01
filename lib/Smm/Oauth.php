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
	const VK_ACCESS_WALL				= (1 << 13);
	
	const VK_MINIMAL_GROUP_ACCESS =
		self::VK_ACCESS_GROUP_PHOTOS | 
		self::VK_ACCESS_GROUP_APP_WIDGETS | 
		self::VK_ACCESS_GROUP_MESSAGES | 
		self::VK_ACCESS_GROUP_DOCS | 
		self::VK_ACCESS_GROUP_MANAGE | 
		self::VK_ACCESS_WALL;
	
	public static function getAccessToken($type) {
		$oauth_users = \Z\Config::get('oauth_users');
		
		if (!isset($oauth_users[$type]))
			throw new \Exception("Unknown oauth type!");
		
		$token = DB::select()
			->from('vk_oauth')
			->where('type', '=', $type)
			->execute()
			->current();
		
		if ($token) {
			return [
				'access_token'			=> $token['access_token'], 
				'secret'				=> $token['secret'], 
				'access_token_type'		=> 'user', 
				'client'				=> $oauth_users[$type]['client']
			];
		}
		
		return false;
	}
	
	public static function getGroupAccessToken($id) {
		$token = DB::select()
			->from('vk_groups_oauth')
			->where('group_id', '=', $id)
			->execute()
			->current();
		
		if ($token) {
			return [
				'access_token'			=> $token['access_token'], 
				'secret'				=> '', 
				'access_token_type'		=> 'community', 
				'client'				=> 'standalone'
			];
		}
		
		return false;
	}
	
	public static function getServiceToken($type) {
		$oauth = \Z\Config::get("oauth");
		
		if (!isset($oauth[$type]))
			throw new \Exception("Unknown oauth app!");
		
		if ($oauth[$type]['service_key']) {
			return [
				'access_token'			=> $oauth[$type]['service_key'], 
				'secret'				=> '', 
				'access_token_type'		=> 'user', 
				'client'				=> 'standalone'
			];
		}
		
		return false;
	}
}
