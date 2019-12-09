<?php
	global $uri;
	global $account;

	if ($account->isSuper()){
		// SUPER USER ROUTES
		require "../../super_user_db.php";
		switch ($uri) {
			case "/adduser":
				require "../routes/adduser.php";
				die();
			
		}
	}else{
		require "../../regular_user_db.php";
	}


	// REGULAR USER ROUTES
	if ($uri == "/asdf") {
		require "../routes/asdf.php";
		die();
	} elseif ( substr($uri, 0, 6) == "/couch" ) {
		require "../routes/couch.php";
		die();
	} elseif ($uri == "/getuser") {
		require "../routes/getuser.php";
		die();
	}

	header("Location: /404.html");

?>
