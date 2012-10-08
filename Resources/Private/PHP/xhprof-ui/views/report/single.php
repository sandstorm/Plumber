		<h2>Report of run <a href="<?php echo $ui->url();?>">#<?php echo $ui->runs[0]->run_id;?></a></h3>
<?php

?>
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
				<tr class="spacer">
					<td>#<?php echo $ui->runs[0]->run_id?> summary:</td>
<?php if ($ui->display_calls) {?>
					<td colspan="2" class="center"><?php echo number_format($ui->totals[0]['ct']);?></td>
<?php }?>
<?php foreach ($ui->metrics as $metric) {?>
					<td colspan="2" class="center"><?php echo number_format($ui->totals[0][$metric]).' '.$ui->config->possible_metrics[$metric][1];?></td>
					<td colspan="2" class="center">&ndash;</td>
<?php }?>
				</tr>
<?php 
foreach ($data as $info) {
?>
			<tr><td class="fn"><a href="<?php echo $ui->url(array('fn' => XHProf_UI\Utils::safe_fn($info['fn'])));?>"><?php echo $info['fn'];?></a></td><?php
	if ($ui->display_calls) {
		echo XHProf_UI\Utils::td_num($info['ct'], $ui->config->format_cbk['ct'], ($ui->sort == 'ct'));
		echo XHProf_UI\Utils::td_pct($info['ct'], $ui->totals[0]['ct'], ($ui->sort == 'ct'));
	}

	foreach ($ui->metrics as $metric) {
		// Inclusive metric
		echo XHProf_UI\Utils::td_num($info[$metric], $ui->config->format_cbk[$metric], ($ui->sort == $metric));
		echo XHProf_UI\Utils::td_pct($info[$metric], $ui->totals[0][$metric], ($ui->sort == $metric));

		// Exclusive Metric
		echo XHProf_UI\Utils::td_num($info['excl_'.$metric], $ui->config->format_cbk['excl_' . $metric], ($ui->sort == 'excl_' . $metric));
		echo XHProf_UI\Utils::td_pct($info['excl_'.$metric], $ui->totals[0][$metric], ($ui->sort == 'excl_' . $metric));
	}
?></tr>
<?php
}
?>
			</tbody>
		</table>
