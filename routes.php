<?php
	global $uri;
	global $account;

	if ($account->isSuper()){
		require "../../super_user_db.php";
	}else{
		require "../../regular_user_db.php";
	}

	switch ($uri) {
		case "/asdf":
			require "../routes/asdf.php";
			die();
		case "/adduser":
			require "../routes/adduser.php";
			die();
		case "/getuser":
			require "../routes/getuser.php";
			die();
	}

	header("Location: /404.html");

?>
