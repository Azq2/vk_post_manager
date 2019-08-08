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
			"parseDates": false,
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
	
