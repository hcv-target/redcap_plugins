<?php

include 'config.php';

$data = array(
	array(
		'arm_num' => '1', 'unique_event_name' => 'event_1_arm_1', 'form' => 'instr_1',
	),
	array(
		'arm_num' => '1', 'unique_event_name' => 'event_1_arm_1', 'form' => 'instr_2',
	),
	array(
		'arm_num' => '1', 'unique_event_name' => 'event_2_arm_1', 'form' => 'instr_1',
	)
);

$data = json_encode($data);

$fields = array(
	'token'   => $GLOBALS['api_token'],
	'content' => 'formEventMapping',
	'format'  => 'json',
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
