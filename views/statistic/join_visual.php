
<div class="row">
	Тип: <?= $type_tabs ?>
</div>

<div class="row">
	Вывод: <?= $output_tabs ?>
</div>

<div class="row">
	Период: <?= $period_tabs ?>
</div>

<div class="row">
	<span class="green">
		Вступили: <?= number_format($total_join, 0, ',', ' ') ?>
		(<?= round(($total_join / ($total_join + $total_leave)) * 100, 1) ?>%)
	</span>
	&nbsp;&nbsp;&nbsp; - &nbsp;&nbsp;&nbsp;
	
	<span class="red">
		Покинули: <?= number_format($total_leave, 0, ',', ' ') ?>
		(<?= round(($total_leave / ($total_join + $total_leave)) * 100, 1) ?>%)
	</span>
	&nbsp;&nbsp;&nbsp; = &nbsp;&nbsp;&nbsp;
	
	<span class="<?= $total_join - $total_leave > 0 ? 'green' : 'red' ?>">
		<?= ($total_join - $total_leave > 0 ? 'Профит: +' : 'Дефицит: ').number_format($total_join - $total_leave, 0, ',', ' ') ?>
		(<?= round(($total_join - $total_leave) / ($total_join + $total_leave) * 100, 1) ?>%)
	</span>
</div>

<div class="row">
	<span class="">
		Забанены: <?= number_format($banned_cnt, 0, ',', ' ') ?>
		(<?= round(($banned_cnt / $total_cnt) * 100, 1) ?>%)
	</span>
	&nbsp;&nbsp;&nbsp; + &nbsp;&nbsp;&nbsp;
	
	<span class="">
		Удалены (совсем): <?= number_format($deleted_cnt, 0, ',', ' ') ?>
		(<?= round(($deleted_cnt / $total_cnt) * 100, 1) ?>%)
	</span>
	&nbsp;&nbsp;&nbsp; + &nbsp;&nbsp;&nbsp;
	
	<span class="">
		Не заходили (пол года): <?= number_format($inactive_6m_cnt, 0, ',', ' ') ?>
		(<?= round(($inactive_6m_cnt / $total_cnt) * 100, 1) ?>%)
	</span>
	&nbsp;&nbsp;&nbsp; = &nbsp;&nbsp;&nbsp;
	
	<span class="red">
		<?= number_format(($banned_cnt + $deleted_cnt + $inactive_6m_cnt), 0, ',', ' ') ?>
		(<?= round((($banned_cnt + $deleted_cnt + $inactive_6m_cnt) / $total_cnt) * 100, 1) ?>%)
	</span>
</div>

<div id="chartdiv" style="width: 100%; height: 600px"></div>
<div id="chart_legend" style="width: 100%"></div>

<style>.main { max-width: 100%; }</style>

<script type="text/javascript">
require(["amcharts.serial"], function (AmCharts) {
	var chart = AmCharts.makeChart("chartdiv", {
		"type": "serial",
		"theme": "light",
		"marginRight": 80,
		"autoMarginOffset": 20,
		"marginTop": 7,
		"dataProvider": <?= json_encode($stat) ?>,
		"valueAxes": [{
			"axisAlpha": 0.2,
			"dashLength": 1,
			"position": "left"
		}],
		"mouseWheelZoomEnabled": true,
		"graphs": <?= json_encode($graphs) ?>,
		"chartScrollbar": {
			"autoGridCount": true,
			"graph": "g1",
			"scrollbarHeight": 40
		},
		"chartCursor": {
		   "limitToGraph":"g1"
		},
		"categoryField": "date",
		"categoryAxis": {
			"parseDates": true,
			"axisColor": "#DADADA",
			"dashLength": 1,
			"minorGridEnabled": true
		},
		"export": {
			"enabled": true
		}
	});
});
</script>
	
