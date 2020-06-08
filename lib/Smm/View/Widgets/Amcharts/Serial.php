<?php
namespace Smm\View\Widgets\Amcharts;

class Serial extends \Smm\View\Widget {
	private $colors = [
		// default amcharts
		"#67b7dc", "#fdd400", "#84b761", "#cc4748", "#cd82ad", "#2f4074", "#448e4d", "#b7b83f", "#b9783f", "#b93e3d", "#913167", 
		
		// additional
		"#e2c6e0", "#FF790C", "#00A383", "#27751A", 
		"#560EAD", "#ff9800", "#fe98df", "#FF0000", "#0000FF", "#00FF00", "#000000", "#00FFFF"
	];
	
	private $config, $height = 600;
	
	public function __construct($id = 'chart', $config = []) {
		$this->id = $id;
		
		$this->config = array_merge([
			'type'				=>  'serial',
			'theme'				=> 'none', 
			'chartScrollbar'	=> [
				'autoGridCount'		=> true, 
				'scrollbarHeight'	=> 40
			], 
			'categoryField'		=> 'date', 
			'categoryAxis'		=> [
				'parseDates'		=> true, 
				'dashLength'		=> 1, 
				'autoGridCount'		=> true, 
				'minorGridEnabled'	=> true
			], 
			'legend'			=> [
				'align'				=> 'center', 
				'markerType'		=> 'circle', 
				'divId'				=> 'chart_'.$this->id.'_legend'
			], 
			'valueAxis'			=> [
				'axisAlpha'			=> 0, 
				'inside'			=> true, 
				'gridCount'			=> 15
			], 
			'chartCursor'		=> [], 
			'categoryAxis'		=> [], 
			'balloon'			=> [
				'fillAlpha'			=> 1
			], 
			'graphs'			=> [], 
			'dataProvider'		=> []
		], $config);
	}
	
	public function addGraph($title, $field, $opts = array()) {
		$color_index = count($this->config['graphs']) % count($this->colors);
		
		$this->config['graphs'][] = array_merge(array(
			'title'			=> $title, 
			'lineColor'		=> $this->colors[$color_index], 
			'lineThickness'	=> 1.5, 
			'bulletSize'	=> 5, 
			'valueField'	=> $field, 
			'balloonText'	=> '[[title]]: [[value]]'
		), $opts);
	}
	
	public function height($h = NULL) {
		if (func_num_args() > 0) {
			$this->height = $h;
			return $this;
		}
		return $this->height;
	}
	
	public function data($data = NULL) {
		if (func_num_args() > 0) {
			$this->config['dataProvider'] = &$data;
			return $this;
		}
		return $this->config['dataProvider'];
	}
	
	public function render() {
		$view = \Z\View::factory('view/widgets/amcharts/serial', [
			'id'				=> $this->id, 
			'config'			=> $this->config, 
			'height'			=> $this->height, 
		]);
		return $view->render();
	}
}
