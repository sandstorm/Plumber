<?php

$base_url_params = xhprof_array_unset(xhprof_array_unset($url_params,
	'run1'),
	'run2');
$href1 = "$base_path/?" .
http_build_query(xhprof_array_set($base_url_params,
	'run', $run1));
$href2 = "$base_path/?" .
http_build_query(xhprof_array_set($base_url_params,
	'run', $run2));

print("<h3><center>Overall Diff Summary</center></h3>");
print('<table border=1 cellpadding=2 cellspacing=1 width="30%" '
	.'rules=rows bordercolor="#bdc7d8" align=center>' . "\n");
print('<tr bgcolor="#bdc7d8" align=right>');
print("<th></th>");
print("<th $vwbar>" . xhprof_render_link("Run #$run1", $href1) . "</th>");
print("<th $vwbar>" . xhprof_render_link("Run #$run2", $href2) . "</th>");
print("<th $vwbar>Diff</th>");
print("<th $vwbar>Diff%</th>");
print('</tr>');

if ($display_calls) {
	print('<tr>');
	print("<td>Number of Function Calls</td>");
	print_td_num($totals_1["ct"], $format_cbk["ct"]);
	print_td_num($totals_2["ct"], $format_cbk["ct"]);
	print_td_num($totals_2["ct"] - $totals_1["ct"], $format_cbk["ct"], true);
	print_td_pct($totals_2["ct"] - $totals_1["ct"], $totals_1["ct"], true);
	print('</tr>');
}

foreach ($metrics as $metric) {
	$m = $metric;
	print('<tr>');
	print("<td>" . str_replace("<br>", " ", $descriptions[$m]) . "</td>");
	print_td_num($totals_1[$m], $format_cbk[$m]);
	print_td_num($totals_2[$m], $format_cbk[$m]);
	print_td_num($totals_2[$m] - $totals_1[$m], $format_cbk[$m], true);
	print_td_pct($totals_2[$m] - $totals_1[$m], $totals_1[$m], true);
	print('<tr>');
}
print('</table>');

$callgraph_report_title = '[View Regressions/Improvements using Callgraph Diff]';





if ($diff_mode) {
	if ($all) {
		$title = "Total Diff Report: '
			.'Sorted by absolute value of regression/improvement in $desc";
	} else {
		$title = "Top 100 <i style='color:red'>Regressions</i>/"
			. "<i style='color:green'>Improvements</i>: "
			. "Sorted by $desc Diff";
	}
} else {
	if ($all) {
		$title = "Sorted by $desc";
	} else {
		$title = "Displaying top $limit functions: Sorted by $desc";
	}
}
