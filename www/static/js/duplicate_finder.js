define(['jquery', 'utils'], function ($, utils) {
//

$('body').on('click', '#do_search', function (e) {
	var el = $(this), 
		photo_url = $('#photo_url').val(), 
		status = $('#queue_status'), 
		results = $('#results');
	
	if (el.attr("disabled"))
		return;
	
	results.html('');
	
	var m;
	if (!(m = photo_url.match(/(photo([\d+-]+)_(\d+))/))) {
		alert("Кривая ссылка!");
		return;
	}
	
	var photo = m[1];
	
	var toggle_spinner = function (f) {
		f ? el.attr("disabled", "disabled") : el.removeAttr("disabled");
		status.toggleClass('hide', !f);
		el.find('.js-spinner').toggleClass('hide', !f);
		
		if (f)
			status.html('Ожидаем очереди...');
	};
	
	toggle_spinner(true);
	
	$.post("/?a=tools/duplicate_finder_queue",  {
		photo:		photo
	}, "json").success(function (res) {
		if (res.success) {
			checker(res.id, function (queue, error) {
				if (error) {
					alert(error);
					toggle_spinner(false);
				} else if (queue.done) {
					toggle_spinner(false);
					
					var html = '';
					
					for (var res of queue.results) {
						var thumb = {
							w: 320, 
							h: 320, 
							src: "/i/img/transparent.gif"
						};
						for (var img of res.photo.sizes) {
							if (img.width <= 640) {
								thumb = {
									w: img.width, 
									h: img.height, 
									src: img.url
								};
							}
						}
						
						var photo_w = thumb.w, 
							photo_h = Math.round(thumb.w / (thumb.w / thumb.h)), 
							aspect = photo_h / photo_w, 
							max_width = Math.min(photo_w, 320);
						
						html += '<div class="wrapper board row">';
						
						if (res.wall) {
							var parts = res.wall.replace(/^wall/, '').split('_');
							
							html += 
								'<div class="pad_b">' +
									'<b>Найден пост:</b> <a href="https://vk.com/' +  res.wall+ '" target="_blank">https://vk.com/' +  res.wall+ '</a>' + 
								'</div>';
						} else {
							html += '<div class="pad_b">Посты не найдены!!!</div>';
						}
						
						var deadline = ((Date.now() / 1000) - 90 * 3600 * 24);
						
						html += 
							'<div>' + 
								'<div class="pad_b grey">' +
									utils.getHumanDate(res.photo.date, "time") + 
								'</div>' + 
								'<div class="pad_b grey">' +
									(deadline >= res.photo.date ? '<span class="red">Пост старше 3 мес.</span>' : '<span class="green">Пост младше 3 мес, можно жаловаться</span>') + 
								'</div>' + 
								'<div style="padding: 10px 0;margin: 0 2px;display: inline-block;max-width: ' + max_width + 'px; width: 100%;">' + 
									'<a href="https://vk.com/photo' + res.photo.owner_id + '_' + res.photo.id + '" target="_blank" class="aspect oh" style="padding-top: ' + (aspect * 100) + '%">' + 
										'<img src="' + thumb.src + '" alt="" class="preview" />' + 
									'</a>' + 
								'</div>' + 
							'</div>';
						
						html += '</div>';
					}
					
					results.html(html || '<span class="grey">Ничего не найдено...</span>');
				} else if (queue.parsed) {
					status.html('Ищем дубли....');
				}
			});
		} else {
			toggle_spinner(false);
			alert(res.error);
		}
	}).fail(function () {
		toggle_spinner(false);
		alert('Страшная ошибка!!11 Мб нет интернета?');
	});
	
	function checker(id, callback) {
		$.post("/?a=tools/duplicate_finder_queue",  {
			id:		id
		}, "json").success(function (res) {
			if (res.success) {
				if (!res.queue.done) {
					setTimeout(function () {
						checker(id, callback);
					}, 500);
				}
				
				callback(res.queue, false);
			} else if (res.error) {
				callback(false, res.error);
			} else {
				setTimeout(function () {
					checker(id, callback);
				}, 5000);
			}
		}).fail(function () {
			setTimeout(function () {
				checker(id, callback);
			}, 5000);
		});
	}
})

//
});
