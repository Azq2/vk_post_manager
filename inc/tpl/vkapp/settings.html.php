
<div class="wrapper bord">
	<div class="row header cursor">
		Приложения
	</div>
	
	<?php foreach ($apps as $app): ?>
		<div class="row">
			<a href="/?a=vkapp/settings&amp;sa=edit&amp;group_id=<?= $app['group_id'] ?>"><?= htmlspecialchars($app['name']) ?></a><br />
			<div class="pad_t">
				<span class="grey">Группа:</span>
				<a href="https://vk.com/public<?= $app['group_id'] ?>" target="_blank"><?= $app['group_id'] ?></a>
			</div>
			<div class="pad_t">
				<span class="grey">Приложение:</span> <?= $app['app'] ?>
			</div>
		</div>
	<?php endforeach; ?>
</div>
