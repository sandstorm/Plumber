<?php
define('XHPROF_ROOT', realpath(__DIR__));

include XHPROF_ROOT.'/views/header.php';

if (!(bool)extension_loaded('xhprof')) {
?>
		<div id="no_xhprof" class="alert-message error">
			<p>You need to install <a href="http://www.mirror.facebook.net/facebook/xhprof/">XHProf</a> to use this feature.</p>
		</div>
<?php 
} else {	

	require_once XHPROF_ROOT.'/classes/class_loader.php';
	new Class_Loader('XHProf_UI', XHPROF_ROOT.'/classes');

	/**
	 * @param string  $source       Category/type of the run. The source in
	 *                              combination with the run id uniquely
	 *                              determines a profiler run.
	 *
	 * @param string  $run          run id, or comma separated sequence of
	 *                              run ids. The latter is used if an aggregate
	 *                              report of the runs is desired.
	 *
	 * @param string  $wts          Comma separate list of integers.
	 *                              Represents the weighted ratio in
	 *                              which which a set of runs will be
	 *                              aggregated. [Used only for aggregate
	 *                              reports.]
	 *
	 * @param string  $symbol       Function symbol. If non-empty then the
	 *                              parent/child view of this function is
	 *                              displayed. If empty, a flat-profile view
	 *                              of the functions is displayed.
	 *
	 * @param string  $run1         Base run id (for diff reports)
	 *
	 * @param string  $run2         New run id (for diff reports)
	 *
	 */
	$xhprof_config = new XHProf_UI\Config();

	$xhprof_ui = new XHProf_UI(
		array(
			'run'       => array(XHProf_UI\Utils::STRING_PARAM, ''),
			'compare'   => array(XHProf_UI\Utils::STRING_PARAM, ''),
			'wts'       => array(XHProf_UI\Utils::STRING_PARAM, ''),
			'fn'        => array(XHProf_UI\Utils::STRING_PARAM, ''),
			'sort'      => array(XHProf_UI\Utils::STRING_PARAM, 'wt'),
			'run1'      => array(XHProf_UI\Utils::STRING_PARAM, ''),
			'run2'      => array(XHProf_UI\Utils::STRING_PARAM, ''),
			'namespace' => array(XHProf_UI\Utils::STRING_PARAM, 'xhprof'),
			'all'       => array(XHProf_UI\Utils::UINT_PARAM, 0),
		),
		$xhprof_config
	);


	if (!$xhprof_report = $xhprof_ui->generate_report()) {
		echo "No XHProf runs specified in the URL.";

		XHProf_UI\Utils::list_runs($xhprof_ui->dir);
	}


	$xhprof_report->render();

}
include XHPROF_ROOT.'/views/footer.php';
