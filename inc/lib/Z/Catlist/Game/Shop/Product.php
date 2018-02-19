<?php
namespace Z\Catlist\Game\Shop;

use \Mysql;

class Product extends \Z\Core\Model\ActiveRecord {
	protected static $pk		= 'id';
	protected static $ai		= true;
	protected static $table		= 'vkapp_catlist_shop';
	
	public static function findAll($find = []) {
		$sql = [];
		foreach ($find as $f => $v) {
			if ($f == 'deleted' || $f == 'type')
				$sql[] = "`$f` = ".Mysql::value($v);
		}
		return self::createModels(Mysql::query("SELECT * FROM `vkapp_catlist_shop`".($sql ? " WHERE ".implode(" AND ", $sql) : "")));
	}
	
	public function checkPhotoExists($md5, $type) {
		return Mysql::query("SELECT EXISTS (SELECT 0 FROM `vkapp_catlist_shop` WHERE type = ? AND photo = ? AND id != ?)", $type, $md5, (int) $this->id)
			->result();
	}
}
