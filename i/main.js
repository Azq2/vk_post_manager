$(function () {
	var tpl = {
		captcha: function (data) {
			var html = 
				'<center><img src="' + data.url + '" alt="" style="width: 100%" /></center>' + 
				'<div class="pad_t">' + 
					'<input type="text" value="" name="captcha" class="js-enter_captcha_code" style="width: 100%" /><br />' + 
					'<button class="btn js-enter_captcha" style="width: 100%">Я не робот!</button>' + 
				'</div>';
			return html;
		}
	};
	
	post_action('.js-post_edit', function (post, gid, id) {
		var data = VK_POST_DATA[id];
		post.find('.post-text').toggleClass('hide');
		post.find('.post-text_edit').toggleClass('hide');
		post.find('textarea').val(data.message).off().on('focus blur input', function () {
			data.message = $(this).val();
		});
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
			message: data.message, 
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
					.html('<b class="green">Пост успешно добавлен в очередь (' + res.date + '). </b>');
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
		el.toggleClass('btn-green', state).data('state', state);
	}).on('click', '.js-convert', function (e) {
		e.preventDefault();
		var el = $(this);
		if (el.attr("disabled"))
			return;
		el.attr('disabled', 'disabled');
		$.api("?a=fix_timeout", {
			gid: el.data('gid'), 
			dataType: "json"
		}, function (res) {
			el.removeAttr('disabled');
			if (res.success)
				location.reload();
			else
				alert(res.error);
		}, function () {
			el.removeAttr('disabled');
			alert("Сетевая ошибка :(");
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
			to = +form.elements.to_hh.value * 3600 + +form.elements.to_mm.value * 60, 
			count = Math.round((to - from + 1) / interval);
		
		$('#post_cnt').text(count).css("color", count > 50 ? 'red' : '');
	}
	
	function post_action(selector, func) {
		$('body').on('click', selector, function (e) {
			e.preventDefault();
			var el = $(this), 
				post = el.parents('.js-post'), 
				gid = post.data('id').split('_')[0], 
				id = post.data('id').split('_')[1];
			func.apply(el, [post, gid, id]);
		});
	}
	
	$.api = function (url, data, fn_ok, fn_err) {
		data = $.extend({}, data);
		$.post(url, data, function (res) {
			if (res.captcha) {
				var win = modal_window(tpl.captcha({url: res.captcha.url}))
				win.find('.js-enter_captcha').on('click', function (e) {
					e.preventDefault();
					
					data.vk_captcha_key = win.find('.js-enter_captcha_code').val();
					data.vk_captcha_sid = res.captcha.sid;
					
					$.api(url, data, fn_ok, fn_err);
					
					modal_window(false);
				});
			} else {
				fn_ok && fn_ok(res);
			}
		}, "json").error(function () {
			fn_err && fn_err();
		});
	};
});

function modal_window(content) {
	$('#modal_overlay').toggleClass('hide', !content);
	return $('#modal_content').html(content || '');
}

function pad(str, n, c) {
	n = n || 2;
	c = c || "0";
	str = str + "";
	n = n - str.length;
	for (var i = 0; i < n; ++i)
		str = c + str;	
	return str;
}
