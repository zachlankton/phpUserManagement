<?php
	// Test Change
	ob_start();
	passthru("cd /var/www && /usr/bin/git pull 2>&1");
	$var = ob_get_contents();
	ob_end_clean(); //Use this instead of ob_flush()
	var_dump($var);
?>
