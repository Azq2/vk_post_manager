<?php
namespace Smm\Task;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;
use \Z\Net\Anticaptcha;

use \Smm\VK\Captcha;

class VkWidgets extends \Z\Task {
	protected $temp_files = [];
	
	public function options() {
		return [];
	}
	
	public function run($args) {
		if (!\Smm\Utils\Lock::lock(__CLASS__)) {
			echo "Already running.\n";
			return;
		}
		
		foreach (DB::select()->from('vk_groups')->where('deleted', '=', 0)->execute() as $group) {
			switch ($group['widget']) {
				case "top_users":
					$this->topUsersWidget($group);
				break;
			}
		}
	}
	
	public function topUsersWidget($group) {
		echo date("Y-m-d H:i:s")." - #".$group['id']." [ GROUP: ".$group['name'].", WIDGET: ".$group['widget']." ]\n";
		
		$widget = DB::select()
			->from('vk_widget_top_users')
			->where('group_id', '=', $group['id'])
			->execute()
			->current();
		
		$tiles = array_slice(explode(",", $widget['tiles']), 0, $widget['tiles_n']);
		$tiles = array_filter($tiles, function ($v) {
			return !empty($v);
		});
		
		$date_to = time();
		$date_from = $date_to - 3600 * 24 * ($widget['days'] - 1);
		
		$formula = '(SUM(likes) * '.$widget['cost_likes'].' + SUM(comments_meaningful) * '.$widget['cost_comments'].')';
		
		$ALLOWED_SIZES = ['480.480', '480.720'];
		
		$api = new \Smm\VK\API(\Smm\Oauth::getGroupAccessToken($group['id']));
		
		// Get community managers
		$error = false;
		for ($i = 0; $i < 3; ++$i) {
			$managers_query = $api->exec("groups.getMembers", [
				'group_id'		=> $group['id'], 
				'filter'		=> 'managers', 
				'offset'		=> 0, 
				'count'			=> 1000
			]);
			
			if (!$managers_query->success()) {
				$error = $managers_query->error();
				sleep(1);
			} else {
				$error = false;
				break;
			}
		}
		
		if ($error) {
			echo "Managers get error: ".$error."\n";
			return;
		}
		
		$skip_users = [];
		foreach ($managers_query->response->items as $u)
			$skip_users[] = $u->id;
		
		$blacklist = DB::select()
			->from('vk_widget_top_users_blacklist')
			->where('group_id', '=', $group['id'])
			->execute()
			->asArray(NULL, 'user_id');
		
		// Select top users from stat
		$users = DB::select(
			'user_id', 
			['SUM(likes)', 'likes'], 
			['SUM(comments_meaningful)', 'comments_meaningful'], 
			[$formula, 'points']
		)
			->from('vk_activity_stat')
			->where('date', 'BETWEEN', [date("Y-m-d", $date_from), date("Y-m-d", $date_to)])
			->where('owner_id', '=', -$group['id'])
			->where('user_id', '>', 0)
			->order('points', 'DESC')
			->group('user_id')
			->limit(count($tiles));
		
		if ($blacklist)
			$users->where('user_id', 'NOT IN', $blacklist);
		
		if ($skip_users)
			$users->where('user_id', 'NOT IN', $skip_users);
		
		$users = $users->execute()->asArray('user_id');
		
		if (count($users) < count($tiles)) {
			echo "Not enought users! (need ".count($tiles).", but get ".count($users).")\n";
			return false;
		}
		
		// Get top users data
		$error = false;
		for ($i = 0; $i < 3; ++$i) {
			$users_query = $api->exec("users.get", [
				"user_ids"		=> implode(",", array_keys($users)), 
				"fields"		=> "photo_100,screen_name"
			]);
			
			if (!$users_query->success()) {
				$error = $users_query->error();
				sleep(1);
			} else {
				$error = false;
				break;
			}
		}
		
		if ($error) {
			echo "Users get error: ".$error."\n";
			return;
		}
		
		$vk_users = [];
		foreach ($users_query->response as $u)
			$vk_users[$u->id] = $u;
		
		$tiles_data = [];
		$i = 0;
		foreach ($users as $id => $user) {
			if (!isset($vk_users[$id]))
				continue;
			
			$tiles_data[] = [
				'template'		=> $tiles[$i], 
				'avatar'		=> $vk_users[$id]->photo_100, 
				'name'			=> $vk_users[$id]->first_name, 
				'surname'		=> $vk_users[$id]->last_name, 
				'likes'			=> $user['likes'], 
				'comments'		=> $user['comments_meaningful'], 
				'points'		=> $user['points'], 
			];
			++$i;
		}
		
		// Process images
		$last_width = 0;
		$last_height = 0;
		
		foreach ($tiles_data as $n => $tile) {
			if (!$tile['template']) {
				echo "Tile #$n not set!\n";
				return;
			}
			
			list ($width, $height) = getimagesize(APP.'www/files/vk_widget/'.$tile['template']);
			
			if (!in_array("$width.$height", $ALLOWED_SIZES)) {
				echo "Tile #$n has invalid size! ($width.$height)\n";
				return;
			}
			
			if ($last_width && $last_height && $last_width != $width && $last_height != $height) {
				echo "Tile #$n has invalid size! ($width.$height, but prev tile has $last_width.$last_height)\n";
				return;
			}
			
			$image = imagecreatefrompng(APP.'www/files/vk_widget/'.$tile['template']);
			
			if (!$image) {
				echo "Tile #$n is not PNG!\n";
				return;
			}
			
			imagesavealpha($image, true);
			imagealphablending($image, true);
			
			$min_transparnet_x = -1;
			$max_transparnet_x = -1;
			$min_transparnet_y = -1;
			$max_transparnet_y = -1;
			
			$width = imagesx($image);
			$height = imagesy($image);
			
			for ($x = 0; $x < $width; ++$x) {
				for ($y = 0; $y < $height; ++$y) {
					$color = imagecolorat($image, $x, $y);
					if ($color & 0xFF000000) {
						if ($min_transparnet_x == -1 || $x < $min_transparnet_x)
							$min_transparnet_x = $x;
						if ($max_transparnet_x == -1 || $x > $max_transparnet_x)
							$max_transparnet_x = $x;
						if ($min_transparnet_y == -1 || $y < $min_transparnet_y)
							$min_transparnet_y = $y;
						if ($max_transparnet_y == -1 || $y > $max_transparnet_y)
							$max_transparnet_y = $y;
					}
				}
			}
			
			if ($min_transparnet_x != -1 && $max_transparnet_x != -1 && $min_transparnet_y != -1 && $max_transparnet_y != -1) {
				$place_width = $max_transparnet_x - $min_transparnet_x + 1;
				$place_height = $max_transparnet_y - $min_transparnet_y + 1;
				
				$avatar_string = file_get_contents($tile['avatar']);
				$avatar = imagecreatefromstring($avatar_string);
				
				if (!$avatar) {
					echo "Can't get avatar (".$tile['avatar'].") for tile #$n!\n";
					return;
				}
				
				// background
				$place = imagecreatetruecolor($width, $height);
				imagesavealpha($place, true);
				imagealphablending($place, true);
				imagefill($place, 0, 0, imagecolorallocatealpha($place, 0, 0, 0, 127));
				
				// add avatar to background
				imagecopyresized($place, $avatar, $min_transparnet_x, $min_transparnet_y, 0, 0, $place_width, $place_height, imagesx($avatar), imagesy($avatar));
				
				// merge foreground to background
				imagecopy($place, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
				
				$tmp_path = APP."tmp/".$tile['template'];
				$this->temp_files[] = $tmp_path;
				$tiles_data[$n]['tmp_path'] = $tmp_path;
				
				imagepng($place, APP."www/files/".$tile['template']);
				
				if (!imagepng($place, $tmp_path)) {
					echo "Can't save image (".$tmp_path.") for tile #$n!\n";
					return;
				}
			} else {
				echo "Can't find avatar placeholder for tile #$n!\n";
				return;
			}
			
			$last_width = $width;
			$last_height = $height;
		}
		
		$error = false;
		for ($i = 0; $i < 3; ++$i) {
			$upload_server = $api->exec("appWidgets.getGroupImageUploadServer", [
				'image_type'		=> ($last_width / 3)."x".($last_height / 3)
			]);
			
			if (!$upload_server->success()) {
				$error = $upload_server->error();
				sleep(1);
			} elseif (!isset($upload_server->response->upload_url)) {
				$error = "upload_url not found!";
				sleep(1);
			} else {
				$error = false;
				break;
			}
		}
		
		if ($error) {
			echo "Can't get upload server: $error\n";
			return;
		}
		
		$cache = \Z\Cache::instance();
		
		$widget_tiles_items_member = [];
		$widget_tiles_items_guest = [];
		
		// Upload images and generate "Tile" widget
		foreach ($tiles_data as $n => $tile) {
			$macroses = [
				'name'			=> $tile['name'], 
				'surname'		=> $tile['surname'], 
				'likes'			=> $tile['likes'], 
				'comments'		=> $tile['comments'], 
				'balls'			=> $tile['points'], 
			];
			
			$md5 = md5_file($tile['tmp_path']);
			$image_id = $cache->get("top_users_widget:$md5");
			
			if (!$image_id) {
				// Upload
				$error = false;
				for ($i = 0; $i < 3; ++$i) {
					$upload_raw = $api->upload($upload_server->response->upload_url, [
						['path' => $tile['tmp_path'], 'name' => 'file.png', 'key' => 'image', 'mime' => 'image/png']
					]);
					$upload = @json_decode($upload_raw->body);
					
					if ($upload_raw->code != 200) {
						$error = "Upload network error: (path: ".$tile['tmp_path'].", server: ".$res->response->upload_url.", code: ".$upload_raw->code.")";
						sleep(1);
					} else if (!$upload) {
						$error = "Bad answer: ".$upload_raw->body;
						sleep(1);
					} else if (isset($upload->error)) {
						$error = $upload->error;
						sleep(1);
					} else {
						$error = false;
						break;
					}
				}
				
				if ($error) {
					echo "Upload error for #$n: $error\n";
					return;
				}
				
				// Try save
				for ($i = 0; $i < 3; ++$i) {
					$file = $api->exec("appWidgets.saveGroupImage", [
						'hash'			=> $upload->hash, 
						'image'			=> $upload->image
					]);
					
					if ($file->success()) {
						$error = false;
						
						$image_id = $file->response->id;
						
						break;
					} else {
						$error = $file->error();
						
						if ($file->errorCode() == \Smm\VK\API\Response::VK_ERR_TOO_FAST)
							sleep(3);
					}
				}
				
				if ($error) {
					echo "Upload save error for #$n: $error\n";
					return;
				}
				
				echo "$image_id - uploaded [$md5]\n";
			} else {
				echo "$image_id - file from cache [$md5]\n";
			}
			
			$cache->set("top_users_widget:$md5", $image_id, 3600 * 24 * 7);
			
			$widget_tiles_items_member[] = [
				'title'			=> \Smm\Utils\Text::prepareMacroses($widget['tile_title'], $macroses), 
				'descr'			=> \Smm\Utils\Text::prepareMacroses($widget['tile_descr'], $macroses), 
				'link'			=> \Smm\Utils\Text::prepareMacroses($widget['tile_link'], $macroses), 
				'icon_id'		=> $image_id, 
				'link_url'		=> 'https://vk.com/public'.$group['id'], 
				'url'			=> 'https://vk.com/public'.$group['id'], 
			];
			
			$widget_tiles_items_guest[] = [
				'title'			=> \Smm\Utils\Text::prepareMacroses($widget['tile_title'], $macroses), 
				'descr'			=> \Smm\Utils\Text::prepareMacroses($widget['tile_descr'], $macroses), 
				'link'			=> \Smm\Utils\Text::prepareMacroses($widget['tile_link'], $macroses), 
				'icon_id'		=> $image_id, 
				'link_url'		=> 'https://vk.com/widget_community.php?act=a_subscribe_box&oid=-'.$group['id'].'&state=1', 
				'url'			=> 'https://vk.com/widget_community.php?act=a_subscribe_box&oid=-'.$group['id'].'&state=1', 
			];
		}
		
		// Try save
		for ($i = 0; $i < 3; ++$i) {
			$js_code = '
				var is_member = API.groups.isMember({
					group_id:		'.$group['id'].', 
					user_id:		Args.uid
				});
				
				if (is_member) {
					return '.json_encode([
						'title'		=> $widget['title'], 
						'tiles'		=> $widget_tiles_items_member
					], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).';
				} else {
					return '.json_encode([
						'title'		=> $widget['title'], 
						'tiles'		=> $widget_tiles_items_guest
					], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).';
				}
			';
			
			$update_widget = $api->exec("appWidgets.update", [
				'type'			=> 'tiles', 
				'code'			=> $js_code
			]);
			
			if ($update_widget->success()) {
				$error = false;
				echo "Done.\n";
				break;
			} else {
				$error = $update_widget->error();
				
				if ($update_widget->errorCode() == \Smm\VK\API\Response::VK_ERR_TOO_FAST)
					sleep(3);
			}
		}
		
		if ($error) {
			echo "Can't update widget: $error\n";
			return;
		}
	}
}
