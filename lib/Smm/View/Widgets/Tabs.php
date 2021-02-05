<?php
namespace Smm\View\Widgets;

use \Z\View;
use \Z\Util\Url;

class Tabs extends \Smm\View\Widget {
	protected $args;
	
	public function __construct($args = []) {
		$this->args = array_merge(array(
			'url'		=> false, 
			'items'		=> [], 
			'param'		=> 'tab', 
			'active'	=> NULL, 
		), $args);
	}
	
	public function addTab($id, $tab) {
		$this->args['items'][$id] = $tab;
		return $this;
	}
	
	public function active($id = NULL) {
		if (is_null($id))
			return $this->args['active'];
		$this->args['active'] = $id;
		return $this;
	}
	
	public function render() {
		$tabs = [];
		foreach ($this->args['items'] as $id => $item) {
			if (is_string($item))
				$item = ['name' => $item];
			
			$url = false;
			if ($this->args['url']) {
				$url = Url::mk($this->args['url'])
					->set($this->args['param'], $id)
					->href();
			}
			
			$tabs[] = array(
				'id'		=> $id, 
				'active'	=> (string) $id == (string) $this->args['active'], 
				'url'		=> isset($item['url']) ? $item['url'] : $url, 
				'name'		=> $item['name'], 
				'last'		=> false, 
				'first'		=> false
			);
		}
		
		if ($tabs) {
			$tabs[0]['first'] = true;
			$tabs[count($tabs) - 1]['last'] = true;
		}
		
		$view = View::factory('view/widgets/tabs')
			->set(array(
				'items'	=> $tabs
			));
		
		return $view->render();
	}
}
