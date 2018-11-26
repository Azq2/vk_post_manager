
<script type="text/javascript">require(['grabber'])</script>

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
			<?php if ($include_list): ?>
				<div class="pad_b">
					<span class="m">Выводим только эти:</span>
					<?php foreach ($include_list as $s): ?>
						<img src="<?= $s['icon'] ?>" width="16" height="16" alt="<?= $s['type'] ?>" class="m" />
						<a href="<?= $s['url'] ?>" target="_blank" class="m"><?= $s['name'] ?></a>
						<b class="red js-grabber_filter_delete cursor" title="Удалить" data-id="<?= $s['key'] ?>">(x)</b><!--
						--><?= $s != end($include_list) ? ',' : '' ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			
			<?php if ($exclude_list): ?>
				<div class="pad_b">
					<span class="m">Выводим всё, кроме этих:</span>
					<?php foreach ($exclude_list as $i => $s): ?>
						<img src="<?= $s['icon'] ?>" width="16" height="16" alt="<?= $s['type'] ?>" class="m" />
						<a href="<?= $s['url'] ?>" target="_blank" class="m"><?= $s['name'] ?></a>
						<b class="red js-grabber_filter_delete cursor" title="Удалить" data-id="<?= $s['key'] ?>">(x)</b><!--
						--><?= $s != end($exclude_list) ? ',' : '' ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			
			<select class="js-grabber_filter_select">
				<option value="">- Фильтр по корованам -</option>
				<?php foreach ($sources as $i => $s): ?>
					<?php if ($s['enabled']): ?>
						<option class="soc-item-<?= strtolower($s['type']) ?>" value="<?= $s['key'] ?>">
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
	<a href="<?= $add_url ?>">
		<div class="row header cursor">
			Мои корованы
		</div>
	</a>
</div>

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
