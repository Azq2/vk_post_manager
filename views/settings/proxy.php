<?php foreach ($list as $proxy): ?>
	<div class="wrapper bord">
		<div class="row header">
			<?= $proxy['title'] ?> (<?= $proxy['type'] ?>)
		</div>
		
		<form action="" method="POST">
			<input type="hidden" name="type" value="<?= $proxy['type'] ?>" />
			
			<div class="row grey">
				Проверка SOCKS5:
				<?php if ($proxy['status'] == 'not_set'): ?>
					<span class="red"><?= $proxy['status_text'] ?></span>
				<?php elseif ($proxy['status'] == 'ok'): ?>
					<span class="green"><?= $proxy['status_text'] ?></span>
				<?php elseif ($proxy['status'] == 'error'): ?>
					<span class="red"><?= $proxy['status_text'] ?></span>
				<?php endif; ?>
			</div>
			
			<div class="row">
				<label class="lbl">Host:</label><br />
				<input type="text" name="host" autocomplete="off" value="<?= $proxy['host'] ?>" placeholder="" />
			</div>
			
			<div class="row">
				<label class="lbl">Port:</label><br />
				<input type="text" name="port" autocomplete="off" value="<?= $proxy['port'] ?>" placeholder="" />
			</div>
			
			<div class="row">
				<label class="lbl">Username:</label><br />
				<input type="text" name="login" autocomplete="off" value="<?= $proxy['login'] ?>" placeholder="" />
			</div>
			
			<div class="row">
				<label class="lbl">Password:</label><br />
				<input type="text" name="password" autocomplete="off" value="<?= $proxy['password'] ?>" placeholder="" />
			</div>
			
			<div class="row">
				<label>
					<input type="checkbox" name="enabled" value="1" <?= $proxy['enabled'] ? ' checked="checked"' : '' ?> />
					Включить прокси (иначе BYPASS)
				</label>
			</div>
			
			<div class="row">
				<input type="submit" class="btn" value="Сохранить" name="do_save" />
			</div>
		</form>
	</div>
<?php endforeach; ?>
