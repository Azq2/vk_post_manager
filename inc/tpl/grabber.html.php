
<script type="text/javascript">require(['grabber<?= isset($_GET['xuj']) ? 2 : '' ?>'])</script>

<div class="hide"
	data-gid="<?= $gid ?>"
	data-sort="<?= htmlspecialchars($sort) ?>"
	data-mode="<?= htmlspecialchars($mode) ?>"
	data-content-filter="<?= htmlspecialchars($content_filter) ?>"
	data-vk-app-id="<?= VK_APP_ID ?>"
	data-sources="<?= htmlspecialchars(json_encode($sources_ids)) ?>"
	data-include="<?= htmlspecialchars(json_encode($include)) ?>"
	data-exclude="<?= htmlspecialchars(json_encode($exclude)) ?>"
	id="grabber_data"></div>

<div class="wrapper">
	<div class="row">
		Режим работы: <?= $mode_tabs ?>
	</div>
	<div class="row">
		Показать посты: <?= $content_tabs ?>
	</div>
	
	<?php if ($mode == 'external'): ?>
		<div class="row">
			<?php if ($include): ?>
				<div class="pad_b">
					<span class="m">Выводим только эти:</span>
					<?php foreach ($include as $sid): ?>
						<?php if (isset($sources[$sid])): ?>
							<img src="<?= $sources[$sid]['icon'] ?>" width="16" height="16" alt="<?= $sources[$sid]['type'] ?>" class="m" />
							<a href="<?= $sources[$sid]['url'] ?>" target="_blank" class="m"><?= $sources[$sid]['name'] ?></a>
							<b class="red js-grabber_filter_delete cursor" title="Удалить" data-id="<?= $sid ?>">(x)</b><!--
							--><?= $sid != end($include) ? ',' : '' ?>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			<?php elseif ($exclude): ?>
				<div class="pad_b">
					<span class="m">Выводим всё, кроме этих:</span>
					<?php foreach ($exclude as $sid): ?>
						<?php if (isset($sources[$sid])): ?>
							<img src="<?= $sources[$sid]['icon'] ?>" width="16" height="16" alt="<?= $sources[$sid]['type'] ?>" class="m" />
							<a href="<?= $sources[$sid]['url'] ?>" target="_blank" class="m"><?= $sources[$sid]['name'] ?></a>
							<b class="red js-grabber_filter_delete cursor" title="Удалить" data-id="<?= $sid ?>">(x)</b><!--
							--><?= $sid != end($exclude) ? ',' : '' ?>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			
			<select class="js-grabber_filter_select">
				<option value="">- Фильтр по корованам -</option>
				<?php foreach ($sources as $i => $s): ?>
					<?php if ($s['enabled']): ?>
						<option class="soc-item-<?= strtolower($s['type']) ?>" value="<?= $s['type'].'_'.$s['id'] ?>">
							<?= $s['name'] ?>
						</option>
					<?php endif; ?>
				<?php endforeach; ?>
			</select>
			<button class="btn btn-green js-grabber_filter_whitelist" title="Никто, кроме">&nbsp;+&nbsp;</button>
			<button class="btn btn-delete js-grabber_filter_blacklist" title="Все, кроме">&nbsp;-&nbsp;</button>
		</div>
	<?php endif; ?>
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
				<input type="text" name="url" autocomplete="off" value="<?= htmlspecialchars($form_url) ?>" placeholder="https://vk.com/catlist" /><br />
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
		
		<?php foreach ($sources as $i => $s): ?>
			<div class="row oh<?= !$s['enabled'] ? ' deleted' : '' ?>">
				<img src="<?= $s['icon'] ?>" width="16" height="16" alt="<?= $s['type'] ?>" class="m" />
				
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
	<div class="row center grey" id="garbber_init_spinner">
		<img src="i/img/spinner2.gif" width="16" height="16" alt="" class="m" />
		<span class="m">Продаём душу аццкой сотоне...</span>
	</div>
</div>

<div class="wrapper" id="grabber_posts">
	
</div>

<div class="wrapper hide" id="garbber_posts_spinner">
	<div class="row center grey">
		<img src="i/img/spinner2.gif" width="16" height="16" alt="" class="m" />
		<span class="m">Ищем ближайшие корованы...</span>
	</div>
</div>

<?php endif; ?>
