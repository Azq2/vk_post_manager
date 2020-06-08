<?php
namespace Smm\VK;

class Statistic {
	public static function computeStatisticDiff($a, $b) {
		$diff = [
			'activity'		=> [], 
			'reach'			=> [], 
			'visitors'		=> [], 
		];
		
		$a->activity->self_growth = $a->activity->subscribed - $a->activity->unsubscribed;
		$b->activity->self_growth = $b->activity->subscribed - $b->activity->unsubscribed;
		
		$a->reach->pc_reach = $a->reach->reach - $a->reach->mobile_reach;
		$b->reach->pc_reach = $b->reach->reach - $b->reach->mobile_reach;
		
		$a->reach->reach_guests = $a->reach->reach - $a->reach->reach_subscribers;
		$b->reach->reach_guests = $b->reach->reach - $b->reach->reach_subscribers;
		
		foreach ($a->activity as $k => $v) {
			$diff['activity'][$k] = [
				$v, 
				$b->activity->{$k}
			];
		}
		
		foreach (['reach', 'visitors'] as $k) {
			$diff[$k]['socdem'] = self::computeSocDemDiff($a->{$k}, $b->{$k});
			$diff[$k]['countries'] = self::computeGeoDiff($a->{$k}->countries, $b->{$k}->countries);
			$diff[$k]['cities'] = self::computeGeoDiff($a->{$k}->cities, $b->{$k}->cities);
		}
		
		$fields = ['mobile_reach', 'pc_reach', 'reach_subscribers', 'reach_guests', 'reach'];
		foreach ($fields as $k) {
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
		
		return $diff;
	}
	
	public static function computeGeoDiff($a, $b) {
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
					$new_diff["Остальные"] = [0, 0, 0, 0];
				$new_diff["Остальные"][0] += $row[0];
				$new_diff["Остальные"][1] += $row[1];
				$new_diff["Остальные"][2] += $row[2];
				$new_diff["Остальные"][3] += $row[3];
			} else {
				$new_diff[$k] = $row;
			}
			++$n;
		}
		
		return $new_diff;
	}
	
	public static function computeSocDemDiff($a, $b) {
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
