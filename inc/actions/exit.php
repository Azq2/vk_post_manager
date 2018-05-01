<?php
\Z\User::instance()->logout();
if (isset($_POST['ajax'])) {
	mk_ajax([]);
} else {
	header("Location: ?");
}
