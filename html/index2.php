<?php

	/*
		===================================
		The purpose of this file is to require any requests that are not
		publically available in the /var/www/html folder to be authenticated.
		====================================
		Authenticated Request will then be forwarded to the routes.php file
		
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

	// global db connection object
	$pdo = NULL;
	$user = "";
	authenticate_session();

	$user_id = NULL;
	$user_enabled = NULL;
	$user_is_super = false;
	$user_info = NULL;
	$user_roles = [];
	get_user_roles();
	get_user_info();


	echo json_encode($user_info);
	die();

	function authenticate_session(){
		
		global $pdo;
		global $user;
		
		/* MySQL account username */
		$user = $_SESSION['user'] ?? $_POST['user'] ?? NULL ;

		/* MySQL account password */
		$passwd = $_SESSION['pw'] ?? $_POST['pw'] ?? NULL;

		/* Connection string, or "data source name" */
		$dsn = "mysql:host=localhost;charset=utf8";

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
		   print "Error!: " . $e->getMessage() . "<br/>";
		   die();
		}
		
		$_SESSION['user'] = $_SESSION['user'] ?? $_POST['user'];
		$_SESSION['pw'] = $_SESSION['pw'] ?? $_POST['pw'];
		
	}



	function get_user_roles()
	{
		global $pdo;
		global $user;
		global $user_roles;
		
		try
		{
			$sth = $pdo->prepare("SELECT role FROM Application.`user_roles` WHERE user = :user ");
			$sth->execute(array(':user'=> $user));
			/* Fetch all of the remaining rows in the result set */
			$user_roles = $sth->fetchAll(PDO::FETCH_COLUMN, 0);
		}catch (PDOException $e)
		{
			throw new Exception("Could not get User Roles.");
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
			$sth = $pdo->prepare("SELECT 
				account_name, account_reg_time, account_enabled, super_user
				FROM `Users`.`accounts` WHERE account_name = :user ");
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
			$user_id = intval($row['account_id'], 10);
			$user_enabled = ($row['account_enabled'] == 1 ? true : false);
			$user_is_super = ($row['super_user'] == 1 ? true : false);
		}
		
		$user_info = [
			'user_name' 	=> $user,
			'user_id'	=> $user_id,
			'user_is_super'	=> $user_is_super,
			'user_enabled' 	=> $user_enabled,
			'roles'		=> $user_roles
		];
	}
	// Composer Autoload
	require './vendor/autoload.php';

	// CAPTURE REQUESTED URI
	$uri = $_SERVER["REQUEST_URI"];
	$query_str = $_SERVER["QUERY_STRING"];
	$uri = str_replace($query_str, "", $uri);
	$uri = str_replace("?", "", $uri); //Remove "?" From URI;

	/* Include the Account class file */
	require '../account_class.php';

	/* Create a new Account object */
	$account = new Account();



	// CHECK IF USER IS AUTHENTICATED BEFORE PROCEEDING WITH THE REQUEST
	try
	{
		$login = $account->sessionLogin();
	}
	catch (Exception $e)
	{
		echo $e->getMessage();
		die();
	}



	// IF REQUEST IS FOR '/login' then attempt login
	if ($uri == '/login'){
		
		// if no user is set in POST request then forward user to login page
		if (!isset($_POST['user'])){
			header("Location: /login.html");
			die();
		}
		
		// If there is already a user logged in... logout first.
		if ($login){
			$account->logout();
		}
		
		// get POST vars
		$user = $_POST['user'];
		$pw = $_POST['pw'];

		// ATTEMPT TO LOGIN USING CREDENTIAL SENT IN POST
		try
		{
			// LOGIN
			$login = $account->login($user, $pw);
			
			if ($login){
				// IF LOGIN SUCCESSFUL FORWARD USER TO THE ORIGINAL REQUESTED URI
				header("Location: ".$_SESSION['requested_uri']);
				die();
			}else{
				echo "Invalid User and/or Password!";
				die();
			}
			
		}
		catch (Exception $e)
		{
			// FAIL WITH MESSAGE
			echo $e->getMessage();
			die();
		}
	}


	



	// IF USER IS AUTHENTICATED AND REQUESTING LOGOUT
	if ($uri == '/logout'){
		$account->logout();
		$login = FALSE;
		echo "Successfully Logged Out!";
		die();
	}


	// IF WE HAVE MADE IT THIS FAR THE USER IS REQUESTING A RESOURCE
	// THAT IS NOT '/login' OR 'logout'

	if ($login) // if login returned true the user was successfully authenticated
	{
		// forward the request to the routes handler
		require "../routes.php";
	}
	else
	{
		// User is not Authenticated, capture this request URI and forward user
		// to login page
		if ($uri != "/login" && $uri != "/logout"){
			$_SESSION['requested_uri'] = $uri;
		}else{
			$_SESSION['requested_uri'] = "/";
		}
		header("Location: /login.html");
	}
?>
