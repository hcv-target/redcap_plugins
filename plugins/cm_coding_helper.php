<?php
/**
 * Created by HCV-TARGET for HCV-TARGET.
 * User: kbergqui
 * Date: 2014-07-16
 */
/**
 * TESTING
 */
$debug = true;
if ($debug) {
	$timer = array();
	$timer['start'] = microtime(true);
}
/**
 * includes
 * adjust dirname depth as needed
 */
$base_path = dirname(dirname(__FILE__));
require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once APP_PATH_DOCROOT . '/Config/init_project.php';
require_once APP_PATH_DOCROOT . '/ProjectGeneral/header.php';
?>
	<!-- DataTables -->
	<link rel="stylesheet" type="text/css"
	      href="<?php echo PLUGIN_PATH ?>DataTables-1.10.2/media/css/jquery.dataTables.min.css">
	<link rel="stylesheet" type="text/css"
	      href="<?php echo PLUGIN_PATH ?>DataTables-1.10.2/extensions/FixedHeader/css/dataTables.fixedHeader.min.css">
	<link rel="stylesheet" type="text/css"
	      href="<?php echo PLUGIN_PATH ?>DataTables-1.10.2/extensions/ColReorder/css/dataTables.colReorder.min.css">
	<link rel="stylesheet" type="text/css"
	      href="<?php echo PLUGIN_PATH ?>DataTables-1.10.2/extensions/ColVis/css/dataTables.colVis.min.css">
	<script type="text/javascript" charset="utf8"
	        src="<?php echo PLUGIN_PATH ?>DataTables-1.10.2/media/js/jquery.dataTables.min.js"></script>
	<script type="text/javascript" charset="utf8"
	        src="<?php echo PLUGIN_PATH ?>DataTables-1.10.2/extensions/FixedHeader/js/dataTables.fixedHeader.nightly.min.js"></script>
	<script type="text/javascript" charset="utf8"
	        src="<?php echo PLUGIN_PATH ?>DataTables-1.10.2/extensions/ColReorder/js/dataTables.colReorder.min.js"></script>
	<script type="text/javascript" charset="utf8"
	        src="<?php echo PLUGIN_PATH ?>DataTables-1.10.2/extensions/ColVis/js/dataTables.colVis.min.js"></script>
	<script type="text/javascript" src="<?php echo PLUGIN_PATH ?>includes/js/jquery.highlight.js"></script>
<?php
/**
 * queries
 */
$sql_query = "SELECT DISTINCT drug.drug_name, atc.atc_name, atc2.atc_name AS atc2_name, atc3.atc_name AS atc3_name, atc4.atc_name AS atc4_name FROM
        (SELECT drug_name, drug_rec_num FROM _whodrug_mp_us) drug
        LEFT JOIN
        (SELECT drug_rec_num, atc_code FROM _whodrug_dda) mp_atc ON TRIM(LEADING '0' FROM drug.drug_rec_num) = mp_atc.drug_rec_num
        LEFT JOIN
        (SELECT atc_code, atc_name FROM _whodrug_atc) atc ON SUBSTRING(mp_atc.atc_code,1,1) = atc.atc_code
        LEFT JOIN
        (SELECT atc_code, atc_name FROM _whodrug_atc) atc2 ON SUBSTRING(mp_atc.atc_code,1,3) = atc2.atc_code
        LEFT JOIN
        (SELECT atc_code, atc_name FROM _whodrug_atc) atc3 ON SUBSTRING(mp_atc.atc_code,1,4) = atc3.atc_code
        LEFT JOIN
        (SELECT atc_code, atc_name FROM _whodrug_atc) atc4 ON SUBSTRING(mp_atc.atc_code,1,5) = atc4.atc_code";
$remote_query = "SELECT DISTINCT drug_name FROM _whodrug_mp_us";
/**
 * variables
 */
$autocomplete_field_name = 'cm_cmdecod';
$_SESSION["query_$autocomplete_field_name"] = $remote_query;
$query_array = explode(' ', $remote_query);
$query_field = $query_array[(array_search('FROM', $query_array) - 1)];
/**
 * form, submitted on input blur()
 */
?>
<h3>This plugin will return the WHODrug coding for a given verbatim term or partial term.</h3>
<p>To search for a known CONMED, enter the CONMED text. If an autocomplete match is found, use the arrow keys to select it, then hit the TAB key. To search for a partial term, enter the partial text and hit the TAB key.</p>
	<form id="get_cm_results" action="<?php echo $_SERVER['PHP_SELF'] . '?pid=' . $project_id; ?> " method="post">
		<div class="data" style='max-width:700px;'>
			<input id="<?php echo $autocomplete_field_name ?>" name="<?php echo $autocomplete_field_name ?>" type="text" value="<?php echo $_POST[$autocomplete_field_name] ?>"/>
	</form>
<?php
/**
 * end form
 */
if (isset($_POST[$autocomplete_field_name])) {
	$cm_cmdecod = $_POST[$autocomplete_field_name];
	$rows = '';
	$item_count = 1;
	/**
	 * query data and return table of results
	 */
	$result = db_query($sql_query . " WHERE drug.drug_name = '$cm_cmdecod'");
	$loose_result = db_query($sql_query . " WHERE drug.drug_name LIKE '%$cm_cmdecod%' AND drug.drug_name <> '$cm_cmdecod'");
	if ($result) {
		if (db_num_rows($result) > 0 || db_num_rows($loose_result) > 0) {
			$table_header = RCView::tr('',
				RCView::th(array('class' => 'header'),
					"Drug"
				) .
				RCView::th(array('class' => 'header'),
					"ATC Level 1"
				) .
				RCView::th(array('class' => 'header'),
					"ATC Level 2"
				) .
				RCView::th(array('class' => 'header'),
					"ATC Level 3"
				) .
				RCView::th(array('class' => 'header'),
					"ATC Level 4"
				)
			);
			$thead = RCView::thead(array('class' => 'header'), $table_header);
			while ($result_row = db_fetch_assoc($result)) {
				$columns = '';
				$columns .= RCView::td(array('class' => row_style($item_count), 'style' => 'white-space: nowrap;'), $result_row['drug_name']);
				$columns .= RCView::td(array('class' => row_style($item_count), 'style' => 'white-space: nowrap;'), $result_row['atc_name']);
				$columns .= RCView::td(array('class' => row_style($item_count), 'style' => 'white-space: nowrap;'), $result_row['atc2_name']);
				$columns .= RCView::td(array('class' => row_style($item_count), 'style' => 'white-space: nowrap;'), $result_row['atc3_name']);
				$columns .= RCView::td(array('class' => row_style($item_count), 'style' => 'white-space: nowrap;'), $result_row['atc4_name']);
				$rows .= RCView::tr('', $columns);
				$item_count++;
			}
			if ($loose_result) {
				if (db_num_rows($loose_result) > 0) {
					while ($loose_result_row = db_fetch_assoc($loose_result)) {
						$columns = '';
						$columns .= RCView::td(array('class' => row_style($item_count), 'style' => 'white-space: nowrap;'), $loose_result_row['drug_name']);
						$columns .= RCView::td(array('class' => row_style($item_count), 'style' => 'white-space: nowrap;'), $loose_result_row['atc_name']);
						$columns .= RCView::td(array('class' => row_style($item_count), 'style' => 'white-space: nowrap;'), $loose_result_row['atc2_name']);
						$columns .= RCView::td(array('class' => row_style($item_count), 'style' => 'white-space: nowrap;'), $loose_result_row['atc3_name']);
						$columns .= RCView::td(array('class' => row_style($item_count), 'style' => 'white-space: nowrap;'), $loose_result_row['atc4_name']);
						$rows .= RCView::tr('', $columns);
						$item_count++;
					}
				}
			}
			$tbody = "<tbody>" . $rows . "</tbody>";
			echo RCView::table(array('id' => 'ae_report_results', 'class' => 'dt', 'cellspacing' => '0'), $thead . $tbody);
			echo RCView::br() . RCView::br();
		} else {
			echo RCView::h3('', "Sorry, there were no results for that search. Try again?");
		}
	}
	/**
	 * after submit and results are returned, highlight the search term
	 * @TODO: figure out why we can't set focus back to the search input field
	 */
	?>
	<script type="text/javascript">
		$(document).ready(function () {
			var table_settings = {
				"paging": false,
				"ordering": true,
				"info": true,
				"bAutoWidth": false,
				"dom": 'iC<"clear">Rlfrtp'
			};
			var search_array = ["<?php echo $cm_cmdecod ?>"];
			var table = $("table#ae_report_results").DataTable(table_settings);
			console.log(search_array);
			fixed_header = new $.fn.dataTable.FixedHeader(table, {
				left: true
			});
			$("table#ae_report_results tr td").highlight(search_array);
			table.on('search.dt', function () {
				fixed_header.fnDisable();
				fixed_header = new $.fn.dataTable.FixedHeader(table, {
					left: true
				});
				if (table.search() != "") {
					search_array[1] = table.search();
				}
				$("table#ae_report_results tr td").highlight(search_array);
			}).draw();
		});
	</script>
	<?php
}
/**
 * inject autocomplete
 */
?>
	<script type="text/javascript">
		$(document).ready(function () {
			$(".highlight").css({backgroundColor: "#FFFF88"});
			$("#<?php echo $autocomplete_field_name ?>").wrap("<span><div class='ui-widget'></div></span>");
			$("#<?php echo $autocomplete_field_name ?>").autocomplete({
				serviceUrl: "<?php echo PLUGIN_PATH ?>Autocomplete/autocomplete_control_ajax.php?pid=<?php echo $project_id ?>&f=<?php echo $autocomplete_field_name ?>&a=<?php echo $query_field ?>",
				deferRequestBy: 0,
				noCache: false,
				minChars: 2,
				type: "POST"
			});
			if ($("#<?php echo $autocomplete_field_name ?>.autocomplete").length) {
				$("#<?php echo $autocomplete_field_name ?>.autocomplete").prepend("<div id='searching' style='display:none;'></div>");
			}
			$("#<?php echo $autocomplete_field_name ?>").blur(function () {
				$("#get_cm_results").trigger('submit');
			});
		});
	</script>
<?php
if ($debug) {
	$timer['main_end'] = microtime(true);
	$init_time = benchmark_timing($timer);
	echo $init_time;
}
