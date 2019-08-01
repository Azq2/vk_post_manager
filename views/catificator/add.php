
<div class="wrapper bord">
	<div class="row header">
		<?php if ($is_edit): ?>
			Изменить категорию
		<?php else: ?>
			Добавить категорию
		<?php endif; ?>
	</div>
	
	<?php if ($errors): ?>
		<div class="row row-error">
			<?= implode('<br />', $errors) ?>
		</div>
	<?php endif; ?>

	<form action="" method="POST">
		<div class="row">
			<label class="lbl">Название:</label><br />
			<input type="text" name="title" autocomplete="off" value="<?= htmlspecialchars($cat['title']) ?>" placeholder="" required="required" />
		</div>
		
		<div class="row">
			<label class="lbl">Триггеры:</label><br />
			<div class="js-triggers">
				<?php foreach ($triggers as $trigger): ?>
					<div class="pad_b js-trigger oh">
						<table>
							<td width="100%">
								<input type="text" name="trigger[]" autocomplete="off" value="<?= htmlspecialchars($trigger) ?>" placeholder="Слово" />
							</td>
							<td>
								<input type="submit" class="btn btn-delete js-trigger_delete" value="&nbsp;x&nbsp;" />
							</td>
						</table>
					</div>
				<?php endforeach; ?>
			</div>
			
			<input type="submit" class="btn js-trigger_add" value="Добавить ещё триггер" />
		</div>
		
		<div class="row oh">
			<input type="submit" class="btn btn-green" name="save" value="Сохранить" />
			<input type="submit" class="btn btn-delete right" name="delete" value="Удалить" onclick="return confirm('Точно удалить???');" />
		</div>
	</form>
</div>

<script>require(['bots/catificator'])</script>
