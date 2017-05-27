<?php if ($period): ?>
	<div class="grey center row"><?= $period ?></div>
<?php endif; ?>

<div class="row<?=$special ? ' row-yellow' : '' ?><?= ($list == 'suggests' && $post_type == 'postpone') ? ' row-blue' : '' ?> js-post" data-id="<?= $gid ?>_<?= $id ?>" id="post_<?= $gid ?>_<?= $id ?>" data-id="<?= $id ?>">
	<div id="post_inner_<?= $gid ?>_<?= $id ?>">
		<div class="oh">
			<div class="left post-preview">
				<?= $user['avatar'] ?>
			</div>
			<div class="oh">
				<span class="time">
					<span class="m">
						<?= $date ?>
					</span>
				</span>
				<?= $user['widget'] ?>
				
				<a href="https://vk.com/wall-<?= $gid ?>_<?= $id ?>" target="_blank">
					<img src="i/img/external.svg" width="14" height="14" class="m" alt="" />
				</a>
				
				<?php if ($list == 'postponed' && $delta): ?>
					&nbsp;<span class="green m"><?= $delta ?></span>
				<?php endif; ?>
				
				<div class="post-text">
					<?php if ($list == 'suggests'): ?>
					<a href="#" class="js-post_edit m right">
						<img src="//s.spac.me/i/edit_info.png" alt="" class="m" />
					</a>
					<?php endif; ?>
					<div class="emoji">
						<?= $text ?>
					</div>
				</div>
			</div>
		</div>
		
		<div class="post-text_edit hide pad_t">
			<textarea style="width: 100%; box-sizing: border-box;" rows="10" name="xuj"></textarea>
		</div>
		
		<?php if ($attachments): ?>
		<div class="post-attaches">
			<?php if ($geo): ?>
			<div class="post-attach js-attach_geo">
				<?php if ($list == 'suggests'): ?>
				<a href="#" style="float: right; padding: 10px" class="js-attach_delete" data-id="geo">
					<img src="//s.spac.me/i/remove.png" alt="" />
				</a>
				<?php endif; ?>
				GEO: <?= $geo->coordinates ?><br />
				<?= $geo->place->title ?>
			</div>
			<?php endif; ?>
			
			<?php foreach ($attachments as $att): ?>
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
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
	</div>
	
	<?php if ($post_type != 'post'): ?>
		<div class="js-comment_btns pad_t">
		<?php if ($list == 'suggests'): ?>
			<button class="btn js-post_accept">В очередь</button>
			<button class="btn btn-green js-toggle_btn js-anon_switch" data-state="1">Анон</button>
			
			<div class="right">
				<button class="btn btn-delete js-post_delete">&nbsp;x&nbsp;</button>
			</div>
		<?php elseif ($list == 'postponed'): ?>
			<button class="btn" onclick="window.open('https://m.vk.com/wall-<?= $gid ?>_<?= $id ?>?act=edit&post_from=postponed&wide=1','','width=640,height=480,top=0,left='+($(window).innerWidth()-640)/2);return false;">Ред</button>
			<!-- <button class="btn js-post_force_add">Запостить</button> -->
			<div class="right">
				<button class="btn btn-delete js-post_delete">&nbsp;x&nbsp;</button>
			</div>
		<?php endif; ?>
		</div>
	<?php else: ?>
		<div class="grey pad_t">
			Последний добавленный пост
		</div>
	<?php endif; ?>
	
	<?php if ($post_type != 'post' && $scheduled): ?>
		<div class="pad_t green">
			В очереди на постинг
		</div>
	<?php endif; ?>
	
	<div class="js-comment_msg hide"></div>
	<div id="post_stub_<?= $gid ?>_<?= $id ?>"></div>
</div>
