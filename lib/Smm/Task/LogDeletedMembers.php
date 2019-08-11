<?php
namespace Smm\Task;

use \Z\DB;
use \Z\Config;
use \Z\Util\Url;
use \Z\Net\VkApi;

use \Smm\VK\Captcha;

class LogDeletedMembers extends \Z\Task {
	public function run($args) {
		if (!\Smm\Utils\Lock::lock(__CLASS__)) {
			echo "Already running.\n";
			return;
		}
		
		ini_set('memory_limit', '2G');
		
		$curl = curl_init();
		curl_setopt_array($curl, [
			CURLOPT_RETURNTRANSFER		=> true, 
			CURLOPT_FOLLOWLOCATION		=> true, 
			CURLOPT_VERBOSE				=> false, 
			CURLOPT_USERAGENT			=> "Mozilla/5.0 (Linux; Android 6.0.1; SM-G532G Build/MMB29T) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/63.0.3239.83 Mobile Safari/537.36", 
			CURLOPT_IPRESOLVE			=> CURL_IPRESOLVE_V4
		]);
		
		echo date("Y-m-d H:i:s")."\n";
		
		$deleted_uids = DB::select()
			->from('vk_comm_users')
			->where('deactivated', '=', 2)
			->group('uid')
			->execute();
		
		foreach ($deleted_uids as $u) {
			$complete_deleted = false;
			
			if ($u['first_name'] == 'DELETED' && $u['last_name'] == '') {
				$complete_deleted = true;
			} else {
				curl_setopt($curl, CURLOPT_URL, "http://vk.com/foaf.php?id=".$u['uid']);
				$data = curl_exec($curl);
				if (stripos($data, "<foaf:Person>") !== false) {
					$complete_deleted = stripos($data, "<ya:firstName>") === false;
				} else {
					echo $u['uid']." - ERROR: ".curl_error($curl)."\n";
				}
			}
			
			if ($complete_deleted) {
				echo $u['uid']." - complete deteled!\n";
				
				DB::insert('vk_comm_users_deleted')
					->ignore()
					->set([
						'user_id'		=> $u['uid']
					])
					->execute();
				
				DB::update('vk_comm_users')
					->set([
						'deactivated'	=> 3
					])
					->where('uid', '=', $u['uid'])
					->execute();
			} else {
				echo $u['uid']." - still alive!\n";
			}
		}
		
		echo date("Y-m-d H:i:s")." - done!\n";
	}
}
