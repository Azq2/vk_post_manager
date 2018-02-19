define(['jquery', 'upload', 'emojionearea', 'functions'], function ($) {
//
$(function () {
	$('body').emojify();
	$('.js-page_spinner_switch').toggleClass('hide');

	post_action('.js-post_edit', function (post, gid, id) {
		var data = VK_POST_DATA[id];
		post.find('.post-text').toggleClass('hide');
		post.find('.post-text_edit').toggleClass('hide');
		post.find('textarea')
			.val(data.message)
			.emojioneArea({
				pickerPosition: "bottom", 
				filtersPosition: "bottom", 
				autocomplete: false, 
				tonesStyle: "checkbox"
			});
		data.message = post.find('textarea').prop("emojioneArea");
		post.data('edited', true);
	});

	function decode_att_id(att_id) {
		if (att_id.indexOf('link_') == 0)
			return unescape(att_id.substr(5).replace(/(..)/g, '%$1'));
		return att_id;
	}

	post_action('.js-attach_delete', function (post, gid, id) {
		var el = this, 
			att_id = el.data('id'), 
			att = post.find('.js-attach_' + att_id), 
			data = VK_POST_DATA[id];
		data.deleted = data.deleted || {};
		
		var decoded_id = decode_att_id(att_id);
		if (data.deleted[decoded_id]) {
			delete data.deleted[decoded_id];
			att.removeClass('deleted');
		} else {
			data.deleted[decoded_id] = true;
			att.addClass('deleted');
		}
		
		console.log(decoded_id, post, gid, id, VK_POST_DATA[id]);
	});

	post_action('.js-post_accept', function (post, gid, id) {
		var el = this, 
			data = VK_POST_DATA[id];
		if (el.attr("disabled"))
			return;
		
		data.deleted = data.deleted || {};
		
		var post_data = {
			gid: gid, id: id, 
			signed: +post.find('.js-anon_switch').data('state') ? 0 : 1, 
			message: data.message.getText ? data.message.getText() : data.message, 
			type: data.post_type, 
			attachments: []
		};
		
		for (var i = 0; i < data.attachments.length; ++i) {
			if (!data.deleted[data.attachments[i]])
				post_data.attachments.push(data.attachments[i]);
		}
		post_data.attachments = post_data.attachments.join(",");
		
		if (!data.deleted.geo) {
			post_data.lat = data.lat;
			post_data.long = data.long;
		}
		
		post.find('textarea').attr('disabled', 'disabled').attr('readonly', 'readonly');
		el.attr('disabled', 'disabled');
		$.api("?a=queue", post_data, function (res) {
			el.removeAttr('disabled');
			if (res.success) {
				post.find('.js-comment_btns').hide();
				post.find('.js-comment_msg')
					.show()
					.html('<b class="green">Пост успешно добавлен в очередь. </b>');
			} else {
				alert(res.error);
			}
		}, function () {
			el.removeAttr('disabled');
			alert("Сетевая ошибка :(");
		});
	});
	post_action('.js-post_delete', function (post, gid, id) {
		var el = this, restore = !!el.data('restore');
		if (el.attr("disabled"))
			return;
		
		el.attr('disabled', 'disabled');
		
		$.api("?a=delete", {
			gid: gid, id: id, 
			anon: +post.find('.js-anon_switch').data('state'), 
			restore: restore ? 1 : 0, 
			dataType: "json"
		}, function (res) {
			el.removeAttr('disabled');
			if (res.success) {
				post.find('.js-comment_btns').toggleClass('hide', !restore);
				post.find('.js-comment_msg')
					.toggleClass('hide', restore)
					.html('<b class="red">Пост успешно удалён. <button class="btn btn-cancel right js-post_delete" data-restore="1">Отменить</button></b>');
			} else {
				alert(res.error);
			}
		}, function () {
			el.removeAttr('disabled');
			alert("Сетевая ошибка :(");
		});
	});

	$('body').on('click', '.js-toggle_btn', function (e) {
		e.preventDefault();
		var el = $(this), 
			state = !el.data('state');
		el.data('state', state).attr("data-state", state ? 1 : 0).trigger("change");
	}).on('click', '.js-convert', function (e) {
		e.preventDefault();
		var el = $(this);
		if (el.attr("disabled"))
			return;
		el.attr('disabled', 'disabled');
		
		var toggle_spinner = function (f) {
			f ? el.attr("disabled", "disabled") : el.removeAttr("disabled");
			el.find('.js-spinner').toggleClass('hide', !f);
		};
		toggle_spinner(true);
		
		var retry = function () {
			$.api("?a=fix_timeout", {
				gid: el.data('gid'), 
				dataType: "json"
			}, function (res) {
				if (res.retry) {
					setTimeout(retry, 2000);
				} else if (res.success) {
					toggle_spinner(false);
					location.reload();
				} else {
					toggle_spinner(false);
					alert(res.error);
				}
			}, function () {
				toggle_spinner(false);
				alert("Сетевая ошибка :(");
			}, function (res) {
				var info = $('#convert_info');
				if ('processed' in res) {
					console.log(res);
					info
						.html('Обработано ' + res.processed + ' из ' + res.total + ' (' + res.fixed + ' исправлено)' + 
							(res.sleep ? ' (Ждём 2 секунды)' : ''))
						.removeClass('hide');
				} else {
					info.addClass('hide');
				}
			});
		}
		retry();
	}).on('file_uploaded', '.js-post', function (e, data) {
		var post = $(this), 
			gid = post.data('id').split('_')[0], 
			id = post.data('id').split('_')[1], 
			post_data = VK_POST_DATA[id];
		
		post_data.attachments.push(data.response.id);
		
		post.find('.js-post_attaches').removeClass('hide').append(data.response.file);
		
		
	}).on('file_upload_start', '.js-post', function (e) {
		$(this).find('.js-post_accept, .js-post_delete').attr("disabled", "disabled");
	}).on('file_upload_end', '.js-post', function (e) {
		$(this).find('.js-post_accept, .js-post_delete').removeAttr("disabled");
	});

	var last_user_search;
	$('.js-search_user_wrap button').on('click', function (e) {
		var el = $(this), 
			wrap = el.parents('.js-search_user_wrap'), 
			input = wrap.find('.js-search_user'), 
			list = wrap.find('.js-search_user_list');
		
		if (last_user_search)
			last_user_search.cancel();
		
		var q = $.trim(input.val());
		last_user_search = $.get("?a=search_users", {q: q}, function (res) {
			last_user_search = null;
			list.html(res.list ? res.list : 'Ничего не найдено?');
		});
	});

	$('#group_settings').on('change click keyup keydown', 'input', function () {
		recalc_freq_settings();
		$('#group_settings').find('.js-btn_save').show();
	}).on('click', '.js-interval_incr', function (e) {
		e.preventDefault();
		var el = $(this), 
			form = $('#group_settings')[0];
		
		var interval = +form.elements.hh.value * 3600 + +form.elements.mm.value * 60;
		interval += 300 * el.data('dir');
		var h = Math.floor(interval / 3600), 
			m = Math.round((interval - h * 3600) / 60);
		
		form.elements.hh.value = pad(h);
		form.elements.mm.value = pad(m);
		
		$(form.elements.hh).trigger('change');
		recalc_freq_settings();
	});

	if ($('#group_settings').length)
		recalc_freq_settings();

	function recalc_freq_settings() {
		var $form = $('#group_settings'), 
			form  = $form[0];
		var interval = +form.elements.hh.value * 3600 + +form.elements.mm.value * 60, 
			from = +form.elements.from_hh.value * 3600 + +form.elements.from_mm.value * 60, 
			to = +form.elements.to_hh.value * 3600 + +form.elements.to_mm.value * 60;
		
		if (to < from)
			to += 24 * 3600;
		
		var count = 0;
		while (from <= to) {
			from += interval;
			from = Math.round(from / 300) * 300;
			++count;
		}
		
		$('#post_cnt').text(count).css("color", count > 50 ? 'red' : '');
	}

	function post_action(selector, func, evt) {
		$('body').on(evt || 'click', selector, function (e) {
			e.preventDefault();
			var el = $(this), 
				post = el.parents('.js-post'), 
				gid = post.data('id').split('_')[0], 
				id = post.data('id').split('_')[1];
			func.apply(el, [post, gid, id]);
		});
	}
});
//
});
