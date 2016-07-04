<?php if ($success): ?>
<div class="success">
	Настройки сохранены
</div>
<?php endif; ?>

<form action="" method="POST" id="group_settings">
	<div class="info">
		Будет опубликовано <span id="post_cnt">0</span> постов в день. 
	</div>
	
	<b>Период:</b><br />
		с <input type="text" value="<?= $from['hh'] ?>" name="from_hh" size="2" />:<input type="text" value="<?= $from['mm'] ?>" name="from_mm" size="2" />
		до
		<input type="text" value="<?= $to['hh'] ?>" name="to_hh" size="2" />:<input type="text" value="<?= $to['mm'] ?>" name="to_mm" size="2" />
	<br />
	
	<b>Интервал:</b><br />
		<button class="btn js-interval_incr" data-dir="-1">&nbsp;-&nbsp;</button>
		<input type="text" value="<?= $interval['hh'] ?>" name="hh" size="2" />:<input type="text" value="<?= $interval['mm'] ?>" name="mm" size="2" />
		<button class="btn js-interval_incr" data-dir="1">&nbsp;+&nbsp;</button>
	<br />
	<br />
	<button class="btn">Сохранить</button>
</form>

<script type="text/javascript" src="i/js/settings.js"></script>
