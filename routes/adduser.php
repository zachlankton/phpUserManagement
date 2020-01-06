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
	
	$create_user_query = "CREATE USER '$user'@'localhost' IDENTIFIED WITH mysql_native_password BY '$pw'; ";
	$insert_user_query = "INSERT INTO `Users`.`accounts`(`account_name`)VALUES('$user'); ";
	$insert_user_role = "INSERT INTO `Application`.`user_roles`(`user`, `role`)VALUES('$user', 'User'); ";
	$grant_user_query = "GRANT SELECT ON `Users`.* TO '$user'@'localhost'; ";
	$grant_app_query = "GRANT SELECT ON `Application`.* TO '$user'@'localhost'; ";
	$grant_err_log = "GRANT INSERT ON `Application`.`access_error_log` TO '$user'@'localhost'; ";
	try
	{
		$cuq = $pdo->query($create_user_query);
		$iuq = $pdo->query($insert_user_query);
		$iur = $pdo->query($insert_user_role);
		$guq = $pdo->query($grant_user_query);
		$gaq = $pdo->query($grant_app_query);
		$gel = $pdo->query($grant_err_log);
		couch("/userdb_$user", "PUT");
		
	}
	catch (Exception $e)
	{
		echo $e->getMessage();
		die();
	}
	
	echo "Successfully Added New User $user";
?>
