<?php 
namespace XHProf_UI\Report;

use XHProf_UI\Utils, 
	XHProf_UI\Config, 
	XHProf_UI\Metrics,
	XHProf_UI\Compute;

class Diff {

	public function __construct(\XHProf_UI &$ui, array $raw_data1, array $raw_data2) {
		$ui->diff_mode = true;

		if (!empty($ui->fn)) {
			$raw_data1 = Compute::trim_run($raw_data1, array($ui->fn));
			$raw_data2 = Compute::trim_run($raw_data2, array($ui->fn));
		}

		$delta = Compute::diff($ui, $raw_data1, $raw_data2);

		$data1 = Compute::flat_info(&$ui, $raw_data1);
		$data2 = Compute::flat_info(&$ui, $raw_data2);
		$data_delta = Compute::flat_info(&$ui, $delta);


		// data tables
		// if (!empty($symbol)) {
		// 		$info1 = isset($symbol_tab1[$rep_symbol]) ? $symbol_tab1[$rep_symbol] : null;
		// 		$info2 = isset($symbol_tab2[$rep_symbol]) ?	$symbol_tab2[$rep_symbol] : null;
		// 		symbol_report($url_params, $run_delta, $symbol_tab[$rep_symbol], $sort, $rep_symbol, $run1, $info1, $run2, $info2);
		// 
		// }

		foreach($data_delta as $fn => &$info) {
			$info = $info + array('fn' => $fn);
		}
		uasort($data_delta, function($a, $b) use ($ui) {
			return Utils::sort_cbk($a, $b, $ui);
		});

			
		$this->_ui = $ui;
		$this->_data = array($raw_data1, $raw_data2, $data_delta, $data1, $data2);
	}

	public function render() {
		$ui = $this->_ui;
		list($raw_data1, $raw_data2, $data_delta, $data1, $data2) = $this->_data;

		if (!empty($ui->fn)) {
			include XHPROF_ROOT.'/views/report/diff_fn.php';
		} else {
			include XHPROF_ROOT.'/views/report/diff.php';
		}
	}

	
}