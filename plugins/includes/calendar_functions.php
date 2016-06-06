<?php
/**
 * Created by HCV-TARGET.
 * User: kenbergquist
 * Date: 9/8/15
 * Time: 12:28 PM
 */

/**
 * RETRIEVE ALL CALENDAR EVENTS
 */
function getCalEventsByContactMethod($month, $year, $method)
{
	global $user_rights, $Proj;

	// Place info into arrays
	$event_info = array();
	$events = array();

	$year_month = (strlen($month) == 2) ? $year . "-" . $month : $year . "-0" . $month;
	$sql = "select * from redcap_events_metadata m
	right outer join redcap_events_calendar c
	on c.event_id = m.event_id
	left outer join redcap_data d
	on c.record = d.record
	where c.project_id = " . PROJECT_ID . " and c.event_date like '{$year_month}%'
	" . (($user_rights['group_id'] != "") ? "and c.group_id = {$user_rights['group_id']}" : "") . "
	and ((d.field_name = 'optin1_scorres' AND d.value = '$method') OR d.record IS NULL)
	order by c.event_date, c.event_time";
	$query_result = db_query($sql);
	$i = 0;
	while ($info = db_fetch_assoc($query_result)) {
		$thisday = substr($info['event_date'], -2) + 0;
		$events[$thisday][] = $event_id = $i;
		$event_info[$event_id]['0'] = $info['descrip'];
		$event_info[$event_id]['1'] = $info['record'];
		$event_info[$event_id]['2'] = $info['event_status'];
		$event_info[$event_id]['3'] = $info['cal_id'];
		$event_info[$event_id]['4'] = $info['notes'];
		$event_info[$event_id]['5'] = $info['event_time'];
		// Add DAG, if exists
		if ($info['group_id'] != "") {
			$event_info[$event_id]['6'] = $Proj->getGroups($info['group_id']);
		}
		$i++;
	}

	// Return the two arrays
	return array($event_info, $events);
}