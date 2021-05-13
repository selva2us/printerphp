<?php 
$host        = "host = 127.0.0.1";
$port        = "port = 5432";
$dbname      = "dbname =prodstarprint";
$credentials = "user = postgres password=password";
$conn = pg_connect( "$host $port $dbname $credentials"  );
	
	if(!$conn)
	{
		die(pg_error());
	}
	
?>
