<?php
namespace Smm\Controllers;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;
use \Z\Net\VkApi;

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
					->where('source_type', '=', $post['source_type'])
					->where('remote_id', '=', $post['remote_id'])
					->execute();
			} else {
				DB::insert('vk_grabber_blacklist')
					->ignore()
					->set([
						'group_id'		=> $this->group['id'], 
						'source_type'	=> $post['source_type'], 
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
		$source_id = $_GET['id'] ?? 0;
		
		$base_url = Url::mk('/')->set('gid', $this->group['id']);
		
		if ($this->user->can('user')) {
			DB::delete('vk_grabber_selected_sources')
				->where('source_id', '=', $source_id)
				->where('group_id', '=', $this->group['id'])
				->execute();
		}
		
		return $this->redirect($base_url->set('a', 'grabber/sources')->url());
	}
	
	public function enable_sourceAction() {
		$enable = $_GET['on'] ?? false;
		$source_id = $_GET['id'] ?? 0;
		
		$base_url = Url::mk('/')->set('gid', $this->group['id']);
		
		if ($this->user->can('user')) {
			DB::update('vk_grabber_selected_sources')
				->set([
					'enabled' => $enable ? 1 : 0
				])
				->where('source_id', '=', $source_id)
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
		
		$sources = $this->_getSources();
		
		$sources_ids = [];
		foreach ($sources as $s) {
			if ($s['enabled']) {
				$key = \Smm\Grabber::$type2name[$s['source_type']].'_'.$s['source_our_id'];
				if ($include && !in_array($key, $include))
					continue;
				if ($exclude && in_array($key, $exclude))
					continue;
				$sources_ids[] = $s['source_id'];
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
			$grabber_query->where('post_type', '>', 0);
		elseif ($content_filter == 'only_gif')
			$grabber_query->where('post_type', 'IN', [1, 3]);
		elseif ($content_filter == 'without_gif')
			$grabber_query->where('post_type', '=', 2);
		
		if ($sort == 'DESC') {
			$grabber_query->order('time', 'DESC');
		} else if ($sort == 'ASC') {
			$grabber_query->order('time', 'ASC');
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
		
		if ($mode == 'internal') {
			$source_id = DB::select('id')
				->from('vk_grabber_sources')
				->where('source_type', '=', \Smm\Grabber::SOURCE_VK)
				->where('source_id', '=', -$this->group['id'])
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
		
		$owners = [];
		$blacklist_ids = [];
		$time_data = microtime(true);
		
		if ($meta) {
			// Получаем овнеров
			$owners = DB::select()
				->from('vk_grabber_data_owners')
				->execute()
				->asArray('id');
			
			$post_data_query = DB::select()
				->from('vk_grabber_data')
				->where('id', 'IN', array_keys($meta));
			foreach ($post_data_query->execute() as $post_data) {
				$post = $meta[$post_data['id']];
				$source_type = \Smm\Grabber::$type2name[$post['source_type']];
				$owner = $owners[$post['source_type'].'_'.$post_data['owner']];
				
				if (!isset($blacklist_ids[$post['source_type']]))
					$blacklist_ids[$post['source_type']] = [];
				$blacklist_ids[$post['source_type']][] = $post['remote_id'];
				
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
					'owner_name'		=> $owner['name'], 
					'owner_url'			=> $owner['url'], 
					'owner_avatar'		=> $owner['avatar'], 
					'attaches'			=> unserialize(gzinflate($post_data['attaches']))
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
		$source_url = trim($_POST['url'] ?? '');
		$error = false;
		$sources = $this->_getSources();
		
		$base_url = Url::mk('/')->set('gid', $this->group['id']);
		
		if ($_POST) {
			// Инстаграмм
			if ($source_url && $source_url[0] == '#')
				$source_url = "https://www.instagram.com/explore/tags/".urlencode(substr($source_url, 1))."/";
			
			if (substr($source_url, 0, 4) != "http")
				$source_url = "https://$source_url";
			
			$parts = parse_url($source_url);
			
			$source_id = false;
			$source_type = false;
			
			if (!$this->user->can('user')) {
				$error = 'Гостевой доступ!';
			} elseif ($source_url == '') {
				$error = 'Сейчас бы тыкать на кнопки ничего не введя.';
			} elseif (isset($parts['host']) && isset($parts['path'])) {
				$type = '';
				if (preg_match("/vk.com|vkontakte.ru|vk.me/i", $parts['host'])) {
					$api = new VkApi(\Smm\Oauth::getAccessToken('VK'));
					
					$group_id = substr($parts['path'], 1);
					if (preg_match("/^(public|club)(\d+)$/i", $group_id, $m))
						$group_id = $m[2];
					
					$res = $api->exec("groups.getById", array(
						'group_ids'	=> $group_id
					));
					if ($res->success()) {
						$source_id = -$res->response[0]->id;
						$source_type = \Smm\Grabber::SOURCE_VK;
						$source_name = $res->response[0]->name;
					} else {
						$error = $res->error();
					}
				} elseif (preg_match("/instagram.com/i", $parts['host'])) {
					$tag_name = substr($parts['path'], 1);
					if (preg_match("/tags\/([^\?\/]+)/i", $tag_name, $m))
						$tag_name = urldecode($m[1]);
					
					$data = @file_get_contents("https://www.instagram.com/explore/tags/".urlencode($tag_name)."/?__a=1");
					if (json_decode($data)) {
						$source_id = $tag_name;
						$source_type = \Smm\Grabber::SOURCE_INSTAGRAM;
						$source_name = "#$tag_name";
					} else {
						$error = 'Instagram вернул странную дичь или тег не найден =\\ (тег: '.$tag_name.', ссылка: '.$source_url.')';
					}
				} else {
					$error = '<b>'.$parts['host'].'</b> - чё за сосайт? Не знаю такой!';
				}
			} else {
				$error = 'Чё за дичь!? =\ Не очень похоже на URL.';
			}
			
			if (!$error && $source_type !== false) {
				DB::insert('vk_grabber_sources')
					->ignore()
					->set([
						'source_type'	=> $source_type, 
						'source_id'		=> $source_id, 
					])
					->execute();
				
				$source = DB::select()
					->from('vk_grabber_sources')
					->where('source_type', '=', $source_type)
					->where('source_id', '=', $source_id)
					->execute()
					->current();
				
				DB::insert('vk_grabber_selected_sources')
					->set([
						'source_id'	=> $source['id'], 
						'group_id'	=> $this->group['id'], 
						'name'		=> $source_name, 
						'enabled'	=> 1, 
					])
					->onDuplicateSetValues('name')
					->execute();
				
				return $this->redirect($base_url->set('a', 'grabber/sources')->url());
			}
		}
		
		$sources_list = [];
		foreach ($sources as $s) {
			$key = \Smm\Grabber::$type2name[$s['source_type']]."_".$s['source_our_id'];
			
			switch ($s['source_type']) {
				case \Smm\Grabber::SOURCE_VK:
					$url = 'https://vk.com/public'.(-$s['source_our_id']);
					$icon = 'https://vk.com/favicon.ico';
				break;
				
				case \Smm\Grabber::SOURCE_INSTAGRAM:
					$url = 'https://www.instagram.com/explore/tags/'.urlencode($s['source_our_id']).'/';
					$icon = 'https://www.instagram.com/static/images/ico/favicon.ico/36b3ee2d91ed.ico';
				break;
			}
			
			$sources_list[] = [
				'key'			=> $key,
				'id'			=> $s['source_our_id'],
				'enabled'		=> $s['enabled'], 
				'name'			=> $s['name'], 
				'type'			=> \Smm\Grabber::$type2name[$s['source_type']], 
				'url'			=> $url, 
				'icon'			=> $icon, 
				'on_url'		=> $base_url->copy()
					->set('a', 'grabber/enable_source')
					->set('id', $s['source_id'])
					->set('on', 1)
					->href(), 
				'off_url'		=> $base_url->copy()
					->set('a', 'grabber/enable_source')
					->set('id', $s['source_id'])
					->set('on', 0)
					->href(), 
				'delete_url'	=> $base_url->copy()
					->set('a', 'grabber/delete_source')
					->set('id', $s['source_id'])
					->set('on', 0)
					->href(), 
			];
		}
		
		$this->title = 'Граббер корованов 2000 / Мои корованы';
		$this->content = View::factory('grabber/add', [
			'form_action'		=> Url::current()->href(), 
			'form_error'		=> $error, 
			'form_url'			=> $source_url, 
			'sources'			=> $sources_list
		]);
	}
	
	public function indexAction() {
		$sort = $_REQUEST['sort'] ?? 'DESC';
		$mode = $_REQUEST['mode'] ?? 'external';
		$content_filter = $_REQUEST['content'] ?? 'pics';
		$include = isset($_REQUEST['include']) && is_array($_REQUEST['include']) ? $_REQUEST['include'] : [];
		$exclude = isset($_REQUEST['exclude']) && is_array($_REQUEST['exclude']) ? $_REQUEST['exclude'] : [];
		
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
		
		$sources_ids = [];
		$sources_list = [];
		$include_list = [];
		$exclude_list = [];
		
		foreach ($sources as $s) {
			$key = \Smm\Grabber::$type2name[$s['source_type']]."_".$s['source_our_id'];
			
			switch ($s['source_type']) {
				case \Smm\Grabber::SOURCE_VK:
					$url = 'https://vk.com/public'.(-$s['source_our_id']);
					$icon = 'https://vk.com/favicon.ico';
				break;
				
				case \Smm\Grabber::SOURCE_INSTAGRAM:
					$url = 'https://www.instagram.com/explore/tags/'.urlencode($s['source_our_id']).'/';
					$icon = 'https://www.instagram.com/static/images/ico/favicon.ico/36b3ee2d91ed.ico';
				break;
			}
			
			$source_view = [
				'key'			=> $key,
				'id'			=> $s['source_our_id'],
				'enabled'		=> $s['enabled'], 
				'name'			=> $s['name'], 
				'type'			=> \Smm\Grabber::$type2name[$s['source_type']], 
				'url'			=> $url, 
				'icon'			=> $icon
			];
			
			if (in_array($key, $include))
				$include_list[] = $source_view;
			
			if (in_array($key, $exclude))
				$exclude_list[] = $source_view;
			
			if ($s['enabled'])
				$sources_ids[] = $s['source_id'];
			
			$sources_list[] = $source_view;
		}
		
		$this->title = 'Граббер корованов 2000';
		$this->content = View::factory('grabber/index', [
			'add_url'			=> $base_url->copy()->set('a', 'grabber/sources')->href(), 
			
			'sources_ids'		=> $sources_ids, 
			'sources'			=> $sources_list, 
			'include_list'		=> $include_list, 
			'exclude_list'		=> $exclude_list, 
			
			'sort'				=> $sort, 
			'mode'				=> $mode, 
			'content_filter'	=> $content_filter, 
			'include'			=> $include, 
			'exclude'			=> $exclude, 
			
			'gid'				=> $this->group['id'], 
			'mode_tabs'			=> $mode_tabs->render(), 
			'content_tabs'		=> $content_tabs->render(), 
			'sort_tabs'			=> $sort_tabs->render()
		]);
	}
	
	private function _getSources() {
		return DB::select('ss.*', 's.source_type', ['s.source_id', 'source_our_id'])
			->from(['vk_grabber_selected_sources', 'ss'])
			->join(['vk_grabber_sources', 's'])
				->on('s.id', '=', 'ss.source_id')
			->where('ss.group_id', '=', $this->group['id'])
			->order('s.id', 'DESC')
			->execute()
			->asArray('id');
	}
}
