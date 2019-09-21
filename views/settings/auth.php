<div class="wrapper">
	<div class="row row-yellow bord">
		Внимание! Настройки общие для всех сообществ.
	</div>
</div>

<div class="wrapper bord">
	<div class="row header cursor">
		OAuth приложения сообщества
	</div>
	
	<?php foreach ($oauth_groups_list as $oauth): ?>
		<div class="row oh">
			<a href="<?= $oauth['oauth_url'] ?>" rel="noopener noreferrer" target="_blank">
				<span <?php if ($oauth['status'] != 'success'): ?>class="deleted"<?php endif; ?>>
					<img src="https://vk.com/favicon.ico" width="16" height="16" alt="VK" class="m" />
					<span class="m"><?= $oauth['title'] ?></span>
				</span>
				
				<?php if ($oauth['status'] == 'not_set'): ?>
					<span class="right red m">Не установлен</span>
				<?php elseif ($oauth['status'] == 'error'): ?>
					<span class="right red m">Ошибка</span>
				<?php elseif ($oauth['status'] == 'expired'): ?>
					<span class="right red m">Нужно обновить</span>
				<?php elseif ($oauth['status'] == 'success'): ?>
					<span class="right green m">OK</span>
				<?php endif; ?>
			</a>
		</div>
	<?php endforeach; ?>
	
	<div class="row">
		<div class="pad_t grey">
			1. <a href="https://vk.com/add_community_app?aid=<?= $groups_app_id ?>" rel="noopener noreferrer" target="_blank">Добавляем приложение</a> в нужное сообщество.
		</div>
		
		<div class="pad_t grey">
			2. Переходим по ссылкам выше и соглашаемся дать авторизацию.
		</div>
		
		<div class="pad_t grey">
			3. Готово!
		</div>
	</div>
</div>

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
					<a href="<?= $oauth['user']['link'] ?>" rel="noopener noreferrer" target="_blank">
						<span class="green">(<?= $oauth['user']['name'] ?>)</span>
					</a>
				<?php else: ?>
					<span class="red">(Не авторизирован)</span>
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
					<a href="<?= $oauth['user']['link'] ?>" rel="noopener noreferrer" target="_blank">
						<span class="green">(<?= $oauth['user']['name'] ?>)</span>
					</a>
				<?php else: ?>
					<span class="red">(Не авторизирован)</span>
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
			<?php elseif ($oauth['form'] == 'COOKIE'): ?>
				<span class="grey">Аккаунт:</span>
				
				<?php if ($oauth['user']): ?>
					<a href="<?= $oauth['user']['link'] ?>" rel="noopener noreferrer" target="_blank">
						<span class="green">(<?= $oauth['user']['name'] ?>)</span>
					</a>
				<?php else: ?>
					<span class="red">(Не авторизирован)</span>
				<?php endif; ?>
				
				<form action="<?= $oauth_action ?>" class="js-oauth_form">
					<div class="js-status_text grey hide pad_t">
						
					</div>
					
					<div class="pad_t">
						<input type="text" name="cookie" value="" placeholder="Значение cookie" size="32" />
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
