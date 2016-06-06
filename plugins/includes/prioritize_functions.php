<?php
/**
 * Created by HCV-TARGET.
 * User: kenbergquist
 * Date: 11/17/15
 * Time: 12:24 PM
 */
/**
 * @param $record
 * @param $event_id
 * @return bool
 * Determine whether all surveys for this time point are complete.
 */
function is_t_complete($record, $event_id)
{
	if (isset($record) && isset($event_id)) {
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
			$data = REDCap::getData('array', $record, $fields, $event_id);
			foreach ($data[$record][$event_id] AS $key => $value) {
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
 * @param $record
 * @param $debug
 * determine completeness of all survey-containing events
 */
function set_survey_completion($record, $debug)
{
	if (isset($record)) {
		global $Proj, $project_id;
		$today = date("Y-m-d");
		$fields = array();
		$arms = get_arms(array_keys($Proj->eventsForms));
		$baseline_event_id = $Proj->firstEventId;
		$trt = Treatment::getTrtInfo($record);
		/**
		 * use the selected duration ($trt['arm']) to set timings
		 */
		$tx_first_event = array_search_recursive($trt['arm'], $arms) !== false ? array_search_recursive($trt['arm'], $arms) : null;
		$survey_event_ids = isset($tx_first_event) ? $Proj->getEventsByArmNum($arms[$tx_first_event]['arm_num']) : null;
		foreach ($survey_event_ids AS $survey_event_id) {
			$data_event_name = $Proj->getUniqueEventNames($survey_event_id);
			$prefix = substr($data_event_name, 0, strpos($data_event_name, '_'));
			/**
			 * derive inter-event timing variables
			 */
			$start_date_value = get_single_field($record, $project_id, $baseline_event_id, $prefix . '_startdate', null);
			$deadline_value = get_single_field($record, $project_id, $baseline_event_id, $prefix . '_deadline', null);
			/**
			 * for baseline surveys, reference date is randomization date. For all other survey events,
			 * reference date is dm_rfxstdtc, recorded treatment start date NOT dm_rfstdtc, the
			 * treatment start derived from the treatment med data
			 */
			$t_base_date = in_array($prefix, array('baseline', 'eot1year', 'eot3year')) ? $trt['rand_date'] : $trt['rfxstdtc'];
			if (isset($t_base_date) && $t_base_date != '') {
				$t_start_date = add_date($t_base_date, ($arms[$data_event_name]['day_offset'] - $arms[$data_event_name]['offset_min']));
				update_field_compare($record, $project_id, $baseline_event_id, $t_start_date, $start_date_value, $prefix . '_startdate', $debug);
				$t_deadline_date = add_date($t_base_date, ($arms[$data_event_name]['day_offset'] + $arms[$data_event_name]['offset_max']) - 1);
				update_field_compare($record, $project_id, $baseline_event_id, $t_deadline_date, $deadline_value, $prefix . '_deadline', $debug);
			}
		}
		foreach ($survey_event_ids AS $survey_event_id) {
			$survey_event_name = $Proj->getUniqueEventNames($survey_event_id);
			$survey_prefix = substr($survey_event_name, 0, strpos($survey_event_name, '_'));
			$fields[] = $survey_prefix . '_completed';
			$fields[] = $survey_prefix . '_date';
			$fields[] = $survey_prefix . '_startdate';
			$fields[] = $survey_prefix . '_deadline';
		}
		$data = REDCap::getData('array', $record, $fields, $baseline_event_id);
		/**
		 * switch to the scheduled arm to determine completion
		 * this is done to avoid subjects switching arms and orphaning surveys completed
		 * prior to duration change. This should (almost) never happen, but we still need to handle if it does
		 */
		$tx_first_event = array_search_recursive($trt['timing_arm'], $arms) !== false ? array_search_recursive($trt['timing_arm'], $arms) : null;
		$survey_event_ids = isset($tx_first_event) ? $Proj->getEventsByArmNum($arms[$tx_first_event]['arm_num']) : null;
		foreach ($survey_event_ids AS $survey_event_id) {
			$data_event_name = $Proj->getUniqueEventNames($survey_event_id);
			$prefix = substr($data_event_name, 0, strpos($data_event_name, '_'));
			$is_t_complete = is_t_complete($record, $survey_event_id);
			$t_complete = $is_t_complete ? '1' : '0';
			$complete_value = get_single_field($record, $project_id, $baseline_event_id, $prefix . '_completed', null);
			$deadline_value = get_single_field($record, $project_id, $baseline_event_id, $prefix . '_deadline', null);
			$t_missed = $complete_value == '0' && $today > $deadline_value && isset($deadline_value) && $deadline_value != '' ? '1' : '0';
			foreach ($data[$record] AS $data_event_id => $data_event) {
				foreach ($data_event AS $key => $value) {
					/**
					 * derive completion variables
					 */
					switch ($key) {
						case $prefix . '_completed':
							update_field_compare($record, $project_id, $data_event_id, $t_complete, $value, $key, $debug);
							break;
						case $prefix . '_date':
							if ($value == '' && $is_t_complete) {
								update_field_compare($record, $project_id, $data_event_id, $today, $value, $key, $debug);
							}
							break;
						case $prefix . '_missed':
							update_field_compare($record, $project_id, $data_event_id, $t_missed, $value, $key, $debug);
							break;
						default:
							break;
					}
				}
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
			$arms_row['descrip'] = str_replace(array("\"", "'", "+"), array("", "", ""), $arms_row['descrip']);
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

/**
 * @param $record
 * @param $event_id
 * @param $group_id
 * @param $debug
 */
function schedule_surveys($record, $event_id, $group_id, $debug)
{
	global $Proj, $project_id, $user_rights, $table_pk;
	/**
	 * if the user is in a DAG
	 */
	if ($user_rights['group_id'] != "") {
		/**
		 * does this record exist?
		 */
		$q = db_query("SELECT 1 from redcap_data WHERE project_id = $project_id AND record = '$record' LIMIT 1");
		if (db_num_rows($q) > 0) {
			/**
			 * is the record in this users DAG?
			 */
			$q = db_query("SELECT 1 from redcap_data WHERE project_id = $project_id AND record = '$record' AND field_name = '__GROUPID__' AND value = '{$user_rights['group_id']}' LIMIT 1");
			if (db_num_rows($q) < 1) {
				/**
				 * record is not in Users DAG!
				 */
				REDCap::logEvent('Scheduled record is not in users DAG', '', '', $record, $event_id, $project_id);
				exit;
			}
		}
	}
	/**
	 * check to see if the subject has an existing schedule on an existing arm
	 */
	$sub = "SELECT DISTINCT e.arm_id from redcap_events_calendar c, redcap_events_metadata e WHERE c.project_id = $project_id AND c.record = '$record' AND c.event_id = e.event_id";
	$sched_arm_result = db_query("SELECT arm_num FROM redcap_events_arms WHERE project_id = $project_id AND arm_id IN (" . pre_query($sub) . ")");
	if ($sched_arm_result) {
		$trt = Treatment::getTrtInfo($record);
		if ($debug) {
			error_log(print_r($trt, true));
		}
		$tx_start_date = $trt['rfxstdtc'];
		$rand_date = $trt['rand_date'];
		$dates = array();
		$arm_num = db_result($sched_arm_result, 0, 'arm_num');
		if (isset($arm_num) && $arm_num != '') { // subject has an existing schedule. keep existing event_id > arm structure
			if ($arm_num != '1') { // make sure we don't put anything in the first arm
				$q = db_query("SELECT * from redcap_events_metadata m, redcap_events_arms a WHERE a.project_id = $project_id AND a.arm_id = m.arm_id AND a.arm_num = $arm_num order by m.day_offset, m.descrip");
				if ($q) {
					while ($row = db_fetch_assoc($q)) { // if we have no $arm_num, this will be empty
						/**
						 * get the event date ($rand_date for baseline and $tx_start_date + day_offset)
						 */
						$row['day_offset'] = $arm_num != $trt['timing_arm_num'] ? $trt['timing_offsets'][$row['descrip']] : $row['day_offset'];
						if (in_array($row['descrip'], array('Baseline', 'EOT+1Year', 'EOT+3Year'))) {
							$this_event_date = isset($rand_date) && $rand_date != '' ? add_date($rand_date, $row['day_offset']) : null;
						} else {
							$this_event_date = isset($tx_start_date) && $tx_start_date != '' ? add_date($tx_start_date, $row['day_offset']) : null;
						}
						$dates[$row['event_id']] = $this_event_date;
					}
					db_free_result($q);
				}
			} else {
				REDCap::logEvent('Scheduling attempted in invalid arm', '', '', $record, $event_id, $project_id);
			}
		} else { // subject's schedule is new. put dates into event_ids for this arm
			$arm_result = db_query("SELECT arm_num FROM redcap_events_arms WHERE project_id = '$project_id' AND arm_name = '{$trt['arm']}'");
			if ($arm_result) {
				$arm_num = db_result($arm_result, 0, 'arm_num');
				if ($arm_num != '1') {
					$q = db_query("SELECT * from redcap_events_metadata m, redcap_events_arms a WHERE a.project_id = $project_id AND a.arm_id = m.arm_id AND a.arm_num = $arm_num order by m.day_offset, m.descrip");
					if ($q) {
						while ($row = db_fetch_assoc($q)) { // if we have no $arm_num, this will be empty
							/**
							 * get the event date ($rand_date for baseline and $tx_start_date + day_offset)
							 */
							if (in_array($row['descrip'], array('Baseline', 'EOT+1Year', 'EOT+3Year'))) {
								$this_event_date = isset($rand_date) && $rand_date != '' ? add_date($rand_date, $row['day_offset']) : null;
							} else {
								$this_event_date = isset($tx_start_date) && $tx_start_date != '' ? add_date($tx_start_date, $row['day_offset']) : null;
							}
							$dates[$row['event_id']] = $this_event_date;
						}
						db_free_result($q);
					}
				} else {
					REDCap::logEvent('Scheduling attempted in invalid arm', '', '', $record, $event_id, $project_id);
				}
				db_free_result($arm_result);
			}
		}
		if ($debug) {
			error_log(print_r($dates, true));
		}
		if (!empty($dates)) {
			/**
			 * do we have an existing schedule?
			 */
			$sql = "SELECT c.event_date, c.baseline_date, e.* FROM redcap_events_calendar c, redcap_events_metadata e WHERE c.project_id = $project_id AND c.record = '$record' AND c.event_id = e.event_id AND e.arm_id IN (" . pre_query($sub) . ")";
			$sched_result = db_query($sql);
			if ($sched_result) {
				$sql_all = array();
				$sql_errors = array();
				if (db_num_rows($sched_result) > 0) {
					while ($sched_row = db_fetch_assoc($sched_result)) {
						$base_date = in_array($sched_row['descrip'], array('Baseline', 'EOT+1Year', 'EOT+3Year')) ? $trt['rand_date'] : $trt['rfxstdtc'];
						/**
						 * if the scheduled date is in the $dates array, we don't care about it, so ignore it and remove from $dates
						 * if we have an existing schedule and the dates have changed, update the schedule and remove from $dates
						 * if the base date has changed, update it and the schedule
						 * whatever is left will be new dates, insert into schedule
						 */
						if ($dates[$sched_row['event_id']] == $sched_row['event_date']) {
							unset($dates[$sched_row['event_id']]);
						}
						if (isset($dates[$sched_row['event_id']]) && $dates[$sched_row['event_id']] != '' && $sched_row['event_date'] != $dates[$sched_row['event_id']]) { // the date has changed. update the date.
							$sql = "UPDATE redcap_events_calendar SET event_date = '{$dates[$sched_row['event_id']]}' WHERE record = '$record' AND project_id = '$project_id' AND group_id = '$group_id' AND event_id = '{$sched_row['event_id']}' AND event_date = '{$sched_row['event_date']}'";
							if (!$debug) {
								if (db_query($sql)) {
									$sql_all[] = $sql;
									log_event($sql, "redcap_events_calendar", "MANAGE", $record, $sched_row['event_id'], "Update calendar event");
								} else {
									$sql_errors[] = $sql;
								}
							} else {
								error_log($sql);
							}
							unset($dates[$sched_row['event_id']]);
						}
						if ($base_date != $sched_row['baseline_date']) { // the base_date has changed. this will only occur if the treatment start date or randomization date are changed in the study.
							$sql = "UPDATE redcap_events_calendar SET baseline_date = '" . prep($base_date) . "' WHERE record = '$record' AND project_id = '$project_id' AND group_id = '$group_id' AND event_id = '{$sched_row['event_id']}' AND baseline_date = '{$sched_row['baseline_date']}'";
							if (!$debug) {
								if (db_query($sql)) {
									$sql_all[] = $sql;
									log_event($sql, "redcap_events_calendar", "MANAGE", $record, $sched_row['event_id'], "Update calendar event");
								} else {
									$sql_errors[] = $sql;
								}
							} else {
								error_log($sql);
							}
							unset($dates[$sched_row['event_id']]);
						}
					}
					foreach ($dates AS $date_event_id => $date) { //Loop through dates and add them to the schedule
						$base_date = in_array($Proj->eventInfo[$date_event_id]['name'], array('Baseline', 'EOT+1Year', 'EOT+3Year')) ? $trt['rand_date'] : $trt['rfxstdtc'];
						if (isset($date) && $date != "") { //Add to table
							$sql = "INSERT INTO redcap_events_calendar (record, project_id, group_id, event_id, event_date, event_time, event_status, baseline_date) VALUES ('$record', $project_id, " . checkNull($group_id) . ", '" . prep($date_event_id) . "', '" . prep($date) . "', '" . null . "', 0, '$base_date')";
							if (!$debug) {
								if (db_query($sql)) {
									$sql_all[] = $sql;
								} else {
									$sql_errors[] = $sql;
								}
							} else {
								error_log($sql);
							}
						} else {
							REDCap::logEvent('Schedule start date is not a valid date', '', '', $record, $event_id, $project_id);
						}
					}
					log_event(implode(";\n", $sql_all), "redcap_events_calendar", "MANAGE", $_GET['idnumber'], "$table_pk = '$record'", "Perform scheduling");
				} else {
					foreach ($dates AS $date_event_id => $date) { //Loop through dates and add them to the schedule
						$base_date = in_array($Proj->eventInfo[$date_event_id]['name'], array('Baseline', 'EOT+1Year', 'EOT+3Year')) ? $trt['rand_date'] : $trt['rfxstdtc'];
						if (isset($date) && $date != "") { //Add to table
							$sql = "INSERT INTO redcap_events_calendar (record, project_id, group_id, event_id, event_date, event_time, event_status, baseline_date) VALUES ('$record', $project_id, " . checkNull($group_id) . ", '" . prep($date_event_id) . "', '" . prep($date) . "', '" . null . "', 0, '$base_date')";
							if (!$debug) {
								if (db_query($sql)) {
									$sql_all[] = $sql;
								} else {
									$sql_errors[] = $sql;
								}
							} else {
								error_log($sql);
							}
						} else {
							REDCap::logEvent('Schedule start date is not a valid date', '', '', $record, $event_id, $project_id);
						}
					}
					log_event(implode(";\n", $sql_all), "redcap_events_calendar", "MANAGE", $_GET['idnumber'], "$table_pk = '$record'", "Perform scheduling");
				}
			}
			db_free_result($sched_result);
		}
		db_free_result($sched_arm_result);
	}
}