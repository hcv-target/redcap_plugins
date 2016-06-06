<?php

include 'config.php';

$record = array(
	'record_id' => substr(sha1(microtime()), 0, 16),
	'first_name' => 'First',
	'last_name' => 'Last',
	'address' => '123 Cherry Lane\nNashville, TN 37015',
	'telephone' => '(615) 255-4000',
	'email' => 'first.last@gmail.com',
	'dob' => '1972-08-10',
	'age' => '43',
	'ethnicity' => '1',
	'race' => '4',
	'sex' => '1',
	'height' => '180',
	'weight' => '105',
	'bmi' => '31.4',
	'comments' => 'comments go here',
	'redcap_event_name' => 'event_1_arm_1',
	'basic_demography_form_complete' => '2',
);

$data = json_encode( array( $record ) );

$fields = array(
	'token'   => $GLOBALS['api_token'],
	'content' => 'record',
	'format'  => 'json',
	'type'    => 'flat',
	'data'    => $data,
);

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $GLOBALS['api_url']);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields, '', '&'));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // Set to TRUE for production use
curl_setopt($ch, CURLOPT_VERBOSE, 0);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_AUTOREFERER, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);

$output = curl_exec($ch);
print $output;
curl_close($ch);
