<html>
	<body>
		<form method="post" action="">
			<label for="cidr">Drag file here -></label>
			<input type="file" name="cidr" id="file">
			<input type="submit">
		</form>
	</body>
</html>

<?php

/*
	============================================

    IP Block Info Generator
    
    - Tested with .txt file
    - Reads each line (IP)
    - Returns a file with IP info formatted like this:
    
     NetRange|CIDR|Name|Handle|Parent|Organization|RegistrationDate|LastUpdateDate

    ============================================
*/

$inputFile;
$ips = $blockInfo = array();

/* Main Controller-like thing */
if (isset($_POST['cidr'])) { 				  // If user submits data
	$inputFile = $_POST['cidr'];
	if(pathinfo($inputFile, PATHINFO_EXTENSION)=='txt'){
		$ips 	   = readInputFile($inputFile);   // Store IP Addresses from input file
		$blockInfo = loopThroughIps($ips);  	  // For each IP, do rest request
		writeNewFile($blockInfo);				  // Write a new file with data returned from requests
	}
	else {
		echo 'Invalid file type, please use .txt';
	}
	
	
}

function readInputFile($inputFile){

	$handle = fopen($inputFile, "r");
	
	if ($handle) {
		$i = 0;
	    while (($line = fgets($handle)) !== false) { // Loop through each line
	        $ips[$i] = $line;
	        $i++;
	    }
	} else {
	    die('Error opening file...');
	} 
	
	fclose($handle);
	return $ips;
}

function loopThroughIps($ips){

	foreach ($ips as $key=>$ip) {
		$blockInfo[$key] = restRequest($ip); // Fill in blockInfo array with data returned from request 
		usleep(1000); // Change this value as needed
	}
	return $blockInfo;
	
}

function restRequest($cidr){
	// FYI: PHP strings in double quotes parse variables within them
	$url  = "http://whois.arin.net/rest/cidr/$cidr";
	
	$response = curlProccess($url); // Make restful request 
	
	if (strpos($response, 'HTTP Status 405')){
		return "HTTP Status 405 for: $cidr";
	}
	elseif(strpos($response, 'HTTP Status 404')){
		return "Resource not found for: $cidr";
	}
	elseif (strpos($response, 'No record found')){
		return "No record found for: $cidr";
	} 
	else {
		
		$response = new SimpleXMLElement($response); // Makes XML an object so data is readable
		
		// '$response->orgRef' is an example of how you reference properties of an object(in this case $response) in PHP
		$orgReference = curlProccess($response->orgRef);   // Run cURL process again to get org data
		$orgReference = new SimpleXMLElement($orgReference);
		
		// Take handle (NET-12-0-0-0-1) and format to NET12
		$parentPost = $response->handle;
		$parentPre  = preg_split('/[-]/', $parentPost); // Create array out of string NET-12-0-0-0-1, splitting by '-'
		$parentPre  = $parentPre[0] . $parentPre[1];    // Concat 1st 2 elements
		
		$output = array();
		
		$output['netRange'] 	= "$response->startAddress - $response->endAddress";
		$output['cidr'] 		= trim($cidr); //strips out line breaks
		$output['name'] 		= $response->name;
		$output['handle'] 		= $response->handle;
		$output['parent']	    = "$parentPre ($parentPost)";
		$output['organization'] = "$orgReference->name ($orgReference->handle)";
		
		// This what we get for registrationDate: 1983-08-23T00:00:00-04:00
		$output['registration_date'] = strstr($response->registrationDate, 'T', true); //get substring up to 'T' for correct output format
		$output['last_update_date']  = strstr($response->updateDate, 'T', true);
		
		//this is for testing - can remove eventually
		//writeToScreen($output);
	
		$output_delimited = implode("|",$output); //Join array with 'glue' string: |
		return $output_delimited;
		
	}
}

function curlProccess( $url ){

	$curl = curl_init($url);							// Initialize cURL session
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); 	// Return transfer as string instead of outputting it directly
	$response = curl_exec($curl);						// Execute cURL session
	curl_close($curl); 									// Close cURL session, free all resources
	return $response;
	
}

// Overwrite blockinfo file
function writeNewFile($blockInfo){

	$blockInfoFile = fopen("blockinfo.txt", "w");
	
	foreach ($blockInfo as $infoLine){
		$txt = "$infoLine\n";
		fwrite($blockInfoFile, $txt);
	}
	
	fclose($blockInfoFile);
	
	// Redirect to new file
	header("Location: blockinfo.txt");
	die();
}

// For testing - can remove eventually
function writeToScreen($output){

	echo("<br/>NetRange: $output[netRange]");
	echo("<br/>CIDR Length: $output[cidr]");
	echo("<br/>Name: $output[name]");
	echo("<br/>Handle: $output[handle]");
	echo("<br/>Parent: $output[parent]");
	echo("<br/>Organization: $output[organization]");
	echo("<br/>Registration Date: $output[registration_date]");
	echo("<br/>Last Updated: $output[last_update_date]");
	echo("<br/><br/>Output: ");
	
}
	
?>