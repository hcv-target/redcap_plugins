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
/**
 * includes
 * adjust dirname depth as needed
 */
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once APP_PATH_DOCROOT . '/Config/init_project.php';
require_once APP_PATH_DOCROOT . '/ProjectGeneral/header.php';
/**
 * restricted use
 */
$allowed_pids = array('26');
REDCap::allowProjects($allowed_pids);
/**
 * vars
 */
/**
 * MAIN
 */
$fields = array('cm_cmdecod', 'cm_suppcm_cmimmuno');
$data = REDCap::getData('array', '', $fields);
foreach ($data AS $subject_id => $subject) {
	foreach ($subject AS $event_id => $event) {
		$immun_flag = 'N';
		$immun_meds = array();
		$immun_meds_result = db_query("SELECT * FROM _target_immune_meds WHERE cm_cmcat != 'steroid' AND cm_cmtrt = '{$event['cm_cmdecod']}'");
		if ($immun_meds_result) {
			while ($immun_meds_row = db_fetch_assoc($immun_meds_result)) {
				$immun_meds[] = $immun_meds_row['cm_cmtrt'];
			}
			db_free_result($immun_meds_result);
		}
		if (count($immun_meds) != 0) {
			$immun_flag = 'Y';
			if ($debug) {
				show_var($immun_meds);
			}
		}
		update_field_compare($subject_id, $project_id, $event_id, $immun_flag, $event['cm_suppcm_cmimmuno'], 'cm_suppcm_cmimmuno', $debug);
	}
}