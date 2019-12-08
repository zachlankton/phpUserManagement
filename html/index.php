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

	// CAPTURE REQUESTED URI
	$uri = $_SERVER["REQUEST_URI"];

	/* Include the database connection file (remember to change the connection parameters) */
	require '../../user_login_db.php';

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

			// IF LOGIN SUCCESSFUL FORWARD USER TO THE ORIGINAL REQUESTED URI
			header("Location: ".$_SESSION['requested_uri']);
		}
		catch (Exception $e)
		{
			// FAIL WITH MESSAGE
			echo $e->getMessage();
			die();
		}
	}


	



	// IF USER IS AUTHENTICATED AND REQUESTING LOGOUT
	if ($uri == '/logout' && $login){
		$account->logout();
		$login = FALSE;
		echo "Successfully Logged Out!";
		die();
	}


	// IF WE HAVE MADE IT THIS FAR THE USER IS REQUESTING A RESOURCE
	// THAT IS NOT '/login' OR 'logout'
	
	var_dump($login);

	if ($login) // if login returned true the user was successfully authenticated
	{
		// forward the request to the routes handler
		require "../routes.php";
	}
	else
	{
		// User is not Authenticated, capture this request URI and forward user
		// to login page
		if ($uri == "/login"){
			echo "Invalid User and/or Password";
		}else{
			if ($uri != "/logout"){
				$_SESSION['requested_uri'] = $uri;
			}else{
				$_SESSION['requested_uri'] = "/";
			}
			//header("Location: /login.html");
		}
		
	}
?>
