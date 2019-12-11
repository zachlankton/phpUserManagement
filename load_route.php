<?php
  global $route_match;

  
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
