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
			case "/getuser":
				require "../routes/getuser.php";
				die();
		}
	}else{
		require "../../regular_user_db.php";
	}


	// REGULAR USER ROUTES
	switch ($uri) {
		case "/asdf":
			require "../routes/asdf.php";
			die();
		case "/couch":
			require "../routes/couch.php";
			die();
	}



	

	header("Location: /404.html");

?>
