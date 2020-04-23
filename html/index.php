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

	// load chrome headless browser to parse page before
	// sending to prince pdf
	
	use Nesk\Puphpeteer\Puppeteer;
	use Nesk\Rialto\Data\JsFunction;
	

	// START SESSION
	session_start();

	// CAPTURE REQUESTED URI
	$uri = $_SERVER["REQUEST_URI"];
	$query_str = $_SERVER["QUERY_STRING"];
	$uri = str_replace($query_str, "", $uri);
	$uri = str_replace("?", "", $uri); //Remove "?" From URI;

	check_logout();

	// mysql xDevApi Session Object
	$xCli = NULL;
	$xDev = NULL;

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
	
	session_write_close();

	if ($user_enabled == false){
		echo "<h1>User is Disabled!</h1>";
		logout();
	}

	// Composer Autoload
	require './vendor/autoload.php';

	setup_error_reporting();

	// find a matching route
	$routes 	= get_routes($uri);
	$route_match 	= $routes[0]['route'] ?? NULL;
	$route_vars 	= get_route_vars();
	$req_type 	= $_SERVER['REQUEST_METHOD'];
	$referer 	= isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "";
	
	if ($user_is_super){
		super_user_routes_match();
	}else{
		check_permissions();
	}

	regular_user_routes_match();
	
	load_routes();

	admin_edit_icon();

    die();






/*
================================================================================================
================================ FUNCTION DEFINITIONS ==========================================
================================================================================================
*/


	function shutdown()
	{
		global $xDev;
		$xDev->close();
	}

	register_shutdown_function('shutdown');

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

	function check_permissions(){
		global $pdo;
		global $uri;
		global $route_match;
		global $req_type;
		global $user;
		global $user_roles;
		$failed = FALSE;
		$match_count = 0;
		
		$userdb = "/couch/userdb_$user";
		if (strpos($uri, $userdb) === 0){
			// user is trying to access their own db
			// we can end here
			return 0;
		}

		// Find a Route that Matches the Requested URI
		$sth = $pdo->prepare("
			SELECT * FROM Application.`route_permissions` 
			WHERE request_type = :req_type AND `route` = :route_match
			ORDER BY `route_permissions`.`priority` ASC ");
		$sth->execute( array(':req_type' => $req_type, ':route_match' => $route_match) );
		$routes = $sth->fetchAll(PDO::FETCH_ASSOC);
		// If there are no results then 404
		$match_count = count($routes);
		if ($match_count == 0){
			http_response_code(404);
			echo "URI: ".$uri."<br>";
			echo "Request Type: " . $req_type . "<br>";
			echo "Permissions For Route and Request Type Not Established.";
			$failed = TRUE;
			$err = "404 NOT FOUND";
		}

		// Check if the User Has the Appropriate Role to Access The Requested Resource
		// If not then issue 403 Forbidden!
		$role = $routes[0]['role'];
		
		if (!$failed && !in_array($role, $user_roles)) {
			http_response_code(403);
			echo "URI: ".$uri."<br>";
			echo "Request Type: " . $req_type . "<br>";
			echo "User Forbidden to Access This Resource! <br>";
			echo $role . " Role Required ";
			$failed = TRUE;
			$err = "403 FORBIDDEN";
		}
		if ($failed) {
			// INSERT ACCESS FAILURE INTO DB
			// This Helps Admin Determine Not Just What users are trying to access
			// But What Permissions are Failing in the event that the access
			// should be granted, then admins can quickly see what role the user
			// should have and quickly adjust permissions to correct issues
			$sth = $pdo->prepare("INSERT INTO `Application`.`access_error_log`(`error_type`, 
				`user_name`, `uri_requested`, `request_type`, `role_required`, `route_match`, `match_count`) 
				VALUES (:err,:user,:uri,:req_type,:role,:match,:count) ");
			
			$values = array(
				':err'      => $err,
				':user'     => $user,
				':uri'      => $uri,
				':req_type' => $req_type,
				':role'     => $role,
				':match'    => $route_match,
				':count'    => $match_count
			);
			$sth->execute( $values );
			die();
		}
		
	}

	function regular_user_routes_match(){
		
		global $uri;
		global $req_type;
		global $referer;
		global $query_str;
		
		// REGULAR USER ROUTES
		if ( substr($uri, 0, 6) == '/couch' ) {
			$json_string = file_get_contents('php://input');
			couch($uri.'?'.$query_str, $req_type, $json_string, false);
			die();
		} elseif ( substr($referer, 0, 28) == 'https://erp2.mmpmg.com/couch') {
			$json_string = file_get_contents('php://input');
			couch($uri.'?'.$query_str, $req_type, $json_string, false);
			die();
		} elseif ($uri == "/getuser") {
			require "../routes/getuser.php";
			die();
		}
	}

	function load_routes(){
		global $uri;
		global $routes;
		global $user_is_super;
		$route_match = $routes[0]['route'] ?? NULL;
		if ($route_match == NULL){
			http_response_code(404);
			echo "URI: ".$uri."<br>";
			echo "Route Not Found! <br>";
			if ($user_is_super){
				echo "<a href='/edit{$uri}'>Create Route</a>";
			}
			die();
		}
		
		$route_file_name = str_replace("/", "_", $route_match);
		$route_file_name = str_replace(".*", ".", $route_file_name);
		
		if (isset($_GET['getPDF'])){
			generatePDF();
		}else{
			require("/var/www/routes/app_routes/$route_file_name");
		}
		
	}

	function generatePDF(){
		require("prince.php");
		$prince = new Prince('/usr/bin/prince');
		$prince->setJavaScript(TRUE);
		$prince->setHTML(TRUE);

		$uri = $_SERVER["REQUEST_URI"];
		$uri = str_replace("getPDF", "", $uri);

		$puppet = new Puppeteer;
		$browser = $puppet->launch();
		$page = $browser->newPage();			
		$page->setCookie( ["name"=>"user", "value"=>$_SESSION['user'], "domain"=>"erp2.mmpmg.com" ] );
		$page->setCookie( ["name"=>"pw", "value"=>$_SESSION['pw'], "domain"=>"erp2.mmpmg.com" ] );
		$page->goto("https://erp2.mmpmg.com" . $uri, ["waitUntil"=>"networkidle2"]);
		$html = $page->evaluate(JsFunction::createWithBody("
		
			//remove all the scripts so prince does not try to run them again
		    document.querySelectorAll('script').forEach(function(n){n.remove()});
		    
		    // return rendered html page to be used for prince pdf
		    return document.documentElement.outerHTML;
		"));
		$browser->close();

		header('Content-Type: application/pdf');

		$errmsgs = [];
		$temp = tempnam("/var/www/files/tmp", "prince-pdf-gen-");
		$r = $prince->convert_string_to_file($html, $temp);
		$str = file_get_contents($temp);
		$str = preg_replace('/(\/CS \/DeviceRGB>>\n\/Annots[\s\S]*?)(stream[\s\S]*?endstream)/i', '$1', $str);
		file_put_contents($temp, $str);
		readFile($temp);
		die();
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
		
		global $xCli;
		global $xDev;
		global $pdo;
		global $user;
		global $uri;
		
		if ($_SERVER['REMOTE_ADDR'] == "142.44.147.12"){
			$_SESSION['user'] = $_COOKIE['user'] ?? "local_request";
			$_SESSION['pw'] = $_COOKIE['pw'] ?? "";
		}
		
		/* MySQL account username */
		$user = $_SESSION['user'] ?? $_POST['user'] ?? NULL ;

		/* MySQL account password */
		$passwd = $_SESSION['pw'] ?? $_POST['pw'] ?? NULL;

		/* Connection string, or "data source name" */
		$dsn = "mysql:host=localhost;charset=utf8";

		
		if ($user == "local_request" && 
		    $_SERVER['REMOTE_ADDR'] != "142.44.147.12"){
			echo "Login Failed!";
			die();
		}
		
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
			// mysql xDevApi Sessions Connection Connection Pool CURRENTLY NOT WORKING!
// 		    	$xCli = mysql_xdevapi\getClient("mysqlx://{$user}:{$passwd}@localhost");
// 			$xDev = $xCli->getSession();
			$xDev = mysql_xdevapi\getSession("mysqlx://{$user}:{$passwd}@localhost");
			
		        // Turn on the users roles for xDev... not sure why pdo works without this but xDev doesnt
			$xDev->sql("SET ROLE ALL")->execute();
			
			/* PDO object creation */
		   $pdo = new PDO($dsn, $user,  $passwd, array(PDO::ATTR_PERSISTENT => true) );

		   /* Enable exceptions on errors */
		   $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		}
		catch (Exception $e)
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
		
		if ($user == "local_request"){
			$user_enabled = true;
			$user_is_super = true;
			$_SESSION['user_id'] = "local_request";
			$_SESSION['user_enabled'] = true;
			$_SESSION['user_is_super'] = true;
			$user_info = [];
			return $user_info;
		}
		
		try
		{
			$sth = $pdo->prepare("
				SELECT
				    account_id AS `user_id`,
				    account_name AS `user_name`,
				    account_reg_time AS `registered_since`,
				    account_enabled AS `user_enabled`,
				    super_user AS `user_is_super`,
				    `nickname`,
				    `first_name`,
				    `middle_name`,
				    `last_name`,
				    `title`,
				    `department`,
				    `phone`,
				    `address`,
				    `mobile_phone`,
				    `email`,
				    `emergency_contact`,
				    `emergency_phone`,
				    `birthday`,
				    `hire_date`,
				    (
				    SELECT
					GROUP_CONCAT(role)
				    FROM
					`Application`.`user_roles`
				    WHERE
					USER = :user
				) AS `roles`
				FROM
				    `Users`.`accounts`
				WHERE
				    account_name = :user
			");
			$sth->execute(array(':user'=> $user));
			/* Fetch all of the remaining rows in the result set */
			//$user_info = $sth->fetchAll(PDO::FETCH_COLUMN, 0);
		}catch (PDOException $e)
		{
			echo $e->getMessage();
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
			'user_name' 		=> $user,
			'user_id'		=> $user_id,
			'user_is_super'		=> $user_is_super,
			'user_enabled' 		=> $user_enabled,
			'nickname'		=> $row['nickname'],
			'first_name'		=> $row['first_name'],
			'middle_name'		=> $row['middle_name'],
			'last_name'		=> $row['last_name'],
			'title'			=> $row['title'],
			'department'		=> $row['department'],
			'phone'			=> $row['phone'],
			'mobile_phone'		=> $row['mobile_phone'],
			'address'		=> $row['address'],
			'email'			=> $row['email'],
			'emergency_contact'	=> $row['emergency_contact'],
			'emergency_phone'	=> $row['emergency_phone'],
			'birthday'		=> $row['birthday'],
			'hire_date'		=> $row['hire_date'],
			'roles'			=> $user_roles
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
	
	echo "<script begin include_route href=\"$route_match\">//Begin Include Route $route_match</script>";
    require ("/var/www/routes/app_routes/$route_file_name");
	echo "<script end include_route href=\"$route_match\">//End Include Route $route_match</script>";
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


	function check_logout(){
		
		global $uri;
		global $pdo;
		global $user;
		
		// IF USER IS AUTHENTICATED AND REQUESTING LOGOUT
		if ($uri == '/logout'){
			logout();
		}
	}

	function logout() {
		$_SESSION = array();
		session_destroy();
		setcookie(session_name(),'',0,'/');
		session_regenerate_id(true);
		echo "Successfully Logged Out!  <br> <a href='/'>Login</a>";
		die();
	}

	function admin_edit_icon(){

		global $user_is_super;
		
		// If we are a super user add a handy edit button in the lower right corner of the page.
		if ($user_is_super){
		?>
			<script>
			var d = document.createElement("div");
			var a = document.createElement("a");
			a.href = "/edit" + location.pathname;
			a.innerHTML = "✎";
			d.append(a);
			d.setAttribute("style", "position: fixed;height:30px;width:30px;bottom:0px;right:0px;background-color:yellow;text-align: center;font-size: 24px;");
			document.body.append(d);
			</script>
		<?php
		}
	}

	function couch($uri, $req, $json_string = "", $return_arr = true){
		// $json_string should come from php://input
		// ie: $json_string = file_get_contents('php://input');
		// or from json string: json_encode( array( 'include_docs' => 'true' ) )
		
		// if this request is for adding an admin then do nothing
		if (strpos($uri, "/couch/_node/couchdb@127.0.0.1/_config/admins/") !== FALSE){
			die();
		}
		
		// if this uri contains "/couch" then strip it for forward to couchdb server
		if (substr($uri, 0, 6) == "/couch"){
			$uri = substr($uri, 6);
		}
		
		// create curl resource
		$ch = curl_init();
		
		$port = "5984";
		
		// set url
		curl_setopt($ch, CURLOPT_URL, "http://127.0.0.1:$port".$uri);
		
		if (strpos($uri, '/_changes') !== FALSE && $_SERVER['HTTP_ACCEPT'] == 'text/event-stream'){

			curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
				'Content-Type: text/event-stream'
				)                                                                       
			);  
			header("Content-Type: text/event-stream");
			$callback = function ($ch, $str) {
				echo $str;
				while (ob_get_level() > 0) {
				    ob_end_flush();
				}
				    flush();
				return strlen($str);//return the exact length
			    };
			curl_setopt($ch, CURLOPT_WRITEFUNCTION, $callback);
			curl_exec($ch);
			curl_close($ch);
		}else{

			//return the transfer as a string
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);

			// Set Request Type (GET, POST, PUT, DELETE)
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $req);

			// Set Payload
			curl_setopt($ch, CURLOPT_POSTFIELDS, $json_string); 

			// Set Content Type and Length
			// need to set this to application/json for most requests
			// however when uploading files in fauxton, like a PDF
			// it is necessary to get the Request Content Type...
			$cType = $_SERVER["CONTENT_TYPE"] ?? "application/json";
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
				'Content-Type: ' . $cType,                                                                                
				'Content-Length: ' . strlen($json_string))                                                                       
			);
			
			curl_setopt($ch, CURLOPT_HEADER, false);
			
			if (!$return_arr){
				$header_cb = function($ch, $str) {
					$len = strlen($str);
					header( $str );
					return $len;
				};
				curl_setopt($ch, CURLOPT_HEADERFUNCTION, $header_cb);
			}
				
			$output = "";
			$write_cb = function ($ch, $str) use ($return_arr, &$output) {
				$len = strlen($str);
				
				if ($return_arr){
					$output .= $str;
				}else{
					echo( $str );
				}
				
				return $len;
			};
			curl_setopt($ch, CURLOPT_WRITEFUNCTION, $write_cb);
			
			// $output contains the output string

			curl_exec($ch);

			// close curl resource to free up system resources
			curl_close($ch); 

			// Set Content Type and Respond!
			if ($return_arr){
				return json_decode($output);
			}
			die();
		}
	}
		    
?>
