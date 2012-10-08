<?php

$run1_url = $ui->url(array('run' => $ui->runs[0]->run_id, 'compare' => null));
$run2_url = $ui->url(array('run' => $ui->runs[1]->run_id, 'compare' => null));
$switch_url = $ui->url(array('run' => $ui->runs[1]->run_id, 'compare' => $ui->runs[0]->run_id));

?>
		<h2>Diff Report of runs <a href="<?php echo $run1_url;?>">#<?php echo $ui->runs[0]->run_id;?></a> and <a href="<?php echo $run2_url;?>">#<?php echo $ui->runs[1]->run_id;?></a> [<a href="<?php echo $switch_url;?>">Switch</a>]</h3>
		<table id="stats" class="zebra-striped">
<?php foreach (array('thead', 'tfoot') as $t) {?>
			<?php echo "<$t>";?> 
				<tr>
<?php 
foreach ($ui->stats as $stat) {
	$desc = $ui->config->descriptions[$stat];

	if (array_key_exists($stat, $ui->config->sortable_columns)) {
		$header = '<a href="'.$ui->url(array('sort' => $stat)).'">'.$desc.'</a>';
	} else {
		$header = $desc;
	}
?>
					<th class="<?php if ($ui->sort == $stat) echo 'headerSortUp blue';?>"<?php if ($stat != 'fn') echo ' colspan="2"';?>><?php echo $header;?></th>
<?php 
}
?>
				</tr>
			<?php echo "</$t>";?> 
<?php }?>
			<tbody>
<?php for ($i = 0; $i < count($ui->runs); $i++) {?>
				<tr>
					<td>#<?php echo $ui->runs[$i]->run_id?> summary:</td>
<?php 	if ($ui->display_calls) {?>
					<td colspan="2" class="center"><?php echo number_format($ui->totals[$i]['ct']);?></td>
<?php 	}?>
<?php 	foreach ($ui->metrics as $metric) {?>
					<td colspan="2" class="center"><?php echo number_format($ui->totals[$i][$metric]).' '.$ui->config->possible_metrics[$metric][1];?></td>
					<td colspan="2" class="center">&ndash;</td>
<?php 	}?>
				</tr>
<?php }
?>
				<tr class="spacer"><td class="fn">Diff summary:</td><?php
		if ($ui->display_calls) {
			echo XHProf_UI\Utils::td_num($ui->totals[1]['ct'] - $ui->totals[0]['ct'], $ui->config->format_cbk['ct'], true, true);
			echo XHProf_UI\Utils::td_pct($ui->totals[1]['ct'] - $ui->totals[0]['ct'], $ui->totals[0]['ct'], true, true);
		}

		foreach ($ui->metrics as $metric) {
			// Inclusive metric
			echo XHProf_UI\Utils::td_num($ui->totals[1][$metric] - $ui->totals[0][$metric], $ui->config->format_cbk[$metric], true, true);
			echo XHProf_UI\Utils::td_pct($ui->totals[1][$metric] - $ui->totals[0][$metric], $ui->totals[0][$metric], true, true);

			echo '<td colspan="2" class="center">&ndash;</td>';
		}
	?></tr>
<?php 

foreach ($data_delta as $info) {
?>
			<tr><td class="fn"><a href="<?php echo $ui->url(array('fn' => XHProf_UI\Utils::safe_fn($info['fn'])));?>"><?php echo $info['fn'];?></a></td><?php
	if ($ui->display_calls) {
		echo XHProf_UI\Utils::td_num($info['ct'], $ui->config->format_cbk['ct'], ($ui->sort == 'ct'), true);
		echo XHProf_UI\Utils::td_pct($info['ct'], $ui->totals[2]['ct'], ($ui->sort == 'ct'), true);
	}

	foreach ($ui->metrics as $metric) {
		// Inclusive metric
		echo XHProf_UI\Utils::td_num($info[$metric], $ui->config->format_cbk[$metric], ($ui->sort == $metric), true);
		echo XHProf_UI\Utils::td_pct($info[$metric], $ui->totals[2][$metric], ($ui->sort == $metric), true);

		// Exclusive Metric
		echo XHProf_UI\Utils::td_num($info['excl_'.$metric], $ui->config->format_cbk['excl_' . $metric], ($ui->sort == 'excl_' . $metric), true);
		echo XHProf_UI\Utils::td_pct($info['excl_'.$metric], $ui->totals[2][$metric], ($ui->sort == 'excl_' . $metric), true);
	}
?></tr>
<?php 
}
?>
			</tbody>
		</table>
<?php