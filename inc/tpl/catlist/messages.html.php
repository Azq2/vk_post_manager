
<link rel="stylesheet" href="//cdn.jsdelivr.net/emojione/2.2.7/assets/css/emojione.min.css"/>
<link rel="stylesheet"  href="i/lib/emojionearea.css" />

<script src="//cdn.jsdelivr.net/emojione/2.2.7/lib/js/emojione.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/Shuffle/4.1.1/shuffle.min.js"></script>
<script src="i/lib/emojionearea.js"></script>

<script type="text/javascript" src="i/catlist.js?<?= time(); ?>"></script>

<div class="row">
	<?= $tabs ?>
</div>

<div class="row">
	<?= $msg_tabs ?>
</div>

<div class="wrapper bord">
	<div class="row header cursor">
		Добавить сообщение
	</div>

	<div class="row" id="form">
		<form action="<?= $form_action ?>" method="POST">
			<div>
				<label class="lbl">Идентификатор сообщения:</label><br />
				<input type="text" name="id" autocomplete="off" />
			</div>
			
			<div class="pad_t">
				<label class="lbl">Текст сообщения:</label><br />
				<textarea name="text" class="js-message_text js-emojiarea"></textarea>
			</div>
			
			<div class="pad_t">
				<button class="btn">Добавить</button>
			</div>
		</form>
	</div>
</div>

<div class="wrapper">
	<div class="row">
		<div>
			<label class="lbl">Склонение по числительному:</label><br />
			<div class="row row-yellow">{some_param_name||Выбран $n кот||Выбрано $n кота||Выбрано $n котов}</div>
		</div>
		
		<div class="pad_t">
			<table class="table" width="100%">
				<tr>
					<th>some_param_name</th>
					<td>Имя перемненой, например money</td>
				</tr>
				<tr>
					<th>$n</th>
					<td>Будет заменено на значение переменной (не обязательно)</td>
				</tr>
			</table>
		</div>
		
		<div class="pad_t">
			<table class="table" width="100%">
				<tr>
					<th>some_param_name=1</th>
					<td>Выбран 1 кот</td>
				</tr>
				<tr>
					<th>some_param_name=2</th>
					<td>Выбрано 2 кота</td>
				</tr>
				<tr>
					<th>some_param_name=6</th>
					<td>Выбрано 6 котов</td>
				</tr>
			</table>
		</div>
	</div>
</div>

<div class="wrapper">
	<div class="row">
		<div>
			<label class="lbl">Склонение игрока по полу:</label><br />
			<div class="row row-yellow">{sex||Он||Она}</div>
		</div>
	</div>
</div>

<div class="wrapper">
	<div class="row">
		<div>
			<label class="lbl">Склонение кота по полу:</label><br />
			<div class="row row-yellow">{cat_sex||Котик||Кошечка}</div>
		</div>
	</div>
</div>

<div class="wrapper">
	<div class="row">
		<label class="lbl">Глобальные параметры:</label><br />
		<table class="table" width="100%">
			<tr>
				<th>{money}</th>
				<td>Обычная валюта</td>
			</tr>
			<tr>
				<th>{bonus}</th>
				<td>"Редкая" валюта</td>
			</tr>
			<tr>
				<th>{food}</th>
				<td>Миска, в %</td>
			</tr>
			<tr>
				<th>{toilet}</th>
				<td>Лоток, в %</td>
			</tr>
			<tr>
				<th>{first_name}</th>
				<td>Имя</td>
			</tr>
			<tr>
				<th>{last_name}</th>
				<td>Фамилия</td>
			</tr>
			<tr>
				<th>{menu}</th>
				<td>Меню</td>
			</tr>
		</table>
	</div>
</div>

<div class="wrapper">
<?php foreach ($messages as $cat => $messages_list): ?>
	<div class="row center"><b><?= $sections[$cat] ?></b></div>
	<?php foreach ($messages_list as $m): ?>
		<div class="row js-message" data-id="<?= $m['id'] ?>">
			<div class="oh">
				<b><?= $m['id'] ?></b>
				<span class="js-status_text hide green right"></span>
			</div>
			<div class="pad_t">
				<textarea name="text" class="js-message_text js-emojiarea"><?= $m['message'] ?></textarea>
			</div>
			<div class="pad_t oh">
				<button class="btn js-message_save">
					<img src="i/img/spinner.gif" alt="" class="m js-spinner hide" />
					<span class="m">Сохранить</span>
				</button>
				<button class="btn btn-delete right js-message_delete" data-id="<?= $m['id'] ?>">
					<img src="i/img/spinner.gif" alt="" class="m js-spinner hide" />
					<span class="m">Удалить</span>
				</button>
			</div>
		</div>
	<?php endforeach; ?>
<?php endforeach; ?>
</div>
