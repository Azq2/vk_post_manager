
<script type="text/javascript">require(['grabber'])</script>

<div class="hide"
	data-gid="<?= $gid ?>"
	data-sort="<?= htmlspecialchars($sort) ?>"
	data-mode="<?= htmlspecialchars($mode) ?>"
	data-content-filter="<?= htmlspecialchars($content_filter) ?>"
	data-vk-app-id="<?= 0 ?>"
	data-sources="<?= htmlspecialchars(json_encode($sources_ids)) ?>"
	data-include="<?= htmlspecialchars(json_encode($include)) ?>"
	data-exclude="<?= htmlspecialchars(json_encode($exclude)) ?>"
	data-interval="<?= htmlspecialchars($interval) ?>"
	data-list-type="<?= htmlspecialchars($list_type) ?>"
	data-date-from="<?= htmlspecialchars($date_from) ?>"
	data-date-to="<?= htmlspecialchars($date_to) ?>"
	data-source-type="<?= htmlspecialchars($source_type) ?>"
	id="grabber_data"></div>

<div class="wrapper">
	<div class="row">
		Режим работы: <?= $mode_tabs ?>
	</div>
	
	<div class="row">
		Показать посты: <?= $content_tabs ?>
	</div>
	
	<div class="row">
		Период: <?= $date_tabs ?>
	</div>
	
	<div class="row">
		Источник: <?= $source_type_tabs ?>
	</div>
	
	<?php if ($interval == 'custom'): ?>
		<div class="row">
			<input type="text" id="date_from" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="datepicker" size="8" placeholder="Начало" /> -
			<input type="text" id="date_to" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="datepicker" size="8" placeholder="Конец" />
			<button class="btn js-grabber_interval_set">OK</button>
		</div>
	<?php endif; ?>
	
	<div class="row">
		Список: <?= $list_type_tabs ?>
	</div>
	
	<?php if ($mode == 'external'): ?>
		<div class="row">
			<a href="#" class="js-open_grabber_src_filter">
				<?php if ($source_filter_type != 'none'): ?><b><?php endif; ?>
				Фильтр по корованам
				<?php if ($source_filter_type != 'none'): ?></b><?php endif; ?>
			</a>
			
			<div id="grabber_src_filter" class="wrapper bord hide">
				<div class="row header cursor">
					Фильтр по корованам
				</div>
				
				<div class="row">
					Тип фильтра:
					<label>
						<input type="radio" name="src_filter_type" value="include"
							<?php if ($source_filter_type == 'none' || $source_filter_type == 'include'): ?> checked<?php endif; ?> /> Только эти
					</label>
					&nbsp;
					<label>
						<input type="radio" name="src_filter_type" value="exclude"
							<?php if ($source_filter_type == 'exclude'): ?> checked<?php endif; ?> /> Все, кроме этих
					</label>
				</div>
				
				<?php foreach ($sources as $i => $s): ?>
					<label class="row" style="display: inline-block;width: 20em;overflow: hidden;white-space: nowrap;text-overflow: ellipsis;">
						<input type="checkbox"
							name="src_filter"
							<?php if (in_array($s['key'], $source_filter_list)): ?>
								checked="checked"
							<?php endif; ?>
							value="<?= $s['key'] ?>" class="m" />
						<img src="/images/grabber/icon/<?= $s['type'] ?>.png" width="16" height="16" alt="<?= $s['type'] ?>" class="m" />
						<span class="m"><?= $s['name'] ?></span>
					</label>
				<?php endforeach; ?>
				
				<div class="row">
					<button class="btn js-grabber_apply_src_filter">Применить</button>
					<button class="btn btn-delete js-grabber_reset_src_filter">Сбросить</button>
				</div>
			</div>
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
				<img src="images/anchor.svg" width="16" height="16" />
			</button>
		</div>
	</div>
</div>

<div class="wrapper">
	<div class="row center grey" id="garbber_init_spinner">
		<img src="images/spinner2.gif" width="16" height="16" alt="" class="m" />
		<span class="m">Продаём душу аццкой сотоне...</span>
	</div>
</div>

<div class="wrapper" id="grabber_posts">
	
</div>

<div class="wrapper hide" id="garbber_posts_spinner">
	<div class="row center grey">
		<img src="images/spinner2.gif" width="16" height="16" alt="" class="m" />
		<span class="m">Ищем ближайшие корованы...</span>
	</div>
</div>

<?php endif; ?>
