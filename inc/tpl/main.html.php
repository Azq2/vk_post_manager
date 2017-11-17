<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
		<meta http-equiv="X-UA-Compatible" content="IE=edge" />
		<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no" />
		<title><?= $title ?></title>
		
		<link rel="stylesheet" media="screen" type="text/css" href="i/main.css?<?= time(); ?>" />
		
		<script src="i/lib/jquery.min.js"></script>
		<script type="text/javascript" src="i/functions.js?<?= time(); ?>"></script>
	</head>
	
	<body>
		<div class="main">
			<div class="overlay hide" id="modal_overlay">
				<div class="modal" id="modal_content">
					
				</div>
			</div>
			
			<div class="row">
				<?= $sections_tabs ?>
				<div class="pad_t oh">
					<?= $comm_tabs ?>
					<span class="right grey"><?= $smm_money ?> р.</span>
				</div>
			</div>
			
			<?php if ($mysql): ?>
				<table class="table" width="100%">
					<tr>
						<th>
							Query
						</th>
						<th>
							Time
						</th>
						<th>
							Cost
						</th>
					</tr>
				<?php foreach ($mysql as $q): ?>
					<tr>
						<td>
							<?= htmlspecialchars($q['query']) ?>
						</td>
						<td>
							<?= sprintf("%.04f", $q['time']) ?>
						</td>
						<td>
							<?= sprintf("%.04f", $q['cost']) ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</table>
			<?php endif; ?>
			
			<div class="content">
				<?= $content ?>
			</div>
		</div>
	</body>
</html>
