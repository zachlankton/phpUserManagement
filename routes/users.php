<?php
  global $pdo;
  
  $sth = $pdo->prepare("SELECT * FROM accounts");
  $sth->execute();

  /* Fetch all of the remaining rows in the result set */
  $result = $sth->fetchAll();
  var_dump($result);
?>
