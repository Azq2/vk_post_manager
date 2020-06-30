<script type="text/javascript" id="suggested_data" data-list="<?= $list ?>" data-gid="<?= $gid ?>">
define('suggested/preload', function () {
	return <?= json_encode($comments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
})
require(['suggested']);
</script>

<div class="row"><?= $tabs ?></div>

<?php if ($load_error): ?>
<div class="row red">
	Ошибка загрузки постов: <?= $load_error ?>
</div>
<?php endif; ?>

<form action="?a=index/settings&return=<?= urlencode($back) ?>&gid=<?= $gid ?>" method="POST" id="group_settings" class="row">
	<div class="info">
		Частота ~<span id="post_cnt">?</span> постов в день. 
		<button class="btn" id="show_freq_settings">Настроить</button>
	</div>
	
	<div id="freq_settings" class="hide">
		<div class="pad_t">
			Публиковать
				с <input type="text" value="<?= $from['hh'] ?>" name="from_hh" size="2" />
				:
				<input type="text" value="<?= $from['mm'] ?>" name="from_mm" size="2" />
				до
				<input type="text" value="<?= $to['hh'] ?>" name="to_hh" size="2" />
				:
				<input type="text" value="<?= $to['mm'] ?>" name="to_mm" size="2" />
		</div>
		
		<div class="pad_t">
			С интервалом
			<button class="btn js-interval_incr" data-key="" data-dir="-5">&nbsp;-&nbsp;</button>
			<input type="text" value="<?= $interval['hh'] ?>" name="hh" size="2" />
			:
			<input type="text" value="<?= $interval['mm'] ?>" name="mm" size="2" />
			<button class="btn js-interval_incr" data-key="" data-dir="5">&nbsp;+&nbsp;</button>
		</div>
		
		<div class="pad_t">
			С девиацией
			<button class="btn js-interval_incr" data-key="deviation" data-dir="-1">&nbsp;-&nbsp;</button>
			<input type="text" value="<?= $deviation['hh'] ?>" name="deviation_hh" size="2" />
			:
			<input type="text" value="<?= $deviation['mm'] ?>" name="deviation_mm" size="2" />
			<button class="btn js-interval_incr" data-key="deviation" data-dir="1">&nbsp;+&nbsp;</button>
			минут
		</div>
		
		<div class="pad_t">
			Отступ до рекламы
			<button class="btn js-interval_incr" data-key="special_post_before" data-dir="-5">&nbsp;-&nbsp;</button>
			<input type="text" value="<?= $special_post_before['hh'] ?>" name="special_post_before_hh" size="2" />
			:
			<input type="text" value="<?= $special_post_before['mm'] ?>" name="special_post_before_mm" size="2" />
			<button class="btn js-interval_incr" data-key="special_post_before" data-dir="5">&nbsp;+&nbsp;</button>
		</div>
		
		<div class="pad_t">
			Отступ после рекламы
			<button class="btn js-interval_incr" data-key="special_post_after" data-dir="-5">&nbsp;-&nbsp;</button>
			<input type="text" value="<?= $special_post_after['hh'] ?>" name="special_post_after_hh" size="2" />
			:
			<input type="text" value="<?= $special_post_after['mm'] ?>" name="special_post_after_mm" size="2" />
			<button class="btn js-interval_incr" data-key="special_post_after" data-dir="5">&nbsp;+&nbsp;</button>
		</div>
		
		<div class="pad_t">
			<button class="btn">Сохранить</button>
		</div>
	</div>
</form>

<?php if ($by_week && $list == 'postponed'): ?>
	<table class="table">
		<tr>
			<?php foreach ($by_week['items'] as $w): ?>
				<th><?= $w['date'] ?></th>
			<?php endforeach; ?>
		</tr>
		<tr>
			<?php foreach ($by_week['items'] as $w): ?>
				<td><?= $w['cnt'] ?></td>
			<?php endforeach; ?>
		</tr>
	</table>
<?php endif; ?>

<div class="row">
	<b>Время последнего поста:</b> <?= $last_post_time ?><br />
	<b>Конец очереди:</b> <?= $last_delayed_post_time ?><br />
	<b>Нужен пост:</b> <?= $next_post_time ?>
	
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

<div id="vk_posts">
	<?php if (!$comments): ?>
		<div class="row center grey">
			Постов ещё нет, но вы держитесь!
		</div>
	<?php endif; ?>
</div>

<div id="vk_posts_error" class="row red hide"></div>

<div id="vk_posts_spinner" class="row center grey">
	<img src="/images/spinner2.gif" alt="" class="m" />
	<span class="m">Загружаем посты...</span>
</div>
