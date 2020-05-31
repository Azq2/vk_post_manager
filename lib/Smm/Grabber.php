<?php
namespace Smm;

use \Z\DB;

class Grabber {
	const SOURCE_VK			= 0;
	const SOURCE_OK			= 1;
	const SOURCE_INSTAGRAM	= 2;
	const SOURCE_PINTEREST	= 3;
	
	public static $type2name = [
		self::SOURCE_VK			=> 'VK', 
		self::SOURCE_OK			=> 'OK', 
		self::SOURCE_INSTAGRAM	=> 'INSTAGRAM', 
		self::SOURCE_PINTEREST	=> 'PINTEREST', 
	];
	
	public static $name2type = [
		'VK'			=> self::SOURCE_VK, 
		'OK'			=> self::SOURCE_OK, 
		'INSTAGRAM'		=> self::SOURCE_INSTAGRAM, 
		'PINTEREST'		=> self::SOURCE_PINTEREST
	];
	
	public static function getSources($type) {
		$sources_enabled = [];
		$sources_disabled = [];
		
		$enabled_sources = DB::select('source_id', ['MAX(enabled)', 'enabled'])
			->from('vk_grabber_selected_sources')
			->group('source_id')
			->execute()
			->asArray('source_id', 'enabled');
		
		$all_groups_ids = [];
		
		if ($type == self::SOURCE_VK) {
			$all_groups_ids = DB::select('id')
				->from('vk_groups')
				->where('deleted', '=', 0)
				->execute()
				->asArray(NULL, 'id');
		}
		
		$sources_query = DB::select()
			->from('vk_grabber_sources')
			->where('source_type', '=', $type)
			->execute();
		
		foreach ($sources_query as $s) {
			$is_enabled = $enabled_sources[$s['id']] ?? false;
			
			if ($s['source_type'] == self::SOURCE_VK && in_array(-$s['source_id'], $all_groups_ids))
				$is_enabled = true;
			
			if ($is_enabled) {
				$sources_enabled[$s['id']] = $s;
			} else {
				$sources_disabled[$s['id']] = $s;
			}
		}
		
		return [$sources_enabled, $sources_disabled];
	}
	
	public static function cleanUnclaimedPosts($sources_ids) {
		if (!$sources_ids)
			return 0;
		
		$rows = DB::select('id', 'data_id')
			->from('vk_grabber_data_index')
			->where('source_id', 'IN', $sources_ids)
			->limit(1000)
			->execute()
			->asArray();
		
		$post_ids = array_map(function ($v) { return $v['id']; }, $rows);
		$data_ids = array_map(function ($v) { return $v['data_id']; }, $rows);
		
		if ($rows) {
			DB::begin();
			DB::delete('vk_grabber_data')
				->where('id', 'IN', $data_ids)
				->execute();
			DB::delete('vk_grabber_data_index')
				->where('id', 'IN', $post_ids)
				->execute();
			DB::commit();
		}
		
		return count($rows);
	}
	
	public static function cleanOldPosts($sources_ids, $max_age) {
		if (!$sources_ids)
			return 0;
		
		$rows = DB::select('id', 'data_id')
			->from('vk_grabber_data_index')
			->where('source_id', 'IN', $sources_ids)
			->where('grab_time', '<=', $max_age)
			->limit(1000)
			->execute()
			->asArray();
		
		$post_ids = array_map(function ($v) { return $v['id']; }, $rows);
		$data_ids = array_map(function ($v) { return $v['data_id']; }, $rows);
		
		if ($rows) {
			echo "delete ".count($rows)." old posts...\n";
			DB::delete('vk_grabber_data')
				->where('id', 'IN', $data_ids)
				->execute();
			DB::delete('vk_grabber_data_index')
				->where('id', 'IN', $post_ids)
				->execute();
		}
		
		return count($rows);
	}
	
	public static function addNewPost($data) {
		DB::begin();
		
		$old_record = DB::select('data_id', 'source_id')
			->from('vk_grabber_data_index')
			->where('source_type', '=', $data->source_type)
			->where('remote_id', '=', $data->remote_id)
			->execute()
			->current();
		
		$data_id = 0;
		$source_id = $data->source_id;
		
		if (!$old_record) {
			$data_id = DB::insert('vk_grabber_data')
				->set([
					'text'		=> $data->text, 
					'owner'		=> $data->owner, 
					'attaches'	=> gzdeflate(serialize($data->attaches)), 
				])
				->onDuplicateSetValues('text')
				->onDuplicateSetValues('owner')
				->onDuplicateSetValues('attaches')
				->execute()
				->insertId();
		} else {
			$source_id = $old_record['source_id'];
			$data_id = $old_record['data_id'];
			
			DB::update('vk_grabber_data')
				->set([
					'text'		=> $data->text, 
					'owner'		=> $data->owner, 
					'attaches'	=> gzdeflate(serialize($data->attaches)), 
				])
				->where('id', '=', $data_id)
				->execute();
		}
		
		$post_type = 0;
		if ($data->images_cnt > 0 && $data->gifs_cnt > 0)
			$post_type = 3;
		elseif ($data->images_cnt > 0)
			$post_type = 2;
		elseif ($data->gifs_cnt > 0)
			$post_type = 1;
		
		DB::insert('vk_grabber_data_index')
			->set([
				'source_id'			=> $source_id, 
				'data_id'			=> $data_id, 
				'grab_time'			=> time(), 
				'post_type'			=> $post_type, 
				'source_type'		=> $data->source_type, 
				'remote_id'			=> $data->remote_id, 
				'time'				=> $data->time, 
				'likes'				=> $data->likes, 
				'comments'			=> $data->comments, 
				'reposts'			=> $data->reposts, 
				'images_cnt'		=> $data->images_cnt, 
				'gifs_cnt'			=> $data->gifs_cnt, 
				'likes'				=> $data->likes, 
			])
			->onDuplicateSetValues('data_id')
			->onDuplicateSetValues('time')
			->onDuplicateSetValues('grab_time')
			->onDuplicateSetValues('likes')
			->onDuplicateSetValues('comments')
			->onDuplicateSetValues('reposts')
			->onDuplicateSetValues('images_cnt')
			->onDuplicateSetValues('gifs_cnt')
			->execute();
		
		DB::commit();
		
		return !$old_record;
	}
}
