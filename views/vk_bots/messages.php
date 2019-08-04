<div class="wrapper">
	<div class="row grey center">
		Редактор сообщений для: <?= $type ?>
	</div>
</div>

<div class="wrapper bord">
	<div class="row header">
		Макросы
	</div>
	
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
	
	<div class="row">
		<div>
			<label class="lbl">Склонение юзера по полу:</label><br />
			<div class="row row-yellow">{sex||Он||Она}</div>
			<div class="row row-yellow">Привет, {sex||котик||кошечка}!</div>
		</div>
	</div>
	
	<div class="row">
		<div>
			<label class="lbl">Вывод сообщения в зависимости от участия в группе:</label><br />
			<div class="row row-yellow">{for_member}Выведется только участникам группы{/for_member}</div>
			<div class="row row-yellow">{for_guest}Выведется только гостям группы{/for_guest}</div>
		</div>
	</div>
	
	<div class="row">
		<label class="lbl">Глобальные параметры:</label><br />
		<table class="table" width="100%">
			<tr>
				<th>{first_name}</th>
				<td>Имя</td>
			</tr>
			<tr>
				<th>{last_name}</th>
				<td>Фамилия</td>
			</tr>
		</table>
	</div>
</div>


<div class="wrapper bord">
	<div class="row header">
		Добавить сообщение
	</div>

	<div class="row">
		<form action="<?= $form_action ?>" method="POST" id="add_form">
			<div>
				<label class="lbl">Идентификатор сообщения:</label><br />
				<input type="text" name="id" autocomplete="off" />
			</div>
			
			<div class="pad_t">
				<label class="lbl">Текст сообщения:</label><br />
				<textarea name="text" class="js-emojiarea" rows="3"></textarea>
			</div>
			
			<div class="pad_t">
				<button class="btn">Добавить</button>
			</div>
		</form>
	</div>
</div>

<div class="wrapper">
	<?php foreach ($messages as $m): ?>
		<div class="row js-message" data-id="<?= $m['id'] ?>">
			<div class="oh">
				<b><?= $m['id'] ?></b>
				<span class="js-status_text hide green right"></span>
			</div>
			
			<div class="pad_t">
				<textarea name="text" class="js-message_text js-emojiarea"><?= htmlspecialchars($m['text']) ?></textarea>
			</div>
			
			<div class="pad_t oh">
				<button class="btn js-message_save">
					<img src="i/img/spinner.gif" alt="" class="m js-spinner hide" />
					<span class="m">Сохранить</span>
				</button>
				<a href="<?= $m['delete_link'] ?>" onclick="return confirm('Точно удалить?');">
					<button class="btn btn-delete right" >
						<span class="m">Удалить</span>
					</button>
				</a>
			</div>
		</div>
	<?php endforeach; ?>
</div>

<script>require(['bots/messages_editor'])</script>
