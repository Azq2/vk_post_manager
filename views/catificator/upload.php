<div class="wrapper bord">
	<div class="row header oh">
		<?= $cat['title'] ?>
	</div>
	
	<?php if ($errors): ?>
		<div class="row row-error">
			<?= implode('<br />', $errors) ?>
		</div>
	<?php endif; ?>

	<div class="row">
		<form action="" method="POST" enctype="multipart/form-data">
			<div class="row">
				<label class="lbl">Файл (MP3, макс. 1 мб):</label><br />
				<table>
					<tr>
						<td width="100%" class="m">
							<input type="file" name="file" />
						</td>
						
						<td class="m">
							<input type="submit" class="btn" value="Загрузить" name="do_upload_audio" />
						</td>
					</tr>
				</table>
			</div>
		</form>
	</div>
</div>
