<?php
  $req = $_SERVER['REQUEST_METHOD'];
  $uri = substr($_SERVER['REQUEST_URI'], 6);
  //echo $req;
  //echo $uri;
  // create curl resource
        $ch = curl_init();

        // set url
        curl_setopt($ch, CURLOPT_URL, "http://127.0.0.1:5984/");

        //return the transfer as a string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        // $output contains the output string
        $output = curl_exec($ch);

        // close curl resource to free up system resources
        curl_close($ch); 

        echo $output;
?>
