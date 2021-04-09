<?php
// Sample for querying the database, managing queue of job data information

function addQueue($db, $name) {
	$sql ="INSERT INTO Queues(name) VALUES ('".$name."')";
    $affected = pg_query($db, $sql);

    if (!isset($affected)) {
        http_response_code(500);
    }
}

function delQueue($db, $id) {
	$sql ="DELETE FROM Queues WHERE id='".$id."'";
    $affected = pg_query($db, $sql);

    if (!isset($affected)) {
        http_response_code(500);
    }
}

function resetQueue($db, $id) {
	$sql ="UPDATE Queues SET position = 1 WHERE id='".$id."'";
    $affected = pg_query($db, $sql);

    if (!isset($affected)) {
        http_response_code(500);
    }
}

function listQueues($db) {
	$sql =" SELECT id, name, position FROM Queues";
    $results = pg_query($db, $sql);
    $rdata = array();
    $count = 0;

    if (isset($results)) {
        while ($row = pg_fetch_row($results)) {
            $rdata[$count] = array("id" => strval($row[0]));
            $rdata[$count] += array("name" => $row[1]);
            $rdata[$count] += array("nextPos" => strval($row[2]));
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

    if (!empty($_GET['del'])) {    
        $del = $_GET['del'];
    }

    if (!empty($_GET['reset'])) {
        $reset = $_GET['reset'];
    }

    if (!isset($db) || empty($db)) {
        http_response_code(500);
        return;
    }

    if (isset($new)) {
        addQueue($db, $new);
    } elseif (isset($del)) {
        delQueue($db, $del);
    } elseif (isset($reset)) {
        resetQueue($db, $reset);
    } else {
        listQueues($db);
    }

    pg_close($db);
}

if ($_SERVER['REQUEST_METHOD'] === "GET") {
    handleGETRequest();
} else {
    http_response_code(405);
}
?>
