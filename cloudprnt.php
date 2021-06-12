<?php
// Sample CloudPRNT Queue system handler

$host        = "host = 127.0.0.1";
$port        = "port = 5432";
$dbname      = "dbname =starprints";
$credentials = "user = postgres password=password";
$db = pg_connect( "$host $port $dbname $credentials"  );      // database into a single table for easier passing between functions
$cputilpath = "";

/*
    Determine the path of cputil and call name.
    If the path of "cputil" application set in the environment variable, 
    then it will be "cputil" or "cputil.exe". 
    If create the directory of "cputil" in same directory with "cloudprnt.php"
    and put the "cputil" application, then it will be "cputil\cputil.exe".
    (In this sample indicated on Windows environment)
*/
if (substr(PHP_OS,0,3) == 'WIN') {
    if (file_exists(dirname(__FILE__).'\cputil\cputil.exe')) {
        $cputilpath = 'cputil\cputil.exe';
    } else {
        $cputilpath = 'cputil.exe';
    }
} else {
    if (file_exists(dirname(__FILE__).'/cputil/cputil')) {
        $cputilpath = 'cputil/cputil';
    } else {
        $cputilpath = 'cputil';
    }
}

/*
	Use cputil to convert an input file to the requested output format and device width
	and write to a requested output file.
*/
function getCPConvertedJob($inputFile, $outputFormat, $deviceWidth, $outputFile){
    global $cputilpath;
    $options = "";

    if ($deviceWidth <= (58 * 8)) {
        $options = $options."thermal2";
    } elseif ($deviceWidth <= (72 * 8)) {
        $options = $options."thermal3";
    } elseif ($deviceWidth <= (82 * 8)) {
        $options = $options."thermal82";
    } elseif ($deviceWidth <= (112 * 8)) {
        $options = $options."thermal4";
    }

    $options = $options." scale-to-fit dither ";

    system($cputilpath." ".$options." decode \"".$outputFormat."\" \"".$inputFile."\" \"".$outputFile."\"", $retval);
}

/*
	Create the print job using Star Markup 
*/
function renderMarkupJob($filename, $position, $queue, $design, $email,$printid, $isBeverage) {
    $file = fopen($filename, 'w+');

    if ($file != FALSE) {
        $api_url = 'https://test01.myfoobarapp.com/fbar/v1/printer/'.$email.'?token='.$printid.'&isBeverage='.$isBeverage;
        //$api_url = 'https://test01.myfoobarapp.com/fbar/v1/printer/foobarappmsttest@gmail.com?token=514b6eb5-a166-455c-98e3-a858c08ba5ad';
	   $json_data = file_get_contents($api_url);        
	   fwrite($file,$json_data);   
       fclose($file);
    }
}

/*
	Read the formatting parameters for the specified queue id from the database (logo, header, footer, coupon...)
	and return as a table
*/
function getQueuePrintParameters($db, $queue) {
    $qfields = array();
    $qfields['Header'] = "";
    $qfields['Footer'] = "";
    $qfields['Logo'] = "";
    $qfields['Coupon'] = "";
	$sql ="SELECT Header, Footer, Logo, Coupon FROM Queues WHERE id = '" .$queue."';";
    $results = pg_query($db, $sql);
	
    if (isset($results)) {
        $row = pg_fetch_row($results);    // fetch next row

        if (isset($row) && !empty($row)) {
            $qfields['Header'] = $row[0];
            $qfields['Footer'] = $row[1];
            $qfields['Logo'] = $row[2];
            $qfields['Coupon'] = $row[3];
        } else {
            // error message
        }
    }

	return $qfields;
}

function handleCloudPRNTGetJob($db) {
    $content_type = $_GET['type'];    // determine the media type that the cloudPRNT device is requesting
                                      // and set it as the content type for this GET response
    // create temporary files for storing the source print job and the version converted to the format requested by the cloudprnt device
    // NOTE: using temporary files is usually very fast, because they will be generated in /tmp which is generally a RAM based filesystem
    //       but, this depends on the OS and distribution. If these files will be written to physical media then it may harm performance
    //       and cause unnecessary writes to disk.
    $mac = $_GET['mac'];
    $sql ="SELECT idKey, printid, isBeverage FROM Devices WHERE DeviceMac = '".$mac."';";
    $results = pg_query($db, $sql);
    $email ="";
    $printid = "";
    $isBeverage = "";
    if (isset($results)) {
        $row = pg_fetch_row($results);     // fetch next row
        $email = $row[0];
        $printid = $row[1];       
        $isBeverage = $row[2];
    } else {
        // error message
    }

    $basefile = tempnam(sys_get_temp_dir(), "markup");
    $markupfile = $basefile.".stm";                                                    // cputil used the filename to determing the format of the job that it is to convert
    $outputfile = tempnam(sys_get_temp_dir(), "output");

    list($position, $queue, $width) = getDevicePrintingRequired($db, $_GET['mac']);    // Find which queue and position is pending for this printer
    $ticketDesign = getQueuePrintParameters($db, $queue);                              // Get design fields for this queue
    
    renderMarkupJob($markupfile, $position, $queue, $ticketDesign, $email,$printid, $isBeverage);
    
    getCPConvertedJob($markupfile, $content_type, $width, $outputfile);                // convert the Star Markup job into the format requested
                                                                                       // by the CloudPRNT device
    header("Content-Type: ".$content_type);
    header("Content-Length: ".filesize($outputfile));
    readfile($outputfile);                                                             // return the converted job as the GET response
    
    // clean up the temporary files
    unlink($basefile);
    unlink($markupfile);
    unlink($outputfile);
}

/*
	Get a list of supported input types from "cputil" for a given input type
*/
function getCPSupportedOutputs($input) {
    global $cputilpath;
    $file = popen($cputilpath." mediatypes-mime \"text/vnd.star.markup\"", "r");

    if ($file != FALSE) {
        $output = fread($file, 8192);

        pclose($file);
        return json_decode($output);
    }

    return "";
}

/*
	Determine whether printing is required for a particular printer, buy checking it's 'Printing' field
	which will be set to the position number to be printer when a job is required.
	Return the position, queue ID and device print width if printing is required
*/
function getDevicePrintingRequired($db, $mac) {
	$sql ="SELECT Printing, QueueID, DotWidth FROM Devices WHERE DeviceMac = '".$mac."';";
    $results = pg_query($db, $sql);

    if (isset($results)) {
        $row = pg_fetch_row($results);     // fetch next row

        if (!isset($row) || (count($row) < 1)) {
            return array(NULL, NULL, NULL);
        } else {
            return array(intval($row[0]), intval($row[1]), intval($row[2]));
        }
    } else {
        // error message
    }
}

/*
	Query the database and return the stored print width for the device.
	This will be used by cputil for correctly formatting the print job.
*/
function getDeviceOutputWidth($db, $mac) {
	$sql ="SELECT DotWidth FROM Devices WHERE DeviceMac = '".$mac."';";
    $results = pg_query($db, $sql);
    
	$width;

    if (isset($results)) {
        $row = pg_fetch_row($results);     // fetch next row

        if (!isset($row) || (count($row) < 1)) {
            return 0;
        } else {
            $width = intval($row[0]);

            if (!isset($width)) {
                $width = 0;
            }

            return $width;
        }    
    } else {
        // error message
    }
}

function setDeviceInfo($db, $mac, $width, $clientType, $clientVer) {
	$sql ="UPDATE Devices SET DotWidth = '".$width."', ClientType = '".$clientType."', ClientVersion = '" .$clientVer."' WHERE DeviceMac = '" .$mac."';";
	 
	$affected = pg_query($db, $sql);
    

    if (!isset($affected)) {
        // error message
    }
}

/*
	Update the device status and timestamp in the database.
	Returns true if the device exists, of false if no printer is registered in the database
	the specified mac address.
*/
function setDeviceStatus($db, $mac, $status) {
	$tstamp = time();    // Store time simply as number of seconds since 1970-01-01 00:00:00+0000 (UTC)
	$sql = "UPDATE Devices SET Status = '".$status."', LastPoll = '".$tstamp."' WHERE DeviceMac = '".$mac."';";
    $affected = pg_query($db, $sql);

    if (empty($affected)) {
        return false;
    }

    return true;
}

function handleCloudPRNTPoll($db) {
    // get the request body, which should be in json format, and parse it
    $parsed = json_decode(file_get_contents("php://input"), true);

    $pollResponse = array();
    $pollResponse['jobReady'] = false;    // set jobReady to false by default, this is enough to provide the minimum cloudprnt response
    //$pollResponse['deleteMethod'] = "GET";    // set jobReady to false by default, this is enough to provide the minimum cloudprnt response

     	$mac = $parsed['printerMAC'];
	$sql ="SELECT idKey FROM Devices WHERE DeviceMac = '".$mac."';";
	$results = pg_query($db, $sql);
	$email ="";
	if (isset($results)) {
        $row = pg_fetch_row($results);     // fetch next row
        $email = $row[0];
       
    } else {
        // error message
    }
    $api_url = 'https://test01.myfoobarapp.com/fbar/v1/printer/'.$email;
	// use key 'http' even if you send the request to https://...
	$options = array(
	'http' => array(
    'method'  => 'POST',
    'content' => json_encode( $parsed ),
    'header'=>  "Content-Type: application/json\r\n" .
                "Accept: application/json\r\n"
		)
	);

	$context  = stream_context_create($options);
	$result = file_get_contents($api_url, false, $context);
	if ($result === FALSE) { /* Handle error */ }
	$response= json_decode($result,true);
	if($response['jobReady'] == true){	
            $printid = $response['jobToken'];
            $sql ="UPDATE Devices SET printing = 1 , printid = '".$printid."' WHERE DeviceMac= '".$mac."'";	
            $res = pg_query($db, $sql);
	}

    $deviceRegistered = setDeviceStatus($db, $parsed['printerMAC'], urldecode($parsed['statusCode']));

    if (!$deviceRegistered) {
        // the request came from a printer that is not currently registered in the database.
        // just do nothing, allow jobReady to return false so that the cloudPrnt device doesn't take any action
        // Note: this can be a good time to print a 'welcome' job if required
    } elseif (isset($parsed['clientAction'])) {
        // client action responses received, meaning that the cloudPRNT device has responded to a 
        // request from the server. This server will request print/paper size and the client type/version when needed
        $width = 0;
        $ctype = "";
        $cver = "";

        $client_actions = $parsed['clientAction'];

        for ($i = 0; $i < count($client_actions); $i++) {
            if ($client_actions[$i]['request'] === "PageInfo") {
                $width = intval($client_actions[$i]['result']['printWidth']) * intval($client_actions[$i]['result']['horizontalResolution']);
            } elseif ($client_actions[$i]['request'] === "ClientType") {
                $ctype = strval($client_actions[$i]['result']);
            } elseif ($client_actions[$i]['request'] === "ClientVersion") {
                $cver = strval($client_actions[$i]['result']);
            }
        }

        setDeviceInfo($db, $parsed['printerMAC'], $width, $ctype, $cver);
    } else {
        // obtain printer device info, to see if this has been stored in the database
        $printWidth = getDeviceOutputWidth($db, $parsed['printerMAC']);

        if (intval($printWidth) === 0) {
            // if the device width is not stored in the database, then use a client action to request it, and other device infor at the same time
            $pollResponse['clientAction'] = array();
            $pollResponse['clientAction'][0] = array("request" => "PageInfo", "options" => "");
            $pollResponse['clientAction'][1] = array("request" => "ClientType", "options" => "");
            $pollResponse['clientAction'][2] = array("request" => "ClientVersion", "options" => "");
        } else {
            // No client action is needed, so check the database to see if a ticket has been requested
            list($printing, $queue, $dotwidth) = getDevicePrintingRequired($db, $parsed['printerMAC']);

            if (isset($printing) && !empty($printing) && isset($queue)) {
                // a ticket has been requested, so let the device know that printing is needed
                $pollResponse['jobReady'] = true;

                // this queuing sample will always use Star Markup to define the print job, so get a list of
                // output formats that can be generated from a Star markup job by cputil and return it.
                // the device will select one format from this list, based on it's internal compatibility and capabilities
                $pollResponse['mediaTypes'] = getCPSupportedOutputs("text/vnd.star.markup");
            }
        }
    }

    header("Content-Type: application/json");
    print_r(json_encode($pollResponse));
}

/*
	Clear a print job from the database, for the specified printer, but setting it's 'Position' field to 'null'
*/
function setCompleteJob($db, $mac) {
       	$ssql ="SELECT idKey , printid FROM Devices WHERE DeviceMac = '".$mac."';";
	$result = pg_query($db, $ssql);

          $row = pg_fetch_row($result);     // fetch next row
        $email = $row[0]; 
        $printid = $row[1];		
		$api_url = 'https://test01.myfoobarapp.com/fbar/v1/printer/'.$email.'?token='.$printid.'&code=OK';
		$options = array(
			'http' => array(
			'method'  => 'DELETE',		
			'header'=>  "Content-Type: application/json\r\n" .
						"Accept: application/json\r\n"
				)
		);
		$context  = stream_context_create($options);
		$results = file_get_contents($api_url, false, $context);

	$sql="UPDATE Devices SET Printing = 0 , printid = '' WHERE DeviceMac = '".$mac."';";
	$affected = pg_query($db, $sql);
  
    if (!isset($affected)) {
        // error message
    }
 }

/*
	Handle http DELETE requests which are used by the CloudPRNT device to clear a print job from the server.
	Usually the request is due to the job having printed sucesfully, but it may also be due to an error
    such as the job media type being incompatible, too large or corrupt.
*/
function handleCloudPRNTDelete($db) {
    $clearJobFromDB = true;

    $headercode = substr($_GET['code'], 0, 1);

    if ($headercode != "2") {
        // job has not printed due to an error
        $fullcode = substr($_GET['code'], 0, 3);

        if ($fullcode === "520") {          // download timeout
            $clearJobFromDB = false;        // do not clear the job in this case, since the cause is more likely to be a network issue
        }

        // error message
    }

    if ($clearJobFromDB) {
        setCompleteJob($db, $_GET['mac']);
    }
}

if (!isset($db) || empty($db)) {
    http_response_code(500);
} elseif ($_SERVER['REQUEST_METHOD'] === "GET") {
    if(strpos($_SERVER['QUERY_STRING'], "&delete") !== false) {    // if server set "deleteMethod":"GET" in POST response
        handleCloudPRNTDelete($db);
    } else {    // Request a content of print job
        handleCloudPRNTGetJob($db);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === "POST") {
    handleCloudPRNTPoll($db);
} elseif ($_SERVER['REQUEST_METHOD'] === "DELETE") {
    handleCloudPRNTDelete($db);
} else {
    http_response_code(405);
}

 pg_close($db);
?>
