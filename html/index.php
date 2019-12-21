<?php

	/*
		===================================
		The purpose of this file is to require any requests that are not
		publically available in the /var/www/html folder to be authenticated.
		====================================
		Authenticated Request will then be processed by the load_routes function
		
		DO NOT PLACE ANY FILE YOU DO NOT WANT PUBLIC IN THE
		'/var/www/html' folder
		
		LIKEWISE... PLACE PROTECTED FILES/ROUTES IN THE
		'/var/www/routes' folder
		
		==============================================
		
		nginx config is set to try all files/folders in the /var/www/html 
		folder and if none exist then route to this file.
		(   try_files $uri $uri/ /index.php ;   )
		
		This file will first check to see if the request is to
		authenticate a user and attempt to do so.
		
		If it is not a login request it will check to see
		if the session is already authenticated.
		
		It then checks if the request is for logout and will
		logout accordingly if so.
		
		After all else, it will either forward the AUTHENTICATED USER
		to the routes handler.
		=====OR=====
		It will forward the user to the login page.
	*/

	// START SESSION
	session_start();

	// CAPTURE REQUESTED URI
	$uri = $_SERVER["REQUEST_URI"];
	$query_str = $_SERVER["QUERY_STRING"];
	$uri = str_replace($query_str, "", $uri);
	$uri = str_replace("?", "", $uri); //Remove "?" From URI;

	// global db connection object
	$pdo = NULL;
	$user = "";
	authenticate_session();

	// user info variables
	$user_id 	= $_SESSION['user_id'] 		?? NULL;
	$user_enabled 	= $_SESSION['user_enabled'] 	?? NULL;
	$user_is_super 	= $_SESSION['user_is_super'] 	?? false;
	$user_roles 	= $_SESSION['user_roles'] 	?? [];
	$user_info 	= $_SESSION['user_info'] 	?? get_user_info();

	if ($user_enabled == false){
		echo "User is Disabled!";
		die();
	}

	// Composer Autoload
	require './vendor/autoload.php';

	// IF USER IS AUTHENTICATED AND REQUESTING LOGOUT
	if ($uri == '/logout'){
		$_SESSION = array();
		session_destroy();
		setcookie(session_name(),'',0,'/');
    		session_regenerate_id(true);
		echo "Successfully Logged Out!";
		die();
	}

	setup_error_reporting();

	// find a matching route
	$routes 	= get_routes($uri);
	$route_match 	= $routes[0]['route'];
	$route_vars 	= get_route_vars();
	$req_type 	= $_SERVER['REQUEST_METHOD'];
	$referer 	= isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "";
	
	if ($user_is_super){
		super_user_routes_match();
	}

	regular_user_routes_match();
	
	load_routes();







	function super_user_routes_match(){
		global $uri;
		global $route_match;
		global $req_type;
		global $user;
		global $user_roles;
		global $user_is_super;
		global $routes;

		$match = "";
		$match_count = 0;



		// match these static routes first
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

		// If there are no results then use $uri
		$match_count = count($routes);
		if ($match_count == 0){
			$route_match = $uri;
		}else{
			$route_match = $routes[0]['route'];
		}
	}

	function regular_user_routes_match(){
		global $pdo;
		global $uri;
		global $route_match;
		global $req_type;
		global $user;
		global $user_roles;
		global $user_is_super;
		$failed = FALSE;
		$match = "";
		$match_count = 0;




	}

	function load_routes(){
		global $uri;
		global $route_match;
		
		$route_file_name = str_replace("/", "_", $route_match);
		$route_file_name = str_replace(".*", ".", $route_file_name);
		require("/var/www/routes/app_routes/$route_file_name");
	}

	function get_route_vars(){
		global $uri;
		global $route_match;
		
		// PARSE ANY ROUTE VARIABLES INTO AN ASSOC ARRAY (OBJECT)
		$route_vars = [];
		$uri_split = explode("/", $uri);
		$route_split = explode("/", $route_match);

		foreach ($route_split as $key => $value) {
			$matches = array();
			$pMatch = preg_match("/^\{([\w]+)\}$/", $value, $matches);

			if ($pMatch){
				$route_vars[$matches[1]] = $uri_split[$key];
			}   
		}
		
		return $route_vars;
	}

	function setup_error_reporting(){
		global $user_is_super;

		if ($user_is_super){
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
		}

	}

	function authenticate_session(){
		
		global $pdo;
		global $user;
		global $uri;
		
		/* MySQL account username */
		$user = $_SESSION['user'] ?? $_POST['user'] ?? NULL ;

		/* MySQL account password */
		$passwd = $_SESSION['pw'] ?? $_POST['pw'] ?? NULL;

		/* Connection string, or "data source name" */
		$dsn = "mysql:host=localhost;charset=utf8";

		// if no user set then record requested URI and 
		// redirect to login screen
		if ($user == NULL){
			if ($uri != "/login" && $uri != "/logout"){
				$_SESSION['requested_uri'] = $uri;
			}else{
				$_SESSION['requested_uri'] = "/";
			}
			header("Location: /login.html");
			die();
		}
	
		/* Connection inside a try/catch block */
		try
		{  
		   /* PDO object creation */
		   $pdo = new PDO($dsn, $user,  $passwd, array(PDO::ATTR_PERSISTENT => true) );

		   /* Enable exceptions on errors */
		   $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		}
		catch (PDOException $e)
		{
			echo "Login Failed!";
		   	die();
		}
		
		$_SESSION['user'] = $_SESSION['user'] ?? $_POST['user'];
		$_SESSION['pw'] = $_SESSION['pw'] ?? $_POST['pw'];
		
		// if there was a previously unauthenticated request 
		// that was rerouted to the login screen
		// then route the user back to the originally requested URI
		if ( isset($_SESSION['requested_uri']) ){
			$req_uri = $_SESSION['requested_uri'];
			unset( $_SESSION['requested_uri'] );
			header("Location: " . $req_uri );
			die();
		}
		
	}

	function get_user_info()
	{	
		global $pdo;
		global $user;
		global $user_id;
		global $user_enabled;
		global $user_is_super;
		global $user_info;
		global $user_roles;
		
		try
		{
			$sth = $pdo->prepare("
				SELECT
				    account_id AS `user_id`,
				    account_name AS `user_name`,
				    account_reg_time AS `registered_since`,
				    account_enabled AS `user_enabled`,
				    super_user AS `user_is_super`,
				    group_concat(role) AS `roles`
				FROM
				    `Users`.`accounts`, `Application`.`user_roles`
				WHERE
				    account_name = :user AND user = :user
			");
			$sth->execute(array(':user'=> $user));
			/* Fetch all of the remaining rows in the result set */
			//$user_info = $sth->fetchAll(PDO::FETCH_COLUMN, 0);
		}catch (PDOException $e)
		{
			throw new Exception("Could not get User Roles.");
		}
		
		$row = $sth->fetch(PDO::FETCH_ASSOC);
			
		if (is_array($row))
		{
			/* Authentication succeeded. Set the class properties (id and name) and return TRUE*/
			$user_id = intval($row['user_id'], 10);
			$user_enabled = ($row['user_enabled'] == 1 ? true : false);
			$user_is_super = ($row['user_is_super'] == 1 ? true : false);
			$user_roles = explode(",", $row['roles']);
		}
		
		$user_info = [
			'user_name' 	=> $user,
			'user_id'	=> $user_id,
			'user_is_super'	=> $user_is_super,
			'user_enabled' 	=> $user_enabled,
			'roles'		=> $user_roles
		];
		
		$_SESSION['user_id'] = $user_id;
		$_SESSION['user_enabled'] = $user_enabled;
		$_SESSION['user_is_super'] = $user_is_super;
		$_SESSION['user_roles'] = $user_roles;
		$_SESSION['user_info'] = $user_info;
		
		return $user_info;
	}


function include_route($uri, $ir_options = NULL)
{
	global $req_type; //request type, ie: GET, POST, PUT, DELETE, etc...
	global $pdo; // Database Connection
	global $query_str; // Query String Component of URL (ie: ?test=hello)
	global $account; // User Account Class
    
	$routes = get_routes($uri);
    // If there are no results then use $uri
    $match_count = count($routes);
    if ($match_count == 0)
    {
        echo "Route: '$uri' - Not Found";
        return 0;
    }
    else
    {
        $route_match = $routes[0]['route'];
    }
    // PARSE ANY ROUTE VARIABLES INTO AN ASSOC ARRAY (OBJECT)
    $uri_split = explode("/", $uri);
    $route_split = explode("/", $route_match);
    foreach ($route_split as $key => $value)
    {
        $matches = array();
        $pMatch = preg_match("/^\{([\w]+)\}$/", $value, $matches);
        if ($pMatch)
        {
            $route_vars[$matches[1]] = $uri_split[$key];
        }
    }
    $route_file_name = str_replace("/", "_", $route_match);
    $route_file_name = str_replace(".*", ".", $route_file_name);
    require ("/var/www/routes/app_routes/$route_file_name");
}



function get_routes($uri){
	global $pdo;
	
	$query = "
		SELECT
		    `route`,
		    `route_regexp`,
		    `content-type`,
		    LENGTH(
			REGEXP_REPLACE(`route`, :r_len_regex, '')
		    ) AS `route_length` 
		FROM
		    `Application`.`routes`
		WHERE
		    :uri RLIKE route_regexp
		ORDER BY
		    `route_length`
		DESC
	";

	// USE PDO To Prepare The Query for Execution
	$prepared_statement = $pdo->prepare($query);
	$prepared_statement->execute( array( ':uri' => $uri, ':r_len_regex' => '\\{\\w+\\}' ) );
	$results_assoc_array = $prepared_statement->fetchAll(PDO::FETCH_ASSOC);

	return $results_assoc_array;
}
		    
		    
?>
