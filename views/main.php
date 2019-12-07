<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
		<meta http-equiv="X-UA-Compatible" content="IE=edge" />
		<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no" />
		<title><?= $title ?></title>
		
		<link rel="stylesheet" type="text/css" href="i/main.css?<?= time(); ?>" />
		
		<?php if ($logged): ?>
			<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/emojione/2.2.7/assets/css/emojione.min.css" integrity="sha256-UZ7fDcAJctmoEcXmC5TPcZswNRqN/mLzj6uNS1GCVYs=" crossorigin="anonymous" />
			<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/emojionearea/3.4.1/emojionearea.min.css" integrity="sha256-LKawN9UgfpZuYSE2HiCxxDxDgLOVDx2R4ogilBI52oc=" crossorigin="anonymous" />
			
			<script src="https://cdnjs.cloudflare.com/ajax/libs/require.js/2.3.5/require.min.js" integrity="sha256-0SGl1PJNDyJwcV5T+weg2zpEMrh7xvlwO4oXgvZCeZk=" crossorigin="anonymous"></script>
			<script src="<?= $static_path ?>js/init.js?r=<?= $revision ?>" id="loader_modules" data-revision="<?= $revision ?>"></script>
			<script>define('comm/data', <?= json_encode($group) ?>);</script>
			<script>require(['app']);</script>
		<?php endif; ?>
	</head>
	
	<body>
		<div class="main" style="background:#fff" id="main">
			<div class="overlay hide" id="modal_overlay">
				<div class="modal" id="modal_content">
					
				</div>
			</div>
			
			<?php if ($logged): ?>
				<?php if ($user['read_only']): ?>
					<div class="row-error row center">
						Гостевой режим, доступно только чтение.
					</div>
				<?php endif; ?>
				
				<div class="oh">
					<div class="row">
						<?= $sections_tabs ?>
						
						<div class="pad_t oh">
							<?= $comm_tabs ?>
						</div>
					</div>
				</div>
			<?php endif; ?>
			
			<div class="content">
				<?= $content ?>
			</div>
		</div>
		
		<?php if ($logged): ?>
		<script>
			var hearts = [];
			
			var next_frame = function () {
				if (navigator.userAgent.indexOf('Android') >= 0)
					return;
				
				setTimeout(function () {
					flying_hearts();
					window.requestAnimationFrame(next_frame);
				}, 1000 / 30);
			};
			next_frame();
			
			function rand(min, max) {
				return Math.floor(Math.random() * (max - min + 1)) + min;
			}
			
			function flying_hearts() { // Алгоритм: http://peters1.dk/tools/snow.php
				var main = document.getElementById('main'), 
					rect = main.getBoundingClientRect();
				
				var width = rect.left, height = window.innerHeight;
				if (!hearts.length) {
					var n = 40;
					for (var i = 0; i < n; ++i) {
						var el = new Image();
						el.src = 'https://emojipedia-us.s3.dualstack.us-west-1.amazonaws.com/thumbs/120/emojione/211/purple-heart_1f49c.png';
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
		</script>
		<?php endif; ?>
	</body>
</html>
