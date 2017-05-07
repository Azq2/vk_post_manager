
<link rel="stylesheet" href="https://cdn.jsdelivr.net/emojione/2.2.7/assets/css/emojione.min.css"/>
<link rel="stylesheet"  href="https://cdn.rawgit.com/mervick/emojionearea/master/dist/emojionearea.min.css" />
<script src="https://cdn.jsdelivr.net/emojione/2.2.7/lib/js/emojione.min.js"></script>
<script src="https://cdn.rawgit.com/mervick/emojionearea/master/dist/emojionearea.min.js"></script>
<script type="text/javascript" src="i/main.js?<?= time(); ?>"></script>

<div class="row"><?= $tabs ?></div>

<form action="?a=settings&return=<?= urlencode($back) ?>&gid=<?= $gid ?>" method="POST" id="group_settings" class="row">
	<div class="info">
		Частота ~<span id="post_cnt">0</span> постов в день. 
	</div>
	
	<div class="pad_t">
		Публиковать
			с <input type="text" value="<?= $from['hh'] ?>" name="from_hh" size="2" />:<input type="text" value="<?= $from['mm'] ?>" name="from_mm" size="2" />
			до
			<input type="text" value="<?= $to['hh'] ?>" name="to_hh" size="2" />:<input type="text" value="<?= $to['mm'] ?>" name="to_mm" size="2" />
	</div>
	
	<div class="pad_t">
		С интервалом
		<button class="btn js-interval_incr" data-dir="-1">&nbsp;-&nbsp;</button>
		<input type="text" value="<?= $interval['hh'] ?>" name="hh" size="2" />:<input type="text" value="<?= $interval['mm'] ?>" name="mm" size="2" />
		<button class="btn js-interval_incr" data-dir="1">&nbsp;+&nbsp;</button>
	</div>
	
	<div class="pad_t">
		<button class="btn js-btn_save hide">Сохранить</button>
	</div>
</form>

<?php if ($invalid_interval_cnt > 0): ?>
<div class="row row-error center">
	Внимание! Обнаружено <?= $invalid_interval_cnt ?> постов с неверным интервалом!<br />
	<?php if ($filter != 'accepted'): ?>
		<div class="pad_t">
			<a href="<?= $postponed_link ?>">Перейти к списку.</a>
		</div>
	<?php else: ?>
		<div id="convert_info" class="pad_t hide"></div>
		<div class="pad_t">
			<button class="btn btn-delete js-convert" data-gid="<?= $gid ?>">
				<img src="i/img/spinner.gif" alt="" class="m js-spinner hide" />
				<span class="m">Сконвертировать в новый интервал?</span>
			</button>
		</div>
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
		echo '<div class="wrapper">'.$c.'</div>';
?>
<?php else: ?>
<div class="row">
	Нет постов. 
</div>
<?php endif; ?>

<script type="text/javascript">var VK_POST_DATA = <?= $json ?>;</script>