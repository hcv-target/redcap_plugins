<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . "/redcap_connect.php";
require_once APP_PATH_DOCROOT . 'ProjectGeneral/form_renderer_functions.php';

/**
 * PAGE HEADER
 */
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8">
	<title>Calendar Event</title>
	<meta name="googlebot" content="noindex, noarchive, nofollow, nosnippet">
	<meta name="robots" content="noindex, noarchive, nofollow">
	<meta name="slurp" content="noindex, noarchive, nofollow, noodp, noydir">
	<meta name="msnbot" content="noindex, noarchive, nofollow, noodp">
	<meta http-equiv="Cache-Control" content="no-cache">
	<meta http-equiv="Pragma" content="no-cache">
	<meta http-equiv="expires" content="0">
	<?php if ($isIE) { ?><meta http-equiv="X-UA-Compatible" content="IE=edge"><?php } ?>
	<link rel="shortcut icon" href="<?php echo APP_PATH_IMAGES ?>favicon.ico" type="image/x-icon">
	<link rel="apple-touch-icon-precomposed" href="<?php echo APP_PATH_IMAGES ?>apple-touch-icon.png">
	<link rel="stylesheet" type="text/css" href="<?php echo APP_PATH_CSS ?>smoothness/jquery-ui-<?php echo JQUERYUI_VERSION ?>.custom.css" media="screen,print">
	<link rel="stylesheet" type="text/css" href="<?php echo APP_PATH_CSS ?>style.css" media="screen,print">
	<script type="text/javascript" src="<?php echo APP_PATH_JS ?>base.js"></script>
</head>
<body style="background-color:#E8EEF7;">
<div id="bodydiv" style="border:1px solid #C2CFF1;padding:15px;background-color:#FFFFFF;position:relative;">		
<div style="text-align:right;position:relative;top:-15px;left:10px;">
	<a href="javascript:self.close();" style="font-family:Arial;font-size:12px;color:#888;text-decoration:none;"><?php echo $lang["calendar_popup_01"] ?> <img src="<?php echo APP_PATH_IMAGES ?>delete_box.gif" class="imgfix"></a>
</div>
<?php


/**
 * SET GLOBAL JAVASCRIPT VARIABLES
 */
renderJsVars();
	
// Render divs holding javascript form-validation text (when error occurs), so they get translated on the page
renderValidationTextDivs();

// Do CSRF token check (using PHP with jQuery)
createCsrfToken();


/**
 * DISPLAY EXISTING CALENDAR EVENT INFO
 */
if (isset($_GET['cal_id']) && is_numeric($_GET['cal_id']) && empty($_POST)) 
{
	//Query to get info for displaying
	$q = db_query("select * from redcap_events_calendar where cal_id = {$_GET['cal_id']}");

	//Display page
	if (db_num_rows($q) > 0) {
		
		$row = db_fetch_assoc($q);
		
		//If this calendar event is associated with an Event, get Event information to display
		if ($row['event_id'] != "") {
			//Get arm and event names
			$sql = "select m.descrip, a.arm_num, a.arm_name from redcap_events_arms a, redcap_events_metadata m where a.project_id = $project_id "
				 . "and a.arm_id = m.arm_id and m.event_id = " . $row['event_id'];
			$q2 = db_query($sql);	
			//If we have an event_id but no descrip (query returns with nothing), then the event was removed. Display notice of removal to user.
			if (db_num_rows($q2) < 1) {
				$event_name = "<span style='font-weight:normal;color:#999;font-size:13px;'><i>".$lang['calendar_popup_02']."</i></span>";
			//Set event name to display to user
			} else {
				$row2 = db_fetch_assoc($q2);	
				$event_name = $row2['descrip'];
				//Get number of Arms (so we can display Arm# if more than one Arm exists)
				$num_arms = db_result(db_query("select count(1) from redcap_events_arms where project_id = $project_id"), 0);
				if ($num_arms > 1) {
					$arm = $row2['arm_num'];
					$event_name .= "&nbsp;&nbsp;<span style='color:gray;'>(".$lang['global_08']." $arm{$lang['colon']} {$row2['arm_name']})</span>";
				}
			}
		}
		
		print  "<div style='color:green;font-family:verdana;padding:5px;margin-bottom:10px;font-weight:bold;font-size:16px;border-bottom:1px solid #aaa;'>
				".$lang['calendar_popup_04']."</div>";
		
		print  "<TABLE cellpadding=0 cellspacing=0 style='position:relative;'><TR><TD valign='top' style='position:relative;padding-right:10px;'>";
		
		
		print  "<table style='font-family:Arial;font-size:14px;position:relative;' cellpadding='0' cellspacing='5'>";		
		// RECORD
		if ($row['record'] != "") {
			print  "
				<tr valign='middle'>
					<td>$table_pk_label{$lang['colon']} </td>
					<td style='padding:5px 0 5px 0;'>
						<b>".removeDDEending($row['record'])."</b> ";
			print Records::getCustomRecordLabelsSecondaryFieldAllRecords($row['record']);
			if ($scheduling && $longitudinal) {
				print  "&nbsp; <a href='javascript:;' style='text-decoration:underline;font-size:11px;' onclick=\"
							window.opener.location.href = '".APP_PATH_WEBROOT."Calendar/scheduling.php?pid=$project_id&record=".removeDDEending($row['record']).($num_arms > 1 ? "&arm=$arm" : "")."';
							self.close();
						\">".$lang['calendar_popup_05']."</a>";
			}
			print  "</td>
				</tr>";
		}
		// GROUP_ID (if exists)
		if ($row['group_id'] != "") 
		{
			print  "
				<tr valign='middle'>
					<td style='font-size:11px;'>{$lang['calendar_popup_31']} </td>
					<td style='padding:5px 0 5px 0;'>
						<b>" . $Proj->getGroups($row['group_id']) . "</b>
					</td>
				</tr>";
		}
		// EVENT NAME
		if ($row['event_id'] == "") $event_name = "<span style='color:#999;'>".$lang['calendar_popup_06']."</span>";
		print "
				<tr valign='middle'>
					<td>{$lang['global_10']}{$lang['colon']}</td>
					<td><b>$event_name</b></td>
				</tr>";
		// STATUS
		if ($row['event_id'] != "") {
			print  "
				<tr valign='middle'>
					<td>{$lang['calendar_popup_08']}{$lang['colon']}</td>
					<td id='td_change_status' style='height:26px;'>";
			// Include file to show status
			$_GET['view'] = 'status';
			$_GET['event_status'] = $row['event_status'];
			include APP_PATH_DOCROOT . 'Calendar/calendar_popup_ajax.php';
			print  "	</td>
					</tr>";
		}
		// DATE
		print  "<tr valign='middle'>
					<td>{$lang['global_18']}{$lang['colon']}</td>
					<td id='td_event_date' style='height:26px;position:relative;'>";
		// Include file to show date
		$_GET['view'] = 'date';
		$_GET['event_date'] = $row['event_date'];
		include APP_PATH_DOCROOT . 'Calendar/calendar_popup_ajax.php';
		print  "	</td>
				</tr>";
		// TIME
		print  "<tr valign='middle'>
					<td>{$lang['global_13']}{$lang['colon']}</td>
					<td id='td_event_time' style='height:26px;'>";
		// Include file to show time
		$_GET['view'] = 'time';
		$_GET['event_time'] = $row['event_time'];
		include APP_PATH_DOCROOT . 'Calendar/calendar_popup_ajax.php';
		print  "	</td>
				</tr>
				<tr valign='top'>
					<td style='padding-top:5px;'>
						{$lang['calendar_popup_11']}{$lang['colon']}
					</td>
					<td style='padding-top:5px;' id='td_notes'>
						<textarea id='notes' class='x-form-textarea x-form-field' style='font-size:12px;width:400px;height:100px;' onkeydown=\"
							document.getElementById('noteprogress').innerHTML = '".$lang['calendar_popup_12']."';
							document.getElementById('savenotes').disabled = false;
						\">{$row['notes']}</textarea>
						<div>
							<input type='button' id='savenotes' value='Save Notes' style='font-size:11px;' disabled onclick=\"
								document.getElementById('noteprogress').innerHTML = '<img src=\'".APP_PATH_IMAGES."progress_circle.gif\' class=\'imgfix\'> <span style=\'color:#666;\'>{$lang['calendar_popup_13']}...</span>';
								this.disabled = true;
								$.post('".APP_PATH_WEBROOT."Calendar/calendar_popup_ajax.php', { pid: pid, action: 'edit_notes', cal_id: {$_GET['cal_id']}, notes: $('#notes').val() },
									function(data) {
										document.getElementById('noteprogress').innerHTML = '<img src=\'".APP_PATH_IMAGES."tick.png\' class=\'imgfix\'> <span style=\'color:green;\'>{$lang['global_39']}!</span>';
									}
								);
							\">
							<span id='noteprogress' style='padding-left:5px;color:red;font-size:12px;'></span>
						</div>";
		if (isset($row['extra_notes']) && !empty($row['extra_notes'])) {
			print  "	<div style='padding:5px 0 0;'>{$lang['calendar_popup_30']}</div>
						<textarea readonly='readonly' class='x-form-textarea x-form-field' style='color:#666;font-size:12px;width:400px;height:60px;'>{$row['extra_notes']}</textarea>";
		}
		print  "	</td>
				</tr>
				<tr valign='middle'>
					<td colspan='2' style='text-align:right;'>
						<br>
						<form method='post' action='{$_SERVER['PHP_SELF']}?pid=$project_id&cal_id={$_GET['cal_id']}&width=500' name='form'>
						<input type='submit' name='deleteCalEv' value='{$lang['calendar_popup_15']}' style='font-size:11px;' onclick=\"
							return confirm('{$lang['calendar_popup_16']}');\">
						</form>
					</td>
				</tr>
				</table>";
		
		
		print  "</TD><TD id='deforms' valign='top' style='padding-left:5px;position:relative;border-left:1px solid #aaa;'>";
		
		
		//List all forms associated with this time-point/visit
		if (isset($row['record']) && $row['record'] != "") {	
			
			// If not longitudinal, get the only existing event_id for the project
			if (!$longitudinal) {
				$sql = "select m.event_id from redcap_events_arms a, redcap_events_metadata m where a.project_id = $project_id and a.arm_id = m.arm_id limit 1";
				$row['event_id'] = db_result(db_query($sql), 0);
			}
			
			if ($row['event_id'] != "") 
			{
				print  "<div id='data_entry_forms' style='font-family:Arial;font-size:14px;width:180px;border-top:1px solid #d0d0d0;'>";
				
				$dataEntry = "<div style='padding:2px 0;'></div>";
				
				if (isset($Proj->eventsForms[$row['event_id']])) 
				{
					//Collect form names and form menu names into arrays
					$form_info = array();
					foreach ($Proj->eventsForms[$row['event_id']] as $this_form) {
						$form_info[$this_form]['form_menu_description'] = $Proj->forms[$this_form]['menu'];
						$form_info[$this_form]['form_status'] = '';					
					}
					//Retrieve all known form status values for forms for this record
					$sql = "select distinct m.form_name, if(d2.value is null, '0', d2.value) as value 
							from (redcap_data d, redcap_metadata m) left join redcap_data d2 
							on d2.project_id = m.project_id and d2.record = d.record and d2.event_id = d.event_id 
							and d2.field_name = concat(m.form_name, '_complete')
							where d.project_id = $project_id and d.project_id = m.project_id and d.record = '".prep($row['record'])."' 
							and d.field_name = m.field_name and m.form_name in (".prep_implode($Proj->eventsForms[$row['event_id']]).")
							and d.event_id = {$row['event_id']}";
					$q = db_query($sql);
					while ($row4 = db_fetch_assoc($q)) {
						$form_info[$row4['form_name']]['form_status'] = $row4['value'];
					}
					
					// Determine if record also exists as a survey response for some instruments
					$surveyResponses = array();
					if ($surveys_enabled) 
					{
						$surveyResponses = Survey::getResponseStatus($project_id, $row['record'], $row['event_id']);
					}
					
					foreach ($form_info as $this_form => $attr) 
					{
						// If it's a survey response, display different icons
						if (isset($surveyResponses[$row['record']][$row['event_id']][$this_form])) {			
							//Determine color of button based on response status
							switch ($surveyResponses[$row['record']][$row['event_id']][$this_form]) {
								case '2':
									$holder_color = 'tick_circle_frame.png';
									break;
								default:
									$holder_color = 'circle_orange_tick.png';
							}
						} else {			
							//Determine color of button based on form status value
							switch ($attr['form_status']) {
								case '0':
									$holder_color = 'circle_red.gif';
									break;
								case '1':
									$holder_color = 'circle_yellow.png';
									break;
								case '2':
									$holder_color = 'circle_green.png';
									break;
								default:
									$holder_color = 'circle_gray.png';
							}
						}
						$dataEntry .= "<div class='hang' style='padding:1px 3px 0px 5px;'>"
									. "<a href='javascript:;' onclick=\"window.opener.location.href='".APP_PATH_WEBROOT
									. "DataEntry/index.php?pid=$project_id&id=".removeDDEending($row['record'])."&page=$this_form&event_id={$row['event_id']}';self.close();\">"
									. "<img src='".APP_PATH_IMAGES.$holder_color."' style='height:16px;width:16px;vertical-align:middle;'>&nbsp;&nbsp;"
									. $attr['form_menu_description'] . "</a>"
									. "</div>";
					}
				
					$dataEntry .= "<div style='padding:2px 0;'></div>";
									
					print renderPanel('Data Entry Forms', $dataEntry);
					
					print  "</div>";
				
				}
			}
			
		}
		
		print  "</TD></TR></TABLE>";
		
		print  "</div>";
		
	//Error
	} else {
		print "<b>{$lang['global_01']}!</b><br><br><a href='javascript:self.close();' style='font-family:Arial;font-size:11px;color:#000066;text-decoration:underline;'>{$lang['calendar_popup_18']}</a>";
	}



/**
 * DISPLAY EMPTY FORM FOR CREATING NEW CALENDAR EVENT
 */
} elseif (!isset($_GET['cal_id']) && empty($_POST)) {
	
	// Make sure values are integers
	$_GET['month'] = (int)$_GET['month'];
	$_GET['day'] = (int)$_GET['day'];
	$_GET['year'] = (int)$_GET['year'];

	//Set the date from URL variables
	$_GET['month']--;
	if (strlen($_GET['month']) < 2) $_GET['month'] = "0" . $_GET['month'];
	if (strlen($_GET['day']) < 2)   $_GET['day']   = "0" . $_GET['day'];	
	$event_date = $_GET['year'] . "-" . $_GET['month'] . "-" . $_GET['day'];
	
	//Check if it's a valid date
	if (!checkdate($_GET['month'], $_GET['day'], $_GET['year'])) {		
		exit("<b>{$lang['global_01']}{$lang['colon']}</b><br>{$lang['calendar_popup_19']}");
	}
	
	print  "<div style='color:green;font-family:verdana;padding:5px;margin-bottom:10px;font-weight:bold;font-size:16px;border-bottom:1px solid #aaa;'>
				{$lang['calendar_popup_20']}</div>
			
			<form method='post' action='{$_SERVER['PHP_SELF']}?pid=$project_id&width=600' name='form'>
			<table style='font-family:Arial;font-size:14px;' cellpadding='0' cellspacing='10'>";
	
	// Show option to attach calendar event to a record (i.e. unscheduled cal event)
	if ($_GET['record']	!= "") {
		$_GET['record'] = strip_tags(label_decode($_GET['record']));
		print  "
			<tr>
				<td valign='top'>$table_pk_label: </td>
				<td valign='top'>
					<b>".RCView::escape(removeDDEending($_GET['record']))."</b>
					<input type='hidden' name='idnumber' value='".RCView::escape($_GET['record'])."'>
				</td>
			</tr>";
	}
	
	print  "<tr>
				<td valign='top'>{$lang['global_18']}{$lang['colon']}</td>
				<td valign='top'>
					<b>".DateTimeRC::format_ts_from_ymd($event_date)." (".DateTimeRC::getDay($event_date).")</b>
					<input type='hidden' id='event_date' name='event_date' value='$event_date'>
				</td>
			</tr>
			<tr>
				<td valign='top'>
					{$lang['global_13']}{$lang['colon']}
					<div style='font-size:10px;color:#888;'>{$lang['global_06']}</div>
				</td>
				<td valign='top'>
					<input type='text' class='x-form-text x-form-field time' id='event_time' name='event_time' maxlength='5' style='width:50px;' onblur=\"redcap_validate(this,'','','soft_typed','time')\"> 
					<span style='font-size:10px;color:#777;font-family:tahoma;'>HH:MM ({$lang['calendar_popup_22']})</span>
				</td>
			</tr>
			<tr>
				<td valign='top'>{$lang['calendar_popup_11']}{$lang['colon']}</td>
				<td valign='top'><textarea id='notes' name='notes' class='x-form-textarea x-form-field' style='font-size:12px;width:400px;height:100px;'>{$row['notes']}</textarea></td>
			</tr>";
	
	// Show option to attach calendar event to a record (i.e. unscheduled cal event)
	if ($_GET['record']	== "") {
		print  "<tr>
					<td valign='top'>$table_pk_label: &nbsp;</td>
					<td>
						<table cellpadding=0 cellspacing=0><tr>
						<td valign='top'>
							<select name='idnumber' id='idnumber' class='x-form-text x-form-field' style='height:22px;padding-right:0;font-size:11px;'>
							<option value=''> - {$lang['calendar_popup_23']} - </option>";
		// Retrieve record list (exclude non-DAG records if user is in a DAG)
		foreach (array_keys(Records::getData('array', array(), $table_pk, array(), $user_rights['group_id'])) as $this_record) {
			print "			<option value='$this_record'>".removeDDEending($this_record)."</option>";
		}
		print  "			</select>
						</td>
						<td valign='top' style='font-size:11px;color:#666;padding-left:10px;'>
							{$lang['calendar_popup_24']} $table_pk_label
						</td>
						</tr></table>
					</td>
				</tr>";
	}			
		
	print  "<tr>
				<td></td>
				<td valign='top'>
					<br><br>
					<input type='submit' value='{$lang['calendar_popup_25']}' onclick=\"
						if (document.getElementById('notes').value.length < 1) {
							alert('{$lang['calendar_popup_26']}');
							return false;						
						}
					\">
					<br><br>
				</td>
			</tr>
			</table>
			</form>";


/**
 * DISPLAY CONFIRMATION THAT NEW CALENDAR EVENT WAS CREATED
 */
} elseif (!isset($_GET['cal_id']) && !empty($_POST)) {
	
	//If an existing record was selected, make sure record doesn't already exist in a DAG. If so, add its group_id to calendar event.
	if ($_POST['idnumber'] != "") {
		$group_id = db_result(db_query("select value from redcap_data where project_id = $project_id and record = '{$_POST['idnumber']}' and field_name = '__GROUPID__' limit 1"), 0);
	//If did not select a record, check if user is in DAG.
	} elseif ($user_rights['group_id'] != "") {
		$group_id = $user_rights['group_id'];
	}
	
	//Add event to calendar
	$sql = "insert into redcap_events_calendar (project_id, group_id, record, event_date, event_time, notes) values "
		 . "($project_id, " . checkNull($group_id) . ", " . checkNull($_POST['idnumber']) . ", '{$_POST['event_date']}', " 
		 . checkNull($_POST['event_time']) . ", '".prep($_POST['notes'])."')";
	
	//Success
	if (db_query($sql)) {
		//Logging
		log_event($sql,"redcap_events_calendar","MANAGE",$new_cal_id,calLogChange(db_insert_id()),"Create calendar event");		
		//Show confirmation
		print  "<div style='color:green;padding:30px 0 0 15px;margin-bottom:10px;font-weight:bold;font-size:16px;'>
					<img src='".APP_PATH_IMAGES."tick.png'>{$lang['calendar_popup_27']}<br><br><br>
				</div>";
		//Render javascript to refresh calendar underneath and close pop-up
		print  "<script type='text/javascript'>
				window.opener.location.reload();
				setTimeout(function(){self.close();},2500);
				</script>";
	//Query failed
	} else {
		print  "<p><b>{$lang['global_01']}{$lang['colon']}</b> {$lang['calendar_popup_28']}</p>";
		if (SUPER_USER) print db_error()."<br>QUERY:<br>$sql";
	}




/**
 * DISPLAY CONFIRMATION THAT CALENDAR EVENT WAS DELETED
 */
} elseif (isset($_GET['cal_id']) && is_numeric($_GET['cal_id']) && !empty($_POST) && isset($_POST['deleteCalEv'])) {
	//Query to delete calendar event
	$sql = "delete from redcap_events_calendar where cal_id = " . $_GET['cal_id'];		
	//Logging
	log_event($sql,"redcap_events_calendar","MANAGE",$_GET['cal_id'],calLogChange($_GET['cal_id']),"Delete calendar event");
	//Run query after logging because values will be deleted
	db_query($sql);		
	//Show confirmation
	print  "<div style='color:red;padding:30px 0 0 15px;margin-bottom:10px;font-weight:bold;font-size:16px;'>
				{$lang['calendar_popup_29']}<br><br><br>
			</div>";
	//Render javascript to refresh calendar underneath and close pop-up
	print  "<script type='text/javascript'>
			window.opener.location.reload();
			setTimeout(function(){self.close();},2500);
			</script>";

}


/**
 * PAGE FOOTER
 */
callJSfile('Calendar.js');
$_GET['width'] = (isset($_GET['width']) && is_numeric($_GET['width']) && $_GET['width'] < 1200) ? $_GET['width'] : 800;
print  "</div>
		<script type='text/javascript'>
		$(function(){
			// Resize window to fit contents
			var maxh = window.screen.height - 100;
			var divh = document.getElementById('bodydiv').offsetHeight + 130;
			var newh = (divh > maxh) ? maxh : divh;
			window.resizeTo({$_GET['width']},newh);
			// Load calendar pop-up
			$('#newdate').datepicker({buttonText: 'Click to select a date',yearRange: '-100:+10',changeMonth: true, changeYear: true, dateFormat: user_date_format_jquery});						
			// Pop-up time-select initialization
			$('.time').timepicker({hour: currentTime('h'), minute: currentTime('m'), timeFormat: 'hh:mm'});
		});
		</script>";

?>
</body>
</html>