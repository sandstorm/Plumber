<?php 
namespace XHProf_UI;

class Config {

	public $possible_metrics =  array(
		'wt'      => array('Wall', '&micro;s', 'walltime'),
		'ut'      => array('User', '&micro;s', 'user cpu time'),
		'st'      => array('Sys', '&micro;s', 'system cpu time'),
		'cpu'     => array('Cpu', '&micro;s', 'cpu time'),
		'mu'      => array('MUse', 'bytes', 'memory usage'),
		'pmu'     => array('PMUse', 'bytes', 'peak memory usage'),
		'samples' => array('Samples', 'samples', 'cpu time')
	);

	// The following column headers are sortable
	public $sortable_columns = array(
		'fn'           => 1,
		'ct'           => 1,
		'wt'           => 1,
		'excl_wt'      => 1,
		'ut'           => 1,
		'excl_ut'      => 1,
		'st'           => 1,
		'excl_st'      => 1,
		'mu'           => 1,
		'excl_mu'      => 1,
		'pmu'          => 1,
		'excl_pmu'     => 1,
		'cpu'          => 1,
		'excl_cpu'     => 1,
		'samples'      => 1,
		'excl_samples' => 1
	);

	// Textual descriptions for column headers in 'single run' mode
	public $descriptions = array(
		'fn'           => 'Function Name',
		'ct'           => 'Calls',

		'wt'           => 'Inc. Wall Time (&micro;s)',
		'excl_wt'      => 'Ex. Wall Time (&micro;s)',

		'ut'           => 'Inc. User (&micro;s)',
		'excl_ut'      => 'Ex. User (&micro;s)',

		'st'           => 'Inc. Sys (&micro;s)',
		'excl_st'      => 'Ex. Sys (&micro;s)',

		'cpu'          => 'Inc. CPU (&micro;s)',
		'excl_cpu'     => 'Ex. CPU (&micro;s)',

		'mu'           => 'Incl. MemUse (bytes)',
		'excl_mu'      => 'Excl. MemUse (bytes)',

		'pmu'          => 'Incl. Peak MemUse (bytes)',
		'excl_pmu'     => 'Excl. Peak MemUse (bytes)',

		'samples'      => 'Incl. Samples',
		'excl_samples' => 'Excl. Samples',
	);

	// Formatting Callback Functions...
	public $format_cbk = array(
		'fn'           => '',
		'ct'           => array('XHProf_UI\Utils', 'count_format'),
		'Calls%'       => array('XHProf_UI\Utils', 'percent_format'),

		'wt'           => 'number_format',
		'IWall%'       => array('XHProf_UI\Utils', 'percent_format'),
		'excl_wt'      => 'number_format',
		'EWall%'       => array('XHProf_UI\Utils', 'percent_format'),

		'ut'           => 'number_format',
		'IUser%'       => array('XHProf_UI\Utils', 'percent_format'),
		'excl_ut'      => 'number_format',
		'EUser%'       => array('XHProf_UI\Utils', 'percent_format'),

		'st'           => 'number_format',
		'ISys%'        => array('XHProf_UI\Utils', 'percent_format'),
		'excl_st'      => 'number_format',
		'ESys%'        => array('XHProf_UI\Utils', 'percent_format'),

		'cpu'          => 'number_format',
		'ICpu%'        => array('XHProf_UI\Utils', 'percent_format'),
		'excl_cpu'     => 'number_format',
		'ECpu%'        => array('XHProf_UI\Utils', 'percent_format'),

		'mu'           => 'number_format',
		'IMUse%'       => array('XHProf_UI\Utils', 'percent_format'),
		'excl_mu'      => 'number_format',
		'EMUse%'       => array('XHProf_UI\Utils', 'percent_format'),

		'pmu'          => 'number_format',
		'IPMUse%'      => array('XHProf_UI\Utils', 'percent_format'),
		'excl_pmu'     => 'number_format',
		'EPMUse%'      => array('XHProf_UI\Utils', 'percent_format'),

		'samples'      => 'number_format',
		'ISamples%'    => array('XHProf_UI\Utils', 'percent_format'),
		'excl_samples' => 'number_format',
		'ESamples%'    => array('XHProf_UI\Utils', 'percent_format'),
	);

}