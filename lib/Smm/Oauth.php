<?php
namespace Smm;

use \Z\DB;

class Oauth {
	public static function getAccessToken($type) {
		return DB::select('access_token')
			->from('vk_oauth')
			->where('type', '=', $type)
			->execute()
			->get('access_token', '');
	}
}
