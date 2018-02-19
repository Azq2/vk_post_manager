
<style>.main { max-width: 100%; }</style>

<div id="chartdiv" style="width: 100%; height: 600px"></div>
<div id="chart_legend" style="width: 100%"></div>

<script type="text/javascript">
require(["amcharts.serial"], function (AmCharts) {
	var chart = AmCharts.makeChart("chartdiv", {
		"type": "serial",
		"theme": "none",
		"marginLeft": 20,
		"dataProvider": <?= json_encode($stat) ?>,
		"valueAxes": [{
			"axisAlpha": 0,
			"inside": true,
			"position": "left",
			"ignoreAxisWidth": true
		}],
		"graphs": [{
			"title": "Репостов",
			"balloonText": "Репостов [[value]]",
			"negativeLineColor": "#637bb6",
			"bulletSize": 2,
			"bullet": "round",
			"valueField": "reposts", 
			"hidden": true
		}, {
			"title": "Комментариев",
			"balloonText": "Комментариев [[value]]",
			"lineColor": "green",
			"type": "smoothedLine",
			"bulletSize": 2,
			"bullet": "round",
			"valueField": "comments", 
			"hidden": true
		}, {
			"title": "Новых постов",
			"balloonText": "Новых постов [[value]]",
			"lineColor": "#006699",
			"type": "smoothedLine",
			"bulletSize": 2,
			"bullet": "round",
			"valueField": "posts", 
			"hidden": true
		}, {
			"title": "Лайков",
			"balloonText": "Лайков [[value]]",
			"lineColor": "#996600",
			"type": "smoothedLine",
			"bulletSize": 2,
			"bullet": "round",
			"valueField": "likes", 
			"hidden": true
		}, {
			"title": "Активных юзеров",
			"balloonText": "Активных юзеров [[value]]",
			"lineColor": "#FF0000",
			"type": "smoothedLine",
			"bulletSize": 2,
			"bullet": "round",
			"valueField": "active_users", 
			"hidden": false
		}],
		"chartScrollbar": {},
		"categoryField": "date"
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
});
</script>
	