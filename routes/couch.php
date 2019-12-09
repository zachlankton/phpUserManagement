<?php
  $req = $_SERVER['REQUEST_METHOD'];
  $uri = substr($_SERVER['REQ'], 6);
  echo $req;
  echo $uri;
?>
