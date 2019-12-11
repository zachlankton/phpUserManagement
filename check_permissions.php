<?php

  global $pdo;
  global $account;
  global $uri;

  $user = $account->getName();
  $roles = $account->getUserRoles();
  $req_type = $_SERVER['REQUEST_METHOD'];
  $failed = FALSE;
  $match = "";
  $match_count = 0;
  

  // Find a Route that Matches the Requested URI
  $sth = $pdo->prepare("
    SELECT * FROM Application.`route_permissions` 
    WHERE request_type = :req_type AND :uri RLIKE route_regexp ");
  $sth->execute( array(':req_type' => $req_type, ':uri' => $uri) );
  $routes = $sth->fetchAll(PDO::FETCH_ASSOC);

  // If there are no results then 404
  $match_count = count($routes);
  if ($match_count == 0){
    http_response_code(404);
    echo "URI: ".$uri."<br>";
    echo "Request Type: " . $req_type . "<br>";
    echo "Route Not Found For Request Type.";
    $failed = TRUE;
    $err = "404 NOT FOUND";
  }
  
  // Check if the User Has the Appropriate Role to Access The Requested Resource
  // If not then issue 403 Forbidden!
  $role = $routes[0]['role'];
  $match = $routes[0]['route'];
  if (!$failed && !in_array($role, $roles)) {
    http_response_code(403);
    echo "URI: ".$uri."<br>";
    echo "Request Type: " . $req_type . "<br>";
    echo "User Forbidden to Access This Resource! <br>";
    echo $role . " Role Required ";
    $failed = TRUE;
    $err = "403 FORBIDDEN";
  }

  if ($failed) {
    // Find a Route that Matches the Requested URI
  $sth = $pdo->prepare("INSERT INTO `Application`.`access_error_log`(`error_type`, 
  `user_name`, `uri_requested`, `request_type`, `role_required`, `route_match`, `match_count`) 
  VALUES (:err,:user,:uri,:req_type,:role,:match,:count) ");
    $values = array(
      ':err'      => $err,
      ':user'     => $user,
      ':uri'      => $uri,
      ':req_type' => $req_type,
      ':role'     => $role,
      ':match'    => $match,
      ':count'    => $match_count
    );
  $sth->execute( $values );
  die();
  }

?>
