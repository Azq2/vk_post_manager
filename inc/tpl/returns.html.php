
<div class="rows">
	<?= $comm_tabs ?>
</div>

<table>
	<tr>
		<th>Чел</th>
		<th>Вступил</th>
		<th>Покинул</th>
	</tr>
	<?php foreach ($stat as $n => $row): ?>
		<?php $user = $row['uid'] > 0 ? $users[$row['uid']] : NULL; ?>
		<tr style="color: <?= $row['type'] ? 'green' : 'red' ?>">
			<?php if ($row['uid'] > 0): ?>
				<td>
					<a target="_blank" href="https://vk.com/id<?= $row['uid'] ?>"><?= $user->first_name ?> <?= $user->last_name ?></a>
				</td>
			<?php else: ?>
				<td>
					<a target="_blank" href="https://vk.com/public<?= -$row['uid'] ?>">Сообщество #<?= -$row['uid'] ?></a>
				</td>
			<?php endif; ?>
			<td><?= $row['cnt_join'] ?></td>
			<td><?= $row['cnt_leave'] ?></td>
		</tr>
	<?php endforeach; ?>
</table>
