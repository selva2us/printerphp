<?php 
	session_start();
		
	if(isset($_SESSION['user_id']))
	{
		header('location:dashboard');
		exit;
	}
	

?>
<?php 
	require('config.php');
	session_start();

	if(isset($_POST['submit']))
	{
		if((isset($_POST['email']) && $_POST['email'] !='') && (isset($_POST['password']) && $_POST['password'] !=''))
		{
			$email = trim($_POST['email']);
			$password = trim($_POST['password']);
			
			$sqlEmail = "select id,first_name,last_name,password from users where email = '".$email."'";
			$rs = pg_query($conn,$sqlEmail);
			$numRows = pg_fetch_row($rs);
			if(pg_num_rows($rs) != 1) {
				$errorMsg =  "Wrong Email Or Password";
			}else{
				$_SESSION['user_id'] = $numRows[0];
				$_SESSION['first_name'] = $numRows[1];
				$_SESSION['last_name'] = $numRows[2];
					
					//echo "<pre>";
					//print_r($_SESSION);
					//echo "</pre>";
					//exit;
					
				header('location:dashboard.php');
				exit;
			}
		}else
			{
				$errorMsg =  "No User Found";
			}	
	}
?>

<!DOCTYPE html>
<html>
<head>
<title>FoobarPrinterService</title>
<link rel="stylesheet" href="style.css">
</head>

<body>
	
	<div class="container">
		<h1>FooBar Printer Service</h1>
		<?php 
			if(isset($errorMsg))
			{
				echo "<div class='error-msg'>";
				echo $errorMsg;
				echo "</div>";
				unset($errorMsg);
			}
			
			if(isset($_GET['logout']))
			{
				echo "<div class='success-msg'>";
				echo "You have successfully logout";
				echo "</div>";
				
			}
			
			
			
		?>
		<form action="<?php echo $_SERVER['PHP_SELF']?>" method="post">
			<div class="field-container">
				<label>Email</label>
				<input type="email" name="email" required placeholder="Enter Your Email">
			</div>
			<div class="field-container">
				<label>Password</label>
				<input type="password" name="password" required placeholder="Enter Your Password">
			</div>
			<div class="field-container">
				<button type="submit" name="submit">Login</button>
			</div>
			
		</form>
	</div>
</body>
</html>
