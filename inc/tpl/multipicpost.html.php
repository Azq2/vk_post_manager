<link rel="stylesheet" href="https://cdn.jsdelivr.net/emojione/2.2.7/assets/css/emojione.min.css"/>
<link rel="stylesheet"  href="https://cdn.rawgit.com/mervick/emojionearea/master/dist/emojionearea.min.css" />
<script src="https://cdn.jsdelivr.net/emojione/2.2.7/lib/js/emojione.min.js"></script>
<script src="https://cdn.rawgit.com/mervick/emojionearea/master/dist/emojionearea.min.js"></script>
<script type="text/javascript" src="i/multipicpost.js?<?= time(); ?>"></script>

<div class="row" style="display: none" id="upload_error_wrap">
	<div class="red" id="upload_error"></div>
	<input type="submit" class="btn js-do_upload_btn" value="Понадеяться на авось!" />
	<input type="submit" class="btn js-do_upload_btn" value="Пропустить эту дичь!" data-skip="1" />
</div>
<div class="row js-file_state_upload center" style="display: none">
	<a href="">Загрузить ещё</a>
</div>
<div class="row js-file_state_none">
	<label class="lbl">Выбери файл (или несколько через зажатый Ctrl):</label><br />
	<input type="file" name="file" id="file_input" multiple="1" />
</div>
<div id="selected_files"></div>
<div class="row" id="do_upload_btn_wrap" style="display: none">
	<input type="submit" class="btn js-do_upload_btn" value="Загрузить!!!1" id="" />
</div>
