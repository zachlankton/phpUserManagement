<?php

  global $uri;
  global $route_match;
  global $account;

  
  $user = $account->getName();
  $roles = $account->getUserRoles();
  $req_type = $_SERVER['REQUEST_METHOD'];
  $failed = FALSE;
  $match = "";
  $match_count = 0;


  // If we have made it to this file as a SUPER USER
  // Then Check Permissions was skipped and this needs to be
  // ran in order to match a route with the request.
  if ($account->isSuper()){
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
      die();
    }
    
    $route_match = $routes[0]['route'];
  }
  
  

  
  // Find a Route that Matches the Requested URI
  $sth = $pdo->prepare("
    SELECT * FROM Application.`routes` 
    WHERE route = :route");
  $sth->execute( array(':route' => $route_match) );
  $routes = $sth->fetchAll(PDO::FETCH_ASSOC);

  $m_count = count($routes);
  if ($m_count != 0){
    $content = $routes[0]['content'];
    eval($content);
    die();
  }
  
?>
