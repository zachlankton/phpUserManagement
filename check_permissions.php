<?php

  global $pdo;
  global $account;
  global $uri;

  $user = $account->getName();
  $roles = $account->getUserRoles();
  $req_type = $_SERVER['REQUEST_METHOD'];
  

  // Find a Route that Matches the Requested URI
  $sth = $pdo->prepare("
    SELECT * FROM Application.`route_permissions` 
    WHERE request_type = :req_type AND :uri RLIKE route_regexp ");
  $sth->execute( array(':req_type' => $req_type, ':uri' => $uri) );
  $routes = $sth->fetchAll(PDO::FETCH_ASSOC);

  // If there are no results then 404
  if (count($routes) == 0){
    http_response_code(404);
    echo "URI: ".$uri."<br>";
    echo "Request Type: " . $req_type . "<br>";
    echo "Route Not Found For Request Type.";
    die();
  }
  
  // Check if the User Has the Appropriate Role to Access The Requested Resource
  // If not then issue 403 Forbidden!
  $role = $routes[0]['role'];
  if (!in_array($role, $roles)) {
    http_response_code(403);
    echo "URI: ".$uri."<br>";
    echo "Request Type: " . $req_type . "<br>";
    echo "User Forbidden to Access This Resource! <br>";
    echo $role . " Role Required ";
    die();
  }

?>
