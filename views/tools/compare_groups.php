<div class="wrapper bord">
	<div class="row header cursor">
		Сравниватель сообществ
	</div>
	
	<div class="row">
		<label class="lbl">ID нашего сообщества:</label><br />
		<input type="text" name="url" autocomplete="off" value="" placeholder="94594114" />
	</div>
	
	<div class="row">
		<label class="lbl">ID чужого сообщества:</label><br />
		<input type="text" name="url" autocomplete="off" value="" placeholder="94594114" />
	</div>
	
	<div class="row">
		<button class="btn">Сравнить</button>
	</div>
</div>

<?php foreach ($compares as $compare): ?>
	<div class="wrapper bord">
		<div class="row header cursor">
			<?= $compare['title'] ?>
		</div>
		
		<div class="row">
			<table class="table" style="width: 100%">
			<?php foreach ($compare['tables'] as $table): ?>
				<tr>
					<th>
						<?= $table['title'] ?>
					</th>
					
					<th>
						Наше
					</th>
					
					<th>
						Чужое
					</th>
					
					<th>
						Δ
					</th>
				</tr>
				<?php foreach ($table['rows'] as $row): ?>
					<tr>
						<td>
							<?= $row['title'] ?>
						</td>
						
						<td>
							<?= number_format($row['value'][0], 0, '.', ' ') ?>
							<?php if ($row['pct']): ?>
								&nbsp;
								<small class="grey"><?= round($row['pct'][0], 1) ?>%</small>
							<?php endif; ?>
						</td>
						
						<td>
							<?= number_format($row['value'][1], 0, '.', ' ') ?>
							<?php if ($row['pct']): ?>
								&nbsp;
								<small class="grey"><?= round($row['pct'][1], 1) ?>%</small>
							<?php endif; ?>
						</td>
						
						<td>
							<?php if ($row['type'] == 'pct'): ?>
								<?php if ($row['diff'] > 0): ?>
									<span class="<?= $row['negative'] ? 'red' : 'green' ?>">+<?= round($row['diff'], 2) ?>%</span>
								<?php elseif ($row['diff'] < 0): ?>
									<span class="<?= $row['negative'] ? 'green' : 'red' ?>"><?= round($row['diff'], 2) ?>%</span>
								<?php endif; ?>
							<?php elseif ($row['type'] == 'abs'): ?>
								<?php if ($row['diff'] > 0): ?>
									<span class="<?= $row['negative'] ? 'red' : 'green' ?>">+<?= number_format($row['diff'], 0, '.', ' ') ?></span>
								<?php elseif ($row['diff'] < 0): ?>
									<span class="<?= $row['negative'] ? 'green' : 'red' ?>"><?= number_format($row['diff'], 0, '.', ' ') ?></span>
								<?php endif; ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endforeach; ?>
			</table>
		</div>
	</div>
<?php endforeach; ?>
