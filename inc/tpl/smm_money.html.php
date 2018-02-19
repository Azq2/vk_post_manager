<div class="wrapper">
	<div class="row">
		<form action="?a=smm_money" method="POST">
			<input type="submit" name="do" class="btn" value="Совершить выплату" />
		</form>
	</div>
	
	<?php foreach ($history as $row): ?>
	
	<div class="row">
		<?= date("Y-m-d H:i:s", $row->time) ?> -&gt;
		<?= date("Y-m-d H:i:s", $row->last_time) ?>
		<b class="green"><?= number_format($row->sum, 2, ',', ' ') ?> р.</b>
	</div>
	
	<?php endforeach; ?>
</div>
