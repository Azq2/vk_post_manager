<?php
namespace Smm\Controllers;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Config;
use \Z\Util\Url;

use \Smm\View\Widgets;

class GrabberController extends \Smm\GroupController {
	public function blacklistAction() {
		$post_id = intval($_POST['id'] ?? 0);
		$restore = intval($_POST['restore'] ?? 0);
		
		$this->mode('json');
		$this->content['success'] = false;
		
		$post = DB::select()
			->from('vk_grabber_data_index')
			->where('id', '=', $post_id)
			->execute()
			->current();
		
		if (!$this->user->can('user')) {
			$this->content['error'] = 'Гостевой доступ!';
		} else if ($post) {
			if ($restore) {
				DB::delete('vk_grabber_blacklist')
					->where('group_id', '=', $this->group['id'])
					->where('source_type', '=', $post['type'])
					->where('remote_id', '=', $post['remote_id'])
					->execute();
			} else {
				DB::insert('vk_grabber_blacklist')
					->ignore()
					->set([
						'group_id'		=> $this->group['id'], 
						'source_type'	=> $post['type'], 
						'remote_id'		=> $post['remote_id'], 
						'time'			=> time()
					])
					->execute();
			}
			
			$this->content['success'] = true;
		} else {
			$this->content['error'] = 'Пост не найден :(';
		}
	}
	
	public function delete_sourceAction() {
		$id = $_GET['id'] ?? 0;
		
		$base_url = Url::mk('/')->set('gid', $this->group['id']);
		
		if ($this->user->can('user')) {
			DB::delete('vk_grabber_selected_sources')
				->where('source_id', '=', $id)
				->where('group_id', '=', $this->group['id'])
				->execute();
		}
		
		return $this->redirect($base_url->set('a', 'grabber/sources')->url());
	}
	
	public function enable_sourceAction() {
		$enable = $_GET['on'] ?? false;
		$id = $_GET['id'] ?? 0;
		
		$base_url = Url::mk('/')->set('gid', $this->group['id']);
		
		if ($this->user->can('user')) {
			DB::update('vk_grabber_selected_sources')
				->set([
					'enabled' => $enable ? 1 : 0
				])
				->where('source_id', '=', $id)
				->where('group_id', '=', $this->group['id'])
				->execute();
		}
		
		return $this->redirect($base_url->set('a', 'grabber/sources')->url());
	}
	
	public function loadAction() {
		$this->mode('json');
		
		$O = $_REQUEST['O'] ?? 0;
		$L = $_REQUEST['L'] ?? 10;
		$sort = $_REQUEST['sort'] ?? 'DESC';
		$mode = $_REQUEST['mode'] ?? 'external';
		$content_filter = $_REQUEST['content'] ?? 'pics';
		$include = isset($_REQUEST['include']) && is_array($_REQUEST['include']) ? $_REQUEST['include'] : [];
		$exclude = isset($_REQUEST['exclude']) && is_array($_REQUEST['exclude']) ? $_REQUEST['exclude'] : [];
		$interval = $_REQUEST['interval'] ?? 'all';
		$list_type = $_REQUEST['list_type'] ?? 'all';
		$source_type = $_REQUEST['source_type'] ?? 'all';
		$date_from = $_REQUEST['date_from'] ?? '';
		$date_to = $_REQUEST['date_to'] ?? '';
		
		if ($include) {
			$exclude = [];
		} else if ($exclude) {
			$include = [];
		}
		
		$sources = $this->_getSources();
		
		$sources_ids = [];
		foreach ($sources as $s) {
			if ($source_type !== 'all' && $source_type != $s['type'])
				continue;
			
			if ($s['enabled']) {
				$key = \Smm\Grabber::$type2name[$s['type']].'_'.$s['value'];
				if ($include && !in_array($key, $include))
					continue;
				if ($exclude && in_array($key, $exclude))
					continue;
				$sources_ids[] = $s['id'];
			}
		}
		
		$grabber_query = DB::select('id')
			->from('vk_grabber_data_index');
		
		if ($sort == "RAND") {
			$grabber_query->limit($L);
		} else {
			$grabber_query
				->calcFoundRows()
				->offset($O)
				->limit($L);
		}
		
		// Фильтр по типу контента
		if ($content_filter == 'pics')
			$grabber_query->where('post_type', 'IN', [\Smm\Grabber::POST_WITH_TEXT_PIC, \Smm\Grabber::POST_WITH_TEXT_PIC_GIF]);
		elseif ($content_filter == 'only_gif')
			$grabber_query->where('post_type', 'IN', [\Smm\Grabber::POST_WITH_TEXT_GIF, \Smm\Grabber::POST_WITH_TEXT_PIC_GIF]);
		elseif ($content_filter == 'without_gif')
			$grabber_query->where('post_type', 'IN', [\Smm\Grabber::POST_WITH_TEXT_PIC]);
		
		$time_sort_field = 'time';
		
		if ($list_type == 'top') {
			$grabber_query->where('list_type', 'IN', [
				\Smm\Grabber::LIST_TOP
			]);
			$time_sort_field = 'first_grab_time';
		} elseif ($list_type == 'new') {
			$grabber_query->where('list_type', 'IN', [
				\Smm\Grabber::LIST_UNKNOWN, \Smm\Grabber::LIST_NEW
			]);
		}
		
		if ($sort == 'DESC') {
			$grabber_query->order($time_sort_field, 'DESC');
		} else if ($sort == 'ASC') {
			$grabber_query->order($time_sort_field, 'ASC');
		} else if ($sort == 'RAND') {
			if (isset($_REQUEST['exclude_posts'])) {
				$exclude = [];
				foreach (explode(",", $_REQUEST['exclude_posts']) as $t) {
					if ($t > 0)
						$exclude[] = (int) $t;
				}
				
				if ($exclude)
					$grabber_query->where('id', 'NOT IN', $exclude);
			}
			$grabber_query->order(DB::expr('RAND()'));
		} else if ($sort == 'LIKES') {
			$grabber_query->order('likes', 'DESC');
		} else if ($sort == 'REPOSTS') {
			$grabber_query->order('reposts', 'DESC');
		} else if ($sort == 'COMMENTS') {
			$grabber_query->order('comments', 'DESC');
		}
		
		switch ($interval) {
			case "today":
				$grabber_query->where('time', '>=', time() - 3600 * 24);
			break;
			
			case "week":
				$grabber_query->where('time', '>=', time() - 3600 * 24 * 7);
			break;
			
			case "month":
				$grabber_query->where('time', '>=', time() - 3600 * 24 * 30);
			break;
			
			case "year":
				$grabber_query->where('time', '>=', time() - 3600 * 24 * 365);
			break;
			
			case "3month_old":
				$grabber_query->where('time', '<', time() - 3600 * 24 * 31 * 3);
			break;
			
			case "1month_old":
				$grabber_query->where('time', '<', time() - 3600 * 24 * 31 * 1);
			break;
			
			case "custom":
				if ($date_from)
					$grabber_query->where('time', '>=', strtotime($date_from." 00:00:00"));
				
				if ($date_to)
					$grabber_query->where('time', '<=', strtotime($date_to." 23:59:59"));
			break;
		}
		
		if ($mode == 'internal') {
			$source_id = DB::select('id')
				->from('vk_grabber_sources')
				->where('type', '=', \Smm\Grabber::SOURCE_VK)
				->where('id', '=', -$this->group['id'])
				->execute()
				->get('id', 0);
			
			// Из своего сообщества
			$source_id ?
				$grabber_query->where('source_id', '=', $source_id) :
				$grabber_query->where(DB::expr('1'), '<>', 1);
		} else {
			// Из чужих сообществ
			$sources_ids ?
				$grabber_query->where('source_id', 'IN', $sources_ids) :
				$grabber_query->where(DB::expr('1'), '<>', 1);
		}
		
		$time_list = microtime(true);
		
		$posts_ids = $grabber_query
			->execute()
			->asArray(NULL, 'id');
		
		$time_list = microtime(true) - $time_list;
		
		$time_count = microtime(true);
		
		$count = 0;
		if ($sort != "RAND") {
			$count = DB::select(['FOUND_ROWS()', 'found_rows'])
				->execute()
				->get('found_rows', 0);
		}
		
		$time_count = microtime(true) - $time_count;
		
		// Получаем массив id данных и 
		$meta = [];
		$items = [];
		
		if ($posts_ids) {
			$meta = DB::select()
				->from('vk_grabber_data_index')
				->where('id', 'IN', $posts_ids)
				->execute()
				->asArray('data_id');
		}
		
		$blacklist_ids = [];
		$time_data = microtime(true);
		
		if ($meta) {
			$post_data_query = DB::select()
				->from('vk_grabber_data')
				->where('id', 'IN', array_keys($meta));
			foreach ($post_data_query->execute() as $post_data) {
				$post = $meta[$post_data['id']];
				$source_type = \Smm\Grabber::$type2name[$post['source_type']];
				$source = $sources[$post['source_id']];
				
				if (!isset($blacklist_ids[$post['source_type']]))
					$blacklist_ids[$post['source_type']] = [];
				$blacklist_ids[$post['source_type']][] = $post['remote_id'];
				
				$attaches = array_map(function ($att) {
					if (isset($att['thumbs'])) {
						foreach ($att['thumbs'] as $size => $thumb)
							$att['thumbs'][$size] = $this->proxyThumb($att['thumbs'][$size]);
					}
					
					if (isset($att['mp4']))
						$att['mp4'] = $this->proxyThumb($att['mp4']);
					
					return $att;
				}, unserialize(gzinflate($post_data['attaches'])));
				
				$items[$post['id']] = [
					'id'				=> $post['id'], 
					'remote_id'			=> $post['remote_id'], 
					'source_id'			=> $post['source_id'], 
					'source_type'		=> $source_type, 
					'source_type_id'	=> $post['source_type'], 
					'time'				=> $post['time'], 
					'likes'				=> $post['likes'], 
					'comments'			=> $post['comments'], 
					'reposts'			=> $post['reposts'], 
					'gifs_cnt'			=> $post['gifs_cnt'], 
					'images_cnt'		=> $post['images_cnt'], 
					'text'				=> $post_data['text'], 
					'spell'				=> \Smm\Utils\Spellcheck::check($post_data['text']), 
					'owner_name'		=> $source['name'], 
					'owner_url'			=> $source['url'], 
					'owner_avatar'		=> $source['avatar'], 
					'attaches'			=> $attaches
				];
			}
		}
		
		$time_data = microtime(true) - $time_data;
		
		$time_blacklist = microtime(true);
		
		$blacklist_query = DB::select()
				->from('vk_grabber_blacklist')
				->where('group_id', '=', $this->group['id']);
		
		$blacklist_query->openGroup();
		foreach ($blacklist_ids as $source_type => $ids) {
			$blacklist_query
				->orOpenGroup()
					->where('source_type', '=', $source_type)
					->where('remote_id', 'IN', $ids)
				->orCloseGroup();
		}
		$blacklist_query->closeGroup();
		
		$blacklist_filtered = [];
		if ($blacklist_ids) {
			foreach ($blacklist_query->execute() as $row)
				$blacklist_filtered[$row['source_type'].":".$row['remote_id']] = 1;
		}
		
		$items_array = [];
		foreach ($posts_ids as $post_id) {
			$item = $items[$post_id];
			if (!isset($blacklist_filtered[$item['source_type_id'].":".$item['remote_id']]))
				$items_array[] = $item;
		}
		
		$time_blacklist = microtime(true) - $time_blacklist;
		
		$this->content = [
			'success'				=> true, 
			'sql'					=> "$grabber_query", 
			'items'					=> $items_array, 
			'total'					=> (int) $count, 
			'time_data'				=> $time_data, 
			'time_list'				=> $time_list, 
			'time_count'			=> $time_count, 
			'time_blacklist'		=> $time_blacklist, 
			'blacklist_filtered'	=> count($blacklist_filtered)
		];
	}
	
	public function sourcesAction() {
		$raw_source_url = trim($_POST['url'] ?? '');
		$source_type_name = $_POST['type'] ?? 'VK';
		$error = false;
		$sources = $this->_getSources();
		
		$base_url = Url::mk('/')->set('gid', $this->group['id']);
		
		$sources_types = [
			'VK'			=> [
				'title'			=> 'Паблик VK', 
				'descr'			=> 'Ссылка вида: https://vk.com/catlist'
			], 
			'INSTAGRAM'		=> [
				'title'			=> 'Instagram', 
				'descr'			=> 
					'Для хэштега: #cat<br />'.
					'Для профиля: @ledravenger'
			], 
			'PINTEREST'		=> [
				'title'			=> 'Pinterest', 
				'descr'			=> 'Ссылка вида: https://pinterest.ru/pin/605874956108657616/'
			], 
			'TUMBLR'		=> [
				'title'			=> 'Tumblr', 
				'descr'			=>
					'Для хэштега: #tag<br />'.
					'Для юзера: https://USER.tumblr.com'
			]
		];
		
		if ($_POST) {
			$new_source = false;;
			
			if (strpos($raw_source_url, "//vk.com") !== false)
				$source_type_name = 'VK';
			
			if (strpos($raw_source_url, "//instagram.com") !== false || strpos($raw_source_url, "//www.instagram.com") !== false)
				$source_type_name = 'INSTAGRAM';
			
			if (strpos($raw_source_url, "//pinterest.") !== false || strpos($raw_source_url, "//www.pinterest.") !== false)
				$source_type_name = 'PINTEREST';
			
			if (!$this->user->can('user')) {
				$error = 'Гостевой доступ!';
			} elseif ($raw_source_url == '') {
				$error = 'Сейчас бы тыкать на кнопки ничего не введя.';
			} else {
				switch ($source_type_name) {
					case "VK":
						$parts = parse_url($raw_source_url);
						$api = new \Smm\VK\API(\Smm\Oauth::getAccessToken('VK'));
						
						$group_id = substr($parts['path'], 1);
						if (preg_match("/^(public|club)(\d+)$/i", $group_id, $m))
							$group_id = $m[2];
						
						$res = $api->exec("groups.getById", array(
							'group_ids'	=> $group_id
						));
						if ($res->success()) {
							$new_source = [
								'value'		=> -$res->response[0]->id, 
								'type'		=> \Smm\Grabber::SOURCE_VK, 
								'name'		=> htmlspecialchars($res->response[0]->name), 
								'url'		=> 'https://vk.com/public'.$res->response[0]->id, 
								'avatar'	=> '/images/grabber/avatar/VK.png'
							];
						} else {
							$error = $res->error();
						}
					break;
					
					case "INSTAGRAM":
						switch ($raw_source_url[0]) {
							// Хэштег
							case "#":
								if (!preg_match('/^#[\w\d_.-]+$/i', $raw_source_url)) {
									$error = 'Неправильный тег.';
								} else {
									$new_source = [
										'value'			=> htmlspecialchars($raw_source_url), 
										'type'			=> \Smm\Grabber::SOURCE_INSTAGRAM, 
										'name'			=> $raw_source_url, 
										'url'			=> 'https://www.instagram.com/explore/tags/'.urlencode(substr($raw_source_url, 1)), 
										'avatar'		=> '/images/grabber/avatar/INSTAGRAM.png', 
										'internal_id'	=> ''
									];
								}
							break;
							
							// Профиль
							case "@":
								if (!preg_match('/^\@[\w\d_.-]+$/i', $raw_source_url)) {
									$error = 'Неправильный тег.';
								} else {
									$new_source = [
										'value'			=> htmlspecialchars($raw_source_url), 
										'type'			=> \Smm\Grabber::SOURCE_INSTAGRAM, 
										'name'			=> $raw_source_url, 
										'url'			=> 'https://www.instagram.com/'.urlencode(substr($raw_source_url, 1)), 
										'avatar'		=> '/images/grabber/avatar/INSTAGRAM.png', 
										'internal_id'	=> ''
									];
								}
							break;
						}
					break;
					
					case "PINTEREST":
						if (preg_match("#/pin/(\d+)#i", $raw_source_url, $m)) {
							$value = $m[1];
							
							$new_source = [
								'value'		=> $value, 
								'type'		=> \Smm\Grabber::SOURCE_PINTEREST, 
								'name'		=> htmlspecialchars($value), 
								'url'		=> 'http://pinterest.ru/pin/'.urlencode($value).'/', 
								'avatar'	=> '/images/grabber/avatar/PINTEREST.png'
							];
						} else {
							$error = 'Неправильная ссылка на pin.';
						}
					break;
					
					case "TUMBLR":
						if (preg_match('/^#[\w\d_.- ]+$/i', $raw_source_url)) {
							$new_source = [
								'value'			=> htmlspecialchars(substr($raw_source_url, 1)), 
								'type'			=> \Smm\Grabber::SOURCE_TUMBLR, 
								'name'			=> $raw_source_url, 
								'url'			=> 'https://www.tumblr.com/tagged/'.urlencode(substr($raw_source_url, 1)), 
								'avatar'		=> '/images/grabber/avatar/TUMBLR.png', 
								'internal_id'	=> ''
							];
						} elseif (preg_match('#http(?:s)?://([\w\d_-]+)\.tumblr\.com/#i', $raw_source_url, $m)) {
							$new_source = [
								'value'			=> '@'.$m[1], 
								'type'			=> \Smm\Grabber::SOURCE_TUMBLR, 
								'name'			=> '@'.$m[1], 
								'url'			=> 'https://'.$m[1].'.tumblr.com/', 
								'avatar'		=> '/images/grabber/avatar/TUMBLR.png', 
								'internal_id'	=> ''
							];
						} else {
							$error = 'Неправильный тег.';
						}
					break;
					
					default:
						$error = 'WTF?';
					break;
				}
			}
			
			if ($new_source) {
				DB::begin();
				
				DB::insert('vk_grabber_sources')
					->ignore()
					->set($new_source)
					->onDuplicateSetValues('name')
					->onDuplicateSetValues('avatar')
					->onDuplicateSetValues('url')
					->onDuplicateSetValues('internal_id')
					->execute();
				
				$new_source_id = DB::select('id')
					->from('vk_grabber_sources')
					->where('type', '=', $new_source['type'])
					->where('value', '=', $new_source['value'])
					->execute()
					->get('id', 0);
				
				DB::insert('vk_grabber_selected_sources')
					->set([
						'source_id'	=> $new_source_id, 
						'group_id'	=> $this->group['id'], 
						'enabled'	=> 1, 
					])
					->onDuplicateSetValues('enabled')
					->execute();
				
				DB::commit();
				
				return $this->redirect($base_url->set('a', 'grabber/sources')->url());
			}
		}
		
		$sources_list = [];
		foreach ($sources as $s) {
			$key = \Smm\Grabber::$type2name[$s['type']]."_".$s['value'];
			
			$sources_list[] = [
				'key'			=> $key,
				'id'			=> $s['value'],
				'enabled'		=> $s['enabled'], 
				'name'			=> $s['name'], 
				'url'			=> $s['url'], 
				'type'			=> \Smm\Grabber::$type2name[$s['type']], 
				'on_url'		=> $base_url->copy()
					->set('a', 'grabber/enable_source')
					->set('id', $s['id'])
					->set('on', 1)
					->href(), 
				'off_url'		=> $base_url->copy()
					->set('a', 'grabber/enable_source')
					->set('id', $s['id'])
					->set('on', 0)
					->href(), 
				'delete_url'	=> $base_url->copy()
					->set('a', 'grabber/delete_source')
					->set('id', $s['id'])
					->set('on', 0)
					->href(), 
			];
		}
		
		$this->title = 'Граббер корованов 2000 / Мои корованы';
		$this->content = View::factory('grabber/add', [
			'form_action'		=> Url::current()->href(), 
			'form_error'		=> $error, 
			'form_url'			=> $raw_source_url, 
			'sources'			=> $sources_list, 
			'sources_types'		=> $sources_types, 
			'source_type'		=> $source_type_name
		]);
	}
	
	public function indexAction() {
		$sort = $_REQUEST['sort'] ?? 'DESC';
		$mode = $_REQUEST['mode'] ?? 'external';
		$content_filter = $_REQUEST['content'] ?? 'pics';
		$include = isset($_REQUEST['include']) && is_array($_REQUEST['include']) ? $_REQUEST['include'] : [];
		$exclude = isset($_REQUEST['exclude']) && is_array($_REQUEST['exclude']) ? $_REQUEST['exclude'] : [];
		$interval = $_REQUEST['interval'] ?? 'all';
		$list_type = $_REQUEST['list_type'] ?? 'all';
		$source_type = $_REQUEST['source_type'] ?? 'all';
		
		if ($include) {
			$exclude = [];
		} else if ($exclude) {
			$include = [];
		}
		
		$date_from = $_REQUEST['date_from'] ?? date("Y-m-d", time() - 3600 * 24 * 31 * 3);
		$date_to = $_REQUEST['date_to'] ?? '';
		
		$sources = $this->_getSources();
		
		$base_url = Url::mk('/')->set('gid', $this->group['id']);
		
		$mode_tabs = new Widgets\Tabs([
			'url'		=> Url::current(), 
			'param'		=> 'mode', 
			'items'		=> [
				'external'		=> 'Граббер', 
				'internal'		=> 'Из своего сообщества', 
			], 
			'active'	=> $mode
		]);
		
		$content_tabs = new Widgets\Tabs([
			'url'		=> Url::current(), 
			'param'		=> 'content', 
			'items'		=> [
				'all'			=> 'Любые', 
				'pics'			=> 'Картинки', 
				'only_gif'		=> 'Только GIF', 
				'without_gif'	=> 'Без GIF', 
			], 
			'active'	=> $content_filter
		]);
		
		$sort_tabs = new Widgets\Tabs([
			'url'		=> Url::current(), 
			'param'		=> 'sort', 
			'items'		=> [
				'DESC'		=> 'Начало', 
				'ASC'		=> 'Конец', 
				'RAND'		=> 'Рандо&#x301;мно', 
				'LIKES'		=> 'Топ Лайки', 
				'REPOSTS'	=> 'Топ Репосты', 
				'COMMENTS'	=> 'Топ Комы', 
			], 
			'active'	=> $sort
		]);
		
		$date_tabs = new Widgets\Tabs([
			'url'		=> Url::current(), 
			'param'		=> 'interval', 
			'items'		=> [
				'all'			=> 'Всё время', 
				'today'			=> 'Сегодня', 
				'week'			=> 'Неделя', 
				'month'			=> 'Месяц', 
				'1month_old'	=> '&gt;1 мес.', 
				'3month_old'	=> '&gt;3 мес.', 
				'custom'		=> 'Указать', 
			], 
			'active'	=> $interval
		]);
		
		$list_type_tabs = new Widgets\Tabs([
			'url'		=> Url::current(), 
			'param'		=> 'list_type', 
			'items'		=> [
				'all'		=> 'Все', 
				'today'		=> 'Новые', 
				'top'		=> 'Популярные', 
			], 
			'active'	=> $list_type
		]);
		
		$source_type_tabs = new Widgets\Tabs([
			'url'		=> Url::current(),
			'param'		=> 'source_type',
			'items'		=> [
				'all'							=> 'Все',
				\Smm\Grabber::SOURCE_VK			=> 'VK',
				\Smm\Grabber::SOURCE_INSTAGRAM	=> 'Instagram',
				\Smm\Grabber::SOURCE_PINTEREST	=> 'Pinterest',
				\Smm\Grabber::SOURCE_TUMBLR		=> 'Tumblr'
			],
			'active'	=> $source_type
		]);
		
		$sources_ids = [];
		$sources_list = [];
		$source_filter_list = [];
		$source_filter_type = 'none';
		
		if ($include) {
			$source_filter_type = 'include';
		} elseif ($exclude) {
			$source_filter_type = 'exclude';
		}
		
		foreach ($sources as $s) {
			$key = \Smm\Grabber::$type2name[$s['type']]."_".$s['value'];
			
			$source_view = [
				'key'			=> $key,
				'id'			=> $s['value'],
				'enabled'		=> $s['enabled'], 
				'name'			=> $s['name'], 
				'url'			=> $s['url'], 
				'type'			=> \Smm\Grabber::$type2name[$s['type']], 
			];
			
			if (in_array($key, $include))
				$source_filter_list[] = $key;
			
			if (in_array($key, $exclude))
				$source_filter_list[] = $key;
			
			if ($s['enabled'])
				$sources_ids[] = $s['id'];
			
			$sources_list[] = $source_view;
		}
		
		$this->title = 'Граббер корованов 2000';
		$this->content = View::factory('grabber/index', [
			'add_url'			=> $base_url->copy()->set('a', 'grabber/sources')->href(), 
			
			'sources_ids'			=> $sources_ids, 
			'sources'				=> $sources_list, 
			'source_filter_list'	=> $source_filter_list, 
			'source_filter_type'	=> $source_filter_type, 
			
			'sort'				=> $sort, 
			'mode'				=> $mode, 
			'content_filter'	=> $content_filter, 
			'include'			=> $include, 
			'exclude'			=> $exclude, 
			'interval'			=> $interval, 
			'list_type'			=> $list_type, 
			'source_type'		=> $source_type, 
			'date_from'			=> $date_from, 
			'date_to'			=> $date_to, 
			
			'gid'				=> $this->group['id'], 
			'mode_tabs'			=> $mode_tabs->render(), 
			'content_tabs'		=> $content_tabs->render(), 
			'sort_tabs'			=> $sort_tabs->render(), 
			'date_tabs'			=> $date_tabs->render(), 
			'list_type_tabs'	=> $list_type_tabs->render(),
			'source_type_tabs'	=> $source_type_tabs->render()
		]);
	}
	
	private function proxyThumb($url) {
		$key = Config::get("common", "image_proxy_key");
		return '/img-proxy/?'.http_build_query([
			'url'		=> $url,
			'hash'		=> hash("sha256", "img-proxy:$key:$url")
		], '', '&');
	}
	
	private function _getSources() {
		return DB::select('s.*', 'ss.enabled')
			->from(['vk_grabber_sources', 's'])
			->join(['vk_grabber_selected_sources', 'ss'], 'INNER')
				->on('s.id', '=', 'ss.source_id')
			->where('ss.group_id', '=', $this->group['id'])
			->order('s.id', 'DESC')
			->execute()
			->asArray('id');
	}
}
