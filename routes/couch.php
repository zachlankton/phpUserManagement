<?php
  $req = $_SERVER['REQUEST_METHOD'];
  $uri = substr($_SERVER['REQUEST_URI'], 6);
  
  $data_string = file_get_contents('php://input');

  // create curl resource
  $ch = curl_init();

  // set url
  curl_setopt($ch, CURLOPT_URL, "http://127.0.0.1:5984/".$uri);

  //return the transfer as a string
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $req);

  //if ($req = "POST"){
  
  curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string); 
  curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
    'Content-Type: application/json',                                                                                
    'Content-Length: ' . strlen($data_string))                                                                       
  );    
  
//}

  // $output contains the output string
  $output = curl_exec($ch);

  $cType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

  // close curl resource to free up system resources
  curl_close($ch); 

  header('Content-Type: '.$cType);
  echo $output;
?>
