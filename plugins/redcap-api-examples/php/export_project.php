<?php

//include 'config.php';
$base_path = dirname(dirname(dirname(dirname(__FILE__))));
require_once $base_path . '/plugins/includes/functions.php';
Kint::enabled(true);


$data = array(
	'token' => 'ED0C3EC305774C4A32623CB071B2E780',
	'content' => 'project',
	'format' => 'csv',
	'returnFormat' => 'json'
);

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, 'https://www.hcvtargetrc.org/api/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Set to TRUE for production use
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_VERBOSE, 0);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_AUTOREFERER, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data, '', '&'));

if ($output = curl_exec($ch)) {
	print $output;
} else {
	d($output);
}

curl_close($ch);
