
<div class="row">
	Тип: <?= $type_tabs ?>
</div>

<div class="row">
	Вывод: <?= $output_tabs ?>
</div>

<div class="row">
	Период: <?= $period_tabs ?>
</div>

<?php if (($total_join + $total_leave) > 0): ?>
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
<?php else: ?>
<div class="row grey">
	Нет данных
</div>
<?php endif; ?>

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
		Не заходили (3 мес): <?= number_format($inactive_6m_cnt, 0, ',', ' ') ?>
		(<?= round(($inactive_6m_cnt / $total_cnt) * 100, 1) ?>%)
	</span>
	&nbsp;&nbsp;&nbsp; = &nbsp;&nbsp;&nbsp;
	
	<span class="red">
		<?= number_format(($banned_cnt + $deleted_cnt + $inactive_6m_cnt), 0, ',', ' ') ?>
		(<?= round((($banned_cnt + $deleted_cnt + $inactive_6m_cnt) / $total_cnt) * 100, 1) ?>%)
	</span>
</div>

<style>.main { max-width: 100%; }</style>

<?= $chart ?>
