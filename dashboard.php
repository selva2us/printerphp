<?php 
	session_start();
		
	if(!isset($_SESSION['user_id']))
	{
		header('location:index.php');
		exit;
	}
	

?>

<!DOCTYPE html>
<html>
<head>
<title>FooBar</title>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="cpphp.css">
<script src="js/jquery-3.3.1.min.js"></script>
<script src="js/management.js"></script>
</head>

<body>
	<div class="container-dashboard">
		Welcome! <span class="user-name"><?php echo ucwords($_SESSION['first_name'])?> <?php echo ucwords($_SESSION['last_name']);?> </span> 
		<br>
		<h1>FooBar Printer Service</h1>

        <h2>Registered CloudPRNT Devices</h2>

        <div id="deviceList" class="datagrid"></div>

        <h2>Queues</h2>
        <div id="queueList" class="datagrid"></div>

        <h3>Device Configuration</h3>
        <p>To connect a new device, please set its CloudPRNT URL to:<br/>
            <div class="cpurl"><span id="cpurl">...</span></div><br/>
            then use the "Register A New Device" option to enable your device with this server.
        </p>
		<a href="logout.php?logout=true" class="logout-link">Logout</a>
	</div>
</body>
</html>
