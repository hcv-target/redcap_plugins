<?php

include 'config.php';

$data = array(
	array(
		'event_name'        => 'Event XX',
		'arm_num'           => '1',
		'day_offset'        => '0',
		'offset_min'        => '0',
		'offset_max'        => '0',
		'unique_event_name' => 'event_xx_arm_1'
	),
	array(
		'event_name'        => 'Event YY',
		'arm_num'           => '2',
		'day_offset'        => '0',
		'offset_min'        => '0',
		'offset_max'        => '0',
		'unique_event_name' => 'event_yy_arm_2'
	),
);

$data = json_encode($data);

$fields = array(
	'token'    => $GLOBALS['api_token'],
	'content'  => 'event',
	'action'   => 'import',
	'format'   => 'json',
	'override' => 1,
	'data'     => $data,
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
