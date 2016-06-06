<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/

/**
 * includes
 */
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once $base_path . '/plugins/includes/calendar_functions.php';
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
/*
 * get the contact method
 */
if (!isset($_GET['method'])) {
	$_GET['method'] = '';
}
$method = $_GET['method'];
$method_string = array('1' => 'Email ', '2' => 'Call Center ', '3' => 'In-Clinic ');
if ($method != '') {
	$lang['app_08'] = $method_string[$method] . $lang['app_08'];
}
/**
 * restrict use of this plugin to the appropriate project(s)
 */
$allowed_pid = array('37', '38');
REDCap::allowProjects($allowed_pid);

//callJSfile('Calendar.js');
print "<script type=\"text/javascript\" src=\"/plugins/includes/js/Calendar.js\"></script>\n";

renderPageTitle("<div style='float:left;'>
					<img src='".APP_PATH_IMAGES."date.png' class='imgfix2'> {$lang['app_08']}
				 </div>
				 <div style='float:right;'>
					<img src='" . APP_PATH_IMAGES . "video_small.png' class='imgfix'> 
					<a onclick=\"window.open('".CONSORTIUM_WEBSITE."videoplayer.php?video=calendar02.flv&referer=".SERVER_NAME."&title=Calendar','myWin','width=1050, height=800, toolbar=0, menubar=0, location=0, status=0, scrollbars=1, resizable=1');\" href=\"javascript:;\" style=\"font-size:12px;text-decoration:underline;font-weight:normal;\">{$lang['calendar_11']} (7 {$lang['calendar_12']})</a>
				 </div><br><br>");

print  "<p>".$lang['calendar_02'];

//If multiple events exist, explain how participants may be scheduled and added to calendar
if ($longitudinal && $scheduling) {
	print  $lang['calendar_03']."<a href='".APP_PATH_WEBROOT."Calendar/scheduling.php?pid=$project_id' style='text-decoration:underline;'>".$lang['calendar_04']."</a> "
			. $lang['calendar_05'];
}
print  "</p>";

//If user is in DAG, only show calendar events from that DAG and give note of that
if ($user_rights['group_id'] != "") {
	print  "<p style='color:#800000;'>{$lang['global_02']}: {$lang['calendar_06']}</p>";
}

// IE CSS Hack - Render the following CSS if using IE
if ($isIE) {
	print '<style type="text/css">.toprightnumber {width: 107%}</style>';
}

// Render calendar table
include $base_path . PLUGIN_PATH . "Calendar/calendar_table.php";

print "<br><br>";

include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';


// Function to add URL variables to tab links
function appendVarTab() {
	$val = "";
	if (isset($_GET['month']) && isset($_GET['year'])) {
		$val .= "&month={$_GET['month']}&year={$_GET['year']}";
	}
	if (isset($_GET['day']) && $_GET['view'] != "month") {
		$val .= "&day={$_GET['day']}";
	}
	return $val;
}
