<?php 
session_start();
  if (!isset($_SESSION["user_id"]))
   {
      header("location: index.php");
      exit;
   }else{
      header("location: dashboard.php");
      exit;       
   }
?>
