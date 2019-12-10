<?php
  global $pdo;
  
  $sth = $pdo->prepare("SELECT account_id, account_name, account_reg_time, account_enabled, super_user FROM accounts");
  $sth->execute();

  /* Fetch all of the remaining rows in the result set */
  $result = $sth->fetchAll(PDO::FETCH_ASSOC);

  header('Content-Type: application/json');
  echo json_encode($result);
?>
