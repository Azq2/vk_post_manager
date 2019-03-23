<?php
namespace Smm;

use \Z\DB;
use \Z\View;
use \Z\App\Exception\NotFound;

class GroupController extends \Smm\BaseController {
	public function before() {
		$retval = parent::before();
		if ($retval === false)
			return $retval;
		
		$gid = isset($_REQUEST['gid']) ? (int) $_REQUEST['gid'] : 0;
		
		if ($gid) {
			$group = DB::select()
				->from('vk_groups')
				->where('id', '=', $gid)
				->execute()
				->current();
		} else {
			$group = DB::select()
				->from('vk_groups')
				->order('pos', 'ASC')
				->limit(1)
				->execute()
				->current();
		}
		
		if (!$group)
			throw new NotFound();
		
		$this->setActiveGroup($group);
	}
}
