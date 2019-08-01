<?php
namespace Smm\Controllers;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;

use \Smm\View\Widgets;

class CatificatorController extends \Smm\GroupController {
	public function delete_trackAction() {
		$id = intval($_GET['id'] ?? 0);
		
		$track = DB::select()
			->from('catificator_tracks')
			->where('id', '=', $id)
			->execute()
			->current();
		
		if ($track) {
			DB::delete('catificator_tracks')
				->where('id', '=', $id)
				->execute();
			
			unlink(APP.'www/files/catificator/'.$track['md5'].'.mp3');
		}
		
		$base_url = Url::mk('/')
			->set('gid', $this->group['id'])
			->set('a', 'catificator/index');
		
		return $this->redirect($base_url->url());
	}
	
	public function uploadAction() {
		$id = intval($_GET['id'] ?? 0);
		
		$cat = DB::select()
			->from('catificator_categories')
			->where('id', '=', $id)
			->execute()
			->current();
		
		if (!$cat)
			return $this->error('Категория не найдена!');
		
		$errors = [];
		
		if ($_POST) {
			if (!isset($_FILES['file'])) {
				$errors[] = 'Файл не обнаружен!';
			} elseif ($_FILES['file']['error']) {
				$errors[] = 'Ошибка загрузки #'.$_FILES['file']['error'];
			} elseif ($_FILES['file']['size'] >= 1024 * 1024) {
				$errors[] = 'Слишком большой файл.';
			} else if (\Smm\Utils\File::mime($_FILES['file']['tmp_name']) != 'audio/mpeg') {
				$errors[] = 'Файл должен быть mp3! Загруженный файл: '.\Smm\Utils\File::mime($_FILES['file']['tmp_name']);
			} else {
				$md5sum = md5_file($_FILES['file']['tmp_name']);
				
				$duplicate = DB::select()
					->from('catificator_tracks')
					->where('md5', '=', $md5sum)
					->execute()
					->current();
				
				if ($duplicate) {
					$errors[] = 'Точно такой же файл уже был добавлен!';
				} else {
					$new_path = APP.'www/files/catificator/'.$md5sum.'.mp3';
					
					if (!file_exists(dirname($new_path))) {
						umask(0);
						mkdir(dirname($new_path), 0777, true);
					}
					
					if (!move_uploaded_file($_FILES['file']['tmp_name'], $new_path)) {
						$errors[] = 'Невозможно сохранить файл на диск.';
					} else {
						DB::insert('catificator_tracks')
							->set([
								'filename'		=> basename($_FILES['file']['name']), 
								'md5'			=> $md5sum, 
								'category_id'	=> $id
							])
							->execute();
						
						$base_url = Url::mk('/')
							->set('gid', $this->group['id'])
							->set('a', 'catificator/index');
						
						return $this->redirect($base_url->url());
					}
				}
			}
		}
		
		$this->title = 'Боты : Котофикатор';
		$this->content = View::factory('catificator/upload', [
			'cat'			=> $cat, 
			'errors'		=> $errors
		]);
	}
	
	public function addAction() {
		$id = intval($_GET['id'] ?? 0);
		
		$triggers = [];
		
		if ($id) {
			$cat = DB::select()
				->from('catificator_categories')
				->where('id', '=', $id)
				->execute()
				->current();
			
			if (!$cat)
				return $this->error('Категория не найдена!');
			
			$triggers = DB::select('word')
				->from('catificator_category_triggers')
				->where('category_id', '=', $id)
				->execute()
				->asArray(NULL, 'word');
		} else {
			$cat = [
				'title'		=> ''
			];
		}
		
		$errors = [];
		
		if ($id && isset($_POST['delete'])) {
			$n_tracks = DB::select(['COUNT(*)', 'cnt'])
				->from('catificator_tracks')
				->where('category_id', '=', $id)
				->execute()
				->get('cnt', 0);
			
			if ($n_tracks) {
				$errors[] = 'Удаление невозможно! Сначала нужно удалить все треки.';
			} else {
				DB::begin();
				
				DB::delete('catificator_categories')
					->where('id', '=', $id)
					->execute();
				
				DB::delete('catificator_category_triggers')
					->where('category_id', '=', $id)
					->execute();
				
				DB::commit();
				
				$base_url = Url::mk('/')
					->set('gid', $this->group['id'])
					->set('a', 'catificator/index');
				
				return $this->redirect($base_url->url());
			}
		}
		
		if ($_POST && !$errors) {
			$cat['title'] = trim($_POST['title'] ?? '');
			
			$triggers = [];
			foreach ($_POST['trigger'] as $word) {
				$word = trim($word);
				if (strlen($word))
					$triggers[] = $word;
			}
			
			$duplicate = DB::select()
				->from('catificator_categories')
				->where('title', '=', $cat['title'])
				->where('id', '!=', $id)
				->execute()
				->current();
			
			if (!strlen($cat['title'])) {
				$errors[] = 'Укажите название!';
			} else if ($duplicate) {
				$errors[] = 'Категория с таким названием уже существует!';
			} else {
				DB::begin();
				
				if ($id) {
					DB::update('catificator_categories')
						->set($cat)
						->where('id', '=', $id)
						->execute();
				} else {
					$res = DB::insert('catificator_categories')
						->set($cat)
						->execute();
					$id = $res->insertId();
				}
				
				DB::delete('catificator_category_triggers')
					->where('category_id', '=', $cat_id)
					->execute();
				
				foreach ($triggers as $word) {
					DB::insert('catificator_category_triggers')
						->ignore()
						->set([
							'category_id'	=> $id, 
							'word'			=> $word
						])
						->execute();
				}
				
				DB::commit();
				
				$base_url = Url::mk('/')
					->set('gid', $this->group['id'])
					->set('a', 'catificator/index');
				
				return $this->redirect($base_url->url());
			}
		}
		
		if (!$triggers)
			$triggers = [''];
		
		$this->title = 'Боты : Котофикатор';
		$this->content = View::factory('catificator/add', [
			'cat'			=> $cat, 
			'errors'		=> $errors, 
			'is_edit'		=> $id > 0, 
			'triggers'		=> $triggers
		]);
	}
	
	public function indexAction() {
		$categories_query = DB::select()
			->from('catificator_categories');
		
		$base_url = Url::mk('/')
			->set('gid', $this->group['id']);
		
		$categories = [];
		foreach ($categories_query->execute() as $row) {
			$tracks_query = DB::select()
				->from('catificator_tracks')
				->order('id', 'ASC')
				->where('category_id', '=', $row['id']);
			
			$tracks = [];
			foreach ($tracks_query->execute() as $track) {
				$tracks[] = [
					'id'			=> $track['id'], 
					'filename'		=> htmlspecialchars($track['filename']), 
					'url'			=> '/files/catificator/'.$track['md5'].'.mp3', 
					'delete_link'	=> $base_url->copy()->set('a', 'catificator/delete_track')->set('id', $track['id'])->href(), 
				];
			}
			
			$categories[] = [
				'id'			=> $row['id'], 
				'title'			=> htmlspecialchars($row['title']), 
				'edit_link'		=> $base_url->copy()->set('a', 'catificator/add')->set('id', $row['id'])->href(), 
				'upload_link'	=> $base_url->copy()->set('a', 'catificator/upload')->set('id', $row['id'])->href(), 
				'tracks'		=> $tracks
			];
		}
		
		$this->title = 'Боты : Котофикатор';
		$this->content = View::factory('catificator/index', [
			'categories'		=> $categories, 
			'add_link'			=> $base_url->copy()->set('a', 'catificator/add')->href()
		]);
	}
	
	public function accessControl() {
		return [
			'*'		=> ['auth_required' => true, 'users' => 'admin'], 
		];
	}
}
