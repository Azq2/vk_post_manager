
<script type="text/javascript">require(['catlist'])</script>

<div class="row">
	<?= $tabs ?>
</div>

<div class="row">
	<?= $type_tabs ?>
</div>

<div class="row">
	<?= $delete_tabs ?>
</div>

<div class="wrapper js-page_spinner_switch">
	<div class="row center grey">
		<img src="//s.spac.me/i/spinner2.gif" width="16" height="16" alt="" class="m" />
		<span class="m">Загрузка...</span>
	</div>
</div>

<div class="wrapper bord js-page_spinner_switch hide" id="shop_type" data-type="<?= $type ?>">
	<div class="row header">
		Добавить товар в магазин
	</div>

	<div class="row" id="form">
		<form action="<?= $form_action ?>" method="POST" class="js-shop_save">
			<div>
				<label class="lbl">Название товара:</label><br />
				<input type="text" value="" name="title" autocomplete="off" />
			</div>
			
			<div class="pad_t">
				<label class="lbl">Описание товара:</label><br />
				<textarea name="description" value="" class="js-message_text js-emojiarea"></textarea>
			</div>
			
			<div class="pad_t">
				<label class="lbl">Цена:</label><br />
				<input type="number" value="" name="price" autocomplete="off" placeholder="0.00" />
			</div>
			
			<?php if ($type == 'food'): ?>
				<div class="pad_t">
					<label class="lbl">Порций:</label><br />
					<input type="number" value="" name="amount" autocomplete="off" placeholder="0" />
				</div>
			<?php endif; ?>
			
			<div class="pad_t">
				<label class="lbl">Фотка товара:</label><br />
				<input type="file" name="photo" accept="image/*" />
			</div>
			
			<div class="pad_t hide js-progress_wrap">
				<div class="progress">
					<div class="progress-item js-progress" style="width:0%"></div>
				</div>
			</div>
			
			<div class="pad_t">
				<button class="btn js-shop_save_btn">
					<img src="i/img/spinner.gif" alt="" class="m js-spinner hide" />
					<span class="m">Добавить</span>
				</button>
			</div>
		</form>
	</div>
</div>

<div class="wrapper js-page_spinner_switch hide">
<?php foreach ($items as $m): ?>
	<div class="row">
		<form action="<?= $form_action ?>" method="POST" class="js-shop_save" data-id="<?= $m->id ?>">
			<div style="max-width: <?= min($m->width, 320) ?>px" class="js-photo_max_width">
				<a class="aspect js-photo_aspect" style="padding-top: <?= $m->height / $m->width * 100 ?>%" href="files/catlist/shop/<?= $m->photo ?>.jpg" target="_blank">
					<img src="files/catlist/shop/<?= $m->photo ?>.jpg" alt="" class="preview js-photo_src" />
				</a>
			</div>
			<div class="oh pad_t">
				<span class="js-status_text hide green right"></span>
				<label class="lbl">Название товара:</label><br />
				<input type="text" value="<?= $m->title ?>" name="title" autocomplete="off" />
			</div>
			<div class="pad_t">
				<label class="lbl">Описание товара:</label><br />
				<textarea name="description" class="js-message_text js-emojiarea"><?= $m->description ?></textarea>
			</div>
			<div class="pad_t">
				<label class="lbl">Цена:</label><br />
				<input type="text" value="<?= $m->price ?>" name="price" autocomplete="off" placeholder="0.00" />
			</div>
			
			<?php if ($type == 'food'): ?>
				<div class="pad_t">
					<label class="lbl">Порций:</label><br />
					<input type="number" value="<?= $m->amount ?>" name="amount" autocomplete="off" placeholder="0" />
				</div>
			<?php endif; ?>
			
			<div class="pad_t">
				<label class="lbl">Фотка товара:</label><br />
				<input type="file" name="photo" accept="image/*" />
			</div>
			<div class="pad_t oh">
				<button class="btn js-shop_save_btn">
					<img src="i/img/spinner.gif" alt="" class="m js-spinner hide" />
					<span class="m">Сохранить</span>
				</button>
				<?php if ($m->deleted): ?>
					<button class="btn btn-cancel right js-shop_delete" data-id="<?= $m->id ?>" data-flag="0">
						<img src="i/img/spinner.gif" alt="" class="m js-spinner hide" />
						<span class="m">Восстановить</span>
					</button>
				<?php else: ?>
					<button class="btn btn-delete right js-shop_delete" data-id="<?= $m->id ?>" data-flag="1">
						<img src="i/img/spinner.gif" alt="" class="m js-spinner hide" />
						<span class="m">Удалить</span>
					</button>
				<?php endif; ?>
			</div>
		</form>
	</div>
<?php endforeach; ?>
</div>
