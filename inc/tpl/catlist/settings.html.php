
<script type="text/javascript">require(['catlist'])</script>

<div class="row">
	<?= $tabs ?>
</div>

<div class="wrapper bord">
	<div class="row header cursor">
		Настройки
	</div>

	<div class="row" id="form">
		<form action="<?= $form_action ?>" method="POST">
			<?php foreach ($settings as $key => $d): ?>
				<div class="pad_b">
					<label class="lbl"><?= $d['title'] ?>:</label><br />
					<?php if ($d['type'] == 'int' || $d['type'] == 'float'): ?>
						<input type="text" name="<?= $key ?>" value="<?= htmlspecialchars($d['value']) ?>" autocomplete="off" />
					<?php else: ?>
						<input type="number" name="<?= $key ?>" value="<?= htmlspecialchars($d['value']) ?>" autocomplete="off" />
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
			
			<div>
				<button class="btn">Сохранить</button>
			</div>
		</form>
	</div>
</div>
