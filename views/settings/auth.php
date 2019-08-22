
<?php foreach ($oauth_list as $oauth): ?>
	<div class="wrapper bord">
		<a name="<?= $oauth['type'] ?>"></a>
		
		<div class="row header oh">
			<?= $oauth['title'] ?>
			
			<?php if ($oauth['required']): ?>
				<span class="red right" style="font-weight:normal">обязательный</span>
			<?php else: ?>
				<span class="grey right" style="font-weight:normal">опциональный</span>
			<?php endif; ?>
		</div>
		
		<div class="row">
			<?php if ($oauth['form'] == 'CODE'): ?>
				<a href="<?= $oauth['oauth_url'] ?>" rel="noopener noreferrer" target="_blank">Запросить доступ</a>
				
				<?php if ($oauth['user']): ?>
					<span class="green">(<?= $oauth['user'] ?>)</span>
				<?php else: ?>
					<span class="red">(Не авторизирован)</span>
				<?php endif; ?>
				
				<?php if ($oauth['user']): ?>
					(<?= $oauth['user'] ?>)
				<?php endif; ?>
				
				<form action="<?= $oauth_action ?>" class="js-oauth_form">
					<div class="js-status_text grey hide pad_t">
						
					</div>
					
					<div class="pad_t">
						<input type="text" name="code" value="" placeholder="code" size="32" />
					</div>
					
					<div class="pad_t">
						<input type="hidden" name="type" value="<?= $oauth['type'] ?>" />
						<button class="btn js-oauth_form_submit">
							<img src="i/img/spinner.gif" alt="" class="m js-spinner hide" />
							<span class="m">Войти</span>
						</button>
					</div>
				</form>
			<?php elseif ($oauth['form'] == 'DIRECT'): ?>
				<span class="grey">Аккаунт:</span>
				
				<?php if ($oauth['user']): ?>
					<span class="green"><?= $oauth['user'] ?></span>
				<?php else: ?>
					<span class="red">Не авторизирован</span>
				<?php endif; ?>
				
				<form action="<?= $oauth_action ?>" class="js-oauth_form">
					<div class="js-status_text grey hide pad_t">
						
					</div>
					
					<div class="pad_t">
						<input type="text" name="login" value="" placeholder="Логин" size="32" />
					</div>
					
					<div class="pad_t">
						<input type="password" name="password" value="" placeholder="Пароль" size="32" />
					</div>
					
					<div class="js-captcha_form hide">
						<div class="pad_t">
							<img src="/i/img/transparent.gif" alt="" width="196" height="81" class="bord js-captcha_img" />
						</div>
						
						<div class="pad_t">
							<label class="lbl">Код с картинки:</label><br />
							<input type="text" name="captcha_key" value="" placeholder="Капча" size="8" />
							<input type="hidden" name="captcha_sid" value="" class="js-captcha_sid" />
						</div>
					</div>
					
					<div class="js-sms-2fa_form hide">
						<div class="pad_t">
							<label class="lbl">Код из SMS:</label><br />
							<input type="text" name="2fa_code" value="" placeholder="Код" size="16" />
						</div>
					</div>
					
					<div class="js-code-2fa_form hide">
						<div class="pad_t">
							<label class="lbl">Код из приложения:</label><br />
							<input type="text" name="2fa_code" value="" placeholder="Код" size="16" />
						</div>
						
						<div class="pad_t">
							<label>
								<input type="checkbox" name="force_sms" value="1" />
								Отправить код в SMS
							</label>
						</div>
					</div>
					
					<div class="pad_t">
						<input type="hidden" name="type" value="<?= $oauth['type'] ?>" />
						<button class="btn js-oauth_form_submit">
							<img src="i/img/spinner.gif" alt="" class="m js-spinner hide" />
							<span class="m">Войти</span>
						</button>
					</div>
				</form>
			<?php endif; ?>
			
			<?php foreach ($oauth['help'] as $help): ?>
				<div class="pad_t grey">
					<?= $help ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
<?php endforeach; ?>

<script>require(['settings/auth'])</script>
