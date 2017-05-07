<div class="wrapper">
	<?php if ($ok): ?>
		<div class="row green">
			<b>Сохранено</b>
		</div>
	<?php endif; ?>
	
	<div class="row">
		<a href="<?= $vk_oauth ?>">VK OAuth</a>
		<form action="?" method="GET">
			<input type="text" name="code" value="" placeholder="code" size="32" />
			<input type="hidden" name="state" value="VK" />
			<input type="hidden" name="a" value="oauth" />
			<input type="submit" class="btn" value="GO" />
		</form>
		<div class="pad_t"></div>
		<form action="?" method="GET">
			<input type="text" name="access_token" value="" placeholder="access token" size="32" />
			<input type="hidden" name="type" value="VK" />
			<input type="hidden" name="a" value="oauth" />
			<input type="submit" class="btn" value="GO" />
		</form>
	</div>
	
	<div class="row">
		<a href="<?= $ok_oauth ?>">OK OAuth</a>
		<form action="?" method="GET">
			<input type="text" name="code" value="" placeholder="code" size="32" />
			<input type="hidden" name="state" value="OK" />
			<input type="hidden" name="a" value="oauth" />
			<input type="submit" class="btn" value="GO" />
		</form>
		<div class="pad_t"></div>
		<form action="?" method="GET">
			<input type="text" name="access_token" value="" placeholder="access token" size="32" />
			<input type="hidden" name="type" value="OK" />
			<input type="hidden" name="a" value="oauth" />
			<input type="submit" class="btn" value="GO" />
		</form>
	</div>
</div>