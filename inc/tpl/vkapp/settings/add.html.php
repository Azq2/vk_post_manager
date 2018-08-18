
<div class="wrapper bord">
	<div class="row header cursor">
		<?= $is_edit ? 'Редактировать приложение' : 'Добавить приложение' ?>
	</div>
	
	<div class="row">
		<form action="<?= $form_action ?>" method="POST">
			<?php if ($error): ?>
				<div class="pad_b red">
					<?= $error ?>
				</div>
			<?php endif; ?>
			
			<div class="pad_b">
				<label class="lbl">Название:</label><br />
				<input type="text" name="name" value="<?= htmlspecialchars($name) ?>" autocomplete="off" />
				<div class="pad_b"></div>
			</div>
			
			<div class="pad_b">
				<label class="lbl">ID группы (числовой):</label><br />
				<input type="text" name="group_id" value="<?= htmlspecialchars($group_id) ?>" autocomplete="off" />
				<div class="pad_b"></div>
				<div class="row row-yellow">
					Управление &gt; Работа с API &gt; Callback API (на сером фоне написан group_id)
				</div>
			</div>
			
			<div class="pad_b">
				<label class="lbl">Ключ доступа:</label><br />
				<input type="text" name="token" value="<?= htmlspecialchars($token) ?>" autocomplete="off" />
				<div class="pad_b"></div>
				<div class="row row-yellow">
					Управление &gt; Работа с API &gt; Ключи доступа &gt; Создать ключ
				</div>
			</div>
			
			<div class="pad_b">
				<label class="lbl">Строка, которую должен вернуть сервер:</label><br />
				<input type="text" name="handshake" value="<?= htmlspecialchars($handshake) ?>" autocomplete="off" />
				<div class="pad_b"></div>
				<div class="row row-yellow">
					Управление &gt; Работа с API &gt; Callback API (на сером блоке, так и называется)
				</div>
			</div>
			
			<div class="pad_b">
				<label class="lbl">Секретный ключ:</label><br />
				<input type="text" name="secret" value="<?= htmlspecialchars($secret) ?>" autocomplete="off" />
				<div class="pad_b"></div>
				<div class="row row-yellow">
					Управление &gt; Работа с API &gt; Callback API (нужно придумать свой и ввести)<br />
					Например: <?= md5(uniqid('', true).mt_rand().time()) ?>
				</div>
			</div>
			
			<div class="pad_b">
				<label class="lbl">Приложение:</label><br />
				<select name="app">
					<?php foreach ($apps as $app_name): ?>
						<option value="<?= $app_name ?>"<?= $app_name == $app ? ' selected="selected"' : '' ?>>
							<?= $app_name ?>
						</option>
					<?php endforeach; ?>
				</select>
				<div class="pad_b"></div>
			</div>
			
			<div>
				<button class="btn"><?= $is_edit ? 'Сохранить приложение' : 'Добавить приложение' ?></button>
			</div>
		</form>
	</div>
</div>
