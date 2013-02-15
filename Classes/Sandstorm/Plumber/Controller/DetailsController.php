<?php
namespace Sandstorm\Plumber\Controller;

/*                                                                        *
 * This script belongs to the FLOW3 package "Sandstorm.Plumber".          *
 *                                                                        *
 * It is free software; you can redistribute it and/or modify it under    *
 * the terms of the GNU General Public License, either version 3          *
 * of the License, or (at your option) any later version.                 *
 *                                                                        *
 * The TYPO3 project - inspiring people to share!                         *
 *                                                                        */
use TYPO3\Flow\Annotations as Flow;

/**
 * Standard controller for the Sandstorm.Plumber package
 *
 * @Flow\Scope("singleton")
 */
class DetailsController extends AbstractController {

	/**
	 *
	 * @param string $file1
	 * @param string $file2
	 */
	public function timelineAction($file1, $file2 = NULL) {
		$profile = $this->getProfile($file1);
		$this->view->assign('numberOfProfiles', $file2 === NULL ? 1 : 2);
		$this->view->assign('profile', $profile);
		$this->view->assign('file1', $file1);

		$js = $this->buildJavaScriptForProfile($profile, 0);

		if ($file2 !== NULL) {
			$profile2 = $this->getProfile($file2);
			$this->view->assign('profile2', $profile2);
			$this->view->assign('file2', $file2);
			$js .= $this->buildJavaScriptForProfile($profile2, 1);
		}
		$this->view->assign('js', $js);
	}

	protected function buildJavaScriptForProfile($profile, $eventSourceIndex) {
		$javaScript = array();
		foreach ($profile->getTimersAsDuration() as $event) {
			$javaScript[] = sprintf('timelineRunner.addEvent(%s, new Timeline.DefaultEventSource.Event({
				start: new Date(%s),
				end:  new Date(%s),
				durationEvent: true,
				caption: %s,
				description: %s,
				color: "#%s"
			}));', $eventSourceIndex, (int)($event['start'] * 1000), (int)($event['stop'] * 1000), json_encode($event['name']), json_encode($event['data']), $this->getColorForEventName($event['name']));
		}

		foreach ($profile->getTimestamps() as $event) {
			$javaScript[] = sprintf('timelineRunner.addEvent(%s, new Timeline.DefaultEventSource.Event({
				start: new Date(%s),
				durationEvent: false,
				text: %s,
				caption: %s,
				description: %s,
				color: "#%s"
			}));', $eventSourceIndex, (int)($event['time'] * 1000), json_encode($event['name']), json_encode($event['name']), json_encode($event['data']), $this->getColorForEventName($event['name']));
		}

		$memory = $profile->getMemory();
		foreach ($memory as &$mem) {
			$mem['time'] = (int)($mem['time'] * 1000);
		}
		$javaScript[] = sprintf('timelineRunner.setMemory(%s, %s);', $eventSourceIndex, json_encode($memory));

		return implode("\n", $javaScript);
	}

	/**
	 * If given an event name without a group (i.e. like "Routing"), this
	 * method will deterministically calculate a color value from the string.
	 *
	 * If given an event name with a group (i.e. like "MVC: Routing" or "MVC: Controller"),
	 * we want to make sure that the group is *roughly* having the same color. That's why
	 * we take the group title ("MVC"), calculate a base color from it, and then
	 * darken or lighten this color using the remaining string.
	 *
	 * @param type $name
	 * @return type
	 */
	protected function getColorForEventName($name) {
		$parts = explode(':', $name);
		if (count($parts) > 1) {
			$firstElementHash = sha1(array_shift($parts));
			$restHash = substr(sha1(implode(':', $parts)), 0, 6);
			$steps = (hexdec($restHash) % 256) - 128;

			$rHex = $firstElementHash[0] . $firstElementHash[1];
			$gHex = $firstElementHash[2] . $firstElementHash[3];
			$bHex = $firstElementHash[4] . $firstElementHash[5];

			$r = hexdec($rHex);
			$g = hexdec($gHex);
			$b = hexdec($bHex);

			$r = max(0, min(255, $r + $steps));
			$g = max(0, min(255, $g + $steps));
			$b = max(0, min(255, $b + $steps));

			return str_pad(dechex($r), 2, '0') . str_pad(dechex($g), 2, '0') . str_pad(dechex($b), 2, '0');
		} else {
			return substr(sha1($name), 0, 6);
		}
	}

	/**
	 * @param string $run
	 */
	public function xhprofDebugAction($run) {
		$profile = $this->getProfile($run);
		\TYPO3\Flow\var_dump($profile->getXhprofTrace());
		return '';
	}
	/**
	 * @param string $run
	 */
	public function xhprofAction($run) {
		$profile = $this->getProfile($run);

		require_once XHPROF_ROOT . '/classes/xhprof_ui.php';
		require_once XHPROF_ROOT . '/classes/xhprof_ui/config.php';
		require_once XHPROF_ROOT . '/classes/xhprof_ui/compute.php';
		require_once XHPROF_ROOT . '/classes/xhprof_ui/utils.php';
		require_once XHPROF_ROOT . '/classes/xhprof_ui/run.php';
		require_once XHPROF_ROOT . '/classes/xhprof_ui/report/driver.php';
		require_once XHPROF_ROOT . '/classes/xhprof_ui/report/single.php';

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
			$xhprof_config, FLOW_PATH_DATA . '/Logs/Profiles'
		);
		$report = $xhprof_ui->generate_report();

		ob_start();

		$report->render();

		$contents = ob_get_contents();
		$contents = str_replace('<tbody', '<tbody class="list"', $contents);
		ob_end_clean();

		$this->view->assign('run', $run);
		$this->view->assign('contents', $contents);
	}
}
?>