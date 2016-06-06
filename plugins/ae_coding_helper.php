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
$sql_query = "SELECT DISTINCT llt.aellt, pt.aedecod, hlt.aehlt, hlgt.aehlgt, soc.aesoc
FROM
(SELECT DISTINCT llt.llt_name AS aellt,
llt.llt_code AS aelltcd,
llt.pt_code
FROM _meddra_low_level_term llt
) llt
LEFT OUTER JOIN
(SELECT DISTINCT pt.pt_name AS aedecod,
pt.pt_code as aeptcd,
pt.pt_soc_code AS aebdsycd
FROM _meddra_pref_term pt
) pt
ON llt.pt_code = CONVERT(pt.aeptcd USING utf8) COLLATE utf8_unicode_ci
LEFT OUTER JOIN
(SELECT DISTINCT hlt.hlt_name AS aehlt,
hlt.hlt_code AS aehltcd,
hlt_pt.pt_code
FROM _meddra_hlt_pref_term hlt
LEFT OUTER JOIN _meddra_hlt_pref_comp hlt_pt
ON hlt.hlt_code = hlt_pt.hlt_code
) hlt
ON llt.pt_code = CONVERT(hlt.pt_code USING utf8) COLLATE utf8_unicode_ci
LEFT OUTER JOIN
(SELECT DISTINCT hlgt.hlgt_name AS aehlgt,
hlgt.hlgt_code AS aehlgtcd,
hlgt_hlt.hlt_code
FROM _meddra_hlgt_pref_term hlgt
LEFT OUTER JOIN _meddra_hlgt_hlt_comp hlgt_hlt
ON hlgt.hlgt_code = hlgt_hlt.hlgt_code
) hlgt
ON hlt.aehltcd = CONVERT(hlgt.hlt_code USING utf8) COLLATE utf8_unicode_ci
LEFT OUTER JOIN
(SELECT soc.soc_name AS aesoc,
soc.soc_code
FROM _meddra_soc_term soc
) soc
ON pt.aebdsycd = CONVERT(soc.soc_code USING utf8) COLLATE utf8_unicode_ci";
$remote_query = "SELECT DISTINCT llt_name FROM _meddra_low_level_term";
/**
 * variables
 */
$autocomplete_field_name = 'ae_aemodify';
$_SESSION["query_$autocomplete_field_name"] = $remote_query;
$query_array = explode(' ', $remote_query);
$query_field = $query_array[(array_search('FROM', $query_array) - 1)];
/**
 * form, submitted on input blur()
 */
?>
<h3>This plugin will return the MedDRA coding for a given verbatim term or partial term.</h3>
<p>To search for a known LLT, enter the LLT text. If an autocomplete match is found, use the arrow keys to select it, then hit the TAB key. To search for a partial term, enter the partial text and hit the TAB key.</p>
	<form id="get_ae_results" action="<?php echo $_SERVER['PHP_SELF'] . '?pid=' . $project_id; ?> " method="post">
		<div class="data" style='max-width:700px;'>
			<label>MedDRA term:
				<input id="<?php echo $autocomplete_field_name ?>" name="<?php echo $autocomplete_field_name ?>"
				       type="text" value="<?php echo $_POST[$autocomplete_field_name] ?>"/>
			</label>
	</form>
<?php
/**
 * end form
 */
if (isset($_POST[$autocomplete_field_name])) {
	$ae_aemodify = $_POST[$autocomplete_field_name];
	$rows = '';
	$item_count = 1;
	/**
	 * query data and return table of results
	 */
	$result = db_query($sql_query . " WHERE llt.aellt = '$ae_aemodify'");
	$loose_result = db_query($sql_query . " WHERE llt.aellt LIKE '%$ae_aemodify%' AND llt.aellt <> '$ae_aemodify'");
	if ($result) {
		if (db_num_rows($result) > 0 || db_num_rows($loose_result) > 0) {
			$table_header = RCView::tr('',
				RCView::th(array('class' => 'header'),
					"LLT"
				) .
				RCView::th(array('class' => 'header'),
					"PT"
				) .
				RCView::th(array('class' => 'header'),
					"HLT"
				) .
				RCView::th(array('class' => 'header'),
					"HLGT"
				) .
				RCView::th(array('class' => 'header'),
					"SOC"
				)
			);
			$thead = RCView::thead(array('class' => 'header'), $table_header);
			while ($result_row = db_fetch_assoc($result)) {
				$columns = '';
				$columns .= RCView::td(array('class' => row_style($item_count), 'style' => 'white-space: nowrap;'), $result_row['aellt']);
				$columns .= RCView::td(array('class' => row_style($item_count), 'style' => 'white-space: nowrap;'), $result_row['aedecod']);
				$columns .= RCView::td(array('class' => row_style($item_count), 'style' => 'white-space: nowrap;'), $result_row['aehlt']);
				$columns .= RCView::td(array('class' => row_style($item_count), 'style' => 'white-space: nowrap;'), $result_row['aehlgt']);
				$columns .= RCView::td(array('class' => row_style($item_count), 'style' => 'white-space: nowrap;'), $result_row['aesoc']);
				$rows .= RCView::tr('', $columns);
				$item_count++;
			}
			if ($loose_result) {
				if (db_num_rows($loose_result) > 0) {
					while ($loose_result_row = db_fetch_assoc($loose_result)) {
						$columns = '';
						$columns .= RCView::td(array('class' => row_style($item_count), 'style' => 'white-space: nowrap;'), $loose_result_row['aellt']);
						$columns .= RCView::td(array('class' => row_style($item_count), 'style' => 'white-space: nowrap;'), $loose_result_row['aedecod']);
						$columns .= RCView::td(array('class' => row_style($item_count), 'style' => 'white-space: nowrap;'), $loose_result_row['aehlt']);
						$columns .= RCView::td(array('class' => row_style($item_count), 'style' => 'white-space: nowrap;'), $loose_result_row['aehlgt']);
						$columns .= RCView::td(array('class' => row_style($item_count), 'style' => 'white-space: nowrap;'), $loose_result_row['aesoc']);
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
			var search_array = ["<?php echo $ae_aemodify ?>"];
			var table = $("table#ae_report_results").DataTable(table_settings);
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
				console.log(search_array);
				$("table#ae_report_results tr td").highlight(search_array);
			}).draw();
			/*table.on('column-sizing.dt', function (table, table_settings) {
				fixed_header.fnDisable();
				fixed_header = new $.fn.dataTable.FixedHeader(table, {
					left: true
				});
			});*/
			/*$("#<?php //echo $autocomplete_field_name ?>").focus();*/
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
				$("#get_ae_results").trigger('submit');
			});
		});
	</script>
<?php
if ($debug) {
	$timer['main_end'] = microtime(true);
	$init_time = benchmark_timing($timer);
	echo $init_time;
}
