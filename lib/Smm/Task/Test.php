<?php
namespace Smm\Task;

use \Z\DB;
use \Z\Config;
use \Z\Util\Url;

class TestModel extends \Z\Model\ActiveRecord {
	public static function table() {
		return "vk_widget_top_users";
	}
}

class Test extends \Z\Task {
	public function run($args) {
		$ig = new Smm\Instagram\Web();
		$ig->auth();
		
		
		// var_dump(new TestModel());
		
		/*
		$sources = DB::select()
			->from('vk_grabber_sources')
			->execute();
		
		foreach ($sources as $s) {
			$url = "";
			
			switch ($s['type']) {
				case \Smm\Grabber::SOURCE_VK:
					$url = 'https://vk.com/public'.(-$s['value']);
				break;
				
				case \Smm\Grabber::SOURCE_INSTAGRAM:
					$value = substr($s['value'], 1);
					if ($s['value'][0] == '#') {
						$url = 'https://www.instagram.com/explore/tags/'.urlencode($value);
					} elseif ($s['value'][0] == '@') {
						$url = 'https://www.instagram.com/'.urlencode($value);
					}
				break;
				
				case \Smm\Grabber::SOURCE_PINTEREST:
					$url = 'https://www.pinterest.ru/search/pins/?rs=ac&len=2&q='.urlencode($s['value']);
				break;
			}
			
			$q = DB::update('vk_grabber_sources')
				->set([
					'url'		=> $url
				])
				->where('id', '=', $s['id']);
			
			echo "$q\n";
			
			$q->execute();
		}
		*/
		
		/*
		$data2id = DB::select('id', 'data_id')
			->from('vk_grabber_data_index')
			->where('source_type', '=', 2)
			->execute()
			->asArray('data_id', 'id');
		
		foreach (array_chunk(array_keys($data2id), 1000) as $chunk) {
			$datas = DB::select()
				->from('vk_grabber_data')
				->where('id', 'IN', $chunk)
				->execute()
				->asArray();
			
			foreach ($datas as $data) {
				$attaches = unserialize(gzinflate($data['attaches']));
				$gifs_cnt = 0;
				$images_cnt = 0;
				
				foreach ($attaches as $att) {
					if ($att['type'] == 'photo') {
						++$images_cnt;
					} elseif ($att['type'] == 'doc') {
						++$gifs_cnt;
					}
				}
				
				$post_type = 0;
				if ($images_cnt > 0 && $gifs_cnt > 0)
					$post_type = 3;
				elseif ($images_cnt > 0)
					$post_type = 2;
				elseif ($gifs_cnt > 0)
					$post_type = 1;
				
				echo "data_id=".$data['id'].", gifs_cnt=$gifs_cnt, $images_cnt\n";
				
				DB::update('vk_grabber_data_index')
					->set([
						'gifs_cnt'		=> $gifs_cnt, 
						'images_cnt'	=> $images_cnt, 
						'post_type'		=> $post_type
					])
					->where('id', '=', $data2id[$data['id']])
					->execute();
			}
		}
		*/
		
		/*
		$list = DB::select('data_id')
			->from('vk_grabber_data_index')
			->where('source_type', '=', 2)
			->execute()
			->asArray(NULL, 'data_id');
		
		$sources = DB::select('id', 'source_id')
			->from('vk_grabber_sources')
			->where('source_type', '=', 2)
			->execute()
			->asArray('id', 'source_id');
		
		foreach (array_chunk($list, 1000) as $chunk) {
			$owners = DB::select('id', 'owner')
				->from('vk_grabber_data')
				->where('id', 'IN', $chunk)
				->execute()
				->asArray('id', 'owner');
			
			echo "patch... ".count($chunk)."\n";
			foreach ($owners as $data_id => $owner) {
				if ($owner[0] != "@" && $owner[0] != "#") {
					DB::update('vk_grabber_data')
						->set(['owner' => $sources[$owner]])
						->where('id', '=', $data_id)
						->execute();
				}
			}
		}
		*/
		
		/*
		$api = new \Smm\VK\API(\Smm\Oauth::getAccessToken('VK_SCHED'));
		var_dump($api->exec("wall.getById", [
			'posts'		=> '-186341291_37641', 
			'extended'	=> 1, 
			'v' => 5.125
		]));
		*/
	}
}
