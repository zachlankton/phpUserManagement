<?php

  global $pdo;
  global $account;

  $user = $account->getName();
  echo $user;
  
  $sth = $pdo->prepare("SELECT role FROM Application.`user_roles` WHERE user = :user ");
  $sth->execute(array("user"=>$user));
  /* Fetch all of the remaining rows in the result set */
  $roles = $sth->fetchAll(PDO::FETCH_NUM);
  header('Content-Type: application/json');
  echo json_encode($roles);
  die();

  http_response_code(403);
  echo "Checking Permissions!";
  die();

?>
