define(['jquery', 'utils', 'feed', 'suggested/preload', 'emojionearea', 'api'], function ($, utils, VkFeed, posts) {
//

// Сколько топиков загружаем за один раз
var TOPICS_CHUNK = 10;

var tpl = {
	toolbarCommon: function () {
		var html =
			'<div class="pad_t grey js-post_status_text hide"></div>' + 
			'<div class="js-post_toolbar_deleted hide">' + 
				'<div class="pad_t oh">' + 
					'<button class="btn btn-cancel js-post_action m" data-action="delete" data-restore="1">Восстановить</button> ' + 
				'</div>' + 
			'</div>';
		
		return html;
	}, 
	toolbarSuggests: function (post) {
		var html =
			tpl.toolbarCommon() + 
			'<div class="pad_t oh js-post_toolbar">' + 
				'<button class="btn js-post_action post-hide_edit m inl" data-action="edit">Редактор</button> ' + 
				'<button class="btn js-post_action post-show_edit m inl" data-action="queue">' + 
					'<img src="/i/img/spinner.gif" alt="" class="m js-spinner hide" /> ' + 
					'В очередь' + 
				'</button> ' + 
				
				'<button class="btn btn-green' + (!post.anon ? ' btn-disabled' : '') + ' js-post_action m" data-action="anon">Анон</button> ' + 
				
				'<div class="right">' + 
					'<button class="btn btn-delete js-post_action m" data-action="delete">&nbsp;x&nbsp;</button>' + 
				'</div>' + 
			'</div>';
		
		return html;
	}, 
	toolbarPostponed: function (post) {
		var html =
			tpl.toolbarCommon() + 
			'<div class="pad_t oh js-post_toolbar">' + 
				'<button class="btn js-post_action post-hide_edit m inl" data-action="edit">Редактор</button> ' + 
				'<button class="btn js-post_action post-show_edit m inl" data-action="save">' + 
					'<img src="/i/img/spinner.gif" alt="" class="m js-spinner hide" /> ' + 
					'Сохранить' + 
				'</button> ' + 
				
				'<button class="btn btn-green' + (!post.anon ? ' btn-disabled' : '') + ' js-post_action post-show_edit m inl" data-action="anon">Анон</button> ' + 
				
				'<div class="right">' + 
					'<button class="btn btn-delete js-post_action m" data-action="delete">&nbsp;x&nbsp;</button>' + 
				'</div>' + 
			'</div>';
		
		if (post.scheduled)
			html += '<div class="pad_t green">В очереди на постинг</div>';
		
		return html;
	}, 
	toolbar: function (post) {
		if (post.type != 'post') {
			if (options.list == 'suggests') {
				return tpl.toolbarSuggests(post);
			} else if (options.list == 'postponed') {
				return tpl.toolbarPostponed(post);
			}
		}
		return '';
	}, 
	error: function (msg) {
		return '<span class="red">' + msg + '</span>';
	}, 
	success: function (msg) {
		return '<span class="green">' + msg + '</span>';
	}, 
	spinner: function (msg) {
		return '<img src="/i/img/spinner2.gif" alt="" class="m" /> <span class="m">' + msg + '</span>';
	}
};

var feed, 
	options, 
	lock_scroll_events, 
	topics_offset = 0;

$(init);

function init() {
	options = $('#suggested_data').data();
	
	feed = new VkFeed($('#vk_posts'), {
		showPeriod:	true, 
		gid:		options.gid
	});
	
	feed.addAction('anon', function (e) {
		e.post.anon = !e.post.anon;
		e.target.toggleClass('btn-disabled', !e.post.anon);
	});
	
	var post_save = function (e) {
		var el = e.target, 
			wrap = e.wrap, 
			status = wrap.find('.js-post_status_text'), 
			post = e.post, 
			textarea = wrap.find('.js-post_textarea'), 
			emojiarea = textarea.data('emojioneArea');
		
		if (el.attr("disabled"))
			return;
		
		var post_data = {
			gid:		options.gid, 
			id:			e.post.id, 
			signed:		e.post.anon ? 0 : 1, 
			type:		post.type, 
			message:	$.trim(emojiarea ? emojiarea.getText() : post.text), 
			attachments: []
		};
		
		for (var i = 0, l = post.attaches.length; i < l; ++i) {
			var att = post.attaches[i];
			
			if (att.deleted)
				continue;
			
			if (att.type == 'geo') {
				post_data.lat = att.lat;
				post_data.long = att.lng;
			} else if (att.type == 'link') {
				post_data.attachments.push(att.url);
			} else {
				post_data.attachments.push(att.id);
			}
		}
		
		status.removeClass('hide');
		
		if (!post_data.message.length && !post_data.attachments.length) {
			status.html(tpl.error('Пост должен быть или с вложениями или с текстом.'));
			return;
		}
		
		post_data.attachments = post_data.attachments.join(',');
		
		var lang = ({
			queue: {
				action:		"/?a=queue", 
				spinner:	"Добавляем в очередь...", 
				success:	"Пост успешно добавлен в очередь.", 
				fail:		"Ошибка добавления в очередь! Попробуйте снова."
			}, 
			save: {
				action:		"/?a=post/edit", 
				spinner:	"Сохраняем пост...", 
				success:	"Пост успешно отредактирован.", 
				fail:		"Ошибка сохранения! Попробуйте снова."
			}
		})[e.type];
		
		status.html(tpl.spinner(lang.spinner));
		el.attr('disabled', 'disabled');
		
		$.api(lang.action, post_data, function (res) {
			el.removeAttr('disabled');
			if (res.success) {
				status.html(tpl.success(lang.success));
				
				if (e.type == 'queue') {
					wrap.find('.js-post_toolbar').addClass('hide');
					
					if (emojiarea)
						emojiarea.disable();
				}
			} else {
				status.html(tpl.error(res.error));
			}
		}, function () {
			el.removeAttr('disabled');
			status.html(tpl.error(lang.fail));
		});
	};
	
	feed.addAction('queue', post_save);
	feed.addAction('save', post_save);
	
	feed.addAction('delete', function (e) {
		var el = e.target, 
			wrap = e.wrap, 
			restore = !!el.data('restore'), 
			status = e.wrap.find('.js-post_status_text');
		
		if (el.attr("disabled"))
			return;
		
		status.removeClass('hide').html(tpl.spinner(restore ? 'Восстанавливаем пост...' : 'Удаляем пост...'));
		el.attr('disabled', 'disabled');
		
		$.api("/?a=delete", {
			gid: options.gid, id: e.post.id, 
			restore: restore ? 1 : 0
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
	
	toggleSpinner(false);
	
	if (posts.length) {
		var $window = $(window), 
			$document = $(document);
		
		$window.on('scroll.suggested', function () {
			var scroll = $window.scrollTop(), 
				inner = $window.innerHeight(), 
				scroll_max = $document.innerHeight() - inner;
			
			if (scroll_max - scroll <= inner && !lock_scroll_events) {
				lock_scroll_events = true;
				setTimeout(function () {
					loadCachedPosts(TOPICS_CHUNK);
					lock_scroll_events = false;
				}, 0);
			}
		});
		
		loadCachedPosts(TOPICS_CHUNK);
	}
	
	
	$('#group_settings').on('change click keyup keydown', 'input', function () {
		recalcFreqSettings();
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
		recalcFreqSettings();
	});
	
	recalcFreqSettings();
}

function recalcFreqSettings() {
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

function toggleSpinner(flag) {
	$('#vk_posts_spinner').toggleClass('hide', !flag);
}

function loadCachedPosts(size) {
	var chunk = [];
	for (var i = topics_offset; i < Math.min(posts.length, topics_offset + size); ++i)
		chunk.push(posts[i]);
	
	if (chunk.length) {
		feed.addPosts(chunk, {toolbar: tpl.toolbar});
	} else {
		$(window).off('scroll.suggested');
	}
	
	topics_offset += chunk.length;
}

//
});
