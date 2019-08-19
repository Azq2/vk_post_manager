<?php
namespace Smm\Task;

use \Z\DB;
use \Z\Config;
use \Z\Util\Url;
use \Z\Net\VkApi;

use \Smm\VK\Captcha;

class DuplicateFinder extends \Z\Task {
	const QUEUE_DIR = APP.'tmp/download_queue';
	
	protected $api;
	
	public function run($args) {
		if (!\Smm\Utils\Lock::lock(__CLASS__))
			return;
		
		$this->api = new \Z\Net\VkApi(\Smm\Oauth::getAccessToken('VK'));
		$this->api->setLimit(3, 1.1);
		
		echo date("Y-m-d H:i:s")." - start\n";
		
		$amqp = \Z\Net\AMQP::instance();
		$amqp->queue_declare('duplicate_finder_queue', false, true);
		
		$n = 0;
		while (1) {
			while (($amqp_msg = $amqp->basic_get('duplicate_finder_queue', false))) {
				$id = $amqp_msg->body;
				
				$queue = \Z\Cache::instance()->get("duplicate_finder_queue:$id");
				if (!$queue || !is_object($queue)) {
					echo "$id: delete invalid task.\n";
					\Z\Cache::instance()->delete("duplicate_finder_queue:$id");
					$amqp->basic_ack($amqp_msg->delivery_info['delivery_tag']);
					continue;
				}
				
				if (time() - $queue->ctime > 600) {
					echo "$id: delete expired task (".date("Y-m-d H:i:s", $queue->ctime).")\n";
					\Z\Cache::instance()->delete("duplicate_finder_queue:$id");
					$amqp->basic_ack($amqp_msg->delivery_info['delivery_tag']);
				} elseif ($queue->done ?? false) {
					echo "$id: already done.\n";
					$amqp->basic_ack($amqp_msg->delivery_info['delivery_tag']);
				} else {
					$this->findDuplicate($id, $queue);
					$amqp->basic_ack($amqp_msg->delivery_info['delivery_tag']);
				}
			}
			
			usleep(300000);
		}
	}
	
	public function findDuplicate($id, $queue) {
		$curl = curl_init();
		curl_setopt_array($curl, [
			CURLOPT_RETURNTRANSFER		=> true, 
			CURLOPT_FOLLOWLOCATION		=> true, 
			CURLOPT_VERBOSE				=> false, 
			CURLOPT_USERAGENT			=> "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36 Edge/12.10240", 
			CURLOPT_IPRESOLVE			=> CURL_IPRESOLVE_V4
		]);
		
		echo date("Y-m-d H:i:s")." search for ".$queue->photo."\n";
		
		$cache = \Z\Cache::instance();
		
		$results = $cache->get("duplicate_finder:".$queue->photo);
		if (!$results) {
			$queue->parsed = true;
			$cache->set("duplicate_finder_queue:$id", $queue, 3600);
			
			$ok = false;
			for ($i = 0; $i < 10; ++$i) {
				$exec_result = $this->api->exec("photos.search", [
					'q'				=> "copy:".$queue->photo, 
					'sort'			=> 0, 
					'count'			=> 10, 
					'radius'		=> 50000
				]);
				if ($exec_result->success()) {
					$ok = true;
					echo "=> OK\n";
					break;
				} else {
					if ($exec_result->error())
						echo "=> error: ".$exec_result->error()."\n";
					
					$flood = false;
					$sleep = false;
					
					if ($exec_result->errorCode() == \Z\Net\VkApi\Response::VK_ERR_FLOOD)
						$flood = true;
					if ($exec_result->errorCode() == \Z\Net\VkApi\Response::VK_ERR_TOO_FAST)
						$sleep = true;
					
					if ($flood)
						break;
					
					sleep($sleep ? 1 : 60);
				}
			}
			
			if (!$ok) {
				echo "=> fatal error, can't search photos...\n";
				$queue->done = true;
				$queue->error = "Ошибка поиска фото: ".$exec_result->error();
				$cache->set("duplicate_finder_queue:$id", $queue, 3600);
				return;
			}
			
			$results = [];
			
			foreach ($exec_result->response->items as $photo) {
				echo "=> https://vk.com/photo".$photo->owner_id."_".$photo->id." [".date("Y-m-d H:i:s", $photo->date)."]\n";
				
				$result = [
					'photo'		=> $photo, 
					'wall'		=> false
				];
				
				$ok = false;
				for ($i = 0; $i < 50; $i += 40) {
					$url = "https://vk.com/wall".$photo->owner_id."?day=".date("dmY", $photo->date)."&offset=$i";
					
					$res = "";
					
					for ($j = 0; $j < 3; ++$j) {
						curl_setopt($curl, CURLOPT_URL, $url);
						$res = curl_exec($curl);
						$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
						
						if ($status >= 200 && $status <= 299)
							break;
						sleep(1);
					}
					
					if (preg_match("/'".preg_quote($photo->owner_id."_".$photo->id)."'\s*,\s*'(wall[\d+-]+_\d+)'/", $res, $m)) {
						echo "==> https://vk.com/".$m[1]."\n";
						$result['wall'] = $m[1];
						$ok = true;
						break;
					}
					
					usleep(100000);
				}
				
				if (!$ok)
					echo "==> posts not found\n";
				
				$results[] = $result;
			}
			
			$cache->set("duplicate_finder:".$queue->photo, $results, 3600 * 24);
		} else {
			echo "=> from cache\n";
		}
		
		$queue->done = true;
		$queue->results = $results;
		$cache->set("duplicate_finder_queue:$id", $queue, 3600);
		
		echo date("Y-m-d H:i:s")." done.\n";
	}
}
