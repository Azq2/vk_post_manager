
<?php if ($ok): ?>
	<div class="wrapper">
		<div class="row row-blue">
			OAuth успешно обновлен.
		</div>
	</div>
<?php endif; ?>

<?php if ($error): ?>
	<div class="wrapper">
		<div class="row row-error">
			<?= $error ?>
		</div>
	</div>
<?php endif; ?>

<div class="wrapper">
	<div class="row row-yellow bord">
		Внимание! Настройки общие для всех сообществ.
	</div>
</div>

<div class="wrapper bord">
	<div class="row header cursor">
		WEB auth для отбеливания
	</div>
	
	<form action="" method="POST">
		<div class="row grey">
			Аккаунт:
			<?php if ($web_auth): ?>
				<a href="https://vk.com/<?= htmlspecialchars($web_auth['screen_name']) ?>" target="_blank">
					<?= htmlspecialchars($web_auth['real_name']) ?>
				</a>
			<?php else: ?>
				<span class="red">Не авторизирован</span>
			<?php endif; ?>
		</div>
		
		<div class="row">
			<label class="lbl">Логин:</label><br />
			<input type="text" name="login" autocomplete="off" value="<?= htmlspecialchars($login) ?>" placeholder="" required="required" size="32" />
		</div>
		
		<div class="row">
			<label class="lbl">Пароль:</label><br />
			<input type="password" name="password" autocomplete="off" value="<?= htmlspecialchars($password) ?>" placeholder="" required="required" size="32" />
		</div>
		
		<?php if ($web_captcha_url): ?>
			<div class="row">
				<div class="pad_b">
					<img src="<?= $web_captcha_url ?>" alt="" />
				</div>
				<input type="text" name="captcha" autocomplete="off" value="" placeholder="" size="4" />
			</div>
		<?php endif; ?>
		
		<input type="hidden" name="auth_state" value="<?= htmlspecialchars($web_auth_state) ?>" />
		
		<div class="row red">
			Вход только по номеру!!!
		</div>
		
		<div class="row">
			<input type="submit" class="btn" value="Авторизировать" name="do_web_auth" />
		</div>
	</form>
</div>

<div class="wrapper bord">
	<div class="row header cursor">
		OAuth приложения сообщества
	</div>
	
	<?php foreach ($oauth_groups_list as $oauth): ?>
		<div class="row oh">
			<a href="<?= $oauth['oauth_url'] ?>">
				<span <?php if ($oauth['status'] != 'success'): ?>class="deleted"<?php endif; ?>>
					<img src="https://vk.com/favicon.ico" width="16" height="16" alt="VK" class="m" />
					<span class="m"><?= $oauth['title'] ?></span>
				</span>
				
				<?php if ($oauth['status'] == 'not_set'): ?>
					<span class="right red m">Не установлен</span>
				<?php elseif ($oauth['status'] == 'error'): ?>
					<span class="right red m">Ошибка</span>
				<?php elseif ($oauth['status'] == 'expired'): ?>
					<span class="right red m">Нужно обновить</span>
				<?php elseif ($oauth['status'] == 'success'): ?>
					<span class="right green m">OK</span>
				<?php endif; ?>
			</a>
		</div>
	<?php endforeach; ?>
	
	<div class="row">
		<div class="pad_t grey">
			1. <a href="https://vk.com/add_community_app?aid=<?= $groups_app_id ?>" target="_blank">Добавляем приложение</a> в нужное сообщество.
		</div>
		
		<div class="pad_t grey">
			2. Переходим по ссылкам выше и соглашаемся дать авторизацию.
		</div>
		
		<div class="pad_t grey">
			3. Готово!
		</div>
	</div>
</div>

<?php foreach ($oauth_list as $oauth): ?>
	<div class="wrapper bord">
		<div class="row header cursor">
			<?= $oauth['title'] ?>
		</div>
		
		<div class="row">
			<a href="<?= $oauth['oauth_url'] ?>" rel="noopener noreferrer">Начать авторизацию OAuth</a>
			
			<?php if ($oauth['user']): ?>
				(<?= $oauth['user'] ?>)
			<?php endif; ?>
			
			<div class="pad_t"></div>
			
			<form action="?" method="GET">
				<input type="text" name="code" value="" placeholder="code" size="32" />
				<input type="hidden" name="state" value="<?= $oauth['type'] ?>" />
				<input type="hidden" name="a" value="settings/oauth" />
				<input type="submit" class="btn" value="GO" />
			</form>
			
			<div class="pad_t"></div>
			
			<form action="?" method="GET">
				<input type="text" name="access_token" value="" placeholder="access token" size="32" />
				<input type="hidden" name="type" value="<?= $oauth['type'] ?>" />
				<input type="hidden" name="a" value="settings/oauth" />
				<input type="submit" class="btn" value="GO" />
			</form>
			
			<?php foreach ($oauth['help'] as $help): ?>
				<div class="pad_t grey">
					<?= $help ?>
				</div>
			<?php endforeach; ?>
			
			<div class="pad_t grey">
				Или использовать вход из офф. приложения:
			</div>
			
			<?php if ($oauth['oauth_direct'] ?? false): ?>
				<form action="?a=settings/oauth" method="POST">
					<div class="pad_t">
						<input type="text" name="login" value="" placeholder="Логин" size="32" />
					</div>
					
					<div class="pad_t">
						<input type="password" name="password" value="" placeholder="Пароль" size="32" />
					</div>
					
					<div class="pad_t">
						<input type="hidden" name="type" value="<?= $oauth['type'] ?>" />
						<input type="hidden" name="direct" value="1" />
						<input type="submit" class="btn" value="Direct auth" />
					</div>
				</form>
			<?php endif; ?>
		</div>
	</div>
<?php endforeach; ?>
