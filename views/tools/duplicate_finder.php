<div class="wrapper bord">
	<div class="row header cursor">
		Поиск баянов
	</div>
	
	<div class="row">
		<label class="lbl">Ссылка на фото:</label><br />
		<input type="text" name="url" autocomplete="off" value="" placeholder="https://vk.com/catlist?z=photo-94594114_457279099%2Falbum-94594114_00%2Frev" id="photo_url" />
	</div>
	
	<div class="row grey hide" id="queue_status">
		
	</div>
	
	<div class="row">
		<button class="btn" id="do_search">
			<img src="/i/img/spinner.gif" alt="" class="m js-spinner hide" /> 
			<span class="m">Найти посты</span>
		</button>
	</div>
</div>

<div id="results"></div>

<script>require(['duplicate_finder'])</script>
