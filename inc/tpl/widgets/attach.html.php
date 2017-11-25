<?php
	if ($att->type == "link") {
		$att_id = $att->type."_".bin2hex($att->link->url);
	} else {
		$att_id = $att->type.$att->{$att->type}->owner_id."_".$att->{$att->type}->id;
	}
?>

<?php if ($att->type != 'photo'): ?>
	<div class="<?= $att->type != 'photo' ? 'post-attach ' : '' ?>js-attach_<?= htmlspecialchars($att_id) ?>">
	<?php if ($list == 'suggests'): ?>
		<a href="#" style="float: right; padding: 10px" class="js-attach_delete" data-id="<?= htmlspecialchars($att_id) ?>">
			<img src="i/img/remove.png" alt="" />
		</a>
	<?php endif; ?>
<?php endif; ?>

<?php if ($att->type == 'photo'): ?>
	<div class="post-pic <?= $att->type != 'photo' ? 'post-attach ' : '' ?>js-attach_<?= htmlspecialchars($att_id) ?>"
			style="padding: 10px 0; display: inline-block; max-width: <?= min(320, $att->photo->width) ?>px; margin: 0 2px;
				width: <?= $att->photo->width >= $att->photo->height ? '50%' : '25%' ?>">
		<a href="<?= $att->photo->photo_604 ?>" target="_blank" target="_blank" class="aspect"
				style="padding-top: <?= $att->photo->height / $att->photo->width * 100 ?>%">
			<img src="<?= $att->photo->photo_604 ?>" alt="" class="preview" />
			<?php if ($list == 'suggests'): ?>
				<span class="post-attach_remove js-attach_delete inl" class="js-attach_delete" data-id="<?= htmlspecialchars($att_id) ?>">
					<img src="i/img/remove_2x.png" alt="" />
				</span>
			<?php endif; ?>
		</a>
	</div>
<?php elseif ($att->type == 'album'): ?>
	<b>Альбом фото:</b> <a href="https://vk.com/<?= $att_id ?>" target="_blank"><?= htmlspecialchars($att->album->title) ?></a><br />
	<a href="https://vk.com/<?= $att_id ?>" target="_blank">
		<img src="<?= $att->album->thumb->photo_130 ?>" alt="" />
	</a>
<?php elseif ($att->type == 'video'): ?>
	<b>Видео:</b> <?= htmlspecialchars($att->video->title) ?><br />
	<img src="<?= $att->video->photo_130 ?>" alt="" /><br />
	<?= htmlspecialchars($att->video->description) ?>
<?php elseif ($att->type == 'doc'): ?>
	<b>Документ:</b> <a href="<?= $att->doc->url ?>" target="_blank"><?= htmlspecialchars($att->doc->title) ?></a><br />
	<?php if (isset($att->video->photo_130)): ?>
		<img src="<?= $att->doc->photo_130 ?>" alt="" />
	<?php endif; ?>
<?php elseif ($att->type == 'audio'): ?>
	<b>MP3:</b> <?= htmlspecialchars($att->audio->artist." - ".$att->audio->title) ?><br />
<?php elseif ($att->type == 'poll'): ?>
	<b>Опрос:</b> <?= htmlspecialchars($att->poll->question) ?><br />
	<b>Анонимный:</b> <?= $att->poll->anonymous ? 'да' : 'нет' ?>
	<ul>
		<?php foreach ($att->poll->answers as $a): ?>
			<li><?= htmlspecialchars($a->text) ?></li>
		<?php endforeach; ?>
	</ul>
<?php elseif ($att->type == 'link'): ?>
	<b>Ссылка:</b> <a href="<?= htmlspecialchars($att->link->url) ?>"><?= htmlspecialchars($att->link->url) ?></a><br />
	<b>Название:</b> <?= htmlspecialchars($att->link->title) ?><br />
	<b>Описание:</b> <?= htmlspecialchars($att->link->description) ?><br />
<?php else: ?>
	<b>Неизвестный аттач</b>
	<pre>
		<?php var_dump($att); ?>
	</pre>
<?php endif; ?>

<?php if ($att->type != 'photo'): ?>
	</div>
<?php endif; ?>