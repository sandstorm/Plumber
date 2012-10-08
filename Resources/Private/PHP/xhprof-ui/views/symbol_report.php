<?php 



/**
 * Generates a report for a single function/symbol.
 *
 * @author Kannan
 */
function symbol_report($url_params,
                       $run_data, $symbol_info, $sort, $rep_symbol,
                       $run1,
                       $symbol_info1 = null,
                       $run2 = 0,
                       $symbol_info2 = null) {
  global $vwbar;
  global $vbar;
  global $totals;
  global $pc_stats;
  global $sortable_columns;
  global $metrics;
  global $diff_mode;
  global $descriptions;
  global $format_cbk;
  global $sort_col;
  global $display_calls;
  global $base_path;

  $possible_metrics = xhprof_get_possible_metrics();

  if ($diff_mode) {
    $diff_text = "<b>Diff</b>";
    $regr_impr = "<i style='color:red'>Regression</i>/<i style='color:green'>Improvement</i>";
  } else {
    $diff_text = "";
    $regr_impr = "";
  }

  if ($diff_mode) {

    $base_url_params = xhprof_array_unset(xhprof_array_unset($url_params,
                                                             'run1'),
                                          'run2');
    $href1 = "$base_path?"
      . http_build_query(xhprof_array_set($base_url_params, 'run', $run1));
    $href2 = "$base_path?"
      . http_build_query(xhprof_array_set($base_url_params, 'run', $run2));

    print("<h3 align=center>$regr_impr summary for $rep_symbol<br><br></h3>");
    print('<table border=1 cellpadding=2 cellspacing=1 width="30%" '
          .'rules=rows bordercolor="#bdc7d8" align=center>' . "\n");
    print('<tr bgcolor="#bdc7d8" align=right>');
    print("<th align=left>$rep_symbol</th>");
    print("<th $vwbar><a href=" . $href1 . ">Run #$run1</a></th>");
    print("<th $vwbar><a href=" . $href2 . ">Run #$run2</a></th>");
    print("<th $vwbar>Diff</th>");
    print("<th $vwbar>Diff%</th>");
    print('</tr>');
    print('<tr>');

    if ($display_calls) {
      print("<td>Number of Function Calls</td>");
      print_td_num($symbol_info1["ct"], $format_cbk["ct"]);
      print_td_num($symbol_info2["ct"], $format_cbk["ct"]);
      print_td_num($symbol_info2["ct"] - $symbol_info1["ct"],
                   $format_cbk["ct"], true);
      print_td_pct($symbol_info2["ct"] - $symbol_info1["ct"],
                   $symbol_info1["ct"], true);
      print('</tr>');
    }


    foreach ($metrics as $metric) {
      $m = $metric;

      // Inclusive stat for metric
      print('<tr>');
      print("<td>" . str_replace("<br>", " ", $descriptions[$m]) . "</td>");
      print_td_num($symbol_info1[$m], $format_cbk[$m]);
      print_td_num($symbol_info2[$m], $format_cbk[$m]);
      print_td_num($symbol_info2[$m] - $symbol_info1[$m], $format_cbk[$m], true);
      print_td_pct($symbol_info2[$m] - $symbol_info1[$m], $symbol_info1[$m], true);
      print('</tr>');

      // AVG (per call) Inclusive stat for metric
      print('<tr>');
      print("<td>" . str_replace("<br>", " ", $descriptions[$m]) . " per call </td>");
      $avg_info1 = 'N/A';
      $avg_info2 = 'N/A';
      if ($symbol_info1['ct'] > 0) {
        $avg_info1 = ($symbol_info1[$m] / $symbol_info1['ct']);
      }
      if ($symbol_info2['ct'] > 0) {
        $avg_info2 = ($symbol_info2[$m] / $symbol_info2['ct']);
      }
      print_td_num($avg_info1, $format_cbk[$m]);
      print_td_num($avg_info2, $format_cbk[$m]);
      print_td_num($avg_info2 - $avg_info1, $format_cbk[$m], true);
      print_td_pct($avg_info2 - $avg_info1, $avg_info1, true);
      print('</tr>');

      // Exclusive stat for metric
      $m = "excl_" . $metric;
      print('<tr style="border-bottom: 1px solid black;">');
      print("<td>" . str_replace("<br>", " ", $descriptions[$m]) . "</td>");
      print_td_num($symbol_info1[$m], $format_cbk[$m]);
      print_td_num($symbol_info2[$m], $format_cbk[$m]);
      print_td_num($symbol_info2[$m] - $symbol_info1[$m], $format_cbk[$m], true);
      print_td_pct($symbol_info2[$m] - $symbol_info1[$m], $symbol_info1[$m], true);
      print('</tr>');
    }

    print('</table>');
  }

  print("<br><h4><center>");
  print("Parent/Child $regr_impr report for <b>$rep_symbol</b>");

  $callgraph_href = "$base_path/callgraph.php?"
    . http_build_query(xhprof_array_set($url_params, 'func', $rep_symbol));

  print(" <a href='$callgraph_href'>[View Callgraph $diff_text]</a><br>");

  print("</center></h4><br>");

  print('<table border=1 cellpadding=2 cellspacing=1 width="90%" '
        .'rules=rows bordercolor="#bdc7d8" align=center>' . "\n");
  print('<tr bgcolor="#bdc7d8" align=right>');

  foreach ($pc_stats as $stat) {
    $desc = stat_description($stat);
    if (array_key_exists($stat, $sortable_columns)) {

      $href = "$base_path/?" .
        http_build_query(xhprof_array_set($url_params,
                                          'sort', $stat));
      $header = xhprof_render_link($desc, $href);
    } else {
      $header = $desc;
    }

    if ($stat == "fn")
      print("<th align=left><nobr>$header</th>");
    else print("<th " . $vwbar . "><nobr>$header</th>");
  }
  print("</tr>");

  print("<tr bgcolor='#e0e0ff'><td>");
  print("<b><i><center>Current Function</center></i></b>");
  print("</td></tr>");

  print("<tr>");
  // make this a self-reference to facilitate copy-pasting snippets to e-mails
  print("<td><a href=''>$rep_symbol</a>");
  print_source_link(array('fn' => $rep_symbol));
  print("</td>");

  if ($display_calls) {
    // Call Count
    print_td_num($symbol_info["ct"], $format_cbk["ct"]);
    print_td_pct($symbol_info["ct"], $totals["ct"]);
  }

  // Inclusive Metrics for current function
  foreach ($metrics as $metric) {
    print_td_num($symbol_info[$metric], $format_cbk[$metric], ($sort_col == $metric));
    print_td_pct($symbol_info[$metric], $totals[$metric], ($sort_col == $metric));
  }
  print("</tr>");

  print("<tr bgcolor='#ffffff'>");
  print("<td style='text-align:right;color:blue'>"
        ."Exclusive Metrics $diff_text for Current Function</td>");

  if ($display_calls) {
    // Call Count
    print("<td $vbar></td>");
    print("<td $vbar></td>");
  }

  // Exclusive Metrics for current function
  foreach ($metrics as $metric) {
    print_td_num($symbol_info["excl_" . $metric], $format_cbk["excl_" . $metric],
                 ($sort_col == $metric),
                 get_tooltip_attributes("Child", $metric));
    print_td_pct($symbol_info["excl_" . $metric], $symbol_info[$metric],
                 ($sort_col == $metric),
                 get_tooltip_attributes("Child", $metric));
  }
  print("</tr>");

  // list of callers/parent functions
  $results = array();
  if ($display_calls) {
    $base_ct = $symbol_info["ct"];
  } else {
    $base_ct = 0;
  }
  foreach ($metrics as $metric) {
    $base_info[$metric] = $symbol_info[$metric];
  }
  foreach ($run_data as $parent_child => $info) {
    list($parent, $child) = xhprof_parse_parent_child($parent_child);
    if (($child == $rep_symbol) && ($parent)) {
      $info_tmp = $info;
      $info_tmp["fn"] = $parent;
      $results[] = $info_tmp;
    }
  }
  usort($results, 'sort_cbk');

  if (count($results) > 0) {
    print_pc_array($url_params, $results, $base_ct, $base_info, true,
                   $run1, $run2);
  }

  // list of callees/child functions
  $results = array();
  $base_ct = 0;
  foreach ($run_data as $parent_child => $info) {
    list($parent, $child) = xhprof_parse_parent_child($parent_child);
    if ($parent == $rep_symbol) {
      $info_tmp = $info;
      $info_tmp["fn"] = $child;
      $results[] = $info_tmp;
      if ($display_calls) {
        $base_ct += $info["ct"];
      }
    }
  }
  usort($results, 'sort_cbk');

  if (count($results)) {
    print_pc_array($url_params, $results, $base_ct, $base_info, false,
                   $run1, $run2);
  }

  print("</table>");

  // These will be used for pop-up tips/help.
  // Related javascript code is in: xhprof_report.js
  print("\n");
  print('<script language="javascript">' . "\n");
  print("var func_name = '\"" . $rep_symbol . "\"';\n");
  print("var total_child_ct  = " . $base_ct . ";\n");
  if ($display_calls) {
    print("var func_ct   = " . $symbol_info["ct"] . ";\n");
  }
  print("var func_metrics = new Array();\n");
  print("var metrics_col  = new Array();\n");
  print("var metrics_desc  = new Array();\n");
  if ($diff_mode) {
    print("var diff_mode = true;\n");
  } else {
    print("var diff_mode = false;\n");
  }
  $column_index = 3; // First three columns are Func Name, Calls, Calls%
  foreach ($metrics as $metric) {
    print("func_metrics[\"" . $metric . "\"] = " . round($symbol_info[$metric]) . ";\n");
    print("metrics_col[\"". $metric . "\"] = " . $column_index . ";\n");
    print("metrics_desc[\"". $metric . "\"] = \"" . $possible_metrics[$metric][2] . "\";\n");

    // each metric has two columns..
    $column_index += 2;
  }
  print('</script>');
  print("\n");

}

