<?php

/**
 * Created by PhpStorm.
 * User: kenbergquist
 * Date: 5/17/16
 * Time: 2:32 PM
 */
class HCVT
{

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
		$fields = array_merge($dm_array, $tx_array, $endt_fields, array('trt_suppcm_txstat'));
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
			 * dependent on TX start
			 */
			if (isset($tx_start_date)) {
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
					$tx_start_year = substr($tx_start_date, 0, 4);
					$age_at_start = ($tx_start_year - $birth_year) > 0 ? $tx_start_year - $birth_year : null;
				}
				update_field_compare($subject_id, $project_id, $baseline_event_id, $age_at_start, $subject[$baseline_event_id]['age_suppvs_age'], 'age_suppvs_age', $debug);
				/**
				 * Date of last dose of HCV treatment or Treatment stop date
				 * dis_suppfa_txendt
				 */
				$stack = array();
				if (array_search_recursive('ONGOING', $end_values) === false) {
					foreach ($tx_prefixes AS $endt_prefix) {
						foreach ($end_values AS $event) {
							if ($event[$endt_prefix . '_cmendtc'] != '' && ($event[$endt_prefix . '_suppcm_cmtrtout'] == 'COMPLETE') || $event[$endt_prefix . '_suppcm_cmtrtout'] == 'PREMATURELY_DISCONTINUED') {
								$stack[] = $event[$endt_prefix . '_cmendtc'];
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
			$regimen = self::getRegimen($regimen_data[$subject_id], $subject[$baseline_event_id]['eot_dsterm'], $txstat);
			update_field_compare($subject_id, $project_id, $baseline_event_id, $regimen['actarm'], $subject[$baseline_event_id]['dm_actarm'], 'dm_actarm', $debug);
			update_field_compare($subject_id, $project_id, $baseline_event_id, $regimen['actarmcd'], $subject[$baseline_event_id]['dm_actarmcd'], 'dm_actarmcd', $debug);
			/**
			 * treatment started flag
			 */
			update_field_compare($subject_id, $project_id, $baseline_event_id, $txstat, $subject[$baseline_event_id]['trt_suppcm_txstat'], 'trt_suppcm_txstat', $debug);
		}
	}

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
	 * @return string $outcome
	 *
	 * Use this version of the outcome engine in HCVT 2.0+
	 * It takes into account all treatment for start/stop
	 */
	public static function get_outcome()
	{
		global $started_tx, $stopped_tx, $post_tx_plus10w_scores, $last_hcvrna_bloq, $lost_to_followup, $tx_stopped_10_wks_ago, $hcv_fu_eligible;
		/**
		 * analyze post-$svr_class-th week outcomes
		 */
		if ($started_tx) { // Is the patient’s TX start date recorded
			if ($stopped_tx) { // Is the patient’s TX stop date recorded
				if (count($post_tx_plus10w_scores) > 0) { // Does the patient have HCV RNA result at 10 weeks or later post treatment
					if (get_end_of_array($post_tx_plus10w_scores) == '0') { // Is the last HCV RNA at 10 weeks or later post-treatment BLOQ?
						$outcome = 'SVR';
					} else {
						$outcome = self::get_failure();
					}
				} else {
					if ($last_hcvrna_bloq) { // Was last recorded HCV RNA BLOQ?
						if ($lost_to_followup || !$hcv_fu_eligible) { // Was the patient lost to follow-up?
							/*$outcome = get_failure();*/
							$outcome = 'LOST TO FOLLOWUP';
						} else { // not lost to followup
							if ($tx_stopped_10_wks_ago) {
								$outcome = 'QUERY HCVRNA';
							} else {
								$outcome = 'STATUS PENDING';
							}
						}
					} else {
						$outcome = self::get_failure();
					}
				}
			} else {
				$outcome = 'QUERY TX STOP';
			}
		} else {
			$outcome = 'QUERY TX START';
		}
		return $outcome;
	}

	/**
	 * @return string
	 */
	public static function get_failure()
	{
		global $on_tx_scores, $post_tx_plus10d_scores, $post_tx_scores, $eot_dsterm, $lost_to_followup, $hcv_fu_eligible;
		if (count($on_tx_scores) > 0 && in_array('0', $on_tx_scores)) { // Was BLOQ recorded at least once during treatment?
			if (get_end_of_array($on_tx_scores) == '1') { // After BLOQ and while still on TX, was LAST HCV RNA Quantified?
				$outcome = 'VIRAL BREAKTHROUGH';
			} else {
				$outcome = 'RELAPSE';
			}
		} else {
			if (count($post_tx_scores) > 0) { //Does the patient have ANY post-treatment HCV RNA?
				if (count($post_tx_plus10d_scores) > 0 && in_array('0', $post_tx_plus10d_scores)) { // Was BLOQ recorded at least once before EOT+10 days or no 10-day scores?
					if (get_end_of_array($on_tx_scores) == '1' || get_end_of_array($post_tx_plus10d_scores) == '1') { // While still on TX or EOT +10 days, was HCV RNA Quantified?
						$outcome = 'VIRAL BREAKTHROUGH';
					} else {
						if (count($on_tx_scores) > 0 || (count($on_tx_scores) == 0 && in_array('0', $post_tx_scores))) { // does subject have on-treatment HCVRNA, or no on-treatment HCVRNA with BLOQ after treatment
							$outcome = 'RELAPSE';
						} elseif (count($on_tx_scores) == 0 && !in_array('0', $post_tx_scores)) {
							$outcome = 'NON-RESPONDER';
						}
					}
				} else {
					$outcome = 'NON-RESPONDER';
				}
			} else {
				if (in_array($eot_dsterm, array('LACK_OF_EFFICACY'))) {
					$outcome = 'NON-RESPONDER';
				} else {
					if ($lost_to_followup || !$hcv_fu_eligible) {
						$outcome = 'LOST TO FOLLOWUP';
					} else {
						$outcome = 'QUERY HCVRNA';
					}
				}
			}
		}
		return $outcome;
	}

	/**
	 * @param $record
	 * @param $debug
	 */
	public static function set_hcvrna_outcome($record, $debug)
	{
		global $Proj, $project_id, $ie_criteria_labels;
		$enable_kint = $debug && (isset($record) && $record != '') ? true : false;
		Kint::enabled($enable_kint);
		$baseline_event_id = $Proj->firstEventId;
		$fieldsets = array(
			'abstracted' => array(
				array('date_field' => 'hcv_lbdtc'),
				array('value_field' => 'hcv_lbstresn'),
				array('detect_field' => 'hcv_supplb_hcvdtct')
			),
			'imported' => array(
				array('date_field' => 'hcv_im_lbdtc'),
				array('value_field' => 'hcv_im_lbstresn'),
				array('detect_field' => 'hcv_im_supplb_hcvdtct'),
				array('trust' => 'hcv_im_nxtrust')
			)
		);
		$data = array();
		$field_translate = array();
		$reverse_translate = array();
		foreach ($fieldsets as $formtype => $fieldset) {
			$filter_logic = $formtype == 'abstracted' ? "[hcv_lbdtc] != ''" : "[hcv_im_lbdtc] != '' AND [hcv_im_nxtrust] != 'N'";
			$fields = array();
			foreach ($fieldset AS $field) {
				foreach ($field as $key => $value) {
					$fields[] = $value;
					$field_translate[$formtype][$key] = $value;
					$reverse_translate[$formtype][$value] = $key;
				}
			}
			$data[$formtype] = REDCap::getData('array', $record, $fields, null, null, false, false, false, $filter_logic);
		}
		/**
		 * Main
		 */
		$ie_fields = array('ie_ietestcd');
		$ie_data = REDCap::getData('array', $record, $ie_fields);
		$date_fields = array('dm_usubjid', 'dm_rfstdtc', 'dis_suppfa_txendt', 'eot_dsterm', 'dis_dsstdy', 'hcv_suppfa_fuelgbl', 'hcv_suppfa_nlgblrsn', 'hcv_suppfa_hcvout', 'hcv_suppfa_wk10rna', 'hcv_suppfa_lastbloq', 'dis_suppds_funcmprsn', 'hcv_suppfa_fudue', 'dm_suppdm_hcvt2id', 'dm_actarmcd', 'dm_suppdm_rtrtsdtc');
		$date_data = REDCap::getData('array', $record, $date_fields, $baseline_event_id);

		foreach ($date_data AS $subject_id => $subject) {
			$all_events = array();
			$post_tx_dates = array();
			$post_tx_bloq_dates = array();
			$re_treat_candidate = false;
			$re_treat_dates = array();
			foreach ($subject AS $date_event_id => $date_event) {
				/**
				 * HCV RNA Outcome
				 */
				$hcvrna_improved = false;
				$on_tx_scores = array();
				$hcvrna_previous_score = '';
				$post_tx_scores = array();
				$post_tx_plus10w_scores = array();
				$post_tx_plus10d_scores = array();
				$last_hcvrna_bloq = false;
				$stop_date_plus_10w = null;
				$stop_date_plus_10d = null;
				$tx_stopped_10_wks_ago = false;
				$started_tx = false;
				$stopped_tx = false;
				$hcv_fu_eligible = true;
				$hcv_fu_ineligible_reason = array();
				$lost_to_followup = false;
				$hcv_data_due = false;
				$tx_start_date = isset($date_event['dm_rfstdtc']) && $date_event['dm_rfstdtc'] != '' ? $date_event['dm_rfstdtc'] : null;
				$stop_date = isset($date_event['dis_suppfa_txendt']) && $date_event['dis_suppfa_txendt'] != '' ? $date_event['dis_suppfa_txendt'] : null;
				$dis_dsstdy = isset($date_event['dis_dsstdy']) && $date_event['dis_dsstdy'] != '' ? $date_event['dis_dsstdy'] : null;
				$eot_dsterm = isset($date_event['eot_dsterm']) && $date_event['eot_dsterm'] != '' ? $date_event['eot_dsterm'] : null;
				/**
				 * look for this dm_usubjid in dm_suppdm_hcvt2id. This is a foreign key between TARGET 2 and TARGET 3 patients.
				 * Get the start date of the TARGET 3 patient if dm_suppdm_hcvt2id is not empty.
				 */
				$t3_fk_result = db_query("SELECT record FROM redcap_data WHERE project_id = '$project_id' AND field_name = 'dm_suppdm_hcvt2id' AND value = '{$date_event['dm_usubjid']}'");
				if ($t3_fk_result) {
					$t3_fk = db_fetch_assoc($t3_fk_result);
					$t3_start_date_value = get_single_field($t3_fk['record'], $project_id, $baseline_event_id, 'dm_rfstdtc', '');
				}
				$t3_start_date = isset($t3_start_date_value) ? $t3_start_date_value : '';
				/**
				 * where are we in treatment?
				 */
				if (isset($tx_start_date)) { // started treatment
					$started_tx = true;
					/**
					 * treatment must have started to stop
					 */
					if (isset($stop_date)) { // completed treatment
						$stopped_tx = true;
						$stop_date_plus_10d = add_date($stop_date, 10, 0, 0);
						$stop_date_plus_10w = add_date($stop_date, 64, 0, 0);
						if (date("Y-m-d") >= $stop_date_plus_10w && isset($stop_date_plus_10w)) {
							$tx_stopped_10_wks_ago = true;
						}
					} else { // not completed treatment
						$stopped_tx = false;
						$hcv_fu_eligible = false;
						$hcv_fu_ineligible_reason[] = 'TX Not Completed';
					}
				} else { // not started treatment
					$started_tx = false;
					$hcv_fu_eligible = false;
					$hcv_fu_ineligible_reason[] = 'TX Not Started';
				}
				/**
				 * get fields for both abstracted (standardized) and imported HCV RNA forms
				 */
				foreach ($fieldsets as $formtype => $fieldset) {
					foreach ($data[$formtype][$subject_id] as $event_id => $event) {
						/**
						 * standardize array keys
						 */
						foreach ($event AS $event_key => $event_value) {
							unset($event[$event_key]);
							$event[array_search($event_key, $field_translate[$formtype])] = $event_value;
						}
						/**
						 * merge into all_events array
						 */
						if ($event['date_field'] != '') {
							$all_events[$event['date_field']][] = $event;
						}
					}
				}
				ksort($all_events);
				/**
				 * get outcomes
				 */
				foreach ($all_events AS $event_date => $event_set) {
					foreach ($event_set as $event) {
						/**
						 * if we have a date, and the HCV RNA isn't an 'untrusted blip'...
						 * (blips are sudden, small increases in viral load following EOT)
						 */
						if (($event['value_field'] != '' || $event['detect_field'] != '') && (($event['date_field'] != '' && $t3_start_date == '') || ($event['date_field'] != '' && $t3_start_date != '' && $event['date_field'] <= $t3_start_date))) {
							$is_bloq = (in_array($event['detect_field'], array('BLOQ', 'NOT_SPECIFIED', 'DETECTED')) || $event['value_field'] == '0') ? true : false;
							$score = $is_bloq ? '0' : '1';
							/**
							 * if treatment has started, and $event['date_field'] is after start date (is baseline or later)
							 */
							if ($started_tx && $tx_start_date <= $event['date_field']) {
								/**
								 * and is...
								 */
								if (!$stopped_tx || ($stopped_tx && $event['date_field'] <= $stop_date)) { // on treatment
									$on_tx_scores[] = $score;
									if ($score >= $hcvrna_previous_score) {
										$hcvrna_improved = false;
									} elseif ($score < $hcvrna_previous_score) {
										$hcvrna_improved = true;
									}
									$hcvrna_previous_score = $score;
									if ($eot_dsterm == 'LACK_OF_EFFICACY' && get_end_of_array($on_tx_scores) == '1') {
										$re_treat_candidate = true;
									}
								} else { // post-treatment
									/**
									 * RE-TREAT handling
									 * If this HCVRNA is quantifiable, add the date to an array
									 * if this HCVRNA is bloq and we have quantified post-TX HCVRNA, it's a re-treat and we don't want it in $post_tx_scores
									 */
									if ($is_bloq && !in_array('1', $post_tx_scores) && !$re_treat_candidate) {
										$post_tx_bloq_dates[] = $event['date_field'];
										$post_tx_scores[] = $score;
										/**
										 * capture scores that are after EOT plus 10 weeks
										 */
										if (isset($stop_date_plus_10w) && $event['date_field'] >= $stop_date_plus_10w) {
											$post_tx_plus10w_scores[] = $score;
										}
										/**
										 * capture scores that are between EOT and EOT plus 10 days
										 */
										if (isset($stop_date_plus_10d) && $event['date_field'] <= $stop_date_plus_10d) {
											$post_tx_plus10d_scores[] = $score;
										}
									}
									if (!$is_bloq && !in_array('1', $post_tx_scores) && !$re_treat_candidate) {
										$post_tx_dates[] = $event['date_field'];
										$post_tx_scores[] = $score;
										/**
										 * capture scores that are after EOT plus 10 weeks
										 */
										if (isset($stop_date_plus_10w) && $event['date_field'] >= $stop_date_plus_10w) {
											$post_tx_plus10w_scores[] = $score;
										}
										/**
										 * capture scores that are between EOT and EOT plus 10 days
										 */
										if (isset($stop_date_plus_10d) && $event['date_field'] <= $stop_date_plus_10d) {
											$post_tx_plus10d_scores[] = $score;
										}
									}
									if ($is_bloq && in_array('1', $post_tx_scores)) {
										$re_treat_candidate = true;
									}
								}
							}
						}
					}
				}
				/**
				 * we have all our score candidates
				 */
				$all_scores = array_merge($on_tx_scores, $post_tx_scores);
				$last_hcvrna_bloq = count($all_scores) > 0 && get_end_of_array($all_scores) == '0' ? true : false;
				/**
				 * get candidates for re-treat cutoff date
				 */
				$re_treat_dates = array_diff(array_unique($post_tx_dates), array_unique($post_tx_bloq_dates));
				/**
				 * HCVRNA Followup Eligibility
				 * subjects are ineligible for followup if:
				 */
				foreach ($ie_data[$subject_id] as $ie_event) {
					if ($ie_event['ie_ietestcd'] != '') { // failed i/e criteria
						$hcv_fu_eligible = false;
						$hcv_fu_ineligible_reason[] = $ie_criteria_labels[$ie_event['ie_ietestcd']];
					}
				}
				/**
				 * disposition-related ineligibility
				 */
				if (in_array($date_event['eot_dsterm'], array('LOST_TO_FOLLOWUP', 'LACK_OF_EFFICACY'))) { // disposition is lost to followup
					$lost_to_followup = true;
					$hcv_fu_eligible = false;
					$hcv_fu_ineligible_reason[] = fix_case($date_event['eot_dsterm']);
				}
				/**
				 * Quantified HCVRNA after EOT
				 */
				if (count($post_tx_scores) > 1 && !$hcvrna_improved) {
					if (in_array('1', $post_tx_scores)) { // had quantified HCV RNA after EOT
						$hcv_fu_eligible = false;
						$hcv_fu_ineligible_reason[] = 'Quantified post-TX HCVRNA';
					}
				} else {
					if (in_array('1', $post_tx_scores)) { // had quantified HCV RNA after EOT
						$hcv_fu_eligible = false;
						$hcv_fu_ineligible_reason[] = 'Quantified post-TX HCVRNA';
					}
				}
				/**
				 * lost to post-treatment follow up if not already LTFU
				 */
				$post_tx_followup_eligible = $date_event['dis_suppds_funcmprsn'] == 'LOST_TO_FOLLOWUP' ? false : true;
				if (!$lost_to_followup) {
					if (!$post_tx_followup_eligible) {
						$lost_to_followup = true;
						$hcv_fu_eligible = false;
						$hcv_fu_ineligible_reason[] = 'Lost to post-TX followup';
					}
				}
				/**
				 * derive outcome now as it's needed below
				 */
				$GLOBALS['started_tx'] = $started_tx;
				$GLOBALS['stopped_tx'] = $stopped_tx;
				$GLOBALS['post_tx_plus10w_scores'] = $post_tx_plus10w_scores;
				$GLOBALS['last_hcvrna_bloq'] = $last_hcvrna_bloq;
				$GLOBALS['lost_to_followup'] = $lost_to_followup;
				$GLOBALS['tx_stopped_10_wks_ago'] = $tx_stopped_10_wks_ago;
				$GLOBALS['hcv_fu_eligible'] = $hcv_fu_eligible;
				$GLOBALS['on_tx_scores'] = $on_tx_scores;
				$GLOBALS['post_tx_plus10d_scores'] = $post_tx_plus10d_scores;
				$GLOBALS['post_tx_scores'] = $post_tx_scores;
				$GLOBALS['eot_dsterm'] = $eot_dsterm;
				$outcome = self::get_outcome();
				/**
				 * IS FOLLOWUP DATA FOR THIS SUBJECT DUE?
				 * if followup eligible and treatment duration greater than 4 weeks...
				 */
				if (($hcv_fu_eligible && $post_tx_followup_eligible) && isset($dis_dsstdy) && $dis_dsstdy >= 29) {
					/**
					 * AND today is TX stop date + 14 weeks ago, and no final outcome, data is due
					 */
					if (date("Y-m-d") >= (add_date($stop_date, 98, 0, 0)) && !in_array($outcome, array('SVR', 'VIRAL BREAKTHROUGH', 'RELAPSE', 'NON-RESPONDER', 'LOST TO FOLLOWUP'))) {
						$hcv_data_due = true;
					}
				}
				/**
				 * if not followup eligible (and no TX stop - implied by ineligible)...
				 */
				if ((!$hcv_fu_eligible || !$post_tx_followup_eligible) && $started_tx && !$stopped_tx) {
					/**
					 * is regimen SOF + RBV?
					 */
					$regimen = get_single_field($subject_id, $project_id, $baseline_event_id, 'dm_actarmcd', null);
//					$due_fields = array('sof_cmstdtc', 'rib_cmstdtc');
//					$due_data = REDCap::getData('array', $subject_id, $due_fields);
//					$sof_rbv_regimen = false;
//					$sof = array();
//					$rbv = array();
//					foreach ($due_data[$subject_id] AS $event_id => $event) {
//						if ($event['sof_cmstdtc'] != '') {
//							$sof[] = true;
//						}
//						if ($event['rib_cmstdtc'] != '') {
//							$rbv[] = true;
//						}
//					}
					$sof_rbv_regimen = $regimen == 'SOF/RBV' ? true : false;
					/**
					 * get genotype
					 */
					$genotype = get_single_field($subject_id, $project_id, $baseline_event_id, 'hcvgt_lborres', '');
					/**
					 * if regimen is SOF + RBV and Genotype 1 or 3
					 */
					if ($sof_rbv_regimen && ($genotype == '1' || $genotype == '3')) {
						/**
						 * AND if TX start is 168 days ago, data is due
						 */
						if (date("Y-m-d") >= (add_date($tx_start_date, 168, 0, 0))) {
							$hcv_data_due = true;
						}
						/**
						 * if regimen is SOF + RBV and Genotype 2
						 */
					} elseif ($sof_rbv_regimen && ($genotype == '2')) {
						/**
						 * if TX start is 84 days ago, data is due
						 */
						if (date("Y-m-d") >= (add_date($tx_start_date, 84, 0, 0))) {
							$hcv_data_due = true;
						}
						/**
						 * if any other regimen or genotype
						 */
					} else {
						/**
						 * if TX start is 84 days ago, data is due
						 */
						if (date("Y-m-d") >= (add_date($tx_start_date, 84, 0, 0))) {
							$hcv_data_due = true;
						}
					}
				}
				/**
				 * get values
				 */
				$last_bloq = $last_hcvrna_bloq ? 'Y' : 'N';
				$eligible = !$hcv_fu_eligible ? 'N' : 'Y';
				$reason = implode('; ', array_unique($hcv_fu_ineligible_reason));
				$data_due = $hcv_data_due ? 'Y' : 'N';
				$wk10_rna = count($post_tx_plus10w_scores) > 0 ? 'Y' : 'N';
				rsort($re_treat_dates);
				$re_treat_date = $re_treat_candidate ? get_end_of_array($re_treat_dates) : null;
				/**
				 * debug
				 */
				d($all_scores);
				if ($started_tx) {
					d($tx_start_date);
					d($on_tx_scores);
					if ($stopped_tx) {
						d($stop_date);
						d($post_tx_scores);
						d($post_tx_plus10d_scores);
						d($post_tx_plus10w_scores);
						d($last_hcvrna_bloq);
						d($lost_to_followup);
						d($post_tx_followup_eligible);
						d($hcv_fu_eligible);
						d($post_tx_bloq_dates);
						d($post_tx_dates);
						d($t3_start_date);
						d($re_treat_candidate);
						d($re_treat_date);
						d($tx_stopped_10_wks_ago);
						d($hcv_data_due);
						d($outcome);
					} else {
						d('NO TX STOP');
					}
				} else {
					d('NO TX START');
				}
				/**
				 * set overall hcvrna followup eligibility and reason if ineligible
				 */
				update_field_compare($subject_id, $project_id, $baseline_event_id, $eligible, $date_event['hcv_suppfa_fuelgbl'], 'hcv_suppfa_fuelgbl', $debug);
				update_field_compare($subject_id, $project_id, $baseline_event_id, $reason, $date_event['hcv_suppfa_nlgblrsn'], 'hcv_suppfa_nlgblrsn', $debug);
				/**
				 * set follow up timing - is it due?
				 */
				update_field_compare($subject_id, $project_id, $baseline_event_id, $data_due, $date_event['hcv_suppfa_fudue'], 'hcv_suppfa_fudue', $debug);
				/**
				 * set outcome
				 */
				update_field_compare($subject_id, $project_id, $baseline_event_id, $outcome, $date_event['hcv_suppfa_hcvout'], 'hcv_suppfa_hcvout', $debug);
				/**
				 * set 10 HCV RNA?
				 */
				update_field_compare($subject_id, $project_id, $baseline_event_id, $wk10_rna, $date_event['hcv_suppfa_wk10rna'], 'hcv_suppfa_wk10rna', $debug);
				/**
				 * set HCV RNA BLOQ?
				 */
				update_field_compare($subject_id, $project_id, $baseline_event_id, $last_bloq, $date_event['hcv_suppfa_lastbloq'], 'hcv_suppfa_lastbloq', $debug);
				/**
				 * set re-treat window start date
				 */
				update_field_compare($subject_id, $project_id, $baseline_event_id, $re_treat_date, $date_event['dm_suppdm_rtrtsdtc'], 'dm_suppdm_rtrtsdtc', $debug);
			}
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
			case 'interferon_administration':
			case 'ribavirin_administration':
			case 'telaprevir_administration':
			case 'boceprevir_administration':
			case 'simeprevir_administration':
			case 'sofosbuvir_administration':
			case 'daclatasvir_administration':
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
			case 'hcc_treatment':
			case 'hcc_coding':
				$recode_llt = $instrument == 'hcc_treatment' ? true : false;
				$recode_pt = true;
				$recode_soc = true;
				/**
				 * AE_AEDECOD
				 */
				$fields = array("hcctrt_prtrt", "hcctrt_oth_prtrt", "hcctrt_prmodify");
				$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
				code_llt($project_id, $record, $this_event_id, fix_case($data[$record][$this_event_id]['hcctrt_prtrt']), fix_case($data[$record][$this_event_id]['hcctrt_oth_prtrt']), $data[$record][$this_event_id]['hcctrt_prmodify'], 'hcctrt_prmodify', $debug, $recode_llt);
				if ($debug) {
					error_log("DEBUG: Coded PRMODIFY {$data[$record][$this_event_id]['hcctrt_prtrt']}: subject=$record, event=$this_event_id for PR {$data[$record][$this_event_id]['hcctrt_prtrt']} - {$data[$record][$this_event_id]['hcctrt_oth_prtrt']}");
				}
				/**
				 * AE_AEDECOD
				 */
				$fields = array("hcctrt_prmodify", "hcctrt_prdecod");
				$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
				code_pt($project_id, $record, $this_event_id, fix_case($data[$record][$this_event_id]['hcctrt_prmodify']), $data[$record][$this_event_id]['hcctrt_prdecod'], 'hcctrt_prdecod', $debug, $recode_pt);
				if ($debug) {
					error_log("DEBUG: Coded PRDECOD {$data[$record][$this_event_id]['hcctrt_prdecod']}: subject=$record, event=$this_event_id for PR {$data[$record][$this_event_id]['hcctrt_prmodify']}");
				}
				/**
				 * AE_AEBODSYS
				 */
				$fields = array("hcctrt_prdecod", "hcctrt_prbodsys");
				$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
				code_bodsys($project_id, $record, $this_event_id, $data[$record][$this_event_id]['hcctrt_prdecod'], $data[$record][$this_event_id]['hcctrt_prbodsys'], 'hcctrt_prbodsys', $debug, $recode_soc);
				if ($debug) {
					error_log("DEBUG: Coded SOC: subject=$record, event=$this_event_id for PR {$data[$record][$this_event_id]['hcctrt_prdecod']}");
				}
				unset($data);
				break;
			default:
				break;
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
}