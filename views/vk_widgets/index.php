<div class="wrapper bord">
	<div class="row header cursor">
		Виджеты для сообществ
	</div>
	
	<?php foreach ($widgets as $link): ?>
		<div class="row oh">
			<a href="<?= $link['url'] ?>">
				<?= $link['title'] ?>
			</a>
			
			<?php if ($link['installed']): ?>
				<a href="<?= $link['delete_url'] ?>" class="red right">
					Отключить
				</a>
			<?php else: ?>
				<a href="<?= $link['install_url'] ?>" class="green right">
					Установить
				</a>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>
