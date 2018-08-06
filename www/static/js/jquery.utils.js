define(['jquery'], function ($) {
//

/*
	Определение моментов, когда юзер проскроллил в конец или начало скролла
*/
$.fn.scrollMonitor = function (opts) {
	if (opts !== false) {
		opts = $.extend({
			up: 0,   // Мнимальная дельта сверху до срабатывания ивента
			down: 0, // Мнимальная дельта снизу до срабатывания ивента
			eventStart: "scrollStart", 
			eventEnd: "scrollEnd", 
			mainScroll: false
		}, opts);
	}
	var $w = $(window);
	return this.each(function() {
		var el = $(this), scroll_el = (opts.mainScroll ? $w : el), 
			last_scroll = scroll_el.scrollTop();
		if (opts === false) {
			scroll_el.off('.scrollMonitor');
		} else {
			scroll_el.on("scroll.scrollMonitor", function (e) {
				var cur_scroll = scroll_el.scrollTop(), 
					direction = last_scroll - cur_scroll > 0, 
					min_scroll = opts.mainScroll ? el.offset().top : 0, 
					max_scroll = opts.mainScroll ? min_scroll + el.outerHeight() - $w.innerHeight() : 
						el[0].scrollHeight - el.outerHeight();
				if (!direction && cur_scroll / max_scroll >= opts.down) {
					el.trigger(opts.eventEnd, {maxScroll: max_scroll, curScroll: cur_scroll});
				} else if (direction && 1 - ((cur_scroll - min_scroll) / (max_scroll - min_scroll)) >= opts.up) {
					el.trigger(opts.eventStart, {maxScroll: max_scroll, curScroll: cur_scroll});
				}
				last_scroll = cur_scroll;
			});
		}
	});
};

/* 
	Проскроллить до элемента
*/
$.fn.scrollTo = function (el, opts) {
	opts = $.extend({
		position: "top"
	}, opts);
	el = $(el);
	
	var self = this, 
		is_global_scroll = (self[0] == window || self[0] == document.body || 
			self[0] == document.documentElement), 
		scroll_getter = is_global_scroll ? $(window) : self, 
		self_offset = self.offset(), 
		el_offset = el.offset();
	
	if (self_offset && el_offset) {
		var scroll = is_global_scroll ? el_offset.top : el_offset.top + scroll_getter.scrollTop() - self_offset.top, 
			scroll_dir = opts.position;
		if (scroll_dir == "visible") {
			var x = is_global_scroll ? scroll_getter.scrollTop() : el_offset.top, 
				x2 = is_global_scroll ? x + $(window).innerHeight() : self_offset.top + self.innerHeight();
			
			if (el_offset.top < x) {
				scroll_dir = "top";
			} else if (el_offset.top + el.outerHeight() > x2) {
				scroll_dir = "bottom";
			} else {
				return;
			}
		}
		
		if (is_global_scroll) {
			if (scroll_dir == "center") {
				scroll -= $(window).innerHeight() / 2 - el.outerHeight() / 2;
			} else if (scroll_dir == "bottom") {
				scroll -= $(window).innerHeight() - el.outerHeight();
			}
		} else {
			if (scroll_dir == "center") {
				scroll -= self.innerHeight() / 2 - el.outerHeight() / 2;
			} else if (scroll_dir == "bottom") {
				scroll -= self.innerHeight() - el.outerHeight();
			}
		}
		self.scrollTop(scroll);
	}
	return self;
};

/*
	Костыль для блокирования mousewheel в родительских элементах
*/
$.fn.disableMousewheel = function () {
	var mustdie = /(trident|msie)/i.test(navigator.userAgent), 
		doc = document.documentElement, 
		event_name, events = {
			onmousewheel: 'mousewheel',
			onwheel: 'wheel', 
			DOMMouseScroll: 'DOMMouseScroll'
		};
	for (var k in events) {
		if (k in doc) {
			event_name = events[k];
			break;
		}
	}
	if (!event_name)
		return this;
	
	function prevent_scroll(e) {
		e.preventDefault();
		e.stopPropagation();
	}
	
	return this.each(function() {
		var el = $(this).on(event_name, function (e) {
			var orig_event = e.originalEvent, 
				cur_scroll = el[0].scrollTop, 
				max_scroll = el[0].scrollHeight - el.outerHeight(), 
				delta = -orig_event.wheelDelta;
			
			var focused = document.activeElement;
			if (focused && focused.tagName.toUpperCase() == "TEXTAREA")
				return;
			
			if (isNaN(delta))
				delta = orig_event.deltaY;
			var direction = delta < 0;
			if ((direction && cur_scroll <= 0) || (!direction && cur_scroll >= max_scroll)) {
				prevent_scroll(e);
			} else if (mustdie) {
				if (direction && -delta > cur_scroll) {
					el[0].scrollTop = 0;
					prevent_scroll(e);
				} else if (!direction && delta > max_scroll - cur_scroll) {
					el[0].scrollTop = max_scroll;
					prevent_scroll(e);
				}
			}
		});
	});
};

//
});
