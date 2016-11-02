<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
		<meta http-equiv="X-UA-Compatible" content="IE=edge" />
		<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no" />
		<title><?= $title ?></title>
		
		<link rel="stylesheet" media="screen" type="text/css" href="i/main.css?<?= time(); ?>" />
		
		<script src="//code.jquery.com/jquery-1.9.1.js"></script>
		<script type="text/javascript" src="i/main.js?<?= time(); ?>"></script>
	</head>
	
	<body>
		<div class="main">
			<div class="overlay hide" id="modal_overlay">
				<div class="modal" id="modal_content">
					
				</div>
			</div>
			<?= $content ?>
		</div>
	</body>
</html>
