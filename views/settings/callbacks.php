
<?php if ($error): ?>
	<div class="wrapper">
		<div class="row row-error">
			<?= $error ?>
		</div>
	</div>
<?php endif; ?>

<?php foreach ($callbacks_list as $callback): ?>
	<div class="wrapper bord">
		<div class="row header">
			<?= $callback['title'] ?> (<?= $callback['type'] ?>)
		</div>
		
		<form action="" method="POST">
			<input type="hidden" name="type" value="<?= $callback['type'] ?>" />
			
			<div class="row">
				<label class="lbl">URL:</label><br />
				<input type="text" name="url" autocomplete="off" value="<?= $callback['url'] ?>" placeholder="" readonly="readonly" />
			</div>
			
			<div class="row">
				<label class="lbl">Строка, которую должен вернуть сервер::</label><br />
				<input type="text" name="install_ack" autocomplete="off" value="<?= $callback['install_ack'] ?>" placeholder="" required="required" />
			</div>
			
			<div class="row">
				<label class="lbl">Секретный ключ:</label><br />
				<input type="text" name="secret" autocomplete="off" value="<?= $callback['secret'] ?>" placeholder="" required="required" />
			</div>
			
			<div class="row">
				<?php foreach ($callback['help'] as $help): ?>
					<div class="pad_t grey">
						<?= $help ?>
					</div>
				<?php endforeach; ?>
			</div>
			
			<div class="row grey">
				Типы событий:<br />
				<?php foreach ($callback['enable'] as $help): ?>
					<div class="pad_t">
						<?= $help ?>
					</div>
				<?php endforeach; ?>
			</div>
			
			<div class="row">
				<input type="submit" class="btn" value="Сохранить" name="do_save" />
			</div>
		</form>
	</div>
<?php endforeach; ?>
