$(function () {
//
var TOPICS_CHUNK = 10, 
	LOAD_CHUNK = 100;

var settings = $('#grabber_data').data(), 
	auth = {
		vk: false, 
		ok: false
	}, 
	all_topics = {}, 
	all_topics_array = [], 
	topics_offset = 0, 
	remote_topics_offset = 0, 
	remote_topics_end = {
		vk: false, 
		ok: false
	}, 
	remote_loading = {
		vk: false, 
		ok: false
	}, 
	editor, 
	max_offset, 
	uniq_id = '[' + settings.gid + '_' + settings.sort + '_' + settings.mode + ']', 
	random_offsets = {
		pool: {}, 
		cnt: 0
	};

function prepareText(text) {
	var URL_RE = /(?:([!()?.,\s\n\r]|^)((https?:\/\/)?((?:[a-z0-9_\-]+\.)+(?:[a-z]{2,7}|xn--p1ai|xn--j1amh|xn--80asehdb|xn--80aswg))(\/.*?)?(\#.*?)?)(?:[.!:;,*()]*([\s\r\n]|$))|([!()?.,\s\n\r]|^)((https?:\/\/)?((?:[a-z0-9а-яєґї_\-]+\.)+(?:рф|укр|онлайн|сайт|срб|su))(\/.*?)?(\#.*?)?)(?:[.!:;,*()]*([\s\r\n]|$))|([!()?.,\s\n\r]|^)((https?:\/\/)((?:[a-z0-9а-яєґї_\-]+\.)+(?:[a-z]{2,7}|рф|укр|онлайн|сайт|срб|su))(\/.*?)?(\#.*?)?)(?:[.!:;,*()]*([\s\r\n]|$)))/gi;
	return emojione.toImage(text.replace(/\[(club|public|id)(\d+)\|([^\]]+)\]/gim, function (_, type, id, title) {
		return '<a href="https://vk.com/' + type + id + '" target="_blank">' + title + '</a>';
	}).replace(URL_RE, function () {
		var m = arguments, 
			offset = m[4] ? 0 : (m[7 + 4] ? 7 : 14), 
			url = m[offset + 2];
		return m[offset + 1] + '<a href="' + url + '" target="_blank">' + url + '</a>' + m[offset + 7];
	})).replace(/\r\n/gi, "<br />");
}

var tpl = {
	attaches: function (data) {
		var attaches = [];
		
		for (var att_id in data) {
			var att = data[att_id];
			var html = '';
			if (att.type == 'photo') {
				var src = /*att.photo.src_xbig || */att.photo.src_big || att.photo.src_small || att.photo.src, 
					aspect = att.photo.height / att.photo.width, 
					max_width = att.photo.height > att.photo.width ? 
						Math.min(320 / aspect, att.photo.width) : 
						Math.min(320, att.photo.width), 
					tile = att.photo.height / att.photo.width > 1.2 ? 
						'25%' : '50%';
				html += 
					(att.photo.height && att.photo.width ? 
						// с width/height
						'<div class="center post-pic js-attach" ' + 
								'style="padding: 10px 0;margin: 0 2px;display: inline-block;max-width: ' + max_width + 'px; width: ' + tile + ';" data-id="' + att_id + '">' + 
							'<a href="' + src + '" target="_blank" class="aspect" style="padding-top: ' + (aspect * 100) + '%">' + 
								'<img src="' + src + '" alt="" class="preview" />' + 
								'<span class="post-attach_remove js-attach_remove inl post-show_edit">' + 
									'<img src="i/img/remove_2x.png" alt="" />' + 
								'</span>' + 
							'</a>' + 
						'</div>' : 
						// без width/height
						'<div class="center post-pic js-attach" style="padding: 10px 0;max-width: 320px" data-id="' + att_id + '">' + 
							'<a href="' + src + '" target="_blank" class="aspect aspect-auto">' + 
								'<img src="' + src + '" alt="" class="preview" />' + 
								'<span class="post-attach_remove js-attach_remove inl post-show_edit">' + 
									'<img src="i/img/remove_2x.png" alt="" />' + 
								'</span>' + 
							'</a>' + 
						'</div>'
					);
			} else if (att.type == 'album') {
				html += 
					'<div class="post-attach deleted js-attach" data-id="' + att_id + '">' + 
						'<b>Альбом фото:</b> <a href="https://vk.com/' + att_id + '" target="_blank">' + 
							att.album.title + '<br />' + 
							'<img src="' + att.album.thumb.src + '" alt="" />' + 
						'</a>' + 
					'</div>';
			} else if (att.type == 'video') {
				var src = att.video.image_xbig || att.video.image_big || att.video.image_small || att.video.image;
				html += 
					'<div class="post-attach deleted js-atatch" data-id="' + att_id + '">' + 
						'<b>Видео:</b> <a href="https://vk.com/' + att_id + '" target="_blank">' + att.video.title + '</a>' + 
						'<div class="pad_t">' + 
							'<a href="https://vk.com/' + att_id + '" target="_blank" class="center aspect" style="padding-top: 56.25%; background-color: transparent">' + 
								'<img src="' + src + '" style="max-height: 100%; width: auto;" class="preview" alt="" />' + 
							'</a>' + 
						'</div>' + 
						(att.video.description ? 
							'<div class="pad_t">' + 
								prepareText(att.video.description) + 
							'</div>' : '') + 
					'</div>';
			} else if (att.type == 'doc') {
				html += 
					'<div class="post-attach deleted js-attach" data-id="' + att_id + '">' + 
						'<b>Документ:</b> <a href="https://vk.com/' + att_id + '" target="_blank">' + 
							att.doc.title + '<br />' + 
							(att.doc.photo_130 ? '<img src="' + att.doc.photo_130 + '" alt="" />' : '') + 
						'</a>' + 
					'</div>';
			} else if (att.type == 'audio') {
				html += 
					'<div class="post-attach deleted js-attach" data-id="' + att_id + '">' + 
						'<b>MP3:</b> ' + att.audio.artist + ' ' + att.audio.title + 
					'</div>';
			} else if (att.type == 'poll') {
				html += 
					'<div class="post-attach deleted js-attach" data-id="' + att + '">' + 
						'<b>Опрос:</b> ' + att.poll.question + (att.poll.anonymous ? '<span class="grey">(Анонимный)</span>' : '') + '<br />';
				if (att.poll.answers) {
					for (var j = 0; j < att.poll.answers.length; ++j)
						html += (j + 1) + ') ' + att.poll.answers[j] + '<br />';
				}
				html += '</div>';
			} else if (att.type == 'link') {
				html += 
					'<div class="post-attach deleted js-attach" data-id="' + att_id + '">' + 
						'<b>Ссылка:</b> <a href="' + att.link.url + '" target="_blank">' + att.link.url + '</a><br />' + 
						'<b>Название:</b> ' + att.link.title + '<br />' + 
						'<div class="emoji">' + prepareText(att.link.description) + '</div>' + 
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
		var html =
			'<div class="row js-post wrapper" data-id="' + data.id + '" data-type="' + data.type + '" data-gid="' + data.gid + '">' + 
				'<div class="oh">' + 
					'<div class="left post-preview relative">' + 
						'<img src="' + data.avatar + '" alt="" width="50" height="50" />' + 
					'</div>' + 
					'<div class="oh">' + 
						'<span class="time">' + 
							'<span class="m">' + data.date + '</span> ' + 
						'</span>' + 
						'<a href="' + data.url + '" target="_blank" class="m"><b class="post-author">' + data.name + '</b></a> ' + 
						'<a href="' + data.post_url + '" target="_blank">' + 
							'<img src="i/img/external.svg" width="14" height="14" class="m" alt="" />' + 
						'</a>' + 
						(data.pinned ? ' <span class="grey m">(Закреплён)</span>' : '') + 
						'<br />' + 
						'<div class="post-text post-hide_edit emoji">' + prepareText(data.text) + '</div>' + 
					'</div>' + 
				'</div>' + 
				'<div class="post-show_edit pad_t">' + 
					'<textarea rows="10"></textarea>' + 
				'</div>' + 
				tpl.attaches(data.attaches || []) + 
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
}).on('click', '.js-vk_oauth', function (e) {
	e.preventDefault();
	$(this).data('exit') ? VK.Auth.logout() : VK.Auth.login();
	VK.Observer.subscribe('auth.login', vkOnLogin);
}).on('click', '.js-post_delete', function (e) {
	e.preventDefault();
	var btn = $(this), 
		wrap = btn.parents('.js-post'), 
		post_id = wrap.data('type') + '_' + wrap.data('gid') + '_' + wrap.data('id');
	
	settings.blacklist[post_id] = 1;
	wrap.remove();
	
	$.api("?a=grabber&sa=blacklist", {
		gid: settings.gid, 
		blacklist: post_id
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
	};
	
	toggle_spinner(true);
	
	var images = [];
	wrap.find('.js-attach:not(.deleted)').each(function () {
		var att = post.attaches[$(this).data('id')], 
			att_data = att[att.type];
		images.push(att_data.src_xbig || att_data.src_big || att_data.src_small || att_data.src);
	});
	
	$.api("?a=grabber&sa=queue", {
		gid: settings.gid, 
		text: wrap.find('textarea').prop("emojioneArea").getText(), 
		images: images, 
		blacklist: post_id
	}, function (res) {
		if (res.error) {
			alert(res.error);
			toggle_spinner(false);
		} else {
			settings.blacklist[post_id] = 1;
			wrap.html('<b class="green">' + (res.date ? 'Пост успешно добавлен в очередь (' + res.date + '). ' : 'Пост опубликован! (очередь была пустая)') + '</b>');
		}
	}, function () {
		alert('Страшная ошибка!!11 Мб нет интернета?');
		toggle_spinner(false);
	});
}).on('click', '.js-attach_remove', function (e) {
	e.preventDefault();
	$(this).parents('.js-attach').toggleClass('deleted');
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

vkInit();

function parseVkWall(target_posts, gid, offset, chunk, res) {
	var users = {}, total = res.wall.shift(), items = res.wall;
	for (var i = 0; i < res.profiles.length; ++i)
		users[res.profiles[i].uid] = res.profiles[i];
	for (var i = 0; i < res.groups.length; ++i)
		users[-res.groups[i].gid] = res.groups[i];
	
	for (var i = 0; i < items.length; ++i) {
		var item = items[i], 
			has_images = false;
		
		var attaches_hash = {};
		if ('attachments' in item) {
			for (var j = 0; j < item.attachments.length; ++j) {
				var attach = item.attachments[j];
				if (attach.type == 'photo')
					has_images = true;
				var att_id = attach.type + attach[attach.type].owner_id + '_' + (attach[attach.type].id || attach[attach.type].pid || 
					attach[attach.type].vid || attach[attach.type].did || attach[attach.type].aid);
				attaches_hash[att_id] = attach;
			}
		}
		
		if (settings.contentFilter == 'all')
			has_images = true;
		
		var post_id = 'vk_' + gid + '_' + item.id;
		if (!has_images || all_topics[post_id] || settings.blacklist[post_id])
			continue;
		
		var user = users[item.from_id];
		target_posts.push({
			id: item.id, 
			gid: gid, 
			n: settings.sort == 'ASC' ? offset + (chunk - i - 1) : offset + i, 
			type: 'vk', 
			text: item.text.replace(/<br[^>]*>/gi, "\r\n"), 
			comments: item.comments.count, 
			likes: item.likes.count, 
			reposts: item.reposts.count, 
			attaches: attaches_hash, 
			avatar: user.photo_medium_rec || user.photo_50, 
			date: getHumanDate(item.date), 
			timestamp: item.date, 
			name: user.name ? user.name : (user.first_name + " " + user.last_name), 
			pinned: item.is_pinned, 
			url: '//vk.com/' + (user.screen_name ? user.screen_name : (user.gid ? 'public' + user.gid : 'id' + user.uid)), 
			post_url: '//vk.com/wall-' + gid + '_' + item.id, 
		});
		
		all_topics['vk_' + gid + '_' + item.id] = target_posts[target_posts.length - 1];
	}
	return total;
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

function loadVkPosts() {
	if (remote_topics_end.vk || remote_loading.vk)
		return;
	
	var toggle_loading = function (f) {
		remote_loading.vk = f;
		$('#garbber_posts_spinner').toggleClass('hide', !f);
	};
	
	var fact_chunk = Math.round(LOAD_CHUNK / Math.min(25, settings.sources.length)), 
		fact_offset = remote_topics_offset;
	
	if (settings.sort == "ASC") {
		if (max_offset === undefined) {
			fact_offset = 0;
			fact_chunk = 1;
		} else {
			fact_offset = max_offset - fact_offset - fact_chunk;
		}
		
		if (fact_offset < 0 && -fact_offset > fact_chunk) {
			remote_topics_end.vk = true;
			$('#grabber_posts').append(tpl.noPosts());
			return;
		}
	}
	
	fact_offset = Math.max(0, fact_offset);
	
	console.log('start offset=' + fact_offset + ', chunk=' + fact_chunk);
	
	var get_wall_posts = function (offset, chunk) {
		if (settings.sort == "RAND" && max_offset !== undefined) {
			var vk_api_calls = [];
			for (var i = 0; i < settings.sources.length; ++i) {
				var source = settings.sources[i];
				if (source[0] == "VK" && vk_api_calls.length < 25) {
					vk_api_calls.push(`
						"${source[1]}": API.wall.get({
							"owner_id":		-${source[1]}, 
							"offset":		rand_offset, 
							"count":		1, 
							"extended":		1, 
							"fields":		"first_name,last_name,domain,photo_50"
						})
					`);
				}
			}
			
			var random_pool = [];
			while (random_pool.length < 25 && random_offsets.cnt < max_offset) {
				var rand = Math.floor(Math.random() * max_offset);
				if (!random_offsets.pool[rand]) {
					random_pool.push(rand);
					random_offsets.pool[rand] = 1;
				}
			}
			
			if (!random_pool.length || !vk_api_calls.length)
				return;
			
			var code = `
				var random_pool = ${JSON.stringify(random_pool)}, 
					max_offset = ${max_offset}, 
					api_cnt = 0, 
					api_chunk = ${vk_api_calls.length}, 
					result = [];
				
				var i = 0;
				while (api_cnt + api_chunk <= 25) {
					var rand_offset = random_pool[i];
					
					result.push({ ${vk_api_calls.join(", ")} });
					
					i = i + 1;
					api_cnt = api_cnt + api_chunk;
				}
				
				return result;
			`;
			
			return code;
		} else {
			var vk_api_calls = []
			for (var i = 0; i < settings.sources.length; ++i) {
				var source = settings.sources[i];
				if (source[0] == "VK" && vk_api_calls.length < 25) {
					vk_api_calls.push(`
						"${source[1]}": API.wall.get({
							"owner_id":		-${source[1]}, 
							"offset":		${offset}, 
							"count":		${chunk}, 
							"extended":		1, 
							"fields":		"first_name,last_name,domain,photo_50"
						})
					`);
				}
			}
			
			if (!vk_api_calls.length)
				return;
			
			return `return [{ ${vk_api_calls.join(", ")} }];`;
		}
	};
	
	var code = get_wall_posts(fact_offset, fact_chunk);
	if (code) {
		toggle_loading(true);
		VK.Api.call("execute", {
			code: code.replace(/\s+/gim, ' ')
		}, function (array) {
			toggle_loading(false);
			if (array.error) {
				$('#grabber_posts').append(tpl.error({
					error: array.error.error_msg
				}));
			} else {
				var posts = [], loaded = 0;
				
				remote_topics_offset += fact_chunk;
				console.log(array);
				
				var error_messages = [];
				if (array.execute_errors) {
					for (var k = 0; k < array.execute_errors.length; ++k) {
						var err = array.execute_errors[k];
						error_messages.push('#' + k + ': ' + err.error_msg);
					}
				}
				/*
				$('#grabber_posts').append(tpl.error({
					error: error_messages.join('<br />')
				}));
				*/
				for (var k = 0; k < array.response.length; ++k) {
					var bulk = array.response[k];
					if (max_offset === undefined) {
						max_offset = 0;
						for (var gid in bulk) {
							var res = bulk[gid];
							if (res)
								max_offset = Math.max(max_offset, res.wall[0]);
						}
						
						if (settings.sort == "ASC" || settings.sort == "RAND") {
							console.log("max_offset="+max_offset);
							setTimeout(loadVkPosts, 0);
							return;
						}
					}
					
					for (var gid in bulk) {
						var res = bulk[gid];
						if (res)
							loaded += parseVkWall(posts, gid, remote_topics_offset - fact_chunk, fact_chunk, res);
					}
				}
				
				$('#grabber_offset').html(remote_topics_offset);
				$('#grabber_total').html(max_offset);
				
				posts.sort(function (a, b) {
					if (a.timestamp == b.timestamp)
						return 0;
					if (a.pinned)
						return 1;
					if (b.pinned)
						return -1;
					return settings.sort == "ASC" ? 
						(a.timestamp > b.timestamp ? 1 : -1) : 
						(a.timestamp > b.timestamp ? -1 : 1);
				});
				
				if (loaded) {
					console.log("loaded " + posts.length + " (raw " + loaded + ") with chunk " + fact_chunk);
					for (var i = 0; i < posts.length; ++i)
						all_topics_array.push(posts[i]);
					onScroll();
				} else {
					remote_topics_end.vk = true;
					$('#grabber_posts').append(tpl.noPosts());
				}
				
				if (settings.sort != "RAND")
					$('#pagenav').html(pagenav('', remote_topics_offset - fact_chunk, LOAD_CHUNK, max_offset));
			}
		});
	}
}

function onScroll() {
	var scroll = $window.scrollTop(), 
		inner = $window.innerHeight(), 
		scroll_max = $document.innerHeight() - inner;
	
	if (scroll_max - scroll <= inner && !lock_scroll_events) {
		lock_scroll_events = true;
		setTimeout(function () {
			if (loadCachedPosts(TOPICS_CHUNK) < TOPICS_CHUNK)
				loadVkPosts();
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

function vkOnLogin(res) {
	auth.vk = res.status == "connected";
	
	if (auth.vk) {
		VK.Api.call("users.get", {}, function (res) {
			res = res.response[0];
			$('#vk_oauth_name').html(res.first_name + " " + res.last_name);
		});
		remote_topics_offset = +window.localStorage["saved_offset_" + uniq_id] || 0;
		$('#post_offset').val(remote_topics_offset);
		loadVkPosts();
	}
	
	$('#vk_oauth_ok').toggleClass('hide', !auth.vk);
	$('#vk_oauth').toggleClass('hide', auth.vk);
}

function onVkInit(res) {
	VK.Observer.subscribe('auth.logout', function () {
		location.reload();
	});
	VK.Auth.getLoginStatus(vkOnLogin);
	$('#garbber_init_spinner').addClass('hide');
}

function vkInit() {
	if (window.VK) {
		VK.init({
			apiId: settings.vkAppId
		});
		onVkInit();
	} else {
		window.vkAsyncInit = vkInit;
	}
}

//
});