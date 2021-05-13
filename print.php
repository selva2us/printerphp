<?php
// Sample for querying the database, configuring and triggering jobs

function triggerPrint($db, $mac, $queue) {
	// Get the next queue position
	$sql ="SELECT position FROM Queues WHERE id = '".$queue."'";
    $results = pg_query($db, $sql);
    if (isset($results)) {
        $row = pg_fetch_row($results);   // fetch next row

        if (isset($row) && !empty($row)) {
            $pos = intval($row[0]);
            $sql ="UPDATE Queues SET position = position + 1 WHERE id = '".$queue."'";
            $updateposition = pg_query($db, $sql);

            if (empty($updateposition))
            {
                http_response_code(500);
                return;
            }
            $sql ="UPDATE Devices SET Printing = '".$pos."' WHERE DeviceMac = '" .$mac."'";
            $updateprinting = pg_query($db, $sql);

            if (empty($updateprinting))
            {
                // error message
                http_response_code(500);
                return;
            }

        }

        print_r($pos);  
    }

	return;
}

function getQueueIDAndPrintingState($db, $mac) {
	$sql ="SELECT QueueID, Printing FROM Devices WHERE DeviceMac = '".$mac."'";
    $results = pg_query($db, $sql);

    if (isset($results)) {
        $row = pg_fetch_row($results);    // fetch next row

        if (isset($row) && !empty($row)) {
            return array($row[0], $row[1]);
        }

    }

    return array(NULL, NULL);
}

function handleGETRequest() {
    $host        = "host = 127.0.0.1";
    $port        = "port = 5432";
    $dbname      = "dbname =prodstarprint";
    $credentials = "user = postgres password=password";

   $db = pg_connect( "$host $port $dbname $credentials"  );
  
    if (!empty($_GET['mac'])) {    
        $mac = $_GET['mac'];
    }

    if (!isset($db) || empty($db)) {
        http_response_code(500);
        return;
    }

    if (!isset($mac) || empty($mac)) {
        http_response_code(400);       // no "mac" parameter(Bad Request)
        return;
    }

    list($queue, $printing) = getQueueIDAndPrintingState($db, $mac);

    if (!isset($queue))
    {
        http_response_code(400);
        return;    // Can't print a ticket if there is no queue assigned to this printer
    }

    if ((isset($printing)) && ($printing > 0))
    {
        http_response_code(200);
        return;    // Don't issue a ticket if one is currently printing
    }

    $pos = triggerPrint($db, $mac, $queue);

   pg_close($db);
}

if ($_SERVER['REQUEST_METHOD'] === "GET") {
    handleGETRequest();
} else {
    http_response_code(405);
}
?>
