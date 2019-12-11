<?php

  global $pdo;
  global $account;
  global $uri;

  $user = $account->getName();
  $roles = $account->getUserRoles();
  $req_type = $_SERVER['REQUEST_METHOD'];
  
  $sth = $pdo->prepare("
    SELECT * FROM Application.`route_permissions` 
    WHERE request_type = :req_type AND :uri RLIKE route_regexp ");
  $sth->execute( array(':req_type' => $req_type, ':uri' => $uri) );
  /* Fetch all of the remaining rows in the result set */
  $routes = $sth->fetchAll(PDO::FETCH_ASSOC);

  if (in_array($routes[0]['role'], $roles)) {
    echo "!User Has Permissions!";
  } else {
    echo "! DENIED !";
  }
  
  echo $uri;
  echo $user;
  echo json_encode($roles);
  echo json_encode($routes);
  
  die();

  http_response_code(403);
  echo "Checking Permissions!";
  die();

?>
