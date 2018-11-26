<?php foreach ($items as $tab): ?>
	<a class="tab<?= $tab['active'] ? ' tab-active' : '' ?>" href="<?= $tab['url'] ?>">
		<?= $tab['name'] ?>
	</a>
	<?php if (!$tab['last']): ?> | <?php endif; ?>
<?php endforeach; ?>
