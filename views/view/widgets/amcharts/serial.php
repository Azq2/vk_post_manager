<div id="chart_<?= $id ?>" style="width: 100%; height: <?= $height ?>px"></div>
<div id="chart_<?= $id ?>_legend" style="width: 100%"></div>

<script type="text/javascript">
require(["amcharts.serial"], function (AmCharts) {
	AmCharts.makeChart("chart_<?= $id ?>", <?= json_encode($config, JSON_UNESCAPED_UNICODE) ?>);
});
</script>
