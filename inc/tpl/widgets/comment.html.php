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
			<div class="js-upload_form pad_t" data-action="?a=vk_upload&amp;gid=<?= $gid ?>" data-id="vk_upload">
				<div class="js-upload_input"></div>
				<div class="js-upload_files pad_t hide"></div>
			</div>
		</div>
		
		<div class="js-post_attaches post-attaches<?php if (!$attachments): ?> hide<?php endif; ?>">
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
			
			<?php if ($attachments): ?>
				<?php foreach ($attachments as $att): ?>
					<?= Tpl::render("widgets/attach.html", [
						'att'	=> $att, 
						'list'	=> $list
					]) ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
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
