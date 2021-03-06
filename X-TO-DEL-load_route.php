<?php

  global $uri;
  global $route_match;
  global $account;
  global $route_vars;

  
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
      SELECT * FROM Application.`routes` 
      WHERE :uri RLIKE route_regexp 
      ORDER BY `routes`.`route` DESC ");
    $sth->execute( array(':uri' => $uri) );
    $routes = $sth->fetchAll(PDO::FETCH_ASSOC);

    
    // If there are no results then use $uri
    $match_count = count($routes);
    if ($match_count == 0){
      $route_match = $uri;
    }else{
      $route_match = $routes[0]['route'];
    }
  }
  


  
// PARSE ANY ROUTE VARIABLES INTO AN ASSOC ARRAY (OBJECT)
    $uri_split = explode("/", $uri);
    $route_split = explode("/", $route_match);
    
    foreach ($route_split as $key => $value) {
        $matches = array();
        $pMatch = preg_match("/^\{([\w]+)\}$/", $value, $matches);
        
        if ($pMatch){
            $route_vars[$matches[1]] = $uri_split[$key];
        }   
    }


  
  // Get The Route
  $sth = $pdo->prepare("
    SELECT * FROM Application.`routes` 
    WHERE route = :route");
  $sth->execute( array(':route' => $route_match) );
  $routes = $sth->fetchAll(PDO::FETCH_ASSOC);

    
  $m_count = count($routes);
  if ($m_count != 0){
    //$content = $routes[0]['content'];
    $route = $routes[0]['route'];
    $route_file_name = str_replace("/", "_", $route);
    $route_file_name = str_replace(".*", ".", $route_file_name);
    require("/var/www/routes/app_routes/$route_file_name");
    
    
    // If we are a super user add a handy edit button in the lower right corner of the page.
    if ($account->isSuper()){

        ?>
        <script>
            var d = document.createElement("div");
            var a = document.createElement("a");
            a.href = "/edit" + location.pathname;
            a.innerHTML = "✎";
            d.append(a);
            d.setAttribute("style", "position: fixed;height:30px;width:30px;bottom:0px;right:0px;background-color:yellow;text-align: center;font-size: 24px;");
            document.body.append(d);
        </script>
        <?php
      
    }
    
    
    die();
  }
  
?>
