<?php
namespace SandstormMedia\PhpProfilerConnector\Controller;

/*                                                                        *
 * This script belongs to the FLOW3 package "SandstormMedia.PhpProfilerConnector".*
 *                                                                        *
 *                                                                        */

use TYPO3\FLOW3\Annotations as FLOW3;

/**
 * Standard controller for the SandstormMedia.PhpProfilerConnector package
 *
 * @FLOW3\Scope("singleton")
 */
class StandardController extends \TYPO3\FLOW3\MVC\Controller\ActionController {

	public function initializeAction() {
		\SandstormMedia\PhpProfiler\Profiler::getInstance()->stop();
	}

	/**
	 * Index action
	 *
	 * @return void
	 */
	public function indexAction() {
		$profiles = $this->getProfiles();

		$options = array();
		foreach ($profiles as $profile) {
			foreach ($profile->getOptions() as $optionName => $optionValue) {
				$options[$optionName] = $optionName;
			}
		}

		$this->view->assign('profiles', $profiles);
		$this->view->assign('options', $options);
	}

	public function removeAllAction() {
		$profiles = $this->getProfiles();

		foreach ($profiles as $profile) {
			$profile->remove();
		}
		$this->redirect('index');
	}

	/**
	 * @param string $file
	 * @param string $value
	 */
	public function updateTagsAction($file, $value) {
		$profile = $this->getProfile($file);
		$tags = \TYPO3\FLOW3\Utility\Arrays::trimExplode(',', $value);
		$profile->setTags($tags);
		$profile->save();
		$this->view->assign('tags', $tags);
	}

	/**
	 *
	 * @param string $run
	 */
	public function removeAction($run) {
		$profile = $this->getProfile($run);
		$profile->remove();
		$this->redirect('index');
	}

	/**
	 *
	 * @param string $file1
	 * @param string $file2
	 */
	public function showAction($file1, $file2 = NULL) {
		$profile = $this->getProfile($file1);
		$this->view->assign('numberOfProfiles', $file2===NULL ? 1 : 2);
		$this->view->assign('profile', $profile);
		$this->view->assign('file1', $file1);
		$this->view->assign('js', $this->buildJavaScriptForProfile($profile));

		if ($file2 !== NULL) {
			$profile2 = $this->getProfile($file2);
			$this->view->assign('profile2', $profile2);
			$this->view->assign('file2', $file2);
			$this->view->assign('js2', $this->buildJavaScriptForProfile($profile2));
		}
	}

	protected function getProfile($file) {
		$file = FLOW3_PATH_DATA . 'Logs/Profiles/' . $file;
		$profile = unserialize(file_get_contents($file));
		$profile->setFullPath($file);
		return $profile;
	}

	protected function buildJavaScriptForProfile($profile) {
		$javaScript = array();
		foreach ($profile->getTimersAsDuration() as $event) {
			$javaScript[] = sprintf('eventSource.add(new Timeline.DefaultEventSource.Event({
				start: new Date(%s),
				end:  new Date(%s),
				durationEvent: true,
				caption: "%s",
				description: %s,
				color: "#%s"
			}));', (int)($event['start']*1000), (int)($event['stop']*1000), $event['name'], json_encode($event['data']), substr(sha1($event['name']), 0, 6));
		}

		foreach ($profile->getTimestamps() as $event) {
			$javaScript[] = sprintf('eventSource.add(new Timeline.DefaultEventSource.Event({
				start: new Date(%s),
				durationEvent: false,
				text: "%s",
				caption: "%s",
				description: %s,
				color: "#%s"
			}));', (int)($event['time']*1000), $event['name'], $event['name'], json_encode($event['data']), substr(sha1($event['name']), 0, 6));
		}

		return implode("\n", $javaScript);
	}

	public function getProfiles() {
		$directoryIterator = new \DirectoryIterator(FLOW3_PATH_DATA . 'Logs/Profiles');

		$profiles = array();
		foreach ($directoryIterator as $element) {
			if (preg_match('/\.profile$/', $element->getFilename())) {
				$profiles[$element->getFilename()] = unserialize(file_get_contents($element->getPathname()));
				$profiles[$element->getFilename()]->setFullPath($element->getPathname());
			}

		}
		return $profiles;
	}

	/**
	 * @param string $run
	 */
	public function xhprofAction($run) {
		$profile = $this->getProfile($run);

		require_once XHPROF_ROOT.'/classes/xhprof_ui.php';
		require_once XHPROF_ROOT.'/classes/xhprof_ui/config.php';
		require_once XHPROF_ROOT.'/classes/xhprof_ui/compute.php';
		require_once XHPROF_ROOT.'/classes/xhprof_ui/utils.php';
		require_once XHPROF_ROOT.'/classes/xhprof_ui/run.php';
		require_once XHPROF_ROOT.'/classes/xhprof_ui/report/driver.php';
		require_once XHPROF_ROOT.'/classes/xhprof_ui/report/single.php';


		error_reporting(0);
		$xhprof_config = new \XHProf_UI\Config();

		$xhprof_ui = new \XHProf_UI(
			array(
				'run'       => array(\XHProf_UI\Utils::STRING_PARAM, ''),
				'compare'   => array(\XHProf_UI\Utils::STRING_PARAM, ''),
				'wts'       => array(\XHProf_UI\Utils::STRING_PARAM, ''),
				'fn'        => array(\XHProf_UI\Utils::STRING_PARAM, ''),
				'sort'      => array(\XHProf_UI\Utils::STRING_PARAM, 'wt'),
				'run1'      => array(\XHProf_UI\Utils::STRING_PARAM, ''),
				'run2'      => array(\XHProf_UI\Utils::STRING_PARAM, ''),
				'namespace' => array(\XHProf_UI\Utils::STRING_PARAM, 'xhprof'),
				'all'       => array(\XHProf_UI\Utils::UINT_PARAM, 0),
			),
			$xhprof_config, FLOW3_PATH_DATA . '/Logs/Profiles'
		);
		$report = $xhprof_ui->generate_report();

		ob_start();

		$report->render();

		$contents = ob_get_contents();
		ob_end_clean();


		$this->view->assign('run', $run);
		$this->view->assign('contents', $contents);
	}

}
?>