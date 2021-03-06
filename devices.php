<?php 
	session_start();
		
	if(!isset($_SESSION['user_id']))
	{
		header('location:index.php');
		exit;
	}
	

?>

<?php
// Sample for querying the database, managing queue of device information
         
$deviceTimeout = 10;    // specify the timeout after which devices will be considered to have lost connection

function addDevice($db, $mac, $queue,$email, $isBeverage) {
    $sql ="INSERT INTO Devices(DeviceMac, QueueID, idKey,isBeverage) VALUES ('".$mac."', '".$queue."','".$email."', '".$isBeverage."')";
    $affected = pg_query($db, $sql);
    if (!isset($affected)) {
        http_response_code(500);
    }
}

function delDevice($db, $mac) {
    $sql ="DELETE FROM Devices WHERE DeviceMac='".$mac."'";
    $affected = pg_query($db, $sql);

    if (!isset($affected)) {
        http_response_code(500);
    }
}

function listDevices($db) {
    global $deviceTimeout;
	$sql ="SELECT DeviceMac, Status, QueueID, Queues.name, ClientType, ClientVersion, LastPoll, idKey, isBeverage FROM Devices INNER JOIN Queues ON Queues.id = Devices.QueueID";
    $results = pg_query($db, $sql);
    $rdata = array();
    $count = 0;

    if (isset($results)) {
        $now = time();

        while ($row = pg_fetch_row($results)) {
            $lpt = 0;    // last polling time
            if (intval($row[6]) > 0) {
                $lpt = intval($row[6]);
            }

            $secondsElapsed = intval($now) - intval($lpt);

            $rdata[$count] = array("mac" => strval($row[0]));

            if (intval($secondsElapsed) < intval($deviceTimeout)) {
                $rdata[$count] += array("status" => strval($row[1]));
            } else {
                $rdata[$count] += array("status" => "Connection Lost");
            }

            $rdata[$count] += array("queueId" => strval($row[2]));
            $rdata[$count] += array("queueName" => strval($row[3]));
            $rdata[$count] += array("clientType" => strval($row[4]));
            $rdata[$count] += array("clientVersion" => strval($row[5]));
            $rdata[$count] += array("lastConnection" => strval($row[6]));
            $rdata[$count] += array("lastPolledTime" => strval($secondsElapsed));
            $rdata[$count] += array("idKey" => strval($row[7]));
            $rdata[$count] += array("isBeverage" => strval($row[8]));

            $count++;
        }
 
        header("Content-Type: application/json");
        print_r(json_encode($rdata));
    } else {
        http_response_code(500);
    }
}

function handleGETRequest() {
    $host        = "host = 127.0.0.1";
    $port        = "port = 5432";
    $dbname      = "dbname =starprints";
    $credentials = "user = postgres password=password";


   $db = pg_connect( "$host $port $dbname $credentials"  );  

    if (!empty($_GET['new'])) {    
        $new = $_GET['new'];
    }

    if (!empty($_GET['queue'])) {    
        $queue = $_GET['queue'];
    }

    if (!empty($_GET['email'])) {    
        $email = $_GET['email'];
    }

    if (!empty($_GET['isBeverage'])) {
        $isBeverage = $_GET['isBeverage'];
    }


    if (!empty($_GET['del'])) {
        $del = $_GET['del'];
    }

    if (!isset($db) || empty($db)) {
        http_response_code(500);
        return;
    }

    if (isset($new) && isset($queue) && isset($email) && isset($isBeverage)) {
        addDevice($db, $new, $queue ,$email, $isBeverage);
    } elseif (isset($del)) {
        delDevice($db, $del);
    } else {
        listDevices($db);
    }

     pg_close($db);
}

function sessionexists(){
   if(!empty($_SESSION)){
     return true;
    }else{
     return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === "GET") {
       handleGETRequest();
       
} else {
    http_response_code(405);
}

?>
