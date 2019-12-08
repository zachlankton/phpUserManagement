<?php

	session_start();
	$uri = $_SERVER["REQUEST_URI"];

	/* Include the database connection file (remember to change the connection parameters) */
	require '../../user_login_db.php';

	/* Include the Account class file */
	require '../account_class.php';

	/* Create a new Account object */
	$account = new Account();

	if ($uri == '/login'){
		if (!isset($_POST['user'])){
			header("Location: /login.html");
		}
		$user = $_POST['user'];
		$pw = $_POST['pw'];

		try
		{
			$login = $account->login($user, $pw);
			header("Location: ".$_SESSION['requested_uri']);
		}
		catch (Exception $e)
		{
			echo $e->getMessage();
			die();
		}
	}

	try
	{
		$login = $account->sessionLogin();
	}
	catch (Exception $e)
	{
		echo $e->getMessage();
		die();
	}

	if ($uri == '/logout'){
		$account->logout();
		$login = $account->sessionLogin();
	}

	if ($login)
	{
		require "../routes.php";
	}
	else
	{
		$_SESSION['requested_uri'] = $uri;
		header("Location: /login.html");
	}
?>
