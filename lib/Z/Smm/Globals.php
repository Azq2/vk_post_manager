<?php
namespace Z\Smm;

use \Z\Core\DB;

class Globals {
	public static function get($gid, $key, $default = NULL) {
		return DB::select('value')
			->from('vk_globals')
			->where('group_id', '=', $gid)
			->where('key', '=', $key)
			->execute()
			->get('value', $default);
	}
	
	public static function set($gid, $key, $value) {
		DB::insert('vk_globals')
			->set([
				'group_id'	=> $gid, 
				'key'		=> $key, 
				'value'		=> $value
			])
			->onDuplicateSetValues('value')
			->execute();
	}
	
	public static function delete($gid, $key) {
		DB::delete('vk_globals')
			->where('group_id', '=', $gid)
			->where('key', '=', $key)
			->execute();
	}
}
