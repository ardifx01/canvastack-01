<?php
/**
 * Created on Oct 19, 2022
 * 
 * Time Created : 5:33:34 PM
 *
 * @filesource	Chart.php
 *
 * @author     wisnuwidi@incodiy.com - 2022
 * @copyright  wisnuwidi
 * @email      wisnuwidi@incodiy.com
 */

if (!function_exists('diy_script_chart')) {
	
	function diy_script_chart($type = 'line', $identity = null, $title = null, $subtitle = null, $xAxis = null, $yAxis = null, $tooltips = null, $legends = null, $series = null) {
		
		$chartType   = "chart: {type: '{$type}'},";
		$tableName   = 'report_data_summary_program_free_sp_3gb';
		$current_url = url(diy_current_route()->uri);
		$link_url    = "renderCharts=true&difta[name]={$tableName}&difta[source]=dynamics";
		$chartURI    = "{$current_url}?{$link_url}";
		$series      = str_replace('series:', '', $series);
		
		return "
<script type=\"text/javascript\">
$.ajax({
	url: '{$chartURI}',
	type: 'get',
	data: {$series}
	success: function (data) {
		console.log(data);
	},
	error: function(jqXHR, textStatus, errorThrown) {
		console.log(textStatus, errorThrown);
	}
});
$(function() {
    $('#{$identity}').highcharts({
        {$chartType}
        {$title}
        {$subtitle}
        {$xAxis}
        {$yAxis}
        {$tooltips}
        {$legends}
        series:{$series} 
    });
});
</script>";
	}
}
