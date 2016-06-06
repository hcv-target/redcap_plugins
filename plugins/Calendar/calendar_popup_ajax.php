<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

//Pick up any variables passed by Post
if (isset($_POST['pid']))  $_GET['pid']  = $_POST['pid'];

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

//Visit date might be passed in URL
if (isset($_GET['event_date'])) $event_date = trim($_GET['event_date']);
if ($isAjax) $event_date = DateTimeRC::format_ts_to_ymd($event_date);

// Validate $_GET['cal_id']
if (isset($_GET['cal_id']) && !is_numeric($_GET['cal_id'])) exit('ERROR!');

//Defaults
$msg = "";
$msg_saved_date   = "&nbsp;<span id='msg_saved_date' style='visibility:visible;font-size:11px;color:red;font-weight:bold;'>{$lang['global_39']}!</span>";
$msg_saved_time   = "&nbsp;<span id='msg_saved_time' style='visibility:visible;font-size:11px;color:red;font-weight:bold;'>{$lang['global_39']}!</span>";
$msg_saved_status = "&nbsp;<span id='msg_saved_status' style='visibility:visible;font-size:11px;color:red;font-weight:bold;'>{$lang['global_39']}!</span>";

//If action is provided in AJAX request, perform action.
if (isset($_REQUEST['action'])) {
	switch ($_REQUEST['action']) {	
		//Edit the visit date
		case "edit_date":
			$sql = "update redcap_events_calendar set event_date = '".prep($event_date)."' where cal_id = '".prep($_GET['cal_id'])."'";
			$q = db_query($sql);
			if (!$q) {
				exit("<b>{$lang['global_01']}!</b>");
			} else {
				$msg = $msg_saved_date;
				//LOGGING
				log_event($sql,"redcap_events_calendar","MANAGE",$_GET['cal_id'],calLogChange($_GET['cal_id']),"Edit calendar event");
			}
			break;
		//Edit the visit time
		case "edit_time":			
			//Add leading zero if missing one
			if (substr($_GET['event_time'],1,1) == ":") $_GET['event_time'] = "0".$_GET['event_time'];
			$sql = "update redcap_events_calendar set event_time = '".prep($_GET['event_time'])."' where cal_id = {$_GET['cal_id']}";
			$q = db_query($sql);
			if (!$q) {
				exit("<b>{$lang['global_01']}!</b>");
			} else {
				$msg = $msg_saved_time;
				//LOGGING
				log_event($sql,"redcap_events_calendar","MANAGE",$_GET['cal_id'],calLogChange($_GET['cal_id']),"Edit calendar event");
			}
			break;
		//Edit the visit status
		case "edit_status":
			$sql = "update redcap_events_calendar set event_status = '".prep($_GET['event_status'])."' where cal_id = {$_GET['cal_id']}";
			$q = db_query($sql);
			if (!$q) {
				exit("<b>{$lang['global_01']}!</b>");
			} else {
				$msg = $msg_saved_status;
				//LOGGING
				log_event($sql,"redcap_events_calendar","MANAGE",$_GET['cal_id'],calLogChange($_GET['cal_id']),"Edit calendar event");
			}
			break;
		//Edit notes
		case "edit_notes":
			$sql = "update redcap_events_calendar set notes = '".prep($_POST['notes'])."' where cal_id = {$_POST['cal_id']}";
			if (db_query($sql)) {
				//LOGGING
				log_event($sql,"redcap_events_calendar","MANAGE",$_POST['cal_id'],calLogChange($_POST['cal_id']),"Edit calendar event");
			}
			exit;
			break;
	}	
}





//DATE Field
if ($_GET['view'] == "date") {

	print  "<div id='change_date' style='display:block;'>
				<b>".DateTimeRC::format_ts_from_ymd($event_date)." (".DateTimeRC::getDay($event_date).")</b>&nbsp; ";
	// Dont' allow user to change date here if tied to an Event (need to change on Scheduling page where it might affect other scheduled dates)
	if ($row['event_id'] == "") {
		print  "<a href='javascript:;' style='text-decoration:underline;font-size:11px;' onclick=\"$('#change_date').css({'display':'none'});$('#save_date').css({'display':'block'});\">{$lang['calendar_popup_ajax_03']}</a>";
	}
	print  "$msg
			</div>
			<div id='save_date' style='display:none;position:relative;'>
				<input type='text' id='newdate' name='newdate' onblur=\"redcap_validate(this,'','','hard','date_'+user_date_format_validation,1,1,user_date_format_delimiter);\" value='".DateTimeRC::format_ts_from_ymd($event_date)."' class='x-form-text x-form-field' style='width:70px;' maxlength='10'><span class='df'>(".DateTimeRC::get_user_format_label().")</span>
				&nbsp;&nbsp;
				<input type='button' id='savebtndatecalpopup' style='font-size:11px;' value='".cleanHtml($lang['calendar_popup_ajax_04'])."' onclick='saveDateCalPopup({$_GET['cal_id']})'> &nbsp;
				<input type='button' style='font-size:11px;' value='".cleanHtml($lang['global_53'])."' onclick=\"$('#change_date').css({'display':'block'});$('#save_date').css({'display':'none'});\">
			</div>";



//TIME Field			
} elseif ($_GET['view'] == "time") {
	
	$time_field =  "<input type='text' class='x-form-text x-form-field time' id='event_time' name='event_time' value='".remBr(cleanHtml($_GET['event_time']))."' maxlength='5' style='width:50px;' onblur=\"redcap_validate(this,'','','soft_typed','time')\"> 
					<span style='font-size:10px;color:#777;font-family:tahoma;'>HH:MM</span> &nbsp; 
					<input type='button' id='savebtntimecalpopup' style='font-size:11px;' value='{$lang['calendar_popup_ajax_06']}' onclick='saveTimeCalPopup({$_GET['cal_id']})'>";

	//Visit Time
	if ($_GET['event_time'] == "") {
		$visible = $time_field;
		$hidden  = "";
	} else {
		$visible = "<b>".DateTimeRC::format_ts_from_ymd($_GET['event_time'])."</b>&nbsp; 
					<a href='javascript:;' style='text-decoration:underline;font-size:11px;' onclick=\"$('#change_time').css({'display':'none'});$('#save_time').css({'display':'block'});\">{$lang['calendar_popup_ajax_07']}</a>";
		$hidden  = $time_field
				 . " &nbsp;
					<input type='button' style='font-size:11px;' value='{$lang['global_53']}' onclick=\"$('#change_time').css({'display':'block'});$('#save_time').css({'display':'none'});\">";
	}	
	
	print  "<div id='change_time' style='display:block;'>
				$visible
				$msg
			</div>
			<div id='save_time' style='display:none;'>
				$hidden
			</div>";




//STATUS Field			
} elseif ($_GET['view'] == "status") {	
	
	//Set display text for visit status
	switch ($_GET['event_status']) {
		case 0: $status = "<img src='".APP_PATH_IMAGES."star_empty.png' style='position:relative;top:1px;'> <b style='color:#777;'>{$lang['calendar_popup_ajax_08']}</b>";	break;
		case 1: $status = "<img src='".APP_PATH_IMAGES."star.png' style='position:relative;top:1px;'> <b style='color:#A86700;'>{$lang['calendar_popup_ajax_09']}</b>";	break;
		case 2: $status = "<img src='".APP_PATH_IMAGES."tick.png' style='position:relative;top:1px;'> <b style='color:green;'>{$lang['calendar_popup_ajax_10']}</b>";	break;
		case 3: $status = "<img src='".APP_PATH_IMAGES."cross.png' class='imgfix'> <b style='color:red;'>{$lang['calendar_popup_ajax_11']}</b>";	break;
		case 4: $status = "<img src='".APP_PATH_IMAGES."delete.png' class='imgfix'> <b style='color:#800000;'>{$lang['calendar_popup_ajax_12']}</b>";	break;
	}
	
	print  "<div id='change_status'>
				$status &nbsp; 
				<a href='javascript:;' style='text-decoration:underline;font-size:11px;' onclick=\"$('#change_status').css({'display':'none'});$('#save_status').css({'display':'block'});\">{$lang['calendar_popup_ajax_13']}</a>
				$msg
			</div>
			<div id='save_status' style='display:none;'>
				<select id='event_status' class='x-form-text x-form-field' style='height:22px;padding-right:0;'>
					<option value='0' "; if ($_GET['event_status'] == 0) {print "selected";} print "> {$lang['calendar_popup_ajax_08']} </option>
					<option value='1' "; if ($_GET['event_status'] == 1) {print "selected";} print "> {$lang['calendar_popup_ajax_09']} </option>
					<option value='2' "; if ($_GET['event_status'] == 2) {print "selected";} print "> {$lang['calendar_popup_ajax_10']} </option>
					<option value='3' "; if ($_GET['event_status'] == 3) {print "selected";} print "> {$lang['calendar_popup_ajax_11']} </option>
					<option value='4' "; if ($_GET['event_status'] == 4) {print "selected";} print "> {$lang['calendar_popup_ajax_12']} </option>
				</select> &nbsp;
				<input type='button' id='savebtnstatuscalpopup' style='font-size:11px;' value='{$lang['calendar_popup_ajax_14']}' onclick='saveStatusCalPopup({$_GET['cal_id']})'> &nbsp;
				<input type='button' style='font-size:11px;' value='{$lang['global_53']}' onclick=\"$('#change_status').css({'display':'block'});$('#save_status').css({'display':'none'});\">
			</div>";

}
