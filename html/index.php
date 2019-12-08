<?php
	
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

	// IF REQUEST IS FOR '/login' then attempt login
	if ($uri == '/login'){
		
		// if no user is set in POST request then forward user to login page
		if (!isset($_POST['user'])){
			header("Location: /login.html");
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



	// IF USER IS AUTHENTICATED AND REQUESTING LOGOUT
	if ($uri == '/logout'){
		$account->logout();
		$login = $account->sessionLogin();
	}


	// IF WE HAVE MADE IT THIS FAR THE USER IS AUTHENTICATED
	// PASS USER ON TO ROUTE HANDLER

	if ($login) // if login returned true -- meaning user was authenticated
	{
		require "../routes.php";
	}
	else
	{
		// User is not Authenticated, capture this request URI and forward user
		// to login page
		$_SESSION['requested_uri'] = $uri;
		header("Location: /login.html");
	}
?>
