<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/


/**
 * Set all calendar variables needed
 */
// If year/month/day exist in query string but are not numeric, then remove them so they can be set with defaults
if (isset($_GET['year']) && !is_numeric($_GET['year'])) unset($_GET['year']);
if (isset($_GET['month']) && !is_numeric($_GET['month'])) unset($_GET['month']);
if (isset($_GET['day']) && !is_numeric($_GET['day'])) unset($_GET['day']);
// Set year/month/day values
if (!isset($_GET['year'])) {
    $_GET['year'] = date("Y");
}
if (!isset($_GET['month'])) {
    $_GET['month'] = date("n")+1;
}
$month = $_GET['month'] - 1;
$year  = $_GET['year'];
if (isset($_GET['day'])) {
	$day = $_GET['day'];
} else {
	$day = $_GET['day'] = 1;
}
$todays_date   = date("j");
$todays_month  = date("n");
$days_in_month = date("t", mktime(0,0,0,$month,1,$year));
$first_day_of_month = date("w", mktime(0,0,0,$month,1,$year)); 
$first_day_of_month++;
$count_boxes = 0;
$days_so_far = 0;
if ($_GET['month'] == 13) {
    $next_month = 2;
    $next_year = $_GET['year'] + 1;
} else {
    $next_month = $_GET['month'] + 1;
    $next_year = $_GET['year'];
}
if ($_GET['month'] == 2) {
    $prev_month = 13;
    $prev_year = $_GET['year'] - 1;
} else {
    $prev_month = $_GET['month'] - 1;
    $prev_year = $_GET['year'];
}
$week_of_month_count = 1; //Default
	
//Check if it's a valid date
if (!checkdate($_GET['month']-1, $_GET['day'], $_GET['year'])) {
	exit("<b>{$lang['global_01']}:</b><br>{$lang['calendar_popup_19']}");
}
//Check if calendar view format is set correctly
if (isset($_GET['view']) && !in_array($_GET['view'], array("day","month","agenda","week"))) {
	$_GET['view'] = "month";
}
/**
 * adjust the PAGE constant for plugin location
 */
if (!defined(PAGE)) {
	define(PAGE, $_SERVER['PHP_SELF']);
}
/**
 * get the method if we don't already have it
 */
if (!isset($method) && isset($_GET['method'])) {
	$method = $_GET['method'];
}

/**
 * TABS FOR CHANGING CALENDAR VIEW
 */
if (PAGE == "/plugins/Calendar/index.php")
{
	print "<div id='sub-nav' style='margin-bottom:0;max-width:800px;'><ul>";
//Day
	print "<li";
	if ($_GET['view'] == "day") print " class='active' ";
	print "><a style='font-size:12px;color:#393733;padding:5px 5px 0px 11px;' href='" . $_SERVER['PHP_SELF'] . "?pid=$project_id&view=day&method=$method";
	print appendVarTab();
	print "'>" . $lang['calendar_07'] . "</a></li>";
//Week
	print "<li";
	if ($_GET['view'] == "week") print " class='active' ";
	print "><a style='font-size:12px;color:#393733;padding:5px 5px 0px 11px;' href='" . $_SERVER['PHP_SELF'] . "?pid=$project_id&view=week&method=$method";
	print appendVarTab();
	print "'>" . $lang['calendar_08'] . "</a></li>";
//Month
	print "<li";
	if (!isset($_GET['view']) || $_GET['view'] == "month" || $_GET['view'] == "") {
		print " class='active' ";
		$_GET['view'] = "month";
	}
	print "><a style='font-size:12px;color:#393733;padding:5px 5px 0px 11px;' href='" . $_SERVER['PHP_SELF'] . "?pid=$project_id&view=month&method=$method";
	print appendVarTab();
	print "'>" . $lang['calendar_09'] . "</a></li>";
//Agenda
	print "<li";
	if ($_GET['view'] == "agenda") print " class='active' ";
	print "><a style='font-size:12px;color:#393733;padding:5px 5px 0px 11px;' href='" . $_SERVER['PHP_SELF'] . "?pid=$project_id&view=agenda&method=$method";
	print appendVarTab();
	print "'>" . $lang['calendar_10'] . "</a></li>";
	print "</ul></div><br><br><br>";
}



/**
 * RETRIEVE ALL CALENDAR EVENTS
 */
list ($event_info, $events) = getCalEventsByContactMethod($month, $year, $method);

//Div to display for calendar event mouseovers
print "<div id='mousecaldiv' style='display:none;position:absolute;z-index:110;width:250px;padding:10px 10px 10px 5px;background-color:#f5f5f5;border:1px solid #777;'></div>";


/**
 * DROP-DOWNS FOR CHANGING MONTH/YEAR
 */
?>
<div align="center" style="max-width:700px;" id="month_year_select">
  <table width="97%" cellspacing="0">
    <tr> 
      <td width="25%">
	  </td>
      <td width="47%" valign="middle" style="text-align:center;">
		  
		  <a href="<?php print $_SERVER['PHP_SELF']."?pid=$project_id&method=$method&month=$prev_month&year=$prev_year&day={$_GET['day']}&view={$_GET['view']}&method=$method" ?>"><img
		  	src="<?php print APP_PATH_IMAGES ?>rewind_blue.png" class="imgfix" alt="<?php print $lang['calendar_table_01'] ?>" 
		  	title="<?php print $lang['calendar_table_01'] ?>"></a> &nbsp; &nbsp;
          
		  <!-- MONTH DROP-DOWN -->
		  <select name="month" id="month" class="x-form-text x-form-field" style="padding:2px 0 0 3px;height:22px;font-weight:bold;font-size:13px;" 
		  onChange="window.location.href='<?php print PAGE."?pid=$project_id&method=$method&year={$_GET['year']}&view={$_GET['view']}&month=" ?>'+this.value+addGoogTrans();">
            <?php
			for ($i = 1; $i <= 12; $i++) {
				$link = $i+1;
				IF($_GET['month'] == $link){
					$selected = "selected";
				} ELSE {
					$selected = "";
				}
				print "<option value='$link' $selected>" . date ("F", mktime(0,0,0,$i,1,$_GET['year'])) . "</option>";
			}
			?>
          </select>
		  
		  <?php
		  if ($_GET['view'] == 'day' || $_GET['view'] == 'week') {
		  ?>
			  <!-- DAY DROP-DOWN (in day or week view) -->
			  <select name="day" id="day" class="x-form-text x-form-field" style="padding:2px 0 0 3px;height:22px;font-weight:bold;font-size:13px;" 
			  onChange="window.location.href='<?php print PAGE."?pid=$project_id&method=$method&year={$_GET['year']}&view={$_GET['view']}&month={$_GET['month']}&day=" ?>'+this.value+addGoogTrans();">
				<?php
				for ($i = 1; $i <= $days_in_month; $i++) {
					IF($_GET['day'] == $i){
						$selected = "selected";
					} ELSE {
						$selected = "";
					}
					print "<option value='$i' $selected>$i</option>";
				}
				?>
			  </select>
		  <?php
		  }
		  ?>
        
          <!-- YEAR DROP-DOWN -->
		  <select name="year" id="year" class="x-form-text x-form-field" style="padding:2px 0 0 3px;height:22px;font-weight:bold;font-size:13px;" 
		  onChange="window.location.href='<?php print PAGE."?pid=$project_id&method=$method&month={$_GET['month']}&view={$_GET['view']}&year=" ?>'+this.value+addGoogTrans();">
		  <?php
		  for ($i = (date("Y")-10); $i <= (date("Y")+5); $i++) {
		  	IF($i == $_GET['year']){
				$selected = "selected";
			} ELSE {
				$selected = "";
			}
		  	print "<option value='$i' $selected>$i</option>";
		  }
		  ?>
          </select>
		  
		  &nbsp; &nbsp; <a href="<?php print $_SERVER['PHP_SELF']."?pid=$project_id&method=$method&month=$next_month&year=$next_year&day={$_GET['day']}&view={$_GET['view']}" ?>"><img
			  src="<?php echo APP_PATH_IMAGES ?>forward_blue.png" class="imgfix" alt="<?php echo $lang['calendar_table_02'] ?>" 
			  title="<?php echo $lang['calendar_table_02'] ?>"></a>
       
	   </td>
       <td width="25%" valign="middle" style="text-align:right;">
			<img src="<?php print APP_PATH_IMAGES; ?>printer.png" class="imgfix"> 
			<a href="javascript:;" class="invisible_in_print" style="color:#800000;text-decoration:underline;" onclick="
			<?php
				print "window.open('/plugins/ProjectGeneral/print_page.php?pid=$project_id&printcalendar&view={$_GET['view']}"
					. (isset($_GET['year'])  ? "&year=".$_GET['year']   : "")
					. (isset($_GET['month']) ? "&month=".$_GET['month'] : "")
					. (isset($_GET['day'])   ? "&day=".$_GET['day'] 	: "")
					. (isset($_GET['method']) ? "&method=" . $_GET['method'] : "")
					. "','myWin','width=850, height=800, toolbar=0, menubar=1, location=0, status=0, scrollbars=1, resizable=1');"
			?>
			"><?php echo $lang['calendar_table_03'] ?></a>
	   </td>
    </tr>
  </table>
  <br>
</div>

<?php


/**
 * AGENDA OR DAY VIEW
 */
if ($_GET['view'] == "agenda" || $_GET['view'] == "day") {
	//Display table with this month's agenda
	print  "<div style='max-width:700px;'>";
	print  "<table class='dt' style='width:650px;' align='center' cellpadding='0' cellspacing='0'>
				<tr class='grp2'>
					<td style='border:1px solid #aaa;font-size:12px;padding-left:8px;width:120px;'>{$lang['calendar_table_04']}</td>
					<td style='border:1px solid #aaa;font-size:12px;padding-left:8px;width:40px;'>{$lang['global_13']}</td>
					<td style='border:1px solid #aaa;font-size:12px;padding-left:10px;'>{$lang['global_20']}</td>
				</tr>";
	$k = 1;
	//Loop through each day this month to see if any events exist
	for ($i = 1; $i <= $days_in_month; $i++) {
		//If in Day view, only show the day selected
		if ($_GET['view'] == "day" && $i != $day) continue;
		//List any events for this day
		if (isset($events[$i])) 
		{
			//Loop through all of this day's events
			while (list($key, $value) = each ($events[$i])) 
			{		
				//Determine if we need to display the date (do not if repeating from previous row)
				$this_day = "$month/$i/$year";
				if ($next_day != $this_day) {
					$day_text =  date ("D", mktime(0,0,0,$month,$i,$year)) . " " . date ("M", mktime(0,0,0,$month,$i,$year)) . " $i";
					$evenOrOdd = ($k%2) == 0 ? 'even' : 'odd';		
					$k++;
				} else {
					$day_text = "";
				}
				print  "<tr class='$evenOrOdd' valign='top'>
							<td style='padding:3px 5px 2px 8px;font-weight:bold;width:120px;'>$day_text</td>
							<td style='padding:3px 5px 1px 8px;font-family:tahoma;font-size:11px;width:40px;'>" . DateTimeRC::format_ts_from_ymd($event_info[$value]['5']) . "</td>
							<td class='notranslate' style='padding:1px 5px 1px 5px;'>";
				renderCalEvent($event_info,$i,$value,$_GET['view']);
				print  "	</td>
						</tr>";
				//Set next day's date
				$next_day = "$month/$i/$year";
			}
		}
	}
	//If no events to display
	if ($k == 1) {
		print  "<tr class='$evenOrOdd' valign='top'>
					<td colspan='3' style='padding:3px 5px 2px 8px;'>{$lang['calendar_table_07']}</td>
				</tr>";
	}
	print  "</table>";
	print  "</div><br><br>";
	
	if (PAGE == "/plugins/Calendar/index.php") {
		include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
	} else {
		// On print page, so hide drop-down to select month/year
		print  "<script type='text/javascript'>
					document.getElementById('month_year_select').style.display = 'none';
				</script>";
	}
	exit;
}


/**
 * MONTHLY/WEEK VIEW
 */
?>
<table cellspacing="0" style="background-color:#8890B0;width:800px;margin-right:250px;">
  <tr>
    <td><table width="100%" border="0" cellpadding="0" cellspacing="1">
        <tr class="topdays"> 
          <td style="padding:5px;"><div align="center"><?php echo $lang['calendar_table_08'] ?></div></td>
          <td style="padding:5px;"><div align="center"><?php echo $lang['calendar_table_09'] ?></div></td>
          <td style="padding:5px;"><div align="center"><?php echo $lang['calendar_table_10'] ?></div></td>
          <td style="padding:5px;"><div align="center"><?php echo $lang['calendar_table_11'] ?></div></td>
          <td style="padding:5px;"><div align="center"><?php echo $lang['calendar_table_12'] ?></div></td>
          <td style="padding:5px;"><div align="center"><?php echo $lang['calendar_table_13'] ?></div></td>
          <td style="padding:5px;"><div align="center"><?php echo $lang['calendar_table_14'] ?></div></td>
        </tr>
		<tr valign="top" id="week_1"> 
		<?php
		
		for ($i = 1; $i <= $first_day_of_month-1; $i++) {
			$days_so_far = $days_so_far + 1;
			$count_boxes = $count_boxes + 1;
			print "<td class='beforedayboxes'></td>";
		}
		
		// Tag for day links when in "print calendar" view
		$printcal = (PAGE == "ProjectGeneral/print_page.php") ? "&printcalendar" : "";
		
		for ($i = 1; $i <= $days_in_month; $i++) {
			
			//Flag this week of the month as containing the current day
			if ($i == $day) $this_week_of_month = $week_of_month_count;
		
   			$days_so_far = $days_so_far + 1;
    		$count_boxes = $count_boxes + 1;
				
			IF ($_GET['month'] == $todays_month+1 && $i == $todays_date) {
				//Today
				$class = "highlighteddayboxes";
				$extra_style = "style='background-color:#C9D6E9;'";
			} ELSE {
				//Not Today
				$class = "dayboxes";
				$extra_style = "";
			}
			
			//Render individual day
			print "<td class='$class'>";
			$link_month = $_GET['month'] - 1;
			
			//Day of month
			print  "<div class='toprightnumber' $extra_style>
					<table class='calday' cellspacing='0' cellpadding='0'><tr>
						<td id='new{$i}' style='width:40px;text-align:left;' onclick=\"popupCalNew($i,{$_GET['month']},{$_GET['year']},'')\" 
							onmouseover='calNewOver($i)' onmouseout='calNewOut($i)'>
							&nbsp;<a href='javascript:;' id='link{$i}' style='font-family:Tahoma;font-size:9px;color:#999;text-decoration:none;'>+ New</a>
						</td>
						<td style='padding-right:4px;'>
							<a href='{$_SERVER['PHP_SELF']}?pid=$project_id&view=day$printcal&month={$_GET['month']}&year={$_GET['year']}&day=$i'><b>$i</b></a>&nbsp;
						</td>
					</tr></table>
					</div>";
			
			$event_limit_show = 5;
			
			//List any events for this day
			if (isset($events[$i])) {
				//Count events for this day
				$events_count = 1;
				//Total events for this day
				$events_total = count($events[$i]);
				//Div for day
				print "<div class='eventinbox'>";
				//Loop through all of day's events
				while (list($key, $value) = each ($events[$i])) {
					//Hide some events if more than $event_limit_show events exist per day
					if (($_GET['view'] == "month") && ($events_count == $event_limit_show+1) && ($events_total != $event_limit_show+1)) {
						print "<div style='display:none;' id='hidden{$i}'>";
					}
					renderCalEvent($event_info,$i,$value,$_GET['view']);
					// Increment counter
					$events_count++;
				}
				//If some events are hidden, close div which contained them
				if (($_GET['view'] == "month") && ($events_count > $event_limit_show+1) && ($events_total != $event_limit_show+1)) {
					print  "</div>
							<div style='text-align:center;padding-bottom:3px;' id='hiddenlink{$i}'>
							<a class='showEv' ev='$i' href='javascript:;' style='color:#000066;text-decoration:underline;' 
								onclick='showEv($i)'>+".($events_count-$event_limit_show-1)." more</a>
							</div>";
				}
				print "</div>";
			}
			print "</td>";
			
			IF(($count_boxes == 7) AND ($days_so_far != (($first_day_of_month-1) + $days_in_month))){
				$count_boxes = 0;
				$week_of_month_count++;
				print "</TR><TR valign='top' id='week_{$week_of_month_count}'>";
			}
		}
		$extra_boxes = 7 - $count_boxes;
		for ($i = 1; $i <= $extra_boxes; $i++) {
			print "<td class='afterdayboxes'></td>";
		}
		
		?>
        </tr>
      </table></td>
  </tr>
</table>

<?php


// If Weekly view, hide non-applicable rows via javascript
if ($_GET['view'] == "week") {
	print "<script type='text/javascript'>";
	for ($i = 1; $i <= $week_of_month_count; $i++) {
		if ($this_week_of_month != $i) print "document.getElementById('week_{$i}').style.display = 'none';";
	}
	print "</script>";
}
