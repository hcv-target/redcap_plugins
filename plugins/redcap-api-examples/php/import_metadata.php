<?php

include 'config.php';

$data = array(
	'field_name' => 'f1',
	'form_name' => 'instr_1',
	'section_header' => '',
	'field_type' => 'text',
	'field_label' => 'f1',
	'select_choices_or_calculations' => '',
	'field_note' => '',
	'text_validation_type_or_show_slider_number' => '',
	'text_validation_min' => '',
	'text_validation_max' => '',
	'identifier' => '',
	'branching_logic' => '',
	'required_field' => '',
	'custom_alignment' => '',
	'question_number' => '',
	'matrix_group_name' => '',
	'matrix_ranking' => '',
	'field_annotation' => ''
);

$data = json_encode( array( $data ) );

$fields = array(
	'token'   => $GLOBALS['api_token'],
	'content' => 'metadata',
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
