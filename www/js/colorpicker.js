define(['jquery', 'utils', 'draggable'], function ($, utils, _) {
//
var tpl = {
	colorpicker: function () {
		var html = 
			'<div class="colorpicker">' + 
				'<table>' + 
					'<tr>' + 
						'<td>' + 
							'<div class="colorpicker-rect">' + 
								'<div>' + 
									'<div class="colorpicker-gradient_cursor"></div>' + 
								'</div>' + 
							'</div>' + 
						'</td>' + 
						'<td>' + 
							'<div class="colorpicker-spectrum">' + 
								'<div class="colorpicker-spectrum_cursor">' + 
							'</div>' + 
						'</td>' + 
						'<td style="max-width: 138px" class="colorpicker-big">' + 
							tpl.form() + 
						'</td>' + 
					'</tr>' + 
				'</table>' + 
				'<div class="colorpicker-small">' + 
					tpl.form() + 
				'</div>' + 
			'</div>';
		return html;
	}, 
	form: function () {
		var html = 
			'<div class="grey">' + 
				'Ваш цвет:' + 
			'</div>' + 
			
			'<div class="colorpicker-row">' + 
				'<div class="colorpicker-color"></div>' + 
			'</div>' + 
			
			'<div class="colorpicker-row">' + 
				'<input type="text" class="text-input" autocomplete="off" name="color" value="" maxlength="64" />' + 
			'</div>' + 
			
			'<div class="colorpicker-row">' + 
				'<div class="btn center js-cc_paste">' + 
					'Выбрать' + 
				'</div>' + 
			'</div>';
		return html;
	}
};
var expando = 'colorpicker-' + Date.now();

$.fn.colorpicker = function (opts) {
	var el = this.first();
	if (el.length) {
		var colorpicker = el.data(expando);
		if (opts === false) {
			if (colorpicker)
				colorpicker.destroy();
			colorpicker = null;
		} else {
			if (!colorpicker)
				el.data(expando, colorpicker = new Colorpicker(el, opts))
		}
		return colorpicker;
	}
	return null;
};

function Colorpicker(el, opts) {
	el.empty().append(tpl.colorpicker());
	
	var spectrum = el.find('.colorpicker-spectrum'), 
		gradient = el.find('.colorpicker-rect'), 
		preview = el.find('.colorpicker-color'), 
		gradient_cursor = el.find('.colorpicker-gradient_cursor'), 
		spectrum_cursor = el.find('.colorpicker-spectrum_cursor'), 
		color_input = el.find('input'), 
		paset_btn = el.find('.js-cc_paste'), 
		self = this, 
		hue, 
		saturation, 
		brightness;
	
	// API
	$.extend(self, {
		setRGB: setRGB, 
		setColor: function (hex_color) {
			var color = hex2color(hex_color);
			color && setRGB(color[0], color[1], color[2]);
		}, 
		color: getCurrentColor, 
		destroy: destroy
	});
	
	init();
	
	function init() {
		opts = $.extend({
			color: '#395387', 
			save: false
		}, opts);
		
		// Hue
		spectrum.draggable({
			relative:			true, 
			disableContextMenu:	false, 
			forceStart:			true, 
			forceMove:			true, 
			scroll:				true, 
			events: {
				dragMove: function (e) {
					setHSB(1 - Math.min(1, Math.max(e.rpH, 0)), saturation, brightness);
				}
			}
		});
		
		// Saturation + Brightness
		gradient.draggable({
			relative:			true, 
			disableContextMenu:	false, 
			forceStart:			true, 
			forceMove:			true, 
			scroll:				true, 
			events: {
				dragMove: function (e) {
					setHSB(hue, Math.min(1, Math.max(e.rpW, 0)), (1 - Math.min(1, Math.max(e.rpH, 0))));
				}
			}
		});
		
		color_input.on('input', function () {
			var hex_color = color_input.val(), 
				color = hex2color(hex_color);
			
			color_input.toggleClass('text-input_error', !color);
			if (color)
				setRGB(color[0], color[1], color[2], true);
		});
		paset_btn.click(function (e) {
			e.preventDefault();
			el.trigger('colorpicker:select', {
				color: getCurrentColor()
			});
		});
		
		var last_color;
		if (opts.save)
			try { last_color = localStorage.getItem('colopicker:' + el.data("id") + ':last'); } catch (e) { }
		self.setColor(last_color || opts.color);
	}
	
	function setRGB(r, g, b, from_input) {
		var hsb = rgbToHsv(r, g, b);
		setHSB(hsb[0], hsb[1], hsb[2], from_input);
	}
	
	function setHSB(h, s, b, from_input) {
		spectrum_cursor[0].style.top = ((1 - h) * 100) + "%";
		gradient_cursor[0].style.top = ((1 - b) * 100) + "%";
		gradient_cursor[0].style.left = (s * 100) + "%";
		
		if (h !== hue) {
			var rgb = hsvToRgb(h, 1, 1);
			gradient.css("background", '#' + hex(rgb[0]) + hex(rgb[1]) + hex(rgb[2]));
		}
		
		hue = h; saturation = s; brightness = b;
		
		var rgb_hex = getCurrentColor();
		if (!from_input)
			color_input.val(rgb_hex);
		preview.css("background", rgb_hex);
		
		el.trigger('colorpicker:live', {
			color: rgb_hex
		});
		
		if (opts.save) {
			try {
				localStorage.setItem('colopicker:' + el.data("id") + ':last', rgb_hex)
			} catch (e) { }
		}
	}
	
	function getCurrentColor() {
		var rgb = hsvToRgb(hue, saturation, brightness);
		return '#' + hex(rgb[0]) + hex(rgb[1]) + hex(rgb[2]);
	}
	
	function destroy() {
		el.removeData(expando).empty();
		spectrum = gradient = preview = gradient_cursor = 
			spectrum_cursor = color_input = paset_btn = null;
	}
}

function hex(v) {
	return utils.pad(v.toString(16).toUpperCase(), 2);
}

function hex2color(v) {
	var color = v.match(/([a-f0-9]+)/i), r, g, b;
	if (!color)
		return null;
	color = color[1];
	
	if (color.length == 6) {
		r = parseInt(color.substr(0, 2), 16);
		g = parseInt(color.substr(2, 2), 16);
		b = parseInt(color.substr(4, 2), 16);
	} else if (color.length == 3) {
		r = parseInt(color[0] + color[0], 16);
		g = parseInt(color[1] + color[1], 16);
		b = parseInt(color[2] + color[2], 16);
	} else {
		// Кривой цвет
		return null;
	}
	return [r, g, b];
}

function rgbToHsv(r, g, b) {
	r = r / 255, g = g / 255, b = b / 255;
	var max = Math.max(r, g, b), min = Math.min(r, g, b);
	var h, s, v = max;
	
	var d = max - min;
	s = max == 0 ? 0 : d / max;
	
	if (max == min) {
		h = 0; // achromatic
	} else {
		switch (max) {
			case r: h = (g - b) / d + (g < b ? 6 : 0); break;
			case g: h = (b - r) / d + 2; break;
			case b: h = (r - g) / d + 4; break;
		}
		h /= 6;
	}
	return [h, s, v];
}

function hsvToRgb(h, s, v) {
	var r, g, b;
	
	var i = Math.floor(h * 6);
	var f = h * 6 - i;
	var p = v * (1 - s);
	var q = v * (1 - f * s);
	var t = v * (1 - (1 - f) * s);
	
	switch (i % 6) {
		case 0: r = v, g = t, b = p; break;
		case 1: r = q, g = v, b = p; break;
		case 2: r = p, g = v, b = t; break;
		case 3: r = p, g = q, b = v; break;
		case 4: r = t, g = p, b = v; break;
		case 5: r = v, g = p, b = q; break;
	}
	return [Math.round(r * 255), Math.round(g * 255), Math.round(b * 255)];
}

//
});