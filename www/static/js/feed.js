define(['jquery', 'class', 'utils', 'emojione', 'upload', 'meme'], function ($, Class, utils, emojione) {
//
var TOPICS = [
	[-1, 'Не выбрано'], 
	[1, 'Арт'], 
	[7, 'IT'], 
	[12, 'Игры'], 
	[16, 'Музыка'], 
	[19, 'Фото'], 
	[21, 'Наука'], 
	[23, 'Спорт'], 
	[25, 'Туризм'], 
	[26, 'Кино'], 
	[32, 'Юмор'], 
	[43, 'Стиль'], 
];

var tpl = {
	attaches: function (data, custom) {
		var attaches = [];
		
		for (var i = 0; i < data.length; ++i) {
			var att = data[i];
			var html = '';
			
			var deleted = custom.checkAttach && custom.checkAttach(att);
			
			// Костыль!
			if (deleted)
				att.deleted = true;
			
			var att_id = att.id;
			if (att.type == 'photo' || (att.type == 'doc' && !('length' in att.thumbs))) {
				var aspect = att.w / att.h, 
					thumb = findBestThumb(att.thumbs, 640), 
					photo_w = thumb.w, 
					photo_h = Math.round(thumb.w / aspect);
				
				var src = att.type == 'photo' ? thumb.orig_src : att.page_url || att.url;
				
				var aspect = photo_h / photo_w, 
					max_width = photo_h > photo_w ? 
						Math.min(320 / aspect, photo_w) : 
						Math.min(320, photo_w), 
					tile = att.type == 'doc' ? '100%' : (photo_h / photo_w > 1.2 ? '25%' : '50%');
				
				html += 
					'<div class="center post-pic' + (deleted ? ' deleted' : '') + ' js-attach' + (att.ext == 'gif' ? ' js-gif' : '') + '" ' + 
							(
								att.ext == 'gif' ? 
								' data-gif="' + att.url + '" data-mp4="' + (att.mp4 || "") + '"' + 
								' data-width="' + att.w + '" data-height="' + att.h + '"' : 
								' '
							) + 
							'style="padding: 10px 0;margin: 0 2px;display: inline-block;max-width: ' + max_width + 'px; width: ' + tile + ';" data-id="' + att_id + '">' + 
						'<div class="pad_b" style="font-size: 13px">' + 
							att.w + ' x ' + att.h + 
						'</div>' + 
						'<a href="' + src + '" target="_blank" class="aspect" style="padding-top: ' + (aspect * 100) + '%">' + 
							(att.mp4 ? 
								'<video poster="' + thumb.src + '" src="' + att.mp4 + '" class="preview" preload="none" />' : 
								'<img src="' + thumb.src + '" alt="" class="preview" />'
							) + 
							(!deleted && att.type == 'photo' ? 
								'<span class="post-attach_edit js-attach_edit inl post-show_edit">' + 
									'<img src="/i/img/edit_image.png" alt="" />' + 
								'</span>' : ''
							) + 
							(!deleted ? 
								'<span class="post-attach_remove js-attach_remove inl post-show_edit">' + 
									'<img src="/i/img/remove_image.png" alt="" />' + 
								'</span>' : ''
							) + 
							(att.ext == 'gif' ? '<img src="/i/img/play.svg" alt="" class="post-doc_play js-gif_hide" />' : '') + 
							(att.type == 'doc' ? '<div class="post-doc_title js-gif_hide"><b>' + utils.htmlWrap(att.title) + '</b></div>' : '') + 
						'</a>' + 
					'</div>';
			} else if (att.type == 'doc') {
				html += 
					'<div class="post-attach' + (deleted ? ' deleted' : '') + ' js-attach" data-id="' + att_id + '">' + 
						'<b class="m">Документ:</b> <a href="' + (att.page_url || att.url) + '" target="_blank" class="m">' + 
							utils.htmlWrap(att.title) + 
						'</a>' + 
						(!deleted ? 
							'<a href="#" class="post-show_edit inl js-attach_remove right">' + 
								'<img src="/i/img/remove.png" alt="" class="m" /> ' + 
							'</a>' : ''
						) + 
					'</div>';
			} else if (att.type == 'audio') {
				html += 
					'<div class="post-attach' + (deleted ? ' deleted' : '') + ' js-attach" data-id="' + att_id + '">' + 
						'<b class="m">Аудио:</b> <span class="m">' + utils.htmlWrap(att.title) + '</span>' + 
						(!deleted ? 
							'<a href="#" class="post-show_edit inl js-attach_remove right">' + 
								'<img src="/i/img/remove.png" alt="" class="m" /> ' + 
							'</a>' : ''
						) + 
					'</div>';
			} else if (att.type == 'link') {
				html += 
					'<div class="post-attach' + (deleted ? ' deleted' : '') + ' js-attach" data-id="' + att_id + '">' + 
						'<b class="m">Ссылка:</b> <a href="' + utils.htmlWrap(att.url) + '" target="_blank" class="m">' + 
							utils.htmlWrap(att.title) + 
						'</a>' + 
						(!deleted ? 
							'<a href="#" class="post-show_edit inl js-attach_remove right">' + 
								'<img src="/i/img/remove.png" alt="" class="m" /> ' + 
							'</a>' : ''
						) + 
						'<br />' + 
						utils.htmlWrap(att.description) + 
					'</div>';
			} else if (att.type == 'geo') {
				html += 
					'<div class="post-attach' + (deleted ? ' deleted' : '') + ' js-attach" data-id="' + att_id + '">' + 
						'<b class="m">GEO:</b> <span class="m">' + att.lat + ", " + att.lng + '</span>' + 
						(!deleted ? 
							'<a href="#" class="post-show_edit inl js-attach_remove right">' + 
								'<img src="/i/img/remove.png" alt="" class="m" /> ' + 
							'</a>' : ''
						) + 
					'</div>';
			} else if (att.type == 'poll') {
				html += 
					'<div class="post-attach' + (deleted ? ' deleted' : '') + ' js-attach oh" data-id="' + att_id + '">' + 
						'<b class="m">Опрос:</b> <span class="m">' + utils.htmlWrap(att.question) + (att.anon ? ' <span class="grey">(Анонимный)</span>' : '') + '</span>' + 
						(!deleted ? 
							'<a href="#" class="post-show_edit inl js-attach_remove right">' + 
								'<img src="/i/img/remove.png" alt="" class="m" /> ' + 
							'</a>' : ''
						) + 
						'<br />';
				if (att.answers) {
					for (var j = 0; j < att.answers.length; ++j)
						html += (j + 1) + ') ' + utils.htmlWrap(att.answers[j]) + '<br />';
				}
				html += '</div>';
			} else if (att.type == 'album' || att.type == 'market_album' || att.type == 'market' || att.type == 'video') {
				var aspect = att.w / att.h, 
					thumb = findBestThumb(att.thumbs, 640), 
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
					'<div class="center post-pic' + (deleted ? ' deleted' : '') + ' js-attach" ' + 
							'style="padding: 10px 0;margin: 0 2px;display: inline-block;max-width: ' + max_width + 'px; width: 100%;" data-id="' + att_id + '">' + 
						'<a href="' + att.url + '" target="_blank" class="aspect oh" style="padding-top: ' + (aspect * 100) + '%">' + 
							'<img src="' + thumb.src + '" alt="" class="preview" />' + 
							'<div class="post-doc_title">' + 
								'<b>' + names[att.type] + ': ' + utils.htmlWrap(att.title) + '</b><br />' + 
								utils.htmlWrap(att.description) + 
							'</div>' + 
							(!deleted ? 
								'<span class="post-attach_remove js-attach_remove inl post-show_edit">' + 
									'<img src="/i/img/remove_image.png" alt="" />' + 
								'</span>' : ''
							) + 
						'</a>' + 
					'</div>';
			} else {
				html += 'UNKNOWN: ' + att.type;
				console.log(att);
			}
			
			attaches.push(html);
		}
		
		return attaches;
	}, 
	post: function (data, custom) {
		var url, owner_url;
		if (data.source_type == 'VK') {
			url = 'https://vk.com/wall' + data.remote_id;
			owner_url = 'https://vk.com' + data.owner_url;
		} else if (data.source_type == 'OK') {
			url = 'https://ok.ru/group/' + data.source_id + '/topic/' + data.remote_id;
			owner_url = data.owner_url;
		} else if (data.source_type == 'INSTAGRAM') {
			url = 'https://www.instagram.com/p/' + data.remote_id;
			owner_url = data.owner_url;
		} else if (data.source_type == 'PINTEREST') {
			url = 'https://www.pinterest.ru/pin/' + data.remote_id;
			owner_url = data.owner_url;
		}
		
		var post_class = '';
		if (data.list == 'suggests' && data.type == 'postpone')
			post_class = ' row-blue';
		
		if (data.special)
			post_class = ' row-yellow';
		
		var attaches = tpl.attaches(data.attaches || [], custom);
		
		var topics_html = '';
		$.each(TOPICS, function () {
			topics_html += '<option value="' + this[0] + '">' + this[1] + '</option>';
		});
		
		var html =
			'<div class="row js-post wrapper' + post_class + '" data-id="' + data.remote_id + '" data-type="' + data.source_type + '" data-gid="' + data.source_id + '"' + 
					' data-post_type="'+ data.type +'">' + 
				'<div class="oh">' + 
					'<div class="left post-preview relative">' + 
						'<img src="' + data.owner_avatar + '" alt="" width="50" height="50" />' + 
					'</div>' + 
					'<div class="oh">' + 
						'<span class="time js-post_time">' + 
							'<span class="m">' + utils.getHumanDate(data.time) + '</span> ' + 
						'</span>' + 
						'<a href="' + owner_url + '" target="_blank" class="m"><b class="post-author post-author-' + data.source_type + '">' + data.owner_name + '</b></a> ' + 
						'<a href="' + url + '" target="_blank">' + 
							'<img src="/i/img/external.svg" width="14" height="14" class="m" alt="" />' + 
						'</a>' + 
						(data.delta ? 
							' &nbsp;<span class="green m js-post_delta">+' + calcPostDelta(data.delta) + '</span> ' : 
							''
						) + 
						'<br />' + 
						'<div class="post-text post-hide_edit emoji">' + prepareText(utils.htmlWrap(prepareSpellCheck(data.text, data.spell))) + '</div>' + 
						(data.comment_text ? 
							'<div class="post-text post-hide_edit emoji pad_t">' + 
								'<div class="row-blue row">' + 
									'<b>Первонах комментарий:</b>' + 
									'<div class="pad_t">' + 
										prepareText(utils.htmlWrap(prepareSpellCheck(data.comment_text, data.comment_spell))) + 
									'</div>' + 
								'</div>' + 
							'</div>' : ''
						) + 
					'</div>' + 
				'</div>' + 
				'<div class="js-post_attach_editor hide"></div>' + 
				'<div class="js-post_editor">' + 
					'<div class="post-show_edit pad_t">' + 
						'<div class="js-post_textarea_wrap">' + 
							'<div class="pad_b oh">' + 
								'<label><input type="checkbox" name="text_add" value="1" class="js-post_textarea_enable" ' + 
									((data.source_type == 'INSTAGRAM' || data.source_type == 'PINTEREST') ? '' : ' checked="checked"') + ' /> Использовать текст</label>' + 
								'<a href="#" class="right js-post_action" data-action="spellcheck">Проверить текст</a>' + 
							'</div>' + 
							'<div class="js-post_spell_result"></div>' + 
							'<textarea rows="10" class="js-post_textarea" name="text"></textarea>' + 
						'</div>' + 
						
						'<div class="js-post_textarea_wrap pad_t">' + 
							'<div class="pad_b oh">' + 
								'<label><input type="checkbox" name="text_add" value="1" data-action="enable_comment" class="js-post_action" /> Первонах комментарий</label>' + 
								'<a href="#" class="right js-post_action hide" data-action="spellcheck">Проверить текст</a>' + 
							'</div>' + 
							'<div class="js-post_comment_edit hide">' + 
								'<div class="js-post_spell_result"></div>' + 
								'<textarea rows="10" class="js-post_comment_textarea" name="comment_text"></textarea>' + 
							'</div>' + 
						'</div>' + 
						
						'<div class="pad_b pad_t oh red">' + 
							'<label><input type="checkbox" name="from_web" value="1" class="js-post_web_enable" /> Убрать шестернь</label>' + 
						'</div>' + 
						
						'<div class="pad_b pad_t">' + 
							'<label class="lbl">Тематика:</label><br />' + 
							'<select class="js-post_topic_id">' + 
								topics_html + 
							'</select>' + 
						'</div>' + 
						
						'<div class="js-upload_form pad_t" data-action="/?a=vk_posts/upload&amp;gid=' + custom.gid + '" data-id="vk_upload">' + 
							'<div class="js-upload_input"></div>' + 
							'<div class="js-upload_files pad_t hide"></div>' + 
						'</div>' + 
					'</div>' + 
					'<div class="js-post_attaches post-attaches' + (!attaches.length ? ' hide' : '') + '">' + attaches.join('') + '</div>' + 
					'<div class="pad_t oh' + (!data.likes && !data.comments && !data.reposts ? ' hide' : '') + '">' + 
						'<img src="/i/img/like.svg" class="m" width="16" height="16" /> <span class="darkblue">' + data.likes + '</span>&nbsp;&nbsp;&nbsp;' + 
						'<img src="/i/img/comment.svg" class="m" width="16" height="16" /> <span class="darkblue">' + data.comments + '</span>&nbsp;&nbsp;&nbsp;' + 
						'<img src="/i/img/repost.svg" class="m" width="16" height="16" /> <span class="darkblue">' + data.reposts + '</span>' + 
					'</div>' + 
					'<div class="js-post_next_date grey pad_t post-show_edit"></div>' + 
					custom.toolbar + 
				'</div>' + 
			'</div>';
		return html;
	},
	spinner: function (msg) {
		return '<img src="/i/img/spinner2.gif" alt="" class="m" /> <span class="m">' + msg + '</span>';
	}, 
	error: function (msg) {
		return '<span class="red">' + msg + '</span>';
	}, 
	period: function (period) {
		return '<div class="grey center row">' + period + '</div>';
	}, 
	spellSuggests: function (text) {
		return '<span style="background: #dae1e8; color: #066; margin: 0 10px">' + utils.htmlWrap(text) + '</span>';
	}, 
	spellResult: function (data) {
		return '<div class="row bord wrapper">' + data.text + '</div>';
	}
};

var VkFeed = Class({
	Constructor: function (wrap, options) {
		var self = this;
		
		self.opts = $.extend({
			showPeriod:		false, 
			checkAttach: 	false
		}, options);
		
		self.posts = {};
		self.actions = {};
		self.wrap = wrap;
		self.last_period = 0;
		self.busy = 0;
		
		wrap.on('meme:save', '.js-post', function (e, result) {
			var el = $(this);
			
			el.find('.js-post_editor').removeClass('hide');
			el.find('.js-post_attach_editor')
				.addClass('hide')
				.memeEditor(false);
			
			result.data.el.find('.js-file_url').val(findBestThumb(result.data.att.thumbs, 99999).src);
			result.data.el.find('.js-file_url_btn').trigger('click', {
				cover:	result.image, 
				data:	result.data.att, 
				offset:	result.options.offset
			});
			
			var top = result.data.el.offset().top;
			if (top < $(window).scrollTop() || top > $(window).scrollTop() + $(window).innerHeight())
				$('html, body').scrollTop(top);
		}).on('meme:cancel', '.js-post', function (e) {
			var el = $(this);
			
			el.find('.js-post_editor').removeClass('hide');
			el.find('.js-post_attach_editor')
				.addClass('hide')
				.memeEditor(false);
		}).on('click', '.js-post_spell_suggests', function (e) {
			e.preventDefault();
			e.stopPropagation();
			e.stopImmediatePropagation();
			
			var el = $(this);
			el.removeClass("js-post_spell_suggests");
			el.after(tpl.spellSuggests(el.attr("title")));
		}).on('click', '.js-attach_edit', function (e) {
			e.preventDefault();
			e.stopPropagation();
			e.stopImmediatePropagation();
			
			var el = $(this), 
				att_wrap = el.parents('.js-attach'), 
				wrap = el.parents('.js-post'), 
				post_id = wrap.data('type') + '_' + wrap.data('gid') + '_' + wrap.data('id'), 
				post = self.posts[post_id];
			
			var att;
			for (var i = 0, l = post.attaches.length; i < l; ++i) {
				att = post.attaches[i];
				if (att_wrap.data("id") == att.id)
					break;
			}
			
			wrap.find('.js-post_editor').addClass('hide');
			wrap.find('.js-post_attach_editor')
				.data('id', att.id)
				.removeClass('hide')
				.memeEditor({
					image:	findBestThumb(att.thumbs, 99999).src, 
					width:	att.w, 
					height:	att.h, 
					data:	{att: att, el: wrap}
				});
		}).on('click', '.js-attach_remove', function (e) {
			e.preventDefault();
			e.stopPropagation();
			e.stopImmediatePropagation();
			
			var el = $(this), 
				att_wrap = el.parents('.js-attach'), 
				wrap = el.parents('.js-post'), 
				post_id = wrap.data('type') + '_' + wrap.data('gid') + '_' + wrap.data('id'), 
				post = self.posts[post_id];
			
			for (var i = 0, l = post.attaches.length; i < l; ++i) {
				var att = post.attaches[i];
				if (att_wrap.data("id") == att.id) {
					att_wrap.toggleClass('deleted');
					att.deleted = att_wrap.hasClass('deleted');
				}
			}
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
		}).on('change', '.js-post_web_enable', function (e) {
			window.localStorage["post_web_enable"] = $(this).prop("checked") ? 1 : "";
		}).on('change', '.js-post_topic_id', function (e) {
			window.localStorage["post_topic_id"] = $(this).val();
		}).on('click', '.js-post_action', function (e) {
			if ($(this).prop("type") != "checkbox" && $(this).prop("type") != "radio")
				e.preventDefault();
			
			if (self.busy) {
				alert("Дождитесь загрузки файла!");
				return;
			}
			
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
		}).on('file_uploaded', '.js-post', function (e, data) {
			e.preventDefault();
			var el = $(this), 
				post_id = el.data('type') + '_' + el.data('gid') + '_' + el.data('id'), 
				post = self.posts[post_id];
			
			var attaches = tpl.attaches(data.response.data.attaches, {
				checkAttach:	self.opts.checkAttach, 
				gid:			self.opts.gid
			}).join('');
			
			var old_att = data.data, 
				att = data.response.data.attaches[0];
			
			if (old_att) {
				for (var i = 0; i < post.attaches.length; ++i) {
					if (post.attaches[i].id == old_att.id) {
						post.attaches[i] = att;
						break;
					}
				}
				el.find('.js-post_attaches').find('[data-id="' + old_att.id + '"]').replaceWith(attaches);
			} else {
				post.attaches.push(att);
				el.find('.js-post_attaches').removeClass('hide').append(attaches);
			}
		}).on('file_upload_start', '.js-post', function (e) {
			++self.busy;
		}).on('file_upload_end', '.js-post', function (e) {
			--self.busy;
		});
		
		self.addAction('enable_comment', function (e) {
			var el = e.target, 
				wrap = e.wrap, 
				post = e.post;
			
			var textarea_wrap = el.parents('.js-post_textarea_wrap');
			textarea_wrap
				.find('.js-post_comment_edit, [data-action="spellcheck"]')
				.toggleClass('hide', !el.prop("checked"));
		});
		
		self.addAction('spellcheck', function (e) {
			var el = e.target, 
				wrap = e.wrap, 
				post = e.post, 
				textarea = textarea_wrap.find('.js-post_comment_textarea, .js-post_textarea'), 
				emojiarea = textarea.data('emojioneArea');
			
			var text = $.trim(emojiarea ? emojiarea.getText() : post.text);
			
			el.text('Проверяем...').css("opacity", 0.5);
			
			var done = function () {
				el.text('Проверить текст').css("opacity", 1);
			};
			
			var textarea_wrap = el.parents('.js-post_textarea_wrap');
			
			$.api('/?a=vk_posts/spellcheck', {text: text}, function (res) {
				done();
				
				textarea_wrap.find('.js-post_spell_result').html(tpl.spellResult({
					text:	prepareText(utils.htmlWrap(prepareSpellCheck(text, res.spell)))
				}));
			}, function () {
				done();
				
				textarea_wrap.find('.js-post_spell_result').html(tpl.spellResult({
					text:	tpl.error('Ошибка проверки.')
				}));
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
					
					attributes: {
						spellcheck:		true
					},
					
					tonesStyle: "checkbox", 
					events: {
						change: function () {
							wrap.find('.js-post_textarea_enable').prop("checked", true);
						}
					}
				});
			
			try {
				wrap.find('.js-post_web_enable').prop("checked", !!window.localStorage["post_web_enable"]);
				wrap.find('.js-post_topic_id').val(window.localStorage["post_topic_id"] || -1);
			} catch (e) { }
			
			wrap.find('.js-post_comment_textarea')
				.val(e.post.comment_text)
				.emojioneArea({
					pickerPosition: "bottom", 
					filtersPosition: "bottom", 
					autocomplete: false, 
					
					attributes: {
						spellcheck:		true
					},
					
					tonesStyle: "checkbox"
				});
			
			if (e.post.comment_text)
				wrap.find('.js-post_action[data-action="enable_comment"]').click();
			
			wrap.genericUploader();
			
			self.updateNextDate();
			
			var top = wrap.offset().top;
			if (top < $(window).scrollTop() || top > $(window).scrollTop() + $(window).innerHeight())
				$('html, body').scrollTop(top);
		});
	}, 
	updateNextDate: function () {
		var self = this;
		
		var post_next_date = self.wrap.find('.js-post_next_date');
		post_next_date.html(tpl.spinner('Расчёт времени публикации...'));
		
		if (self.post_next_date_query)
			self.post_next_date_query.abort();
		
		if (self.post_next_date_timeout) {
			clearTimeout(self.post_next_date_timeout);
			self.post_next_date_timeout = false;
		}
		
		self.post_next_date_query = $.api("/?a=vk_posts/next_date", {
			gid:			self.opts.gid
		}, function (res) {
			post_next_date.html('Дата публикации: ' + utils.getHumanDate(res.date));
		}, function () {
			self.post_next_date_timeout = setTimeout(function () {
				self.updateNextDate();
			}, 2000);
		});
	}, 
	addAction: function (name, callback) {
		var self = this;
		self.actions[name] = callback;
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
				toolbar:		options.toolbar ? options.toolbar(post) : '', 
				checkAttach:	self.opts.checkAttach, 
				gid:			self.opts.gid
			});
		}
		
		self.wrap.append(html);
	}
});

function findBestThumb(hash, size) {
	var ret;
	for (var w in hash) {
		if (!hash.hasOwnProperty(w))
			continue;
		if ((!ret && w > size) || w <= size)
			ret = {src: hash[w], w: +w};
		if (ret)
			ret.orig_src = hash[w];
	}
	
	if (!ret) {
		console.log('findBestThumb(', hash, size, ') = ', ret);
		ret = {
			w: 320, 
			h: 320, 
			src: "/i/img/transparent.gif"
		};
	}
	
	return ret;
}

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

function prepareSpellCheck(text, spell) {
	if (spell) {
		var last_index = 0, new_text = "";
		for (var i = 0, l = spell.length; i < l; ++i) {
			var entry = spell[i], 
				word_offset = entry[0], 
				word_length = entry[1], 
				suggests = entry[2], 
				word = text.substring(word_offset, word_offset + word_length);
			
			new_text += text.substring(last_index, word_offset);
			new_text += "[spell=" + suggests.join(", ") + "]" + word + "[/spell]";
			last_index = word_offset + word_length;
		}
		new_text += text.substring(last_index);
		
		text = new_text;
	}
	
	return text;
}

function prepareText(text, spell) {
	var URL_RE = /(?:([!()?.,\s\n\r]|^)((https?:\/\/)?((?:[a-z0-9_\-]+\.)+(?:[a-z]{2,7}|xn--p1ai|xn--j1amh|xn--80asehdb|xn--80aswg))(\/.*?)?(\#.*?)?)(?:[.!:;,*()]*([\s\r\n]|$))|([!()?.,\s\n\r]|^)((https?:\/\/)?((?:[a-z0-9а-яєґї_\-]+\.)+(?:рф|укр|онлайн|сайт|срб|su))(\/.*?)?(\#.*?)?)(?:[.!:;,*()]*([\s\r\n]|$))|([!()?.,\s\n\r]|^)((https?:\/\/)((?:[a-z0-9а-яєґї_\-]+\.)+(?:[a-z]{2,7}|рф|укр|онлайн|сайт|срб|su))(\/.*?)?(\#.*?)?)(?:[.!:;,*()]*([\s\r\n]|$)))/gi;
	
	// Replace spellcheck tags
	text = text.replace(/\[spell=([^\]]*)\](.*?)\[\/spell\]/gim, function (_, suggests, word) {
		return '<span class="spell cursor js-post_spell_suggests" title="' + suggests + '">' + word + '</span>';
	});
	
	// Replace VK internal links
	text = text.replace(/\[(club|public|id)(\d+)\|([^\]]+)\]/gim, function (_, type, id, title) {
		return '<a href="https://vk.com/' + type + id + '" target="_blank">' + title + '</a>';
	});
	
	// Replace other links
	text = text.replace(URL_RE, function () {
		var m = arguments, 
			offset = m[4] ? 0 : (m[7 + 4] ? 7 : 14), 
			url = m[offset + 2];
		return m[offset + 1] + '<a href="' + url + '" target="_blank">' + url + '</a>' + m[offset + 7];
	});
	
	// Replace emoji and new lines
	return emojione.toImage(text).replace(/\r\n|\r|\n/gi, "<br />");
}

return VkFeed;
//
});
