<?php foreach ($users as $user): ?>
<div class="oh row">
	<div class="oh">
		<div class="left post-preview">
			<?= $user['avatar'] ?>
		</div>
		<div class="oh">
			<?= $user['widget'] ?>
		</div>
	</div>
</div>
<?php endforeach; ?>