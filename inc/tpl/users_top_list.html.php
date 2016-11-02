
<style>.main { max-width: 900px; }</style>

<div class="rows">
	<?= $comm_tabs ?>
</div>

<div class="rows">
	<?= $tabs ?>
</div>

<h3>Топ по Комментариям</h3>
<table>
	<tr>
		<th>#</th>
		<th></th>
		<th>Чел</th>
		<th>Комов</th>
		<th>В соо</th>
	</tr>
	<?php foreach ($stat['comments'] as $n => $row): ?>
		<?php $user = $row['user_id'] > 0 ? $users[$row['user_id']] : NULL; ?>
		<?php $user_w = $row['user_id'] > 0 ? vk_user_widget($user) : NULL; ?>
		<?php $join = $row['user_id'] > 0 ? @$join_stat[$row['user_id']] : NULL; ?>
		<tr>
			<td><?= $n + 1 ?></td>
			<?php if ($row['user_id'] > 0): ?>
				<td>
					<?= $user_w['avatar'] ?>
				</td>
				<td>
					<?= $user_w['widget'] ?>
				</td>
			<?php else: ?>
				<td></td>
				<td>
					<a target="_blank" href="https://vk.com/public<?= -$row['user_id'] ?>">Сообщество #<?= -$row['user_id'] ?></a>
				</td>
			<?php endif; ?>
			<td><?= $row['c'] ?></td>
			<td>
				<?php if ($join): ?>
					<?= $join['type'] ? 
						'<span style="color:green">'.count_time(time() - $join['time']).'</span>' : 
						'<span style="color:red">'.count_time(time() - $join['time']).'</span>, <b style="color:purple">'.display_date($join['time']).'</b>';
					?>
				<?php else: ?>
					старожил
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
</table>

<h3>Топ по Лайкам</h3>
<table>
	<tr>
		<th>#</th>
		<th>Чел</th>
		<th>Лайков</th>
		<th>В соо</th>
	</tr>
	<?php foreach ($stat['likes'] as $n => $row): ?>
		<?php $user = $row['user_id'] > 0 ? @$users[$row['user_id']] : NULL; ?>
		<?php $user_w = $row['user_id'] > 0 ? vk_user_widget($user) : NULL; ?>
		<?php $join = $row['user_id'] > 0 ? @$join_stat[$row['user_id']] : NULL; ?>
		<tr>
			<td><?= $n + 1 ?></td>
			<?php if ($row['user_id'] > 0): ?>
				<td>
					<?= $user_w['avatar'] ?>
				</td>
				<td>
					<?= $user_w['widget'] ?>
				</td>
			<?php else: ?>
				<td></td>
				<td>
					<a target="_blank" href="https://vk.com/public<?= -$row['user_id'] ?>">Сообщество #<?= -$row['user_id'] ?></a>
				</td>
			<?php endif; ?>
			<td><?= $row['c'] ?></td>
			<td>
				<?php if ($join): ?>
					<?= $join['type'] ? 
						'<span style="color:green">'.count_time(time() - $join['time']).'</span>' : 
						'<span style="color:red">'.count_time(time() - $join['time']).'</span>, <b style="color:purple">'.display_date($join['time']).'</b>';
					?>
				<?php else: ?>
					старожил
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
</table>

<h3>Топ по Репостам</h3>
<table>
	<tr>
		<th>#</th>
		<th>Чел</th>
		<th>Репостнул</th>
		<th>Репостов</th>
		<th>Лайков</th>
		<th>Комов</th>
		<th>В соо</th>
	</tr>
	<?php foreach ($stat['reposts'] as $n => $row): ?>
		<?php $user = $row['user_id'] > 0 ? $users[$row['user_id']] : NULL; ?>
		<?php $user_w = $row['user_id'] > 0 ? vk_user_widget($user) : NULL; ?>
		<?php $join = $row['user_id'] > 0 ? @$join_stat[$row['user_id']] : NULL; ?>
		<tr>
			<td><?= $n + 1 ?></td>
			<?php if ($row['user_id'] > 0): ?>
				<td>
					<?= $user_w['avatar'] ?>
				</td>
				<td>
					<?= $user_w['widget'] ?>
				</td>
			<?php else: ?>
				<td></td>
				<td>
					<a target="_blank" href="https://vk.com/public<?= -$row['user_id'] ?>">Сообщество #<?= -$row['user_id'] ?></a>
				</td>
			<?php endif; ?>
			<td><?= $row['c'] ?></td>
			<td><?= $row['reposts'] ?></td>
			<td><?= $row['likes'] ?></td>
			<td><?= $row['comments'] ?></td>
			<td>
				<?php if ($join): ?>
					<?= $join['type'] ? 
						'<span style="color:green">'.count_time(time() - $join['time']).'</span>' : 
						'<span style="color:red">'.count_time(time() - $join['time']).'</span>, <b style="color:purple">'.display_date($join['time']).'</b>';
					?>
				<?php else: ?>
					старожил
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
</table>