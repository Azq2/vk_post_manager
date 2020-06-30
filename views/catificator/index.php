<div class="wrapper bord">
	<div class="row header cursor">
		Настройки
	</div>
	
	<div class="row">
		<a href="<?= $add_link ?>">
			Добавить категорию
		</a>
	</div>
	
	<div class="row">
		<a href="<?= $edit_messages_link ?>">
			Изменить сообщения бота
		</a>
	</div>
</div>

<?php foreach ($categories as $cat): ?>
	<div class="wrapper bord">
		<div class="row header oh">
			<?= $cat['title'] ?>
			<a href="<?= $cat['edit_link'] ?>" class="right">Изменить</a>
		</div>
		
		<div class="list-link">
			<form action="<?= $cat['upload_link'] ?>" method="POST" enctype="multipart/form-data">
				<label class="lbl">Файл (MP3, макс. 1 мб):</label><br />
				<table>
					<tr>
						<td width="100%" class="m">
							<input type="file" name="file[]" multiple="multiple" />
						</td>
						
						<td class="m">
							<input type="submit" class="btn" value="Загрузить" name="do_upload_audio" />
						</td>
					</tr>
				</table>
			</form>
		</div>
		
		<?php foreach ($cat['tracks'] as $track): ?>
			<div class="list-link js-track oh relative" data-url="<?= $track['url'] ?>?<?= microtime(true) ?>">
				<div style="background:rgba(0, 150, 136, 0.2);position:absolute;top:0;left:0;right:0;bottom:0;width:0%;z-index:-1;" class="js-track_progress"></div>
				
				<a href="<?= $track['delete_link'] ?>" class="red right m">Удалить</a>
				
				<img src="/images/play_audio.svg" alt="" width="16" height="16" class="js-track_play m cursor" />
				<img src="/images/pause_audio.svg" alt="" width="16" height="16" class="js-track_pause hide m cursor" />
				
				<span class="m grey"><?= $track['filename'] ?> (<?= $track['duration'] ?> сек., 
					<?php if ($track['volume']['mean'] < -20): ?>
						<b class="red">
					<?php else: ?>
						<b class="green">
					<?php endif; ?>
					<?= $track['volume']['mean'] ?: '?' ?> dB</b>)
				</span>
			</div>
		<?php endforeach; ?>
	</div>
<?php endforeach; ?>

<script>require(['bots/catificator'])</script>
