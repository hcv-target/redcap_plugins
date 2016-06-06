<?php

include 'config.php';

$record = array(
	'username'                 => 'test_user_47',
	'expiration'               => '2016-01-01',
	'data_access_group'        => '1',
	'data_export'              => '1',
	'mobile_app'               => '1',
	'mobile_app_download_data' => '1',
	'lock_record_multiform'    => '1',
	'lock_record'              => '1',
	'lock_record_customize'    => '1',
	'record_delete'            => '1',
	'record_rename'            => '1',
	'record_create'            => '1',
	'api_import'               => '1',
	'api_export'               => '1',
	'data_quality_execute'     => '1',
	'data_quality_design'      => '1',
	'file_repository'          => '1',
	'data_logging'             => '1',
	'data_comparison_tool'     => '1',
	'data_import_tool'         => '1',
	'calendar'                 => '1',
	'graphical'                => '1',
	'reports'                  => '1',
	'user_rights'              => '1',
	'design'                   => '1',
);

$data = json_encode( array( $record ) );

$fields = array(
	'token'   => $GLOBALS['api_token'],
	'content' => 'user',
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
