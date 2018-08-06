define(['jquery', 'upload', 'emojionearea', 'functions'], function ($) {
//
var tpl = {
	file: function (data) {
		if (data.errors.length)
			return '' + 
				'<div class="row red js-file js-file_error" id="' + data.id + '">' + 
					'<b>' + html_wrap(data.name) + '</b> - ' + data.errors.join('<br />') + 
					'<div style="margin-top: 3px">' + 
						'<input type="submit" class="btn js-file_delete" value="Ой всё" />' + 
					'</div>' + 
				'</div>';
		
		var html = 
			'<div class="row js-file" id="' + data.id + '">' + 
				'<table style="width: 100%"><tr>' + 
					'<td class="left post-preview file-thumb">' + 
						'<div class="file-thumb-center"></div>' + 
						'<img src="i/img/transparent.gif" alt="" id="file_thumb_' + data.id + '" />' + 
					'</td>' + 
					'<td style="width:100%">' + 
						'<b>' + html_wrap(data.name) + '</b> <span class="grey">(' + data.size + ')</span>' + 
						'<div class="js-file_state_none">' + 
							'<div class="pad_t">' + 
								'<textarea name="pizda" id="file_descr_' + data.id + '" rows="3" style="width: 100%; box-sizing: border-box; margin-top: 3px" placeholder="Напиши тут чёто интересное"></textarea>' + 
							'</div>' + 
							'<div class="pad_t">' + 
								'<textarea name="pizda" id="file_caption_' + data.id + '" rows="1" style="width: 100%; box-sizing: border-box; margin-top: 3px" placeholder="Тут описание внутри фоточки"></textarea>' + 
							'</div>' + 
							'<div style="margin-top: 3px">' + 
								'<input type="submit" class="btn js-file_delete" value="Удалить" />' + 
							'</div>' + 
						'</div>' + 
						'<div class="js-file_state_upload" style="display: none">' + 
							'<div id="file_upload_info_' + data.id + '" class="grey">Ожидает загрузки...</div>' + 
							'<div class="progress" style="margin-top: 3px">' + 
								'<div class="progress-item" style="width: 0%" id="file_pct_' + data.id + '"></div>' + 
							'</div>' + 
						'</div>' + 
					'</td>' + 
				'</tr></table>' + 
			'</div>';
		
		return html;
	}
};

var files = [], 
	cur_file, 
	upload = false;

$(function () {
	$('.js-page_spinner_switch').toggleClass('hide');
	
	$('#file_input').on('change', function (e) {
		$('.js-file_error').remove();
		
		$.each(this.files, function (_, blob) {
			var file = {
				id: 'xuj_' + Date.now(), 
				blob: blob
			};
			
			var errors = [];
			if (blob.size > 20 * 1024 * 1024)
				errors.push('Слишком жирный файл <s>как твои ляхи</s> (' + getHumanSize(blob.size) + ' <s>Кг</s>)');
			else if (!/\.(jpg|jpeg|bmp|gif|png)$/i.test(blob.name))
				errors.push('Сейчас бы в 2к16 не знать как выглядит картинка и грузить вместо неё всякую дичь -_-');
			
			$('#selected_files').append(tpl.file({
				id: file.id, 
				name: blob.name, 
				size: getHumanSize(blob.size), 
				errors: errors
			}));
			
			$('#file_descr_' + file.id).emojioneArea({
				pickerPosition: "bottom", 
				filtersPosition: "bottom", 
				autocomplete: false, 
				tonesStyle: "checkbox"
			});
			$('#file_caption_' + file.id).emojioneArea({
				pickerPosition: "bottom", 
				filtersPosition: "bottom", 
				autocomplete: false, 
				inline: true,
				tonesStyle: "checkbox"
			});
			
			if (!errors.length) {
				setTimeout(function () {
					$('#file_thumb_' + file.id).prop("src", createObjectURL(blob));
				}, 0);
				files.push(file);
			}
		});
		checkButtons();
		
		this.value = "";
	});

	$('.js-do_upload_btn').on('click', function (e) {
		e.preventDefault();
		
		var el = $(this);
		
		if (el.data('skip') && files.length) {
			$('#' + files[0].id).remove();
			files.shift();
			
			if (!files.length)
				location.reload();
		}
		
		$('.js-file_error').remove();
		$('.js-file_state_none').hide();
		$('.js-file_state_upload').show();
		$('#upload_error_wrap').hide();
		
		uploadNextFile();
	});

	$('body').on('click', '.js-file_delete', function (e) {
		e.preventDefault();
		var el = $(this), 
			wrap = el.parents('.js-file');
		
		for (var i = 0, l = files.length; i < l; ++i) {
			if (files[i].id == wrap.attr("id")) {
				files.splice(i, 1);
				break;
			}
		}
		wrap.remove();
		checkButtons();
		
		if (!files.length && upload)
			location.reload();
	});
});

function uploadNextFile() {
	if (cur_file || !files.length)
		return;
	
	upload = true;
	
	cur_file = files[0];
	
	var form = new FormData();
	form.append('file', cur_file.blob);
	form.append('message', $('#file_descr_' + cur_file.id).prop("emojioneArea").getText());
	form.append('caption', $('#file_caption_' + cur_file.id).prop("emojioneArea").getText());
	
	var xhr = new XMLHttpRequest();
	xhr.onreadystatechange = function () {
		var file = cur_file;
		var error;
		try {
			if (xhr.readyState == 4) {
				xhr.onreadystatechange = function() { };
				
				var status = 0, statusText = '';
				try { status = xhr.status; } catch (e) { }
				try { statusText = xhr.statusText; } catch (e) { }
				
				if (status >= 200 && status < 300) {
					cur_file = null;
					
					var json;
					try {
						json = JSON.parse(xhr.responseText);
					} catch (e) { }
					
					if (!json) {
						error = "Ошибка разбора Джейсона Стетхема! <br />" + xhr.responseText;
					} else {
						if (json.success) {
							files.shift();
							
							$('#file_upload_info_' + file.id).html('<span class="green">Поставлено в очередь</span><br />' + 
								'<a href="' + json.link + '" target="_blank">Перейти к записи</a>');
							$('#file_pct_' + file.id).parent().remove();
							
							setTimeout(uploadNextFile, 0);
						} else {
							if (json.fatal) {
								$('#' + file.id).html(tpl.file({
									name: file.blob.name, 
									errors: [json.error]
								}));
								files.shift();
								setTimeout(uploadNextFile, 0);
							} else {
								error = json.error;
							}
						}
					}
				} else {
					error = !status ? "Нет интернета! Экскаватор?" : 
						"Http error: " + status + (statusText ? ' (' + statusText + ')' : '');
				}
				
				xhr = null;
			}
		} catch (e) {
			error = (e.stack || e.message) + "";
		}
		
		if (error) {
			$('#upload_error_wrap').show();
			$('#upload_error').html('Не смогли загрузить файл <b>' + file.blob.name + '</b>!<br />' + error);
			$('html, body').scrollTop(0);
			cur_file = null;
		}
	};
	xhr.open("POST", location.href, true);
	try { xhr.withCredentials = true; } catch (e) {  }
	xhr.upload.onprogress = function (e) {
		if (e.lengthComputable) {
			var pct = (e.loaded / e.total) * 100;
			$('#file_pct_' + cur_file.id).css("width", pct.toFixed(2) + '%');
			$('#file_upload_info_' + cur_file.id).html('<b>' + getHumanSize(e.loaded) + '</b> из <b>' + getHumanSize(e.total) + '</b> (' + +pct.toFixed(2) + '%)');
		}
	};
	xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
	$('#file_upload_info_' + cur_file.id).html('Загружаем...');
	xhr.send(form);
}

function checkButtons() {
	$('#do_upload_btn_wrap').toggle(files.length > 0);
}

//
});