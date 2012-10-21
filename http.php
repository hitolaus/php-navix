<?php
function http_request($url, $method = "GET", $params = null, $optional_headers = null) {

	if ($method == "POST") {
		array_push($optional_headers, "Content-Length: " . strlen($params));
	}

	$opts = array('http' =>
    	array(
        	'method'  => 'POST',
        	'header'  => $optional_headers,
        	'timeout' => 5,
        	'content' => $params
    	));

	$context  = stream_context_create($opts);
	
	$data = file_get_contents($url, false, $context);
	
	return array($data, $http_response_header);
}

function get_cookie_value($headers, $name) {

	$header_values = get_header_values($headers, "Set-Cookie");
	
	for ($i = 0; $i < count($header_values); $i++) {
		$header_value = $header_values[$i];
		
		if ($header_value == NULL) {
			return NULL;
		}
	
		list($cookie_name, $cookie_value) = explode("=",$header_value, 2);
	
		if ($cookie_name == $name) {
			$cookie_components = explode(";", $cookie_value);
			return $cookie_components[0];
		}
	}
	return NULL;
}

function get_header_value($headers, $name) {
	$values = get_header_values($headers, $name);
	return $values[0]; 
}

function get_header_values($headers, $name) {
	$match = array();
	
	for ($i = 0; $i < count($headers); $i++) {
		list($header_name, $header_value) = explode(":", $headers[$i], 2);
		
		if ($header_name == $name) {
			array_push($match, trim($header_value));
		}
	}
	
	return $match;
}

/*
$headers = array("Set-Cookie: PHPSESSID=6cag06tjh9rgmo7r4rdvrgp0l4; path=/", "Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0");

echo get_cookie_value($headers, "PHPSESSID");
*/
/*
$headers = array("Set-Cookie: pp=1309127206; expires=Sun, 10-Jul-2011 21:26:46 GMT; path=/");
echo get_cookie_value($headers, "pp");
*/
?>