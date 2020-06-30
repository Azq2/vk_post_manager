define(['jquery', 'utils', 'emojionearea'], function ($, utils) {
//

$('.js-emojiarea').emojioneArea({
	pickerPosition: "bottom", 
	filtersPosition: "bottom", 
	autocomplete: false, 
	
	attributes: {
		spellcheck:		true, 
		rows:			3
	},
	
	tonesStyle: "checkbox"
});

$('body').on('click', '.js-message_save', function (e) {
	var el = $(this), 
		wrap = el.parents('.js-message');
	
	if (el.attr("disabled"))
		return;
	
	var toggle_spinner = function (f) {
		f ? el.attr("disabled", "disabled") : el.removeAttr("disabled");
		el.find('.js-spinner').toggleClass('hide', !f);
		wrap.find('.js-status_text').addClass('hide');
	};
	
	toggle_spinner(true);
	
	$.post($('#add_form').prop("action"),  {
		id:		wrap.data('id'), 
		text:	wrap.find('.js-message_text').prop("emojioneArea").getText()
	}, "json").done(function (res) {
		toggle_spinner(false);
		if (res.success) {
			wrap.find('.js-status_text').removeClass('hide').text('сохранено');
		} else {
			alert(res.error);
		}
	}).fail(function () {
		toggle_spinner(false);
		alert('Страшная ошибка!!11 Мб нет интернета?');
	});
})

//
});
