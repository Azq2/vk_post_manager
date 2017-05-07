
<div class="js-search_user_wrap">
	<input type="text" class="js-search_user" style="width: 70%" value="" placeholder="Поиска пользователя" />
	<button style="width: 20%">Поиск!</button>
	<div class="js-search_user_list"></div>
</div>

<div class="oh row">
	<div class="oh">
		<div class="left post-preview">
			<?= $user['avatar'] ?>
		</div>
		<div class="oh">
			<?= $user['widget'] ?>
		</div>
		Всего в соо: <?= $time_in_comm ?>
	</div>
	<br />
	<table class="table">
		<tr>
			<th>Лайки</th>
			<th>Репосты</th>
			<th>Репосты (30 дней)</th>
			<th>Комы</th>
			<th>Комы (30 дней)</th>
		</tr>
		<tr>
			<td><?= $likes ?></td>
			<td><?= $reposts ?></td>
			<td><?= $reposts30 ?></td>
			<td><?= $comments ?></td>
			<td><?= $comments30 ?></td>
		</tr>
	</table>
	<br />
	<table class="table">
		<tr>
			<th>Дата</th>
			<th>Что сделал</th>
		</tr>
		<?php foreach ($joins as $join): ?>
		<tr class="type_<?= $join['type'] ?>">
			<td>
				<?= display_date($join['time']) ?>
			</td>
			<td>
				<?= $join['type'] ? 'вступил' : 'покинул' ?>
				<?= $join['time_in_comm'] ? ' (пробыл: '.count_time($join['time_in_comm']).')' : '' ?>
			</td>
		</tr>
		<?php endforeach; ?>
	</table>
	
</div>