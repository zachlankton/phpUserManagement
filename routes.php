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
			break;
		case "/adduser":
			require "../routes/adduser.php";
			break;
		case "/getuser":
			require "../routes/getuser.php";
			break;
	}

	header("Location: /404.html");

?>
