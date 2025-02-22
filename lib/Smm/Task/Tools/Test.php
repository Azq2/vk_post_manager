<?php
namespace Smm\Task\Tools;

use \Z\DB;
use \Z\View;
use \Z\Date;
use \Z\Config;
use \Z\Util\Url;
use \Z\Net\Anticaptcha;

use \Smm\VK\Captcha;

class Test extends \Z\Task {
	public function options() {
		return [];
	}
	
	public function run($args) {
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
		
		$this->getDiffStatistic($a, $b, false);
	}
	
	public function getDiffStatistic($a, $b, $relative) {
		$diff = [
			'activity'		=> [], 
			'reach'			=> [], 
			'visitors'		=> [], 
		];
		
		foreach ($a->activity as $k => $v) {
			$diff['activity'][$k] = [
				$v, 
				$b->activity->{$k}
			];
		}
		
		foreach (['reach', 'visitors'] as $k) {
			$diff[$k]['socdem'] = $this->getSocDem($a->{$k}, $b->{$k}, $relative);
			$diff[$k]['countries'] = $this->getGeo($a->{$k}->countries, $b->{$k}->countries, $relative);
			$diff[$k]['cities'] = $this->getGeo($a->{$k}->cities, $b->{$k}->cities, $relative);
		}
		
		foreach (['mobile_reach', 'reach', 'reach_subscribers'] as $k) {
			$diff['reach'][$k] = [
				$a->reach->{$k}, 
				$b->reach->{$k}
			];
		}
		
		foreach (['mobile_views', 'views', 'visitors'] as $k) {
			$diff['visitors'][$k] = [
				$a->visitors->{$k}, 
				$b->visitors->{$k}
			];
		}
		
		echo json_encode($diff)."\n";
	}
	
	public function getGeo($a, $b, $relative) {
		$a_values = [];
		$b_values = [];
		
		$a_total = 0;
		$b_total = 0;
		
		$diff = [];
		
		$names = [];
		
		foreach ($a as $v) {
			$names[] = $v->name;
			$a_values[$v->name] = $v->count;
			$a_total += $v->count;
		}
		
		foreach ($b as $v) {
			$names[] = $v->name;
			$b_values[$v->name] = $v->count;
			$b_total += $v->count;
		}
		
		foreach (array_unique($names) as $name) {
			$a_cnt = $a_values[$name] ?? 0;
			$b_cnt = $b_values[$name] ?? 0;
			
			$diff[$name] = [
				$a_cnt, 
				$b_cnt, 
				$a_cnt / $a_total * 100, 
				$b_cnt / $b_total * 100, 
			];
		}
		
		uasort($diff, function ($a, $b) {
			return max($b[2], $b[3]) <=> max($a[2], $a[3]);
		});
		
		$new_diff = [];
		$n = 0;
		foreach ($diff as $k => $row) {
			if ($n >= 10) {
				if (!isset($new_diff["Остальные"]))
					$new_diff["Остальные"] = [0, 0];
				$new_diff["Остальные"][0] += $row[0];
				$new_diff["Остальные"][1] += $row[1];
			} else {
				$new_diff[$k] = $row;
			}
			++$n;
		}
		
		return $new_diff;
	}
	
	public function getSocDem($a, $b, $relative) {
		$diff = [
			"age"		=> [], 
			"sex"		=> [], 
			"sex_age"	=> [], 
		];
		
		$ages = [
			"12-18", 
			"18-21", 
			"21-24", 
			"24-27", 
			"27-30", 
			"30-35", 
			"35-45", 
			"45-100"
		];
		
		$sexes = ["f", "m"];
		
		$a_values = [];
		$b_values = [];
		
		$a_total = [];
		$b_total = [];
		
		foreach ($sexes as $sex) {
			$a_values[$sex] = 0;
			$b_values[$sex] = 0;
			foreach ($ages as $age) {
				$a_values[$age] = 0;
				$b_values[$age] = 0;
				$a_values[$sex.";".$age] = 0;
				$b_values[$sex.";".$age] = 0;
			}
		}
		
		foreach (['age', 'sex', 'sex_age'] as $k) {
			$a_total[$k] = 0;
			$b_total[$k] = 0;
			
			foreach ($a->{$k} as $v) {
				$a_total[$k] += $v->count;
				$a_values[$v->value] = $v->count;
			}
			
			foreach ($b->{$k} as $v) {
				$b_total[$k] += $v->count;
				$b_values[$v->value] = $v->count;
			}
		}
		
		foreach ($ages as $age) {
			$diff["age"][$age] = [
				$a_values[$age], 
				$b_values[$age], 
				$a_values[$age] / $a_total["age"] * 100, 
				$b_values[$age] / $b_total["age"] * 100
			];
		}
		
		foreach ($sexes as $sex) {
			$diff["sex"][$sex] = [
				$a_values[$sex], 
				$b_values[$sex], 
				$a_values[$sex] / $a_total["sex"] * 100, 
				$b_values[$sex] / $b_total["sex"] * 100
			];
		}
		
		foreach ($sexes as $sex) {
			foreach ($ages as $age) {
				$diff["sex_age"]["$sex;$age"] = [
					$a_values["$sex;$age"], 
					$b_values["$sex;$age"], 
					$a_values["$sex;$age"] / $a_total["sex_age"] * 100, 
					$b_values["$sex;$age"] / $b_total["sex_age"] * 100
				];
			}
		}
		
		return $diff;
	}
}
