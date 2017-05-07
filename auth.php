<?php
$_GET['a'] = 'oauth';
header("Location: index.php?".http_build_query($_GET));
