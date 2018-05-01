
<div class="pad_t"></div>
	
<div class="wrapper bord">
	<div class="row header cursor">
		XujXuj SMM Manager 9000
	</div>
	
	<div class="row" id="form">
		<form action="<?= htmlspecialchars($action) ?>" method="POST">
			<div class="pad_b">
				<label class="lbl">Логин:</label><br />
				<input type="text" name="login" value="<?= htmlspecialchars($login) ?>" autocomplete="off" />
			</div>
			
			<div class="pad_b">
				<label class="lbl">Пароль:</label><br />
				<input type="password" name="password" value="<?= htmlspecialchars($password) ?>" autocomplete="off" />
			</div>
			
			<?php if ($error): ?>
				<div class="pad_b red">
					<?= $error ?>
				</div>
			<?php endif; ?>
			
			<div>
				<button class="btn">Мур?</button>
			</div>
		</form>
	</div>
</div>
