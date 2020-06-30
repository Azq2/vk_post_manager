<div class="wrapper">
	<div class="row">
		Период: <?= $period_tabs ?>
	</div>
</div>

<div class="wrapper">
<?php if (($total_join + $total_leave) > 0): ?>
	<div class="row">
		<span class="green">
			Вступили: <?= number_format($total_join, 0, ',', ' ') ?>
			(<?= round(($total_join / ($total_join + $total_leave)) * 100, 1) ?>%)
		</span>
		&nbsp;&nbsp;&nbsp; - &nbsp;&nbsp;&nbsp;
		
		<span class="red">
			Покинули: <?= number_format($total_leave, 0, ',', ' ') ?>
			(<?= round(($total_leave / ($total_join + $total_leave)) * 100, 1) ?>%)
		</span>
		&nbsp;&nbsp;&nbsp; = &nbsp;&nbsp;&nbsp;
		
		<span class="<?= $total_join - $total_leave > 0 ? 'green' : 'red' ?>">
			<?= ($total_join - $total_leave > 0 ? 'Профит: +' : 'Дефицит: ').number_format($total_join - $total_leave, 0, ',', ' ') ?>
			(<?= round(($total_join - $total_leave) / ($total_join + $total_leave) * 100, 1) ?>%)
		</span>
	</div>
<?php else: ?>
	<div class="row grey">
		Нет данных
	</div>
<?php endif; ?>
</div>

<div class="wrapper">
	<?php foreach ($users_list as $u): ?>
		<?php if ($u['header']): ?>
			<div class="grey center row"><?= $u['header'] ?></div>
		<?php endif; ?>
		
		<div class="row wrapper">
			<div class="oh">
				<div class="left post-preview relative">
					<img src="<?= $u['avatar'] ?: "/images/transparent.gif" ?>" loading="lazy" alt="" width="50" height="50" />
				</div>
				
				<div class="oh">
					<a href="<?= $u['url'] ?>" target="_blank" class="m">
						<b class="post-author post-author-VK"><?= $u['name'] ?></b>
					</a>
				</div>
				
				<div class="oh">
					<div class="pad_t">
						<?php if ($u['type']): ?>
							<?php if ($u['last_join'] && $u['last_join']['joins_cnt'] > 1): ?>
								<span class="blue">ЗАНОВО вступил в <?= $u['time'] ?> (ранее вступал <?= $u['last_join']['joins_cnt'] - 1 ?> раз)</span>
							<?php else: ?>
								<span class="green">Вступил в <?= $u['time'] ?></span>
							<?php endif ?>
						<?php else: ?>
							<?php if ($u['last_join'] && $u['last_join']['joins_cnt'] > 1): ?>
								<span class="red">Покинул в <?= $u['time'] ?> (ранее вступал <?= $u['last_join']['joins_cnt'] ?> раз)</span>
							<?php else: ?>
								<span class="red">Покинул в <?= $u['time'] ?></span>
							<?php endif ?>
						<?php endif; ?>
					</div>
				
					<?php if ($u['last_join'] && !$u['type']): ?>
						<div class="pad_t">
							Пробыл в сообществе: <?= $u['last_join']['time_in_group'] ?>
						</div>
					<?php endif; ?>
					
					<div class="pad_t">
						<img src="/images/like.svg" class="m" width="16" height="16">
						<span class="darkblue" m><?= $u['likes'] ?></span>
						
						&nbsp;&nbsp;&nbsp;
						
						<img src="/images/comment.svg" class="m" width="16" height="16">
						<span class="darkblue m"><?= $u['comments'] ?></span>
					</div>
				</div>
			</div>
		</div>
	<?php endforeach; ?>
</div>
