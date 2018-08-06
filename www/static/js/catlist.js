define(['jquery', 'upload', 'emojionearea', 'functions'], function ($) {
//

$(function () {
	$('.js-page_spinner_switch').toggleClass('hide');
	
	$('.js-emojiarea').emojioneArea({
		pickerPosition: "bottom", 
		filtersPosition: "bottom", 
		autocomplete: false, 
		tonesStyle: "checkbox"
	});

	// Сообщения
	$('body').on('click', '.js-message_save', function (e) {
		e.preventDefault();
		var btn = $(this), 
			wrap = btn.parents('.js-message');
		
		if (btn.attr("disabled"))
			return;
		
		var toggle_spinner = function (f) {
			f ? btn.attr("disabled", "disabled") : btn.removeAttr("disabled");
			btn.find('.js-spinner').toggleClass('hide', !f);
			wrap.find('.js-status_text').addClass('hide');
		};
		
		toggle_spinner(true);
		
		$.api("?a=game/catlist&sa=message_add&ajax=1", {
			id: wrap.data('id'), 
			text: wrap.find('textarea').prop("emojioneArea").getText()
		}, function (res) {
			toggle_spinner(false);
			if (res.error) {
				alert(res.error);
			} else {
				wrap.find('.js-status_text').removeClass('hide').text('сохранено');
			}
		}, function () {
			alert('Страшная ошибка!!11 Мб нет интернета?');
			toggle_spinner(false);
		});
	})

	.on('click', '.js-message_delete', function (e) {
		e.preventDefault();
		var btn = $(this);
		
		if (btn.attr("disabled"))
			return;
		
		if (!confirm("Точна???"))
			return;
		
		$.api("?a=game/catlist&sa=message_delete&id=" + btn.data('id') + "&ok=1", {}, function () {
			location.reload();
		}, function () {
			alert('Страшная ошибка!!11 Мб нет интернета?');
		});
	})

	// Приют
	.on('click', '.js-cats_delete', function (e) {
		e.preventDefault();
		var btn = $(this);
		
		if (btn.attr("disabled"))
			return;
		
		if (!confirm("Точна???"))
			return;
		
		location.href = "?a=game/catlist&sa=cats_delete&id=" + btn.data('id');
	})

	.on('submit', '.js-cats_save', function (e) {
		e.preventDefault();
		
		var form = $(this), 
			elements = form.prop("elements"), 
			btn = form.find('.js-cats_save_btn'), 
			id = form.data("id");
		
		if (btn.attr("disabled"))
			return;
		
		var toggle_spinner = function (f) {
			f ? btn.attr("disabled", "disabled") : btn.removeAttr("disabled");
			btn.find('.js-spinner').toggleClass('hide', !f);
			form.find('.js-progress_wrap').toggleClass('hide', !f);
			form.find('.js-progress').css("width", '0%');
			form.find('.js-status_text').addClass('hide');
		};
		
		var error, 
			name = $.trim(elements.name.value), 
			text = $.trim(elements.text.value), 
			photo = elements.photo.files[0], 
			type = $('#cats_type').data('type'), 
			price = parseFloat(elements.price.value);
		
		if (!name.length)
			error = 'У котика нет имени? =/';
		else if (!photo && !id)
			error = 'У котика нет фоточки? =/';
		else if (type == 'shelter' && price > 0)
			error = 'Это же приют, таки откуда тут цена?';
		else if (type == 'catshop' && !price)
			error = 'Тут не место для благотворительности! Бесплатные коты - только в приют.';
		
		if (error) {
			alert(error);
		} else {
			toggle_spinner(true);
			
			var data = new FormData();
			data.append('name', name);
			data.append('text', text);
			data.append('photo', photo);
			data.append('sex', elements.sex.value);
			data.append('price', price);
			
			if (id)
				data.append('id', id);
			
			$.ajax({
				url: form.prop("action"), 
				method: 'POST', 
				data: data, 
				processData: false, 
				contentType: false, 
				cache: false, 
				dataType: "json", 
				xhr: function () {
					var xhr = $.ajaxSettings.xhr();
					if (photo) {
						xhr.upload.addEventListener("progress", function (e) {
							var pct = e.loaded / Math.max(e.total, photo.size) * 100;
							form.find('.js-progress').css("width", pct + '%');
						});
					}
					return xhr;
				}
			}).success(function (res) {
				toggle_spinner(false);
				if (res.success) {
					if (id) {
						form.find('.js-photo_max_width').css("max-width", Math.min(res.width, 320));
						form.find('.js-photo_aspect').css("padding-top", (res.height / res.width * 100) + "%");
						form.find('.js-status_text').removeClass('hide').text('сохранено');
						
						var img = form.find('.js-photo_src');
						img.replaceWith(img.clone(true).prop("src", "files/catlist/cats/" + res.photo));
					} else {
						location.reload();
					}
				} else {
					alert(res.error);
				}
			}).error(function () {
				toggle_spinner(false);
				alert('Страшная ошибка!!11 Мб нет интернета?');
			});
			
			console.log(elements);
		}
	})

	// Магазин
	.on('click', '.js-shop_delete', function (e) {
		e.preventDefault();
		var btn = $(this);
		
		if (btn.attr("disabled"))
			return;
		
		if (!confirm("Точна???"))
			return;
		
		location.href = "?a=game/catlist&sa=shop_delete" + 
			'&deleted=' + (btn.data('flag') ? 0 : 1) + '&flag=' + btn.data('flag') + '&id=' + btn.data('id') + '&type=' + $('#shop_type').data('type');
	})

	.on('submit', '.js-shop_save', function (e) {
		e.preventDefault();
		
		var form = $(this), 
			elements = form.prop("elements"), 
			btn = form.find('.js-shop_save_btn'), 
			id = form.data("id");
		
		if (btn.attr("disabled"))
			return;
		
		var toggle_spinner = function (f) {
			f ? btn.attr("disabled", "disabled") : btn.removeAttr("disabled");
			btn.find('.js-spinner').toggleClass('hide', !f);
			form.find('.js-progress_wrap').toggleClass('hide', !f);
			form.find('.js-progress').css("width", '0%');
			form.find('.js-status_text').addClass('hide');
		};
		
		var error, 
			title = $.trim(elements.title.value), 
			description = $.trim(elements.description.value), 
			photo = elements.photo.files[0], 
			type = $('#shop_type').data('type'), 
			price = parseFloat(elements.price.value);
		
		if (!title.length)
			error = 'У товара нет названия? =/';
		else if (!photo && !id)
			error = 'У товара нет фоточки? =/';
		else if (price <= 0)
			error = 'Тут не место для благотворительности!';
		
		if (error) {
			alert(error);
		} else {
			toggle_spinner(true);
			
			var data = new FormData();
			data.append('title', title);
			data.append('description', description);
			data.append('photo', photo);
			data.append('price', price);
			
			if (elements.amount)
				data.append('amount', elements.amount.value);
			
			if (id)
				data.append('id', id);
			
			$.ajax({
				url: form.prop("action"), 
				method: 'POST', 
				data: data, 
				processData: false, 
				contentType: false, 
				cache: false, 
				dataType: "json", 
				xhr: function () {
					var xhr = $.ajaxSettings.xhr();
					if (photo) {
						xhr.upload.addEventListener("progress", function (e) {
							var pct = e.loaded / Math.max(e.total, photo.size) * 100;
							form.find('.js-progress').css("width", pct + '%');
						});
					}
					return xhr;
				}
			}).success(function (res) {
				toggle_spinner(false);
				if (res.success) {
					if (id) {
						form.find('.js-photo_max_width').css("max-width", Math.min(res.width, 320));
						form.find('.js-photo_aspect').css("padding-top", (res.height / res.width * 100) + "%");
						form.find('.js-status_text').removeClass('hide').text('сохранено');
						
						var img = form.find('.js-photo_src');
						img.replaceWith(img.clone(true).prop("src", "files/catlist/shop/" + res.photo));
					} else {
						location.reload();
					}
				} else {
					alert(res.error);
				}
			}).error(function () {
				toggle_spinner(false);
				alert('Страшная ошибка!!11 Мб нет интернета?');
			});
			
			console.log(elements);
		}
	});
});

//
});