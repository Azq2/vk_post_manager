<?php
	require "init.php";
	
	$show_chart = isset($_COOKIE['show_chart']) && $_COOKIE['show_chart'];
	if (isset($_GET['show_chart'])) {
		$show_chart = !!$_GET['show_chart'];
		setcookie('show_chart', $show_chart ? 1 : null, $show_chart ? time() + 24 * 3600 * 365 : -1, '/');
	}
?>
<!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="X-UA-Compatible" content="IE=edge" />
		<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no" />
		<title>Стата ВК</title>
		<link rel="stylesheet" type="text/css" href="static/main.css" />
		
		<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/1.12.4/jquery.min.js" integrity="sha256-ZosEbRLbNQzLpnKIkEdrPv7lOy9C27hHQ+Xp8a4MxAQ=" crossorigin="anonymous"></script>
		
		<?php if ($show_chart): ?>
			<script src="https://cdnjs.cloudflare.com/ajax/libs/amcharts/3.21.12/amcharts.js" integrity="sha256-BWWuudFbBaOHSj0fD+HjZtiEn45PQNl+AzErJ5wCY2g=" crossorigin="anonymous"></script>
			<script src="https://cdnjs.cloudflare.com/ajax/libs/amcharts/3.21.12/serial.js" integrity="sha256-YvUVT2EX5u0GeM1zlOWmoACliati8+d4pKbWONQdrUg=" crossorigin="anonymous"></script>
		<?php endif; ?>
	</head>
	
	<body>
<?php
	$comms = array();
	$req = mysql_query("SELECT DISTINCT cid FROM `vk_comm_users`");
	while ($res = mysql_fetch_assoc($req))
		$comms[] = $res['cid'];
	mysql_free_result($req);
	
	$cid = isset($_GET['cid']) ? (int) $_GET['cid'] : 0;
	$sort = isset($_GET['sort']) ? $_GET['sort'] : 'day';
	if (!$cid)
		$cid = $comms[0];
	
	$groups = vk("groups.getById", array('group_ids' => implode(",", $comms)))->response;
	
	switch ($sort) {
		case "day":
			$time = localtime(time());
			$start = mktime(0, 0, 0, $time[4] + 1, $time[3], 1900 + $time[5]);
			$sql = "WHERE `cid` = $cid AND `time` >= ".$start." ORDER BY `id` DESC";
		break; 
		case "yesterday":
			$time = localtime(time());
			$start = mktime(0, 0, 0, $time[4] + 1, $time[3] - 1, 1900 + $time[5]);
			$end = mktime(0, 0, 0, $time[4] + 1, $time[3], 1900 + $time[5]);
			$sql = "WHERE `cid` = $cid AND `time` >= ".$start." AND `time` <= ".$end." ORDER BY `id` DESC";
		break; 
		case "week":
			$time = localtime(time());
			$offsets = array(6, 0, 1, 2, 3, 4, 5);
			$start = mktime(0, 0, 0, $time[4] + 1, $time[3] - $offsets[$time[6]], 1900 + $time[5]);
			$sql = "WHERE `cid` = $cid AND `time` >= ".$start." ORDER BY `id` DESC";
		break; 
		
		case "7day":
			$start = time() - 3600 * 24 * 7;
			$sql = "WHERE `cid` = $cid AND `time` >= ".$start." ORDER BY `id` DESC";
		break; 
		
		case "month":
			$start = time() - 3600 * 24 * 30;
			$sql = "WHERE `cid` = $cid AND `time` >= ".$start." ORDER BY `id` DESC";
		break; 
		
		case "year":
			$start = time() - 3600 * 24 * 365;
			$sql = "WHERE `cid` = $cid AND `time` >= ".$start." ORDER BY `id` DESC";
		break; 
		
		case "2year":
			$start = time() - 3600 * 24 * 365 * 2;
			$sql = "WHERE `cid` = $cid AND `time` >= ".$start." ORDER BY `id` DESC";
		break; 
		
		default:
			$sql = "WHERE `cid` = $cid ORDER BY `id` DESC";
		break; 
	}
	
	$users = array();
	$stat = array(); $need_users = array();
	$req = mysql_query("SELECT * FROM `vk_join_stat` ".$sql);
	$total_join = 0; $total_leave = 0;
	while ($res = mysql_fetch_assoc($req)) {
		if (!isset($users[$res['uid']]))
			$need_users[] = $res['uid'];
		$stat[] = $res;
		
		$res['type'] ? ++$total_join : ++$total_leave;
	}
	
	$cache = @unserialize(@file_get_contents("tmp/cache.txt"));
	if (!$cache)
		$cache = array();
	
	while (count($need_users)) {
		$users_list = array();
		for ($i = 0; $i < 500; ++$i)
			$users_list[] = array_pop($need_users);
		
		$not_found = array();
		foreach ($users_list as $id) {
			if (!isset($cache[$id])) {
				$not_found[] = $id;
				continue;
			}
			$users[$id] = $cache[$id];
		}
		
		if ($not_found) {
			$vk_users = vk("users.get", array('user_ids' => implode(",", $not_found), 'fields' => 'sex,photo_50,bdate'))->response;
			foreach ($vk_users as $u) {
				$users[$u->id] = $u;
				$cache[$u->id] = $u;
			}
			file_put_contents("tmp/cache.txt", serialize($cache));
		}
		unset($vk_users);
	}
	
	if ($show_chart) {
		$tmp = array();
		
		foreach ($stat as $res) {
			if (!isset($tmp[$res['time']]))
				$tmp[$res['time']] = array();
			$tmp[$res['time']][] = $res;
		}
		
		$speed_stat = array();
		foreach ($tmp as $time => $sts) {
			$join = 0; $leave = 0;
			foreach ($sts as $st)
				$st['type'] ? ++$join : ++$leave;
			$speed_stat[] = array(
				'date' => date($sort == 'day' ? "H:i" : "m-d-Y H:i", $time), 
				'join' => $join, 
				'leave' => $leave, 
			);
		}
?>
		<div id="chartdiv" style="width: 100%; height: 300px"></div>
		<script type="text/javascript">
		var chart = AmCharts.makeChart("chartdiv", {
			"type": "serial",
			"theme": "none",
			"marginLeft": 20,
			"pathToImages": "http://www.amcharts.com/lib/3/images/",
			"dataProvider": <?= json_encode(array_reverse($speed_stat)) ?>,
			"valueAxes": [{
				"axisAlpha": 0,
				"inside": true,
				"position": "left",
				"ignoreAxisWidth": true
			}],
			"graphs": [{
				"balloonText": "[[category]]<br><b><span style='font-size:14px;'>[[value]]</span></b>",
				"bullet": "round",
				"bulletSize": 6,
				"lineColor": "#d1655d",
				"lineThickness": 2,
				"negativeLineColor": "#637bb6",
				"type": "smoothedLine",
				"valueField": "leave"
			}, {
				"balloonText": "[[category]]<br><b><span style='font-size:14px;'>[[value]]</span></b>",
				"bullet": "round",
				"bulletSize": 6,
				"lineColor": "#006400",
				"lineThickness": 2,
				"type": "smoothedLine",
				"valueField": "join"
			}],
			"chartScrollbar": {},
			"dataDateFormat": "<?= $sort == 'day' ? 'HH:II' : 'MMM-DD-YYYY HH:II' ?>",
			"categoryField": "date"
		});
		</script>
<?php	
	}
	
	echo '<div class="main">';
	
	$tabs = array();
	foreach ($groups as $group)
		$tabs[$group->id] = array($group->name, '?cid='.$group->id.'&sort='.urlencode($sort));
	
	echo '<div class="selector-comm">'.switch_tabs($tabs, $cid).'</div>';
	echo '<div class="selector-sort">'.switch_tabs(array(
		"day"  => array("За сутки", "?cid=$cid&sort=day"), 
		"yesterday" => array("За вчера", "?cid=$cid&sort=yesterday"), 
		"week" => array("За эту неделю", "?cid=$cid&sort=week"), 
		"7day"  => array("За 7 дней", "?cid=$cid&sort=7day"), 
		"month"  => array("За 30 дней", "?cid=$cid&sort=month"), 
		"year"  => array("За год", "?cid=$cid&sort=year"), 
		"2years"  => array("За 2 года", "?cid=$cid&sort=2years"), 
		"all"  => array("За всё время", "?cid=$cid&sort=all"), 
	), $sort).'</div>';
	if (!$show_chart) {
		echo '<a href="?cid='.$cid.'&sort='.urlencode($sort).'&show_chart=1">Показать график</a><br />';
	} else {
		echo '<a href="?cid='.$cid.'&sort='.urlencode($sort).'&show_chart=0">Скрыть график</a><br />';
	}
	echo '<span class="type_1">Всего вступили: '.$total_join.' ('.@round($total_join * 100 / ($total_join + $total_leave), 2).'%)</span>, '.
		'<span class="type_0">всего покинули: '.$total_leave.' ('.@round($total_leave * 100 / ($total_join + $total_leave), 2).'%)</span>, '.
		'<span class="type_'.(($total_join - $total_leave) < 0 ? 0 : 1).'">профит: '.($total_join - $total_leave).' ('.@round(($total_join - $total_leave) * 100 / ($total_join + $total_leave), 2).'%)</span><br />';
	echo '<table>';
	echo '<tr><th>Дата</th><th>Пользователь</th><th>Возраст</th><th>Юзеры</th></tr>';
	
	$last_cnt = 0;
	foreach ($stat as $i => $res) {
		$u = $users[$res['uid']];
		
		$last_join = 0;
		if (!$res['type']) {
			$req = mysql_query("SELECT `time` FROM `vk_join_stat` WHERE `uid` = ".(int) $res['uid']." AND `time` < ".$res['time']." AND `type` = 1 ORDER BY `id` DESC LIMIT 1");
			if (mysql_num_rows($req) > 0)
				$last_join = mysql_result($req, 0);
		}
		
		$age = '?';
		if (isset($u->bdate)) {
			$dt = explode(".", $u->bdate);
			if (count($dt) == 3)
				$age = DateTime::createFromFormat('d.m.Y', $u->bdate)->diff(new DateTime('now'))->y;
		}
		
		echo '<tr class="type_'.$res['type'].' '.(isset($stat[$i + 1]) && $res['users_cnt'] != $stat[$i + 1]['users_cnt'] ? 'bottom_sep' : '').'">';
		echo '<td class="date">'.display_date($res['time']).'</td>';
		echo '<td><img src="static/img/'.($u->sex == 2 ? 'man' : 'woman').'_on.gif"> '.
				'<!-- <img src="'.$u->photo_50.'" alt="" /> --><a href="http://vk.com/id'.$u->id.'">'.$u->first_name.' '.$u->last_name.
				($last_join ? ', пробыл'.($u->sex == 1 ? 'а' : '').': '.count_time($res['time'] - $last_join) : '').'</a></td>';
		echo '<td>'.$age.'</td>';
		echo '<td>'.($last_cnt != $res['users_cnt'] ? $res['users_cnt'] : '').'</td>';
		echo '</tr>';
		
		$last_cnt = $res['users_cnt'];
	}
	echo '</table>'; 
	echo '</div>'; 
?>
	</body>
</html>

<?php
	function switch_tabs($tabs, $active, $class = 'tab', $active_class = 'tab tab-active') {
		$out = '';
		$total = count($tabs);
		foreach ($tabs as $id => $tab) {
			$out .= '<a href="'.htmlspecialchars(!isset($tab[1]) ? '#' : $tab[1]).'" class="'.
				(strcmp($active, $id) == 0 ? $active_class : $class).'" tab-id="'.$id.'">'.$tab[0].'</a>';
			if (--$total)
				$out .= ' | ';
		}
		return $out;
	}
	
	function display_date($time_unix, $full = false, $show_time = true) {
		static $to_russian_week = [6, 0, 1, 2, 3, 4, 5]; 
		static $week_names = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс']; 
		static $month_list = [
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
		static $month_list_short = [
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
			return date("d ".$month_list[$time[4] + 1]." Y в H:i", $time_unix); 
		
		if (time() >= $time_unix) {
			// Сегодня
			if ($time[3] == $curr_time[3] && $time[4] == $curr_time[4] && $time[5] == $curr_time[5])
				return date("H:i", $time_unix); 
			
			// Вчера
			$yesterday = mktime(0, 0, 0, $curr_time[4] + 1, $curr_time[3] - 1, 1900 + $curr_time[5]); 
			if ($yesterday <= $time_unix)
				return "вчера ".date("H:i", $time_unix); 
			
			// На этой неделе
			$start_week = mktime(0, 0, 0, $curr_time[4] + 1, $curr_time[3] - $to_russian_week[$curr_time[6]], 1900 + $curr_time[5]); 
			if ($start_week <= $time_unix)
				return $week_names[$to_russian_week[$time[6]]].", ".date("H:i", $time_unix); 
		}
		
		// В этом году
		if ($curr_time[5] == $time[5])
			return $time[3]." ".$month_list_short[$time[4] + 1]." ".date("H:i", $time_unix); 
		
		// Хрен знает когда
		return $time[3]." ".$month_list_short[$time[4] + 1]." ".(1900 + $time[5]); 
	}
	
	function count_time($time) {
		$out = []; 
		$days = floor($time / (3600 * 24)); 
		
		if ($days > 0) {
			$time -= $days * 3600 * 24; 
			$out[] = $days."д"; 
		}
		
		$hours = floor($time / 3600); 
		if ($hours > 0 || $days > 0) {
			$time -= $hours * 3600; 
			$out[] = $hours."ч"; 
		}
		
		$minutes = floor($time / 60); 
		if ($minutes > 0 || $hours > 0 || $days > 0) {
			$time -= $minutes * 60; 
			$out[] = $minutes."м"; 
		}
		
		if (empty($out) || $time > 0) {
			$seconds = $time; 
			$out[] = $seconds."с"; 
		}
		return implode(', ', $out); 
	}
?>
