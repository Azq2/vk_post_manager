define(['jquery', 'utils', 'howler'], function ($, utils) {
//

$('body').on('click', '.js-trigger_add', function (e) {
	e.preventDefault();
	
	var new_trigger = $('.js-triggers').children().first().clone(true);
	new_trigger.find('input:text').val('');
	$('.js-triggers').append(new_trigger);
}).on('click', '.js-trigger_delete', function (e) {
	e.preventDefault();
	
	if ($('.js-trigger').length == 1)
		$('.js-trigger_add').click();
	
	$(this).parents('.js-trigger').remove();
}).on('click', '.js-track_play, .js-track_pause', function (e) {
	var el = $(this), 
		track = el.parents('.js-track');
	
	$('.js-track').each(function () {
		if (this != track[0]) {
			if ($(this).data("howler"))
				$(this).data("howler").stop();
		}
	});
	
	var do_update = function () {
		track.find('.js-track_progress').css("width", (track.data("howler").seek() / track.data("howler").duration() * 100) + '%');
		if (track.data("howler").playing())
			requestAnimationFrame(do_update);
	};
	
	var do_start = function () {
		track.css("opacity", 1);
		track.find('.js-track_play').addClass('hide');
		track.find('.js-track_pause').removeClass('hide');
		do_update();
	};
	
	var do_stop = function () {
		track.css("opacity", 1);
		track.find('.js-track_play').removeClass('hide');
		track.find('.js-track_pause').addClass('hide');
	};
	
	track.css("opacity", 0.5);
	
	if (!track.data("howler")) {
		track.data("howler", new Howl({
			src:			[track.data("url")], 
			html5:			true, 
			onload:			function () {
				track.css("opacity", 1);
			}, 
			onplay:			do_start, 
			onloaderror:	do_stop, 
			onplayerror:	do_stop, 
			onend:			do_stop, 
			onpause:		do_stop, 
			onstop:			function () {
				track.find('.js-track_progress').css("width", "0%");
				do_stop();
			}, 
		}));
	}
	
	var howler = track.data("howler");
	
	if (howler.playing()) {
		howler.pause();
	} else {
		howler.play();
	}
});

//
});
