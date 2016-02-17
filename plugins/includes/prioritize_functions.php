<?php
/**
 * Created by HCV-TARGET.
 * User: kenbergquist
 * Date: 11/17/15
 * Time: 12:24 PM
 */
/**
 * @param $subject_id
 * @param $event_id
 * @return bool
 * Determine whether all surveys for this time point are complete.
 */
function is_t_complete($subject_id, $event_id)
{
	if (isset($subject_id) && isset($event_id)) {
		global $Proj;
		$fields = array();
		$complete = true;
		$surveys_query = "SELECT form_name FROM redcap_surveys WHERE project_id = '$Proj->project_id'";
		$surveys_result = db_query($surveys_query);
		if ($surveys_result) {
			while ($survey_row = db_fetch_array($surveys_result)) {
				if ($Proj->validateFormEvent($survey_row['form_name'], $event_id)) {
					$fields[] = $survey_row['form_name'] . '_complete';
				}
			}
			$data = REDCap::getData('array', $subject_id, $fields, $event_id);
			foreach ($data[$subject_id][$event_id] AS $key => $value) {
				if ($value != '2') {
					$complete = false;
				}
			}
			db_free_result($surveys_result);
			return $complete;
		}
	} else {
		return false;
	}
}

/**
 * @param $subject_id
 * @param $debug
 * determine completeness of all survey-containing events
 */
function events_completion($subject_id, $debug)
{
	if (isset($subject_id)) {
		global $Proj, $project_id;
		$today = date("Y-m-d");
		$fields = array();
		$arms = get_arms(array_keys($Proj->eventsForms));
		$baseline_event_id = $Proj->firstEventId;
		$enrollment_event_id = getNextEventId($baseline_event_id);
		$tx_duration = get_single_field($subject_id, $project_id, $enrollment_event_id, 'dm_suppdm_actarmdur', null);
		$tx_first_event = array_search_recursive($tx_duration . ' Weeks', $arms) !== false ? array_search_recursive($tx_duration . ' Weeks', $arms) : null;
		$survey_event_ids = isset($tx_first_event) ? array_merge($baseline_event_id, $Proj->getEventsByArmNum($arms[$tx_first_event]['arm_num'])) : array($baseline_event_id);
		foreach ($survey_event_ids AS $survey_event_id) {
			$survey_event_name = $Proj->getUniqueEventNames($survey_event_id);
			$survey_prefix = substr($survey_event_name, 0, strpos($survey_event_name, '_'));
			$fields[] = $survey_prefix . '_completed';
			$fields[] = $survey_prefix . '_date';
			$fields[] = $survey_prefix . '_startdate';
			$fields[] = $survey_prefix . '_deadline';
		}
		$data = REDCap::getData('array', $subject_id, $fields, $baseline_event_id);
		foreach ($survey_event_ids AS $survey_event_id) {
			$data_event_name = $Proj->getUniqueEventNames($survey_event_id);
			$prefix = substr($data_event_name, 0, strpos($data_event_name, '_'));
			$is_t_complete = is_t_complete($subject_id, $survey_event_id);
			$t_complete = $is_t_complete ? '1' : '0';
			foreach ($data[$subject_id] AS $data_event_id => $data_event) {
				foreach ($data_event AS $key => $value) {
					/**
					 * derive intra-event timing variables
					 */
					switch ($key) {
						case $prefix . '_completed':
							update_field_compare($subject_id, $project_id, $data_event_id, $t_complete, $value, $key, $debug);
							break;
						case $prefix . '_date':
							if ($value == '' && $is_t_complete) {
								update_field_compare($subject_id, $project_id, $data_event_id, $today, $value, $key, $debug);
							}
							break;
						default:
							break;
					}
				}
			}
			/**
			 * derive inter-event timing variables
			 */
			$complete_value = get_single_field($subject_id, $project_id, $baseline_event_id, $prefix . '_completed', null);
			$start_date_value = get_single_field($subject_id, $project_id, $baseline_event_id, $prefix . '_startdate', null);
			$deadline_value = get_single_field($subject_id, $project_id, $baseline_event_id, $prefix . '_deadline', null);
			$missed_value = get_single_field($subject_id, $project_id, $baseline_event_id, $prefix . '_missed', null);
			$t_missed = $complete_value == '0' && $today > $deadline_value && isset($deadline_value) && $deadline_value != '' ? '1' : '0';
			switch ($prefix) {
				case 't1':
					$t_base_date = get_single_field($subject_id, $project_id, $baseline_event_id, $prefix . '_date', null);
					$t_start_date = $t_base_date;
					$t_deadline_date = add_date($t_base_date, 90);
					/*if (isset($t_base_date) && $t_base_date != '') {
						$start_date_value = get_single_field($subject_id, $project_id, $baseline_event_id, $prefix . '_startdate', null);
						$deadline_value = get_single_field($subject_id, $project_id, $baseline_event_id, $prefix . '_deadline', null);
						update_field_compare($subject_id, $Proj->project_id, $baseline_event_id, $t_start_date, $start_date_value, $prefix . '_startdate', $debug);
						update_field_compare($subject_id, $Proj->project_id, $baseline_event_id, $t_deadline_date, $deadline_value, $prefix . '_deadline', $debug);
					}*/
					break;
				default:
					$t_base_date = get_single_field($subject_id, $project_id, $enrollment_event_id, 'dm_rfstdtc', null);
					$t_start_date = add_date($t_base_date, ($arms[$data_event_name]['day_offset'] - $arms[$data_event_name]['offset_min']));
					$t_deadline_date = add_date($t_base_date, ($arms[$data_event_name]['day_offset'] + $arms[$data_event_name]['offset_max']));
					/**
					 * iterate the subject's treatment arm in $arms: foreach this arm in $arms, event->send_date = rfstdtc + (day_offset - offset_min), deadline = rfstdtc + (day_offset + offset_max)
					 */
					/*if (isset($t_base_date) && $t_base_date != '') {
						$start_date_value = get_single_field($subject_id, $project_id, $baseline_event_id, $prefix . '_startdate', null);
						$deadline_value = get_single_field($subject_id, $project_id, $baseline_event_id, $prefix . '_deadline', null);
						update_field_compare($subject_id, $project_id, $baseline_event_id, add_date($t_base_date, ($arms[$data_event_name]['day_offset'] - $arms[$data_event_name]['offset_min'])), $start_date_value, $prefix . '_startdate', $debug);
						update_field_compare($subject_id, $project_id, $baseline_event_id, add_date($t_base_date, ($arms[$data_event_name]['day_offset'] + $arms[$data_event_name]['offset_max'])), $deadline_value, $prefix . '_deadline', $debug);
					}*/
					break;
			}
			update_field_compare($subject_id, $project_id, $baseline_event_id, $t_complete, $complete_value, $prefix . '_completed', $debug);
			update_field_compare($subject_id, $project_id, $baseline_event_id, $t_missed, $missed_value, $prefix . '_missed', $debug);
			if (isset($t_base_date) && $t_base_date != '') {
				update_field_compare($subject_id, $project_id, $baseline_event_id, $t_start_date, $start_date_value, $prefix . '_startdate', $debug);
				update_field_compare($subject_id, $project_id, $baseline_event_id, $t_deadline_date, $deadline_value, $prefix . '_deadline', $debug);
			}
		}
	}
}

/**
 * @param $tx_arm_event_ids
 * @return array
 */
function get_arms($tx_arm_event_ids)
{
	global $Proj;
	$tx_arm_event_id_list = "'" . implode("','", $tx_arm_event_ids) . "'";
	$arms = array();
	$arms_result = db_query("SELECT events_meta.*, arm.arm_num, arm.arm_name FROM
        (SELECT * FROM redcap_events_metadata) events_meta
        LEFT OUTER JOIN
        (SELECT * FROM redcap_events_arms) arm
        ON arm.arm_id = events_meta.arm_id
        WHERE arm.project_id = '$Proj->project_id'
        AND events_meta.event_id IN ($tx_arm_event_id_list)");
	if ($arms_result) {
		while ($arms_row = db_fetch_array($arms_result)) {
			$arms[strtolower($arms_row['descrip']) . '_arm_' . $arms_row['arm_num']]['event_id'] = $arms_row['event_id'];
			$arms[strtolower($arms_row['descrip']) . '_arm_' . $arms_row['arm_num']]['arm_num'] = $arms_row['arm_num'];
			$arms[strtolower($arms_row['descrip']) . '_arm_' . $arms_row['arm_num']]['arm_name'] = $arms_row['arm_name'];
			$arms[strtolower($arms_row['descrip']) . '_arm_' . $arms_row['arm_num']]['day_offset'] = $arms_row['day_offset'];
			$arms[strtolower($arms_row['descrip']) . '_arm_' . $arms_row['arm_num']]['offset_min'] = $arms_row['offset_min'];
			$arms[strtolower($arms_row['descrip']) . '_arm_' . $arms_row['arm_num']]['offset_max'] = $arms_row['offset_max'];
		}
	}
	return $arms;
}