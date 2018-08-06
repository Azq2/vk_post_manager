define(['jquery', 'colorpicker', 'utils', 'api', 'comm/data'], function ($, _, utils, _, comm) {
//
var MEME_WIDTH			= 510, 
	MEME_HEIGHT			= 510, 
	MEME_FONT			= 'Impact, ImpactExternal', 
	MEME_FONT_SIZE		= 35, 
	MEME_STROKE			= 2, 
	
	
	MEME_WATERMARK_FONT			= '"Goudy Old Style", "Goudy Old Style External"', 
	MEME_WATERMARK_FONT_SIZE	= 10, 
	MEME_WATERMARK_STROKE		= 1;

var expando = 'memegen-' + Date.now(), 
	new_template;

var tpl = {
	editor: function () {
		var html =
			'<div class="wrapper meme js-meme">' + 
				'<div class="row center js-meme_canvas_parent">' + 
					'<div class="relative js-meme_canvas_wrap inl_bl">' + 
						'<canvas class="js-meme_canvas"></canvas>' + 
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
					'<div class="row inl_bl">' + 
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
					'<div class="row inl_bl">' + 
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
					'<div class="row inl_bl">' + 
						'<label class="lbl">Alpha</label><br />' + 
						'<input type="submit" value="&nbsp;-&nbsp;" class="btn js-input_incr" data-step="-1" />' + 
						'<input name="font_alpha" readonly="readonly" type="text" class="js-meme_font_alpha center" size="2" data-min="0" data-max="100" />' + 
						'<input type="submit" value="&nbsp;+&nbsp;" class="btn js-input_incr" data-step="1" />' + 
					'</div>' + 
					'<div class="row inl_bl js-meme_position_edit">' + 
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
					'<div class="row inl_bl hide">' + 
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
		instance_id = 'meme' + Date.now(), 
		canvas_viewport, 
		canvas, 
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
		
		var fonts = {
			"10px Impact":				"10px ImpactExternal", 
			"10px 'Goudy Old Style'":	"10px 'Goudy Old Style External'"
		};
		var fonts_queue = 0;
		$.each(function (font, font_fallback) {
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
				resize();
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
		
		$(window).on('resize.' + instance_id, resize);
		
		resize();
		addTextBox('Верхний текст', 'top');
		addTextBox('Нижний текст', 'bottom');
		addTextBox('Средний текст', 'middle');
		addTextBox('Watermark', 'watermark');
		repaint(canvas);
		
		wrap.on('click', '.js-input_incr', function (e) {
			e.preventDefault();
			var el = $(this), 
				input = el.parent().find('input[data-min]');
			 input.val(Math.max(input.data("min") || 0, Math.min(input.data('max') || 100, +input.val() + +el.data('step'))))
				.trigger('change');
		});
		
		wrap.on('click', '.js-meme_save', function (e) {
			e.preventDefault();
			
			saveImageMask(function (blob) {
				wrap.trigger('meme:save', {
					image: blob, 
					data: opts.data
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
		var tmp = document.createElement('canvas');
		tmp.width = opts.width;
		tmp.height = opts.height;
		repaint(tmp, true);
		tmp.toBlob(callback);
	}

	function resize() {
		var parent = $(canvas).parents('.js-meme_canvas_parent');
		
		canvas.width = Math.min(MEME_WIDTH, parent.width());
		canvas.height = Math.floor(canvas.width * (opts.height / opts.width));
		
		canvas.getContext("2d").scale(canvas.width / opts.width, canvas.height / opts.height);
		
		repaint(canvas, false, true);
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

	function repaint(canvas, no_image, force) {
		var ctx = canvas.getContext("2d");
		ctx.save();
		if (!no_image)
			ctx.drawImage(img, 0, 0, opts.width, opts.height);
		
		for (var j = textboxes.length; j-->0; ) {
			var tb = textboxes[j];
			
			var buffer = document.createElement('canvas');
			buffer.width = opts.width;
			buffer.height = opts.height;
			var ctx2 = buffer.getContext("2d");
			
			ctx2.lineWidth = 0;
			ctx2.textBaseline = "top";
			ctx2.textAlign = "start";
			ctx2.lineJoin = 'round';
			
			if (!tb.render || force) {
				tb.x = 5;
				tb.width = opts.width - 10;
				
				var text = tb.uc.prop("checked") ? tb.text.val().toUpperCase() : tb.text.val();
				
				var lines = tb.enabled.prop("checked") ? text.split(/\r\n|\n|\r/) : [], 
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
						ctx2.font = font_str;
						
						line_width = ctx2.measureText(lines[i]).width;
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
					render_height += lh;
				}
				tb.render = {
					height: render_height, 
					width: render_width, 
					x: x, 
					lines: render_lines
				};
			}
			
			switch (tb.position.val()) {
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
			
			ctx2.shadowColor = tb.strokeColor;
			ctx2.fillStyle = tb.textColor;
			
			var watermark = textboxes[textboxes.length - 1];
			if (tb !== watermark && tb.position.val() === watermark.position.val() && watermark.enabled.prop("checked")) {
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
			
			for (var i = 0, l = tb.render.lines.length; i < l; ++i) {
				var line = tb.render.lines[i];
				ctx2.font = line.font;
				fillStrokeText(ctx2, tb.x + line.x, tb.y + line.y, line.text, line.stroke);
			}
			
			ctx.globalAlpha = tb.fontAlpha.val() / 100;
			ctx.drawImage(buffer, 0, 0, buffer.width, buffer.height);
			
			// tb.draggable.css({top: (tb.y / opts.height * 100) + "%", height: (tb.render.height / opts.height * 100) + "%"});
		}
		
		ctx.restore();
	}
	
	function serialize() {
		var save = ['text', 'textColor', 'strokeColor', 'fontSize', 'fontAlpha', 'bold', 'uc'];
		var settings = {textboxes: {}};
		$.each(textboxes, function (k, tb) {
			var tmp = {};
			$.each(save, function (_, k) {
				var v = tb[k];
				
				if (k == 'text' && tb.type != 'watermark')
					return;
				
				if ((v instanceof $)) {
					tmp[k] = v.val();
					if (v.prop("type") == 'checkbox')
						tmp[k] = v.prop("checked");
				} else if (["string", "number"].indexOf(typeof v) > -1) {
					tmp[k] = v;
				}
			});
			settings.textboxes[tb.type] = tmp;
		});
		return settings;
	}
	
	function restoreSettings(settings) {
		if (settings.textboxes) {
			$.each(textboxes, function (k, tb) {
				var restore = settings.textboxes[tb.type];
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
	
	function addTextBox(title, type, text) {
		var saved = (opts.template && opts.template.textboxes[type]) || {};
		
		var tb = {
			id:				textboxes.length, 
			width:			opts.width - 10, 
			x:				5, 
			y:				0, 
			type:			type, 
			strokeColor:	saved.strokeColor || '#000000', 
			textColor:		saved.textColor || '#FFFFFF', 
			title:			title, 
			text:			saved.text || text || ""
		};
		
		var font_size = MEME_FONT_SIZE, 
			stroke = MEME_STROKE, 
			position, align, font = MEME_FONT;
		
		if (type == 'watermark') {
			font_size = MEME_WATERMARK_FONT_SIZE;
			stroke = MEME_WATERMARK_STROKE;
			font = MEME_WATERMARK_FONT;
			position = "bottom";
			align = "right";
		} else {
			position = type;
			align = "center";
		}
		
		var invalidate = function () {
			tb.render = false;
			repaint(canvas);
			
			editor.find('.js-meme_editor, .js-meme_text')
				.toggleClass('hide', !tb.enabled.prop("checked"));
		}
		
		var editor = $(tpl.textBoxEdit(tb));
		editor.find('.js-meme_position_edit').toggleClass('hide', type != 'watermark');
		
		tb.enabled = editor.find('.js-meme_tb_enable')
			.prop("checked", type != 'watermark' && type != 'middle')
			.on('input change', invalidate);
		
		tb.bold = editor.find('.js-meme_tb_bold')
			.on('input change', invalidate)
			.prop("checked", 'bold' in saved ? saved.bold : false);
		
		tb.uc = editor.find('.js-meme_tb_uc')
			.on('input change', invalidate)
			.prop("checked", 'uc' in saved ? saved.uc : true);
		
		tb.editor = editor;
		tb.text = editor.find('.js-meme_text').on('input change', invalidate).val(tb.text);
		
		tb.font = editor.find('.js-meme_font')
			.val(font).on('input change', invalidate);
		tb.position = editor.find('.js-meme_text_position').val(position).on('input change', invalidate);
		tb.align = editor.find('.js-meme_text_align').val(align).on('input change', invalidate);
		
		tb.fontAlpha = editor.find('.js-meme_font_alpha')
			.val(saved.fontAlpha || 100).on('input change', invalidate);
		tb.fontSize = editor.find('.js-meme_font_size')
			.val(saved.fontSize || font_size).on('input change', invalidate);
		tb.fontStroke = editor.find('.js-meme_font_stroke')
			.val(saved.fontStroke || stroke).on('input change', invalidate);
		
		tb.draggable = $(tpl.draggable());
		
		// wrap.find('.js-meme_canvas_wrap').append(tb.draggable);
		
		wrap.find('.js-meme_form').append(editor);
		textboxes.push(tb);
		
		invalidate();
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
