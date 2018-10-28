<?php
namespace Z\Smm;

use Mysql;

class Globals {
	public static function get($gid, $key, $default = NULL) {
		$req = Mysql::query("SELECT `value` FROM `vk_globals` WHERE `group_id` = ? AND `key` = ?", $gid, $key);
		return $req->num() ? $req->result() : $default;
	}
	
	public static function set($gid, $key, $value) {
		Mysql::query("INSERT INTO `vk_globals` SET `group_id` = ?, `key` = ?, `value` = ? ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)", $gid, $key, $value);
	}
	
	public static function delete($gid, $key) {
		Mysql::query("DELETE FROM `vk_globals` WHERE `group_id` = ? AND `key` = ?", $gid, $key);
	}
}
