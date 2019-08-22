define(['jquery', 'utils'], function ($, utils) {
//

var tpl = {
	error: function (text) {
		return '<span class="red">' + text + '</span>';
	}, 
	success: function (text) {
		return '<span class="green">' + text + '</span>';
	}
};

$('body').on('click', '.js-oauth_form_submit', function (e) {
	var el = $(this), 
		wrap = el.parents('.js-oauth_form');
	
	if (el.attr("disabled"))
		return;
	
	var toggle_spinner = function (f) {
		f ? el.attr("disabled", "disabled") : el.removeAttr("disabled");
		el.find('.js-spinner').toggleClass('hide', !f);
		wrap.find('.js-status_text').addClass('hide');
	};
	
	var captcha_form = wrap.find('.js-captcha_form'), 
		sms_2f_form = wrap.find('.js-sms-2fa_form'), 
		code_2f_form = wrap.find('.js-code-2fa_form');
	
	toggle_spinner(true);
	
	$.post(wrap.prop("action"), wrap.serialize(), null, "json").success(function (res) {
		toggle_spinner(false);
		if (res.success) {
			wrap
				.find('.js-status_text')
				.removeClass('hide')
				.html(tpl.success('Авторизация установлена.'));
			
			location.reload();
		} else {
			sms_2f_form.toggleClass('hide', !res.sms_2f)
				.find('input').val('');
			code_2f_form.toggleClass('hide', !res.code_2f)
				.find('input').val('');
			captcha_form.toggleClass('hide', !res.captcha)
				.find('input').val('');
			
			if (res.captcha) {
				captcha_form.find('.js-captcha_img').prop("src", res.captcha.url);
				captcha_form.find('.js-captcha_sid').val(res.captcha.sid);
			}
			
			wrap
				.find('.js-status_text')
				.removeClass('hide')
				.html(tpl.error(res.error));
		}
		console.log(res);
	}).error(function () {
		toggle_spinner(false);
		wrap
			.find('.js-status_text')
			.removeClass('hide')
			.html(tpl.error('Страшная ошибка!!11 Мб нет интернета?'));
	});
})

//
});
