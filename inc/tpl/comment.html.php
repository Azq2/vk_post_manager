<div class="oh row js-post<?= $invalid ? ' row-error' : '' ?>" data-id="<?= $gid ?>_<?= $id ?>" id="post_<?= $gid ?>_<?= $id ?>" data-id="<?= $id ?>">
	<div id="post_inner_<?= $gid ?>_<?= $id ?>">
		<div class="oh">
			<div class="left post-preview">
				<?= $user['avatar'] ?>
			</div>
			<div class="oh">
				<span class="time">
					<span class="m"><?= $date ?></span>
				</span>
				<?= $user['widget'] ?>
				
				<?php if ($invalid): ?>
					<span class="red">(<?= $invalid > 0 ? '+'.$invalid : $invalid ?>)</span>
				<?php endif; ?>
				
				<br />
				<div class="post-text">
					<?php if ($post_type == 'suggest'): ?>
					<a href="#" class="js-post_edit m right">
						<img src="http://s.spac.me/i/edit_info.png" alt="" class="m" />
					</a>
					<?php endif; ?>
					<?= $text ?>
				</div>
				<div class="post-text_edit hide">
					<textarea style="width: 100%; box-sizing: border-box;" rows="10" name="xuj"></textarea>
				</div>
				
				<?php if ($attachments): ?>
				<div class="post-attaches">
					<?php if ($geo): ?>
					<div class="post-attach js-attach_geo">
						<?php if ($post_type == 'suggest'): ?>
						<a href="#" style="float: right; padding: 10px" class="js-attach_delete" data-id="geo">
							<img src="http://s.spac.me/i/remove.png" alt="" />
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
						
						<div class="post-attach js-attach_<?= htmlspecialchars($att_id) ?>">
						<?php if ($post_type == 'suggest'): ?>
							<a href="#" style="float: right; padding: 10px" class="js-attach_delete" data-id="<?= htmlspecialchars($att_id) ?>">
								<img src="http://s.spac.me/i/remove.png" alt="" />
							</a>
						<?php endif; ?>
						<?php if ($att->type == 'photo'): ?>
							<a href="<?= $att->photo->photo_604 ?>" target="_blank">
								<img src="<?= $att->photo->photo_130 ?>" alt="" />
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
						</div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
			</div>
		</div>
		
		<div class="js-comment_btns pad_t">
		<?php if ($post_type == 'suggest'): ?>
			<button class="btn js-post_accept">В очередь</button>
			<button class="btn btn-green js-toggle_btn js-anon_switch" data-state="1">Анон</button>
			<div class="right">
				<button class="btn btn-delete js-post_delete js-anon_switch">Удалить</button>
			</div>
		<?php elseif ($post_type == 'postpone'): ?>
			<button class="btn" onclick="window.open('https://m.vk.com/wall-<?= $gid ?>_<?= $id ?>?act=edit&post_from=postponed&wide=1','','width=640,height=480,top=0,left='+($(window).innerWidth()-640)/2);return false;">Ред</button>
			<!-- <button class="btn js-post_force_add">Запостить</button> -->
			<div class="right">
				<button class="btn btn-delete js-post_delete">Удалить</button>
			</div>
		<?php endif; ?>
		</div>
		<div class="js-comment_msg hide"></div>
	</div>
	<div id="post_stub_<?= $gid ?>_<?= $id ?>"></div>
</div>
