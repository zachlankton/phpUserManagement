<?php 
	global $pdo;

	if ($_SERVER['HTTP_REFERER'] == 'https://erp2.mmpmg.com/login.html'){
		require "../routes/adduser.html";
		die();
	}

	if (!isset($_POST['user'])){
		require "../routes/adduser.html";
		die();
	}

	$user = $_POST["user"];
	$pw = $_POST["pw"];
	
	$create_user_query = "CREATE USER '$user'@'localhost' IDENTIFIED BY '$pw'; ";
	$insert_user_query = "INSERT INTO `Users`.`accounts`(`account_name`)VALUES('$user'); ";
	$grant_user_query = "GRANT SELECT ON `Users`.* TO '$user'@'localhost'; ";
	$grant_app_query = "GRANT SELECT ON `Application`.* TO '$user'@'localhost'; ";
	try
	{
		$cuq = $pdo->query($create_user_query);
		$iuq = $pdo->query($insert_user_query);
		$guq = $pdo->query($grant_user_query);
		$gaq = $pdo->query($grant_app_query);
		
	}
	catch (Exception $e)
	{
		echo $e->getMessage();
		die();
	}
	
	echo "Successfully Added New User $user";
?>
