<?php 
	global $account;

	if (!isset($_POST['user'])){
		echo "No User!";
		die();
	}

	$user = $_POST["user"];
	$pw = $_POST["pw"];
	
	try
	{
		$newId = $account->addAccount($user, $pw);
	}
	catch (Exception $e)
	{
		echo $e->getMessage();
		die();
	}

	echo $newId;
?>
