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
				
				'<button class="btn btn-green js-post_action m" data-action="anon" data-state="1">Анон</button> ' + 
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
	error: function (err) {
		return '<span class="red">' + err + '</span>';
	}, 
	spinner: function (text) {
		return '<img src="/i/img/spinner2.gif" alt="" class="m" /> <span class="m">' + text + '</span>';
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
		showPeriod: true
	});
	
	feed.addAction('anon', function (e) {
		e.post.anon = !e.post.anon;
		e.target.toggleClass('btn-disabled', !e.post.anon);
	});
	
	feed.addAction('queue', function (e) {
		console.log('add post to queue');
	});
	
	feed.addAction('save', function (e) {
		console.log('save edited post');
	});
	
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
				status.text(tpl.error(res.error));
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
