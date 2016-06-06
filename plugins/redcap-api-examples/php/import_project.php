<?php

include 'config.php';

$record = array(
	'project_title' => 'New Project via API',
	'purpose'       => 0,
	'purpose_other' => '',
	'project_note'  => 'Some notes about the project',
);

$data = json_encode($record);

/* xml
$data = <<<EOF
<?xml version="1.0" encoding="UTF-8" ?>
<items>
<project>
<project_title><![CDATA[New Project via API]]></project_title>
<purpose>0</purpose>
<purpose_other></purpose_other>
<project_note><![CDATA[Some notes about the project]]></project_note>
</project>
</items>
EOF;
*/

/* csv
$data = <<<EOF
project_title,purpose,purpose_other,project_notes
"Project ABC","",0,"Some notes about the project"
EOF;
*/

$fields = array(
	'token'   => $GLOBALS['super_api_token'],
	'content' => 'project',
	'data'    => $data,
	'format'  => 'json'
	//'format'  => 'xml'
	//'format'  => 'csv'
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
