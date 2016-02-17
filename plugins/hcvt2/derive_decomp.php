<?php
/**
 * Created by NC TraCS for HCV-TARGET.
 * User: kbergqui
 * Date: 10-26-2013
 */
/**
 * TESTING
 */
$debug = false;
$subjects = ''; // '' = ALL! Limit for testing if needed to focus on one subject
if ($debug) {
	$timer = array();
	$timer['start'] = microtime(true);
}
/**
 * includes
 * adjust dirname depth as needed
 */
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . '/redcap_connect.php';
require_once $base_path . '/plugins/includes/functions.php';
require_once APP_PATH_DOCROOT . '/Config/init_project.php';
require_once APP_PATH_DOCROOT . '/ProjectGeneral/header.php';
/**
 * restricted use
 */
$allowed_pids = array('26');
REDCap::allowProjects($allowed_pids);
/**
 * INIT VARS
 */
Kint::enabled($debug);
global $Proj;
$first_event = $Proj->firstEventId;
/**
 * MAIN
 */
if ($debug) {
	$timer['main_start'] = microtime(true);
}
$decomp_pts = array('Acute hepatic failure', 'Ascites', 'Oesophageal varices haemorrhage', 'Encephalopathy', 'Hepatic encephalopathy', 'Hepatic failure', 'Hepatic cirrhosis', 'Peritonitis bacterial', 'Subacute hepatic failure');
$decomp_conmeds = array('rifaximin', 'xifaxan', 'lactulose');
$fields = array('ae_aedecod', 'cm_cmdecod', 'cm_suppcm_cmprtrt', 'cm_suppcm_indcod', 'cirr_suppfa_decomp', 'cirr_suppfa_cirrstat');
$data = REDCap::getData('array', $subjects, $fields);
foreach ($data AS $subject_id => $subject) {
	/**
	 * SUBJECT-LEVEL vars
	 */
	$_decomp = $subject[$first_event]['cirr_suppfa_decomp'];
	$decompensated = $_decomp == 'Y' ? true : false;
	/**
	 * MAIN EVENT LOOP
	 */
	foreach ($subject AS $event_id => $event) {
		if (!$decompensated && (in_array($event['ae_aedecod'], $decomp_pts) || ($event['cm_suppcm_cmprtrt'] == 'N' && in_array($event['cm_cmdecod'], $decomp_conmeds)))) {
			$decompensated = true;
		}
	}
	$decomp = $decompensated ? 'Y' : 'N';
	update_field_compare($subject_id, $project_id, $first_event, $decomp, $_decomp, 'cirr_suppfa_decomp', $debug);
}
if ($debug) {
	$timer['main_end'] = microtime(true);
	$init_time = benchmark_timing($timer);
	echo $init_time;
}