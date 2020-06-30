
<div class="wrapper bord">
	<div class="row header">
		Настройки
	</div>
	
	<?php if ($error_settings): ?>
		<div class="row row-error">
			<?= $error_settings ?>
		</div>
	<?php endif; ?>

	<form action="" method="POST">
		<div class="oh">
			<div style="display: inline-block; width: 345px">
				<div class="row">
					<label class="lbl">Баллов за лайк:</label><br />
					<input type="text" name="cost_likes" autocomplete="off" value="<?= $cost_likes ?>" placeholder="0" required="required" size="3" />
				</div>
			</div>
			
			<div style="display: inline-block; width: 345px">
				<div class="row">
					<label class="lbl">Баллов за коммент:</label><br />
					<input type="text" name="cost_comments" autocomplete="off" value="<?= $cost_comments ?>" placeholder="0" required="required" size="3" />
				</div>
			</div>
			
			<div style="display: inline-block; width: 345px">
				<div class="row">
					<label class="lbl">Статистика за период (дней):</label><br />
					<input type="text" name="days" autocomplete="off" value="<?= $days ?>" placeholder="0" required="required" size="3" />
				</div>
			</div>
			
			<div style="display: inline-block; width: 345px">
				<div class="row">
					<label class="lbl">Кол-во тайлов:</label><br />
					<input type="text" name="tiles_n" autocomplete="off" value="<?= $tiles_n ?>" placeholder="0" required="required" size="3" />
				</div>
			</div>
		</div>
		
		<div class="row">
			<label class="lbl">Заголовок блока:</label><br />
			<textarea type="text" name="title" class="emojionearea-source" readonly="readonly" rows="1"><?= $title ?></textarea>
		</div>
		
		<div class="row">
			<label class="lbl">Заголовок каждого тайла:</label><br />
			<textarea type="text" name="tile_title" class="emojionearea-source" readonly="readonly" rows="1"><?= $tile_title ?></textarea>
		</div>
		
		<div class="row">
			<label class="lbl">Описание каждого тайла:</label><br />
			<textarea type="text" name="tile_descr" class="emojionearea-source" readonly="readonly" rows="1"><?= $tile_descr ?></textarea>
		</div>
		
		<div class="row">
			<label class="lbl">Название ссылки каждого тайла:</label><br />
			<textarea type="text" name="tile_link" class="emojionearea-source" readonly="readonly" rows="1"><?= $tile_link ?></textarea>
		</div>
		
		<div id="macroses" class="hide">
			<table>
				<tr>
					<td class="row grey">{name}</td>
					<td class="row">- имя</td>
				</tr>
				<tr>
					<td class="row grey">{surname}</td>
					<td class="row">- фамилия</td>
				</tr>
				<tr>
					<td class="row grey">{likes}</td>
					<td class="row">- кол-во лайков</td>
				</tr>
				<tr>
					<td class="row grey">{comments}</td>
					<td class="row">- кол-во комментов</td>
				</tr>
				<tr>
					<td class="row grey">{balls}</td>
					<td class="row">- кол-во баллов</td>
				</tr>
			</table>
		</div>
		
		<div class="row oh">
			<input type="submit" class="btn" value="Сохранить" name="do_save_settings" />
			<button onclick="$('#macroses').toggleClass('hide'); return false" class="right btn">Макросы</button>
		</div>
	</form>
</div>

<div class="wrapper bord">
	<div class="row header">
		Картинки шаблоны
	</div>
	
	<?php if ($error_upload): ?>
		<div class="row row-error">
			<?= $error_upload ?>
		</div>
	<?php endif; ?>

	<div class="row">
		<?php foreach ($images as $n => $img): ?>
			<a href="<?= $img['src'] ?: "/images/transparent.gif" ?>" target="_blank" style="width: <?= $img['width'] ?>px; max-width: 30%; display: inline-block">
				<div class="aspect" style="padding-top: <?= round($img['height'] / $img['width'] * 100, 2) ?>%">
					<img src="<?= $img['src'] ?: "/images/transparent.gif" ?>" alt="" class="preview" />
					<span style="position: absolute;top: 0;left: 0;background: #cddae7;width: 1em;text-align: center;padding: 5px;"><?= $n + 1 ?></span>
				</div>
			</a>
		<?php endforeach; ?>
	</div>
	
	<form action="" method="POST" enctype="multipart/form-data">
		<div class="row">
			<label class="lbl">Файл (PNG, размеры: <?= implode(", ", $allowed_sizes) ?>):</label><br />
			<select name="file_id">
				<?php foreach ($images as $n => $img): ?>
					<option value="<?= $n ?>">Картинка #<?= $n + 1 ?></option>
				<?php endforeach; ?>
			</select>
			<input type="file" name="file" />
		</div>
		
		<div class="row">
			<input type="submit" class="btn" value="Загрузить" name="do_upload_images" />
		</div>
	</form>
</div>

<div class="wrapper">
	<?php foreach ($users_list as $n => $u): ?>
		<div class="row wrapper<?= $u['blacklisted'] ? ' deleted' : '' ?>">
			<div class="oh">
				<div class="left post-preview relative">
					<img src="<?= $u['avatar'] ?: "/images/transparent.gif" ?>" alt="" width="50" height="50" />
				</div>
				<div class="oh">
					<b class="post-author post-author-VK m bord">&nbsp;<?= $n + 1 ?>&nbsp;</b>
					<a href="<?= $u['url'] ?>" target="_blank" class="m">
						<b class="post-author post-author-VK"><?= $u['name'] ?></b>
					</a>
				</div>
				
				<div class="pad_t oh">
					<img src="/images/diamond.svg" class="m" width="16" height="16">
					<span class="darkblue m"><?= $u['points'] ?></span>
					
					&nbsp;&nbsp;&nbsp;
					
					<img src="/images/like.svg" class="m" width="16" height="16">
					<span class="darkblue" m><?= $u['likes'] ?></span>
					
					&nbsp;&nbsp;&nbsp;
					
					<img src="/images/comment.svg" class="m" width="16" height="16">
					<span class="darkblue m"><?= $u['comments'] ?></span>
					
					<?php if ($u['blacklisted']): ?>
						<a href="<?= $u['unblacklist_url'] ?>">
							<button class="btn btn-yellow right m">
								Восстановить
							</button>
						</a>
					<?php else: ?>
						<a href="<?= $u['blacklist_url'] ?>">
							<button class="btn btn-delete right m">
								ЧС
							</button>
						</a>
					<?php endif; ?>
				</div>
			</div>
		</div>
	<?php endforeach; ?>
</div>

<script>require(['vk_widget/top_users'])</script>
