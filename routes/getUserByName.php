<?php
  global $account;
  $name = $_GET['name'];
  echo $account->getIdFromName($name);
?>
