<?php

$colspan = (1 + ($ui->display_calls ? 2 : 0) + count($ui->metrics) * 2);

?>
		<h2>Parent/Child report for <strong><?php echo $ui->fn;?></strong> (Run <a href="<?php echo $ui->url(array('fn' => null));?>">#<?php echo $ui->runs[0]->run_id;?></a>)</h3>
		<table id="stats" class="zebra-striped">
<?php foreach (array('thead', 'tfoot') as $t) {?>
			<?php echo "<$t>";?> 
				<tr>
<?php 
foreach ($ui->pc_stats as $stat) {
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
				<tr><th colspan="<?php echo $colspan;?>">Current Function</th></tr>
				<tr><td><a href="<?php echo $ui->url(array('fn' => $ui->fn));?>"><?php echo htmlentities($ui->fn);?></a></td><?php
if ($ui->display_calls) {
	echo XHProf_UI\Utils::td_num($data[$ui->fn]['ct'], $ui->config->format_cbk['ct'], ($ui->sort == 'ct'));
	echo XHProf_UI\Utils::td_pct($data[$ui->fn]['ct'], $ui->totals['ct'], ($ui->sort == 'ct'));
}

foreach ($ui->metrics as $metric) {
	// Inclusive metric
	echo XHProf_UI\Utils::td_num($data[$ui->fn][$metric], $ui->config->format_cbk[$metric], ($ui->sort == $metric));
	echo XHProf_UI\Utils::td_pct($data[$ui->fn][$metric], $ui->totals[$metric], ($ui->sort == $metric));
}

?></tr>
				<tr><td>Exclusive Metrics for Current Function</td><?php
if ($ui->display_calls) {
	echo '<td class="right">&ndash;</td><td class="right">&ndash;</td>';
}
foreach ($ui->metrics as $metric) {
	// Inclusive metric
	echo XHProf_UI\Utils::td_num($data[$ui->fn]['excl_'.$metric], $ui->config->format_cbk['excl_'.$metric], ($ui->sort == $metric), 'Child', $metric);
	echo XHProf_UI\Utils::td_pct($data[$ui->fn]['excl_'.$metric], $ui->totals[$metric], ($ui->sort == $metric), 'Child', $metric);
}

?></tr> 
<?php 

// list of callers/parent functions
$results = array('parent' => array(), 'child' => array());
$base_ct = array(
	'parent' => ($ui->display_calls ? $data[$ui->fn]['ct'] : 0),
	'child' => 0,
);

$base_info = array();
foreach ($ui->metrics as $metric) {
	$base_info[$metric] = $data[$ui->fn][$metric];
}

foreach ($raw_data as $parent_child => $info) {
	list($parent, $child) = XHProf_UI\Compute::parse_parent_child($parent_child);

	if (($child == $ui->fn) && ($parent)) {
		$results['parent'][] = $info + array('fn' => $parent);
	}

	if ($parent == $ui->fn) {
		$results['child'][] = $info + array('fn' => $child);
		$base_ct['child'] += $info['ct'];
	}
}


foreach (array('parent', 'child') as $pc) {
	\XHProf_UI\Utils::sort($results[$pc], $ui);
	
	if (count($results[$pc]) > 0) {
?>
				<tr><th colspan="<?php echo $colspan;?>"><?php echo ucfirst($pc);?> function<?php echo count($results[$pc]) == 1 ? '' : 's';?></th></tr>
<?php
		foreach ($results[$pc] as $info) {
?>
				<tr><td><a href="<?php echo $ui->url(array('fn' => $info['fn']));?>"><?php echo htmlentities($info['fn']);?></a></td><?php
			if ($ui->display_calls) {
				echo XHProf_UI\Utils::td_num($info['ct'], $ui->config->format_cbk['ct'], ($ui->sort == 'ct'));
				echo XHProf_UI\Utils::td_pct($info['ct'], $base_ct[$pc], ($ui->sort == 'ct'));
			}
			foreach ($ui->metrics as $metric) {
				// Inclusive metric
				echo XHProf_UI\Utils::td_num($info[$metric], $ui->config->format_cbk[$metric], ($ui->sort == $metric));
				echo XHProf_UI\Utils::td_pct($info[$metric], $pc == 'parent' ? $ui->totals[$metric] : $base_info[$metric], ($ui->sort == $metric));
			}
	?></tr>
<?php 
		}
	}
}
?>
			</tbody>
		</table>
