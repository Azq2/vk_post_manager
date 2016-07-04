<div class="row"><?= $comm_tabs ?></div>
<div class="row"><?= $tabs ?></div>

<form action="?a=settings&return=<?= urlencode($back) ?>" method="POST" id="group_settings" class="row">
	<div class="info">
		Частота ~<span id="post_cnt">0</span> постов в день. 
	</div>
	
	Публиковать
		с <input type="text" value="<?= $from['hh'] ?>" name="from_hh" size="2" />:<input type="text" value="<?= $from['mm'] ?>" name="from_mm" size="2" />
		до
		<input type="text" value="<?= $to['hh'] ?>" name="to_hh" size="2" />:<input type="text" value="<?= $to['mm'] ?>" name="to_mm" size="2" />
	<br />
	
	С интервалом
	<button class="btn js-interval_incr" data-dir="-1">&nbsp;-&nbsp;</button>
	<input type="text" value="<?= $interval['hh'] ?>" name="hh" size="2" />:<input type="text" value="<?= $interval['mm'] ?>" name="mm" size="2" />
	<button class="btn js-interval_incr" data-dir="1">&nbsp;+&nbsp;</button>
	<br />
	<button class="btn js-btn_save hide">Сохранить</button>
</form>

<?php if ($invalid_interval_cnt > 0): ?>
<div class="row row-error">
	Внимание! Обнаружено <?= $invalid_interval_cnt ?> постов с неверным интервалом!<br />
	<?php if ($filter != 'accepted'): ?>
		<a href="<?= $postponed_link ?>">Перейти к списку.</a>
	<?php else: ?>
		<button class="btn btn-delete js-convert">Сконвертировать в новый интервал?</button>
	<?php endif; ?>
</div>
<?php endif; ?>

<div class="row">
	<b>Время последнего поста:</b> <?= $last_post_time ?><br />
	<b>Конец очереди:</b> <?= $last_delayed_post_time ?>
	
	<div class="progress">
		<div class="progress-item" style="width:<?= 
			100 - min(100, 
				max(0, 
					round(($last_delayed_post_time_unix - time()) / (3600 * 24 * 3), 4) * 100
				)
			)
		?>%"></div>
	</div>
</div>

<?php if ($comments): ?>
<?php
	foreach ($comments as $c)
		echo $c;
?>
<?php else: ?>
<div class="row">
	Нет постов. 
</div>
<?php endif; ?>

<script type="text/javascript">var VK_POST_DATA = <?= $json ?>;</script>