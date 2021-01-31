define(['jquery', 'functions'], function ($) {
//
var tpl = {
	spinner: function (msg) {
		return '<img src="/images/spinner2.gif" alt="" class="m" /> <span class="m">' + msg + '</span>';
	}
};

setTimeout(flyingHeart222s, 0);

function flyingHeart222s() {
	if (navigator.userAgent.indexOf('Android') >= 0)
		return;
	
	var hearts = [];
	var n = 10;
	
	getHeartsLine();
	
	window.addEventListener('resize', getHeartsLine, false);
	window.addEventListener('orientationchange', getHeartsLine, false);
	
	function getHeartsLine() {
		while (hearts.length) {
			var heart = hearts.pop();
			heart.parentNode.removeChild(heart);
		}
		
		var last_x = 30;
		while ((function () {
			var scale = rand(50, 150) / 100;
			var w = Math.ceil(55 * scale);
			var height_offset = rand(-window.innerHeight / 2, window.innerHeight / 2)
			var x = last_x;
			
			var move = rand(4, 7);
			var bounce = rand(2, 5);
			
			var heart = document.createElement('div');
			heart.className = 'heart';
			
			heart.style.transform = 'scale(' + scale + ') translate(' + (x / scale) + 'px, 0px)';
			heart.style.animation = 'heartmove ' + move + 's linear, heartbounce ' + bounce + 's linear';
			heart.style.animationIterationCount = 'infinite';
			heart.style.animationDelay = -(move / 100 * rand(0, 100)) + 's, 0s';
			
			document.body.appendChild(heart);
			
			hearts.push(heart);
			
			last_x += w/1.3;
			
			if (last_x > window.innerWidth)
				return false;
			return true;
		})());
	}
}

function flyingHearts() {
	var hearts = [];

	var next_frame = function () {
		if (navigator.userAgent.indexOf('Android') >= 0)
			return;

		setTimeout(function () {
			worker();
			window.requestAnimationFrame(next_frame);
		}, 1000 / 30);
	};
	next_frame();

	function rand(min, max) {
		return Math.floor(Math.random() * (max - min + 1)) + min;
	}
	
	function worker() {
		var main = document.getElementById('main'), 
			rect = main.getBoundingClientRect();

		var width = rect.left, height = window.innerHeight;
		if (!hearts.length) {
			var n = 40;
			for (var i = 0; i < n; ++i) {
				var el = new Image();
				el.src = 'https://emojipedia-us.s3.dualstack.us-west-1.amazonaws.com/thumbs/120/twitter/259/purple-heart_1f49c.png';
				el.style.position = 'fixed';
				el.style.zIndex = 99999999;
				el.style.width = rand(16, 32) + "px";
				el.style.opacity = '1';

				document.body.appendChild(el);

				hearts.push({
					dx: 0, 
					x: Math.random() * (width - 50), 
					y: Math.random() * height, 
					am: Math.random() * 20, 
					stepX: 0.02 + Math.random() / 10, 
					stepY: 0.7 + Math.random(), 
					rotate: rand(-30, 30), 
					el: el
				});
			}

		}

		var delta = (hearts.length / 2);
		for (var i = 0; i < delta; ++i) {
			hearts[i].y += hearts[i].stepY;
			if (hearts[i].y > height - 50) {
				hearts[i].x = Math.random() * (width - hearts[i].am - 50);
				hearts[i].y = 0;
				hearts[i].stepX = 0.02 + Math.random() / 10;
				hearts[i].stty = 0.7 + Math.random();
			}
			hearts[i].dx += hearts[i].stepX;
			hearts[i].el.style.top = hearts[i].y + "px";
			hearts[i].el.style.left = (hearts[i].x + hearts[i].am * Math.sin(hearts[i].dx)) + "px";
			hearts[i].el.style.transform = 'rotate(' + (hearts[i].rotate + 30 * Math.sin(hearts[i].dx)) + 'deg)';

			hearts[i+delta].el.style.top = hearts[i].y + "px";
			hearts[i+delta].el.style.left = rect.left + rect.width + (hearts[i].x + hearts[i].am * Math.sin(hearts[i].dx)) + "px";
			hearts[i+delta].el.style.transform = 'rotate(' + (hearts[i].rotate + 30 * Math.sin(hearts[i].dx)) + 'deg)';
		}
	}
}

//
});
