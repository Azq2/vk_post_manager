<?php
namespace Smm\Controllers;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;

use \Smm\View\Widgets;

use PhpAmqpLib\Message\AMQPMessage;

class ToolsController extends \Smm\GroupController {
	public function indexAction() {
		$this->title = 'SMM Tools';
		
		$base_url = Url::mk('/')->set('gid', $this->group['id']);
		
		$this->content = View::factory('tools/index', [
			'multipicpost_url'		=> $base_url->copy()->set('a', 'index/multipicpost')->href(), 
			'duplicate_finder_url'	=> $base_url->copy()->set('a', 'tools/duplicate_finder')->href(), 
		]);
	}
	
	public function compare_groupsAction() {
		$now = time();
		
		$api = new \Smm\VK\API(\Smm\Oauth::getAccessToken('VK'));
		$response = $api->exec("stats.get", [
			'group_id'			=> 186341291, 
			'timestamp_from'	=> strtotime(date("Y-m-d", $now - 3600 * 24 * 30)." 00:00:00"), 
			'timestamp_to'		=> strtotime(date("Y-m-d", $now)." 00:00:00"), 
			'interval'			=> 'all', 
			'intervals_count'	=> 0, 
		]);
		$a = $response->response[0];
		
		$response = $api->exec("stats.get", [
			'group_id'			=> 194035741, 
			'timestamp_from'	=> strtotime(date("Y-m-d", $now - 3600 * 24 * 30)." 00:00:00"), 
			'timestamp_to'		=> strtotime(date("Y-m-d", $now)." 00:00:00"), 
			'interval'			=> 'all', 
			'intervals_count'	=> 0, 
		]);
		$b = $response->response[0];
		
		$diff = \Smm\VK\Statistic::computeStatisticDiff($a, $b);
		
		$compares = [
			'activity'		=> [
				'title'		=> 'Активность', 
				'tables'	=> $this->_getTables($diff['activity'])
			], 
			'reach'			=> [
				'title'		=> 'Охват', 
				'tables'	=> $this->_getTables($diff['reach'])
			], 
			'visitors'		=> [
				'title'		=> 'Посещения', 
				'tables'	=> $this->_getTables($diff['visitors'])
			], 
		];
		
		$this->title = 'Сравниватель сообществ';
		$this->content = View::factory('tools/compare_groups', [
			'compares'		=> $compares
		]);
	}
	
	public function _getTables($diff) {
		$tables = [
			'summary'	=> [
				'title'		=> 'Основное', 
				'rows'		=> []
			]
		];
		
		foreach ($diff as $k => $v) {
			switch ($k) {
				case "socdem":
					$names = [
						'age'			=> 'Возраст', 
						'sex'			=> 'Пол', 
						'sex_age'		=> 'Возраст+Пол', 
					];
					
					foreach ($names as $k => $title) {
						$tables[$k] = [
							'title'		=> $title, 
							'rows'		=> []
						];
						foreach ($v[$k] as $item_name => $item_value)
							$tables[$k]['rows'][] = $this->_getValueRow($item_name, $item_value, false);
					}
				break;
				
				case "countries":
				case "cities":
					$names = [
						'countries'		=> 'Страны', 
						'cities'		=> 'Города'
					];
					
					$tables[$k] = [
						'title'		=> $names[$k], 
						'rows'		=> []
					];
					foreach ($v as $geo_name => $geo_value)
						$tables[$k]['rows'][] = $this->_getValueRow($geo_name, $geo_value, false);
				break;
				
				default:
					$negative = [
						'unsubscribed', 
						'hidden'
					];
					
					$names = [
						'comments'				=> 'Комментарии', 
						'copies'				=> 'Репосты', 
						'hidden'				=> 'Скрытия из новостей', 
						'likes'					=> 'Лайки', 
						'subscribed'			=> 'Подписались', 
						'unsubscribed'			=> 'Отписались', 
						'mobile_reach'			=> 'Охват (мобилы)', 
						'reach_subscribers'		=> 'Охват (подписчики)', 
						'reach'					=> 'Охват (полный)', 
						'self_growth'			=> 'Саморост'
					];
					
					$tables['summary']['rows'][] = $this->_getValueRow($names[$k] ?? $k, $v, in_array($k, $negative));
				break;
			}
		}
		
		return $tables;
	}
	
	public function _getValueRow($title, $v, $is_negative) {
		if (isset($v[2])) {
			return [
				'title'			=> $title, 
				'value'			=> [$v[0], $v[1]], 
				'pct'			=> [$v[2], $v[3]], 
				'diff'			=> $v[2] - $v[3], 
				'type'			=> 'pct', 
				'negative'		=> $is_negative
			];
		} else {
			return [
				'title'		=> $title, 
				'value'		=> [$v[0], $v[1]], 
				'diff'		=> $v[0] > $v[1] ? (100 - $v[1] / $v[0] * 100) : -(100 - $v[0] / $v[1] * 100), 
				'pct'		=> false, 
				'type'		=> 'pct', 
				'negative'		=> $is_negative
			];
		}
	}
	
	public function duplicate_finderAction() {
		$this->title = 'SMM Tools : Поиск баянов';
		
		$this->content = View::factory('tools/duplicate_finder', [
			
		]);
	}
	
	public function duplicate_finder_queueAction() {
		$this->mode('json');
		$this->content['success'] = false;
		$this->content['error'] = false;
		
		if (!$this->user->can('user')) {
			$this->content['error'] = 'Гостевой доступ.';
			return;
		}
		
		$id = preg_replace("/[^a-f0-9]/", "", $_REQUEST['id'] ?? "");
		$photo = $_REQUEST['photo'] ?? '';
		
		if (!$id) {
			if (!preg_match("/^photo([\d-]+)_(\d+)$/", $photo)) {
				$this->content['error'] = 'Invalid photo!';
			} else {
				$msg = (object) [
					'photo'		=> $photo, 
					'ctime'		=> time()
				];
				
				$id = md5(json_encode($msg));
				
				\Z\Cache::instance()->set("duplicate_finder_queue:$id", $msg, 3600);
				
				$amqp = \Z\Net\AMQP::instance();
				$amqp->queue_declare('duplicate_finder_queue', false, true);
				$amqp->basic_publish(new AMQPMessage($id), '', 'duplicate_finder_queue');
			}
		}
		
		if ($id) {
			$status = \Z\Cache::instance()->get("duplicate_finder_queue:$id");
			
			$this->content['id'] = $id;
			
			if ($status) {
				if (isset($status->error)) {
					$this->content['error'] = $status->error;
				} else {
					$this->content['success'] = true;
					$this->content['queue'] = $status;
				}
			} else {
				$this->content['error'] = 'Очередь проверки уже удалена. ('.$id .')';
			}
		}
	}
	
	public function accessControl() {
		return [
			'*' => [
				'auth_required'		=> true, 
				'users'				=> ''
			]
		];
	}
}
