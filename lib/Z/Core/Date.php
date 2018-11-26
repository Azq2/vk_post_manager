<?php
namespace Z\Core;

class Date {
	public static function getDayStart($time = 0) {
		$time = !$time ? time() : $time;
		return mktime(0, 0, 0, date("m", $time), date("d", $time), date("Y", $time));
	}
	
	public static function display($time_unix, $full = false, $show_time = true) {
		$to_russian_week = [6, 0, 1, 2, 3, 4, 5];
		$week_names = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
		$month_list = [
			1  => 'Января', 
			2  => 'Февраля', 
			3  => 'Марта', 
			4  => 'Апреля', 
			5  => 'Мая', 
			6  => 'Июня', 
			7  => 'Июля', 
			8  => 'Августа', 
			9  => 'Сентября', 
			10 => 'Октября', 
			11 => 'Ноября', 
			12 => 'Декабря', 
			13 => 'Мартабря', 
		];
		$month_list_short = [
			1  => 'янв', 
			2  => 'фев', 
			3  => 'мар', 
			4  => 'апр', 
			5  => 'мая', 
			6  => 'июн', 
			7  => 'июл', 
			8  => 'авг', 
			9  => 'сен', 
			10 => 'окт', 
			11 => 'ноя', 
			12 => 'дек', 
		];
		
		$curr_time = localtime(time());
		$time = localtime($time_unix);
		
		if ($full)
			return date("d ".$month_list[$time[4] + 1]." Y в H:i:s", $time_unix);
		
		// Сегодня
		if ($time[3] == $curr_time[3] && $time[4] == $curr_time[4] && $time[5] == $curr_time[5])
			return $show_time ? date("H:i:s", $time_unix) : "сегодня";
		
		if (time() >= $time_unix) {
			// Вчера
			$yesterday = mktime(0, 0, 0, $curr_time[4] + 1, $curr_time[3] - 1, 1900 + $curr_time[5]);
			if ($yesterday <= $time_unix)
				return "вчера".($show_time ? " ".date("H:i:s", $time_unix) : "");
			
			// На этой неделе
			$start_week = mktime(0, 0, 0, $curr_time[4] + 1, $curr_time[3] - $to_russian_week[$curr_time[6]], 1900 + $curr_time[5]);
			if ($start_week <= $time_unix)
				return $week_names[$to_russian_week[$time[6]]].($show_time ? ", ".date("H:i:s", $time_unix) : "");
		}
		
		// В этом году
		if ($curr_time[5] == $time[5])
			return $time[3]." ".$month_list_short[$time[4] + 1].($show_time ? " ".date("H:i:s", $time_unix) : "");
		
		// Хрен знает когда
		return $time[3]." ".$month_list_short[$time[4] + 1]." ".(1900 + $time[5]);
	}
	
	public static function countTime($time = 0) {
		$out = [];
		
		$years = floor($time / (3600 * 24 * 365));
		if ($years > 0) {
			$time -= $years * 3600 * 24 * 365;
			$out[] = $years." год";
		}
		
		$months = floor($time / (3600 * 24 * 30));
		if ($months > 0) {
			$time -= $months * 3600 * 24 * 30;
			$out[] = $months." мес";
		}
		
		$days = floor($time / (3600 * 24));
		if ($days > 0) {
			$time -= $days * 3600 * 24;
			$out[] = $days." дн";
		}
		
		$hours = floor($time / 3600);
		if ($hours > 0 || $days > 0) {
			$time -= $hours * 3600;
			$out[] = $hours." ч";
		}
		
		$minutes = floor($time / 60);
		if ($minutes > 0 || $hours > 0 || $days > 0) {
			$time -= $minutes * 60;
			$out[] = $minutes." м";
		}
		
		if (empty($out) || $time > 0) {
			$seconds = $time;
			$out[] = $seconds."с";
		}
		return implode(', ', array_slice($out, 0, 2));
	}
	
	public static function countDelta($time = 0) {
		$out = [];
	
		$years = floor($time / (3600 * 24 * 365));
		$time -= $years * 3600 * 24 * 365;
		
		$months = floor($time / (3600 * 24 * 30));
		$time -= $months * 3600 * 24 * 30;
		
		$days = floor($time / (3600 * 24));
		$time -= $days * 3600 * 24;
		
		$hours = floor($time / 3600);
		$time -= $hours * 3600;
		
		$minutes = floor($time / 60);
		$time -= $minutes * 60;
		
		$seconds = $time;
		
		if ($years > 0)
			$out[] = $years." год";
		
		if ($months > 0)
			$out[] = $months." мес.";
		
		if ($days > 0)
			$out[] = $days." день";
		
		if ($hours > 0 || $days > 0 || $minutes > 0) {
			$out[] = sprintf("%02d:%02d", $hours, $minutes);
		}
		
		if (!$out)
			$out[] = sprintf("%02d:%02d:%02d", 0, 0, $seconds);
		
		return implode(" ", $out);
	}
}
