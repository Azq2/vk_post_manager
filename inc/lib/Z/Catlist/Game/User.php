<?php
namespace Z\Catlist\Game;

use \Mysql;

class User extends \Z\Core\Model\ActiveRecord {
	protected static $pk		= 'user_id';
	protected static $ai		= false;
	protected static $table		= 'vkapp_catlist_users';
	
	private $money_change_reason;
	
	public function setAction($action, $state = "") {
		$this->action	= $action;
		$this->state	= $state ? json_encode($state) : "";
		$this->mtime	= time();
		return $this;
	}
	
	public function getState() {
		return $this->state ? json_decode($this->state) : [];
	}
	
	public function moneyIncr($value, $descr) {
		if ($this->isChanged('money'))
			throw new \Exception("User money already changed!");
		$this->incr('money', $value);
		$this->money_change_reason = $descr;
		return $this;
	}
	
	public function moneyDecr($value, $descr) {
		return $this->moneyIncr(-$value, $descr);
	}
	
	public function onAfterSave() {
		if ($this->isChanged('deny')) {
			if ($this->deny) {
				Mysql::query("INSERT INTO `vkapp_catlist_deny` SET `user_id` = ?, `time` = ?
					ON DUPLICATE KEY UPDATE `time` = VALUES(`time`)", $this->user_id, time());
			} else {
				Mysql::query("DELETE FROM `vkapp_catlist_deny` WHERE `user_id` = ?", $this->user_id);
			}
		}
		
		if ($this->money_change_reason) {
			$old_money = $this->getOldValue('money');
			Mysql::query("
				INSERT INTO `vkapp_catlist_money_history` SET
					`user_id`	= ?, 
					`ctime`		= ?, 
					`diff`		= ?, 
					`value`		= ?, 
					`descr`		= ?
				", 
				$this->user_id, 
				time(), 
				$this->money - $old_money, 
				$this->money, 
				$this->money_change_reason
			);
		}
		$this->money_change_reason = NULL;
		
		return true;
	}
}
