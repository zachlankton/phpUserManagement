<?php 
	global $pdo;

	if (!isset($_POST['user'])){
		require "../routes/adduser.html";
		die();
	}

	$user = $_POST["user"];
	$pw = $_POST["pw"];
	
	$create_user_query = "CREATE USER '$user'@'localhost' IDENTIFIED BY '$pw'; ";
	$grant_usage_query = "GRANT USAGE ON *.* TO '$user'@'localhost'; ";
	$grant_user_query = "GRANT SELECT ON `Users`.* TO '$user'@'localhost'; ";
	$grant_app_query = "GRANT SELECT ON `Application`.* TO '$user'@'localhost'; ";
	try
	{
		
	}
	catch (Exception $e)
	{
		echo $e->getMessage();
		die();
	}
	
	echo $newId;
?>
