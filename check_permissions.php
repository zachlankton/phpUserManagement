<?php

  global $pdo;
  global $account;

  $user = $account->getName();
  $roles = $account->getUserRoles();
  
  echo $user;
  echo json_encode($roles);
  
  die();

  http_response_code(403);
  echo "Checking Permissions!";
  die();

?>
