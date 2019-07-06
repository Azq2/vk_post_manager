
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
		</div>
	</div>
<?php endforeach; ?>
