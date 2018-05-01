
<script type="text/javascript">require(['catlist'])</script>

<div class="row">
	<?= $tabs ?>
</div>

<div class="row">
	<?= $price_tabs ?>
</div>

<div class="wrapper js-page_spinner_switch">
	<div class="row center grey">
		<img src="i/img/spinner2.gif" width="16" height="16" alt="" class="m" />
		<span class="m">Загрузка...</span>
	</div>
</div>

<div class="wrapper bord hide js-page_spinner_switch" id="cats_type" data-type="<?= $type ?>">
	<div class="row header">
		Добавить котейку в приют
	</div>

	<div class="row" id="form">
		<form action="<?= $form_action ?>" method="POST" class="js-cats_save">
			<div>
				<label class="lbl">Имя котейки:</label><br />
				<input type="text" value="" name="name" autocomplete="off" />
			</div>
			
			<div class="pad_t">
				<label class="lbl">Описание котейки:</label><br />
				<textarea name="text" value="" class="js-message_text js-emojiarea"></textarea>
			</div>
			
			<div class="pad_t">
				<label class="lbl">Пол:</label>
				<label><input type="radio" name="sex" value="0" checked="checked" /> Котик</label>
				<label><input type="radio" name="sex" value="1" /> Кошечка</label>
			</div>
			
			<div>
				<label class="lbl">Цена:</label><br />
				<input type="text" value="" name="price" autocomplete="off" placeholder="0.00 - приют" />
			</div>
			
			<div class="pad_t">
				<label class="lbl">Фотка котейки:</label><br />
				<input type="file" name="photo" accept="image/*" />
			</div>
			
			<div class="pad_t hide js-progress_wrap">
				<div class="progress">
					<div class="progress-item js-progress" style="width:0%"></div>
				</div>
			</div>
			
			<div class="pad_t">
				<button class="btn">
					<img src="i/img/spinner.gif" alt="" class="m js-spinner hide" />
					<span class="m">Добавить</span>
				</button>
			</div>
		</form>
	</div>
</div>

<div class="wrapper hide js-page_spinner_switch">
<?php foreach ($cats as $m): ?>
	<div class="row">
		<form action="<?= $form_action ?>" method="POST" class="js-cats_save" data-id="<?= $m['id'] ?>">
			<div style="max-width: <?= min($m['width'], 320) ?>px" class="js-photo_max_width">
				<a class="aspect js-photo_aspect" style="padding-top: <?= $m['height'] / $m['width'] * 100 ?>%" href="files/catlist/cats/<?= $m['photo'] ?>.jpg" target="_blank">
					<img src="files/catlist/cats/<?= $m['photo'] ?>.jpg" alt="" class="preview js-photo_src" />
				</a>
			</div>
			<div class="oh pad_t">
				<span class="js-status_text hide green right"></span>
				<label class="lbl">Имя котейки:</label><br />
				<input type="text" value="<?= $m['name'] ?>" name="name" autocomplete="off" />
			</div>
			<div class="pad_t">
				<label class="lbl">Описание котейки:</label><br />
				<textarea name="text" class="js-message_text js-emojiarea"><?= $m['descr'] ?></textarea>
			</div>
			<div class="pad_t">
				<label class="lbl">Пол:</label>
				<label><input type="radio" name="sex" value="0"<?= $m['sex'] == 0 ? ' checked="checked"' : '' ?> /> Котик</label>
				<label><input type="radio" name="sex" value="1"<?= $m['sex'] == 1 ? ' checked="checked"' : '' ?> /> Кошечка</label>
			</div>
			<div>
				<label class="lbl">Цена:</label><br />
				<input type="text" value="<?= $m['price'] == 0 ? '' : $m['price'] ?>" name="price" autocomplete="off" placeholder="0.00 - приют" />
			</div>
			<div class="pad_t">
				<label class="lbl">Фотка котейки:</label><br />
				<input type="file" name="photo" accept="image/*" />
			</div>
			<div class="pad_t oh">
				<button class="btn js-cats_save_btn">
					<img src="i/img/spinner.gif" alt="" class="m js-spinner hide" />
					<span class="m">Сохранить</span>
				</button>
				<button class="btn btn-delete right js-cats_delete" data-id="<?= $m['id'] ?>">
					<img src="i/img/spinner.gif" alt="" class="m js-spinner hide" />
					<span class="m">Удалить</span>
				</button>
			</div>
		</form>
	</div>
<?php endforeach; ?>
</div>
