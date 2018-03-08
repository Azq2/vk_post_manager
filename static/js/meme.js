define(['jquery', 'colorpicker'], function ($) {
//
var MEME_WIDTH		= 510, 
	MEME_HEIGHT		= 510, 
	MEME_FONT		= 'Impact, ImpactExternal', 
	MEME_FONT_SIZE	= 35, 
	MEME_STROKE		= 2;

var expando = 'memegen-' + Date.now();

var tpl = {
	editor: function () {
		var html =
			'<div class="wrapper meme js-meme">' + 
				'<div class="row center">' + 
					'<canvas class="js-meme_canvas"></canvas>' + 
				'</div>' + 
				'<div class="js-meme_form"></div>' + 
				'<div class="row">' + 
					'<input type="submit" class="js-meme_save btn btn-green" value="Сохранить" /> ' + 
					'<input type="submit" class="js-meme_cancel btn" value="Закрыть" />' + 
				'</div>' + 
			'</div>';
		return html;
	}, 
	textBoxEdit: function (data) {
		var html =
			'<div class="js-meme_textbox" data-id="' + data.id + '">' + 
				'<div class="row">' + 
					'<label class="lbl"><b>' + data.title + '</b></label><br />' + 
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
		canvas, 
		viewport = {}, 
		ctx, 
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
			image:	false, 
			width:	640, 
			height:	480
		}, options);
		
		if (!document.fonts.check('10px Impact') && !document.fonts.check('10px ImpactExternal')) {
			el.html(tpl.fontFloading());
			document.fonts.load("10px ImpactExternal").then(function () {
				opts && init(el, options);
			});
			return;
		}
		
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
		canvas = wrap.find('.js-meme_canvas')[0];
		ctx = canvas.getContext("2d");
		
		$(window).on('resize.' + instance_id, resize);
		
		resize();
		addTextBox('Верхний текст', 'top');
		addTextBox('Нижний текст', 'bottom');
		addTextBox('Средний текст', 'middle');
		repaint();
		
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
					image: blob
				});
			});
		});
		
		wrap.on('click', '.js-meme_cancel', function (e) {
			e.preventDefault();
			wrap.trigger('meme:cancel');
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
		
		wrap.on('colorpicker:select', '.js-meme_color_window', function (w, data) {
			wrap.find('.js-meme_editor').removeClass('hide');
			wrap.find('.js-meme_color_window').addClass('hide').colorpicker(false);
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
			repaint();
		})
	}

	function saveImageMask(callback) {
		ctx.save();
		canvas.style.display = "none";
		canvas.width = opts.width;
		canvas.height = opts.height;
		ctx.scale(canvas.width / viewport.width, canvas.height / viewport.height);
		repaint(true);
		
		canvas.toBlob(callback);
		
		ctx.restore();
		resize();
		canvas.style.display = "";
	}

	function resize() {
		var parent = $(canvas).parent();
		
		canvas.width = Math.min(MEME_WIDTH, parent.width());
		canvas.height = Math.floor(canvas.width * (opts.height / opts.width));
		
		viewport.width = Math.min(MEME_WIDTH, opts.width);
		viewport.height = Math.floor(viewport.width * (opts.height / opts.width));
		
		if (viewport.height > MEME_HEIGHT) {
			viewport.height = MEME_HEIGHT;
			viewport.width = Math.floor(viewport.height * (opts.width / opts.height));
		}
		
		if (canvas.height > MEME_HEIGHT) {
			canvas.height = MEME_HEIGHT;
			canvas.width = Math.floor(canvas.height * (opts.width / opts.height));
		}
		
		parent.css("min-height", canvas.height);
		
		ctx.scale(canvas.width / viewport.width, canvas.height / viewport.height);
		repaint(false, true);
	}

	function fillStrokeText(x, y, text, stroke) {
		stroke = Math.ceil(stroke * (canvas.width / viewport.width));
		
		ctx.shadowBlur = stroke;
		
		ctx.shadowOffsetX = -stroke;
		ctx.shadowOffsetY = -stroke;
		ctx.fillText(text, x, y);
		
		ctx.shadowOffsetX = stroke;
		ctx.shadowOffsetY = stroke;
		ctx.fillText(text, x, y);
		
		ctx.shadowOffsetX = -stroke;
		ctx.shadowOffsetY = stroke;
		ctx.fillText(text, x, y);
		
		ctx.shadowOffsetX = stroke;
		ctx.shadowOffsetY = -stroke;
		ctx.fillText(text, x, y);
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

	function repaint(no_image, force) {
		ctx.save();
		if (!no_image)
			ctx.drawImage(img, 0, 0, viewport.width, viewport.height);
		ctx.lineWidth = 0;
		ctx.textBaseline = "top";
		ctx.textAlign = "start";
		ctx.lineJoin = 'round';
		
		for (var j = 0; j < textboxes.length; ++j) {
			var tb = textboxes[j];
			
			if (!tb.render || force) {
				tb.x = 5;
				tb.width = viewport.width - 10;
				
				var lines = tb.text.val().toUpperCase().split(/\r\n|\n|\r/), 
					render_height = 0, 
					render_lines = [];
				for (var i = 0, l = lines.length; i < l; ++i) {
					if (!$.trim(lines[i]).length)
						continue;
					
					var font_size = tb.fontSize.val(), 
						line_width, n = 0, 
						font_str;
					while (true) {
						font_str = font_size + "px " + MEME_FONT;
						ctx.font = font_str;
						
						line_width = ctx.measureText(lines[i]).width;
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
					
					var lh = getFontHeight(font_str);
					render_lines.push({
						font:	font_str, 
						text:	lines[i], 
						x:		Math.round((tb.width - line_width) / 2), 
						y:		render_height
					});
					render_height += lh;
				}
				tb.render = {
					height: render_height, 
					lines: render_lines
				};
			}
			
			switch (tb.position) {
				case "top":
					tb.y = 0;
				break;
				
				case "bottom":
					tb.y = viewport.height - tb.render.height;
				break;
				
				case "middle":
					tb.y = Math.round((viewport.height - tb.render.height) / 2);
				break;
			}
			
			ctx.shadowColor = tb.strokeColor;
			ctx.fillStyle = tb.textColor;
			
			for (var i = 0, l = tb.render.lines.length; i < l; ++i) {
				var line = tb.render.lines[i];
				ctx.font = line.font;
				fillStrokeText(tb.x + line.x, tb.y + line.y, line.text, tb.fontStroke.val());
			}
		}
		
		ctx.restore();
	}

	function addTextBox(title, position) {
		var tb = {
			id:				textboxes.length, 
			fontSize:		40, 
			width:			viewport.width - 10, 
			x:				5, 
			y:				0, 
			strokeColor:	'#000000', 
			textColor:		'#FFFFFF', 
			title:			title, 
			position:		position, 
			text:			""
		};
		
		var invalidate = function () {
			tb.render = false;
			repaint();
		}
		
		var editor = $(tpl.textBoxEdit(tb));
		
		tb.editor = editor;
		tb.text = editor.find('.js-meme_text').on('input change', invalidate).val(tb.text);
		
		tb.fontSize = editor.find('.js-meme_font_size').val(MEME_FONT_SIZE).on('input change', invalidate);
		tb.fontStroke = editor.find('.js-meme_font_stroke').val(MEME_STROKE).on('input change', invalidate);
		
		wrap.find('.js-meme_form').append(editor);
		textboxes.push(tb);
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
