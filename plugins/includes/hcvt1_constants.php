<?php
/**
 * Created by HCV-TARGET.
 * User: kenbergquist
 * Date: 6/17/15
 * Time: 10:25 AM
 */
$genotype_labels = array('1' => 'a', '2' => 'b', '3' => 'c', '4' => 'd', '5' => 'e', '6' => ' n/a');
$daa_labels = array('0' => 'Boceprevir', '1' => 'Telaprevir');
$daa_dosing_labels = array('0' => '2x/Day', '1' => '3x/Day');
$cirrhosis_labels = array('0' => 'No', '1' => 'Yes');
$gender_labels = array('0' => 'Female', '1' => 'Male');
$race_labels = array('0' => 'African/American', '1' => 'White', '2' => 'Asian', '3' => 'American Indian/Alaskan', '4' => 'Hawaiian/Pacific');
$ethnicity_labels = array('0' => 'Hispanic', '1' => 'Non-hispanic');
$hcv_detect_labels = array('0' => 'Not Specified', '1' => 'BLOD', '2' => 'BLOQ');
$prior_treatment_labels = array('0' => 'Naive', '1' => 'Experienced');
$prior_treatment_experience_labels = array('0' => 'Unknown', '1' => 'Null', '2' => 'Partial', '3' => 'Relapser', '5' => 'Breakthrough', '4' => 'Intolerant');
$ifn_status_labels = array('0' => 'Ongoing', '1' => 'Completed', '2' => 'Discontinued', '3' => 'Dose Modified/Interrupted');
$ifn_discon_labels = array('1' => 'Adverse Event', '2' => 'Efficacy Failure', '3' => 'Other');
$eot_status_labels = array('1' => 'Adverse Event', '2' => 'Efficacy Failure', '3' => 'Withdrew Consent', '4' => 'Lost to FU', '5' => 'Other');
$eot_outcome_labels = array('1' => 'Adverse Event', '2' => 'Efficacy Failure', '3' => 'Withdrew Consent', '4' => 'Lost to FU', '5' => 'Other');
$yn_labels = array('0' => 'No', '1' => 'Yes');
$complete_labels = array('0' => 'Incomplete', '1' => 'Unverified', '2' => 'Complete');
$outcome_labels = array('0' => 'SVR', '1' => 'Viral Breakthrough', '2' => 'Relapse', '3' => 'Efficacy Failure');
$eot_fu_incomplete_labels = array('1' => 'Lost to Post-treatment FU', '2' => 'SVR data is pending');
$patient_edu_labels = array('1' => 'Individual by Provider', '2' => 'Group by Provider', '3' => 'Pharma', '4' => 'Specialty Pharmacy', '5' => 'Other');
$il28b_labels = array('1' => 'CC', '2' => 'CT', '3' => 'TT');
$ifn_pharmacy_labels = array('0' => 'Unknown / Information not available', '1' => 'Retail pharmacy', '2' => 'Specialty pharmacy', '3' => 'Obtained through a drug assistance program', '4' => 'Other');
$rib_pharmacy_labels = array('1' => 'Unknown / Information not available', '2' => 'Retail pharmacy', '3' => 'Specialty pharmacy', '4' => 'Obtained through a drug assistance program', '5' => 'Other');
$daa_pharmacy_labels = array('0' => 'Not applicable', '1' => 'Unknown / Information not available', '2' => 'Retail pharmacy', '3' => 'Specialty pharmacy', '4' => 'Obtained through a drug assistance program', '5' => 'Other');
$insurance_labels = array('0' => 'No insurance', '1' => 'Medicaid', '2' => 'Medicare', '3' => 'Commercial insurance', '4' => 'Veteran Administration Health Care', '5' => 'Other');
$prior_discon_labels = array('0' => 'Systemic symptoms', '1' => 'Anemia', '2' => 'Neutropenia', '3' => 'Neuropsychological conditions', '4' => 'Patient choice', '5' => 'Unknown', '6' => 'Other');
$fibrosis_scale_labels = array('1' => 'Metavir', '2' => 'Ishak (modified Knodell)', '3' => 'Batts-Ludwig', '4' => 'Scheuer', '5' => 'Unknown');
$edg_labels = array('0' => 'No', '1' => 'Yes', '2' => 'No EGD on file');
$height_unit_labels = array('0' => 'cm', '1' => 'IN');
$weight_unit_labels = array('0' => 'kg', '1' => 'LB');
$fibrosis_labels = array('0' => 'No', '1' => 'Yes', '3' => 'Not Done');
$daa_not_started_labels = array('0' => 'Currently on lead-in and awaiting DAA start', '1' => 'Peg/RBV treatment discontinued prior to DAA start', '2' => 'Never started any portion of the HCV regimen');
$hcv_not_started_labels = array('0' => 'Awaiting Insurance approval/authorization denied', '1' => 'Patient changed mind about HCV treatment', '2' => 'Patient\'s condition changed making them ineligible for current HCV treatment', '3' => 'Other');