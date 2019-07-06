<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
		<meta http-equiv="X-UA-Compatible" content="IE=edge" />
		<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no" />
		<title><?= $title ?></title>
		
		<link rel="stylesheet" type="text/css" href="/static/css/vk_apps.css?<?= time(); ?>" />
		<script src="https://vk.com/js/api/xd_connection.js?2"  type="text/javascript"></script>
		<script src="/static/js/vendor/jquery.js"  type="text/javascript"></script>
	</head>
	
	<body>
		<div class="content">
			<?= $content ?>
		</div>
	</body>
</html>
