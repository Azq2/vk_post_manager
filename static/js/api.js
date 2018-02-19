define(['jquery', 'functions'], function ($) {
//
var tpl = {
	captcha: function (data) {
		var html = 
			'<center><img src="' + data.url + '" alt="" style="width: 100%" /></center>' + 
			'<div class="pad_t">' + 
				'<input type="text" value="" name="captcha" class="js-enter_captcha_code" style="width: 100%" />' + 
			'</div>' + 
			'<div class="pad_t">' + 
				'<button class="btn js-enter_captcha" style="width: 100%">Я не робот!</button>' + 
			'</div>';
		return html;
	}
};

$.api = function (url, data, fn_ok, fn_err, fn_hook) {
	data = $.extend({}, data);
	$.post(url, data, function (res) {
		fn_hook && fn_hook(res);
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
	}, "json").error(function (e) {
		fn_err && fn_err();
	});
};

//
});
