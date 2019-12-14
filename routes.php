<?php
	global $uri;
	global $account;
	$route_match = "";
	$route_vars = array();
	$req_type = $_SERVER['REQUEST_METHOD'];
	
	$referer = $_SERVER['HTTP_REFERER'];

	if ($account->isSuper()){
		// SUPER USER ROUTES
		require "../../super_user_db.php";
		switch ($uri) {
			case "/addUser":
				require "../routes/adduser.php";
				die();
			case "/editUser":
				require "../routes/editUser.php";
				die();
			case "/getUserByName":
				require "../routes/getUserByName.php";
				die();
			case "/users":
				require "../routes/users.php";
				die();
			case "/admin":
				require "../routes/admin.php";
				die();
		}
	}else{
		require "../../regular_user_db.php";
		require "../check_permissions.php";
	}

	

	// REGULAR USER ROUTES
	if ($uri == "/asdf") {
		require "../routes/asdf.php";
		die();
	} elseif ( substr($uri, 0, 6) == "/couch" ) {
		require "../routes/couch.php";
		die();
	} elseif (strpos($referer, "/couch") !== FALSE) {
		require "../routes/couch.php";
		die();
	} elseif ($uri == "/getuser") {
		require "../routes/getuser.php";
		die();
	}


	require "../load_route.php";

	header("Location: /404.html");

?>
