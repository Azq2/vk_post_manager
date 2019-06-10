
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
