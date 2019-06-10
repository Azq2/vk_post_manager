<div class="wrapper bord">
	<div class="row header cursor">
		Настройки
	</div>
	
	<?php if ($is_admin): ?>
		<div class="row">
			<a href="<?= $oauth_url ?>">
				Настройки <b>oauth</b>
			</a>
		</div>
		
		<div class="row">
			<a href="<?= $groups_url ?>">
				Группы ВК
			</a>
		</div>
	<?php endif; ?>
	
	<div class="row">
		<a href="<?= $exit_url ?>">
			Выход (<?= $login ?>)
		</a>
	</div>
</div>
