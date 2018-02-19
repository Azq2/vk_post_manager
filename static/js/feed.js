define(['jquery', 'class', 'utils', 'emojione'], function ($, Class, utils, emojione) {
//
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
								'<img src="/i/img/remove_image.png" alt="" />' + 
							'</span>' + 
							(att.ext == 'gif' ? '<img src="/i/img/play.svg" alt="" class="post-doc_play js-gif_hide" />' : '') + 
							(att.type == 'doc' ? '<div class="post-doc_title js-gif_hide"><b>' + utils.htmlWrap(att.title) + '</b></div>' : '') + 
						'</a>' + 
					'</div>';
			} else if (att.type == 'doc') {
				console.log(att);
				html += 
					'<div class="post-attach deleted js-attach" data-id="' + att_id + '">' + 
						'<b>Документ:</b> <a href="' + (att.page_url || att.url) + '" target="_blank">' + 
							utils.htmlWrap(att.title) + 
						'</a>' + 
					'</div>';
			} else if (att.type == 'audio') {
				html += 
					'<div class="post-attach deleted js-attach" data-id="' + att_id + '">' + 
						'<b>Аудио:</b> ' + utils.htmlWrap(att.title) + 
					'</div>';
			} else if (att.type == 'link') {
				html += 
					'<div class="post-attach deleted js-attach" data-id="' + att_id + '">' + 
						'<b>Ссылка:</b> <a href="' + utils.htmlWrap(att.url) + '" target="_blank">' + 
							utils.htmlWrap(att.title) + 
						'</a><br />' + 
						utils.htmlWrap(att.description) + 
					'</div>';
			} else if (att.type == 'geo') {
				html += 
					'<div class="post-attach deleted js-attach" data-id="' + att_id + '">' + 
						'<b>GEO:</b> ' + att.lat + ", " + att.lng + 
					'</div>';
			} else if (att.type == 'poll') {
				html += 
					'<div class="post-attach deleted js-attach oh" data-id="' + att + '">' + 
						'<b>Опрос:</b> ' + utils.htmlWrap(att.question) + (att.anon ? ' <span class="grey right">Анонимный</span>' : '') + '<br />';
				if (att.answers) {
					for (var j = 0; j < att.answers.length; ++j)
						html += (j + 1) + ') ' + utils.htmlWrap(att.answers[j]) + '<br />';
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
								'<b>' + names[att.type] + ': ' + utils.htmlWrap(att.title) + '</b><br />' + 
								utils.htmlWrap(att.description) + 
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
	post: function (data, custom) {
		var url, owner_url;
		if (data.source_type == 'VK') {
			url = 'https://vk.com/wall' + data.remote_id;
			owner_url = 'https://vk.com' + data.owner_url;
		} else if (data.source_type == 'OK') {
			url = 'https://ok.ru/group/' + data.source_id + '/topic/' + data.remote_id;
			owner_url = data.owner_url;
		}
		
		var post_class = '';
		if (data.list == 'suggests' && data.type == 'postpone')
			post_class = ' row-blue';
		
		if (data.special)
			post_class = ' row-yellow';
		
		var html =
			'<div class="row js-post wrapper' + post_class + '" data-id="' + data.remote_id + '" data-type="' + data.source_type + '" data-gid="' + data.source_id + '">' + 
				'<div class="oh">' + 
					'<div class="left post-preview relative">' + 
						'<img src="' + data.owner_avatar + '" alt="" width="50" height="50" />' + 
					'</div>' + 
					'<div class="oh">' + 
						'<span class="time">' + 
							'<span class="m">' + utils.getHumanDate(data.time) + '</span> ' + 
						'</span>' + 
						'<a href="' + owner_url + '" target="_blank" class="m"><b class="post-author post-author-' + data.source_type + '">' + data.owner_name + '</b></a> ' + 
						'<a href="' + url + '" target="_blank">' + 
							'<img src="/i/img/external.svg" width="14" height="14" class="m" alt="" />' + 
						'</a>' + 
						(data.delta ? 
							' &nbsp;<span class="green m">+' + calcPostDelta(data.delta) + '</span> ' : 
							''
						) + 
						'<br />' + 
						'<div class="post-text post-hide_edit emoji">' + prepareText(utils.htmlWrap(data.text)) + '</div>' + 
					'</div>' + 
				'</div>' + 
				'<div class="post-show_edit pad_t">' + 
					'<textarea rows="10" class="js-post_textarea" name="text"></textarea>' + 
				'</div>' + 
				tpl.attaches(data.attaches || []) + 
				'<div class="pad_t oh' + (!data.likes && !data.comments && !data.reposts ? ' hide' : '') + '">' + 
					'<img src="/i/img/like.svg" class="m" width="16" height="16" /> <span class="darkblue">' + data.likes + '</span>&nbsp;&nbsp;&nbsp;' + 
					'<img src="/i/img/comment.svg" class="m" width="16" height="16" /> <span class="darkblue">' + data.comments + '</span>&nbsp;&nbsp;&nbsp;' + 
					'<img src="/i/img/repost.svg" class="m" width="16" height="16" /> <span class="darkblue">' + data.reposts + '</span>' + 
				'</div>' + 
				custom.toolbar + 
			'</div>';
		return html;
	},
	period: function (period) {
		return '<div class="grey center row">' + period + '</div>';
	}
};

var VkFeed = Class({
	Constructor: function (wrap, options) {
		var self = this;
		
		self.opts = $.extend({
			showPeriod: false
		}, options);
		
		self.posts = {};
		self.actions = {};
		self.wrap = wrap;
		self.last_period = 0;
		
		wrap.on('click', '.js-attach_remove', function (e) {
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
		}).on('click', '.js-post_action', function (e) {
			e.preventDefault();
			var wrap = $(this).parents('.js-post'), 
				el = $(this), 
				action = el.data('action'), 
				post_id = wrap.data('type') + '_' + wrap.data('gid') + '_' + wrap.data('id');
			
			self.actions[action] && self.actions[action].call(self, {
				id:			post_id, 
				type:		action, 
				post:		self.posts[post_id], 
				wrap:		wrap, 
				target:		el
			});
		});
		
		self.addAction('edit', function (e) {
			var wrap = e.wrap;
			
			wrap.addClass('post-edit');
			wrap.find('.js-post_textarea')
				.val(e.post.text)
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
	}, 
	addAction: function (name, callback) {
		var self = this;
		self.actions[name] = callback;
		return self;
	}, 
	execAction: function (name, post) {
		var self = this;
		self.actions[name] && self.actions[name].apply(self, [post]);
		return self;
	}, 
	addPosts: function (posts, options) {
		var self = this;
		
		options = $.extend({
			toolbar: null
		}, options);
		
		var html = '';
		for (var i = 0, l = Math.min(10, posts.length); i < l; ++i) {
			var post = posts[i];
			self.posts[post.source_type + '_' + post.source_id + '_' + post.remote_id] = post;
			
			if (self.opts.showPeriod) {
				var period = utils.getHumanDate(post.time, "date");
				if (self.last_period != period) {
					html += tpl.period(period);
					self.last_period = period;
				}
			}
			
			html += tpl.post(post, {
				toolbar: options.toolbar ? options.toolbar(post) : ''
			});
		}
		
		self.wrap.append(html);
		
		console.log('addPosts');
	}
});

function calcPostDelta(delta) {
	var out = "";
	
	var hours = Math.floor(delta / 3600);
	out += utils.pad(hours) + ':';
	delta -= hours * 3600;
	
	var minutes = Math.floor(delta / 60);
	out += utils.pad(minutes);
	delta -= minutes * 60;
	
	if (!hours && !minutes && delta)
		out += ':' + utils.pad(delta);
	
	return out;
}

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

return VkFeed;
//
});
