define(['jquery', 'api'], function ($) {
//
var tpl = {
	select: function () {
		var html = 
			'<div>' + 
				'<label class="lbl">Выбери файл (или несколько через зажатый Ctrl):</label><br />' + 
				'<input type="file" multiple="multiple" class="js-file_input" name="file" value="" accept="image/*" /><br />' + 
			'</div>' + 
			'<div class="pad_t">' + 
				'<label class="lbl">Ссылка на файл:</label><br />' + 
				'<table width="100%"><tr>' + 
					'<td width="100%">' + 
						'<input type="text" name="file_url" class="js-file_url" value="" />' + 
					'</td>' + 
					'<td style="padding-left: 4px">' + 
						'<input type="submit" value="Скачать" class="btn js-file_url_btn" />' + 
					'</td>' + 
				'</tr></table>' + 
			'</div>';
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
			'<div class="pad_t pad_b js-upload_file oh break-word" id="' + data.id + '">' + 
				'<table style="width: 100%"><tr>' + 
					'<td class="left post-preview file-thumb">' + 
						'<div class="file-thumb-center"></div>' + 
						'<img src="i/img/transparent.gif" alt="" class="js-file_thumb" />' + 
					'</td>' + 
					'<td style="width:100%">' + 
						'<b class="darkblue">' + html_wrap(data.name) + '</b>' + (data.size ? ' <span class="grey">(' + data.size + ')</span>' : '') + 
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

$.fn.genericUploader = function () {
	var forms = this;
	if (!forms.hasClass('js-upload_form'))
		forms = forms.find('.js-upload_form');
	forms.each(function () {
		var el = $(this);
		initForm(el);
	});
};

$.urlUploader = function (options) {
	options = $.extend({
		gid:			0, 
		files:			[], 
		images:			[], 
		documents:		[], 
		videos:			[], 
		cover:			false, 
		coverOffset:	false, 
		onStateChanged:	false, 
		onDone:			false, 
		onError:		false, 
		action:			""
	}, options);
	
	var upload_id;
	
	var check_upload = function () {
		if (upload_id) {
			api_data = {id: upload_id, type: "url"};
		} else {
			api_data = {
				files:			options.files, 
				images:			options.images, 
				documents:		options.documents, 
				videos:			options.videos, 
				type:			"url", 
				cover:			options.cover, 
				offset:			options.coverOffset
			}
		}
		
		$.api(options.action, api_data, function (res) {
			if (res.data) {
				options.onDone && options.onDone(res);
			} else if (res.queue) {
				upload_id = res.id;
				
				var status, pct = 0;
				if (!('downloaded' in res.queue)) {
					status = 'Ожидаем очереди...';
				} else if (res.done) {
					status = 'Ожидаем чуда...';
				} else {
					var compelted_pct = (res.queue.downloaded + res.queue.uploaded) / (res.queue.total * 2) * 100, 
						download_pct = 0;
					
					if (res.queue.download_size)
						download_pct = res.queue.download_offset / res.queue.download_size * 100;
					
					pct = Math.round(compelted_pct + (50 / res.queue.total * download_pct / 100), 1);
					
					status = pct + '%, скачано: ' + res.queue.downloaded + ' из ' +
						res.queue.total + ', загружено: ' + res.queue.uploaded + ' из ' + res.queue.total;
				}
				
				options.onStateChanged && options.onStateChanged({status: status, percent: pct});
				
				setTimeout(check_upload, 10);
			} else if (res.error) {
				options.onError && options.onError(res.error);
			} else if (!res.queue) {
				options.onError && options.onError('Внутренняя ошибка.');
			}
		}, function () {
			setTimeout(check_upload, 500);
		});
	}
	
	check_upload();
	
	return this;
};

function initForm(el) {
	var input_wrap = el.find('.js-upload_input'), 
		files_wrap = el.find('.js-upload_files');
	
	el.removeClass('js-upload_form');
	
	input_wrap.html(tpl.select());
	
	input_wrap.on('click', '.js-file_url_btn', function (e, extra) {
		e.preventDefault();
		
		var url_input = input_wrap.find('.js-file_url');
		
		var file = {
			id:		'upload_url_' + Date.now(), 
			url:	$.trim(url_input.val()), 
			action:	el.data('action'), 
		};
		
		if (!file.url.length)
			return;
		
		var errors = [];
		if (file.url.substr(0, 2) == '//')
			file.url = 'http:' + file.url;
		else if (file.url.substr(0, 2) == '/')
			errors.push('Кривая ссылка.');
		else if (!file.url.match(/^(http|https):\/\//))
			file.url = 'http://' + file.url;
		
		file.el = $(tpl.file({
			id:		file.id, 
			name:	file.url, 
			errors:	errors
		}));
		
		file.el.find('.js-file_thumb').prop("src", "/i/img/link_2x.png");
		
		files_wrap.removeClass('hide').append(file.el);
		
		if (!errors.length) {
			url_input.val('');
			file.el.trigger('file_upload_start');
			$.urlUploader({
				action:			file.action, 
				files:			[file.url], 
				cover:			extra && extra.cover, 
				coverOffset:	extra && extra.offset, 
				
				onError: function (err) {
					if (file.deleted)
						return;
					
					file.done = true;
					file.el.trigger('file_upload_end');
					file.el.html(tpl.file({
						name: file.url, 
						errors: [err || 'Не смогли загрузить файл!']
					}));
				}, 
				onStateChanged: function (e) {
					if (file.deleted)
						return;
					
					file.el.find('.js-upload_info').html(e.status);
				}, 
				onDone: function (res) {
					if (file.deleted)
						return;
					
					file.done = true;
					file.el.trigger('file_upload_end');
					file.el.trigger('file_uploaded', {
						file:		file, 
						response:	res, 
						data:		extra && extra.data, 
						offset:		extra && extra.offset, 
					});
					file.el.find('.js-file_delete').click();
				}
			});
			
			files.push(file);
		}
	});
	
	files_wrap.on('click', '.js-file_delete', function (e) {
		e.preventDefault();
		
		var file = $(this).parents('.js-upload_file');
		
		var new_files = [];
		for (var i = 0; i < files.length; ++i) {
			if (files[i].id == file.prop("id")) {
				if (files[i].xhr) {
					files[i].xhr.abort();
					files[i].xhr = null;
					
					if (cur_file && cur_file.id == files[i].id)
						cur_file = null;
				}
				
				if (!files[i].done) {
					files[i].done = true;
					files[i].el.trigger('file_upload_end');
				}
				
				files[i].deleted = true;
			} else {
				new_files.push(files[i]);
			}
		}
		files = new_files;
		
		file.remove();
		files_wrap.toggleClass('hide', !files.length);
		
		uploadNextFile();
	});
	
	input_wrap.find('.js-file_input').on('change', function (e, extra) {
		files_wrap.find('.js-file_error').remove();
	
		$.each(extra ? [extra.blob] : this.files, function (_, blob) {
			var file = {
				id: 'upload_file_' + Date.now(), 
				blob: blob, 
				action: el.data('action'), 
				type: 'upload', 
				data: extra && extra.data
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
}

function uploadNextFile() {
	if (cur_file || !files.length)
		return;
	
	for (var i = 0; i < files.length; ++i) {
		if (files[i].type == 'upload') {
			cur_file = files[i];
			break;
		}
	}
	
	if (!cur_file)
		return;
	
	upload = true;
	
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
							file.done = true;
							file.el.trigger('file_upload_end');
							file.el.trigger('file_uploaded', {
								file:		file, 
								data:		file.data, 
								response:	json
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
			cur_file.done = true;
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
