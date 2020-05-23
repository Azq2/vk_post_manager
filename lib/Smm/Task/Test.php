<?php
namespace Smm\Task;

use \Z\DB;
use \Z\Config;
use \Z\Util\Url;

class TestModel extends \Z\Model\ActiveRecord {
	protected static $table = "vk_widget_top_users";
}

class Test extends \Z\Task {
	public function run($args) {
		$api = new \Smm\VK\API(\Smm\Oauth::getGroupAccessToken(186341291));
		
		$result = $api->exec("likes.getList", [
			"type"		=> "post", 
			"owner_id"	=> -186341291, 
			"item_id"	=> 35407, 
			"offset"	=> 0, 
			"count"		=> 1000
		]);
		
		var_dump($result);
		exit;
		
		$q = DB::update('table');
		
		$q->bitUp('a', 1);
		$q->bitDown('a', 4);
		$q->incr('a');
		$q->decr('a');
		
		echo "$q\n";
		
		// ((((a | 1) &~ 4) + 1) - 1)
		
		$q = DB::update('table');
		
		$q->set('a', 2);
		$q->bitUp('a', 1);
		$q->bitDown('a', 4);
		$q->incr('a');
		$q->decr('a');
		
		echo "$q\n";
		
		// ((((2 | 1) &~ 4) + 1) - 1)
		
		$q = DB::update('table');
		
		$q->bitUp('a', 1);
		$q->bitDown('a', 4);
		$q->incr('a');
		$q->decr('a');
		$q->set('a', 2);
		
		echo "$q\n";
		
		// 2
		
		$q = DB::update('table');
		
		$q->incr('a');
		
		echo "$q\n";
		
		// + 1
	// 	new TestModel();
	}
}
