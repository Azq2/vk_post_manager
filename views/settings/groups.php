<?php if ($error): ?>
	<div class="wrapper">
		<div class="row row-error">
			<?= $error ?>
		</div>
	</div>
<?php endif; ?>

<div class="wrapper bord">
	<div class="row header">
		Добавить новую группу
	</div>
	
	<form action="" method="POST">
		<div class="row">
			<label class="lbl">Ссылка на группу:</label><br />
			<input type="text" name="url" autocomplete="off" value="<?= $url ?>" placeholder="https://vk.com/catlist" required="required" />
		</div>
		
		<div class="row">
			<input type="submit" class="btn" value="Добавить" name="do_add" />
		</div>
	</form>
</div>

<?php foreach ($groups_list as $group): ?>
	<div class="wrapper bord">
		<div class="row header">
			<a href="<?= $group['url'] ?>" rel="noopener noreferrer" target="_blank">
				<?= $group['name'] ?>
			</a>
		</div>
		
		<form action="" method="POST">
			<input type="hidden" name="id" value="<?= $group['id'] ?>" />
			
			<div class="row">
				<label class="lbl">Название:</label><br />
				<input type="text" name="name" autocomplete="off" value="<?= $group['name'] ?>" placeholder="" required="required" />
			</div>
			
			<div class="row">
				<label class="lbl">Позиция в списке:</label><br />
				<input type="text" name="pos" autocomplete="off" value="<?= $group['pos'] ?>" placeholder="0" size="3" required="required" />
			</div>
			
			<div class="row">
				<label class="lbl">Виджет сообщества:</label><br />
				<select name="widget">
					<?php foreach ($avail_widgets as $k => $name): ?>
						<option value="<?= $k ?>"<?= $k == $group['widget'] ? ' selected="selected"' : '' ?>>
							<?= $name ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			
			<div class="row">
				<input type="submit" class="btn" value="Сохранить" name="do_save" />
				<input type="submit" class="btn btn-delete" value="Удалить" name="do_delete" onclick="return confirm('Точно удалить???');" />
			</div>
		</form>
	</div>
<?php endforeach; ?>
