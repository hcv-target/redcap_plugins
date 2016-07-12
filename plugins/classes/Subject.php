<?php

/**
 * Created by PhpStorm.
 * User: kenbergquist
 * Date: 5/17/16
 * Time: 2:32 PM
 */
class Subject
{

	/**
	 * @param array $subject_regimen_data
	 * @param null|string $dsterm
	 * @param null|string $txstat
	 * @return array
	 */
	public static function getRegimen($subject_regimen_data, $dsterm = null, $txstat = null)
	{
		global $tx_to_arm, $intended_regimens;
		$actarm_array = array();
		$actarmcd_array = array();
		$arm = NULL;
		$armcd = NULL;
		$actarm = NULL;
		$actarmcd = NULL;
		foreach ($subject_regimen_data AS $regimen_event) {
			foreach ($regimen_event AS $reg_key => $reg_val) {
				if ($reg_key != 'reg_suppcm_regimen') { // actual ARM
					if ($reg_val != '') {
						$this_regimen = strtoupper(substr($reg_key, 0, strpos($reg_key, '_')));
						foreach ($tx_to_arm[$this_regimen] AS $arm_order => $arm_pair) {
							foreach ($arm_pair as $actarmcd => $arm_name) {
								$actarm_array[$arm_order] = $arm_name;
								$actarmcd_array[$arm_order] = $actarmcd;
							}
						}
					}
				} else { // planned ARM
					if ($reg_val != '') {
						$planned_array = $intended_regimens[$reg_val];
						foreach ($planned_array as $key => $val) {
							$arm = $val;
							$armcd = $key;
						}
					}
				}
			}
		}
		/**
		 * Viekira relabeling
		 */
		d('before', $actarmcd_array);
		d($actarm_array);
		if (array_search('VPK', $actarmcd_array) !== false) {
			if (array_search('DBV', $actarmcd_array) !== false) {
				unset($actarmcd_array[7], $actarm_array[7]);
				$actarm_array = array_replace($actarm_array, array(array_search('Viekira', $actarm_array) => 'Viekira Pak'));
			} else {
				$actarmcd_array = array_replace($actarmcd_array, array(array_search('VPK', $actarmcd_array) => 'TCN'));
				$actarm_array = array_replace($actarm_array, array(array_search('Viekira', $actarm_array) => 'Technivie'));
			}
		}
		if (!empty($actarm_array)) {
			ksort($actarm_array);
			ksort($actarmcd_array);
			$actarm = implode('/', array_unique($actarm_array));
			//$actarm = strpos($actarm, 'Viekira/Dasabuvir') !== false ? str_replace('Viekira/Dasabuvir', 'Viekira Pak', $actarm) : $actarm;
			$actarmcd = implode('/', array_unique($actarmcd_array));
			//$actarmcd = strpos($actarmcd, 'VPK/DBV') !== false ? str_replace('VPK/DBV', 'VPK', $actarmcd) : $actarmcd;
			/**
			 * undocumented in JMP Clinical:
			 * if subject ARM or ARMCD are missing, the subject will be SCREEN FAIL.
			 * Yeah. Sweeet.
			 */
			$arm = !isset($arm) ? $actarm : $arm;
			$armcd = !isset($armcd) ? $actarmcd : $armcd;
		} else {
			if ($dsterm == 'SCREEN_FAILURE') {
				$actarm = fix_case($dsterm);
				$actarmcd = 'SCRNFAIL';
			} elseif ($txstat == 'N') {
				$actarm = 'Not Treated';
				$actarmcd = 'NOTTRT';
			}
		}
		$return_array['arm'] = $arm;
		$return_array['armcd'] = $armcd;
		$return_array['actarm'] = $actarm;
		$return_array['actarmcd'] = $actarmcd;
		return $return_array;
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

}