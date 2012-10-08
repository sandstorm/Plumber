<?php 

class XHProf_UI {
	
	public $params = array();
	public $dir = '';
	
	public $config;
	
	public $runs = array();
	
	// default column to sort on -- wall time
	public $sort = 'wt';

	// default is 'single run' report
	public $diff_mode = false;

	// call count data present?
	public $display_calls = true;

	// drill to down to a fn
	public $fn = null;

	// columns that'll be displayed in a top-level report
	public $stats = array();

	// columns that'll be displayed in a function's parent/child report
	public $pc_stats = array();

	// Various total counts
	public $totals = array();

	/*
	* The subset of $possible_metrics that is present in the raw profile data.
	*/
	public $metrics = null;

	function __construct($params, XHProf_UI\Config $config, $dir = null) {
		$this->params = XHProf_UI\Utils::parse_params($params);
		
	    // if user hasn't passed a directory location,
	    // we use the xhprof.output_dir ini setting
	    // if specified, else we default to the directory
	    // in which the error_log file resides.
		if (
			(!empty($dir) && !is_dir($dir)) || 
			(empty($dir) && !is_dir($dir = ini_get('xhprof.output_dir')))
		) {
			throw new Exception('Warning: Must specify directory location for XHProf runs. '.
				'You can either pass the directory location as an argument to the constructor or set xhprof.output_dir ini param.');
		}

		$this->config = $config;

		$this->dir = $dir;
	}
	
	/**
	 * Generate a XHProf Display View given the various params
	 *
	 */
	function generate_report() {
		extract($this->params, EXTR_SKIP);

		// specific run to display?
		if ($run) {
			// run may be a single run or a comma separate list of runs
			// that'll be aggregated. If "wts" (a comma separated list
			// of integral weights is specified), the runs will be
			// aggregated in that ratio.
			foreach (explode(',', $run) as $run_id) {
				$this->runs[] = new XHProf_UI\Run($this->dir, $namespace, $run_id);
			}

			if ($compare) {
				$this->runs = array(
					$this->runs[0],
					new XHProf_UI\Run($this->dir, $namespace, $compare)
				);

				if (($data1 = $this->runs[0]->get_data()) && ($data2 = $this->runs[1]->get_data())) {
					$this->_setup_metrics($data2);

					return new XHProf_UI\Report\Diff(&$this, $data1, $data2);
				}
				
			} elseif (count($this->runs) == 1) {
				if ($data = $this->runs[0]->get_data()) {
					$this->_setup_metrics($data);

					return new XHProf_UI\Report\Single(&$this, $data);
				}

			} else {
				$wts = strlen($wts) > 0 ? explode(',', $wts) : null;

				if ($data = Compute::aggregate_runs(&$this, $this->runs, $wts)) {
					$this->_setup_metrics($data);

					return new XHProf_UI\Report\Single(&$this, $data);
				}
			}
		}

		return false;
	}
	
	
	public function url(array $params = array()) {
		return '?'.http_build_query(array_filter(array_merge($this->params, $params)));
	}



	protected function _setup_metrics($data) {
		extract($this->params, EXTR_SKIP);
		
		$this->fn = XHProf_UI\Utils::safe_fn($fn);

		if (!empty($sort)) {
			if (array_key_exists($sort, $this->config->sortable_columns)) {
				$this->sort = $sort;
			} else {
				throw new Exception("Invalid Sort Key $sort specified in URL");
			}
		}

		// For C++ profiler runs, walltime attribute isn't present.
		// In that case, use "samples" as the default sort column.
		if (!isset($data['main()']['wt'])) {
			if ($this->sort == 'wt') {
				$this->sort = 'samples';
			}

			// C++ profiler data doesn't have call counts.
			// ideally we should check to see if "ct" metric
			// is present for "main()". But currently "ct"
			// metric is artificially set to 1. So, relying
			// on absence of "wt" metric instead.
			$this->display_calls = false;
		}

		// parent/child report doesn't support exclusive times yet.
		// So, change sort hyperlinks to closest fit.
		if (!empty($fn)) {
			$this->sort = str_replace('excl_', '', $this->sort);
		}

		$this->pc_stats = $this->stats = $this->display_calls ? array('fn', 'ct') : array('fn');

		foreach ($this->config->possible_metrics as $metric => $desc) {
			if (isset($data['main()'][$metric])) {
				$this->metrics[] = $metric;

				// flat (top-level reports): we can compute
				// exclusive metrics reports as well.
				$this->stats[] = $metric;
				// $this->stats[] = "I" . $desc[0] . "%";
				$this->stats[] = "excl_" . $metric;
				// $this->stats[] = "E" . $desc[0] . "%";

				// parent/child report for a function: we can
				// only breakdown inclusive times correctly.
				$this->pc_stats[] = $metric;
				// $this->pc_stats[] = "I" . $desc[0] . "%";
			}
		}
	}



	/**
	* Takes raw XHProf data that was aggregated over "$num_runs" number
	* of runs averages/nomalizes the data. Essentially the various metrics
	* collected are divided by $num_runs.
	*/
	protected function normalize_metrics($raw_data, $num_runs) {

		if (empty($raw_data) || ($num_runs == 0)) {
			return $raw_data;
		}

		$raw_data_total = array();

		if (isset($raw_data["==>main()"]) && isset($raw_data["main()"])) {
			xhprof_error("XHProf Error: both ==>main() and main() set in raw data...");
		}

		foreach ($raw_data as $parent_child => $info) {
			foreach ($info as $metric => $value) {
				$raw_data_total[$parent_child][$metric] = ($value / $num_runs);
			}
		}

		return $raw_data_total;
	}

}