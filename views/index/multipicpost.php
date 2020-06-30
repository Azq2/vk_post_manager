<script type="text/javascript">require(['multipicpost'])</script>

<div class="wrapper js-page_spinner_switch">
	<div class="row center grey">
		<img src="images/spinner2.gif" width="16" height="16" alt="" class="m" />
		<span class="m">Загрузка...</span>
	</div>
</div>

<div class="wrapper js-page_spinner_switch hide">
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
		<input type="file" name="file" id="file_input" multiple="1" accept="image/*" />
	</div>
	<div id="selected_files"></div>
	<div class="row" id="do_upload_btn_wrap" style="display: none">
		<input type="submit" class="btn js-do_upload_btn" value="Загрузить!!!1" id="" />
	</div>
</div>
