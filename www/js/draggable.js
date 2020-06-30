define(['jquery'], function ($) {
//
var expando = 'draggable-' + Date.now(), 
	MIN_GESTURE_DISTANCE = [50, 70], 
	MAX_GESTURE_DIFF = 60, 
	last_touch_end = 0, 
	ignore_mouse = false;

$.fn.draggable = function (opts) {
	var self = this;
	
	if (opts === false) {
		destroyDraggable(self);
		return self;
	}
	
	if (self.length > 1) {
		self.each(function () {
			$(this).draggable(opts);
		});
		return self;
	}
	
	if (!self.length || self.data(expando))
		return self;
	
	initDraggable(self, opts);
	
	return self;
};

$.draggableNoClick = function () {
	return Date.now() - last_touch_end < 100;
};

function initDraggable(el, opts) {
	opts = $.extend(true, {
		selector:				null, 
		onlyEvents:				true, 
		disableContextMenu:		true, 
		preventStart:			false, 
		detectZoom:				false, 
		forceStart:				false, 
		forceMove:				false, 
		relative:				false, 
		scroll:					false, 
		events:					false /* {dragStart, dragMove, dragEnd} */
	}, opts);
	
	var cleanup_data = {};
	el.data(expando, cleanup_data);
	
	el.on((opts.disableContextMenu ? 'contextmenu.draggable dblclick.draggable ' : '') + 'dragstart.draggable dragenter.draggable', function (e) {
		return false;
	});
	
	var last_x, last_y, $window = $(window), start_x, start_y;
	
	var in_touching = false, true_touch = false, 
		skip_multitouches = false, 
		start_x, start_y, last_x, last_y, 
		origin_dx, origin_dy, // Для смены центра (при зуме!)
		start_distance, in_drag, moved, 
		last_event;
	
	var trigger_event = function (name, data) {
		var ret = true;
		if (opts.events) {
			var func = opts.events[name];
			if (func)
				ret = func(data) === false;
		} else {
			var evt = new $.Event('x-' + name.toLowerCase());
			extend(evt, data);
			el.trigger(evt);
		}
		return ret;
	};
	var event_sender_func = function () {
		if (!in_touching)
			return;
		if (last_event) {
			trigger_event('dragMove', last_event);
			last_event = null;
		}
	}, event_sender;
	
	var mouse_global_events = function (attach) {
		var true_events = !!document.addEventListener;
		if (attach) {
			if (true_events) {
				document.addEventListener('mousemove', on_touchmove, false);
			} else {
				$document.on('mousemove.draggable', on_touchmove);
			}
		} else {
			if (true_events) {
				document.removeEventListener('mousemove', on_touchmove);
			} else {
				$document.off('mousemove', on_touchmove);
			}
		}
	};
	var prefix = opts.scroll ? 'page' : 'client', 
		relative_rect, 
		curr_element, 
		opt_calc_relative = !!opts.relative;
	
	if (opt_calc_relative && !opts.scroll)
		throw "`relative` depends on `scroll`";
	
	// Начало движения мыши/пальца
	var on_touchstart = function (e) {
		if (ignore_mouse && e.type == 'mousedown') // баг хрома какой-то, шлёт сначала тач ивенты, а потом mouse*.
			return; // При чём и с мобилы тоже. WTF?
		
		var touches = (e.originalEvent || e).touches;
		if (in_touching || (e.type == 'mousedown' && e.which != 1) || (touches && touches.length > 1 && skip_multitouches))
			return;
		
		if (opts.selector) { // Делегаты
			var cur = e.target, find = false;
			while (cur) {
				if (jQuery.find(opts.selector, this, null, [cur]).length > 0) {
					find = true;
					break;
				}
				cur = cur.parentNode;
			}
			if (!find)
				return;
			curr_element = $(cur);
		} else {
			curr_element = el;
		}
		
		in_touching = true;
		true_touch = (e.type == 'touchstart');
		
		if (true_touch)
			ignore_mouse = true;
		
		var touch = getPosition(e, prefix);
		origin_dx = origin_dy = 0;
		moved = in_drag = start_distance = false;
		start_x = last_x = touch[0];
		start_y = last_y = touch[1];
		
		if (opt_calc_relative) {
			var offset = curr_element.offset();
			relative_rect = {
				x: offset.left, 
				y: offset.top, 
				w: curr_element.width(), 
				h: curr_element.height()
			};
		}
		
		// Симулируем touchmove в touchstart
		on_touchmove(e, true);
		
		if (opts.preventStart && touches) {
			e.stopPropagation && e.stopPropagation();
			e.preventDefault && e.preventDefault();
			return false;
		}
		
		if (!true_touch)
			mouse_global_events(true);
	};
	
	// Движение мыши/пальца
	var on_touchmove = function (e, read_only) {
		if (!in_touching || (e.type == 'mousemove' && true_touch))
			return;
		
		if (!read_only) {
			e.stopPropagation && e.stopPropagation();
			e.preventDefault && e.preventDefault();
		}
		
		var touches = (e.originalEvent || e).touches, 
			changed = false;
		
		if (touches && !touches.length)
			return;
		
		var touch = getPosition(e, prefix);
		
		// Зум двумя пальцами
		if (touches) {
			if (touches.length > 1) {
				if (start_distance === false) {
					start_distance = calcDistance(touches);
					origin_dx = touch[2];
					origin_dy = touch[3];
					trigger_event('zoomStart', {});
				}
			} else if (start_distance !== false) {
				origin_dx = touch[0] - last_x;
				origin_dy = touch[1] - last_y;
				
				trigger_event('zoomEnd', {});
				start_distance = false;
			}
		}
		
		var x = touch[0] - origin_dx, y = touch[1] - origin_dy, 
			force_move = false;
		if (!in_drag && (last_x - x != 0 || last_y - y != 0 || opts.forceStart)) {
			in_drag = true;
			trigger_event('dragStart', {target: el[0], x: x, y: y, trueTouch: true_touch});
			force_move = opts.forceMove;
		}
		
		if (in_drag && (last_x != x || last_y != y || force_move)) {
			last_event = {
				x: x, y: y, 
				dX: x - start_x, dY: y - start_y, 
				dirX: last_x - x < 0 ? -1 : 1, 
				dirY: last_y - y < 0 ? -1 : 1, 
				target: el[0], 
				trueTouch: true_touch
			};
			
			if (opt_calc_relative) {
				last_event.relX = x - relative_rect.x;
				last_event.relY = y - relative_rect.y;
				last_event.rpW = last_event.relX / relative_rect.w; /* relative progress W */
				last_event.rpH = last_event.relY / relative_rect.h; /* relative progress H */
			}
			
			if (start_distance !== false) {
				var cur_distance = calcDistance(touches);
				if (cur_distance !== false)
					last_event.scale = cur_distance / start_distance;
			}
			
			trigger_event('dragMove', last_event);
			moved = true;
		}
		last_x = x; last_y = y;
		return false;
	};
	
	// Конец движения мыши/пальца
	var on_touchend = function (e) {
		// при мультитаче прилетают на каждый палец
		if (e.touches && e.touches.length)
			return;
		
		if (!in_touching || (e.type == 'mouseup' && true_touch))
			return;
		on_touchmove(e, true);
		
		in_touching = false;
		relative_rect = curr_element = null;
		
		if (start_distance !== false)
			trigger_event('zoomEnd', {});
			
		if (in_drag)
			trigger_event('dragEnd', {
				moved: moved, 
				dX: start_x - last_x, 
				dY: start_y - last_y, 
				x: last_x, y: last_y, 
				target: el[0], 
				trueTouch: true_touch
			});
		if (moved) {
			last_touch_end = Date.now();
			e.stopPropagation && e.stopPropagation();
			e.preventDefault && e.preventDefault();
		} else if (opts.preventStart && touches) {
			var target_el = document.elementFromPoint && 
				document.elementFromPoint(last_x+window.pageXOffset, last_y+window.pageYOffset);
			$(target_el ? target_el : el).trigger('click');
		}
		
		if (!true_touch)
			mouse_global_events(false);
	};
	
	// for remove
	var orig_el = el[0];
	cleanup_data.events = {
		'mouseup mousemove': [document, [on_touchmove, on_touchend]], 
		'touchstart mousedown': [orig_el, [on_touchstart]], 
		'touchmove': [orig_el, [on_touchmove]], 
		'touchend touchcancel': [orig_el, [on_touchend]]
	};
	if (orig_el.addEventListener) {
		orig_el.addEventListener('touchstart', on_touchstart, false);
		orig_el.addEventListener('touchmove', on_touchmove, false);
		orig_el.addEventListener('touchend', on_touchend, false);
		orig_el.addEventListener('touchcancel', on_touchend, false);
		document.addEventListener('mouseup', on_touchend, false);	
	} else {
		$document.on('mouseup.draggable', on_touchend);
	}
	
	if (navigator.userAgent.match(/MSIE [678]/)) { // IE не нужен :(
		$document.on('mousedown.draggable', function (e) {
			var p = el.offset(), w = el.outerWidth(), 
				h = el.outerHeight();
			if (p.left <= e.pageX && p.left + w >= e.pageX && p.top <= e.pageY && p.top + h >= e.pageY)
				return on_touchstart.apply(this, [e]);
		});
	} else {
		if (orig_el.addEventListener) {
			orig_el.addEventListener('mousedown', on_touchstart);
		} else {
			el.on('mousedown.draggable', on_touchstart);
		}
	}
}

function destroyDraggable(el) {
	var $document = $(document);
	$document.off('.draggable');
	$document.off('.draggable_tmp');
	
	this.each(function () {
		var el = $(this), 
			cleanup_data = el.data(expando);
		
		if (cleanup_data) {
			el.removeData(expando).off('.draggable').off('.draggable_tmp');
			
			var handlers = cleanup_data.events;
			if (el[0].removeEventListener && handlers) {
				each(handlers, function (v, k) {
					var events = k.split(" ");
					for (var j = 0; j < events.length; ++j) {
						for (var i = 0; i < v[1].length; ++i)
							v[0].removeEventListener(events[j], v[1][i]);
					}
				});
			}
		}
	});
}

function getPosition(e, prefix) {
	var e = e.originalEvent || e, 
		touches = e.touches, 
		clientX = prefix + 'X', 
		clientY = prefix + 'Y';
	if (touches) {
		if (touches.length < 2)
			return [touches[0][clientX], touches[0][clientY]];
		
		var x = (touches[0][clientX] + touches[1][clientX]) / 2, 
			y = (touches[0][clientY] + touches[1][clientY]) / 2;
		
		return [
			x, y, 
			x - touches[0][clientX], 
			y - touches[0][clientY]
		];
	}
	return [e[clientX], e[clientY]];
}

function calcDistance(touches) {
	if (touches && touches.length > 1) {
		var dx = (touches[1].clientX - touches[0].clientX),
			dy = (touches[1].clientY - touches[0].clientY);
		return Math.sqrt(dx * dx + dy * dy);
	}
	return false;
}

//
});