$(function () {
//
var TOPICS_CHUNK = 10, 
	LOAD_CHUNK = 100;

var settings = $('#grabber_data').data(), 
	all_topics = {}, 
	all_topics_array = [], 
	topics_offset = 0, 
	remote_topics_offset = 0, 
	remote_topics_end = false, 
	remote_loading = false, 
	editor, 
	max_offset, 
	uniq_id = '[' + settings.gid + '_' + settings.sort + '_' + settings.mode + ']', 
	exclude = [], 
	queue_size = 0;

function prepareText(text) {
	var URL_RE = /(?:([!()?.,\s\n\r]|^)((https?:\/\/)?((?:[a-z0-9_\-]+\.)+(?:[a-z]{2,7}|xn--p1ai|xn--j1amh|xn--80asehdb|xn--80aswg))(\/.*?)?(\#.*?)?)(?:[.!:;,*()]*([\s\r\n]|$))|([!()?.,\s\n\r]|^)((https?:\/\/)?((?:[a-z0-9а-яєґї_\-]+\.)+(?:рф|укр|онлайн|сайт|срб|su))(\/.*?)?(\#.*?)?)(?:[.!:;,*()]*([\s\r\n]|$))|([!()?.,\s\n\r]|^)((https?:\/\/)((?:[a-z0-9а-яєґї_\-]+\.)+(?:[a-z]{2,7}|рф|укр|онлайн|сайт|срб|su))(\/.*?)?(\#.*?)?)(?:[.!:;,*()]*([\s\r\n]|$)))/gi;
	return emojione.toImage(text.replace(/\[(club|public|id)(\d+)\|([^\]]+)\]/gim, function (_, type, id, title) {
		return '<a href="https://vk.com/' + type + id + '" target="_blank">' + title + '</a>';
	}).replace(URL_RE, function () {
		var m = arguments, 
			offset = m[4] ? 0 : (m[7 + 4] ? 7 : 14), 
			url = m[offset + 2];
		return m[offset + 1] + '<a href="' + url + '" target="_blank">' + url + '</a>' + m[offset + 7];
	})).replace(/\r\n|\r|\n/gi, "<br />");
}

var tpl = {
	attaches: function (data) {
		var attaches = [];
		
		var find_thumb = function (hash, size) {
			var ret;
			for (var w in hash) {
				if (!hash.hasOwnProperty(w))
					continue;
				if ((!ret && w > size) || w <= size)
					ret = {src: hash[w], w: +w};
				if (ret)
					ret.orig_src = hash[w];
			}
			
			if (!ret)
				console.log('find_thumb(', hash, size, ') = ', ret);
			
			return ret;
		};
		
		for (var i = 0; i < data.length; ++i) {
			var att = data[i];
			var html = '';
			
			var att_id = att.id;
			if (att.type == 'photo' || (att.type == 'doc' && !('length' in att.thumbs))) {
				var aspect = att.w / att.h, 
					thumb = find_thumb(att.thumbs, 640), 
					photo_w = thumb.w, 
					photo_h = Math.round(thumb.w / aspect);
				
				var src = att.type == 'photo' ? thumb.orig_src : att.page_url || att.url;
				
				var aspect = photo_h / photo_w, 
					max_width = photo_h > photo_w ? 
						Math.min(320 / aspect, photo_w) : 
						Math.min(320, photo_w), 
					tile = att.type == 'doc' ? '100%' : (photo_h / photo_w > 1.2 ? '25%' : '50%');
				
				var deleted = att.type == 'doc' && !att.ext.match(/^png|jpg|jpeg|bmp|webp|gif$/);
				
				html += 
					'<div class="center post-pic' + (deleted ? ' deleted' : '') + ' js-attach' + (att.ext == 'gif' ? ' js-gif' : '') + '" ' + 
							(
								att.ext == 'gif' ? 
								' data-gif="' + att.url + '" data-mp4="' + (att.mp4 || "") + '"' + 
								' data-width="' + att.w + '" data-height="' + att.h + '"' : 
								' '
							) + 
							'style="padding: 10px 0;margin: 0 2px;display: inline-block;max-width: ' + max_width + 'px; width: ' + tile + ';" data-id="' + att_id + '">' + 
						'<a href="' + src + '" target="_blank" class="aspect" style="padding-top: ' + (aspect * 100) + '%">' + 
							(att.mp4 ? 
								'<video poster="' + thumb.src + '" src="' + att.mp4 + '" class="preview" preload="none" />' : 
								'<img src="' + thumb.src + '" alt="" class="preview" />'
							) + 
							'<span class="post-attach_remove js-attach_remove inl post-show_edit">' + 
								'<img src="i/img/remove_2x.png" alt="" />' + 
							'</span>' + 
							(att.ext == 'gif' ? '<img src="i/img/play.svg" alt="" class="post-doc_play js-gif_hide" />' : '') + 
							(att.type == 'doc' ? '<div class="post-doc_title js-gif_hide"><b>' + html_wrap(att.title) + '</b></div>' : '') + 
						'</a>' + 
					'</div>';
			} else if (att.type == 'doc') {
				console.log(att);
				html += 
					'<div class="post-attach deleted js-attach" data-id="' + att_id + '">' + 
						'<b>Документ:</b> <a href="' + (att.page_url || att.url) + '" target="_blank">' + 
							html_wrap(att.title) + 
						'</a>' + 
					'</div>';
			} else if (att.type == 'audio') {
				html += 
					'<div class="post-attach deleted js-attach" data-id="' + att_id + '">' + 
						'<b>Аудио:</b> ' + html_wrap(att.title) + 
					'</div>';
			} else if (att.type == 'link') {
				html += 
					'<div class="post-attach deleted js-attach" data-id="' + att_id + '">' + 
						'<b>Ссылка:</b> <a href="' + html_wrap(att.url) + '" target="_blank">' + 
							html_wrap(att.title) + 
						'</a><br />' + 
						html_wrap(att.description) + 
					'</div>';
			} else if (att.type == 'geo') {
				html += 
					'<div class="post-attach deleted js-attach" data-id="' + att_id + '">' + 
						'<b>GEO:</b> ' + att.lat + ", " + att.lng
					'</div>';
			} else if (att.type == 'poll') {
				html += 
					'<div class="post-attach deleted js-attach oh" data-id="' + att + '">' + 
						'<b>Опрос:</b> ' + html_wrap(att.question) + (att.anon ? ' <span class="grey right">Анонимный</span>' : '') + '<br />';
				if (att.answers) {
					for (var j = 0; j < att.answers.length; ++j)
						html += (j + 1) + ') ' + html_wrap(att.answers[j]) + '<br />';
				}
				html += '</div>';
			} else if (att.type == 'album' || att.type == 'market_album' || att.type == 'market' || att.type == 'video') {
				var aspect = att.w / att.h, 
					thumb = find_thumb(att.thumbs, 640), 
					photo_w = thumb.w, 
					photo_h = Math.round(thumb.w / aspect);
				
				var names = {
					album:			'Альбом', 
					market_album:	'Коллекция', 
					market:			'Товар', 
					video:			'Видео', 
				};
				
				var aspect = photo_h / photo_w, 
					max_width = Math.min(photo_w, 320);
				
				html += 
					'<div class="center post-pic deleted js-attach" ' + 
							'style="padding: 10px 0;margin: 0 2px;display: inline-block;max-width: ' + max_width + 'px; width: 100%;" data-id="' + att_id + '">' + 
						'<a href="' + att.url + '" target="_blank" class="aspect oh" style="padding-top: ' + (aspect * 100) + '%">' + 
							'<img src="' + thumb.src + '" alt="" class="preview" />' + 
							'<div class="post-doc_title">' + 
								'<b>' + names[att.type] + ': ' + html_wrap(att.title) + '</b><br />' + 
								html_wrap(att.description) + 
							'</div>' + 
						'</a>' + 
					'</div>';
			} else {
				html += 'UNKNOWN: ' + att.type;
				console.log(att);
			}
			
			attaches.push(html);
		}
		
		return attaches.length ? '<div class="post-attaches">' + attaches.join('') + '</div>' : '';
	}, 
	post: function (data) {
		var url, owner_url;
		if (data.source_type == 'VK') {
			url = 'https://vk.com/wall' + data.remote_id;
			owner_url = 'https://vk.com' + data.owner_url;
		} else if (data.source_type == 'OK') {
			url = 'https://ok.ru/group/' + data.source_id + '/topic/' + data.remote_id;
			owner_url = data.owner_url;
		}
		
		var html =
			'<div class="row js-post wrapper" data-id="' + data.remote_id + '" data-type="' + data.source_type + '" data-gid="' + data.source_id + '">' + 
				'<div class="oh">' + 
					'<div class="left post-preview relative">' + 
						'<img src="' + data.owner_avatar + '" alt="" width="50" height="50" />' + 
					'</div>' + 
					'<div class="oh">' + 
						'<span class="time">' + 
							'<span class="m">' + getHumanDate(data.time) + '</span> ' + 
						'</span>' + 
						'<a href="' + owner_url + '" target="_blank" class="m"><b class="post-author post-author-' + data.source_type + '">' + data.owner_name + '</b></a> ' + 
						'<a href="' + url + '" target="_blank">' + 
							'<img src="i/img/external.svg" width="14" height="14" class="m" alt="" />' + 
						'</a>' + 
						'<br />' + 
						'<div class="post-text post-hide_edit emoji">' + prepareText(html_wrap(data.text)) + '</div>' + 
					'</div>' + 
				'</div>' + 
				'<div class="post-show_edit pad_t">' + 
					'<textarea rows="10"></textarea>' + 
				'</div>' + 
				tpl.attaches(data.attaches || []) + 
				'<div class="pad_t grey js-status_text hide"></div>' + 
				'<div class="pad_t oh">' + 
					'<img src="i/img/like.svg" class="m" width="16" height="16" /> <span class="darkblue">' + data.likes + '</span>&nbsp;&nbsp;&nbsp;' + 
					'<img src="i/img/comment.svg" class="m" width="16" height="16" /> <span class="darkblue">' + data.comments + '</span>&nbsp;&nbsp;&nbsp;' + 
					'<img src="i/img/repost.svg" class="m" width="16" height="16" /> <span class="darkblue">' + data.reposts + '</span>' + 
				'</div>' + 
				'<div class="pad_t oh">' + 
					'<button class="btn js-post_edit post-hide_edit m">Редактор</button> ' + 
					'<button class="btn js-post_queue inl post-show_edit m">' + 
						'<img src="i/img/spinner.gif" alt="" class="m js-spinner hide" /> ' + 
						'<span class="m">Граббить</span>' + 
					'</button> ' + 
					'<button class="btn js-post_anchor m" data-n="' + data.n + '">' + 
						'<img src="i/img/anchor.svg" width="16" height="16" />' + 
					'</button>' + 
					'<button class="btn btn-delete js-post_delete right">Аццтой</button>' + 
				'</div>' + 
			'</div>';
		return html;
	}, 
	error: function (data) {
		return '<div class="row row-error wrapper">' + data.error + '</div>';
	}, 
	noPosts: function () {
		var html = 
			'<div class="row row-error wrapper center">' + 
				'Постов больше нет, но вы держитесь!<br />' + 
				'<a href="#" class="js-post_anchor" data-n="0">Перейти в начало?</a>'
			'</div>';
		return html;
	}
};

window.onbeforeunload = function() { 
	return queue_size > 0 ? "Посты ещё добавляются. Точна???" : undefined; 
};

$('body').on('click', '.js-page_input_btn', function (e) {
	e.preventDefault();
	var page = Math.max(1, +$(this).parents('.js-pagenav').find('.js-page_input').val() || 0);
	$('#post_offset').val((page - 1) * LOAD_CHUNK);
	$('#post_offset_btn').click();
}).on('click', '.js-page', function (e) {
	e.preventDefault();
	var page = $(this).data('p');
	$('#post_offset').val((page - 1) * LOAD_CHUNK);
	$('#post_offset_btn').click();
}).on('click', '#post_offset_btn', function (e) {
	e.preventDefault();
	window.localStorage["saved_offset_" + uniq_id] = $('#post_offset').val();
	location.href = location.href;
}).on('click', '.js-post_anchor', function (e) {
	e.preventDefault();
	window.localStorage["saved_offset_" + uniq_id] = $(this).data('n');
	location.href = location.href;
}).on('click', '.js-post_delete', function (e) {
	e.preventDefault();
	var btn = $(this), 
		wrap = btn.parents('.js-post');
	
	wrap.remove();
	
	$.api("?a=grabber&sa=blacklist", {
		gid: settings.gid, 
		source_id: wrap.data('gid'), 
		source_type: wrap.data('type'), 
		remote_id: wrap.data('id')
	});
}).on('click', '.js-post_queue', function (e) {
	e.preventDefault();
	var btn = $(this), 
		wrap = btn.parents('.js-post'), 
		post_id = wrap.data('type') + '_' + wrap.data('gid') + '_' + wrap.data('id'), 
		post = all_topics[post_id];
	
	if (btn.attr("disabled"))
		return;
	
	var toggle_spinner = function (f) {
		f ? btn.attr("disabled", "disabled") : btn.removeAttr("disabled");
		btn.find('.js-spinner').toggleClass('hide', !f);
		wrap.find('.js-status_text').addClass('hide');
		
		f ? ++queue_size : --queue_size;
	};
	
	toggle_spinner(true);
	
	var attaches = {};
	wrap.find('.js-attach:not(.deleted)').each(function () {
		attaches[$(this).data('id')] = 1;
	});
	
	console.log(attaches);
	
	var images = [], documents = [];
	for (var i = 0; i < post.attaches.length; ++i) {
		var att = post.attaches[i];
		console.log(att.id, att.type);
	
		if (attaches[att.id]) {
			if (att.type == 'doc') {
				documents.push(att.url);
			} else if (att.type == 'photo') {
				var url;
				for (var k in att.thumbs) {
					if (!att.thumbs.hasOwnProperty(k))
						continue;
					url = att.thumbs[k];
				}
				images.push(url);
			}
		}
	}
	
	var check_queue = function (id) {
		$.api("?a=grabber&sa=queue_done", {
			gid: settings.gid, 
			id: id
		}, function (res) {
			if (res.error) {
				toggle_spinner(false);
				alert(res.error);
			} else if (res.date) {
				toggle_spinner(false);
				wrap.html('<b class="green">' + (res.date ? 'Пост успешно добавлен в очередь (' + res.date + '). ' : 'Пост опубликован! (очередь была пустая)') + '</b>');
				
				// Блэклистим
				$.api("?a=grabber&sa=blacklist", {
					gid: settings.gid, 
					source_id: wrap.data('gid'), 
					source_type: wrap.data('type'), 
					remote_id: wrap.data('id')
				});
			} else {
				if (res.queue) {
					if (!('downloaded' in res.queue)) {
						status = '[1 / 3] Ожидаем очереди...';
					} else if (res.queue.downloaded < res.queue.total) {
						status = '[2 / 3] Скачано: ' + res.queue.downloaded + ' из ' + res.queue.total;
					} else if (res.queue.uploaded < res.queue.total) {
						status = '[3 / 3] Выгружено: ' + res.queue.uploaded + ' из ' + res.queue.total;
					} else {
						status = 'Создаём запись...';
					}
					wrap.find('.js-status_text').html(status).removeClass('hide');
				}
				
				setTimeout(function () {
					check_queue(id);
				}, 1000);
			}
		}, function () {
			setTimeout(function () {
				check_queue(id);
			}, 500);
			toggle_spinner(false);
		});
	};
	
	$.api("?a=grabber&sa=queue", {
		gid: settings.gid, 
		text: wrap.find('textarea').prop("emojioneArea").getText(), 
		images: images, 
		documents: documents
	}, function (res) {
		if (res.error) {
			alert(res.error);
			toggle_spinner(false);
		} else {
			check_queue(res.id);
		}
	}, function () {
		alert('Страшная ошибка!!11 Мб нет интернета?');
		toggle_spinner(false);
	});
}).on('click', '.js-attach_remove', function (e) {
	e.preventDefault();
	e.stopPropagation();
	e.stopImmediatePropagation();
	$(this).parents('.js-attach').toggleClass('deleted');
}).on('click', '.js-gif', function (e) {
	e.preventDefault();
	var el = $(this), 
		gif = el.data('gif'), 
		mp4 = el.data('mp4'), 
		w = el.data('height'), 
		h = el.data('width'), 
		video = el.find('video')[0], 
		img = el.find('img.preview');
	
	if (el.data('oldMaxWidth')) {
		el.css("max-width", el.data('oldMaxWidth'));
		el.removeData('oldMaxWidth');
		el.find('.js-gif_hide').removeClass('hide');
		
		if (mp4) {
			video.pause()
		} else {
			img.replaceWith(img.clone(true).prop("src", el.data('oldGif')));
			el.removeData('oldGif');
		}
	} else {
		el.data('oldMaxWidth', el.css("max-width"));
		el.css("max-width", w);
		el.find('.js-gif_hide').addClass('hide');
		
		if (mp4) {
			video.load();
			video.play();
		} else {
			el.data('oldGif', img.prop("src"));
			img.replaceWith(img.clone(true).prop("src", gif));
		}
	}
	
	console.log(gif, mp4);
}).on('click', '.js-post_edit', function (e) {
	e.preventDefault();
	var wrap = $(this).parents('.js-post');
	wrap.addClass('post-edit');
	wrap.find('textarea')
		.val(all_topics[wrap.data('type') + '_' + wrap.data('gid') + '_' + wrap.data('id')].text)
		.emojioneArea({
			pickerPosition: "bottom", 
			filtersPosition: "bottom", 
			autocomplete: false, 
			tonesStyle: "checkbox"
		});
	
	var top = wrap.offset().top;
	if (top < $(window).scrollTop() || top > $(window).scrollTop() + $(window).innerHeight())
		$('html, body').scrollTop(top);
});

var $window = $(window), 
	$document = $(document), 
	lock_scroll_events = false;
$window.on('scroll', onScroll);

init();

function init() {
	loadPosts();
	$('#garbber_init_spinner').addClass('hide');
}

function getHumanDate(unix) {
	var now = new Date(), 
		time = new Date(), 
		yesterday = new Date();
	
	time.setTime(unix * 1000);
	yesterday.setDate(yesterday.getDate() - 1);
	
	var date_key = time.getFullYear() + "-" + pad(time.getMonth() + 1) + "-" + pad(time.getDate()), 
		now_key = now.getFullYear() + "-" + pad(now.getMonth() + 1) + "-" + pad(now.getDate()), 
		yesterday_key = yesterday.getFullYear() + "-" + pad(yesterday.getMonth() + 1) + "-" + pad(yesterday.getDate());
	if (date_key == now_key) {
		return "в " + pad(time.getHours()) + ":" + pad(time.getMinutes());
	} else if (date_key == yesterday_key) {
		return "вчера в " + pad(time.getHours()) + ":" + pad(time.getMinutes());
	} else {
		return date_key + " в " + pad(time.getHours()) + ":" + pad(time.getMinutes());
	}
}

function loadPosts() {
	if (remote_topics_end || remote_loading)
		return;
	
	var toggle_loading = function (f) {
		remote_loading = f;
		$('#garbber_posts_spinner').toggleClass('hide', !f);
	};
	
	toggle_loading(true);
	$.post("?a=grabber&sa=load", {
		O: remote_topics_offset, 
		L: LOAD_CHUNK, 
		sort: settings.sort, 
		mode: settings.mode, 
		content: settings.contentFilter, 
		exclude: exclude.join(","), 
		gid: settings.gid
	}, function (res) {
		toggle_loading(false);
		
		if (res.success) {
			max_offset = res.total;
			remote_topics_offset += LOAD_CHUNK;
			
			console.log(res.sql);
			console.log('data: ' + res.time_data + ', list: ' + res.time_list);
			
			if (res.items.length) {
				console.log("loaded " + res.items.length + "with chunk " + LOAD_CHUNK);
				for (var i = 0; i < res.items.length; ++i) {
					var item = res.items[i];
					if (settings.sort == "RAND")
						exclude.push(item.time + '_' + item.remote_id);
					all_topics[item.source_type + '_' + item.source_id + '_' + item.remote_id] = item;
					all_topics_array.push(item);
				}
				onScroll();
			} else {
				remote_topics_end = true;
				$('#grabber_posts').append(tpl.noPosts());
			}
			
			if (settings.sort != "RAND")
				$('#pagenav').html(pagenav('', remote_topics_offset - LOAD_CHUNK, LOAD_CHUNK, max_offset));
		} else if (res.error) {
			$('#grabber_posts').append(tpl.error({
				error: html_wrap(res.error) + '<br /><pre>' + html_wrap(res.sql) + '</pre>'
			}));
		}
	}).error(function () {
		toggle_loading(false);
		setTimeout(loadPosts, 0);
	});
}

function onScroll() {
	var scroll = $window.scrollTop(), 
		inner = $window.innerHeight(), 
		scroll_max = $document.innerHeight() - inner;
	
	if (scroll_max - scroll <= inner && !lock_scroll_events) {
		lock_scroll_events = true;
		setTimeout(function () {
			if (loadCachedPosts(TOPICS_CHUNK) < TOPICS_CHUNK)
				loadPosts();
			lock_scroll_events = false;
		}, 0);
	}
}

function loadCachedPosts(size) {
	var content = [];
	for (var i = topics_offset; i < Math.min(all_topics_array.length, topics_offset + size); ++i) {
		var topic = all_topics_array[i];
		content.push(tpl.post(topic));
	}
	if (content.length)
		$('#grabber_posts').append(content);
	topics_offset += content.length;
	
	return content.length;
}

//
});