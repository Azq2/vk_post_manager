<div class="wrapper bord">
	<div class="row header cursor">
		Добавить корован
	</div>

	<div class="row" id="form">
		<?php if ($form_error): ?>
			<div class="pad_b">
				<div class="row-error row">
					<?= $form_error ?>
				</div>
			</div>
		<?php endif; ?>
		
		<form action="<?= $form_action ?>" method="POST">
			<div>
				<label class="lbl">Корован, который грабить будем:</label><br />
				<input type="text" name="url" autocomplete="off" value="<?= htmlspecialchars($form_url) ?>" placeholder="" /><br />
			</div>
			
			<div class="pad_t grey">
				<?php foreach ($sources_types as $type => $data): ?>
					<label>
						<input type="radio" name="type" value="<?= $type ?>" <?= $source_type == $type ? ' checked="checked"' : '' ?> />
						<b><?= $data['title'] ?></b><br />
						<?= $data['descr'] ?>
					</label><br />
				<?php endforeach; ?>
			</div>
			
			<div class="pad_t">
				<button class="btn">Добавить</button>
			</div>
		</form>
	</div>
</div>

<?php if ($sources): ?>
	<div class="wrapper bord">
		<div class="row header cursor">
			Мои корованы
		</div>
		
		<?php foreach ($sources as $i => $s): ?>
			<div class="row oh<?= !$s['enabled'] ? ' deleted' : '' ?>">
				<img src="<?= $s['icon'] ?>" width="16" height="16" alt="<?= $s['type'] ?>" class="m" />
				
				<a href="<?= $s['url'] ?>" target="_blank" class="m"><?= $s['name'] ?></a>
				
				<div class="right">
					<?php if (!$s['enabled']): ?>
						<a href="<?= $s['on_url'] ?>" class="green m">Вкл</a>&nbsp;&nbsp;
					<?php else: ?>
						<a href="<?= $s['off_url'] ?>" class="red m">Выкл</a>&nbsp;&nbsp;
					<?php endif; ?>
					<a href="<?= $s['delete_url'] ?>" class="red m" onclick="return confirm('Точна????')">Удалить</a>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
