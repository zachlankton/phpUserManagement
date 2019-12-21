<?php
	require("../include_route.php");
	global $uri;
	global $account;
	$route_match = "";
	$route_vars = array();
	$req_type = $_SERVER['REQUEST_METHOD'];
	
	
	$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "";

	if ($account->isSuper()){
		
		// Turn on Error Reporting For Super Users!
		$whoops = new \Whoops\Run;
		$handler = new \Whoops\Handler\PrettyPageHandler;
		$handler->setEditor(function($file, $line) {
			if (strpos($file, "/var/www/routes/app_routes") > -1){
				$uri = str_replace("/var/www/routes/app_routes/", "", $file);
				$uri = str_replace("_", "/", $uri);
    				$uri = str_replace(".", ".*", $uri);
	    			return "https://erp2.mmpmg.com/edit$uri";
			} else {
				$uri = str_replace("/var/www", "", $file);
				return "https://github.com/zachlankton/phpUserManagement/blob/master$uri";
			}

		});
		$whoops->prependHandler($handler);
		$whoops->register();
		
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

	http_response_code(404);
	echo "<h1>404 - Not Found!</h1>";

?>
