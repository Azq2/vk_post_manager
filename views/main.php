<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
		<meta http-equiv="X-UA-Compatible" content="IE=edge" />
		<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no" />
		<title><?= $title ?></title>
		
		<link rel="stylesheet" type="text/css" href="/css/main.css?<?= time(); ?>" />
		
		<?php if ($logged): ?>
			<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/emojione@4.5.0/extras/css/emojione.min.css" integrity="sha256-UZ7fDcAJctmoEcXmC5TPcZswNRqN/mLzj6uNS1GCVYs=" crossorigin="anonymous">
			<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/emojionearea@3.4.2/dist/emojionearea.min.css" integrity="sha256-LKawN9UgfpZuYSE2HiCxxDxDgLOVDx2R4ogilBI52oc=" crossorigin="anonymous">
			
			<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tui-image-editor@3.9.0/dist/tui-image-editor.min.css" integrity="sha256-bCMe+Bbn9sylVz64xzApEMo3tQ0A77v1pm+/7MAf728=" crossorigin="anonymous">
			<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tui-color-picker@2.2.6/dist/tui-color-picker.min.css" integrity="sha256-YXyPq5gUbyI1rL7bBKd9T7TW+Q8psU6EMveRMLM4iZU=" crossorigin="anonymous">
			
			<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/pickadate@3.6.4/lib/compressed/themes/classic.css" integrity="sha256-yEAddV9VwZMtV/g4lgh7jSH2wHoeYAQgeT8E/Z7fx8Q=" crossorigin="anonymous">
			<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/pickadate@3.6.4/lib/compressed/themes/classic.date.css" integrity="sha256-U24A2dULD5s+Dl/tKvi5zAe+CAMKBFUaHUtLN8lRnKE=" crossorigin="anonymous">
			
			<script src="https://cdnjs.cloudflare.com/ajax/libs/require.js/2.3.5/require.min.js" integrity="sha256-0SGl1PJNDyJwcV5T+weg2zpEMrh7xvlwO4oXgvZCeZk=" crossorigin="anonymous"></script>
			<script src="/js/init.js?r=<?= $revision ?>" id="loader_modules" data-revision="<?= $revision ?>"></script>
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
	</body>
</html>
