
<script type="text/javascript" src="https://vk.com/js/api/openapi.js?143" async="async"></script>

<link rel="stylesheet" href="//cdn.jsdelivr.net/emojione/2.2.7/assets/css/emojione.min.css"/>
<link rel="stylesheet"  href="i/lib/emojionearea.css" />

<script src="//cdn.jsdelivr.net/emojione/2.2.7/lib/js/emojione.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/Shuffle/4.1.1/shuffle.min.js"></script>
<script src="i/lib/emojionearea.js"></script>

<script type="text/javascript" src="i/grabber.js?<?= time(); ?>"></script>

<div class="hide"
	data-gid="<?= $gid ?>"
	data-sort="<?= htmlspecialchars($sort) ?>"
	data-mode="<?= htmlspecialchars($mode) ?>"
	data-content-filter="<?= htmlspecialchars($content_filter) ?>"
	data-vk-app-id="<?= VK_APP_ID ?>"
	data-sources="<?= htmlspecialchars(json_encode($sources_ids)) ?>"
	data-blacklist="<?= htmlspecialchars(json_encode($blacklist)) ?>"
	id="grabber_data"></div>

<div class="wrapper">
	<div class="row">
		Режим работы: <?= $mode_tabs ?>
	</div>
	<div class="row">
		Показать посты: <?= $content_tabs ?>
	</div>
</div>

<?php if ($mode == 'external'): ?>
<div class="wrapper bord">
	<div class="row header cursor" onclick="$('#form').toggleClass('hide'); return false">
		Добавить корован
	</div>

	<div class="row<?= $sa == 'add' ? '' : ' hide' ?>" id="form">
		<?php if ($form_error): ?>
			<div class="pad_b">
				<div class="row-error row">
					<?= $form_error ?>
				</div>
			</div>
		<?php endif; ?>
		
		<form action="<?= $form_action ?>" method="POST">
			<div>
				<label class="lbl">Адрес корована, который грабить будем:</label><br />
				<input type="text" name="url" autocomplete="off" value="<?= htmlspecialchars($form_url) ?>" placeholder="https://vk.com/mokrie.kiski" /><br />
			</div>
			
			<div class="pad_t">
				<button class="btn">Добавить</button>
			</div>
		</form>
	</div>
</div>

<div class="wrapper bord" id="sources_ids">
	<div class="row header cursor" onclick="$('#list').toggleClass('hide'); return false">
		Мои корованы
	</div>

	<div id="list" class="<?= !$sources_ids || !$sources || $sa == 'list' ? '' : ' hide' ?>">
		<?php if (!$sources): ?>
			<div class="row center grey">
				Необходимо добавить хотя бы один корован, что бы начать грабить!
			</div>
		<?php endif; ?>
		
		<?php if (!$sources_ids && $sources): ?>
			<div class="row row-error center">
				Что бы граббить, нужно включить хотя бы один корован!
			</div>
		<?php endif; ?>

		<?php if (count($sources) >= 25): ?>
			<div class="row row-error center">
				На текущий момент можно использовать ДО 25 корованов одновременно. <br />
				Все, что не влезли - не используются. <br />
				¯ \ _ (ツ) _ / ¯
			</div>
		<?php endif; ?>
		
		<?php foreach ($sources as $i => $s): ?>
			<div class="row oh<?= $i >= 24 ? ' deleted' : '' ?>">
				<?php if ($s['type'] == 'OK'): ?>
					<img src="https://ok.ru/favicon.ico" width="16" height="16" alt="<?= $s['type'] ?>" class="m" />
				<?php elseif ($s['type'] == 'VK'): ?>
					<img src="https://vk.com/favicon.ico" width="16" height="16" alt="<?= $s['type'] ?>" class="m" />
				<?php endif; ?>
				
				<a href="<?= $s['url'] ?>" target="_blank" class="m"><?= $s['name'] ?></a>
				
				<div class="right">
					<?php if (!$s['enabled']): ?>
						<a href="<?= $s['on_url'] ?>" class="green m">Вкл</a>&nbsp;&nbsp;
					<?php else: ?>
						<a href="<?= $s['off_url'] ?>" class="red m">Выкл</a>&nbsp;&nbsp;
					<?php endif; ?>
					<a href="<?= $s['delete_url'] ?>" class="red m" onclick="return confirm('Точна????')">Удалить</a>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</div>
<?php endif; ?>

<div id="pagenav">
	
</div>

<?php if ($sources_ids && ($sources || $mode == 'internal')): ?>
<div class="wrapper">
	<div class="row oh">
		<div class="left">
			<?= $sort_tabs ?>
		</div>
		<div class="right">
			<input type="text" size="6" value="0" id="post_offset" class="m" />
			<button class="btn m" id="post_offset_btn">
				<img src="i/img/anchor.svg" width="16" height="16" />
			</button>
		</div>
	</div>
</div>

<div class="wrapper">
	<div id="vk_oauth" class="row hide">
		<a href="#vk_oauth" class="js-vk_oauth">
			<img src="https://vk.com/favicon.ico" width="16" height="16" alt="<?= $s['type'] ?>" class="m" />
			<span class="m">Войди в ВК, что бы грабить илитные корованы</span>
		</a>
	</div>
	
	<div id="vk_oauth_ok" class="row hide">
		<a href="#vk_oauth" class="js-vk_oauth" data-exit="1">
			<img src="https://vk.com/favicon.ico" width="16" height="16" alt="<?= $s['type'] ?>" class="m" />
			<span class="m">Выйти с грабителя <span id="vk_oauth_name"></span></span>
		</a>
	</div>
	
	<div id="ok_oauth" class="row hide">
		<a href="#ok_oauth" class="js-ok_oauth">
			<img src="https://ok.ru/favicon.ico" width="16" height="16" alt="<?= $s['type'] ?>" class="m" />
			<span class="m">Войди в OK, что бы грабить аццтойные корованы</span>
		</a>
	</div>
	
	<div class="row center grey" id="garbber_init_spinner">
		<img src="//s.spac.me/i/spinner2.gif" width="16" height="16" alt="" class="m" />
		<span class="m">Продаём душу аццкой сотоне...</span>
	</div>
</div>

<div class="wrapper" id="grabber_posts">
	
</div>

<div class="wrapper hide" id="garbber_posts_spinner">
	<div class="row center grey">
		<img src="//s.spac.me/i/spinner2.gif" width="16" height="16" alt="" class="m" />
		<span class="m">Ищем ближайшие корованы... <span id="grabber_offset">дохуя</span> из <span id="grabber_total">нихуя</span></span>
	</div>
</div>

<?php endif; ?>
