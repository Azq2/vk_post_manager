define(['jquery'], function ($) {
//
var tpl = {
	select: function () {
		var html = 
			'<label class="lbl">Выбери файл (или несколько через зажатый Ctrl):</label><br />' + 
			'<input type="file" multiple="multiple" name="file" value="" accept="image/*" />';
		return html;
	}, 
	file: function (data) {
		if (data.errors.length)
			return '' + 
				'<div class="pad_t pad_b red js-upload_file js-file_error" id="' + data.id + '">' + 
					'<b>' + html_wrap(data.name) + '</b> - ' + data.errors.join('<br />') + 
					'<div style="margin-top: 3px">' + 
						'<input type="submit" class="btn js-file_delete" value="Ой всё" />' + 
					'</div>' + 
				'</div>';
		
		var html = 
			'<div class="pad_t pad_b js-upload_file" id="' + data.id + '">' + 
				'<table style="width: 100%"><tr>' + 
					'<td class="left post-preview file-thumb">' + 
						'<div class="file-thumb-center"></div>' + 
						'<img src="//s.spac.me/i/transparent.gif" alt="" class="js-file_thumb" />' + 
					'</td>' + 
					//~ '<td style="width:100%">' + 
					//~ '<td style="width:100%">' + 
						'<b class="darkblue">' + html_wrap(data.name) + '</b> <span class="grey">(' + data.size + ')</span>' + 
						'<div>' + 
							'<div class="js-upload_info" class="grey">Ожидает загрузки...</div>' + 
							'<div class="progress" style="margin-top: 3px">' + 
								'<div class="progress-item js-upload_pct" style="width: 0%"></div>' + 
								'<div style="margin-top: 3px">' + 
									'<input type="submit" class="btn js-file_delete" value="Отмена" />' + 
								'</div>' + 
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
	upload;

$(function () {
	$('.js-upload_form').each(function () {
		var el = $(this), 
			input_wrap = el.find('.js-upload_input'), 
			files_wrap = el.find('.js-upload_files');
		
		el.removeClass('js-upload_form');
		
		input_wrap.html(tpl.select());
		
		files_wrap.on('click', '.js-file_delete', function (e) {
			e.preventDefault();
			
			var file = $(this).parents('.js-upload_file');
			
			var new_files = [];
			for (var i = 0; i < files.length; ++i) {
				if (files[i].id == file.prop("id")) {
					if (files[i].xhr) {
						files[i].xhr.abort();
						files[i].xhr = null;
						files[i].el.trigger('file_upload_end');
						cur_file = null;
					}
				} else {
					new_files.push(files[i]);
				}
			}
			files = new_files;
			
			file.remove();
			files_wrap.toggleClass('hide', !files.length);
			
			uploadNextFile();
		});
		
		input_wrap.find('input').on('change', function (e) {
			files_wrap.find('.js-file_error').remove();
		
			$.each(this.files, function (_, blob) {
				var file = {
					id: 'upload_file_' + Date.now(), 
					blob: blob, 
					action: el.data('action')
				};
				
				var errors = [];
				if (blob.size > 20 * 1024 * 1024)
					errors.push('Слишком жирный файл <s>как твои ляхи</s> (' + getHumanSize(blob.size) + ' <s>Кг</s>)');
				else if (!/\.(jpg|jpeg|bmp|gif|png)$/i.test(blob.name))
					errors.push('Сейчас бы в 2к17 не знать как выглядит картинка и грузить вместо неё всякую дичь -_-');
				
				file.el = $(tpl.file({
					id: file.id, 
					name: blob.name, 
					size: getHumanSize(blob.size), 
					errors: errors
				}));
				
				files_wrap.removeClass('hide').append(file.el);
				
				if (!errors.length) {
					setTimeout(function () {
						file.el.find('.js-file_thumb').prop("src", createObjectURL(blob));
					}, 0);
					files.push(file);
				}
			});
			
			this.value = "";
			
			uploadNextFile();
		});
	});
});

function uploadNextFile() {
	if (cur_file || !files.length)
		return;
	
	upload = true;
	
	cur_file = files[0];
	
	var form = new FormData();
	form.append('file', cur_file.blob);
	
	cur_file.el.trigger('file_upload_start');
	
	var xhr = new XMLHttpRequest();
	
	cur_file.xhr = xhr;
	
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
					cur_file.xhr = null;
					cur_file = null;
					
					var json;
					try {
						json = JSON.parse(xhr.responseText);
					} catch (e) { }
					
					if (!json) {
						error = "Ошибка разбора Джейсона Стетхема! <br />" + $('<div>').text(xhr.responseText).html();
					} else {
						if (json.success) {
							file.el.trigger('file_upload_end');
							file.el.trigger('file_uploaded', {
								file: file, 
								response: json
							});
							file.el.find('.js-file_delete').click();
						} else {
							error = json.error;
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
			file.el.html(tpl.file({
				name: file.blob.name, 
				errors: ['Не смогли загрузить файл!<br />' + error]
			}));
			cur_file.el.trigger('file_upload_end');
			cur_file = null;
			setTimeout(uploadNextFile, 0);
		}
	};
	xhr.open("POST", cur_file.action, true);
	try { xhr.withCredentials = true; } catch (e) {  }
	xhr.upload.onprogress = function (e) {
		if (e.lengthComputable) {
			var pct = (e.loaded / e.total) * 100;
			cur_file.el.find('.js-upload_pct').css("width", pct.toFixed(2) + '%');
			cur_file.el.find('.js-upload_info').html('<b>' + getHumanSize(e.loaded) + '</b> из <b>' + getHumanSize(e.total) + '</b> (' + +pct.toFixed(2) + '%)');
		}
	};
	xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
	cur_file.el.find('.js-upload_info').html('Загружаем...');
	xhr.send(form);
}

//	
});
