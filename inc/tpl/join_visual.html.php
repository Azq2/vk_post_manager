
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/amcharts/3.13.0/amcharts.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/amcharts/3.13.0/serial.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/amcharts/3.13.0/themes/light.js"></script>

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

<div id="chartdiv" style="width: 100%; height: 600px"></div>
<div id="chart_legend" style="width: 100%"></div>

<style>.main { max-width: 100%; }</style>

<script type="text/javascript">
var chart = AmCharts.makeChart("chartdiv", {
	"type":				"serial",
	"theme":			"light",
	"pathToImages":		"https://cdnjs.cloudflare.com/ajax/libs/amcharts/3.13.0/images/",
	"dataProvider":		<?= json_encode($stat) ?>,
    "chartScrollbar": {
        "autoGridCount":	true,
        "scrollbarHeight":	40
    },
    "categoryField":		"date",
    "categoryAxis": {
		"parseDates":		true,
		"axisColor":		"#DADADA",
		"dashLength":		1,
		"minorGridEnabled":	true
	},
	"graphs": <?= json_encode($graphs) ?>
});

var chartCursor = new AmCharts.ChartCursor();
chart.addChartCursor(chartCursor);

var legend = new AmCharts.AmLegend();
legend.align = "center";
legend.markerType = "circle";
chart.addLegend(legend, "chart_legend");

var valueAxis = new AmCharts.ValueAxis();
valueAxis.axisAlpha = 0;
valueAxis.inside = true;
valueAxis.gridAlpha = 0.1;
valueAxis.gridCount = 15;
chart.addValueAxis(valueAxis);

var categoryAxis = chart.categoryAxis;
categoryAxis.parseDates = false;
categoryAxis.dashLength = 1;
categoryAxis.gridAlpha = 0.15;
categoryAxis.axisColor = "#DADADA";
categoryAxis.gridCount = 15;
categoryAxis.autoGridCount = false;
</script>
	