<?php
namespace Smm\Controllers;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Util\Url;

use \Smm\View\Widgets;

class VkMiniAppsController extends \Smm\VkAppController {
	public function indexAction() {
		if (($error = $this->checkStartupParams()))
			return $this->_error($error);
		
		$this->title = 'Сервисное приложение';
		
		$app_id = $_GET['api_id'] ?? 0;
		$group_id = $_GET['group_id'] ?? 0;
		$user_id = $_GET['viewer_id'] ?? 0;
		$user_access_token = $_GET['access_token'] ?? '';
		
		if (!$group_id) {
			$this->content = View::factory('vk_apps/not_installed', ['app_id' => $app_id]);
			return;
		}
		
		// Get group info
		$group = DB::select()
			->from('vk_groups')
			->where('id', '=', $group_id)
			->execute()
			->current();
		
		$error = false;
		
		if (!$group)
			return $this->_error('Данное сообщество не было добавлено в <b>Котопубликовалку</b>.');
		
		$auth_status = 'not_set';
		
		$VK_COMM_MINI_APP = \Z\Config::get("oauth", "VK_COMM_MINI_APP");
		$access_token = \Smm\Oauth::getGroupAccessToken($group_id);
		
		if ($access_token) {
			$api = new \Smm\VK\API($access_token);
			$res = $api->exec("groups.getTokenPermissions");
			
			if ($res->success()) {
				if (($res->response->mask & \Smm\Oauth::VK_MINIMAL_GROUP_ACCESS) != \Smm\Oauth::VK_MINIMAL_GROUP_ACCESS) {
					$auth_status = 'expired';
				} else {
					$auth_status = 'success';
				}
			} else {
				$auth_status = 'error';
			}
		}
		
		$group_widget = [
			'type'			=> 'text', 
			'code'			=> 'return false;'
		];
		
		$this->content = View::factory('vk_apps/widgets', [
			'group_id'				=> $group['id'], 
			'user_id'				=> $user_id, 
			'group_widget'			=> $group_widget, 
			'user_id_sign'			=> hash_hmac("sha256", "$user_id:$group_id", $VK_COMM_MINI_APP['secret']), 
			'group_name'			=> $group['name'], 
			'auth_status'			=> $auth_status, 
			'vk_request_access'		=> \Smm\Oauth::VK_MINIMAL_GROUP_ACCESS
		]);
	}
	
	public function update_tokenAction() {
		$this->mode('json');
		
		$access_token = $_POST['access_token'] ?? '';
		$group_id = $_POST['group_id'] ?? 0;
		$user_id = $_POST['user_id'] ?? 0;
		$user_id_sign = $_POST['user_id_sign'] ?? 0;
		
		$VK_COMM_MINI_APP = \Z\Config::get("oauth", "VK_COMM_MINI_APP");
		$user_id_sign_real = hash_hmac("sha256", "$user_id:$group_id", $VK_COMM_MINI_APP['secret']);
		
		$this->content['success'] = false;
		$this->content['error'] = false;
		
		if ($user_id_sign_real === $user_id_sign) {
			$api = new \Smm\VK\API([
				'access_token'			=> $access_token, 
				'secret'				=> '', 
				'access_token_type'		=> 'community', 
				'client'				=> 'standalone'
			]);
			$res = $api->exec("groups.getMembers", [
				'filter'	=> 'managers', 
				'group_id'	=> $group_id, 
				'offset'	=> 0, 
				'count'		=> 100
			]);
			
			if ($res->error()) {
				$this->content['error'] = $res->error();
			} else {
				$is_admin = false;
				foreach ($res->response->items as $member) {
					if ($member->id == $user_id) {
						$is_admin = in_array($member->role, ['creator', 'administrator']);
						break;
					}
				}
				
				if (!$is_admin) {
					$this->content['response'] = $res->response;
					$this->content['error'] = 'Вы не являетесь администратором сообщества, невозможно обновить Access Token.';
				} else {
					DB::insert('vk_groups_oauth')
						->set([
							'group_id'			=> $group_id, 
							'access_token'		=> $access_token
						])
						->onDuplicateSetValues('access_token')
						->execute();
					
					$this->content['success'] = true;
				}
			}
		} else {
			$this->content['error'] = 'Ошибка подписи! Обновите страницу и попробуйте снова.';
		}
	}
	
	public function getAppType() {
		return 'VK_COMM_MINI_APP';
	}
	
	public function _error($error) {
		$this->title = 'Ошибочка';
		$this->content = View::factory('vk_apps/error', [
			'error'		=> $error
		]);
		return false;
	}
}
