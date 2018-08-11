define(['jquery', 'colorpicker', 'utils', 'api', 'comm/data'], function ($, _, utils, _, comm) {
//
var MEME_WIDTH			= 510, 
	MEME_HEIGHT			= 510, 
	MEME_FONT			= 'Impact, ImpactExternal', 
	MEME_TEXT_BLOCK_PAD	= 10, 
	
	MEME_WATERMARK_FONT			= '"Goudy Old Style", "Goudy Old Style External"', 
	MEME_WATERMARK_FONT_SIZE	= 10;

var FONTS = {
	IMPACT:		'Impact, ImpactExternal', 
	GOUDY:		'"Goudy Old Style", "Goudy Old Style External"', 
	TAHOMA:		'Tahoma', 
	ARIAL:		'Arial'
};

var REQUIRED_FONTS = {
	"10px Impact":				"10px ImpactExternal", 
	"10px 'Goudy Old Style'":	"10px 'Goudy Old Style External'"
};

var expando = 'memegen-' + Date.now(), 
	new_template;

var RE_WORDS		= /(.+?([\s!?\-,.():;#\/"'`\\#@^%$&_=~]+|$))/g, 
	RE_RTRIM		= /\s+$/g, 
	RE_IS_SPACE		= /\s+/;

var TEXTBOXES_CONFIG = {
	top_block:	{
		title:			'Текст над картинкой',
		position:		'top',
		type:			'block', 
		align:			'left', 
		enabled:		false, 
		bgColor:		'#FFFFFF', 
		strokeColor:	'#FFFFFF', 
		textColor:		'#000000', 
		
		uc:			false, 
		bold:		false, 
		fontSize:	20, 
		fontStroke:	0, 
		fontAlpha:	100, 
		font:		FONTS.ARIAL, 
		
		edit:		{
			fontSizeAndColor:	true, 
			strokeSizeAndColor:	false, 
			alpha:				false, 
			position:			false, 
			fontFamily:			false, 
			bgColor:			true
		}
	}, 
	bottom_block:	{
		title:		'Текст под картинкой',
		position:	'bottom',
		type:		'block', 
		align:		'left', 
		enabled:	false, 
		bgColor:		'#FFFFFF', 
		strokeColor:	'#FFFFFF', 
		textColor:		'#000000', 
		
		uc:			false, 
		bold:		false, 
		fontSize:	20, 
		fontStroke:	0, 
		fontAlpha:	100, 
		font:		FONTS.ARIAL, 
		
		edit:		{
			fontSizeAndColor:	true, 
			strokeSizeAndColor:	false, 
			alpha:				false, 
			position:			false, 
			fontFamily:			false, 
			bgColor:			true
		}
	}, 
	top: {
		title:		'Верхний текст',
		position:	'top',
		type:		'line', 
		align:		'center', 
		enabled:	true, 
		
		uc:			true, 
		bold:		false, 
		fontSize:	35, 
		fontStroke:	2, 
		fontAlpha:	100, 
		font:		FONTS.IMPACT, 
		
		edit:		{
			fontSizeAndColor:	true, 
			strokeSizeAndColor:	true, 
			alpha:				true, 
			position:			false, 
			fontFamily:			false, 
			bgColor:			false
		}
	}, 
	bottom:	{
		title:		'Нижний текст',
		position:	'bottom',
		type:		'line', 
		align:		'center', 
		enabled:	true, 
		
		uc:			true, 
		bold:		false, 
		fontSize:	35, 
		fontStroke:	2, 
		fontAlpha:	100, 
		font:		FONTS.IMPACT, 
		
		edit:		{
			fontSizeAndColor:	true, 
			strokeSizeAndColor:	true, 
			alpha:				true, 
			position:			false, 
			fontFamily:			false, 
			bgColor:			false
		}
	}, 
	middle:	{
		title:		'Средний текст',
		position:	'middle',
		type:		'line', 
		align:		'center', 
		enabled:	false, 
		
		uc:			true, 
		bold:		false, 
		fontSize:	35, 
		fontStroke:	2, 
		fontAlpha:	100, 
		font:		FONTS.IMPACT, 
		
		edit:		{
			fontSizeAndColor:	true, 
			strokeSizeAndColor:	true, 
			alpha:				true, 
			position:			false, 
			fontFamily:			false, 
			bgColor:			false
		}
	}, 
	watermark:	{
		title:		'Watermark',
		position:	'bottom',
		type:		'line', 
		align:		'right', 
		enabled:	false, 
		
		uc:			true, 
		bold:		false, 
		fontSize:	10, 
		fontStroke:	1, 
		fontAlpha:	100, 
		font:		FONTS.GOUDY, 
		
		edit:		{
			fontSizeAndColor:	true, 
			strokeSizeAndColor:	true, 
			alpha:				true, 
			position:			true, 
			fontFamily:			false, 
			bgColor:			false
		}
	}
};

var tpl = {
	editor: function () {
		var html =
			'<div class="wrapper meme js-meme">' + 
				'<div class="js-meme_form_top"></div>' + 
				'<div class="row center js-meme_canvas_parent">' + 
					'<div class="relative js-meme_canvas_wrap inl_bl">' + 
						'<div class="js-meme_canvas_wrap meme-canvas_wrap" style="max-width: ' + MEME_WIDTH + 'px">' + 
							'<canvas class="js-meme_canvas_top hide"></canvas>' + 
							'<canvas class="js-meme_canvas"></canvas>' + 
							'<canvas class="js-meme_canvas_bottom hide"></canvas>' + 
						'</div>' + 
					'</div>' + 
				'</div>' + 
				'<div class="js-meme_form"></div>' + 
				'<div class="row oh">' + 
					'<input type="submit" class="js-meme_save btn btn-green" value="Готово" /> ' + 
					'<input type="submit" class="js-meme_cancel btn" value="Закрыть" />' + 
					'<input type="submit" class="js-meme_template_save btn right" value="Сохранить" />' + 
				'</div>' + 
			'</div>';
		return html;
	}, 
	textBoxEdit: function (data) {
		var html =
			'<div class="js-meme_textbox" data-id="' + data.id + '">' + 
				'<div class="row">' + 
					'<label class="lbl">' + 
						'<input type="checkbox" value="1" name="enable" class="js-meme_tb_enable" /> ' + 
						'<b>' + data.title + '</b>' + 
					'</label>' + 
					'<div class="right">' + 
						'<label class="lbl">' + 
							'<input type="checkbox" value="1" name="bold" class="js-meme_tb_bold" /> ' + 
							'bold' + 
						'</label> ' + 
						'<label class="lbl">' + 
							'<input type="checkbox" value="1" name="uc" class="js-meme_tb_uc" /> ' + 
							'uc' + 
						'</label>' + 
					'</div>' + 
					'<br />' + 
					'<textarea class="js-meme_text" placeholder="' + data.title + '"></textarea>' + 
				'</div>' + 
				'<div class="js-meme_editor">' + 
					'<div class="row inl_bl' + (data.edit.fontSizeAndColor ? '' : ' hide') + '">' + 
						'<label class="lbl">Размер</label><br />' + 
						'<input type="submit" value="&nbsp;-&nbsp;" class="btn js-input_incr" data-step="-1" />' + 
						'<input name="font_size" readonly="readonly" type="text" class="js-meme_font_size center" size="2" data-min="5" data-max="100"  />' + 
						'<input type="submit" value="&nbsp;+&nbsp;" class="btn js-input_incr" data-step="1" />' + 
					
						'&nbsp;' + 
						
						'<button name="font_stroke" type="submit" class="js-meme_color btn btn-colorpicker" value="#FFFFFF" data-type="textColor" ' + 
								'style="background: ' + data.textColor + '">' + 
							'&nbsp;' + 
						'</button>' + 
					'</div>' + 
					'<div class="row inl_bl' + (data.edit.strokeSizeAndColor ? '' : ' hide') + '">' + 
						'<label class="lbl">Контур</label><br />' + 
						'<input type="submit" value="&nbsp;-&nbsp;" class="btn js-input_incr" data-step="-1" />' + 
						'<input name="font_stroke" readonly="readonly" type="text" class="js-meme_font_stroke center" size="2" data-min="0" data-max="100" />' + 
						'<input type="submit" value="&nbsp;+&nbsp;" class="btn js-input_incr" data-step="1" />' + 
						
						'&nbsp;' + 
						
						'<button name="font_stroke" type="submit" class="js-meme_color btn btn-colorpicker" value="#000000" data-type="strokeColor" ' + 
								'style="background: ' + data.strokeColor + '">' + 
							'&nbsp;' + 
						'</button>' + 
					'</div>' + 
					'<div class="row inl_bl' + (data.edit.bgColor ? '' : ' hide') + '">' + 
						'<label class="lbl">Фон</label><br />' + 
						'<button name="font_stroke" type="submit" class="js-meme_color btn btn-colorpicker" value="#000000" data-type="bgColor" ' + 
								'style="background: ' + data.bgColor + '">' + 
							'&nbsp;' + 
						'</button>' + 
					'</div>' + 
					'<div class="row inl_bl' + (data.edit.alpha ? '' : ' hide') + '">' + 
						'<label class="lbl">Alpha</label><br />' + 
						'<input type="submit" value="&nbsp;-&nbsp;" class="btn js-input_incr" data-step="-1" />' + 
						'<input name="font_alpha" readonly="readonly" type="text" class="js-meme_font_alpha center" size="2" data-min="0" data-max="100" />' + 
						'<input type="submit" value="&nbsp;+&nbsp;" class="btn js-input_incr" data-step="1" />' + 
					'</div>' + 
					'<div class="row inl_bl' + (data.edit.position ? '' : ' hide') + '">' + 
						'<label class="lbl">Положение</label><br />' + 
						'<select name="text_align" class="js-meme_text_align">' + 
							'<option value="left">Лево</option>' + 
							'<option value="right">Право</option>' + 
							'<option value="center">Центр</option>' + 
						'</select> ' + 
						'<select name="text_align" class="js-meme_text_position">' + 
							'<option value="top">Верх</option>' + 
							'<option value="middle">Серед.</option>' + 
							'<option value="bottom">Низ</option>' + 
						'</select>' + 
					'</div>' + 
					'<div class="row inl_bl' + (data.edit.fontFamily ? '' : ' hide') + '">' + 
						'<label class="lbl">Шрифт</label><br />' + 
						'<select name="text_align" class="js-meme_font">' + 
							'<option value="' + utils.htmlWrap(MEME_FONT) + '">Impact</option>' + 
							'<option value="' + utils.htmlWrap(MEME_WATERMARK_FONT) + '">GOUDOS</option>' + 
						'</select> ' + 
					'</div>' + 
				'</div>' + 
				'<div class="row js-meme_color_window"></div>' + 
			'</div>';
		return html;
	}, 
	fontFloading: function () {
		var html =
			'<div class="wrapper">' + 
				'<div class="row grey">' + 
					'<img src="/i/img/spinner2.gif" alt="" width="16" height="16" class="m" /> ' + 
					'<span class="m">Загружаем шрифт Impact...</span>' + 
				'</div>' + 
			'</div>';
		return html;
	}, 
	draggable: function () {
		var html =
			'<div style="position: absolute; top: 0; left: 0; width: 100%; height: 10px; cursor: pointer; background: rgba(0,0,0,0.12)">' + 
				
			'</div>';
		return html;
	}
};

$.fn.memeEditor = function (opts) {
	var self = this.first();
	if (!self.length)
		return;
	
	var instance = self.data(expando);
	if (opts === false) {
		self.removeData(expando)
		instance && instance.destroy();
		return;
	} else {
		if (!instance)
			self.data(expando, new MemeEditor(self, opts));
		return instance;
	}
};

function MemeEditor(el, options) {
	var self = this, 
		wrap, 
		textboxes = [], 
		textboxes_by_type = {}, 
		textboxes_by_name = {}, 
		instance_id = 'meme' + Date.now(), 
		canvas_viewport, 
		canvas, 
		canvas_blocks, 
		viewport = {}, 
		opts, 
		img, 
		font_height_cache = {};
	
	$.extend(self, {
		destroy: destroy
	});
	
	if (!document.fonts || !document.fonts.load) {
		alert('Устаревший браузер, невозможно запустить.');
		return;
	}
	
	init(el, options);
	
	function init(el, options) {
		opts = $.extend({
			image:		false, 
			width:		640, 
			height:		480, 
			data:		null, 
			template:	comm.meme
		}, options);
		
		opts.template = new_template || opts.template;
		
		var fonts_queue = 0;
		$.each(REQUIRED_FONTS, function (font, font_fallback) {
			if (!document.fonts.check(font) && !document.fonts.check(font_fallback)) {
				el.html(tpl.fontFloading());
				++fonts_queue;
				document.fonts.load(font_fallback).then(function () {
					--fonts_queue;
					if (fonts_queue == 0)
						opts && init(el, options);
				});
			}
		});
		
		if (fonts_queue)
			return;
		
		var retry_load_image = function () {
			img = new Image();
			img.src = opts.image;
			img.onload = function () {
				opts.width = img.width;
				opts.height = img.height;
				resize(true);
			};
			img.onerror = function () {
				console.log('retry_load_image');
				img._timer = setTimeout(retry_load_image, 1000);
			};
		};
		retry_load_image();
		
		el.html(tpl.editor());
		wrap = el.find('.js-meme');
		canvas = wrap.find('.js-meme_canvas').css({
			maxWidth: MEME_WIDTH, 
			maxHeight: MEME_HEIGHT
		})[0];
		
		canvas_blocks = {
			top: wrap.find('.js-meme_canvas_top').css({
				maxWidth: MEME_WIDTH
			})[0], 
			bottom: wrap.find('.js-meme_canvas_bottom').css({
				maxWidth: MEME_WIDTH
			})[0]
		};
		
		$(window).on('resize.' + instance_id, resize);
		
		$.each(TEXTBOXES_CONFIG, function (k, v) {
			addTextBox(k, v);
		});
		resize(true);
		
		wrap.on('click', '.js-input_incr', function (e) {
			e.preventDefault();
			var el = $(this), 
				input = el.parent().find('input[data-min]');
			 input.val(Math.max(input.data("min") || 0, Math.min(input.data('max') || 100, +input.val() + +el.data('step'))))
				.trigger('change');
		});
		
		wrap.on('click', '.js-meme_save', function (e) {
			e.preventDefault();
			
			saveImageMask(function (blob, options) {
				wrap.trigger('meme:save', {
					image:		blob, 
					data:		opts.data, 
					options:	options
				});
			});
		});
		
		wrap.on('click', '.js-meme_cancel', function (e) {
			e.preventDefault();
			wrap.trigger('meme:cancel', {
				data: opts.data
			});
		});
		
		wrap.on('click', '.js-meme_color', function (e) {
			e.preventDefault();
			var el = $(this), 
				tb_wrap = el.parents('.js-meme_textbox'), 
				tb = textboxes[tb_wrap.data('id')], 
				type = el.data("type");
			
			tb_wrap.find('.js-meme_editor').addClass('hide');
			tb_wrap.find('.js-meme_color_window')
				.data('type', el.data('type'))
				.removeClass('hide')
				.colorpicker({
					color: tb[type], 
					save: false
				});
		});
		
		wrap.on('click', '.js-meme_template', function (e) {
			e.preventDefault();
			wrap.find('.js-meme_template_form').toggleClass('hide');
		});
		
		wrap.on('click', '.js-meme_template_save', function (e) {
			e.preventDefault();
			
			var el = $(this);
			
			if (el.attr("disabled"))
				return;
			
			new_template = serialize();
			
			el.attr("disabled", "disabled");
			$.api("/?a=settings", {type: "meme", settings: JSON.stringify(new_template)}, function () {
				el.removeAttr("disabled");
			}, function (err) {
				el.removeAttr("disabled");
				alert("Ошибочка сохранения! err=" + err);
			});
		});
		
		wrap.on('colorpicker:select', '.js-meme_color_window', function (w, data) {
			var el = $(this).parents('.js-meme_textbox');
			el.find('.js-meme_editor').removeClass('hide');
			el.find('.js-meme_color_window').addClass('hide').colorpicker(false);
		}).on('colorpicker:live', '.js-meme_color_window', function (w, data) {
			var el = $(this), 
				tb_wrap = el.parents('.js-meme_textbox'), 
				tb = textboxes[tb_wrap.data('id')], 
				type = el.data("type");
			
			tb_wrap.find('.js-meme_color[data-type="' + type + '"]')
				.val(data.color)
				.css("background", data.color);
			tb[type] = data.color;
			
			tb.render = false;
			repaint(canvas);
		})
	}

	function saveImageMask(callback) {
		// Расчитываем итоговую высоту
		var height = opts.height, 
			offset = 0;
		for (var j = textboxes_by_type.block.length; j-->0; ) {
			var tb = textboxes_by_type.block[j];
			
			if (!tb.enabled.prop("checked"))
				continue;
			
			if (tb.position.val() == 'top')
				offset = tb.render.originalBuffer.height;
			
			height += tb.render.originalBuffer.height;
		}
		
		var tmp = document.createElement('canvas');
		tmp.width = opts.width;
		tmp.height = height;
		repaint(tmp, true);
		tmp.toBlob(function (e) {
			callback(e, {offset: offset});
		});
	}
	
	function resetRenderCache() {
		for (var i = 0, l = textboxes.length; i < l; ++i)
			textboxes[i].render = false;
	}
	
	function resize(force) {
		var parent = $(canvas).parents('.js-meme_canvas_parent');
		
		var new_width = Math.min(MEME_WIDTH, parent.width()), 
			new_height = Math.floor(new_width * (opts.height / opts.width));
		
		if (new_height > MEME_HEIGHT) {
			new_height = MEME_HEIGHT;
			new_width = Math.floor(new_height * (opts.width / opts.height));
		}
		
		parent.find('.js-meme_canvas_wrap').css("max-width", new_width + "px");
		
		if (canvas.width != new_width || canvas.height != new_height || force) {
			canvas.width = new_width;
			canvas.height = new_height;
			resetRenderCache();
			repaint(canvas, false);
		}
	}

	function fillStrokeText(ctx, x, y, text, stroke) {
		if (!stroke) {
			ctx.fillText(text, x, y);
			return;
		}
		
		ctx.shadowBlur = stroke;
		
		for (var i = 1; i <= stroke; ++i) {
			ctx.shadowOffsetX = -i;
			ctx.shadowOffsetY = -i;
			ctx.fillText(text, x, y);
			
			ctx.shadowOffsetX = i;
			ctx.shadowOffsetY = i;
			ctx.fillText(text, x, y);
			
			ctx.shadowOffsetX = -i;
			ctx.shadowOffsetY = i;
			ctx.fillText(text, x, y);
			
			ctx.shadowOffsetX = i;
			ctx.shadowOffsetY = -i;
			ctx.fillText(text, x, y);
		}
	}

	function getFontHeight(font) {
		if (font_height_cache[font])
			return font_height_cache[font];
		
		var span = $('<span>').css({
			font: font, 
			padding: 0, margin: 0, 
			opacity: 0, visiblity: "hidden", 
			wordWrap: 'normal', 
			whiteSpace: 'pre', 
			letterSpacing: '0'
		}).text('M');
		$(document.body).append(span);
		
		font_height_cache[font] = span.innerHeight();
		
		span.empty().remove();
		
		return font_height_cache[font];
	}
	
	function painTextBox(ctx, tb, hq) {
		if (tb.render)
			return tb.render[!hq ? 'buffer' : 'originalBuffer'];
		
		// Проводим нужные рассчёты
		tb.x = 5;
		tb.width = opts.width - 10;
		
		var text = tb.uc.prop("checked") ? tb.text.val().toUpperCase() : tb.text.val();
		
		var lines = text.split(/\r\n|\n|\r/), 
			render_height = 0, 
			render_width = 0, 
			render_x = -1, 
			render_lines = [];
		for (var i = 0, l = lines.length; i < l; ++i) {
			if (!$.trim(lines[i]).length)
				continue;
			
			var font_size = Math.ceil(tb.fontSize.val() * (opts.width / MEME_WIDTH)), 
				stroke = Math.ceil(tb.fontStroke.val() * (opts.width / MEME_HEIGHT)), 
				line_width, n = 0, 
				font_str;
			while (true) {
				font_str = (tb.bold.prop("checked") ? "bold " : "") + font_size + "px " + tb.font.val();
				ctx.font = font_str;
				
				line_width = ctx.measureText(lines[i]).width + stroke * 2;
				if (line_width > tb.width && font_size > 0) {
					if (n > 1) {
						// Точная подстройка, если не смогли вычислить
						font_size = (font_size - 0.05).toFixed(2);
					} else {
						// Вычисляем примерный размер шрифта, с которым текст полностью должен влазить
						font_size = ((tb.width / line_width) * font_size).toFixed(4);
					}
					++n;
					continue;
				}
				break;
			}
			
			var x;
			switch (tb.align.val()) {
				case "left":
					x = 0;
				break;
				
				case "right":
					x = tb.width - line_width;
				break;
				
				case "center":
					x = Math.round((tb.width - line_width) / 2);
				break;
			}
			
			render_width = Math.max(render_width, line_width);
			render_x = render_x < 0 ? x : Math.min(render_x, x);
			
			var lh = getFontHeight(font_str);
			render_lines.push({
				font:	font_str, 
				text:	lines[i], 
				x:		x, 
				y:		render_height, 
				stroke:	stroke
			});
			render_height += lh + stroke * 2;
		}
		
		tb.render = {
			height: render_height, 
			width: render_width, 
			x: x, 
			lines: render_lines
		};
		
		if (render_lines.length) {
			// Рисуем в буфер
			var buffer = document.createElement('canvas');
			buffer.width = opts.width;
			buffer.height = tb.render.height;
			var ctx2 = buffer.getContext("2d");
			
			ctx2.lineWidth = 0;
			ctx2.textBaseline = "top";
			ctx2.textAlign = "start";
			ctx2.lineJoin = 'round';
			ctx2.shadowColor = tb.strokeColor;
			ctx2.fillStyle = tb.textColor;
			
			for (var i = 0, l = tb.render.lines.length; i < l; ++i) {
				var line = tb.render.lines[i];
				ctx2.font = line.font;
				fillStrokeText(ctx2, tb.x + line.x, line.y, line.text, line.stroke);
			}
			
			// Сразу ресайзим
			var resized = document.createElement('canvas'), 
				ctx3 = resized.getContext('2d');
			
			var aspect = buffer.height / buffer.width;
			resized.width = canvas.width;
			resized.height = Math.round(canvas.width * (buffer.height / buffer.width));
			drawResized(ctx3, buffer, 0, 0, resized.width, resized.height);
			
			tb.render.originalBuffer = buffer;
			tb.render.buffer = resized;
		}
		
		return tb.render[!hq ? 'buffer' : 'originalBuffer'];
	}
	
	function painTextBlock(block_canvas, tb, hq) {
		if (tb.render)
			return tb.render[!hq ? 'buffer' : 'originalBuffer'];
		
		var ctx = block_canvas.getContext('2d'), 
			text = tb.uc.prop("checked") ? tb.text.val().toUpperCase() : tb.text.val(), 
			lines = text.split(/\r\n|\n|\r/), 
			render_height = 0, 
			render_width = 0, 
			render_lines = [], 
			font_size = Math.ceil(tb.fontSize.val() * (opts.width / MEME_WIDTH)), 
			font_str = (tb.bold.prop("checked") ? "bold " : "") + font_size + "px " + tb.font.val(), 
			padding = Math.ceil(MEME_TEXT_BLOCK_PAD * (opts.width / MEME_WIDTH)), 
			max_width = opts.width - padding * 2;
		
		ctx.font = font_str;
		
		var lh = Math.round(getFontHeight(font_str) * 1.1), y = 0;
		for (var i = 0, l = lines.length; i < l; ++i) {
			var line = lines[i].replace(RE_RTRIM, '');
			
			if (!line.length) {
				render_lines.push("");
				continue;
			}
			
			var new_line = "", 
				new_line_words = 0, 
				words = [];
			
			while ((m = RE_WORDS.exec(line)))
				words.push(m[1]);
			
			// Подбираем пословно содержимое строки
			for (var j = 0, lj = words.length; j < lj; ++j) {
				var word = words[j];
				
				var line_width = ctx.measureText((new_line + word).replace(RE_RTRIM, '')).width;
				if (line_width >= max_width || j == lj - 1) {
					if (line_width < max_width) {
						new_line += word;
					} else if (new_line_words == 0) {
						// Подбираем побуквенно, остальную часть переносим на другую строку
						for (var k = 0, lk = word.length; k < lk; ++k) {
							var c = word.substr(k, 1);
							
							line_width = ctx.measureText((new_line + c).replace(RE_RTRIM, '')).width;
							if (line_width >= max_width || k == lk - 1) {
								if (line_width < max_width)
									new_line += c;
								
								render_lines.push(new_line.replace(RE_RTRIM, ''));
								
								new_line = "";
								
								if (k == lk - 1) {
									word = "";
									
									if (line_width >= max_width) {
										if (j == lj - 1) {
											if (!RE_IS_SPACE.test(c))
												render_lines.push(c);
										} else {
											word = c;
										}
									}
								}
								
							}
							
							new_line += c;
						}
						break;
					}
					
					if (line_width < max_width || new_line_words != 0) {
						render_lines.push(new_line.replace(RE_RTRIM, ''));
						
						new_line = "";
						new_line_words = 0;
					}
				}
				
				if (word.length) {
					new_line += word;
					++new_line_words;
				}
			}
		}
		
		tb.render = {
			font:		font_str, 
			height:		render_lines.length * lh + padding * 2, 
			width:		opts.width, 
			lines:		render_lines
		};
		
		if (render_lines.length) {
			ctx.save();
			// Рисуем в буфер
			var buffer = document.createElement('canvas');
			buffer.width = tb.render.width;
			buffer.height = tb.render.height;
			var ctx2 = buffer.getContext("2d");
			
			ctx2.fillStyle = tb.bgColor;
			ctx2.fillRect(0, 0, buffer.width, buffer.height);
			
			ctx2.font = tb.render.font;
			ctx2.lineWidth = 0;
			ctx2.textBaseline = "top";
			ctx2.textAlign = "start";
			ctx2.lineJoin = 'round';
			ctx2.shadowColor = tb.strokeColor;
			ctx2.fillStyle = tb.textColor;
			
			var y = padding;
			for (var i = 0, l = tb.render.lines.length; i < l; ++i) {
				ctx2.fillText(tb.render.lines[i], padding, y);
				y += lh;
			}
			
			// Сразу ресайзим
			var aspect = buffer.height / buffer.width;
			block_canvas.width = canvas.width;
			block_canvas.height = Math.round(canvas.width * (buffer.height / buffer.width));
			drawResized(ctx, buffer, 0, 0, block_canvas.width, block_canvas.height);
			
			tb.render.originalBuffer = buffer;
			tb.render.buffer = block_canvas;
			ctx.restore();
		}
		
		return tb.render[!hq ? 'buffer' : 'originalBuffer'];
	}
	
	function drawResized(dst_ctx, img, x, y, w, h) {
		var steps = Math.ceil(Math.log(img.width / w) / Math.log(2));
		console.log('steps='+steps, x, y, w, h);
		
		if (steps > 1) {
			var buffer = document.createElement('canvas'), 
				ctx = buffer.getContext('2d');
			
			// step 1
			buffer.width = img.width * 0.5;
			buffer.height = img.height * 0.5;
			ctx.drawImage(img, 0, 0, buffer.width, buffer.height);
			
			// step2
			var buffer2 = document.createElement('canvas'), 
				ctx2 = buffer2.getContext('2d');
			
			buffer2.width = img.width * 0.5;
			buffer2.height = img.height * 0.5;
			
			ctx2.drawImage(buffer, 0, 0, buffer.width * 0.5, buffer.height * 0.5);
			
			// step3
			dst_ctx.drawImage(buffer2, 0, 0, buffer2.width * 0.5, buffer2.height * 0.5, x, y, w, h);
		} else {
			dst_ctx.drawImage(img, 0, 0, img.width, img.height, x, y, w, h);
		}
		
	}
	
	function repaint(canvas, make_cover) {
		console.time("repaint");
		
		var offset = 0, 
			ctx = canvas.getContext("2d");
		ctx.save();
		if (!make_cover)
			ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
		
		// Обрабатываем текстбоксы, которые располагаются в виде отдельного текстового блока
		for (var j = 0, lj = textboxes_by_type.block.length; j < lj; ++j) {
			var tb = textboxes_by_type.block[j];
			
			if (!tb.enabled.prop("checked"))
				continue;
			
			if (make_cover) {
				var buffer = painTextBlock(canvas_blocks[tb.position.val()], tb, true);
				if (buffer) {
					if (tb.position.val() == 'top') {
						ctx.drawImage(buffer, 0, 0);
						offset = buffer.height;
					} else if (tb.position.val() == 'bottom') {
						ctx.drawImage(buffer, 0, offset + opts.height);
					}
				}
			} else {
				painTextBlock(canvas_blocks[tb.position.val()], tb);
			}
		}
		
		// Обрабатываем текстбоксы, которые располагаются на картинке в виде линий
		for (var j = textboxes_by_type.line.length; j-->0; ) {
			var tb = textboxes_by_type.line[j];
			
			if (!tb.enabled.prop("checked"))
				continue;
			
			var buffer = painTextBox(ctx, tb, make_cover);
			if (buffer) {
				var position = tb.position.val();
				switch (position) {
					case "top":
						tb.y = 0;
					break;
					
					case "bottom":
						tb.y = opts.height - tb.render.height;
					break;
					
					case "middle":
						tb.y = Math.round((opts.height - tb.render.height) / 2);
					break;
				}
				
				var watermark = textboxes_by_name.watermark;
				if (watermark && tb !== watermark && position === watermark.position.val() && watermark.enabled.prop("checked")) {
					var check = [
						[watermark.render.x, tb.render.x, tb.render.x + tb.render.width], 
						[watermark.render.x + watermark.render.width, tb.render.x, tb.render.x + tb.render.width], 
						[tb.render.x, watermark.render.x, watermark.render.x + watermark.render.width], 
						[tb.render.x + tb.render.width, watermark.render.x, watermark.render.x + watermark.render.width]
					];
					
					var overlaps = false;
					check.forEach(function (v) {
						if (v[0] >= v[1] && v[0] <= v[2]) {
							overlaps = true;
							return false;
						}
					});
					
					if (overlaps) {
						switch (tb.position.val()) {
							case "top":
							case "middle":
								tb.y = watermark.y + watermark.render.height;
							break;
							
							case "bottom":
								tb.y -= watermark.render.height;
							break;
						}
					}
				}
				
				ctx.globalAlpha = tb.fontAlpha.val() / 100;
				ctx.drawImage(buffer, 0, (offset + tb.y) * (canvas.width / opts.width));
			}
		}
		
		ctx.restore();
		
		console.timeEnd("repaint");
	}
	
	function serialize() {
		var save = ['text', 'textColor', 'bgColor', 'strokeColor', 'fontSize', 'fontAlpha', 'bold', 'uc'];
		var settings = {textboxes: {}};
		$.each(textboxes, function (k, tb) {
			var tmp = {};
			$.each(save, function (_, k) {
				var v = tb[k];
				
				if (k == 'text' && tb.name != 'watermark')
					return;
				
				if ((v instanceof $)) {
					tmp[k] = v.val();
					if (v.prop("type") == 'checkbox')
						tmp[k] = v.prop("checked");
				} else if (["string", "number"].indexOf(typeof v) > -1) {
					tmp[k] = v;
				}
			});
			settings.textboxes[tb.name] = tmp;
		});
		return settings;
	}
	
	function restoreSettings(settings) {
		if (settings.textboxes) {
			$.each(textboxes, function (k, tb) {
				var restore = settings.textboxes[tb.name];
				if (restore) {
					$.each(restore, function (k, v) {
						if ((tb[k] instanceof $)) {
							if (tb[k].prop("type") == 'checkbox') {
								tb[k].prop("checked", v);
							} else {
								tb[k].val(v);
							}
						} else if (["string", "number"].indexOf(typeof tb[k]) > -1) {
							tb[k] = v;
						}
					});
				}
			});
		}
	}
	
	function addTextBox(name, config) {
		var saved = (opts.template && opts.template.textboxes[name]) || {};
		
		var tb = $.extend({
			id:				textboxes.length, 
			width:			opts.width - 10, 
			x:				5, 
			y:				0, 
			bold:			'bold' in saved ? saved.bold : config.bold, 
			uc:				'uc' in saved ? saved.uc : config.uc, 
			name:			name
		}, config, saved);
		
		var sync_enabled_state = function () {
			var enabled = tb.enabled.prop("checked");
			editor.find('.js-meme_editor, .js-meme_text')
				.toggleClass('hide', !enabled);
			
			if (tb.type == 'block')
				$(canvas_blocks[tb.position.val()]).toggleClass('hide', !enabled);
		};
		
		var invalidate = function () {
			tb.render = false;
			repaint(canvas);
		};
		
		var editor = $(tpl.textBoxEdit(tb));
		tb.enabled = editor.find('.js-meme_tb_enable')
			.prop("checked", tb.enabled)
			.on('input change', function () {
				sync_enabled_state();
				invalidate();
			});
		
		tb.bold = editor.find('.js-meme_tb_bold')
			.on('input change', invalidate)
			.prop("checked", tb.bold);
		
		tb.uc = editor.find('.js-meme_tb_uc')
			.on('input change', invalidate)
			.prop("checked", tb.uc);
		
		tb.editor = editor;
		tb.text = editor.find('.js-meme_text').on('input change', invalidate).val(tb.text);
		
		tb.font = editor.find('.js-meme_font')
			.val(tb.font).on('input change', invalidate);
		tb.position = editor.find('.js-meme_text_position').val(tb.position).on('input change', invalidate);
		tb.align = editor.find('.js-meme_text_align').val(tb.align).on('input change', invalidate);
		
		tb.fontAlpha = editor.find('.js-meme_font_alpha')
			.val(tb.fontAlpha).on('input change', invalidate);
		tb.fontSize = editor.find('.js-meme_font_size')
			.val(tb.fontSize).on('input change', invalidate);
		tb.fontStroke = editor.find('.js-meme_font_stroke')
			.val(tb.fontStroke).on('input change', invalidate);
		
		tb.draggable = $(tpl.draggable());
		
		if (tb.type == 'block' && tb.position.val() == 'top') {
			wrap.find('.js-meme_form_top').append(editor);
		} else {
			wrap.find('.js-meme_form').append(editor);
		}
		
		// Список по типу текстбокса
		if (!textboxes_by_type[tb.type])
			textboxes_by_type[tb.type] = [];
		textboxes_by_type[tb.type].push(tb);
		
		// Список по имени текстбокса
		textboxes_by_name[tb.name] = tb;
		
		// Список всех чекбоксов
		textboxes.push(tb);
		
		sync_enabled_state();
		
		return tb;
	}
	
	function destroy() {
		if (wrap) {
			if (img) {
				img.onload = img.onerror = null;
				img._timer && clearTimeout(img._timer);
			}
			
			wrap.empty();
			$(window).off('resize.' + instance_id);
			textboxes = canvas = viewport = ctx = opts = null;
			font_height_cache = null;
			wrap = img = null;
		}
	}
}

//
});
