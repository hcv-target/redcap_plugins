<?php

/**
 * Created by HCV-TARGET.
 * User: kenbergquist
 * Date: 11/17/15
 * Time: 12:24 PM
 */
class Prioritize
{
	/**
	 * @param $record
	 * @param $instrument
	 * @param $debug
	 */
	public static function set_dag($record, $instrument, $debug)
	{
		global $project_id;
		/**
		 * SET Data Access Group based upon dm_usubjid prefix
		 */
		$fields = array('dm_usubjid');
		$data = REDCap::getData('array', $record, $fields);
		foreach ($data AS $subject) {
			foreach ($subject AS $event_id => $event) {
				if ($event['dm_usubjid'] != '') {
					/**
					 * find which DAG this subject belongs to
					 */
					$site_prefix = substr($event['dm_usubjid'], 0, 3) . '%';
					$dag_query = "SELECT group_id, group_name FROM redcap_data_access_groups WHERE project_id = '$project_id' AND group_name LIKE '$site_prefix'";
					$dag_result = db_query($dag_query);
					if ($dag_result) {
						$dag = db_fetch_assoc($dag_result);
						if (isset($dag['group_id'])) {
							/**
							 * For each event in project for this subject, determine if this subject_id has been added to its appropriate DAG. If it hasn't, make it so.
							 * First, we need a list of events for which this subject has data
							 */
							$subject_events_query = "SELECT DISTINCT event_id FROM redcap_data WHERE project_id = '$project_id' AND record = '$record' AND field_name = '" . $instrument . "_complete'";
							$subject_events_result = db_query($subject_events_query);
							if ($subject_events_result) {
								while ($subject_events_row = db_fetch_assoc($subject_events_result)) {
									if (isset($subject_events_row['event_id'])) {
										$_GET['event_id'] = $subject_events_row['event_id']; // for logging
										/**
										 * The subject has data in this event_id
										 * does the subject have corresponding DAG assignment?
										 */
										$has_event_data_query = "SELECT DISTINCT event_id FROM redcap_data WHERE project_id = '$project_id' AND record = '$record' AND event_id = '" . $subject_events_row['event_id'] . "' AND field_name = '__GROUPID__'";
										$has_event_data_result = db_query($has_event_data_query);
										if ($has_event_data_result) {
											$has_event_data = db_fetch_assoc($has_event_data_result);
											if (!isset($has_event_data['event_id'])) {
												/**
												 * Subject does not have a matching DAG assignment for this data
												 * construct proper matching __GROUPID__ record and insert
												 */
												$insert_dag_query = "INSERT INTO redcap_data SET record = '$record', event_id = '" . $subject_events_row['event_id'] . "', value = '" . $dag['group_id'] . "', project_id = '$project_id', field_name = '__GROUPID__'";
												if (!$debug) {
													if (db_query($insert_dag_query)) {
														target_log_event($insert_dag_query, 'redcap_data', 'insert', $record, $dag['group_name'], 'Assign record to Data Access Group (' . $dag['group_name'] . ')');
													} else {
														error_log("SQL INSERT FAILED: " . db_error() . "\n");
														echo db_error() . "\n";
													}
												} else {
													show_var($insert_dag_query);
													error_log('(TESTING) NOTICE: ' . $insert_dag_query);
												}
											}
											db_free_result($has_event_data_result);
										}
									}
								}
								db_free_result($subject_events_result);
							}
						}
						db_free_result($dag_result);
					}
				}
			}
		}
	}
	/**
	 * @param $record
	 * @return bool|null
	 */
	public static function getGroupID($record)
	{
		global $Proj, $project_id;
		$first_event_id = $Proj->firstEventId;
		$group_id = null;
		$group_id_result = db_query("SELECT value FROM redcap_data WHERE project_id = '$project_id' AND record = '$record' AND event_id = '$first_event_id' AND field_name = '__GROUPID__' LIMIT 1");
		if ($group_id_result) {
			$group_id = db_result($group_id_result, 0, 'value');
		}
		return $group_id;
	}
	/**
	 * @param $record
	 * @param $event_id
	 * @return bool
	 * Determine whether all surveys for this time point are complete.
	 */
	public static function is_t_complete($record, $event_id)
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
	public static function set_survey_completion($record, $debug)
	{
		if (isset($record)) {
			global $Proj, $project_id;
			$today = date("Y-m-d");
			$fields = array();
			$arms = self::get_arms(array_keys($Proj->eventsForms));
			$baseline_event_id = $Proj->firstEventId;
			$trt = Prioritize::getTrtInfo($record);
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
			if ($debug) {
				error_log(print_r($data, true));
			}
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
				$is_t_complete = self::is_t_complete($record, $survey_event_id);
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
	public static function get_arms($tx_arm_event_ids)
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
	public static function schedule_surveys($record, $event_id, $group_id, $debug)
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
			$trt = Prioritize::getTrtInfo($record);
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

	/**
	 * @param $userid
	 * @param $headers
	 * @param $table_csv
	 * @param array $fields
	 * @param $parent_chkd_flds
	 * @param $export_file_name
	 * @param $debug
	 * @param null $comment
	 * @param array $to
	 */
	public static function do_sendit($userid, $headers, $table_csv, $fields = array(), $parent_chkd_flds, $export_file_name, $comment = null, $to = array(), $debug)
	{
		global $project_id, $user_rights, $app_title, $lang, $redcap_version; // we could use the global $userid, but we need control of it for setting the user as [CRON], so this is passed in args.
		$return_val = false;
		$export_type = 0; // this puts all files generated here in the Data Export category in the File Repository
		$today = date("Y-m-d_Hi"); //get today for filename
		$projTitleShort = substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($app_title, ENT_QUOTES)))), 0, 20); // shortened project title for filename
		$originalFilename = $projTitleShort . "_" . $export_file_name . "_DATA_" . $today . ".csv"; // name the file for storage
		$today = date("Y-m-d-H-i-s"); // get today for comment, subsequent processing as needed
		$docs_comment_WH = $export_type ? "Data export file created by $userid on $today" : fix_case($export_file_name) . " file created by $userid on $today. $comment"; // unused, but I keep it around just in case
		/**
		 * setup vars for value export logging
		 */
		$chkd_fields = implode(',', $fields);
		/**
		 * turn on/off exporting per user rights
		 */
		if (($user_rights['data_export_tool'] || $userid == '[CRON]') && !$debug) {
			$table_csv = addBOMtoUTF8($headers . $table_csv);
			/**
			 * Store the file in the file system and log the activity, handle if error
			 */
			if (!DataExport::storeExportFile($originalFilename, $table_csv, true)) {
				log_event("", "redcap_data", "data_export", "", str_replace("'", "", $chkd_fields) . (($parent_chkd_flds == "") ? "" : ", " . str_replace("'", "", $parent_chkd_flds)), "Data Export Failed");
			} else {
				log_event("", "redcap_data", "data_export", "", str_replace("'", "", $chkd_fields) . (($parent_chkd_flds == "") ? "" : ", " . str_replace("'", "", $parent_chkd_flds)), "Export data for SendIt");
				/**
				 * email file link and download password in two separate emails via REDCap SendIt
				 */
				$file_info_sql = db_query("SELECT docs_id, docs_size, docs_type FROM redcap_docs WHERE project_id = $project_id ORDER BY docs_id DESC LIMIT 1"); // get required info about the file we just created
				if ($file_info_sql) {
					$docs_id = db_result($file_info_sql, 0, 'docs_id');
					$docs_size = db_result($file_info_sql, 0, 'docs_size');
					$docs_type = db_result($file_info_sql, 0, 'docs_type');
				}
				$yourName = 'PRIORITIZE REDCap';
				$expireDays = 3; // set the SendIt to expire in this many days
				/**
				 * $file_location:
				 * 1 = ephemeral, will be deleted on $expireDate
				 * 2 = export file, visible only to rights in file repository
				 */
				$file_location = 2;
				$send = 1; // always send download confirmation
				$expireDate = date('Y-m-d H:i:s', strtotime("+$expireDays days"));
				$expireYear = substr($expireDate, 0, 4);
				$expireMonth = substr($expireDate, 5, 2);
				$expireDay = substr($expireDate, 8, 2);
				$expireHour = substr($expireDate, 11, 2);
				$expireMin = substr($expireDate, 14, 2);

				// Add entry to sendit_docs table
				$query = "INSERT INTO redcap_sendit_docs (doc_name, doc_orig_name, doc_type, doc_size, send_confirmation, expire_date, username,
					location, docs_id, date_added)
				  VALUES ('$originalFilename', '" . prep($originalFilename) . "', '$docs_type', '$docs_size', $send, '$expireDate', '" . prep($userid) . "',
					$file_location, $docs_id, '" . NOW . "')";
				db_query($query);
				$newId = db_insert_id();

				$logDescrip = "Send file from file repository (Send-It)";
				log_event($query, "redcap_sendit_docs", "MANAGE", $newId, "document_id = $newId", $logDescrip);

				// Set email subject
				$subject = "[PRIORITIZE] " . $comment;
				$subject = html_entity_decode($subject, ENT_QUOTES);

				// Set email From address
				$from = array('Ken Bergquist' => 'kbergqui@email.unc.edu');

				// Begin set up of email to send to recipients
				$email = new Message();
				foreach ($from as $name => $address) {
					$email->setFrom($address);
					$email->setFromName($name);
				}
				$email->setSubject($subject);

				// Loop through each recipient and send email
				foreach ($to as $name => $address) {
					// If a non-blank email address
					if (trim($address) != '') {
						// create key for unique url
						$key = strtoupper(substr(uniqid(md5(mt_rand())), 0, 25));

						// create password
						$pwd = generateRandomHash(8, false, true);

						$query = "INSERT INTO redcap_sendit_recipients (email_address, sent_confirmation, download_date, download_count, document_id, guid, pwd)
						  VALUES ('$address', 0, NULL, 0, $newId, '$key', '" . md5($pwd) . "')";
						$q = db_query($query);

						// Download URL
						$url = APP_PATH_WEBROOT_FULL . 'redcap_v' . $redcap_version . '/SendIt/download.php?' . $key;

						// Message from sender
						$note = "$comment for $today";
						// Get YMD timestamp of the file's expiration time
						$expireTimestamp = date('Y-m-d H:i:s', mktime($expireHour, $expireMin, 0, $expireMonth, $expireDay, $expireYear));

						// Email body
						$body = "<html><body style=\"font-family:Arial;font-size:10pt;\">
							$yourName {$lang['sendit_51']} \"$originalFilename\" {$lang['sendit_52']} " .
							date('l', mktime($expireHour, $expireMin, 0, $expireMonth, $expireDay, $expireYear)) . ",
							" . DateTimeRC::format_ts_from_ymd($expireTimestamp) . "{$lang['period']}
							{$lang['sendit_53']}<br><br>
							{$lang['sendit_54']}<br>
							<a href=\"$url\">$url</a><br><br>
							$note
							<br>-----------------------------------------------<br>
							{$lang['sendit_55']} " . CONSORTIUM_WEBSITE_DOMAIN . ".
							</body></html>";

						// Construct email and send
						$email->setTo($address);
						$email->setToName($name);
						$email->setBody($body);
						if ($email->send()) {
							// Now send follow-up email containing password
							$bodypass = "<html><body style=\"font-family:Arial;font-size:10pt;\">
								{$lang['sendit_50']}<br><br>
								$pwd<br><br>
								</body></html>";
							$email->setSubject("Re: $subject");
							$email->setBody($bodypass);
							sleep(2); // Hold for a second so that second email somehow doesn't reach the user first
							$email->send();
						} else {
							error_log("ERROR: pid=$project_id: Email to $name <$address> NOT SENT");
						}

					}
				}
			}
			unset($table_csv);
		}
	}

	/**
	 * @param $record
	 * @param $redcap_event_name
	 * @param $instrument
	 * @param $type
	 * @param $debug
	 */
	public static function set_notification($record, $redcap_event_name, $instrument, $debug)
	{
		global $Proj, $project_id;
		$group_id = self::getGroupID($record);
		$group_name_result = db_query("SELECT group_name FROM redcap_data_access_groups WHERE project_id = '$project_id' AND group_id = '$group_id'");
		if ($group_name_result) {
			$today = date('Y-m-d');
			$group_name_row = db_fetch_assoc($group_name_result);
			$group_name = $group_name_row['group_name'];
			$site_id = substr($group_name_row['group_name'], 0, 3);
			$first_event_id = $Proj->firstEventId;
			switch ($instrument) {
				case 'site_source_upload_form':
					if ($debug) {
						error_log("DEBUG: $instrument notification for $group_name");
					} else {
						if (!db_query("INSERT INTO target_email_actions SET project_id = '$project_id', record = '$record', redcap_event_name = '$redcap_event_name', redcap_data_access_group = '" . prep($group_name) . "', form_name = '$instrument', action_date = '$today'")) {
							error_log(db_error());
						}
					}
					break;
				case 'source_upload_form':
					if ($debug) {
						error_log("DEBUG: $instrument notification for $group_name");
					} else {
						if ($site_id >= '300') {
							if (!db_query("INSERT INTO target_email_actions SET project_id = '$project_id', record = '$record', redcap_event_name = '$redcap_event_name', redcap_data_access_group = '" . prep($group_name) . "', form_name = '$instrument', action_date = '$today'")) {
								error_log(db_error());
							}
						}
					}
					break;
				case 'treatment_start':
					$started_tx = get_single_field($record, $project_id, $first_event_id, 'trt_suppex_txstat', null);
					if ($started_tx == 'Y') {
						if ($debug) {
							error_log("DEBUG: Subject# $record for $group_name has started treatment");
						} else {
							if (!db_query("INSERT INTO target_email_actions SET project_id = '$project_id', record = '$record', redcap_event_name = '$redcap_event_name', redcap_data_access_group = '" . prep($group_name) . "', form_name = '$instrument', action_date = '$today'")) {
								error_log(db_error());
							}
						}
					} else {
						error_log("DEBUG: Subject# $record for $group_name has not started treatment");
					}
					break;
				default:
					break;
			}
		}
	}

	/**
	 * @param $record
	 * @param $debug
	 */
	public static function set_tx_data($record, $debug)
	{
		global $Proj, $project_id, $tx_prefixes, $dm_array, $tx_array, $endt_fields, $regimen_fields;
		$enable_kint = $debug && (isset($record) && $record != '') ? true : false;
		Kint::enabled($enable_kint);
		$baseline_event_id = $Proj->firstEventId;
		$fields = array_merge($dm_array, $tx_array, $endt_fields, array('trt_suppex_txstat'));
		$data = REDCap::getData('array', $record, $fields);
		$regimen_data = REDCap::getData('array', $record, $regimen_fields);
		foreach ($data AS $subject_id => $subject) {
			$start_stack = array();
			$tx_start_date = null;
			$stop_date = null;
			$age_at_start = null;
			$end_values = array();
			foreach ($subject AS $event_id => $event) {
				/**
				 * build dm_rfstdtc array
				 */
				foreach ($tx_array AS $tx_start) {
					if ($event[$tx_start] != '') {
						$start_stack[] = $event[$tx_start];
					}
				}
				/**
				 * build entdtc array
				 */
				foreach ($endt_fields AS $endt_field) {
					if ($event[$endt_field] != '') {
						$end_values[$event_id][$endt_field] = $event[$endt_field];
					}
				}
			}
			/**
			 * SUBJECT LEVEL
			 */
			rsort($start_stack);
			$tx_start_date = get_end_of_array($start_stack);
			/**
			 * dm_rfstdtc
			 */
			update_field_compare($subject_id, $project_id, $baseline_event_id, $tx_start_date, $subject[$baseline_event_id]['dm_rfstdtc'], 'dm_rfstdtc', $debug);
			/**
			 * age is dependent on the dm_rfxstdtc, not the derived treatment start date used elsewhere
			 */
			$dm_rfxstdtc = $subject[$baseline_event_id]['dm_rfxstdtc'];
			if (isset($dm_rfxstdtc)) {
				/**
				 * Age at start of treatment
				 * age_suppvs_age
				 */
				if ($subject[$baseline_event_id]['dm_brthyr'] != '') {
					$birth_year = $subject[$baseline_event_id]['dm_brthyr'];
				} elseif ($subject[$baseline_event_id]['dm_brthdtc'] != '') {
					$birth_year = substr($subject[$baseline_event_id]['dm_brthdtc'], 0, 4);
				} else {
					$birth_year = '';
				}
				if (isset($birth_year) && $birth_year != '') {
					$tx_start_year = substr($dm_rfxstdtc, 0, 4);
					$age_at_start = ($tx_start_year - $birth_year) > 0 ? $tx_start_year - $birth_year : null;
				}
				update_field_compare($subject_id, $project_id, $baseline_event_id, $age_at_start, $subject[$baseline_event_id]['age_suppvs_age'], 'age_suppvs_age', $debug);
			}
			/**
			 * dependent on derived TX start
			 */
			if (isset($tx_start_date)) {
				/**
				 * Date of last dose of HCV treatment or Treatment stop date
				 * dis_suppfa_txendt
				 */
				$stack = array();
				if (array_search_recursive('ONGOING', $end_values) === false) {
					foreach ($tx_prefixes AS $endt_prefix) {
						foreach ($end_values AS $event) {
							if ($event[$endt_prefix . '_exendtc'] != '' && ($event[$endt_prefix . '_suppex_extrtout'] == 'COMPLETE') || $event[$endt_prefix . '_suppex_extrtout'] == 'PREMATURELY_DISCONTINUED') {
								$stack[] = $event[$endt_prefix . '_exendtc'];
								d('PREFIX ' . $endt_prefix, $event);
							}
						}
					}
				}
				sort($start_stack);
				sort($stack);
				$last_date_in_start_stack = get_end_of_array($start_stack);
				$last_date_in_stack = get_end_of_array($stack);
				$stop_date = $last_date_in_stack < $last_date_in_start_stack ? null : $last_date_in_stack;
				d($end_values);
				d($start_stack);
				d($stack);
				d($last_date_in_start_stack);
				d($last_date_in_stack);
				d($stop_date);
				update_field_compare($subject_id, $project_id, $baseline_event_id, $stop_date, $subject[$baseline_event_id]['dis_suppfa_txendt'], 'dis_suppfa_txendt', $debug);
				/**
				 * HCV Treatment duration
				 */
				if (isset($stop_date)) {
					$tx_start_date_obj = new DateTime($tx_start_date);
					$tx_stop_date_obj = new DateTime($stop_date);
					$tx_duration = $tx_start_date_obj->diff($tx_stop_date_obj);
					$dis_dsstdy = $tx_duration->format('%R%a') + 1;
					update_field_compare($subject_id, $project_id, $baseline_event_id, $dis_dsstdy, $subject[$baseline_event_id]['dis_dsstdy'], 'dis_dsstdy', $debug);
				}
			}
			/**
			 * update treatment regimen
			 */
			$txstat = isset($tx_start_date) ? 'Y' : 'N';
			$regimen = get_regimen($regimen_data[$subject_id], $subject[$baseline_event_id]['eot_dsterm'], $txstat);
			update_field_compare($subject_id, $project_id, $baseline_event_id, $regimen['actarm'], $subject[$baseline_event_id]['dm_actarm'], 'dm_actarm', $debug);
			update_field_compare($subject_id, $project_id, $baseline_event_id, $regimen['actarmcd'], $subject[$baseline_event_id]['dm_actarmcd'], 'dm_actarmcd', $debug);
		}
	}

	/**
	 * @param $record
	 * @param $debug
	 */
	public static function set_treatment_exp($record, $debug)
	{
		global $Proj, $project_id;
		$trt_exp_array = array('gen2_mhoccur', 'pegifn_mhoccur', 'triple_mhoccur', 'nopegifn_mhoccur', 'dm_suppdm_trtexp');
		$enable_kint = $debug && (isset($record) && $record != '') ? true : false;
		Kint::enabled($enable_kint);
		$baseline_event_id = $Proj->firstEventId;
		$data = REDCap::getData('array', $record, $trt_exp_array, $baseline_event_id);
		if ($debug) {
			error_log(print_r($data, TRUE));
		}
		foreach ($data AS $subject_id => $subject) {
			/**
			 * Are you experienced?
			 */
			$experienced = false;
			foreach ($subject AS $event_id => $event) {
				if ($event['simsof_mhoccur'] == 'Y' || $event['simsofrbv_mhoccur'] == 'Y' || $event['pegifn_mhoccur'] == 'Y' || $event['triple_mhoccur'] == 'Y' || $event['nopegifn_mhoccur'] == 'Y') {
					$experienced = true;
				}
			}
			$trt_exp = $experienced ? 'Y' : 'N';
			update_field_compare($subject_id, $project_id, $baseline_event_id, $trt_exp, $subject[$baseline_event_id]['dm_suppdm_trtexp'], 'dm_suppdm_trtexp', $debug);
		}
	}

	/**
	 * @param $record
	 * @param $redcap_event_name
	 * @param $instrument
	 * @param $debug
	 */
	public static function code_terms($record, $redcap_event_name, $instrument, $debug)
	{
		global $Proj, $project_id, $tx_fragment_labels;
		$this_event_id = $Proj->getEventIdUsingUniqueEventName($redcap_event_name);
		switch ($instrument) {
			case 'ae_coding':
				$recode_llt = false;
				$recode_pt = true;
				$recode_soc = true;
				$prefix = 'ae';
				/**
				 * AE_AEMODIFY
				 */
				$fields = array("ae_aeterm", "ae_oth_aeterm", "ae_aemodify");
				$data = REDCap::getData('array', $record, $fields, $this_event_id);
				code_llt($project_id, $record, $this_event_id, fix_case($data[$record][$this_event_id]['ae_aeterm']), fix_case($data[$record][$this_event_id]['ae_oth_aeterm']), $data[$record][$this_event_id]['ae_aemodify'], 'ae_aemodify', $debug, $recode_llt);
				if ($debug) {
					error_log("DEBUG: Coded AE_AEMODIFY {$data[$record][$this_event_id]['ae_aemodify']}: subject=$record, event=$this_event_id for AE {$data[$record][$this_event_id]['ae_aeterm']} - {$data[$record][$this_event_id]['ae_oth_aeterm']}");
				}
				/**
				 * PREFIX_AEDECOD
				 * uses $tx_prefixes preset array
				 */
				$fields = array($prefix . "_aemodify", $prefix . "_aedecod");
				$data = REDCap::getData('array', $record, $fields, $this_event_id);
				code_pt($project_id, $record, $this_event_id, $data[$record][$this_event_id][$prefix . "_aemodify"], $data[$record][$this_event_id][$prefix . "_aedecod"], $prefix . "_aedecod", $debug, $recode_pt);
				if ($debug) {
					error_log("DEBUG: Coded " . strtoupper($prefix) . "_AEDECOD {$data[$record][$this_event_id][$prefix . '_aedecod']}: subject=$record, event=$this_event_id for AEMODIFY {$data[$record][$this_event_id][$prefix . '_aemodify']}");
				}
				/**
				 * PREFIX_AEBODSYS
				 * uses $tx_prefixes preset array
				 */
				$fields = array($prefix . "_aedecod", $prefix . "_aebodsys");
				$data = REDCap::getData('array', $record, $fields, $this_event_id);
				code_bodsys($project_id, $record, $this_event_id, $data[$record][$this_event_id][$prefix . "_aedecod"], $data[$record][$this_event_id][$prefix . "_aebodsys"], $prefix . "_aebodsys", $debug, $recode_soc);
				if ($debug) {
					error_log("DEBUG: Coded SOC: subject=$record, event=$this_event_id for AE {$data[$record][$this_event_id][$prefix . "_aedecod"]}");
				}
				unset($data);
				break;
			/**
			 * ADVERSE EVENTS
			 * ACTION: auto-code AE
			 */
			case 'adverse_events':
				$recode_llt = true;
				$recode_pt = true;
				$recode_soc = true;
				/**
				 * AE_AEDECOD
				 */
				$fields = array("ae_aeterm", "ae_oth_aeterm", "ae_aemodify");
				$data = REDCap::getData('array', $record, $fields, $this_event_id);
				code_llt($project_id, $record, $this_event_id, fix_case($data[$record][$this_event_id]['ae_aeterm']), fix_case($data[$record][$this_event_id]['ae_oth_aeterm']), $data[$record][$this_event_id]['ae_aemodify'], 'ae_aemodify', $debug, $recode_llt);
				if ($debug) {
					error_log("DEBUG: Coded AE_AEMODIFY {$data[$record][$this_event_id]['ae_aemodify']}: subject=$record, event=$this_event_id for AE {$data[$record][$this_event_id]['ae_aeterm']} - {$data[$record][$this_event_id]['ae_oth_aeterm']}");
				}
				/**
				 * AE_AEDECOD
				 */
				$fields = array("ae_aemodify", "ae_aedecod");
				$data = REDCap::getData('array', $record, $fields, $this_event_id);
				code_pt($project_id, $record, $this_event_id, fix_case($data[$record][$this_event_id]['ae_aemodify']), $data[$record][$this_event_id]['ae_aedecod'], 'ae_aedecod', $debug, $recode_pt);
				if ($debug) {
					error_log("DEBUG: Coded AE_AEDECOD {$data[$record][$this_event_id]['ae_aedecod']}: subject=$record, event=$this_event_id for AE {$data[$record][$this_event_id]['ae_aemodify']}");
				}
				/**
				 * AE_AEBODSYS
				 */
				$fields = array("ae_aedecod", "ae_aebodsys");
				$data = REDCap::getData('array', $record, $fields, $this_event_id);
				code_bodsys($project_id, $record, $this_event_id, $data[$record][$this_event_id]['ae_aedecod'], $data[$record][$this_event_id]['ae_aebodsys'], 'ae_aebodsys', $debug, $recode_soc);
				if ($debug) {
					error_log("DEBUG: Coded SOC: subject=$record, event=$this_event_id for AE {$data[$record][$this_event_id]['ae_aedecod']}");
				}
				unset($data);
				break;
			/**
			 * MEDICAL HISTORY
			 * ACTION: auto-code MH
			 */
			case 'key_medical_history':
				$recode_llt = false;
				$recode_pt = true;
				$recode_soc = true;
				$mh_prefixes = array('othpsy', 'othca');
				/**
				 * MH_MHMODIFY
				 */
				foreach ($mh_prefixes AS $prefix) {
					$fields = array($prefix . "_oth_mhterm", $prefix . "_mhmodify");
					$data = REDCap::getData('array', $record, $fields, $this_event_id);
					code_llt($project_id, $record, $this_event_id, fix_case($data[$record][$this_event_id][$prefix . "_oth_mhterm"]), '', $data[$record][$this_event_id][$prefix . "_mhmodify"], $prefix . "_mhmodify", $debug, $recode_llt);
					if ($debug) {
						error_log("DEBUG: Coded " . strtoupper($prefix) . "_MHMODIFY {$data[$record][$this_event_id][$prefix . "_mhmodify"]}: subject=$record, event=$this_event_id for MH {$data[$record][$this_event_id][$prefix . "_oth_mhterm"]}");
					}
					/**
					 * PREFIX_MHDECOD
					 * uses $mh_prefixes preset array
					 */
					$fields = array($prefix . "_mhmodify", $prefix . "_mhdecod");
					$data = REDCap::getData('array', $record, $fields, $this_event_id);
					code_pt($project_id, $record, $this_event_id, $data[$record][$this_event_id][$prefix . "_mhmodify"], $data[$record][$this_event_id][$prefix . "_mhdecod"], $prefix . "_mhdecod", $debug, $recode_pt);
					if ($debug) {
						error_log("DEBUG: Coded " . strtoupper($prefix) . "_MHDECOD {$data[$record][$this_event_id][$prefix . '_mhdecod']}: subject=$record, event=$this_event_id for MHMODIFY {$data[$record][$this_event_id][$prefix . '_mhmodify']}");
					}
					/**
					 * PREFIX_mhBODSYS
					 * uses $mh_prefixes preset array
					 */
					$fields = array($prefix . "_mhdecod", $prefix . "_mhbodsys");
					$data = REDCap::getData('array', $record, $fields, $this_event_id);
					code_bodsys($project_id, $record, $this_event_id, $data[$record][$this_event_id][$prefix . "_mhdecod"], $data[$record][$this_event_id][$prefix . "_mhbodsys"], $prefix . "_mhbodsys", $debug, $recode_soc);
					if ($debug) {
						error_log("DEBUG: Coded " . strtoupper($prefix) . "_MHBODSYS {$data[$record][$this_event_id][$prefix . "_mhbodsys"]}: subject=$record, event=$this_event_id for MHDECOD {$data[$record][$this_event_id][$prefix . "_mhdecod"]}");
					}
				}
				unset($data);
				break;
			/**
			 * EOT
			 */
			case 'early_discontinuation_eot':
				$recode_llt = true;
				$recode_pt = true;
				$recode_soc = true;
				/**
				 * EOT_AEDECOD
				 */
				$data = REDCap::getData('array', $record, array("eot_suppds_ncmpae", "eot_oth_suppds_ncmpae", "eot_aemodify", "eot_dsterm"), $this_event_id);
				$ptdata = REDCap::getData('array', $record, array("eot_aemodify", "eot_aedecod"), $this_event_id);
				$soc_data = REDCap::getData('array', $record, array("eot_aedecod", "eot_aebodsys"), $this_event_id);
				foreach ($data[$record][$this_event_id] AS $event) {
					if ($event['eot_dsterm'] == 'ADVERSE_EVENT') {
						code_llt($project_id, $record, $this_event_id, fix_case($event['eot_suppds_ncmpae']), fix_case($event['eot_oth_suppds_ncmpae']), $event['eot_aemodify'], 'eot_aemodify', $debug, $recode_llt);
						if ($debug) {
							error_log("INFO (TESTING EOT): Coded EOT_AEMODIFY {$event['eot_aemodify']}: subject=$record, event=$this_event_id for AE {$event['eot_suppds_ncmpae']} - {$event['eot_oth_suppds_ncmpae']}");
						}
						/**
						 * AE_AEDECOD
						 */
						foreach ($ptdata[$record][$this_event_id] AS $ptevent) {
							code_pt($project_id, $record, $this_event_id, fix_case($ptevent['eot_aemodify']), $ptevent['eot_aedecod'], 'eot_aedecod', $debug, $recode_pt);
							if ($debug) {
								error_log("DEBUG: Coded EOT_AEDECOD {$ptevent['eot_aedecod']}: subject=$record, event=$this_event_id for AEMODIFY {$ptevent['eot_aemodify']}");
							}
						}
						/**
						 * EOT_AEBODSYS
						 */
						foreach ($soc_data[$record][$this_event_id] AS $soc_event) {
							code_bodsys($project_id, $record, $this_event_id, $soc_event['eot_aedecod'], $soc_event['eot_aebodsys'], 'eot_aebodsys', $debug, $recode_soc);
							if ($debug) {
								error_log("DEBUG: Coded SOC: subject=$record, event=$this_event_id for AE {$soc_event['eot_aedecod']}");
							}
						}
					}
				}
				unset($data);
				unset($ptdata);
				unset($soc_data);
				break;
			/**
			 * TX stop AEs
			 */
			case 'ribavirin_administration':
			case 'harvoni_administration':
			case 'ombitasvir_paritaprevir':
			case 'dasabuvir':
			case 'zepatier_administration':
				$recode_llt = true;
				$recode_pt = true;
				$recode_soc = true;
				$tx_prefix = array_search(substr($instrument, 0, strpos($instrument, '_')), $tx_fragment_labels);
				/**
				 * AE_AEMODIFY
				 */
				$fields = array($tx_prefix . '_suppcm_cmncmpae', $tx_prefix . '_oth_suppcm_cmncmpae', $tx_prefix . '_aemodify');
				$data = REDCap::getData('array', $record, $fields, $this_event_id);
				foreach ($data[$record][$this_event_id] AS $event) {
					code_llt($project_id, $record, $this_event_id, fix_case($event[$tx_prefix . '_suppcm_cmncmpae']), fix_case($event[$tx_prefix . '_oth_suppcm_cmncmpae']), $event[$tx_prefix . '_aemodify'], $tx_prefix . '_aemodify', $debug, $recode_llt);
					if ($debug) {
						error_log("DEBUG: Coded AE_AEMODIFY {$event[$tx_prefix . '_aemodify']}: subject=$record, event=$this_event_id for AE {$event[$tx_prefix . '_suppcm_cmncmpae']} - {$event[$tx_prefix . '_oth_suppcm_cmncmpae']}");
					}
				}
				/**
				 * AE_AEDECOD
				 */
				$fields = array($tx_prefix . '_aemodify', $tx_prefix . "_aedecod");
				$data = REDCap::getData('array', $record, $fields, $this_event_id);
				foreach ($data[$record][$this_event_id] AS $event) {
					code_pt($project_id, $record, $this_event_id, fix_case($event[$tx_prefix . '_aemodify']), $event[$tx_prefix . '_aedecod'], $tx_prefix . '_aedecod', $debug, $recode_pt);
					if ($debug) {
						error_log("DEBUG: Coded AE_AEDECOD {$event[$tx_prefix . '_aedecod']}: subject=$record, event=$this_event_id for AE {$event[$tx_prefix . '_aemodify']}");
					}
				}
				/**
				 * AE_AEBODSYS
				 */
				$fields = array($tx_prefix . '_aedecod', $tx_prefix . '_aebodsys');
				$data = REDCap::getData('array', $record, $fields, $this_event_id);
				foreach ($data[$record][$this_event_id] AS $event) {
					code_bodsys($project_id, $record, $this_event_id, $event[$tx_prefix . '_aedecod'], $event[$tx_prefix . '_aebodsys'], $tx_prefix . '_aebodsys', $debug, $recode_soc);
					if ($debug) {
						error_log("DEBUG: Coded SOC: subject=$record, event=$this_event_id for AE {$event[$tx_prefix . '_aedecod']}");
					}
				}
				unset($data);
				break;
			/**
			 * CONMEDS
			 * ACTION: auto-code CONMEDS
			 */
			case 'conmeds':
				/**
				 * CM_CMDECOD
				 */
				$recode_cm = true;
				$recode_llt = true;
				$recode_pt = true;
				$recode_soc = true;
				$recode_atc = true;
				$fields = array("cm_cmtrt", "cm_cmdecod", "cm_cmindc", "cm_oth_cmindc", "cm_suppcm_indcod");
				$data = REDCap::getData('array', $record, $fields, $this_event_id);
				code_cm($project_id, $record, $this_event_id, $data[$record][$this_event_id], $debug, $recode_cm);
				/**
				 * cm_suppcm_mktstat
				 * PRESCRIPTION or OTC
				 */
				$fields = array("cm_cmdecod", "cm_suppcm_mktstat");
				$data = REDCap::getData('array', $record, $fields, $this_event_id);
				foreach ($data as $subject_id => $subject) {
					foreach ($subject as $event_id => $event) {
						if (isset($event['cm_cmdecod']) && $event['cm_cmdecod'] != '') {
							update_field_compare($subject_id, $project_id, $event_id, get_conmed_mktg_status($event['cm_cmdecod']), $event['cm_suppcm_mktstat'], 'cm_suppcm_mktstat', $debug);
							if ($debug) {
								error_log("DEBUG: $subject_id Marketing Status = " . get_conmed_mktg_status($event['cm_cmdecod']));
							}
						}
					}
				}
				/**
				 * CM_SUPPCM_INDCOD
				 */
				$fields = array("cm_cmindc", "cm_oth_cmindc", "cm_suppcm_indcmodf");
				$data = REDCap::getData('array', $record, $fields, $this_event_id);
				/**
				 * re-code all nutritional support to nutritional supplement
				 */
				if ($data[$record][$this_event_id]['cm_oth_cmindc'] == 'Nutritional support') {
					$data[$record][$this_event_id]['cm_oth_cmindc'] = 'Nutritional supplement';
				}
				code_llt($project_id, $record, $this_event_id, fix_case($data[$record][$this_event_id]['cm_cmindc']), fix_case($data[$record][$this_event_id]['cm_oth_cmindc']), $data[$record][$this_event_id]['cm_suppcm_indcmodf'], 'cm_suppcm_indcmodf', $debug, $recode_llt);
				if ($debug) {
					error_log("DEBUG: Coded INDC LLT: {} subject=$record, event=$this_event_id for INDICATION {$data[$record][$this_event_id]['cm_cmindc']}");
				}
				/**
				 * CM_SUPPCM_INDCOD
				 */
				$fields = array("cm_suppcm_indcmodf", "cm_suppcm_indcod");
				$data = REDCap::getData('array', $record, $fields, $this_event_id);
				code_pt($project_id, $record, $this_event_id, $data[$record][$this_event_id]['cm_suppcm_indcmodf'], $data[$record][$this_event_id]['cm_suppcm_indcod'], 'cm_suppcm_indcod', $debug, $recode_pt);
				if ($debug) {
					error_log("DEBUG: Coded INDC PT: subject=$record, event=$this_event_id for INDICATION {$data[$record][$this_event_id]['cm_suppcm_indcod']}");
				}
				/**
				 * CM_SUPPCM_INDCSYS
				 */
				$fields = array("cm_suppcm_indcod", "cm_suppcm_indcsys");
				$data = REDCap::getData('array', $record, $fields, $this_event_id);
				code_bodsys($project_id, $record, $this_event_id, $data[$record][$this_event_id]['cm_suppcm_indcod'], $data[$record][$this_event_id]['cm_suppcm_indcsys'], 'cm_suppcm_indcsys', $debug, $recode_soc);
				if ($debug) {
					error_log("DEBUG: Coded INDCSYS: subject=$record, event=$this_event_id for INDC {$data[$record][$this_event_id]['cm_suppcm_indcod']}");
				}
				/**
				 * CM_SUPPCM_ATCNAME
				 * CM_SUPPCM_ATC2NAME
				 */
				$fields = array("cm_cmdecod", "cm_suppcm_atcname", "cm_suppcm_atc2name");
				$data = REDCap::getData('array', $record, $fields, $this_event_id);
				code_atc($project_id, $record, $this_event_id, $data[$record][$this_event_id]['cm_cmdecod'], $data[$record][$this_event_id]['cm_suppcm_atcname'], $data[$record][$this_event_id]['cm_suppcm_atc2name'], $debug, $recode_atc);
				if ($debug) {
					error_log("DEBUG: Coded ATCs: subject=$record, event=$this_event_id for CONMED {$data[$record][$this_event_id]['cm_cmdecod']}");
				}
				break;

			case 'transfusions':
				$recode_cm = true;
				$recode_llt = true;
				$recode_soc = true;
				$recode_atc = true;
				/**
				 * XFSN_CMDECOD
				 */
				$fields = array("xfsn_cmtrt", "xfsn_cmdecod", "xfsn_cmindc", "xfsn_suppcm_indcod");
				$data = REDCap::getData('array', $record, $fields, $this_event_id);
				code_cm($project_id, $record, $this_event_id, $data[$record][$this_event_id], $debug, $recode_cm);
				/**
				 * XFSN_SUPPCM_INDCOD
				 */
				$fields = array("xfsn_cmindc", "xfsn_suppcm_indcod");
				$data = REDCap::getData('array', $record, $fields, $this_event_id);
				code_llt($project_id, $record, $this_event_id, fix_case($data[$record][$this_event_id]['xfsn_cmindc']), fix_case($data[$record][$this_event_id]['xfsn_oth_cmindc']), $data[$record][$this_event_id]['xfsn_suppcm_indcod'], 'xfsn_suppcm_indcod', $debug, $recode_llt);
				if ($debug) {
					error_log("DEBUG: Coded XFSN INDC: subject=$record, event=$this_event_id for CONMED {$data[$record][$this_event_id]['xfsn_cmdecod']}");
				}
				/**
				 * XFSN_SUPPCM_INDCSYS
				 */
				$fields = array("xfsn_suppcm_indcod", "xfsn_suppcm_indcsys");
				$data = REDCap::getData('array', $record, $fields, $this_event_id);
				code_bodsys($project_id, $record, $this_event_id, $data[$record][$this_event_id]['xfsn_suppcm_indcod'], $data[$record][$this_event_id]['xfsn_suppcm_indcsys'], 'xfsn_suppcm_indcsys', $debug, $recode_soc);
				if ($debug) {
					error_log("DEBUG: Coded XFSN INDCSYS: subject=$record, event=$this_event_id for INDC {$data[$record][$this_event_id]['xfsn_suppcm_indcod']}");
				}
				/**
				 * XFSN_SUPPCM_ATCNAME
				 * XFSN_SUPPCM_ATC2NAME
				 */
				$fields = array("xfsn_cmdecod", "xfsn_suppcm_atcname", "xfsn_suppcm_atc2name");
				$data = REDCap::getData('array', $record, $fields, $this_event_id);
				code_atc_xfsn($project_id, $record, $this_event_id, $data[$record][$this_event_id]['xfsn_cmdecod'], $data[$record][$this_event_id]['xfsn_suppcm_atcname'], $data[$record][$this_event_id]['xfsn_suppcm_atc2name'], $debug, $recode_atc);
				if ($debug) {
					error_log("DEBUG: Coded XFSN ATCs: subject=$record, event=$this_event_id for CONMED {$data[$record][$this_event_id]['xfsn_cmdecod']}");
				}
				unset($data);
				break;

			case 'mh_coding':
				$recode_llt = false;
				$recode_pt = true;
				$recode_soc = true;
				$mh_prefixes = array('othpsy', 'othca');
				/**
				 * MH_MHMODIFY
				 */
				foreach ($mh_prefixes AS $prefix) {
					$fields = array($prefix . "_oth_mhterm", $prefix . "_mhmodify");
					$data = REDCap::getData('array', $record, $fields, $this_event_id);
					code_llt($project_id, $record, $this_event_id, fix_case($data[$record][$this_event_id][$prefix . "_oth_mhterm"]), '', $data[$record][$this_event_id][$prefix . "_mhmodify"], $prefix . "_mhmodify", $debug, $recode_llt);
					if ($debug) {
						error_log("DEBUG: Coded " . strtoupper($prefix) . "_MHMODIFY {$data[$record][$this_event_id][$prefix . "_mhmodify"]}: subject=$record, event=$this_event_id for MH {$data[$record][$this_event_id][$prefix . "_oth_mhterm"]}");
					}
					/**
					 * PREFIX_MHDECOD
					 * uses $mh_prefixes preset array
					 */
					$fields = array($prefix . "_mhmodify", $prefix . "_mhdecod");
					$data = REDCap::getData('array', $record, $fields, $this_event_id);
					code_pt($project_id, $record, $this_event_id, $data[$record][$this_event_id][$prefix . "_mhmodify"], $data[$record][$this_event_id][$prefix . "_mhdecod"], $prefix . "_mhdecod", $debug, $recode_pt);
					if ($debug) {
						error_log("DEBUG: Coded " . strtoupper($prefix) . "_MHDECOD {$data[$record][$this_event_id][$prefix . '_mhdecod']}: subject=$record, event=$this_event_id for MHMODIFY {$data[$record][$this_event_id][$prefix . '_mhmodify']}");
					}
					/**
					 * PREFIX_mhBODSYS
					 * uses $mh_prefixes preset array
					 */
					$fields = array($prefix . "_mhdecod", $prefix . "_mhbodsys");
					$data = REDCap::getData('array', $record, $fields, $this_event_id);
					code_bodsys($project_id, $record, $this_event_id, $data[$record][$this_event_id][$prefix . "_mhdecod"], $data[$record][$this_event_id][$prefix . "_mhbodsys"], $prefix . "_mhbodsys", $debug, $recode_soc);
					if ($debug) {
						error_log("DEBUG: Coded  " . strtoupper($prefix) . "_MHBODSYS {$data[$record][$this_event_id][$prefix . "_mhbodsys"]}: subject=$record, event=$this_event_id for MHDECOD {$data[$record][$this_event_id][$prefix . "_mhdecod"]}");
					}
				}
				unset($data);
				break;

			case 'cm_coding':
				$recode_llt = false;
				$recode_pt = true;
				$recode_soc = true;
				$recode_atc = false;
				$recode_cm = true;
				/**
				 * CM_CMDECOD
				 */
				$fields = array("cm_cmtrt", "cm_cmdecod", "cm_cmindc", "cm_oth_cmindc", "cm_suppcm_indcod");
				$data = REDCap::getData('array', $record, $fields, $this_event_id);
				code_cm($project_id, $record, $this_event_id, $data[$record][$this_event_id], $debug, $recode_cm);
				if ($debug) {
					error_log("DEBUG: Coded CONMED: subject=$record, event=$this_event_id for CMTRT {$data[$record][$this_event_id]['cm_cmtrt']}");
				}
				/**
				 * cm_suppcm_mktstat
				 * PRESCRIPTION or OTC
				 */
				$fields = array("cm_cmdecod", "cm_suppcm_mktstat");
				$data = REDCap::getData('array', $record, $fields, $this_event_id);
				foreach ($data as $subject_id => $subject) {
					foreach ($subject as $event_id => $event) {
						if (isset($event['cm_cmdecod']) && $event['cm_cmdecod'] != '') {
							update_field_compare($subject_id, $project_id, $event_id, get_conmed_mktg_status($event['cm_cmdecod']), $event['cm_suppcm_mktstat'], 'cm_suppcm_mktstat', $debug);
							if ($debug) {
								error_log("DEBUG: $subject_id Marketing Status = " . get_conmed_mktg_status($event['cm_cmdecod']));
							}
						}
					}
				}
				/**
				 * CM_SUPPCM_INDCOD
				 */
				$fields = array("cm_cmindc", "cm_oth_cmindc", "cm_suppcm_indcmodf");
				$data = REDCap::getData('array', $record, $fields, $this_event_id);
				/**
				 * re-code all nutritional support to nutritional supplement
				 */
				if ($data[$record][$this_event_id]['cm_oth_cmindc'] == 'Nutritional support') {
					$data[$record][$this_event_id]['cm_oth_cmindc'] = 'Nutritional supplement';
				}
				code_llt($project_id, $record, $this_event_id, fix_case($data[$record][$this_event_id]['cm_cmindc']), fix_case($data[$record][$this_event_id]['cm_oth_cmindc']), $data[$record][$this_event_id]['cm_suppcm_indcmodf'], 'cm_suppcm_indcmodf', $debug, $recode_llt);
				if ($debug) {
					error_log("DEBUG: Coded INDC LLT: {} subject=$record, event=$this_event_id for INDICATION {$data[$record][$this_event_id]['cm_cmindc']}");
				}
				/**
				 * CM_SUPPCM_INDCOD
				 */
				$fields = array("cm_suppcm_indcmodf", "cm_suppcm_indcod");
				$data = REDCap::getData('array', $record, $fields, $this_event_id);
				code_pt($project_id, $record, $this_event_id, $data[$record][$this_event_id]['cm_suppcm_indcmodf'], $data[$record][$this_event_id]['cm_suppcm_indcod'], 'cm_suppcm_indcod', $debug, $recode_pt);
				if ($debug) {
					error_log("DEBUG: Coded INDC PT: subject=$record, event=$this_event_id for INDICATION {$data[$record][$this_event_id]['cm_suppcm_indcod']}");
				}
				/**
				 * CM_SUPPCM_INDCSYS
				 */
				$fields = array("cm_suppcm_indcod", "cm_suppcm_indcsys");
				$data = REDCap::getData('array', $record, $fields, $this_event_id);
				code_bodsys($project_id, $record, $this_event_id, $data[$record][$this_event_id]['cm_suppcm_indcod'], $data[$record][$this_event_id]['cm_suppcm_indcsys'], 'cm_suppcm_indcsys', $debug, $recode_soc);
				if ($debug) {
					error_log("DEBUG: Coded INDCSYS: subject=$record, event=$this_event_id for INDC {$data[$record][$this_event_id]['cm_suppcm_indcod']}");
				}
				/**
				 * CM_SUPPCM_ATCNAME
				 * CM_SUPPCM_ATC2NAME
				 */
				$fields = array("cm_cmdecod", "cm_suppcm_atcname", "cm_suppcm_atc2name");
				$data = REDCap::getData('array', $record, $fields, $this_event_id);
				code_atc_soc($project_id, $record, $this_event_id, $data[$record][$this_event_id]['cm_cmdecod'], $data[$record][$this_event_id]['cm_suppcm_atcname'], $data[$record][$this_event_id]['cm_suppcm_atc2name'], $debug, $recode_atc);
				if ($debug) {
					error_log("DEBUG: Coded ATCs: subject=$record, event=$this_event_id for CONMED {$data[$record][$this_event_id]['cm_cmdecod']}");
				}
				/**
				 * XFSN_CMDECOD
				 */
				$fields = array("xfsn_cmtrt", "xfsn_cmdecod", "xfsn_cmindc", "xfsn_suppcm_indcod");
				$data = REDCap::getData('array', $record, $fields, $this_event_id);
				foreach ($data AS $subject_id => $subject) {
					foreach ($subject AS $event_id => $event) {
						if (isset($event['xfsn_cmtrt']) && $event['xfsn_cmtrt'] != '') {
							$med = array();
							$med_result = db_query("SELECT DISTINCT drug_coded FROM _target_xfsn_coding WHERE drug_name = '" . prep($event['xfsn_cmtrt']) . "'");
							if ($med_result) {
								$med = db_fetch_assoc($med_result);
								if (isset($med['drug_coded']) && $med['drug_coded'] != '') {
									update_field_compare($subject_id, $project_id, $event_id, $med['drug_coded'], $event['xfsn_cmdecod'], 'xfsn_cmdecod', $debug);
								}
							}
							if ($debug) {
								error_log("DEBUG: Coded Transfusion: subject=$subject_id, event=$event_id for CMTRT {$event['xfsn_cmtrt']}");
							}
						} else {
							update_field_compare($subject_id, $project_id, $event_id, '', $event['xfsn_cmdecod'], 'xfsn_cmdecod', $debug);
						}
					}
				}
				/**
				 * XFSN_SUPPCM_INDCOD
				 */
				$fields = array("xfsn_cmindc", "xfsn_suppcm_indcod");
				$data = REDCap::getData('array', $record, $fields, $this_event_id);
				code_llt($project_id, $record, $this_event_id, fix_case($data[$record][$this_event_id]['xfsn_cmindc']), fix_case($data[$record][$this_event_id]['xfsn_oth_cmindc']), $data[$record][$this_event_id]['xfsn_suppcm_indcod'], 'xfsn_suppcm_indcod', $debug, $recode_llt);
				if ($debug) {
					error_log("DEBUG: Coded XFSN INDC: subject=$record, event=$this_event_id for CONMED {$data[$record][$this_event_id]['xfsn_cmdecod']}");
				}
				/**
				 * XFSN_SUPPCM_INDCSYS
				 */
				$fields = array("xfsn_suppcm_indcod", "xfsn_suppcm_indcsys");
				$data = REDCap::getData('array', $record, $fields, $this_event_id);
				code_bodsys($project_id, $record, $this_event_id, $data[$record][$this_event_id]['xfsn_suppcm_indcod'], $data[$record][$this_event_id]['xfsn_suppcm_indcsys'], 'xfsn_suppcm_indcsys', $debug, $recode_soc);
				if ($debug) {
					error_log("DEBUG: Coded XFSN INDCSYS: subject=$record, event=$this_event_id for INDC {$data[$record][$this_event_id]['xfsn_suppcm_indcod']}");
				}
				/**
				 * XFSN_SUPPCM_ATCNAME
				 * XFSN_SUPPCM_ATC2NAME
				 */
				$fields = array("xfsn_cmdecod", "xfsn_suppcm_atcname", "xfsn_suppcm_atc2name");
				$data = REDCap::getData('array', $record, $fields, $this_event_id);
				code_atc_xfsn($project_id, $record, $this_event_id, $data[$record][$this_event_id]['xfsn_cmdecod'], $data[$record][$this_event_id]['xfsn_suppcm_atcname'], $data[$record][$this_event_id]['xfsn_suppcm_atc2name'], $debug, $recode_atc);
				if ($debug) {
					error_log("DEBUG: Coded XFSN ATCs: subject=$record, event=$this_event_id for CONMED {$data[$record][$this_event_id]['xfsn_cmdecod']}");
				}
				unset($data);
				break;

			case 'ex_coding':
				$recode_pt = true;
				$recode_soc = true;
				$prefix = 'eot';
				/**
				 * PREFIX_AEDECOD
				 * uses $tx_prefixes preset array
				 */
				$fields = array($prefix . "_aemodify", $prefix . "_aedecod");
				$data = REDCap::getData('array', $record, $fields, $this_event_id);
				code_pt($project_id, $record, $this_event_id, $data[$record][$this_event_id][$prefix . "_aemodify"], $data[$record][$this_event_id][$prefix . "_aedecod"], $prefix . "_aedecod", $debug, $recode_pt);
				if ($debug) {
					error_log("DEBUG: Coded " . strtoupper($prefix) . "_AEDECOD {$data[$record][$this_event_id][$prefix . '_aedecod']}: subject=$record, event=$this_event_id for AEMODIFY {$data[$record][$this_event_id][$prefix . '_aemodify']}");
				}
				/**
				 * PREFIX_AEBODSYS
				 * uses $tx_prefixes preset array
				 */
				$fields = array($prefix . "_aedecod", $prefix . "_aebodsys");
				$data = REDCap::getData('array', $record, $fields, $this_event_id);
				code_bodsys($project_id, $record, $this_event_id, $data[$record][$this_event_id][$prefix . "_aedecod"], $data[$record][$this_event_id][$prefix . "_aebodsys"], $prefix . "_aebodsys", $debug, $recode_soc);
				if ($debug) {
					error_log("DEBUG: Coded SOC: subject=$record, event=$this_event_id for AE {$data[$record][$this_event_id][$prefix . "_aedecod"]}");
				}
				unset($data);
				break;
			default:
				break;
		}
	}

	/**
	 * @param $record
	 * @return array
	 */
	public static function getTrtInfo($record)
	{
		global $Proj, $project_id;
		$trtinfo = array();
		$baseline_event_id = $Proj->firstEventId;
		$randomization_date = get_single_field($record, $project_id, $baseline_event_id, 'rand_suppex_rndstdtc', null);
		if ($randomization_date != '') {
			$trtinfo['rand_date'] = $randomization_date;
			$trtinfo['rfxstdtc'] = get_single_field($record, $project_id, $baseline_event_id, 'dm_rfxstdtc', null);
			$trtinfo['rfstdtc'] = get_single_field($record, $project_id, $baseline_event_id, 'dm_rfstdtc', null);
			$trtinfo['regimen'] = $regimen = strtolower(get_single_field($record, $project_id, $baseline_event_id, 'rand_suppex_randreg', null));
			$trtinfo['dur'] = $duration = get_single_field($record, $project_id, $baseline_event_id, $regimen . '_suppex_trtdur', null);
			$trtinfo['num'] = $num = substr($duration, strpos($duration, 'P') + 1, strlen($duration) - 2);
			$trtinfo['arm'] = $num . ' Weeks';
			/**
			 * check to see if the subject has an existing schedule on an existing arm
			 */
			$sub = "SELECT DISTINCT e.arm_id from redcap_events_calendar c, redcap_events_metadata e WHERE c.project_id = $project_id AND c.record = '$record' AND c.event_id = e.event_id";
			$sched_arm_result = db_query("SELECT arm_name FROM redcap_events_arms WHERE project_id = $project_id AND arm_id IN (" . pre_query($sub) . ") LIMIT 1");
			if ($sched_arm_result) {
				$trtinfo['timing_arm'] = db_result($sched_arm_result, 0, 'arm_name');
				db_free_result($sched_arm_result);
			}
			$timing_arm_result = db_query("SELECT arm_num FROM redcap_events_arms WHERE project_id = $project_id AND arm_name = '{$trtinfo['arm']}' LIMIT 1");
			if ($timing_arm_result) {
				$trtinfo['timing_arm_num'] = db_result($timing_arm_result, 0, 'arm_num');
				db_free_result($timing_arm_result);
			}
			$q = db_query("SELECT * from redcap_events_metadata m, redcap_events_arms a WHERE a.project_id = $project_id AND a.arm_id = m.arm_id AND a.arm_num = {$trtinfo['timing_arm_num']} order by m.day_offset, m.descrip");
			if ($q) {
				while ($q_row = db_fetch_assoc($q)) {
					$trtinfo['timing_events'][$q_row['descrip']] = $q_row['event_id'];
					$trtinfo['timing_offsets'][$q_row['descrip']] = $q_row['day_offset'];
					$trtinfo['timing_min'][$q_row['descrip']] = $q_row['offset_min'];
					$trtinfo['timing_max'][$q_row['descrip']] = $q_row['offset_max'];
				}
			}
		}
		return $trtinfo;
	}

	/**
	 * @param $record
	 * @param $debug
	 */
	public static function setTrtDuration($record, $debug)
	{
		/**
		 * derive treatment duration and therefore arm from randomized treatment and duration selected in this form
		 */
		global $Proj, $project_id;
		$first_event_id = $Proj->firstEventId;
		$trt = self::getTrtInfo($record);
		if ($debug) {
			error_log(print_r($trt, true));
		}
		$prescribed_duration = get_single_field($record, $project_id, $first_event_id, 'dm_suppex_trtdur', null);
		if (!isset($prescribed_duration) || $prescribed_duration == '') {
			update_field_compare($record, $project_id, $first_event_id, $trt['dur'], $prescribed_duration, 'dm_suppex_trtdur', $debug);
		}
	}
}