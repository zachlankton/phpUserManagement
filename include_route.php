<?php
function include_route($uri, $ir_options = NULL)
{
    global $req_type; //request type, ie: GET, POST, PUT, DELETE, etc...
    global $pdo; // Database Connection
    global $query_str; // Query String Component of URL (ie: ?test=hello)
    global $account; // User Account Class
    $sth = $pdo->prepare("
      SELECT * FROM Application.`routes` 
      WHERE :uri RLIKE route_regexp 
      ORDER BY `routes`.`route` DESC ");
    $sth->execute(array(
        ':uri' => $uri
    ));
    $routes = $sth->fetchAll(PDO::FETCH_ASSOC);

    // If there are no results then use $uri
    $match_count = count($routes);
    if ($match_count == 0)
    {
        $route_match = $uri;
    }
    else
    {
        $route_match = $routes[0]['route'];
    }

    // PARSE ANY ROUTE VARIABLES INTO AN ASSOC ARRAY (OBJECT)
    $uri_split = explode("/", $uri);
    $route_split = explode("/", $route_match);

    foreach ($route_split as $key => $value)
    {
        $matches = array();
        $pMatch = preg_match("/^\{([\w]+)\}$/", $value, $matches);

        if ($pMatch)
        {
            $route_vars[$matches[1]] = $uri_split[$key];
        }
    }

    $route_file_name = str_replace("/", "_", $route_match);
    $route_file_name = str_replace(".*", ".", $route_file_name);
    require ("/var/www/routes/app_routes/$route_file_name");

}

?>
