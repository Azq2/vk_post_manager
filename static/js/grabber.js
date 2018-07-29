define(['jquery', 'feed', 'url', 'upload', 'emojionearea', 'functions'], function ($, VkFeed, Url) {
//
var TOPICS_CHUNK = 10, 
	LOAD_CHUNK = 100;

var feed, 
	options, 
	lock_scroll_events, 
	remote_topics_offset = 0, 
	remote_topics_end = false, 
	remote_loading = false, 
	topics_offset = 0, 
	max_offset = 0, 
	exclude = [], 
	posts = [], 
	uniq_id, 
	posts, 
	busy_posts = {};

var tpl = {
	toolbar: function (post) {
		var html =
			'<div class="pad_t grey js-post_status_text hide"></div>' + 
			'<div class="js-post_toolbar_deleted hide">' + 
				'<div class="pad_t oh">' + 
					'<button class="btn btn-cancel js-post_action m" data-action="delete" data-restore="1">Восстановить</button> ' + 
				'</div>' + 
			'</div>' + 
			'<div class="pad_t oh js-post_toolbar">' + 
				'<button class="btn js-post_action post-hide_edit m inl" data-action="edit">Редактор</button> ' + 
				'<button class="btn js-post_action post-show_edit m inl" data-action="queue">' + 
					'<img src="/i/img/spinner.gif" alt="" class="m js-spinner hide" /> ' + 
					'В очередь' + 
				'</button> ' + 
				
				'<button class="btn js-post_action post-hide_edit m inl" data-action="anchor">' + 
					'<img src="i/img/anchor.svg" width="16" height="16" />' + 
				'</button>' + 
				
				'<div class="right">' + 
					'<button class="btn btn-delete js-post_action m" data-action="delete">Аццтой</button>' + 
				'</div>' + 
			'</div>';
		
		return html;
	}, 
	error: function (msg) {
		return '<span class="red">' + msg + '</span>';
	}, 
	success: function (msg) {
		return '<span class="green">' + msg + '</span>';
	}, 
	spinner: function (msg) {
		return '<img src="/i/img/spinner2.gif" alt="" class="m" /> <span class="m">' + msg + '</span>';
	}, 
	noPosts: function () {
		var html = 
			'<div class="row row-error wrapper center">' + 
				'Постов больше нет, но вы держитесь!<br />' + 
				'<a href="#" class="js-page" data-p="1">Перейти в начало?</a>'
			'</div>';
		return html;
	}, 
	loadError: function (data) {
		return '<div class="row row-error wrapper">' + data.error + '</div>';
	}
};

$(init);

function init() {
	options = $('#grabber_data').data();
	uniq_id = '[' + options.gid + '_' + options.sort + '_' + options.mode + ']';
	
	window.onbeforeunload = function() { 
		return !$.isEmptyObject(busy_posts) ? "Посты ещё добавляются. Точна???" : undefined; 
	};
	
	remote_topics_offset = +window.localStorage["saved_offset_" + uniq_id] || 0;
	$('#post_offset').val(remote_topics_offset);
	
	feed = new VkFeed($('#grabber_posts'), {
		showPeriod:		false, 
		gid:			options.gid, 
		checkAttach: function (att) {
			// Разрешаем только картинки и гифки
			return !att.type.match(/^photo|doc$/) || (att.type == 'doc' && !att.ext.match(/^png|jpg|jpeg|bmp|webp|gif$/i));
		}
	});
	
	feed.addAction('anchor', function (e) {
		window.localStorage["saved_offset_" + uniq_id] = e.post.n;
		location.href = location.href;
	});
	
	feed.addAction('delete', function (e) {
		var el = e.target, 
			wrap = e.wrap, 
			restore = !!el.data('restore'), 
			status = e.wrap.find('.js-post_status_text');
		
		if (busy_posts[e.post.id])
			return;
		
		if (el.attr("disabled"))
			return;
		
		status.removeClass('hide').html(tpl.spinner(restore ? 'Восстанавливаем пост...' : 'Удаляем пост...'));
		el.attr('disabled', 'disabled');
		
		$.api("/?a=grabber&sa=blacklist", {
			gid:			options.gid, 
			source_id:		e.post.source_id, 
			source_type:	e.post.source_type, 
			remote_id:		e.post.id, 
			restore:		restore ? 1 : 0
		}, function (res) {
			el.removeAttr('disabled');
			if (res.success) {
				if (restore) {
					status.addClass('hide').empty();
				} else {
					status.html(tpl.error('Пост успешно удалён.'));
				}
				
				wrap.find('.js-post_toolbar').toggleClass('hide', !restore);
				wrap.find('.js-post_toolbar_deleted').toggleClass('hide', restore);
			} else {
				status.html(tpl.error(res.error));
			}
		}, function () {
			el.removeAttr('disabled');
			status.html(tpl.error('Ошибка удаления! Попробуйте снова.'));
		});
	});
	
	var post_save = function (e) {
		var el = e.target, 
			wrap = e.wrap, 
			status = wrap.find('.js-post_status_text'), 
			post = e.post, 
			textarea = wrap.find('.js-post_textarea'), 
			emojiarea = textarea.data('emojioneArea'), 
			text_enable_cb = wrap.find('.js-post_textarea_enable'), 
			text_enable = !text_enable_cb.length || text_enable_cb.prop("checked");
		
		if (el.attr("disabled"))
			return;
		
		var post_data = {
			gid:		options.gid, 
			signed:		0, 
			type:		'new', 
			message:	text_enable ? $.trim(emojiarea ? emojiarea.getText() : post.text) : "", 
			attachments: []
		};
		
		var documents = [], images = [];
		for (var i = 0, l = post.attaches.length; i < l; ++i) {
			var att = post.attaches[i];
			
			if (att.deleted)
				continue;
			
			if (att.type == 'doc') {
				documents.push(att.url);
			} else if (att.type == 'photo') {
				var url, max_q = 0;
				$.each(att.thumbs, function (q, u) {
					if (q > max_q) {
						url = u;
						max_q = +q;
					}
				});
				images.push(url);
			}
		}
		
		status.removeClass('hide');
		
		if (!post_data.message.length && !documents.length && !images.length) {
			status.html(tpl.error('Пост должен быть или с вложениями или с текстом.'));
			return;
		}
		
		busy_posts[e.post.id] = 1;
		el.attr('disabled', 'disabled');
		
		var queue_post = function () {
			status.html(tpl.spinner("Добавляем в очередь..."));
			post_data.attachments = post_data.attachments.join(',');
			
			$.api("/?a=queue", post_data, function (res) {
				delete busy_posts[e.post.id];
				el.removeAttr('disabled');
				if (res.success) {
					status.html(tpl.success("Пост успешно добавлен в очередь."));
					
					// Блэклистим
					$.api("?a=grabber&sa=blacklist", {
						gid:			options.gid, 
						source_id:		e.post.source_id, 
						source_type:	e.post.source_type,
						remote_id:		e.post.id
					});
					
					wrap.find('.js-post_toolbar').addClass('hide');
					
					if (emojiarea)
						emojiarea.disable();
				} else {
					status.html(tpl.error(res.error));
				}
			}, function () {
				delete busy_posts[e.post.id];
				el.removeAttr('disabled');
				status.html(tpl.error("Ошибка добавления в очередь! Попробуйте снова."));
			});
		};
		
		if (!documents.length && !images.length) {
			// Если нет аттачей - сразу добавляем в очередь
			queue_post();
		} else {
			status.html(tpl.spinner("Становимся в очередь кражи аттачей..."));
			$.urlUploader({
				action:		"/?a=vk_upload&gid=" + options.gid, 
				images:		images, 
				documents:	documents, 
				
				onError: function (err) {
				delete busy_posts[e.post.id];
					el.removeAttr('disabled');
					status.html(tpl.error("Ошибка кражи аттачей: " + err));
				}, 
				onStateChanged: function (e) {
					status.html(tpl.spinner(e.status));
				}, 
				onDone: function (res) {
					post_data.attachments = res.attaches;
					queue_post();
				}
			});
		}
	};
	
	feed.addAction('queue', post_save);
	
	var source_filter = function (key_delete, key_add) {
		var url = new Url(location.href), 
			source = $('.js-grabber_filter_select').val();
		
		delete url.query[key_delete];
		
		if (source) {
			if (!url.query[key_add])
				url.query[key_add] = [];
			if (!(url.query[key_add] instanceof Array))
				url.query[key_add] = [url.query[key_add]];
			
			if (url.query[key_add].indexOf(source) < 0)
				url.query[key_add].push(source);
		} else {
			delete url.query[key_add];
		}
		
		location.href = url.toString();
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
	}).on('click', '.js-grabber_filter_whitelist', function (e) {
		e.preventDefault();
		source_filter('exclude[]', 'include[]');
	}).on('click', '.js-grabber_filter_blacklist', function (e) {
		e.preventDefault();
		source_filter('include[]', 'exclude[]');
	}).on('click', '.js-grabber_filter_delete', function (e) {
		e.preventDefault();
		
		var url = new Url(location.href), 
			source = $(this).data('id');
		
		$.each(['include[]', 'exclude[]'], function (_, k) {
			if (!url.query[k])
				url.query[k] = [];
			if (!(url.query[k] instanceof Array))
				url.query[k] = [url.query[k]];
			
			var new_values = [];
			$.each(url.query[k], function (_, v) {
				if (v != source)
					new_values.push(v);
			});
			url.query[k] = new_values;
			
			if (!url.query[k].length)
				delete url.query[k];
		});
		
		location.href = url.toString();
	});
	
	$(window).on('scroll.grabber', onScroll);
	
	$('#garbber_init_spinner').addClass('hide');
	
	loadPosts();
}

function toggleSpinner(flag) {
	remote_loading = flag;
	$('#garbber_posts_spinner').toggleClass('hide', !flag);
}

function onScroll() {
	var scroll = $(window).scrollTop(), 
		inner = $(window).innerHeight(), 
		scroll_max = $(document).innerHeight() - inner;
	
	if (scroll_max - scroll <= inner && !lock_scroll_events) {
		lock_scroll_events = true;
		setTimeout(function () {
			if (loadCachedPosts(TOPICS_CHUNK) < TOPICS_CHUNK)
				loadPosts();
			lock_scroll_events = false;
		}, 0);
	}
}

function loadPosts() {
	if (remote_topics_end || remote_loading)
		return;
	
	toggleSpinner(true);
	$.post("?a=grabber&sa=load", {
		O: remote_topics_offset, 
		L: LOAD_CHUNK, 
		sort: options.sort, 
		mode: options.mode, 
		include: options.include, 
		exclude: options.exclude, 
		content: options.contentFilter, 
		exclude_posts: exclude.join(","), 
		gid: options.gid
	}, function (res) {
		toggleSpinner(false);
		
		if (res.success) {
			max_offset = res.total;
			remote_topics_offset += LOAD_CHUNK;
			
			console.log(res.sql);
			
			if (res.items.length) {
				for (var i = 0; i < res.items.length; ++i) {
					var item = res.items[i];
					item.n = remote_topics_offset + i - LOAD_CHUNK;
					if (options.sort == "RAND")
						exclude.push(item.time + '_' + item.remote_id);
					posts.push(item);
				}
				
				onScroll();
			} else {
				remote_topics_end = true;
				$('#grabber_posts').append(tpl.noPosts());
			}
			
			if (options.sort != "RAND")
				$('#pagenav').html(pagenav('', remote_topics_offset - LOAD_CHUNK, LOAD_CHUNK, max_offset));
		} else if (res.error) {
			$('#grabber_posts').append(tpl.loadError({
				error: html_wrap(res.error) + '<br /><pre>' + html_wrap(res.sql) + '</pre>'
			}));
		}
	}).error(function () {
		toggleSpinner(false);
		setTimeout(loadPosts, 500);
	});
}

function loadCachedPosts(size) {
	var chunk = [];
	for (var i = topics_offset; i < Math.min(posts.length, topics_offset + size); ++i)
		chunk.push(posts[i]);
	
	if (chunk.length)
		feed.addPosts(chunk, {toolbar: tpl.toolbar});
	
	topics_offset += chunk.length;
	
	return chunk.length;
}

//
});
