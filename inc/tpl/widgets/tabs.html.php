<?php foreach ($tabs as $tab): ?>
	<a href="<?= $tab['url'] ?>" class="tab<?php if ($tab['active']): ?> tab-active<?php endif; ?>"><!--
		--><?= $tab['title'] ?><!--
	--></a>
	<?php if (!$tab['last']): ?> | <?php endif; ?>
<?php endforeach; ?>
